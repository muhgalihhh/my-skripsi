"""
Training Routes
Endpoints for model training (BERTopic & LDA).
"""

import re
import math
import json
from datetime import datetime
from pathlib import Path
from typing import List, Optional, Tuple

import pandas as pd
from app.core import database
from app.core.config import path_settings
from app.ml.evaluator import TopicEvaluator
from app.ml.hyperparameters import BERTOPIC_PRESETS
from app.models.schemas import (BERTopicHyperparameters, LDAHyperparameters,
                                ModelType, TrainingRequest,
                                TitleRecommendationRequest,
                                TitleRecommendationResponse,
                                TopicCurationSuggestionRequest,
                                TopicCurationSuggestionResponse,
                                TopicInferenceBatchItem,
                                TopicInferenceBatchRequest,
                                TopicInferenceBatchResponse,
                                TopicInferenceItem,
                                TopicInferenceRequest,
                                TopicInferenceResponse, TrainingStatus,
                                TrainingStatusResponse)
from app.services.gemini_title_recommendation import (
    GeminiTitleRecommendationError,
    gemini_title_recommendation_service,
)
from app.services.pipeline import pipeline_service
from app.services.gemini_topic_curation import (
    GeminiTopicCurationError,
    gemini_topic_curation_service,
)
from app.services.training import TrainingService
from loguru import logger

from fastapi import APIRouter, BackgroundTasks, File, Form, HTTPException, UploadFile
from fastapi.responses import FileResponse, JSONResponse

router = APIRouter(prefix="/training", tags=["Training"])
service = TrainingService()


_JOB_ID_RE = re.compile(r"^[A-Za-z0-9_-]{1,64}$")
TOP_WORDS_PREVIEW_LIMIT = 15


def _validate_job_id(job_id: str) -> str:
    if not _JOB_ID_RE.match(job_id):
        raise HTTPException(status_code=400, detail="Invalid job_id")
    return job_id


def _parse_iso_datetime(value: object) -> Optional[datetime]:
    if not isinstance(value, str) or value.strip() == "":
        return None

    try:
        return datetime.fromisoformat(value)
    except Exception:
        return None


def _has_bertopic_model_artifact(job_id: str) -> bool:
    model_path = path_settings.get_models_dir() / f"bertopic_{job_id}" / "model"
    if model_path.exists():
        return True

    try:
        results = service.load_results(job_id)
    except Exception:
        results = {}

    result_model_dir_raw = results.get("model_path")
    if not result_model_dir_raw:
        return False

    try:
        result_model_dir = Path(str(result_model_dir_raw))
    except Exception:
        return False

    return result_model_dir.exists() and (result_model_dir / "model").exists()


def _resolve_bertopic_job_for_inference(requested_job_id: Optional[str]) -> Tuple[str, str]:
    """Resolve BERTopic job_id for inference.

    Priority:
    1) explicitly requested job_id
    2) latest completed BERTopic job from persisted job state
    3) latest BERTopic result_*.json fallback
    """

    if requested_job_id is not None:
        return _validate_job_id(requested_job_id), "requested_job_id"

    jobs = service.list_jobs()
    candidates: List[Tuple[datetime, str]] = []

    for job in jobs:
        model_type = str(job.get("model_type") or "").lower()
        status = str(job.get("status") or "").lower()
        job_id = str(job.get("job_id") or "").strip()

        if model_type != ModelType.BERTOPIC.value:
            continue
        if status != TrainingStatus.COMPLETED.value:
            continue
        if not job_id:
            continue

        completed_at = _parse_iso_datetime(job.get("completed_at"))
        started_at = _parse_iso_datetime(job.get("started_at"))
        sort_key = completed_at or started_at
        if sort_key is None:
            continue

        candidates.append((sort_key, job_id))

    candidates.sort(key=lambda item: item[0], reverse=True)

    for _, job_id in candidates:
        if _has_bertopic_model_artifact(job_id):
            return job_id, "latest_completed_job"

    # Fallback for older runs where job-state is missing but result JSON still exists.
    results_dir = path_settings.get_results_dir()
    result_files = sorted(
        results_dir.glob("result_*.json"),
        key=lambda path: path.stat().st_mtime,
        reverse=True,
    )

    for result_file in result_files:
        try:
            payload = json.loads(result_file.read_text(encoding="utf-8"))
        except Exception:
            continue

        model_type = str(payload.get("model_type") or "").lower()
        if model_type != ModelType.BERTOPIC.value:
            continue

        job_id = str(payload.get("job_id") or result_file.stem.replace("result_", "")).strip()
        if not job_id:
            continue

        if _has_bertopic_model_artifact(job_id):
            return job_id, "latest_results_fallback"

    raise HTTPException(
        status_code=404,
        detail=(
            "Belum ada model BERTopic siap inferensi. "
            "Silakan jalankan training BERTopic minimal satu kali."
        ),
    )


