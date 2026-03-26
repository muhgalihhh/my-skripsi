"""
Health Check Route
"""

from app.core.config import app_settings, bertopic_settings
from app.models.schemas import HealthResponse

from fastapi import APIRouter

router = APIRouter()


@router.get("/health", response_model=HealthResponse, tags=["Health"])
async def health_check():
    """Check if the API is running."""
    return HealthResponse(
        status="ok",
        app_name=app_settings.APP_NAME,
        version="0.1.0",
        environment=app_settings.APP_ENV,
        embedding_model=bertopic_settings.BERTOPIC_EMBEDDING_MODEL,
    )
