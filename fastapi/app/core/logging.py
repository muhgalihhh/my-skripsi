"""
Logging Configuration
Uses loguru for structured logging.
"""

import sys
from pathlib import Path

from app.core.config import BASE_DIR, app_settings
from loguru import logger

# Remove default handler
logger.remove()

# Log format
LOG_FORMAT = (
    "<green>{time:YYYY-MM-DD HH:mm:ss}</green> | "
    "<level>{level: <8}</level> | "
    "<cyan>{name}</cyan>:<cyan>{function}</cyan>:<cyan>{line}</cyan> | "
    "<level>{message}</level>"
)

# Console handler
logger.add(
    sys.stderr,
    format=LOG_FORMAT,
    level="DEBUG" if app_settings.APP_DEBUG else "INFO",
    colorize=True,
)

# File handler
log_dir = BASE_DIR / "logs"
log_dir.mkdir(parents=True, exist_ok=True)

logger.add(
    str(log_dir / "app_{time:YYYY-MM-DD}.log"),
    format=LOG_FORMAT,
    level="DEBUG",
    rotation="1 day",
    retention="30 days",
    compression="zip",
)

# Training-specific log
logger.add(
    str(log_dir / "training_{time:YYYY-MM-DD}.log"),
    format=LOG_FORMAT,
    level="INFO",
    rotation="1 day",
    retention="60 days",
    filter=lambda record: "training" in record["extra"].get("context", ""),
)


def get_logger(name: str = "app"):
    """Get a logger instance with context."""
    return logger.bind(context=name)
