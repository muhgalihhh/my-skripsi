"""API security helpers."""

from hmac import compare_digest

from app.core.config import app_settings
from fastapi import Header, HTTPException


def verify_api_key(x_api_key: str | None = Header(default=None, alias="X-API-Key")) -> None:
    """Validate service API key for protected routes."""
    if not app_settings.FASTAPI_REQUIRE_API_KEY:
        return

    expected = str(app_settings.FASTAPI_API_KEY or "").strip()
    if expected == "":
        raise HTTPException(status_code=500, detail="FASTAPI_API_KEY belum dikonfigurasi.")

    provided = str(x_api_key or "").strip()
    if provided == "" or not compare_digest(provided, expected):
        raise HTTPException(status_code=401, detail="Unauthorized")
