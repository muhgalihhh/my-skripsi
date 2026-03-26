"""
Scraping Job Manager
Manages background scraping jobs with progress tracking.
Ensures health endpoint stays responsive while scraping runs in a separate thread.
"""

import threading
import uuid
from datetime import datetime
from enum import Enum
from typing import Any, Dict, List, Optional

from app.core.logging import get_logger

logger = get_logger("scraping_job_manager")


class JobStatus(str, Enum):
    PENDING = "pending"
    RUNNING = "running"
    COMPLETED = "completed"
    FAILED = "failed"
    CANCELLED = "cancelled"


class ScrapingJob:
    """Represents a single scraping job with progress tracking."""

    def __init__(self, job_id: str, start_year: int, end_year: int, max_pages: Optional[int] = None):
        self.job_id = job_id
        self.start_year = start_year
        self.end_year = end_year
        self.max_pages = max_pages
        self.status = JobStatus.PENDING
        self.progress = 0  # 0-100
        self.current_step = ""
        self.total_detail_urls = 0
        self.scraped_count = 0
        self.documents_found = 0
        self.error_message: Optional[str] = None
        self.result: Optional[Dict[str, Any]] = None
        self.created_at = datetime.utcnow()
        self.started_at: Optional[datetime] = None
        self.completed_at: Optional[datetime] = None
        self._lock = threading.Lock()
        # Detailed tracking for monitoring modal
        self.found_urls: List[Dict[str, str]] = []  # [{url, title, author, year}]
        self.scraped_items: List[Dict[str, str]] = []  # [{title, author, year, status}]
        self.skipped_count = 0  # docs that failed to parse
        self.filtered_count = 0  # docs filtered by year range

    def _add_found_url(self, url: str, title: str = "", author: str = "", year: str = ""):
        """Add a found URL to tracking list (called during listing phase)."""
        self.found_urls.append({
            "url": url,
            "title": title,
            "author": author,
            "year": year,
        })

    def _add_scraped_item(self, title: str, author: str = "", year: str = "", status: str = "success"):
        """Add a scraped item to tracking list (called during detail phase)."""
        self.scraped_items.append({
            "title": title[:100] if title else "Unknown",
            "author": author,
            "year": year,
            "status": status,
        })

    def update_progress(
        self,
        scraped_count: int,
        total: int,
        current_step: str = "",
    ):
        """Thread-safe progress update."""
        with self._lock:
            self.scraped_count = scraped_count
            self.total_detail_urls = total
            self.current_step = current_step
            if total > 0:
                # Listing phase is 0-20%, scraping phase is 20-99%
                self.progress = min(20 + int((scraped_count / total) * 79), 99)

    def update_listing_progress(self, page: int, total_pages: int):
        """Update progress during listing phase (0-20%)."""
        with self._lock:
            self.current_step = f"Mengumpulkan URL halaman {page}/{total_pages}"
            if total_pages > 0:
                self.progress = min(int((page / total_pages) * 20), 20)

    def mark_running(self):
        with self._lock:
            self.status = JobStatus.RUNNING
            self.started_at = datetime.utcnow()
            self.current_step = "Memulai scraping..."

    def mark_completed(self, result: Dict[str, Any]):
        with self._lock:
            self.status = JobStatus.COMPLETED
            self.completed_at = datetime.utcnow()
            self.progress = 100
            self.result = result
            self.current_step = "Selesai"
            self.documents_found = result.get("total_documents", 0)

    def mark_failed(self, error: str):
        with self._lock:
            self.status = JobStatus.FAILED
            self.completed_at = datetime.utcnow()
            self.error_message = error
            self.current_step = f"Gagal: {error}"

    def to_dict(self, include_data: bool = False, include_monitoring: bool = False) -> Dict[str, Any]:
        """Convert job to dictionary for API response."""
        with self._lock:
            result = {
                "job_id": self.job_id,
                "status": self.status.value,
                "progress": self.progress,
                "current_step": self.current_step,
                "start_year": self.start_year,
                "end_year": self.end_year,
                "max_pages": self.max_pages,
                "total_detail_urls": self.total_detail_urls,
                "scraped_count": self.scraped_count,
                "documents_found": self.documents_found,
                "skipped_count": self.skipped_count,
                "filtered_count": self.filtered_count,
                "error_message": self.error_message,
                "created_at": self.created_at.isoformat() if self.created_at else None,
                "started_at": self.started_at.isoformat() if self.started_at else None,
                "completed_at": self.completed_at.isoformat() if self.completed_at else None,
            }
            if include_data and self.result:
                result["result"] = self.result
            if include_monitoring:
                # Return last 50 items for monitoring (to keep response small)
                result["found_urls"] = self.found_urls[-50:] if self.found_urls else []
                result["found_urls_total"] = len(self.found_urls)
                result["scraped_items"] = self.scraped_items[-50:] if self.scraped_items else []
                result["scraped_items_total"] = len(self.scraped_items)
            return result


