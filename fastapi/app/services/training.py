"""
Training Service (Orchestrator)
Coordinates the full training pipeline:
  data loading → preprocessing → model training → evaluation → saving results.

Manages training jobs with status tracking.
"""

import json
import time
import uuid
from datetime import datetime
from pathlib import Path
from typing import Any, Dict, List, Optional

import pandas as pd
from app.core.config import path_settings
from app.ml.bertopic_trainer import BERTopicTrainer
from app.ml.evaluator import TopicEvaluator
from app.ml.lda_trainer import LDATrainer
from app.models.schemas import (BERTopicHyperparameters, LDAHyperparameters,
                                ModelType, TrainingStatus)
from app.services.preprocessing import TextPreprocessor
from loguru import logger

# In-memory job store (replace with DB for production)
_training_jobs: Dict[str, Dict[str, Any]] = {}


class TrainingService:
    """Orchestrates the full training pipeline."""

    def __init__(self):
        self.preprocessor = TextPreprocessor()
        self.evaluator = TopicEvaluator()

    # ============================================
    # Job Management
    # ============================================

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

    # ============================================
    # Data Loading
    # ============================================

    def load_data(self, filename: str = "raw_data.csv") -> pd.DataFrame:
        """Load raw data from CSV."""
        data_path = path_settings.get_raw_data_dir() / filename
        if not data_path.exists():
            raise FileNotFoundError(
                f"Data file not found: {data_path}. "
                f"Please place your data CSV in {path_settings.get_raw_data_dir()}"
            )
        df = pd.read_csv(data_path, encoding="utf-8")
        logger.info(f"Loaded {len(df)} records from {data_path}")
        return df

    def preprocess_data(
        self,
        df: pd.DataFrame,
        text_column: str = "abstract",
    ) -> pd.DataFrame:
        """
        Preprocess data (dual pipeline) and save processed version.

        Output DataFrame will contain both:
          - cleaned_text  → for BERTopic (IndoSBERT)
          - processed_text → for LDA (tokenized + stemmed)
        """
        processed_df = self.preprocessor.preprocess_dataframe(
            df, text_column=text_column
        )

        # Save processed data
        path_settings.ensure_dirs()
        output_path = path_settings.get_processed_data_dir() / "processed_data.csv"
        processed_df.to_csv(output_path, index=False, encoding="utf-8")
        logger.info(f"Saved processed data to {output_path}")

        return processed_df

    # ============================================
    # Training Pipelines
    # ============================================

    def train_bertopic(
        self,
        job_id: str,
        documents: List[str],
        timestamps: Optional[List[int]] = None,
        params: Optional[BERTopicHyperparameters] = None,
    ) -> Dict[str, Any]:
        """
        Full BERTopic training pipeline.

        Args:
            job_id: Training job ID
            documents: Preprocessed text documents
            timestamps: Year for each document (for dynamic analysis)
            params: Hyperparameters (uses defaults if None)
        """
        self._update_job(
            job_id,
            status=TrainingStatus.RUNNING.value,
            started_at=datetime.now().isoformat(),
            message="Starting BERTopic training...",
            progress=10.0,
        )

        try:
            trainer = BERTopicTrainer(params=params)

            # Step 1: Train
            self._update_job(job_id, message="Training BERTopic model...", progress=30.0)
            result = trainer.train(documents, timestamps=timestamps)

            # Step 2: Evaluate
            self._update_job(job_id, message="Evaluating model...", progress=70.0)
            metrics = self.evaluator.evaluate_bertopic(
                model=trainer.model,
                documents=documents,
            )
            result["metrics"] = metrics

            # Step 3: Save model
            self._update_job(job_id, message="Saving model...", progress=90.0)
            model_path = trainer.save_model(job_id)
            result["model_path"] = model_path

            # Step 4: Save results
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
            logger.error(f"BERTopic training failed: {e}")
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
        """
        Full LDA training pipeline.

        Args:
            job_id: Training job ID
            documents: Preprocessed text documents
            timestamps: Year for each document (for per-year analysis)
            params: Hyperparameters (uses defaults if None)
        """
        self._update_job(
            job_id,
            status=TrainingStatus.RUNNING.value,
            started_at=datetime.now().isoformat(),
            message="Starting LDA training...",
            progress=10.0,
        )

        try:
            trainer = LDATrainer(params=params)

            # Step 1: Train
            self._update_job(job_id, message="Training LDA model...", progress=30.0)
            result = trainer.train(documents, timestamps=timestamps)

            # Step 2: Evaluate
            self._update_job(job_id, message="Evaluating model...", progress=70.0)
            metrics = self.evaluator.evaluate_lda(
                model=trainer.model,
                tokenized_docs=trainer.tokenized_docs,
                dictionary=trainer.dictionary,
            )
            result["metrics"] = metrics

            # Step 3: Save model
            self._update_job(job_id, message="Saving model...", progress=90.0)
            model_path = trainer.save_model(job_id)
            result["model_path"] = model_path

            # Step 4: Save results
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

    # ============================================
    # Results Management
    # ============================================

    def _save_results(self, job_id: str, result: Dict[str, Any]) -> str:
        """Save training results to JSON."""
        path_settings.ensure_dirs()
        results_dir = path_settings.get_results_dir()
        result_path = results_dir / f"result_{job_id}.json"

        # Make result JSON serializable
        serializable = json.loads(
            json.dumps(result, default=str)
        )

        with open(result_path, "w", encoding="utf-8") as f:
            json.dump(serializable, f, indent=2, ensure_ascii=False)

        logger.info(f"Results saved to {result_path}")
        return str(result_path)

    def load_results(self, job_id: str) -> Dict[str, Any]:
        """Load training results from JSON."""
        result_path = path_settings.get_results_dir() / f"result_{job_id}.json"
        if not result_path.exists():
            raise FileNotFoundError(f"Results not found for job {job_id}")

        with open(result_path, "r", encoding="utf-8") as f:
            return json.load(f)
