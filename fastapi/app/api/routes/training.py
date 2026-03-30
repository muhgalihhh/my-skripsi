"""
Training Routes
Endpoints for model training (BERTopic & LDA).
"""

from typing import List, Optional

import pandas as pd
from app.core.config import path_settings
from app.ml.hyperparameters import (BERTOPIC_PRESETS, LDA_PRESETS,
                                    get_bertopic_preset, get_lda_preset)
from app.models.schemas import (BERTopicHyperparameters, LDAHyperparameters,
                                ModelType, TrainingRequest,
                                TrainingResultResponse, TrainingStatus,
                                TrainingStatusResponse)
from app.services.training import TrainingService
from app.core import database

from fastapi import APIRouter, BackgroundTasks, HTTPException

router = APIRouter(prefix="/training", tags=["Training"])
service = TrainingService()


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
        # Load processed data from MySQL database
        df = database.load_processed_data_from_db()
        
        if df.empty:
            raise ValueError(
                "Processed data not found in DB. Run preprocessing first via /api/v1/preprocessing/start"
            )

        # Clean dataframe to prevent NoneType errors in embeddings
        df = df.dropna(subset=['cleaned_text', 'processed_text'])
        df['cleaned_text'] = df['cleaned_text'].astype(str)
        df['processed_text'] = df['processed_text'].astype(str)
        df = df[(df['cleaned_text'].str.strip() != '') & (df['processed_text'].str.strip() != '')]

        if df.empty:
            raise ValueError("No valid text data found after dropping empty records.")

        timestamps = df["year"].tolist() if "year" in df.columns else None

        if model_type == ModelType.BERTOPIC:
            # BERTopic needs cleaned (non-stemmed) text for IndoSBERT embeddings
            documents = df["cleaned_text"].tolist()
            service.train_bertopic(
                job_id=job_id,
                documents=documents,
                timestamps=timestamps,
                params=bertopic_params,
            )
        elif model_type == ModelType.LDA:
            # LDA needs fully preprocessed (stemmed) text
            documents = df["processed_text"].tolist()
            service.train_lda(
                job_id=job_id,
                documents=documents,
                timestamps=timestamps,
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
        results = service.load_results(job_id)
        return results
    except FileNotFoundError:
        raise HTTPException(
            status_code=404,
            detail=f"Results not found for job {job_id}. "
                   f"Job might still be running or hasn't been started.",
        )


# ============================================
# Hyperparameter Presets
# ============================================

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