class ScrapingJobManager:
    """
    Singleton manager for scraping jobs.
    Runs scraping in background threads so the API stays responsive.
    """

    _instance = None
    _lock = threading.Lock()

    def __new__(cls):
        with cls._lock:
            if cls._instance is None:
                cls._instance = super().__new__(cls)
                cls._instance._jobs: Dict[str, ScrapingJob] = {}
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
        return job.status in (JobStatus.PENDING, JobStatus.RUNNING)

    @property
    def active_job(self) -> Optional[ScrapingJob]:
        """Get the currently active job."""
        if self._active_job_id is None:
            return None
        return self._jobs.get(self._active_job_id)

    def create_job(
        self,
        start_year: int,
        end_year: int,
        max_pages: Optional[int] = None,
    ) -> ScrapingJob:
        """Create a new scraping job. Only one job can run at a time."""
        if self.has_active_job:
            raise ValueError(
                f"Sudah ada scraping job yang sedang berjalan (ID: {self._active_job_id}). "
                "Tunggu hingga selesai atau batalkan terlebih dahulu."
            )

        job_id = str(uuid.uuid4())[:8]
        job = ScrapingJob(job_id, start_year, end_year, max_pages)
        self._jobs[job_id] = job
        self._active_job_id = job_id

        logger.info(f"Created scraping job {job_id}: years {start_year}-{end_year}")
        return job

    def get_job(self, job_id: str) -> Optional[ScrapingJob]:
        """Get a job by ID."""
        return self._jobs.get(job_id)

    def get_all_jobs(self) -> List[ScrapingJob]:
        """Get all jobs, newest first."""
        return sorted(
            self._jobs.values(),
            key=lambda j: j.created_at,
            reverse=True,
        )

    def start_job_in_background(self, job: ScrapingJob, scraping_func):
        """
        Start the scraping function in a background thread.
        scraping_func should accept (job,) and update job progress internally.
        """
        def _worker():
            try:
                job.mark_running()
                logger.info(f"Job {job.job_id} started in background thread")
                scraping_func(job)
            except Exception as e:
                logger.error(f"Job {job.job_id} failed: {e}")
                job.mark_failed(str(e))
            finally:
                # Clear active job reference if this was the active one
                if self._active_job_id == job.job_id:
                    if job.status in (JobStatus.COMPLETED, JobStatus.FAILED, JobStatus.CANCELLED):
                        self._active_job_id = None

        thread = threading.Thread(
            target=_worker,
            name=f"scraping-job-{job.job_id}",
            daemon=True,
        )
        thread.start()
        logger.info(f"Background thread started for job {job.job_id}")

    def cancel_job(self, job_id: str) -> bool:
        """Cancel a running job (best-effort)."""
        job = self._jobs.get(job_id)
        if job is None:
            return False
        if job.status not in (JobStatus.PENDING, JobStatus.RUNNING):
            return False
        job.status = JobStatus.CANCELLED
        job.completed_at = datetime.utcnow()
        job.current_step = "Dibatalkan oleh user"
        if self._active_job_id == job_id:
            self._active_job_id = None
        logger.info(f"Job {job_id} cancelled")
        return True


# Singleton instance
job_manager = ScrapingJobManager()
