"""
Training Routes
Endpoints for model training (BERTopic & LDA).
"""

import re
import math
from pathlib import Path
from typing import List, Optional

import pandas as pd
from app.core import database
from app.core.config import path_settings
from app.ml.evaluator import TopicEvaluator
from app.ml.hyperparameters import BERTOPIC_PRESETS, LDA_PRESETS
from app.models.schemas import (BERTopicHyperparameters, LDAHyperparameters,
                                ModelType, TrainingRequest,
                                TrainingStatus, TrainingStatusResponse)
from app.services.pipeline import pipeline_service
from app.services.training import TrainingService

from fastapi import APIRouter, BackgroundTasks, HTTPException
from fastapi.responses import FileResponse

router = APIRouter(prefix="/training", tags=["Training"])
service = TrainingService()


_JOB_ID_RE = re.compile(r"^[A-Za-z0-9_-]{1,64}$")


def _validate_job_id(job_id: str) -> str:
    if not _JOB_ID_RE.match(job_id):
        raise HTTPException(status_code=400, detail="Invalid job_id")
    return job_id


def _run_training_job(
    job_id: str,
    model_type: ModelType,
    bertopic_params: Optional[BERTopicHyperparameters],
    lda_params: Optional[LDAHyperparameters],
):
    """
    Background task for model training.

    Pemilihan kolom:
      - BERTopic → 'cleaned_text' (teks bersih tanpa stemming, untuk IndoSBERT)
      - LDA      → 'processed_text' (tokenized + stemmed + stopword removed)
    """
    try:
        # Load and validate pipeline dataset from DB snapshot produced by preprocessing.
        df = pipeline_service.load_training_dataset()
        payload = pipeline_service.build_training_payload(df=df, model_type=model_type)

        if model_type == ModelType.BERTOPIC:
            service.train_bertopic(
                job_id=job_id,
                documents=payload.documents,
                timestamps=payload.timestamps,
                document_ids=payload.document_ids,
                params=bertopic_params,
            )
        elif model_type == ModelType.LDA:
            service.train_lda(
                job_id=job_id,
                documents=payload.documents,
                timestamps=payload.timestamps,
                params=lda_params,
            )

    except Exception as e:
        service._update_job(
            job_id,
            status=TrainingStatus.FAILED.value,
            message=f"Training failed: {str(e)}",
            error=str(e),
        )


@router.post("/start", response_model=TrainingStatusResponse)
async def start_training(
    request: TrainingRequest,
    background_tasks: BackgroundTasks,
):
    """
    Start a model training job (runs in background).

    - Choose model_type: 'bertopic' or 'lda'
    - Optionally provide custom hyperparameters
    - Returns a job_id to track progress
    """
    # Create job
    job_id = service.create_job(
        model_type=request.model_type,
        description=request.description or "",
    )

    # Start training in background
    background_tasks.add_task(
        _run_training_job,
        job_id=job_id,
        model_type=request.model_type,
        bertopic_params=request.bertopic_params,
        lda_params=request.lda_params,
    )

    return TrainingStatusResponse(
        job_id=job_id,
        status=TrainingStatus.PENDING,
        model_type=request.model_type,
        progress=0.0,
        message="Training job created and queued",
    )


@router.get("/status/{job_id}", response_model=TrainingStatusResponse)
async def get_training_status(job_id: str):
    """Get the status of a training job."""
    job = service.get_job(job_id)
    if job is None:
        raise HTTPException(status_code=404, detail=f"Job {job_id} not found")

    return TrainingStatusResponse(
        job_id=job["job_id"],
        status=TrainingStatus(job["status"]),
        model_type=ModelType(job["model_type"]),
        progress=job["progress"],
        message=job["message"],
        started_at=job.get("started_at"),
        completed_at=job.get("completed_at"),
        error=job.get("error"),
    )


@router.get("/jobs", response_model=List[TrainingStatusResponse])
async def list_training_jobs():
    """List all training jobs."""
    jobs = service.list_jobs()
    return [
        TrainingStatusResponse(
            job_id=j["job_id"],
            status=TrainingStatus(j["status"]),
            model_type=ModelType(j["model_type"]),
            progress=j["progress"],
            message=j["message"],
            started_at=j.get("started_at"),
            completed_at=j.get("completed_at"),
            error=j.get("error"),
        )
        for j in jobs
    ]


