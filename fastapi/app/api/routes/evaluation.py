"""
Evaluation & Comparison Routes
Endpoints for evaluating / comparing models and Dynamic Topic Analysis (DTA).
"""

import numpy as np
import pandas as pd
from app.core.config import dta_settings, path_settings
from app.models.schemas import (ComparisonRequest, ComparisonResponse,
                                DTARequest, DTAResponse, ModelType, TopicTrend,
                                TrendDirection)
from app.services.training import TrainingService

from fastapi import APIRouter, HTTPException

router = APIRouter(prefix="/evaluation", tags=["Evaluation"])
service = TrainingService()


@router.post("/compare", response_model=ComparisonResponse)
async def compare_models(request: ComparisonRequest):
    """
    Compare BERTopic vs LDA based on their training results.

    Requires two completed training job IDs (one BERTopic, one LDA).
    """
    try:
        bertopic_results = service.load_results(request.bertopic_job_id)
        lda_results = service.load_results(request.lda_job_id)
    except FileNotFoundError as e:
        raise HTTPException(status_code=404, detail=str(e))

    bertopic_metrics = bertopic_results.get("metrics", {})
    lda_metrics = lda_results.get("metrics", {})

    b_coh = bertopic_metrics.get("coherence_cv", 0.0)
    l_coh = lda_metrics.get("coherence_cv", 0.0)
    b_div = bertopic_metrics.get("topic_diversity", 0.0)
    l_div = lda_metrics.get("topic_diversity", 0.0)

    # Build summary
    coherence_winner = "BERTopic" if b_coh > l_coh else "LDA"
    diversity_winner = "BERTopic" if b_div > l_div else "LDA"
    summary = (
        f"Coherence (C_v): BERTopic={b_coh:.4f} vs LDA={l_coh:.4f} → {coherence_winner} wins. "
        f"Diversity: BERTopic={b_div:.4f} vs LDA={l_div:.4f} → {diversity_winner} wins."
    )

    return ComparisonResponse(
        bertopic_coherence=b_coh,
        lda_coherence=l_coh,
        bertopic_diversity=b_div,
        lda_diversity=l_div,
        bertopic_num_topics=bertopic_results.get("num_topics", 0),
        lda_num_topics=lda_results.get("num_topics", 0),
        summary=summary,
    )


# ============================================
# Dynamic Topic Analysis (DTA)
# ============================================

@router.post("/dta", response_model=DTAResponse)
async def dynamic_topic_analysis(request: DTARequest):
    """
    Dynamic Topic Analysis — track topic evolution over time.

    Uses BERTopic.topics_over_time() to compute topic frequency
    per year, then classifies each topic as emerging, declining, or stable
    based on linear regression slope.
    """
    from app.ml.bertopic_trainer import BERTopicTrainer

    # Load trained BERTopic model
    try:
        trainer = BERTopicTrainer()
        trainer.load_model(request.job_id)
    except FileNotFoundError:
        raise HTTPException(
            status_code=404,
            detail=f"BERTopic model not found for job {request.job_id}",
        )

    # Load processed data
    processed_path = path_settings.get_processed_data_dir() / "processed_data.csv"
    if not processed_path.exists():
        raise HTTPException(
            status_code=404,
            detail="Processed data not found. Run preprocessing first.",
        )

    df = pd.read_csv(processed_path, encoding="utf-8")

    if "year" not in df.columns:
        raise HTTPException(
            status_code=400,
            detail="Data does not contain a 'year' column for DTA.",
        )

    # Filter by year range
    df_filtered = df[
        (df["year"] >= request.year_start)
        & (df["year"] <= request.year_end)
    ].copy()

    if len(df_filtered) == 0:
        raise HTTPException(
            status_code=400,
            detail=f"No documents found in year range {request.year_start}-{request.year_end}",
        )

    # BERTopic needs cleaned_text
    documents = df_filtered["cleaned_text"].tolist()
    # Convert year to timestamps (datetime for BERTopic)
    timestamps = pd.to_datetime(df_filtered["year"].astype(str) + "-01-01")

    try:
        topics_over_time = trainer.model.topics_over_time(
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

    # Classify topic trends
    year_range = list(range(request.year_start, request.year_end + 1))
    emerging, declining, stable = [], [], []

    topic_ids = [tid for tid in topics_over_time["Topic"].unique() if tid != -1]

    for topic_id in topic_ids:
        topic_data = topics_over_time[topics_over_time["Topic"] == topic_id]

        # Frequency per year
        freq_per_year = {}
        for _, row in topic_data.iterrows():
            yr = str(row["Timestamp"].year) if hasattr(row["Timestamp"], "year") else str(row["Timestamp"])[:4]
            freq_per_year[yr] = float(row["Frequency"])

        # Fill missing years with 0
        for yr in year_range:
            if str(yr) not in freq_per_year:
                freq_per_year[str(yr)] = 0.0

        # Compute slope via linear regression
        years_arr = np.array(sorted(int(y) for y in freq_per_year.keys()), dtype=float)
        freqs_arr = np.array([freq_per_year[str(int(y))] for y in years_arr], dtype=float)

        if len(years_arr) >= 2 and freqs_arr.sum() > 0:
            slope = float(np.polyfit(years_arr, freqs_arr, 1)[0])
        else:
            slope = 0.0

        # Get top words
        try:
            top_words = [w for w, _ in trainer.model.get_topic(topic_id)]
        except Exception:
            top_words = []

        # Classify
        threshold = 0.5  # adjustable
        if slope > threshold:
            direction = TrendDirection.EMERGING
        elif slope < -threshold:
            direction = TrendDirection.DECLINING
        else:
            direction = TrendDirection.STABLE

        trend = TopicTrend(
            topic_id=int(topic_id),
            topic_label=f"Topic {topic_id}",
            top_words=top_words[:10],
            trend=direction,
            frequency_per_year=freq_per_year,
            trend_slope=round(slope, 4),
        )

        if direction == TrendDirection.EMERGING:
            emerging.append(trend)
        elif direction == TrendDirection.DECLINING:
            declining.append(trend)
        else:
            stable.append(trend)

    return DTAResponse(
        job_id=request.job_id,
        model_type=ModelType.BERTOPIC,
        total_topics=len(topic_ids),
        year_range=year_range,
        emerging_topics=emerging,
        declining_topics=declining,
        stable_topics=stable,
        topics_over_time_raw=topics_over_time.to_dict(orient="records") if len(topics_over_time) < 5000 else None,
    )
