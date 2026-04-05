"""
Preprocessing Job Manager
Manages background preprocessing jobs with progress tracking.
Uses the Singleton pattern to ensure job state persists across API calls,
mirroring the design of ScrapingJobManager.
"""

import threading
import uuid
from datetime import datetime
from enum import Enum
from typing import Any, Dict, List, Optional

import pandas as pd
from loguru import logger

from app.core import database
from app.services.preprocessing import TextPreprocessor


class PreprocessingJobStatus(str, Enum):
    PENDING = "pending"
    RUNNING = "running"
    COMPLETED = "completed"
    FAILED = "failed"
    CANCELLED = "cancelled"


class PreprocessingJob:
    """Represents a single preprocessing job with progress tracking."""

    def __init__(self, job_id: str, run_id: int):
        self.job_id = job_id
        self.run_id = run_id
        self.status = PreprocessingJobStatus.PENDING
        self.progress = 0.0
        self.message = "Initializing..."
        self.total_documents = 0
        self.processed = 0
        self.error: Optional[str] = None
        self.created_at = datetime.utcnow()
        self.started_at: Optional[datetime] = None
        self.completed_at: Optional[datetime] = None
        self._lock = threading.Lock()

    def update(self, **kwargs):
        """Thread-safe state update."""
        with self._lock:
            for key, value in kwargs.items():
                if hasattr(self, key):
                    setattr(self, key, value)

    def mark_running(self):
        with self._lock:
            self.status = PreprocessingJobStatus.RUNNING
            self.started_at = datetime.utcnow()

    def mark_completed(self, total_processed: int = 0):
        with self._lock:
            self.status = PreprocessingJobStatus.COMPLETED
            self.completed_at = datetime.utcnow()
            self.progress = 100.0
            self.processed = total_processed
            self.message = "Preprocessing fully completed and saved to DB"

    def mark_failed(self, error: str):
        with self._lock:
            self.status = PreprocessingJobStatus.FAILED
            self.completed_at = datetime.utcnow()
            self.error = error
            self.message = f"Gagal: {error}"

    def to_dict(self) -> Dict[str, Any]:
        """Convert job to dictionary for API response."""
        with self._lock:
            return {
                "job_id": self.job_id,
                "run_id": self.run_id,
                "status": self.status.value,
                "progress": self.progress,
                "message": self.message,
                "total_documents": self.total_documents,
                "processed": self.processed,
                "error": self.error,
                "started_at": self.started_at.isoformat() if self.started_at else None,
                "completed_at": self.completed_at.isoformat() if self.completed_at else None,
            }