def _extract_bertopic_params_payload(payload: dict) -> dict:
    if not isinstance(payload, dict):
        raise HTTPException(
            status_code=422,
            detail="JSON config harus berupa object/dict.",
        )

    candidates: List[dict] = []

    if isinstance(payload.get("best_bertopic"), dict):
        best_block = payload.get("best_bertopic") or {}
        if isinstance(best_block.get("params"), dict):
            candidates.append(best_block["params"])
        elif isinstance(best_block.get("hyperparameters"), dict):
            candidates.append(best_block["hyperparameters"])

    if isinstance(payload.get("bertopic_params"), dict):
        candidates.append(payload["bertopic_params"])

    if isinstance(payload.get("params"), dict):
        candidates.append(payload["params"])

    if isinstance(payload.get("bertopic"), dict):
        candidates.append(payload["bertopic"])

    if not candidates:
        raise HTTPException(
            status_code=422,
            detail=(
                "JSON config tidak berisi params BERTopic. "
                "Gunakan kunci: best_bertopic.params, bertopic_params, params, atau bertopic."
            ),
        )

    params_payload = candidates[0]
    if not isinstance(params_payload, dict):
        raise HTTPException(
            status_code=422,
            detail="Format params BERTopic harus object/dict.",
        )

    return params_payload


def _coerce_bertopic_params(payload: dict, source: str) -> Optional[BERTopicHyperparameters]:
    if not isinstance(payload, dict) or not payload:
        return None

    try:
        return BERTopicHyperparameters(**payload)
    except Exception as exc:
        logger.warning("Ignoring invalid BERTopic params from {}: {}", source, str(exc))
        return None


def _coerce_lda_params(payload: dict, source: str) -> Optional[LDAHyperparameters]:
    if not isinstance(payload, dict) or not payload:
        return None

    try:
        return LDAHyperparameters(**payload)
    except Exception as exc:
        logger.warning("Ignoring invalid LDA params from {}: {}", source, str(exc))
        return None


def _resolve_bertopic_params_for_start(request: TrainingRequest) -> tuple[BERTopicHyperparameters, str]:
    if request.user_id is not None:
        source = f"topic_model_settings(user_id={int(request.user_id)})"
        try:
            settings = database.get_topic_model_settings(int(request.user_id))
        except Exception as exc:
            logger.warning("Failed to load {}: {}", source, str(exc))
            settings = None

        from_settings = _coerce_bertopic_params(
            (settings or {}).get("bertopic_params") or {},
            source,
        )
        if from_settings is not None:
            if request.bertopic_params is not None:
                logger.info("Ignoring request BERTopic params; using DB params from {}", source)
            return from_settings, source

        logger.warning("No valid BERTopic params found in {}. Falling back to schema defaults", source)
        return BERTopicHyperparameters(), "schema_default"

    if request.bertopic_params is not None:
        return request.bertopic_params, "request"

    return BERTopicHyperparameters(), "schema_default"


def _resolve_lda_params_for_start(request: TrainingRequest) -> tuple[LDAHyperparameters, str]:
    if request.user_id is not None:
        source = f"topic_model_settings(user_id={int(request.user_id)})"
        try:
            settings = database.get_topic_model_settings(int(request.user_id))
        except Exception as exc:
            logger.warning("Failed to load {}: {}", source, str(exc))
            settings = None

        from_settings = _coerce_lda_params((settings or {}).get("lda_params") or {}, source)
        if from_settings is not None:
            if request.lda_params is not None:
                logger.info("Ignoring request LDA params; using DB params from {}", source)
            return from_settings, source

        logger.warning("No valid LDA params found in {}. Falling back to schema defaults", source)
        return LDAHyperparameters(), "schema_default"

    if request.lda_params is not None:
        return request.lda_params, "request"

    return LDAHyperparameters(), "schema_default"


