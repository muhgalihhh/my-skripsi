"""Training service with file-backed job tracking."""

import hashlib
import json
import tarfile
import uuid
from datetime import datetime
from pathlib import Path
from threading import Lock
from typing import Any, Dict, List, Optional

from app.core.config import path_settings
from app.ml.bertopic_trainer import BERTopicTrainer
from app.ml.evaluator import TopicEvaluator
from app.ml.lda_trainer import LDATrainer
from app.models.schemas import (BERTopicHyperparameters, LDAHyperparameters,
                                ModelType, TrainingStatus)
from loguru import logger

_training_jobs: Dict[str, Dict[str, Any]] = {}
_JOB_STATE_FILE = "training_jobs_state.json"
_IMPORT_SNAPSHOT_FILE = "import_snapshot.json"
_JOB_STORE_LOCK = Lock()


def _job_state_path() -> Path:
    """Return persisted job-state path under FastAPI results directory."""
    path_settings.ensure_dirs()
    results_dir = path_settings.get_results_dir()
    results_dir.mkdir(parents=True, exist_ok=True)
    return results_dir / _JOB_STATE_FILE


def _read_jobs_from_store() -> Dict[str, Dict[str, Any]]:
    """Load training job state from disk with basic shape validation."""
    path = _job_state_path()
    if not path.exists():
        return {}

    try:
        with open(path, "r", encoding="utf-8") as f:
            raw = json.load(f)
    except Exception as exc:
        logger.warning(f"Failed to read persisted training jobs from {path}: {exc}")
        return {}

    if not isinstance(raw, dict):
        logger.warning(f"Invalid training job store format in {path}, expected object")
        return {}

    jobs: Dict[str, Dict[str, Any]] = {}
    for key, value in raw.items():
        if isinstance(value, dict):
            value.setdefault("job_id", str(key))
            jobs[str(key)] = value

    return jobs


def _write_jobs_to_store(jobs: Dict[str, Dict[str, Any]]) -> None:
    """Write training job state atomically to reduce corruption risk."""
    path = _job_state_path()
    tmp_path = path.with_suffix(path.suffix + ".tmp")

    serializable = json.loads(json.dumps(jobs, default=str))
    with open(tmp_path, "w", encoding="utf-8") as f:
        json.dump(serializable, f, indent=2, ensure_ascii=False)

    tmp_path.replace(path)


def _load_jobs_snapshot() -> Dict[str, Dict[str, Any]]:
    """Refresh in-memory cache from disk and return a snapshot copy."""
    with _JOB_STORE_LOCK:
        jobs = _read_jobs_from_store()
        _training_jobs.clear()
        _training_jobs.update(jobs)
        return dict(_training_jobs)


def _save_jobs_snapshot(jobs: Dict[str, Dict[str, Any]]) -> None:
    """Persist provided snapshot and update in-memory cache."""
    with _JOB_STORE_LOCK:
        _training_jobs.clear()
        _training_jobs.update(jobs)
        _write_jobs_to_store(_training_jobs)


class JobCancelled(Exception):
    """Raised when a training job is cancelled by user request."""