class PreprocessingJobManager:
    """
    Singleton manager for preprocessing jobs.
    Runs preprocessing in background threads so the API stays responsive.
    Mirrors the design of ScrapingJobManager.
    """

    _instance = None
    _lock = threading.Lock()
    YEAR_MIN = 2018
    YEAR_MAX = 2026

    def __new__(cls):
        with cls._lock:
            if cls._instance is None:
                cls._instance = super().__new__(cls)
                cls._instance._jobs: Dict[str, PreprocessingJob] = {}
                cls._instance._active_job_id: Optional[str] = None
            return cls._instance

    @property
    def has_active_job(self) -> bool:
        """Check if there's currently a running job."""
        if self._active_job_id is None:
            return False
        job = self._jobs.get(self._active_job_id)
        if job is None:
            return False
        return job.status in (PreprocessingJobStatus.PENDING, PreprocessingJobStatus.RUNNING)

    @property
    def active_job(self) -> Optional[PreprocessingJob]:
        """Get the currently active job."""
        if self._active_job_id is None:
            return None
        return self._jobs.get(self._active_job_id)

    def get_job(self, job_id: str) -> Optional[PreprocessingJob]:
        """Get a job by ID."""
        return self._jobs.get(job_id)

    def get_all_jobs(self) -> List[PreprocessingJob]:
        """Get all jobs, newest first."""
        return sorted(
            self._jobs.values(),
            key=lambda j: j.created_at,
            reverse=True,
        )

    def start_job(self, run_id: int) -> str:
        """Create and start a preprocessing job in the background."""
        if self.has_active_job:
            raise ValueError(
                f"Sudah ada preprocessing job yang sedang berjalan (ID: {self._active_job_id}). "
                "Tunggu hingga selesai atau batalkan terlebih dahulu."
            )

        job_id = f"prep_{uuid.uuid4().hex[:8]}"
        job = PreprocessingJob(job_id, run_id)
        self._jobs[job_id] = job
        self._active_job_id = job_id

        logger.info(f"Created preprocessing job {job_id} for run_id={run_id}")

        # Start in background thread
        thread = threading.Thread(
            target=self._run_preprocessing,
            args=(job,),
            name=f"preprocessing-job-{job_id}",
            daemon=True,
        )
        thread.start()
        logger.info(f"Background thread started for preprocessing job {job_id}")

        return job_id

    def cancel_job(self, job_id: str) -> bool:
        """Cancel a running job (best-effort)."""
        job = self._jobs.get(job_id)
        if job is None:
            return False
        if job.status not in (PreprocessingJobStatus.PENDING, PreprocessingJobStatus.RUNNING):
            return False
        job.status = PreprocessingJobStatus.CANCELLED
        job.completed_at = datetime.utcnow()
        job.message = "Dibatalkan oleh user"
        if self._active_job_id == job_id:
            self._active_job_id = None
        logger.info(f"Preprocessing job {job_id} cancelled")
        return True

    def _run_preprocessing(self, job: PreprocessingJob):
        """Worker function that runs in a background thread."""
        try:
            job.mark_running()
            job.update(message="Fetching configurations...", progress=5.0)

            # Load config from DB
            config = database.get_run_config(job.run_id)
            if not config:
                raise ValueError(f"Run config not found for run_id {job.run_id}")

            job.update(message="Loading abstracts from database...", progress=10.0)

            # 1. Load data
            df = database.load_abstracts_from_db()
            total_docs_raw = len(df)

            if total_docs_raw == 0:
                job.mark_completed(0)
                job.update(message="No abstracts found to preprocess")
                return

            job.update(
                message=f"Loaded {total_docs_raw} records...",
                total_documents=total_docs_raw,
                progress=20.0,
            )

            # 1.1 Notebook-aligned dataset cleaning
            before_na = len(df)
            df = df.dropna(subset=["abstract"])
            after_na = len(df)

            before_dedup = len(df)
            # Normalize abstract to make dedup robust against casing/whitespace differences.
            # This keeps behavior aligned with notebook intent (remove semantic duplicates).
            abstract_norm = (
                df["abstract"]
                .astype(str)
                .str.lower()
                .str.replace(r"\s+", " ", regex=True)
                .str.strip()
            )
            df = df.loc[~abstract_norm.duplicated(keep="first")]
            after_dedup = len(df)

            if "year" in df.columns:
                year_series = pd.to_numeric(df["year"], errors="coerce")
                df = df[year_series.between(self.YEAR_MIN, self.YEAR_MAX)]
            after_year = len(df)
            df = df.reset_index(drop=True)

            job.update(
                message=(
                    f"Dataset cleaned: dropna={before_na - after_na}, "
                    f"dedup={before_dedup - after_dedup}, "
                    f"year_filter={after_dedup - after_year}"
                ),
                total_documents=after_year,
                progress=25.0,
            )

            if after_year == 0:
                job.mark_completed(0)
                job.update(message="No records left after dedup/year filtering")
                return

            # 2. Preprocess Data
            preprocessor = TextPreprocessor(
                remove_stopwords=config.get("remove_stopwords", True),
                use_stemming=True,
                min_word_length=config.get("min_word_length", 3),
                language=config.get("language", "indonesian"),
            )

            # Check if cancelled
            if job.status == PreprocessingJobStatus.CANCELLED:
                return

            # 2.1 Build combined_text + dual pipeline (cleaned_text + processed_text)
            job.update(message="Running notebook-aligned dual preprocessing...", progress=40.0)
            df = preprocessor.preprocess_dataframe(
                df,
                text_column="abstract",
                title_column="title",
                conclusion_column="conclusion",
            )

            if len(df) == 0:
                job.mark_completed(0)
                job.update(message="No records left after text preprocessing")
                return

            if job.status == PreprocessingJobStatus.CANCELLED:
                return

            job.update(message="Updating database with clean texts...", progress=85.0)

            # 3. Save into DB
            database.update_processed_texts_in_db(df[["id", "cleaned_text", "processed_text"]])

            # 3.1 Save training-ready snapshot into dedicated dataset table
            database.replace_preprocessed_dataset_in_db(
                df[
                    [
                        "id",
                        "title",
                        "abstract",
                        "conclusion",
                        "year",
                        "cleaned_text",
                        "processed_text",
                    ]
                ]
            )

            # Finalize
            job.mark_completed(int(len(df)))
            logger.info(
                f"Preprocessing job {job.job_id} completed: raw={total_docs_raw}, final={len(df)}"
            )

        except Exception as e:
            logger.error(f"Preprocessing job {job.job_id} failed: {e}")
            job.mark_failed(str(e))
        finally:
            # Clear active job reference if this was the active one
            if self._active_job_id == job.job_id:
                if job.status in (
                    PreprocessingJobStatus.COMPLETED,
                    PreprocessingJobStatus.FAILED,
                    PreprocessingJobStatus.CANCELLED,
                ):
                    self._active_job_id = None


# Singleton instance
preprocessing_job_manager = PreprocessingJobManager()
