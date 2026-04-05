"""
Pydantic Schemas for API request/response models.

Arsitektur:
    BERTopic menggunakan IndoSBERT-large (denaya/indoSBERT-large) sebagai
    sentence encoder. IndoSBERT sendiri adalah IndoBERT-large yang dilatih
    ulang menggunakan Siamese Network, sehingga fondasi teorinya tetap
    berbasis IndoBERT.
"""

from datetime import datetime
from enum import Enum
from typing import Any, Dict, List, Literal, Optional

from pydantic import BaseModel, Field, field_validator

# ============================================
# Enums
# ============================================

class ModelType(str, Enum):
    """Supported topic model types."""
    BERTOPIC = "bertopic"
    LDA = "lda"


class TrainingStatus(str, Enum):
    """Status of a training job."""
    PENDING = "pending"
    RUNNING = "running"
    COMPLETED = "completed"
    FAILED = "failed"


class TrendDirection(str, Enum):
    """Direction of a topic trend."""
    EMERGING = "emerging"
    DECLINING = "declining"
    STABLE = "stable"


# ============================================
# Scraping Schemas
# ============================================

class ScrapingRequest(BaseModel):
    """Request to start a scraping job."""
    start_year: int = Field(2019, ge=2000, le=2030, description="Start year for scraping")
    end_year: int = Field(2025, ge=2000, le=2030, description="End year for scraping")
    max_pages: Optional[int] = Field(None, ge=1, description="Max pages to scrape per year")


class ScrapedDocument(BaseModel):
    """Single scraped document."""
    title: str
    abstract: str
    year: int
    author: Optional[str] = None
    url: Optional[str] = None


class ScrapingResponse(BaseModel):
    """Response from scraping job."""
    status: str
    total_documents: int
    documents_per_year: Dict[str, int]
    message: str


# ============================================
# Preprocessing Schemas
# ============================================

class PreprocessingStartRequest(BaseModel):
    """Request to start a preprocessing background job."""
    run_id: int = Field(description="Run ID from the database to load config")

class PreprocessingJobStatus(BaseModel):
    """Response containing preprocessing job status."""
    job_id: str
    status: str
    progress: float
    message: str
    total_documents: int = 0
    processed: int = 0
    error: Optional[str] = None


# ============================================
# Hyperparameter Schemas
# ============================================

class UMAPHyperparameters(BaseModel):
    """UMAP dimensionality reduction parameters."""
    n_neighbors: int = Field(75, ge=2, description="Number of neighbors for local structure")
    n_components: int = Field(5, ge=2, description="Target dimensions")
    min_dist: float = Field(0.0, ge=0.0, le=1.0, description="Minimum distance between points")
    metric: str = Field("cosine", description="Distance metric")
    random_state: int = 42


class HDBSCANHyperparameters(BaseModel):
    """HDBSCAN clustering parameters."""
    min_cluster_size: int = Field(12, ge=2, description="Minimum cluster size")
    min_samples: Optional[int] = Field(1, ge=1, description="Min samples")
    cluster_selection_method: str = Field("eom", description="'eom' or 'leaf'")


class BERTopicHyperparameters(BaseModel):
    """
    Hyperparameters for BERTopic model.

    Default embedding_model = denaya/indoSBERT-large
    → IndoBERT-large architecture + Siamese Network training
    → Produces 768-dim sentence embeddings optimized for Bahasa Indonesia
    """
    # Sentence encoder: IndoSBERT-large (Siamese from IndoBERT-large)
    embedding_model: str = Field(
        "denaya/indoSBERT-large",
        description=(
            "Sentence-transformer model. Default: denaya/indoSBERT-large "
            "(IndoBERT-large re-trained with Siamese Network)"
        ),
    )
    min_topic_size: int = Field(10, ge=2, description="Minimum topic size for BERTopic")
    nr_topics: Optional[int | Literal["auto"]] = Field(
        None,
        description="Number of topics. Use integer or 'auto'.",
    )
    top_n_words: int = Field(10, ge=1, description="Number of words per topic representation")
    n_gram_range: List[int] = Field(default=[1, 2], description="Vectorizer n-gram range [min, max]")
    vectorizer_min_df: int | float = Field(2, description="CountVectorizer min_df")
    vectorizer_max_df: int | float = Field(0.95, description="CountVectorizer max_df")
    vectorizer_token_pattern: str = Field(r"(?u)\b\w{3,}\b", description="CountVectorizer token pattern")
    vectorizer_fallback_min_df: int = Field(1, ge=1, description="Fallback min_df when DF constraints fail")
    vectorizer_fallback_max_df: float = Field(1.0, gt=0.0, le=1.0, description="Fallback max_df when DF constraints fail")

    coherence_type: str = Field("c_v", description="Coherence metric type")
    coherence_tokenization: Literal["vectorizer"] = Field(
        "vectorizer",
        description="Locked coherence tokenization to vectorizer analyzer",
    )
    coherence_dict_no_below: int = Field(3, ge=1, description="Dictionary filter no_below")
    coherence_dict_no_above: float = Field(0.95, gt=0.0, le=1.0, description="Dictionary filter no_above")

    reduce_outliers: bool = Field(True, description="Enable reduce_outliers during training")
    reduce_outliers_threshold_ctfidf: float = Field(0.1, ge=0.0, le=1.0)
    reduce_outliers_use_distributions: bool = Field(True)
    reduce_outliers_threshold_distributions: float = Field(0.05, ge=0.0, le=1.0)

    use_mmr_representation: bool = Field(True, description="Enable MMR representation model")
    mmr_diversity: float = Field(0.3, ge=0.0, le=1.0)

    embedding_batch_size: int = Field(16, ge=1, description="Batch size for embedding computation")
    seed: int = Field(42, description="Random seed for reproducibility")
    # Sub-component parameters
    umap_params: UMAPHyperparameters = Field(default_factory=UMAPHyperparameters)
    hdbscan_params: HDBSCANHyperparameters = Field(default_factory=HDBSCANHyperparameters)

    @field_validator("n_gram_range")
    @classmethod
    def validate_ngram_range(cls, value: List[int]) -> List[int]:
        if len(value) != 2:
            raise ValueError("n_gram_range must contain exactly two integers")
        if value[0] < 1 or value[1] < value[0]:
            raise ValueError("Invalid n_gram_range")
        return value

    @field_validator("nr_topics")
    @classmethod
    def validate_nr_topics(cls, value):
        if value is None or value == "auto":
            return value
        if isinstance(value, int) and value >= 2:
            return value
        raise ValueError("nr_topics must be >=2, null, or 'auto'")


