"""
Application Settings
Loads configuration from .env file using pydantic-settings.

Arsitektur model:
    - Fondasi teori   : IndoBERT (indobenchmark/indobert-base-p1)
    - Sentence Encoder : IndoSBERT-large (denaya/indoSBERT-large)
      → IndoBERT-large yang dilatih ulang dengan Siamese Network
      → Menghasilkan sentence embeddings berkualitas tinggi untuk Bahasa Indonesia
    - Topic Modeling   : BERTopic (neural) vs LDA (baseline tradisional)
"""

from pathlib import Path
from typing import List, Optional

from pydantic import field_validator
from pydantic_settings import BaseSettings, SettingsConfigDict

# Base directory of the fastapi project
BASE_DIR = Path(__file__).resolve().parent.parent.parent


class AppSettings(BaseSettings):
    """General application settings."""

    model_config = SettingsConfigDict(
        env_file=str(BASE_DIR / ".env"),
        env_file_encoding="utf-8",
        extra="ignore",
    )

    APP_NAME: str = "Skripsi Topic Modeling API"
    APP_ENV: str = "development"
    APP_DEBUG: bool = True
    APP_HOST: str = "0.0.0.0"
    APP_PORT: int = 8000

    # CORS
    CORS_ORIGINS: str = "http://localhost:8080,http://localhost:3000,http://localhost:8000,http://127.0.0.1:8000"

    @property
    def cors_origins_list(self) -> List[str]:
        return [origin.strip() for origin in self.CORS_ORIGINS.split(",")]


class PathSettings(BaseSettings):
    """Paths for data and models."""

    model_config = SettingsConfigDict(
        env_file=str(BASE_DIR / ".env"),
        env_file_encoding="utf-8",
        extra="ignore",
    )

    DATA_DIR: str = "./data"
    RAW_DATA_DIR: str = "./data/raw"
    PROCESSED_DATA_DIR: str = "./data/processed"
    MODELS_DIR: str = "./app/ml/trained_models"
    RESULTS_DIR: str = "./data/results"
    EMBEDDINGS_DIR: str = "./data/embeddings"

    def get_data_dir(self) -> Path:
        return BASE_DIR / self.DATA_DIR

    def get_raw_data_dir(self) -> Path:
        return BASE_DIR / self.RAW_DATA_DIR

    def get_processed_data_dir(self) -> Path:
        return BASE_DIR / self.PROCESSED_DATA_DIR

    def get_models_dir(self) -> Path:
        return BASE_DIR / self.MODELS_DIR

    def get_results_dir(self) -> Path:
        return BASE_DIR / self.RESULTS_DIR

    def get_embeddings_dir(self) -> Path:
        return BASE_DIR / self.EMBEDDINGS_DIR

    def ensure_dirs(self) -> None:
        """Create all required directories if they don't exist."""
        for dir_path in [
            self.get_data_dir(),
            self.get_raw_data_dir(),
            self.get_processed_data_dir(),
            self.get_models_dir(),
            self.get_results_dir(),
            self.get_embeddings_dir(),
        ]:
            dir_path.mkdir(parents=True, exist_ok=True)


class ScrapingSettings(BaseSettings):
    """Web scraping configuration."""

    model_config = SettingsConfigDict(
        env_file=str(BASE_DIR / ".env"),
        env_file_encoding="utf-8",
        extra="ignore",
    )

    SCRAPING_BASE_URL: str = "https://repository.unsoed.ac.id"
    SCRAPING_DELAY: float = 2.0
    SCRAPING_MAX_RETRIES: int = 3


