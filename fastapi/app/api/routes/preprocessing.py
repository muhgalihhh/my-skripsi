"""Preprocessing API routes."""

from app.models.schemas import PreprocessingStartRequest
from app.services.preprocessing_job_manager import preprocessing_job_manager

from fastapi import APIRouter, HTTPException

router = APIRouter(prefix="/preprocessing", tags=["Preprocessing"])


@router.post("/start")
async def start_preprocessing(request: PreprocessingStartRequest):
    """Memulai proses preprocessing teks di background."""
    try:
        job_id = preprocessing_job_manager.start_job(run_id=request.run_id)
        job = preprocessing_job_manager.get_job(job_id)
        return job.to_dict()
    except ValueError as e:
        # Job lain sedang berjalan
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
    """Mendapatkan status dan progress dari job preprocessing."""
    job = preprocessing_job_manager.get_job(job_id)
    if not job:
        raise HTTPException(status_code=404, detail="Job not found")

    return job.to_dict()


@router.get("/jobs/{job_id}/dropped")
async def get_preprocessing_job_dropped(job_id: str):
    """Mendapatkan laporan record yang gagal di-preprocess."""
    job = preprocessing_job_manager.get_job(job_id)
    if not job:
        raise HTTPException(status_code=404, detail="Job not found")

    info = job.to_dict()
    return {
        "job_id": info["job_id"],
        "status": info["status"],
        "dropped_summary": info.get("dropped_summary", {}),
        "dropped_records_total": info.get("dropped_records_total", 0),
        "dropped_records_sample": info.get("dropped_records_sample", []),
    }



@router.post("/jobs/{job_id}/cancel")
async def cancel_job(job_id: str):
    """Membatalkan job preprocessing yang sedang berjalan."""
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