class TrainingService:
    """Run BERTopic/LDA training and persist outputs."""

    def __init__(self):
        self.evaluator = TopicEvaluator()
        _load_jobs_snapshot()

    def create_job(self, model_type: ModelType, description: str = "") -> str:
        """Create a new training job and return its ID."""
        job_id = str(uuid.uuid4())[:8]
        with _JOB_STORE_LOCK:
            jobs = _read_jobs_from_store()
            jobs[job_id] = {
                "job_id": job_id,
                "model_type": model_type.value,
                "status": TrainingStatus.PENDING.value,
                "progress": 0.0,
                "message": "Job created, waiting to start",
                "description": description,
                "started_at": None,
                "completed_at": None,
                "error": None,
                "result": None,
                "cancel_requested": False,
            }
            _training_jobs.clear()
            _training_jobs.update(jobs)
            _write_jobs_to_store(_training_jobs)
        logger.info(f"Created training job {job_id} for {model_type.value}")
        return job_id

    def get_job(self, job_id: str) -> Optional[Dict[str, Any]]:
        """Get training job status."""
        jobs = _load_jobs_snapshot()
        return jobs.get(job_id)

    def list_jobs(self) -> List[Dict[str, Any]]:
        """List all training jobs."""
        jobs = _load_jobs_snapshot()
        return list(jobs.values())

    def _update_job(self, job_id: str, **kwargs):
        """Update training job fields."""
        with _JOB_STORE_LOCK:
            jobs = _read_jobs_from_store()
            if job_id in jobs:
                jobs[job_id].update(kwargs)
                _training_jobs.clear()
                _training_jobs.update(jobs)
                _write_jobs_to_store(_training_jobs)

    def _is_cancel_requested(self, job_id: str) -> bool:
        job = self.get_job(job_id)
        return bool(job and job.get("cancel_requested"))

    def _raise_if_cancel_requested(self, job_id: str):
        if self._is_cancel_requested(job_id):
            raise JobCancelled("Training cancelled by user")

    def cancel_job(self, job_id: str) -> Optional[Dict[str, Any]]:
        """Request cancellation for a pending/running training job."""
        job = self.get_job(job_id)
        if job is None:
            return None

        current_status = str(job.get("status") or "")
        if current_status in {
            TrainingStatus.COMPLETED.value,
            TrainingStatus.FAILED.value,
        }:
            return job

        # Mark as failed immediately for UX responsiveness while keeping
        # cancel_requested so in-flight training exits at next safe checkpoint.
        self._update_job(
            job_id,
            cancel_requested=True,
            status=TrainingStatus.FAILED.value,
            completed_at=datetime.now().isoformat(),
            message="Training cancelled by user",
            error="Cancelled by user",
        )

        return self.get_job(job_id)

    @staticmethod
    def _build_run_fingerprint(
        model_type: str,
        documents: List[str],
        params: Dict[str, Any],
        timestamps: Optional[List[int]] = None,
        document_ids: Optional[List[int]] = None,
    ) -> str:
        payload = {
            "model_type": model_type,
            "documents": documents,
            "params": params,
            "timestamps": timestamps or [],
            "document_ids": document_ids or [],
        }
        canonical = json.dumps(payload, sort_keys=True, ensure_ascii=False, default=str)
        return hashlib.sha256(canonical.encode("utf-8")).hexdigest()

    def train_bertopic(
        self,
        job_id: str,
        documents: List[str],
        timestamps: Optional[List[int]] = None,
        document_ids: Optional[List[int]] = None,
        params: Optional[BERTopicHyperparameters] = None,
    ) -> Dict[str, Any]:
        """Run BERTopic training, evaluation, and persistence for one job."""
        self._raise_if_cancel_requested(job_id)

        self._update_job(
            job_id,
            status=TrainingStatus.RUNNING.value,
            started_at=datetime.now().isoformat(),
            message="Starting BERTopic training...",
            progress=10.0,
        )

        try:
            trainer = BERTopicTrainer(params=params)

            self._update_job(job_id, message="Training BERTopic model...", progress=30.0)
            self._raise_if_cancel_requested(job_id)

            result = trainer.train(
                documents,
                timestamps=timestamps,
                document_ids=document_ids,
            )

            self._raise_if_cancel_requested(job_id)

            result["reproducibility"] = {
                "run_fingerprint": self._build_run_fingerprint(
                    model_type="bertopic",
                    documents=documents,
                    params=trainer.params.model_dump(),
                    timestamps=timestamps,
                    document_ids=document_ids,
                ),
                "n_documents": len(documents),
            }

            if (
                document_ids is not None
                and "document_topics" not in result
                and trainer.topics is not None
                and len(document_ids) == len(trainer.topics)
            ):
                result["document_topics"] = [
                    {
                        "skripsi_id": int(doc_id),
                        "topic_id": int(topic_id),
                        "is_outlier": int(topic_id) == -1,
                    }
                    for doc_id, topic_id in zip(document_ids, trainer.topics)
                ]

            self._update_job(job_id, message="Evaluating model...", progress=70.0)
            self._raise_if_cancel_requested(job_id)

            metrics = self.evaluator.evaluate_bertopic(
                model=trainer.model,
                documents=documents,
                topics=trainer.topics,
                vectorizer_model=trainer.vectorizer_model,
                coherence_type=trainer.params.coherence_type,
                coherence_tokenization=trainer.params.coherence_tokenization,
                coherence_dict_no_below=trainer.params.coherence_dict_no_below,
                coherence_dict_no_above=trainer.params.coherence_dict_no_above,
                top_n_words=trainer.params.top_n_words,
            )
            result["metrics"] = metrics

            self._update_job(job_id, message="Saving model...", progress=90.0)
            self._raise_if_cancel_requested(job_id)

            model_path = trainer.save_model(job_id)
            result["model_path"] = model_path

            self._raise_if_cancel_requested(job_id)

            self._save_results(job_id, result)

            self._raise_if_cancel_requested(job_id)

            self._update_job(
                job_id,
                status=TrainingStatus.COMPLETED.value,
                completed_at=datetime.now().isoformat(),
                message=f"Training complete. Coherence: {metrics['coherence_cv']:.4f}, "
                        f"Diversity: {metrics['topic_diversity']:.4f}",
                progress=100.0,
                result=result,
            )

            return result

        except JobCancelled:
            logger.info("BERTopic training cancelled by user")
            self._update_job(
                job_id,
                status=TrainingStatus.FAILED.value,
                completed_at=datetime.now().isoformat(),
                message="Training cancelled by user",
                error="Cancelled by user",
            )
            return {"job_id": job_id, "status": "cancelled"}

        except Exception as e:
            logger.exception("BERTopic training failed")
            self._update_job(
                job_id,
                status=TrainingStatus.FAILED.value,
                completed_at=datetime.now().isoformat(),
                message=f"Training failed: {str(e)}",
                error=str(e),
            )
            raise

    def train_lda(
        self,
        job_id: str,
        documents: List[str],
        timestamps: Optional[List[int]] = None,
        params: Optional[LDAHyperparameters] = None,
    ) -> Dict[str, Any]:
        """Run LDA training, evaluation, and persistence for one job."""
        self._raise_if_cancel_requested(job_id)

        self._update_job(
            job_id,
            status=TrainingStatus.RUNNING.value,
            started_at=datetime.now().isoformat(),
            message="Starting LDA training...",
            progress=10.0,
        )

        try:
            trainer = LDATrainer(params=params)

            self._update_job(job_id, message="Training LDA model...", progress=30.0)
            self._raise_if_cancel_requested(job_id)

            result = trainer.train(documents, timestamps=timestamps)

            self._raise_if_cancel_requested(job_id)

            result["reproducibility"] = {
                "run_fingerprint": self._build_run_fingerprint(
                    model_type="lda",
                    documents=documents,
                    params=trainer.params.model_dump(),
                    timestamps=timestamps,
                ),
                "n_documents": len(documents),
            }

            self._update_job(job_id, message="Evaluating model...", progress=70.0)
            self._raise_if_cancel_requested(job_id)

            metrics = self.evaluator.evaluate_lda(
                model=trainer.model,
                tokenized_docs=trainer.tokenized_docs,
                dictionary=trainer.dictionary,
            )
            result["metrics"] = metrics

            self._update_job(job_id, message="Saving model...", progress=90.0)
            self._raise_if_cancel_requested(job_id)

            model_path = trainer.save_model(job_id)
            result["model_path"] = model_path

            self._raise_if_cancel_requested(job_id)

            self._save_results(job_id, result)

            self._raise_if_cancel_requested(job_id)

            self._update_job(
                job_id,
                status=TrainingStatus.COMPLETED.value,
                completed_at=datetime.now().isoformat(),
                message=f"Training complete. Coherence: {metrics['coherence_cv']:.4f}, "
                        f"Diversity: {metrics['topic_diversity']:.4f}",
                progress=100.0,
                result=result,
            )

            return result

        except JobCancelled:
            logger.info("LDA training cancelled by user")
            self._update_job(
                job_id,
                status=TrainingStatus.FAILED.value,
                completed_at=datetime.now().isoformat(),
                message="Training cancelled by user",
                error="Cancelled by user",
            )
            return {"job_id": job_id, "status": "cancelled"}

        except Exception as e:
            logger.error(f"LDA training failed: {e}")
            self._update_job(
                job_id,
                status=TrainingStatus.FAILED.value,
                completed_at=datetime.now().isoformat(),
                message=f"Training failed: {str(e)}",
                error=str(e),
            )
            raise

    def _save_results(self, job_id: str, result: Dict[str, Any]) -> str:
        """Save training results to JSON."""
        path_settings.ensure_dirs()
        results_dir = path_settings.get_results_dir()
        result_path = results_dir / f"result_{job_id}.json"

        model_path = result.get("model_path")
        snapshot_path = self._write_import_snapshot(model_path=model_path, result=result)
        archive_path = self._archive_model_artifacts(job_id=job_id, model_path=model_path)

        result["results_path"] = str(result_path)
        if snapshot_path is not None:
            result["import_snapshot_path"] = snapshot_path
        if archive_path is not None:
            result["model_archive_path"] = archive_path

        serializable = json.loads(
            json.dumps(result, default=str)
        )

        with open(result_path, "w", encoding="utf-8") as f:
            json.dump(serializable, f, indent=2, ensure_ascii=False)

        logger.info(f"Results saved to {result_path}")
        return str(result_path)

    def _write_import_snapshot(self, model_path: Any, result: Dict[str, Any]) -> Optional[str]:
        """Persist a compact payload inside model artifacts for deterministic imports."""
        if not model_path:
            return None

        try:
            model_dir = Path(str(model_path))
        except Exception:
            return None

        if not model_dir.exists() or not model_dir.is_dir():
            return None

        topic_info = result.get("topic_info")
        if not isinstance(topic_info, list):
            topic_info = []

        document_topics = result.get("document_topics")
        if not isinstance(document_topics, list):
            document_topics = []

        metrics = result.get("metrics")
        if not isinstance(metrics, dict):
            metrics = {}

        snapshot_payload = {
            "schema_version": 1,
            "job_id": str(result.get("job_id") or ""),
            "model_type": str(result.get("model_type") or ""),
            "num_topics": result.get("num_topics"),
            "num_outliers": result.get("num_outliers"),
            "topic_info": topic_info,
            "document_topics": document_topics,
            "metrics": metrics,
            "created_at": datetime.now().isoformat(),
        }

        snapshot_path = model_dir / _IMPORT_SNAPSHOT_FILE
        with open(snapshot_path, "w", encoding="utf-8") as f:
            json.dump(snapshot_payload, f, indent=2, ensure_ascii=False)

        return str(snapshot_path)

    def _archive_model_artifacts(self, job_id: str, model_path: Any) -> Optional[str]:
        """Create a tar.gz archive of the trained model directory inside results.

        Returns the archive path as string, or None if model_path is missing/invalid.
        """

        if not model_path:
            return None

        try:
            model_dir = Path(str(model_path))
        except Exception:
            return None

        if not model_dir.exists() or not model_dir.is_dir():
            return None

        results_dir = path_settings.get_results_dir()
        archive_file = results_dir / f"model_{job_id}.tar.gz"

        try:
            if archive_file.exists():
                archive_file.unlink()
        except Exception:
            pass

        with tarfile.open(archive_file, "w:gz") as tar:
            tar.add(model_dir, arcname=model_dir.name)

        logger.info(f"Model archived to {archive_file}")
        return str(archive_file)

    def load_results(self, job_id: str) -> Dict[str, Any]:
        """Load training results from JSON."""
        result_path = path_settings.get_results_dir() / f"result_{job_id}.json"
        if not result_path.exists():
            raise FileNotFoundError(f"Results not found for job {job_id}")

        with open(result_path, "r", encoding="utf-8") as f:
            return json.load(f)
