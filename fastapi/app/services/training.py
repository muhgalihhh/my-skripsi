"""Training service with in-memory job tracking."""

import hashlib
import json
import tarfile
import uuid
from datetime import datetime
from pathlib import Path
from typing import Any, Dict, List, Optional

from app.core.config import path_settings
from app.ml.bertopic_trainer import BERTopicTrainer
from app.ml.evaluator import TopicEvaluator
from app.ml.lda_trainer import LDATrainer
from app.models.schemas import (BERTopicHyperparameters, LDAHyperparameters,
                                ModelType, TrainingStatus)
from loguru import logger

_training_jobs: Dict[str, Dict[str, Any]] = {}


class TrainingService:
    """Run BERTopic/LDA training and persist outputs."""

    def __init__(self):
        self.evaluator = TopicEvaluator()

    def create_job(self, model_type: ModelType, description: str = "") -> str:
        """Create a new training job and return its ID."""
        job_id = str(uuid.uuid4())[:8]
        _training_jobs[job_id] = {
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
        }
        logger.info(f"Created training job {job_id} for {model_type.value}")
        return job_id

    def get_job(self, job_id: str) -> Optional[Dict[str, Any]]:
        """Get training job status."""
        return _training_jobs.get(job_id)

    def list_jobs(self) -> List[Dict[str, Any]]:
        """List all training jobs."""
        return list(_training_jobs.values())

    def _update_job(self, job_id: str, **kwargs):
        """Update training job fields."""
        if job_id in _training_jobs:
            _training_jobs[job_id].update(kwargs)

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
            result = trainer.train(
                documents,
                timestamps=timestamps,
                document_ids=document_ids,
            )

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
            model_path = trainer.save_model(job_id)
            result["model_path"] = model_path

            self._save_results(job_id, result)

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
            result = trainer.train(documents, timestamps=timestamps)

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
            metrics = self.evaluator.evaluate_lda(
                model=trainer.model,
                tokenized_docs=trainer.tokenized_docs,
                dictionary=trainer.dictionary,
            )
            result["metrics"] = metrics

            self._update_job(job_id, message="Saving model...", progress=90.0)
            model_path = trainer.save_model(job_id)
            result["model_path"] = model_path

            self._save_results(job_id, result)

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
        archive_path = self._archive_model_artifacts(job_id=job_id, model_path=model_path)

        result["results_path"] = str(result_path)
        if archive_path is not None:
            result["model_archive_path"] = archive_path

        serializable = json.loads(
            json.dumps(result, default=str)
        )

        with open(result_path, "w", encoding="utf-8") as f:
            json.dump(serializable, f, indent=2, ensure_ascii=False)

        logger.info(f"Results saved to {result_path}")
        return str(result_path)

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
