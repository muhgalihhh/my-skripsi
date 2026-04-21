"""Evaluation routes for model comparison and DTA."""

import numpy as np
import pandas as pd
from app.models.schemas import (DTARequest, DTAResponse, ModelType, TopicTrend,
                                TrendDirection)
from app.services.pipeline import pipeline_service
from app.services.training import TrainingService

from fastapi import APIRouter, HTTPException

router = APIRouter(prefix="/evaluation", tags=["Evaluation"])
service = TrainingService()
# Keep in sync with notebook_tuning_unified DTA rule.
DTA_TREND_THRESHOLD = 0.10
DTA_MIN_POINTS = 3
TOP_WORDS_PREVIEW_LIMIT = 15


def _load_bertopic_trainer(job_id: str):
    """Load BERTopic trainer/model for an existing training job."""
    from app.ml.bertopic_trainer import BERTopicTrainer

    try:
        trainer = BERTopicTrainer()
        trainer.load_model(job_id)
        return trainer
    except FileNotFoundError:
        raise HTTPException(
            status_code=404,
            detail=f"BERTopic model not found for job {job_id}",
        )


def _compute_topics_over_time(
    trainer,
    request: DTARequest,
    documents: list[str],
    years: list[int],
) -> pd.DataFrame:
    """Run BERTopic topics_over_time with DB-backed pipeline payload."""
    timestamps = pd.to_datetime(pd.Series(years).astype(str) + "-01-01")

    try:
        return trainer.model.topics_over_time(
            docs=documents,
            timestamps=timestamps,
            evolution_tuning=request.evolution_tuning,
            global_tuning=request.global_tuning,
        )
    except Exception as e:
        raise HTTPException(
            status_code=500,
            detail=f"DTA computation failed: {str(e)}",
        )


def _classify_topic_trends(
    trainer,
    topics_over_time: pd.DataFrame,
    year_range: list[int],
) -> tuple[list[TopicTrend], list[TopicTrend], list[TopicTrend]]:
    """Classify BERTopic trend direction via linear regression slope."""
    emerging, declining, stable = [], [], []

    topic_ids = [tid for tid in topics_over_time["Topic"].unique() if tid != -1]

    for topic_id in topic_ids:
        topic_data = topics_over_time[topics_over_time["Topic"] == topic_id].sort_values("Timestamp")

        # Keep parameter for API compatibility; notebook slope does not impute missing years.
        _ = year_range

        # Frequency per year
        freq_per_year: dict[str, float] = {}
        for _, row in topic_data.iterrows():
            yr = (
                str(row["Timestamp"].year)
                if hasattr(row["Timestamp"], "year")
                else str(row["Timestamp"])[:4]
            )
            freq_per_year[yr] = freq_per_year.get(yr, 0.0) + float(row["Frequency"])

        # Match notebook: regress on ordered raw frequency points from topics_over_time.
        y_vals = pd.to_numeric(topic_data["Frequency"], errors="coerce").fillna(0.0).to_numpy(dtype=float)
        n_points = len(y_vals)

        if n_points < DTA_MIN_POINTS:
            slope = float("nan")
            relative_slope = float("nan")
            direction = TrendDirection.STABLE
        else:
            x = np.arange(n_points, dtype=float)
            slope = float(np.polyfit(x, y_vals, 1)[0])
            baseline = max(float(np.mean(y_vals)), 1.0)
            relative_slope = float(slope / baseline)

            if relative_slope >= DTA_TREND_THRESHOLD and float(y_vals[-1]) >= float(y_vals[0]):
                direction = TrendDirection.EMERGING
            elif relative_slope <= -DTA_TREND_THRESHOLD and float(y_vals[-1]) <= float(y_vals[0]):
                direction = TrendDirection.DECLINING
            else:
                direction = TrendDirection.STABLE

        # Get top words
        try:
            top_words = [w for w, _ in trainer.model.get_topic(topic_id)]
        except Exception:
            top_words = []

        trend = TopicTrend(
            topic_id=int(topic_id),
            topic_label=f"Topic {topic_id}",
            top_words=top_words[:TOP_WORDS_PREVIEW_LIMIT],
            trend=direction,
            frequency_per_year=freq_per_year,
            trend_slope=round(relative_slope, 4) if np.isfinite(relative_slope) else None,
        )

        if direction == TrendDirection.EMERGING:
            emerging.append(trend)
        elif direction == TrendDirection.DECLINING:
            declining.append(trend)
        else:
            stable.append(trend)

    return emerging, declining, stable


@router.post("/dta", response_model=DTAResponse)
async def dynamic_topic_analysis(request: DTARequest):
    """
    Dynamic Topic Analysis — track topic evolution over time.

    Uses BERTopic.topics_over_time() to compute topic frequency
    per year, then classifies each topic as emerging, declining, or stable
    based on linear regression slope.
    """
    trainer = _load_bertopic_trainer(request.job_id)

    try:
        payload = pipeline_service.build_dta_payload(
            year_start=request.year_start,
            year_end=request.year_end,
        )
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))

    topics_over_time = _compute_topics_over_time(
        trainer=trainer,
        request=request,
        documents=payload.documents,
        years=payload.years,
    )

    emerging, declining, stable = _classify_topic_trends(
        trainer=trainer,
        topics_over_time=topics_over_time,
        year_range=payload.year_range,
    )

    topic_ids = [tid for tid in topics_over_time["Topic"].unique() if tid != -1]

    return DTAResponse(
        job_id=request.job_id,
        model_type=ModelType.BERTOPIC,
        total_topics=len(topic_ids),
        year_range=payload.year_range,
        emerging_topics=emerging,
        declining_topics=declining,
        stable_topics=stable,
        topics_over_time_raw=topics_over_time.to_dict(orient="records") if len(topics_over_time) < 5000 else None,
    )