def _tokenize_query_for_keyword_fallback(text: str) -> List[str]:
    tokens = re.findall(r"[a-zA-Z0-9_]+", text.lower())
    stopwords = {
        "dan", "yang", "untuk", "dengan", "pada", "dari", "atau", "the", "of", "in", "to",
        "di", "ke", "sebagai", "dalam", "analisis", "studi", "berbasis", "menggunakan",
    }
    return [token for token in tokens if len(token) >= 3 and token not in stopwords]


def _is_recommendation_prompt_in_context(
    prompt: str,
    topic_keywords: List[str],
    mapped_titles: Optional[List[str]] = None,
) -> bool:
    prompt_tokens = set(_tokenize_query_for_keyword_fallback(prompt))
    if not prompt_tokens:
        return False

    context_anchor_tokens = {
        "skripsi",
        "judul",
        "penelitian",
        "riset",
        "topik",
        "metode",
        "analisis",
        "model",
        "sistem",
        "dataset",
        "algoritma",
        "klasifikasi",
        "prediksi",
        "deteksi",
        "optimasi",
    }

    has_anchor = bool(prompt_tokens.intersection(context_anchor_tokens))

    topic_context_tokens = set()
    for keyword in topic_keywords or []:
        topic_context_tokens.update(_tokenize_query_for_keyword_fallback(keyword))

    for title in (mapped_titles or [])[:20]:
        topic_context_tokens.update(_tokenize_query_for_keyword_fallback(title))

    overlap_count = len(prompt_tokens.intersection(topic_context_tokens))

    return overlap_count >= 1 or has_anchor