@router.get("/results/{job_id}")
async def get_training_results(job_id: str):
    """Get full training results for a completed job."""
    try:
        _validate_job_id(job_id)
        results = service.load_results(job_id)
        return results
    except FileNotFoundError:
        raise HTTPException(
            status_code=404,
            detail=f"Results not found for job {job_id}. "
                   f"Job might still be running or hasn't been started.",
        )


@router.get("/model/{job_id}/download")
async def download_trained_model(job_id: str):
    """Download the archived trained model (.tar.gz) for a job.

    The archive is stored under the results directory as `model_<job_id>.tar.gz`.
    """

    _validate_job_id(job_id)

    results_dir = path_settings.get_results_dir()
    archive_path = results_dir / f"model_{job_id}.tar.gz"

    # If not present, try to create it from the saved model directory.
    if not archive_path.exists():
        model_path = None
        try:
            results = service.load_results(job_id)
            model_path = results.get("model_path")
        except Exception:
            model_path = None

        # Fallback guesses (in case results JSON isn't available)
        if not model_path:
            bertopic_dir = path_settings.get_models_dir() / f"bertopic_{job_id}"
            lda_dir = path_settings.get_models_dir() / f"lda_{job_id}"
            if bertopic_dir.exists():
                model_path = str(bertopic_dir)
            elif lda_dir.exists():
                model_path = str(lda_dir)

        created = service._archive_model_artifacts(job_id=job_id, model_path=model_path)
        if created is None or not archive_path.exists():
            raise HTTPException(
                status_code=404,
                detail=(
                    f"Model archive not found for job {job_id}. "
                    "Make sure training has completed successfully."
                ),
            )

    # Ensure we only serve files from the results directory
    try:
        archive_path.resolve().relative_to(results_dir.resolve())
    except Exception:
        raise HTTPException(status_code=400, detail="Invalid archive path")

    return FileResponse(
        path=str(archive_path),
        media_type="application/gzip",
        filename=f"model_{job_id}.tar.gz",
    )


@router.get("/model/{job_id}/test")
async def test_trained_model(job_id: str):
    """Lightweight smoke-test for a trained BERTopic model.

    Loads the model from disk (if present) and returns a small summary.
    Does NOT run embeddings/inference on new documents.

    Note: This endpoint is BERTopic-only.
    """

    _validate_job_id(job_id)

    results: dict = {}
    try:
        results = service.load_results(job_id)
    except Exception:
        results = {}

    model_type = (results.get("model_type") or "").lower()
    model_path = results.get("model_path")
    if not model_path:
        # Guess model directory (BERTopic only)
        bertopic_dir = path_settings.get_models_dir() / f"bertopic_{job_id}"
        if bertopic_dir.exists():
            model_path = str(bertopic_dir)
            model_type = "bertopic"

    model_dir = Path(str(model_path)) if model_path else None

    if not model_dir or not model_dir.exists():
        raise HTTPException(status_code=404, detail=f"Model directory not found for job {job_id}")

    summary = {
        "job_id": job_id,
        "model_type": model_type or None,
        "model_path": str(model_dir),
    }

    # BERTopic smoke test (only)
    if model_type != "bertopic" and not (model_dir / "model").exists():
        raise HTTPException(
            status_code=400,
            detail="Only BERTopic is supported for model testing in this API.",
        )

    if (model_dir / "model").exists():
        from app.services.stopwords import load_stopwords
        from app.ml.bertopic_trainer import BERTopicTrainer

        trainer = BERTopicTrainer()
        trainer.load_model(job_id)

        info = trainer.model.get_topic_info()
        topic_ids = [int(t) for t in info["Topic"].tolist() if int(t) != -1]
        stopwords = load_stopwords("indonesian", include_academic=True)

        sample_topics = []
        for tid in topic_ids[:5]:
            words_scores = trainer.model.get_topic(tid) or []
            filtered = [(w, s) for (w, s) in words_scores if w not in stopwords and len(w) >= 3]
            if not filtered:
                filtered = words_scores
            sample_topics.append({
                "topic_id": tid,
                "top_words": [w for w, _ in filtered[:10]],
            })

        summary.update(
            {
                "model_type": "bertopic",
                "num_topics": len(topic_ids),
                "sample_topics": sample_topics,
            }
        )

        return summary

    raise HTTPException(status_code=404, detail=f"BERTopic model artifacts not found for job {job_id}")