class LDAHyperparameters(BaseModel):
    """Hyperparameters for LDA model (Gensim) — baseline tradisional."""
    num_topics: int = Field(10, ge=2, description="Number of topics")
    passes: int = Field(15, ge=1, description="Number of passes through the corpus")
    iterations: int = Field(400, ge=1, description="Max iterations for convergence")
    chunksize: int = Field(100, ge=1, description="Number of docs per training chunk")
    random_state: int = 42
    alpha: str = Field("auto", description="Document-topic density ('auto', 'symmetric', or float)")
    eta: str = Field("auto", description="Topic-word density ('auto', 'symmetric', or float)")
    no_below: int = Field(5, ge=1, description="Filter tokens appearing in fewer than N docs")
    no_above: float = Field(0.5, gt=0.0, le=1.0, description="Filter tokens appearing in more than fraction of docs")


# ============================================
# Training Schemas
# ============================================

class TrainingRequest(BaseModel):
    """Request to start a training job."""
    model_type: ModelType
    bertopic_params: Optional[BERTopicHyperparameters] = None
    lda_params: Optional[LDAHyperparameters] = None
    description: Optional[str] = Field(None, description="Description for this training run")


class TrainingStatusResponse(BaseModel):
    """Response for training status check."""
    job_id: str
    status: TrainingStatus
    model_type: ModelType
    progress: float = Field(0.0, ge=0.0, le=100.0, description="Progress percentage")
    message: str
    started_at: Optional[datetime] = None
    completed_at: Optional[datetime] = None
    error: Optional[str] = None


class TrainingResultResponse(BaseModel):
    """Response containing training results."""
    job_id: str
    model_type: ModelType
    status: TrainingStatus
    # Metrics
    coherence_score: Optional[float] = None
    topic_diversity: Optional[float] = None
    num_topics_found: Optional[int] = None
    # Topics
    topics: Optional[List[Dict[str, Any]]] = None
    # Metadata
    training_duration_seconds: Optional[float] = None
    hyperparameters: Optional[Dict[str, Any]] = None
    created_at: Optional[datetime] = None


# ============================================
# Evaluation / Comparison Schemas
# ============================================

class ComparisonRequest(BaseModel):
    """Request to compare BERTopic vs LDA."""
    bertopic_job_id: str
    lda_job_id: str


class ComparisonResponse(BaseModel):
    """
    Response from model comparison.
    Metrics: Topic Coherence (C_v) and Topic Diversity.
    """
    bertopic_coherence: float
    lda_coherence: float
    bertopic_diversity: float
    lda_diversity: float
    bertopic_num_topics: int
    lda_num_topics: int
    summary: str


# ============================================
# Dynamic Topic Analysis (DTA) Schemas
# ============================================

class DTARequest(BaseModel):
    """
    Request for Dynamic Topic Analysis.

    DTA menganalisis evolusi topik per tahun menggunakan
    BERTopic.topics_over_time() untuk mendeteksi topik
    emerging, declining, dan stable.
    """
    job_id: str = Field(description="BERTopic training job ID to use")
    year_start: int = Field(2019, ge=2000, le=2030)
    year_end: int = Field(2025, ge=2000, le=2030)
    evolution_tuning: bool = Field(
        True,
        description="Fine-tune topic representation per time bin",
    )
    global_tuning: bool = Field(
        True,
        description="Use global topic representation as anchor",
    )


class TopicTrend(BaseModel):
    """Single topic trend over time."""
    topic_id: int
    topic_label: str
    top_words: List[str]
    trend: TrendDirection
    frequency_per_year: Dict[str, float]
    trend_slope: Optional[float] = Field(None, description="Linear regression slope of frequency")


class DTAResponse(BaseModel):
    """Response from Dynamic Topic Analysis."""
    job_id: str
    model_type: ModelType = ModelType.BERTOPIC
    total_topics: int
    year_range: List[int]
    emerging_topics: List[TopicTrend]
    declining_topics: List[TopicTrend]
    stable_topics: List[TopicTrend]
    topics_over_time_raw: Optional[List[Dict[str, Any]]] = Field(
        None,
        description="Raw topics_over_time DataFrame as list of dicts",
    )


# ============================================
# General Response Schemas
# ============================================

class HealthResponse(BaseModel):
    """Health check response."""
    status: str = "ok"
    app_name: str
    version: str
    environment: str
    embedding_model: str = Field(
        description="Active sentence-transformer model (IndoSBERT)"
    )


class ErrorResponse(BaseModel):
    """Standard error response."""
    detail: str
    error_code: Optional[str] = None