class BERTopicSettings(BaseSettings):
    """
    BERTopic core configuration.

    PENTING: embedding_model di sini merujuk ke IndoSBERT-large
    (denaya/indoSBERT-large) yang merupakan IndoBERT-large
    yang telah dilatih ulang menggunakan pendekatan Siamese Network.

    Jadi secara fondasi teori ini tetap "berbasis IndoBERT",
    namun engine sentence-embedding yang dipakai adalah IndoSBERT.
    """

    model_config = SettingsConfigDict(
        env_file=str(BASE_DIR / ".env"),
        env_file_encoding="utf-8",
        extra="ignore",
    )

    # IndoSBERT-large: IndoBERT-large + Siamese Network fine-tuning
    BERTOPIC_EMBEDDING_MODEL: str = "denaya/indoSBERT-large"
    BERTOPIC_MIN_TOPIC_SIZE: int = 10
    BERTOPIC_NR_TOPICS: str = "auto"
    BERTOPIC_TOP_N_WORDS: int = 10
    BERTOPIC_EMBEDDING_BATCH_SIZE: int = 16
    BERTOPIC_SEED: int = 42

    def get_nr_topics(self) -> Optional[int]:
        """Return nr_topics as int or None if 'auto'."""
        if self.BERTOPIC_NR_TOPICS.lower() == "auto":
            return None
        return int(self.BERTOPIC_NR_TOPICS)


class UMAPSettings(BaseSettings):
    """UMAP dimensionality reduction configuration for BERTopic."""

    model_config = SettingsConfigDict(
        env_file=str(BASE_DIR / ".env"),
        env_file_encoding="utf-8",
        extra="ignore",
    )

    UMAP_N_NEIGHBORS: int = 15
    UMAP_N_COMPONENTS: int = 5
    UMAP_MIN_DIST: float = 0.0
    UMAP_METRIC: str = "cosine"
    UMAP_RANDOM_STATE: int = 42


class HDBSCANSettings(BaseSettings):
    """HDBSCAN clustering configuration for BERTopic."""

    model_config = SettingsConfigDict(
        env_file=str(BASE_DIR / ".env"),
        env_file_encoding="utf-8",
        extra="ignore",
    )

    HDBSCAN_MIN_CLUSTER_SIZE: int = 10
    HDBSCAN_MIN_SAMPLES: Optional[int] = None
    HDBSCAN_CLUSTER_SELECTION_METHOD: str = "eom"

    @field_validator("HDBSCAN_MIN_SAMPLES", mode="before")
    @classmethod
    def parse_min_samples(cls, v):
        """Allow empty string in .env to mean None."""
        if v == "" or v is None:
            return None
        return int(v)


class LDASettings(BaseSettings):
    """LDA (Gensim) hyperparameters configuration — baseline model."""

    model_config = SettingsConfigDict(
        env_file=str(BASE_DIR / ".env"),
        env_file_encoding="utf-8",
        extra="ignore",
    )

    LDA_NUM_TOPICS: int = 10
    LDA_PASSES: int = 15
    LDA_ITERATIONS: int = 400
    LDA_CHUNKSIZE: int = 100
    LDA_RANDOM_STATE: int = 42
    LDA_ALPHA: str = "auto"
    LDA_ETA: str = "auto"
    LDA_NO_BELOW: int = 5
    LDA_NO_ABOVE: float = 0.5


class DTASettings(BaseSettings):
    """Dynamic Topic Analysis configuration."""

    model_config = SettingsConfigDict(
        env_file=str(BASE_DIR / ".env"),
        env_file_encoding="utf-8",
        extra="ignore",
    )

    DTA_YEAR_START: int = 2019
    DTA_YEAR_END: int = 2025
    DTA_EVOLUTION_TUNING: bool = True
    DTA_GLOBAL_TUNING: bool = True


class TrainingSettings(BaseSettings):
    """General training configuration."""

    model_config = SettingsConfigDict(
        env_file=str(BASE_DIR / ".env"),
        env_file_encoding="utf-8",
        extra="ignore",
    )

    TRAINING_RANDOM_STATE: int = 42


# ============================================
# Singleton instances
# ============================================
app_settings = AppSettings()
path_settings = PathSettings()
scraping_settings = ScrapingSettings()
bertopic_settings = BERTopicSettings()
umap_settings = UMAPSettings()
hdbscan_settings = HDBSCANSettings()
lda_settings = LDASettings()
dta_settings = DTASettings()
training_settings = TrainingSettings()
