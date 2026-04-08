"""FastAPI application entry point."""

from contextlib import asynccontextmanager

from app.api.routes.evaluation import router as evaluation_router
from app.api.routes.health import router as health_router
from app.api.routes.preprocessing import router as preprocessing_router
from app.api.routes.scraping import router as scraping_router
from app.api.routes.training import router as training_router
from app.core.config import app_settings, path_settings
from app.core.logging import get_logger
from fastapi.middleware.cors import CORSMiddleware

from fastapi import FastAPI

logger = get_logger("main")


@asynccontextmanager
async def lifespan(app: FastAPI):
    """Application startup and shutdown events."""
    logger.info(f"Starting {app_settings.APP_NAME}...")
    path_settings.ensure_dirs()
    logger.info("All required directories created/verified")
    logger.info(f"Environment: {app_settings.APP_ENV}")
    yield
    logger.info("Shutting down...")

app = FastAPI(
    title=app_settings.APP_NAME,
    description=(
        "API Service untuk analisis evolusi topik riset skripsi "
        "mahasiswa Informatika UNSOED (2019-2025). "
        "Menggunakan BERTopic (IndoSBERT-large / denaya/indoSBERT-large) "
        "dan LDA (Gensim) untuk topic modeling, "
        "serta Dynamic Topic Analysis (DTA) untuk tren topik per tahun."
    ),
    version="0.1.0",
    lifespan=lifespan,
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=app_settings.cors_origins_list,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

API_V1_PREFIX = "/api/v1"

app.include_router(health_router, prefix=API_V1_PREFIX)
app.include_router(scraping_router, prefix=API_V1_PREFIX)
app.include_router(preprocessing_router, prefix=API_V1_PREFIX)
app.include_router(training_router, prefix=API_V1_PREFIX)
app.include_router(evaluation_router, prefix=API_V1_PREFIX)

@app.get("/", tags=["Root"])
async def root():
    """Root endpoint - API info."""
    return {
        "app": app_settings.APP_NAME,
        "version": "0.1.0",
        "docs": "/docs",
        "redoc": "/redoc",
        "health": f"{API_V1_PREFIX}/health",
    }

if __name__ == "__main__":
    from granian import Granian

    server = Granian(
        target="app.main:app",
        address=app_settings.APP_HOST,
        port=app_settings.APP_PORT,
        interface="asgi",
        reload=app_settings.APP_DEBUG,
        workers=1,
        threads=4,
        log_level="info",
    )
    server.serve()