@router.get("/model/{job_id}/test-dataset")
async def test_trained_model_with_dataset(job_id: str):
    """Re-test a saved BERTopic model against the existing dataset in DB.

    This endpoint loads the trained model from disk, loads the current
    preprocessed dataset from MySQL, re-computes evaluation metrics,
    and compares them against the metrics stored at training time.

        Notes:
            - Re-test does NOT re-run embeddings/inference; it evaluates coherence/diversity
                based on model topic words and dataset tokens.
            - Results may differ if the dataset in DB has changed since training.
            - This endpoint is BERTopic-only.
    """

    _validate_job_id(job_id)

    try:
        stored_results = service.load_results(job_id)
    except FileNotFoundError:
        raise HTTPException(status_code=404, detail=f"Results not found for job {job_id}")

    model_type = (stored_results.get("model_type") or "").lower()
    if model_type and model_type != "bertopic":
        raise HTTPException(
            status_code=400,
            detail="Only BERTopic is supported for dataset re-test in this API.",
        )

    model_path = stored_results.get("model_path")
    if not model_path:
        # Guess model directory (BERTopic only)
        bertopic_dir = path_settings.get_models_dir() / f"bertopic_{job_id}"
        if bertopic_dir.exists():
            model_path = str(bertopic_dir)
            model_type = "bertopic"

    if not model_type:
        model_type = "bertopic"

    # Load dataset from DB (same source as training)
    df = database.load_processed_data_from_db()
    total = int(len(df))
    if total == 0:
        raise HTTPException(status_code=400, detail="Dataset in DB is empty. Run preprocessing first.")

    before = len(df)
    df = df.dropna(subset=["cleaned_text", "processed_text"])
    df["cleaned_text"] = df["cleaned_text"].astype(str)
    df["processed_text"] = df["processed_text"].astype(str)
    df = df[(df["cleaned_text"].str.strip() != "") & (df["processed_text"].str.strip() != "")]
    used = int(len(df))
    dropped = int(before - used)
    if used == 0:
        raise HTTPException(status_code=400, detail="No valid records after cleaning empty texts.")

    year_min = None
    year_max = None
    if "year" in df.columns:
        try:
            years = pd.to_numeric(df["year"], errors="coerce").dropna().astype(int)
            if not years.empty:
                year_min = int(years.min())
                year_max = int(years.max())
        except Exception:
            year_min = None
            year_max = None

    evaluator = TopicEvaluator()
    stored_metrics = stored_results.get("metrics") or {}

    tolerance = 0.0001

    def _delta(a, b):
        try:
            return round(float(a) - float(b), 6)
        except Exception:
            return None

    def _sanitize_json(value):
        """Recursively convert NaN/Inf floats to None for JSON compliance."""
        if isinstance(value, float):
            return value if math.isfinite(value) else None
        if isinstance(value, dict):
            return {k: _sanitize_json(v) for k, v in value.items()}
        if isinstance(value, list):
            return [_sanitize_json(v) for v in value]
        return value

    # Compare topic keywords (using the same stopword-filtering strategy as trainers)
    def _keyword_match_ratio(stored_topic_info, current_topic_info, topn: int = 10) -> float:
        try:
            stored_map = {
                int(t.get("topic_id")): list(t.get("top_words") or [])[:topn]
                for t in (stored_topic_info or [])
                if t.get("topic_id") is not None
            }
            current_map = {
                int(t.get("topic_id")): list(t.get("top_words") or [])[:topn]
                for t in (current_topic_info or [])
                if t.get("topic_id") is not None
            }
        except Exception:
            return 0.0

        if not stored_map or not current_map:
            return 0.0

        common_ids = sorted(set(stored_map.keys()) & set(current_map.keys()))
        if not common_ids:
            return 0.0

        matches = 0
        for tid in common_ids:
            if stored_map[tid] == current_map[tid]:
                matches += 1

        return round(matches / len(common_ids), 4)

    # BERTopic re-test (only)
    from app.services.stopwords import load_stopwords
    from app.ml.bertopic_trainer import BERTopicTrainer

    documents = [d for d in df["cleaned_text"].tolist() if isinstance(d, str) and d.strip()]
    if not documents:
        raise HTTPException(status_code=400, detail="No valid cleaned_text documents for BERTopic")

    trainer = BERTopicTrainer()
    trainer.load_model(job_id)

    hyper = stored_results.get("hyperparameters") or {}
    top_n_words = int(hyper.get("top_n_words", 10))
    coherence_type = str(hyper.get("coherence_type", "c_v"))
    coherence_tokenization = str(hyper.get("coherence_tokenization", "vectorizer"))
    coherence_dict_no_below = int(hyper.get("coherence_dict_no_below", 3))
    coherence_dict_no_above = float(hyper.get("coherence_dict_no_above", 0.95))

    retest_metrics = evaluator.evaluate_bertopic(
        model=trainer.model,
        documents=documents,
        vectorizer_model=getattr(trainer.model, "vectorizer_model", None),
        coherence_type=coherence_type,
        coherence_tokenization=coherence_tokenization,
        coherence_dict_no_below=coherence_dict_no_below,
        coherence_dict_no_above=coherence_dict_no_above,
        top_n_words=top_n_words,
    )

    stopwords = load_stopwords("indonesian", include_academic=True)
    current_topic_info = []
    for topic_id in trainer.model.get_topics().keys():
        if int(topic_id) == -1:
            continue
        words_scores = trainer.model.get_topic(int(topic_id)) or []
        filtered = [(w, s) for (w, s) in words_scores if w not in stopwords and len(w) >= 3]
        if not filtered:
            filtered = words_scores
        current_topic_info.append({
            "topic_id": int(topic_id),
            "top_words": [w for w, _ in filtered[:10]],
        })

    keyword_match = _keyword_match_ratio(
        stored_topic_info=stored_results.get("topic_info"),
        current_topic_info=current_topic_info,
        topn=top_n_words,
    )

    return _sanitize_json({
            "job_id": job_id,
            "model_type": "bertopic",
            "model_path": model_path,
            "dataset": {
                "total": total,
                "used": used,
                "dropped": dropped,
                "year_min": year_min,
                "year_max": year_max,
            },
            "stored_metrics": stored_metrics,
            "retest_metrics": retest_metrics,
            "delta": {
                "coherence_cv": _delta(retest_metrics.get("coherence_cv"), stored_metrics.get("coherence_cv")),
                "topic_diversity": _delta(retest_metrics.get("topic_diversity"), stored_metrics.get("topic_diversity")),
                "num_topics": _delta(retest_metrics.get("num_topics"), stored_metrics.get("num_topics")),
            },
            "same": {
                "coherence_cv": abs(float(retest_metrics.get("coherence_cv", 0)) - float(stored_metrics.get("coherence_cv", 0))) <= tolerance
                if stored_metrics.get("coherence_cv") is not None
                else None,
                "topic_diversity": abs(float(retest_metrics.get("topic_diversity", 0)) - float(stored_metrics.get("topic_diversity", 0))) <= tolerance
                if stored_metrics.get("topic_diversity") is not None
                else None,
                "num_topics": int(retest_metrics.get("num_topics", 0)) == int(stored_metrics.get("num_topics", 0))
                if stored_metrics.get("num_topics") is not None
                else None,
                "keyword_match_ratio": keyword_match,
            },
        })



@router.get("/dataset/summary")
async def get_training_dataset_summary():
    """
    Return a quick summary of the processed dataset in DB.

    This is used by the Laravel UI to show readiness before training:
    - total rows loaded
    - rows valid for BERTopic (cleaned_text non-empty)
    - rows valid for LDA (processed_text non-empty)
    - year range
    """
    return pipeline_service.summarize_training_dataset()

@router.get("/presets/bertopic")
async def get_bertopic_presets():
    """Get available BERTopic hyperparameter presets."""
    return {
        name: params.model_dump()
        for name, params in BERTOPIC_PRESETS.items()
    }


@router.get("/presets/lda")
async def get_lda_presets():
    """Get available LDA hyperparameter presets."""
    return {
        name: params.model_dump()
        for name, params in LDA_PRESETS.items()
    }
