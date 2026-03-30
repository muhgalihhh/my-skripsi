"""
Preprocessing Routes
Endpoints for text preprocessing operations.

Architecture:
  - POST /preprocessing/start            → Start preprocessing in background, returns job_id
  - GET  /preprocessing/jobs/{job_id}     → Poll job progress
  - GET  /preprocessing/jobs              → List all jobs
  - GET  /preprocessing/status            → Active job status
  - POST /preprocessing/jobs/{job_id}/cancel → Cancel a running job
"""

from app.models.schemas import PreprocessingStartRequest, PreprocessingJobStatus
from app.services.preprocessing_job_manager import preprocessing_job_manager

from fastapi import APIRouter, HTTPException

router = APIRouter(prefix="/preprocessing", tags=["Preprocessing"])


@router.post("/start")
async def start_preprocessing(request: PreprocessingStartRequest):
    """
    Start text preprocessing in the background.

    Reads data and config from database and updates texts in background.
    Returns immediately with a job_id that can be polled for progress.
    Only one preprocessing job can run at a time.
    """
    try:
        job_id = preprocessing_job_manager.start_job(run_id=request.run_id)
        job = preprocessing_job_manager.get_job(job_id)
        return job.to_dict()
    except ValueError as e:
        # Already a job running
        active = preprocessing_job_manager.active_job
        raise HTTPException(
            status_code=409,
            detail={
                "status": "conflict",
                "message": str(e),
                "active_job": active.to_dict() if active else None,
            },
        )
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Failed to start preprocessing: {str(e)}")


@router.get("/jobs/{job_id}")
async def get_preprocessing_job_status(job_id: str):
    """
    Get the status and progress of a preprocessing job.

    Returns:
        Job status, progress percentage, current step, and optionally the data
    """
    job = preprocessing_job_manager.get_job(job_id)
    if not job:
        raise HTTPException(status_code=404, detail="Job not found")

    return job.to_dict()


@router.get("/jobs")
async def list_jobs():
    """
    List all preprocessing jobs (newest first).
    """
    jobs = preprocessing_job_manager.get_all_jobs()
    return {
        "jobs": [j.to_dict() for j in jobs],
        "has_active_job": preprocessing_job_manager.has_active_job,
        "active_job_id": preprocessing_job_manager._active_job_id,
    }


@router.get("/status")
async def preprocessing_status():
    """
    Get current preprocessing status and whether a job is active.
    """
    active = preprocessing_job_manager.active_job
    return {
        "status": "busy" if preprocessing_job_manager.has_active_job else "idle",
        "active_job": active.to_dict() if active else None,
    }


@router.post("/jobs/{job_id}/cancel")
async def cancel_job(job_id: str):
    """
    Cancel a running preprocessing job.
    """
    success = preprocessing_job_manager.cancel_job(job_id)
    if not success:
        raise HTTPException(
            status_code=400,
            detail={
                "status": "error",
                "message": f"Job {job_id} tidak dapat dibatalkan (sudah selesai atau tidak ditemukan)",
            },
        )

    return {"status": "cancelled", "message": f"Job {job_id} berhasil dibatalkan", "job_id": job_id}