def _build_keyword_fallback_distribution(trainer, query: str, top_n_topics: int) -> List[TopicInferenceItem]:
    query_tokens = _tokenize_query_for_keyword_fallback(query)
    query_token_set = set(query_tokens)

    scored_topics: List[tuple[int, float, List[str]]] = []

    for raw_topic_id in (trainer.model.get_topics() or {}).keys():
        topic_id = int(raw_topic_id)
        if topic_id == -1:
            continue

        words_scores = trainer.model.get_topic(topic_id) or []
        top_words = [str(word) for word, _ in words_scores[:TOP_WORDS_PREVIEW_LIMIT]]
        topic_words_lower = [word.lower() for word in top_words]

        if query_token_set:
            exact_hits = len(query_token_set.intersection(topic_words_lower))
            partial_hits = 0
            for token in query_token_set:
                if any(token in word or word in token for word in topic_words_lower):
                    partial_hits += 1

            score = ((exact_hits * 1.0) + (partial_hits * 0.4)) / float(len(query_token_set))
            score = max(0.0, min(1.0, score))
            if score > 0:
                scored_topics.append((topic_id, score, top_words))

    # If no lexical overlap, fallback to most frequent topics.
    if not scored_topics:
        try:
            info = trainer.model.get_topic_info()
            for _, row in info.iterrows():
                topic_id = int(row.get("Topic", -1))
                if topic_id == -1:
                    continue

                words_scores = trainer.model.get_topic(topic_id) or []
                top_words = [str(word) for word, _ in words_scores[:TOP_WORDS_PREVIEW_LIMIT]]
                doc_count = int(row.get("Count", 0) or 0)
                score = min(1.0, max(0.05, math.log1p(max(doc_count, 1)) / 10.0))
                scored_topics.append((topic_id, score, top_words))
        except Exception as e:
            logger.warning("Keyword fallback topic_info extraction failed: {}", str(e))

    scored_topics.sort(key=lambda item: item[1], reverse=True)

    return [
        TopicInferenceItem(
            topic_id=topic_id,
            similarity=round(float(similarity), 6),
            top_words=top_words,
        )
        for topic_id, similarity, top_words in scored_topics[:top_n_topics]
    ]


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
    job = service.get_job(job_id)
    if job and bool(job.get("cancel_requested")):
        logger.info("Skip training job {} because cancellation was already requested", job_id)
        return

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
        current_job = service.get_job(job_id)
        if current_job and bool(current_job.get("cancel_requested")):
            logger.info("Ignore exception for cancelled job {}: {}", job_id, str(e))
            return

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

    - Model yang didukung: 'bertopic' dan 'lda'
    - Optionally provide custom hyperparameters sesuai model
    - Returns a job_id to track progress
    """

    if request.user_id is None:
        raise HTTPException(
            status_code=422,
            detail=(
                "user_id wajib diisi. Runtime training hanya mengambil parameter dari "
                "DB topic_model_settings."
            ),
        )

    if request.model_type == ModelType.BERTOPIC:
        resolved_bertopic_params, params_source = _resolve_bertopic_params_for_start(request)
        resolved_lda_params = request.lda_params
    else:
        resolved_lda_params, params_source = _resolve_lda_params_for_start(request)
        resolved_bertopic_params = request.bertopic_params

    description = (request.description or "").strip()
    if description != "":
        description = f"{description} | params_source={params_source}"
    else:
        description = f"params_source={params_source}"

    # Create job
    job_id = service.create_job(
        model_type=request.model_type,
        description=description,
    )

    # Start training in background
    background_tasks.add_task(
        _run_training_job,
        job_id=job_id,
        model_type=request.model_type,
        bertopic_params=resolved_bertopic_params,
        lda_params=resolved_lda_params,
    )

    return TrainingStatusResponse(
        job_id=job_id,
        status=TrainingStatus.PENDING,
        model_type=request.model_type,
        progress=0.0,
        message=f"Training job created and queued (params_source={params_source})",
    )


@router.post("/settings/upload")
async def upload_bertopic_settings(
    user_id: int = Form(...),
    config_file: UploadFile = File(...),
):
    """Upload BERTopic JSON params and update topic_model_settings in DB."""
    filename = (config_file.filename or "").strip()
    if filename and not filename.lower().endswith(".json"):
        raise HTTPException(status_code=400, detail="File harus berformat .json")

    try:
        raw_bytes = await config_file.read()
    except Exception as exc:
        raise HTTPException(status_code=400, detail=f"Gagal membaca file: {exc}")

    if not raw_bytes:
        raise HTTPException(status_code=400, detail="File JSON kosong")

    try:
        payload = json.loads(raw_bytes.decode("utf-8"))
    except UnicodeDecodeError:
        raise HTTPException(status_code=400, detail="File JSON harus UTF-8")
    except json.JSONDecodeError as exc:
        raise HTTPException(status_code=400, detail=f"JSON tidak valid: {exc.msg}")

    params_payload = _extract_bertopic_params_payload(payload)

    try:
        bertopic_params = BERTopicHyperparameters(**params_payload)
    except Exception as exc:
        raise HTTPException(status_code=422, detail=f"Invalid BERTopic params: {exc}")

    try:
        database.upsert_topic_model_settings(
            user_id=int(user_id),
            bertopic_params=bertopic_params.model_dump(),
        )
    except Exception as exc:
        raise HTTPException(status_code=500, detail=f"Gagal update DB: {exc}")

    return {
        "status": "ok",
        "user_id": int(user_id),
        "bertopic_params": bertopic_params.model_dump(),
    }


@router.post("/start-from-json")
async def upload_bertopic_settings_legacy(
    user_id: int = Form(...),
    config_file: UploadFile = File(...),
):
    """Deprecated alias for /training/settings/upload."""
    return await upload_bertopic_settings(user_id=user_id, config_file=config_file)


@router.get("/settings/template")
async def download_bertopic_settings_template():
    """Download template JSON for BERTopic settings."""
    template_payload = {
        "bertopic_params": BERTopicHyperparameters().model_dump(),
    }
    return JSONResponse(
        content=template_payload,
        headers={
            "Content-Disposition": "attachment; filename=bertopic_params_template.json",
        },
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


@router.post("/jobs/{job_id}/cancel")
async def cancel_training_job(job_id: str):
    """Cancel a running/pending training job (best effort soft-cancel)."""
    _validate_job_id(job_id)

    job = service.get_job(job_id)
    if job is None:
        raise HTTPException(status_code=404, detail="Job not found")

    current_status = str(job.get("status") or "")
    if current_status in {
        TrainingStatus.COMPLETED.value,
        TrainingStatus.FAILED.value,
    }:
        raise HTTPException(
            status_code=400,
            detail={
                "status": "error",
                "message": f"Job {job_id} tidak dapat dibatalkan (sudah selesai)",
            },
        )

    updated_job = service.cancel_job(job_id)
    updated_status = str((updated_job or {}).get("status") or "")

    if updated_status == TrainingStatus.FAILED.value:
        return {
            "status": "cancelled",
            "message": f"Job {job_id} berhasil dibatalkan",
            "job_id": job_id,
        }

    return {
        "status": "cancel_requested",
        "message": f"Permintaan pembatalan job {job_id} diterima. Menunggu proses berhenti aman.",
        "job_id": job_id,
    }


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
    """Lightweight smoke-test for a trained BERTopic/LDA model.

    Loads the model from disk (if present) and returns a small summary.
    Does NOT run embeddings/inference on new documents.
    """

    _validate_job_id(job_id)

    results: dict = {}
    try:
        results = service.load_results(job_id)
    except Exception:
        results = {}

    model_type = (results.get("model_type") or "").lower()
    model_path = results.get("model_path")

    model_dir = Path(str(model_path)) if model_path else None
    if model_dir is None or not model_dir.exists():
        bertopic_dir = path_settings.get_models_dir() / f"bertopic_{job_id}"
        lda_dir = path_settings.get_models_dir() / f"lda_{job_id}"

        if model_type == "lda" and lda_dir.exists():
            model_dir = lda_dir
        elif model_type == "bertopic" and bertopic_dir.exists():
            model_dir = bertopic_dir
        elif bertopic_dir.exists():
            model_dir = bertopic_dir
            model_type = "bertopic"
        elif lda_dir.exists():
            model_dir = lda_dir
            model_type = "lda"

    if not model_dir or not model_dir.exists():
        raise HTTPException(status_code=404, detail=f"Model directory not found for job {job_id}")

    summary = {
        "job_id": job_id,
        "model_type": model_type or None,
        "model_path": str(model_dir),
    }

    is_bertopic_artifact = (model_dir / "model").exists()
    is_lda_artifact = (model_dir / "lda_model").exists()

    if model_type == "lda" or (not model_type and is_lda_artifact):
        if not is_lda_artifact:
            raise HTTPException(status_code=404, detail=f"LDA model artifacts not found for job {job_id}")

        from app.services.stopwords import load_stopwords
        from gensim.corpora import Dictionary
        from gensim.models import LdaModel

        model = LdaModel.load(str(model_dir / "lda_model"))
        _ = Dictionary.load(str(model_dir / "dictionary.dict"))

        stopwords = load_stopwords("indonesian", include_academic=True)
        sample_topics = []
        for tid in range(min(model.num_topics, 5)):
            words_scores = model.show_topic(tid, topn=TOP_WORDS_PREVIEW_LIMIT)
            filtered = [(w, s) for (w, s) in words_scores if w not in stopwords and len(w) >= 3]
            if not filtered:
                filtered = words_scores
            sample_topics.append({
                "topic_id": tid,
                "top_words": [w for w, _ in filtered[:TOP_WORDS_PREVIEW_LIMIT]],
            })

        summary.update(
            {
                "model_type": "lda",
                "num_topics": model.num_topics,
                "sample_topics": sample_topics,
            }
        )
        return summary

    if model_type == "bertopic" or is_bertopic_artifact:
        if not is_bertopic_artifact:
            raise HTTPException(status_code=404, detail=f"BERTopic model artifacts not found for job {job_id}")

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
                "top_words": [w for w, _ in filtered[:TOP_WORDS_PREVIEW_LIMIT]],
            })

        summary.update(
            {
                "model_type": "bertopic",
                "num_topics": len(topic_ids),
                "sample_topics": sample_topics,
            }
        )

        return summary

    raise HTTPException(status_code=400, detail="Unsupported model artifacts for this job")


def _load_bertopic_trainer(job_id: str):
    from app.ml.bertopic_trainer import BERTopicTrainer
    import numpy as np
    from bertopic import BERTopic

    trainer = BERTopicTrainer()
    try:
        trainer.load_model(job_id)
        return trainer
    except FileNotFoundError:
        try:
            results = service.load_results(job_id)
        except Exception:
            results = {}

        model_dir_raw = results.get("model_path")
        model_dir = Path(str(model_dir_raw)) if model_dir_raw else None

        if model_dir is not None and model_dir.exists() and (model_dir / "model").exists():
            trainer.model = BERTopic.load(str(model_dir / "model"))

            embeddings_path = model_dir / "embeddings.npy"
            if embeddings_path.exists():
                trainer.embeddings = np.load(str(embeddings_path))

            return trainer

        raise HTTPException(
            status_code=404,
            detail=f"BERTopic model not found for job {job_id}",
        )


def _infer_topic_from_text_with_trainer(
    *,
    trainer,
    job_id: str,
    request: TopicInferenceRequest,
) -> TopicInferenceResponse:
    query = request.text.strip()
    if query == "":
        raise HTTPException(status_code=422, detail="text cannot be empty")

    distribution: List[TopicInferenceItem] = []

    similar_topic_ids: List[int] = []
    similarities: List[float] = []

    can_run_semantic_search = True
    if getattr(trainer.model, "embedding_model", None) is None:
        if bool(getattr(trainer, "_embedding_attach_failed", False)):
            can_run_semantic_search = False
        else:
            try:
                trainer.embedding_model = trainer._build_embedding_model()
                trainer.model.embedding_model = trainer.embedding_model
            except Exception as e:
                trainer._embedding_attach_failed = True
                can_run_semantic_search = False
                logger.warning(
                    "Embedding model unavailable for semantic topic search (job_id={}): {}",
                    job_id,
                    str(e),
                )

    if can_run_semantic_search:
        try:
            similar_topic_ids, similarities = trainer.model.find_topics(
                query,
                top_n=request.top_n_topics,
            )
        except Exception as e:
            logger.warning(
                "Semantic topic search failed (job_id={}): {}",
                job_id,
                str(e),
            )
            similar_topic_ids, similarities = [], []

    for raw_topic_id, raw_similarity in zip(similar_topic_ids or [], similarities or []):
        topic_id = int(raw_topic_id)
        if topic_id == -1:
            continue

        similarity = max(0.0, min(1.0, float(raw_similarity)))
        words_scores = trainer.model.get_topic(topic_id) or []
        top_words = [str(word) for word, _ in words_scores[:TOP_WORDS_PREVIEW_LIMIT]]

        distribution.append(
            TopicInferenceItem(
                topic_id=topic_id,
                similarity=round(similarity, 6),
                top_words=top_words,
            )
        )

    primary_topic_id = -1
    primary_similarity = 0.0

    if distribution:
        primary_topic_id = int(distribution[0].topic_id)
        primary_similarity = float(distribution[0].similarity)

    # Fallback to transform when semantic find_topics cannot return candidates.
    if primary_topic_id == -1:
        try:
            inferred_topics, inferred_probabilities = trainer.model.transform([query])
            if inferred_topics and int(inferred_topics[0]) != -1:
                primary_topic_id = int(inferred_topics[0])

                if inferred_probabilities is not None:
                    try:
                        first_probs = inferred_probabilities[0]
                        if hasattr(first_probs, "__iter__"):
                            primary_similarity = float(max(first_probs))
                        else:
                            primary_similarity = float(first_probs)
                    except Exception:
                        primary_similarity = 0.0
        except Exception:
            primary_topic_id = -1
            primary_similarity = 0.0

    # Last fallback: lexical match against topic keywords (no embedding model required).
    if primary_topic_id == -1:
        distribution = _build_keyword_fallback_distribution(
            trainer=trainer,
            query=query,
            top_n_topics=request.top_n_topics,
        )
        if distribution:
            primary_topic_id = int(distribution[0].topic_id)
            primary_similarity = float(distribution[0].similarity)

    primary_words: List[str] = []
    if primary_topic_id != -1:
        primary_words_scores = trainer.model.get_topic(primary_topic_id) or []
        primary_words = [str(word) for word, _ in primary_words_scores[:TOP_WORDS_PREVIEW_LIMIT]]

        if all(item.topic_id != primary_topic_id for item in distribution):
            distribution.insert(
                0,
                TopicInferenceItem(
                    topic_id=primary_topic_id,
                    similarity=round(max(0.0, min(1.0, primary_similarity)), 6),
                    top_words=primary_words,
                ),
            )

    return TopicInferenceResponse(
        job_id=job_id,
        query=query,
        topic_id=primary_topic_id,
        topic_similarity=round(max(0.0, min(1.0, primary_similarity)), 6),
        top_words=primary_words,
        topic_distribution=distribution[: request.top_n_topics],
    )


@router.post("/model/{job_id}/infer", response_model=TopicInferenceResponse)
async def infer_topic_from_text(job_id: str, request: TopicInferenceRequest):
    """Infer the most relevant BERTopic topic from a free-text query."""

    _validate_job_id(job_id)
    trainer = _load_bertopic_trainer(job_id)
    return _infer_topic_from_text_with_trainer(trainer=trainer, job_id=job_id, request=request)


@router.post("/model/infer-batch", response_model=TopicInferenceBatchResponse)
async def infer_topics_for_new_data(request: TopicInferenceBatchRequest):
    """Infer topics for multiple new texts using an existing BERTopic model.

    If `job_id` is omitted, this endpoint automatically uses the latest completed
    BERTopic model available on the server (no retraining required).
    """

    resolved_job_id, model_source = _resolve_bertopic_job_for_inference(request.job_id)
    trainer = _load_bertopic_trainer(resolved_job_id)

    items: List[TopicInferenceBatchItem] = []
    for idx, text in enumerate(request.texts):
        single_result = _infer_topic_from_text_with_trainer(
            trainer=trainer,
            job_id=resolved_job_id,
            request=TopicInferenceRequest(text=text, top_n_topics=request.top_n_topics),
        )

        items.append(
            TopicInferenceBatchItem(
                index=idx,
                query=single_result.query,
                topic_id=single_result.topic_id,
                topic_similarity=single_result.topic_similarity,
                top_words=single_result.top_words,
                topic_distribution=single_result.topic_distribution,
            )
        )

    return TopicInferenceBatchResponse(
        job_id=resolved_job_id,
        model_source=model_source,
        total=len(items),
        items=items,
    )


@router.get("/model/{job_id}/test-dataset")
async def test_trained_model_with_dataset(job_id: str):
    """Re-test a saved BERTopic/LDA model against the existing dataset in DB.

    This endpoint loads the trained model from disk, loads the current
    preprocessed dataset from MySQL, re-computes evaluation metrics,
    and compares them against the metrics stored at training time.

        Notes:
            - Re-test does NOT re-run embeddings/inference; it evaluates coherence/diversity
                based on model topic words and dataset tokens.
            - Results may differ if the dataset in DB has changed since training.
    """

    _validate_job_id(job_id)

    try:
        stored_results = service.load_results(job_id)
    except FileNotFoundError:
        raise HTTPException(status_code=404, detail=f"Results not found for job {job_id}")

    model_type = (stored_results.get("model_type") or "").lower()

    model_path = stored_results.get("model_path")
    model_dir = Path(str(model_path)) if model_path else None
    if model_dir is None or not model_dir.exists():
        bertopic_dir = path_settings.get_models_dir() / f"bertopic_{job_id}"
        lda_dir = path_settings.get_models_dir() / f"lda_{job_id}"

        if model_type == "lda" and lda_dir.exists():
            model_dir = lda_dir
        elif model_type == "bertopic" and bertopic_dir.exists():
            model_dir = bertopic_dir
        elif bertopic_dir.exists():
            model_dir = bertopic_dir
            model_type = "bertopic"
        elif lda_dir.exists():
            model_dir = lda_dir
            model_type = "lda"

    if not model_type:
        model_type = "bertopic"

    # Load dataset from DB (same source as training)
    df = database.load_processed_data_from_db()
    total = int(len(df))
    if total == 0:
        raise HTTPException(status_code=400, detail="Dataset in DB is empty. Run preprocessing first.")

    before = len(df)
    if model_type == "lda":
        df = df.dropna(subset=["processed_text"])
        df["processed_text"] = df["processed_text"].astype(str)
        df = df[df["processed_text"].str.strip() != ""]
    else:
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
    def _keyword_match_ratio(stored_topic_info, current_topic_info, topn: int = 10) -> Optional[float]:
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
            if not stored_map or not current_map:
                return None

            scores = []
            for topic_id, words in stored_map.items():
                current_words = current_map.get(topic_id)
                if not current_words:
                    continue
                overlap = set(words).intersection(current_words)
                scores.append(len(overlap) / max(len(words), 1))

            if not scores:
                return 0.0

            return round(sum(scores) / len(scores), 4)
        except Exception:
            return None

    keyword_match = None

    if model_type == "lda":
        from gensim.corpora import Dictionary
        from gensim.models import LdaModel

        if model_dir is None or not (model_dir / "lda_model").exists():
            raise HTTPException(status_code=404, detail=f"LDA model artifacts not found for job {job_id}")

        model = LdaModel.load(str(model_dir / "lda_model"))
        dictionary = Dictionary.load(str(model_dir / "dictionary.dict"))
        tokenized_docs = [str(doc).split() for doc in df["processed_text"].tolist()]
        tokenized_docs = [tokens for tokens in tokenized_docs if tokens]

        if not tokenized_docs:
            raise HTTPException(status_code=400, detail="No valid processed_text tokens for LDA evaluation.")

        retest_metrics = evaluator.evaluate_lda(
            model=model,
            tokenized_docs=tokenized_docs,
            dictionary=dictionary,
        )
    else:
        from app.ml.bertopic_trainer import BERTopicTrainer
        from app.services.stopwords import load_stopwords

        trainer = BERTopicTrainer()
        trainer.load_model(job_id)

        retest_metrics = evaluator.evaluate_bertopic(
            model=trainer.model,
            documents=df["cleaned_text"].tolist(),
            topics=None,
            vectorizer_model=trainer.vectorizer_model,
            coherence_type=stored_metrics.get("coherence_type", "c_v"),
            coherence_tokenization=stored_metrics.get("coherence_tokenization", "vectorizer"),
            coherence_dict_no_below=stored_metrics.get("coherence_dict_no_below", 3),
            coherence_dict_no_above=stored_metrics.get("coherence_dict_no_above", 0.95),
            top_n_words=int(stored_metrics.get("top_n_words", 10) or 10),
        )

        current_topic_info = []
        info = trainer.model.get_topic_info()
        topic_ids = [int(t) for t in info["Topic"].tolist() if int(t) != -1]
        stopwords = load_stopwords("indonesian", include_academic=True)
        top_n_words = int(stored_metrics.get("top_n_words", TOP_WORDS_PREVIEW_LIMIT) or TOP_WORDS_PREVIEW_LIMIT)

        for topic_id in topic_ids:
            words_scores = trainer.model.get_topic(int(topic_id)) or []
            filtered = [(w, s) for (w, s) in words_scores if w not in stopwords and len(w) >= 3]
            if not filtered:
                filtered = words_scores
            current_topic_info.append({
                "topic_id": int(topic_id),
                "top_words": [w for w, _ in filtered[:TOP_WORDS_PREVIEW_LIMIT]],
            })

        keyword_match = _keyword_match_ratio(
            stored_topic_info=stored_results.get("topic_info"),
            current_topic_info=current_topic_info,
            topn=top_n_words,
        )

    retest_metrics = _sanitize_json(retest_metrics)

    return JSONResponse(content={
        "status": "ok",
        "job_id": job_id,
        "model_type": model_type,
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


@router.post("/topic-curation/generate", response_model=TopicCurationSuggestionResponse)
async def generate_topic_curation_suggestion(request: TopicCurationSuggestionRequest):
    """Generate curated topic name and representation using Gemini via Python SDK."""
    try:
        suggestion = gemini_topic_curation_service.generate_suggestion(
            keywords=request.keywords,
            topic_id=request.topic_id,
            topic_doc_count=request.topic_doc_count,
            model_type=request.model_type,
            representative_titles=request.representative_titles,
            representative_abstracts=request.representative_abstracts,
            broader_terms=request.broader_terms,
        )
        return TopicCurationSuggestionResponse(
            custom_name=suggestion["custom_name"],
            representation_description=suggestion["representation_description"],
        )
    except ValueError as e:
        raise HTTPException(status_code=422, detail=str(e))
    except GeminiTopicCurationError as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/title-recommendation/generate", response_model=TitleRecommendationResponse)
async def generate_title_recommendation(request: TitleRecommendationRequest):
    """Generate skripsi title recommendations using Gemini via Python SDK."""
    if request.strict_context and not _is_recommendation_prompt_in_context(
        prompt=request.user_prompt,
        topic_keywords=request.topic_keywords,
        mapped_titles=request.mapped_titles,
    ):
        raise HTTPException(
            status_code=422,
            detail="Prompt di luar konteks topik skripsi. Gunakan prompt yang relevan dengan topik/keyword.",
        )

    try:
        payload = gemini_title_recommendation_service.generate_recommendations(
            topic_label=request.topic_label,
            topic_keywords=request.topic_keywords,
            mapped_titles=request.mapped_titles,
            user_prompt=request.user_prompt,
            recommendations_count=request.recommendations_count,
        )

        context_ok = bool(payload.get("context_ok", True))
        context_message = payload.get("context_message")
        recommendations = payload.get("recommendations") or []

        if request.strict_context and not context_ok:
            raise HTTPException(
                status_code=422,
                detail=str(context_message or "Prompt di luar konteks topik skripsi."),
            )

        return TitleRecommendationResponse(
            topic_id=request.topic_id,
            topic_label=request.topic_label,
            context_ok=context_ok,
            context_message=str(context_message) if context_message else None,
            recommendations=recommendations,
        )
    except ValueError as e:
        raise HTTPException(status_code=422, detail=str(e))
    except GeminiTitleRecommendationError as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.get("/presets/bertopic")
async def get_bertopic_presets():
    """Get available BERTopic hyperparameter presets."""
    return {
        name: params.model_dump()
        for name, params in BERTOPIC_PRESETS.items()
    }
