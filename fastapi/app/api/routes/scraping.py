"""Scraping API routes."""

from app.core.config import scraping_settings
from app.core.logging import get_logger
from app.models.schemas import ScrapingRequest
from app.services.scraping import ScrapingService
from app.services.scraping_job_manager import JobStatus, job_manager

from fastapi import APIRouter, HTTPException

router = APIRouter(prefix="/scraping", tags=["Scraping"])

logger = get_logger("scraping_route")

# Service singleton
scraping_service = ScrapingService()


def _run_scraping_job(job):
    """
    Worker function that runs in a background thread.
    Updates job progress as it goes.
    """
    try:
        documents = scraping_service.scrape(
            start_year=job.start_year,
            end_year=job.end_year,
            max_pages=job.max_pages,
            job=job,  # Pass job for progress tracking
        )

        # Check if cancelled mid-way
        if job.status == JobStatus.CANCELLED:
            logger.info(f"Job {job.job_id} was cancelled during execution")
            return

        # Summary per year
        year_counts = {}
        for doc in documents:
            year = str(doc.get("year", doc.get("Tahun", "unknown")))
            year_counts[year] = year_counts.get(year, 0) + 1

        result = {
            "status": "success",
            "total_documents": len(documents),
            "documents_per_year": year_counts,
            "message": f"Berhasil scraping {len(documents)} dokumen skripsi.",
            "data": documents,
        }

        job.mark_completed(result)
        logger.info(f"Job {job.job_id} completed: {len(documents)} documents")

    except Exception as e:
        logger.error(f"Job {job.job_id} failed: {e}")
        job.mark_failed(str(e))


@router.post("/start", tags=["Scraping"])
async def start_scraping(request: ScrapingRequest):
    """
    Start scraping thesis data in the background.

    Returns immediately with a job_id that can be polled for progress.
    Only one scraping job can run at a time.
    """
    logger.info(
        f"Scraping request received: {request.start_year}-{request.end_year}, "
        f"max_pages={request.max_pages}"
    )

    try:
        job = job_manager.create_job(
            start_year=request.start_year,
            end_year=request.end_year,
            max_pages=request.max_pages,
        )
    except ValueError as e:
        # Already a job running
        active = job_manager.active_job
        raise HTTPException(
            status_code=409,
            detail={
                "status": "conflict",
                "message": str(e),
                "active_job": active.to_dict() if active else None,
            },
        )

    # Start scraping in background thread
    job_manager.start_job_in_background(job, _run_scraping_job)

    return {
        "status": "accepted",
        "message": "Scraping job dimulai. Gunakan endpoint /jobs/{job_id} untuk memantau progress.",
        "job_id": job.job_id,
        "poll_url": f"/api/v1/scraping/jobs/{job.job_id}",
    }


@router.get("/jobs/{job_id}", tags=["Scraping"])
async def get_job_status(job_id: str, include_data: bool = False, include_monitoring: bool = False):
    """
    Get the status and progress of a scraping job.

    Args:
        job_id: The job ID returned from /start
        include_data: If true and job is completed, include the scraped documents
        include_monitoring: If true, include detailed monitoring data (found urls, scraped items)

    Returns:
        Job status, progress percentage, current step, and optionally the data
    """
    job = job_manager.get_job(job_id)
    if job is None:
        raise HTTPException(status_code=404, detail={"status": "error", "message": f"Job {job_id} tidak ditemukan"})

    return job.to_dict(include_data=include_data, include_monitoring=include_monitoring)


@router.get("/jobs", tags=["Scraping"])
async def list_jobs():
    """
    List all scraping jobs (newest first).
    """
    jobs = job_manager.get_all_jobs()
    return {
        "jobs": [j.to_dict() for j in jobs],
        "has_active_job": job_manager.has_active_job,
        "active_job_id": job_manager._active_job_id,
    }


@router.post("/jobs/{job_id}/cancel", tags=["Scraping"])
async def cancel_job(job_id: str):
    """
    Cancel a running scraping job.
    """
    success = job_manager.cancel_job(job_id)
    if not success:
        raise HTTPException(
            status_code=400,
            detail={"status": "error", "message": f"Job {job_id} tidak dapat dibatalkan (sudah selesai atau tidak ditemukan)"},
        )

    return {"status": "cancelled", "message": f"Job {job_id} berhasil dibatalkan", "job_id": job_id}


@router.get("/status", tags=["Scraping"])
async def scraping_status():
    """
    Get current scraping status and configuration.
    """
    active = job_manager.active_job
    return {
        "status": "busy" if job_manager.has_active_job else "idle",
        "active_job": active.to_dict() if active else None,
        "base_url": scraping_settings.SCRAPING_BASE_URL,
        "delay_between_requests": scraping_settings.SCRAPING_DELAY,
        "max_retries": scraping_settings.SCRAPING_MAX_RETRIES,
    }

