"""Pydantic schemas for API request/response models."""

from datetime import datetime
from enum import Enum
from typing import Any, Dict, List, Literal, Optional

from pydantic import BaseModel, Field, field_validator


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


class ScrapingRequest(BaseModel):
    """Request to start a scraping job."""
    start_year: int = Field(2019, ge=2000, le=2030, description="Start year for scraping")
    end_year: int = Field(2026, ge=2000, le=2030, description="End year for scraping")
    max_pages: Optional[int] = Field(None, ge=1, description="Max pages to scrape per year")


class PreprocessingStartRequest(BaseModel):
    """Request to start a preprocessing background job."""
    run_id: int = Field(description="Run ID from the database to load config")

class UMAPHyperparameters(BaseModel):
    """UMAP dimensionality reduction parameters."""
    n_neighbors: int = Field(40, ge=2, description="Number of neighbors for local structure")
    n_components: int = Field(5, ge=2, description="Target dimensions")
    min_dist: float = Field(0.0, ge=0.0, le=1.0, description="Minimum distance between points")
    metric: str = Field("cosine", description="Distance metric")
    random_state: int = 42


class HDBSCANHyperparameters(BaseModel):
    """HDBSCAN clustering parameters."""
    min_cluster_size: int = Field(16, ge=2, description="Minimum cluster size")
    min_samples: Optional[int] = Field(1, ge=1, description="Min samples")
    metric: str = Field("euclidean", description="Distance metric for HDBSCAN")
    cluster_selection_method: str = Field("eom", description="'eom' or 'leaf'")


class BERTopicHyperparameters(BaseModel):
    """
    Hyperparameters for BERTopic model.

    Default embedding_model = denaya/indoSBERT-large
    -> IndoBERT-large architecture + Siamese Network training
    -> Produces 256-dim sentence embeddings optimized for Indonesian text
    """
    # Sentence encoder: IndoSBERT-large (Siamese from IndoBERT-large)
    embedding_model: str = Field(
        "denaya/indoSBERT-large",
        description=(
            "Sentence-transformer model. Default: denaya/indoSBERT-large "
            "(IndoBERT-large re-trained with Siamese Network)"
        ),
    )
    min_topic_size: int = Field(12, ge=2, description="Minimum topic size for BERTopic")
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

    @field_validator("vectorizer_max_df", mode="before")
    @classmethod
    def normalize_vectorizer_max_df(cls, value):
        """Keep ratio-like max_df values (0 < x <= 1) as float semantics."""
        if value is None:
            return value

        try:
            num = float(value)
        except (TypeError, ValueError):
            return value

        if 0.0 < num <= 1.0:
            return float(num)

        if abs(num - round(num)) < 1e-9:
            return int(round(num))

        return num

    @field_validator("nr_topics")
    @classmethod
    def validate_nr_topics(cls, value):
        if value is None or value == "auto":
            return value
        if isinstance(value, int) and value >= 2:
            return value
        raise ValueError("nr_topics must be >=2, null, or 'auto'")


class LDAHyperparameters(BaseModel):
    """Hyperparameters for the LDA (Gensim) baseline model."""
    num_topics: int = Field(12, ge=2, description="Number of topics")
    passes: int = Field(20, ge=1, description="Number of passes through the corpus")
    iterations: int = Field(300, ge=1, description="Max iterations for convergence")
    chunksize: int = Field(100, ge=1, description="Number of docs per training chunk")
    random_state: int = 42
    alpha: str | float = Field("asymmetric", description="Document-topic density ('auto', 'symmetric', 'asymmetric', or float)")
    eta: str | float | None = Field(None, description="Topic-word density ('auto', 'symmetric', 'asymmetric', float, or null)")
    no_below: int = Field(2, ge=1, description="Filter tokens appearing in fewer than N docs")
    no_above: float = Field(0.95, gt=0.0, le=1.0, description="Filter tokens appearing in more than fraction of docs")


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


class DTARequest(BaseModel):
    """
    Request for Dynamic Topic Analysis.

    Tracks topic evolution by year using BERTopic.topics_over_time()
    and classifies trends as emerging, declining, or stable.
    """
    job_id: str = Field(description="BERTopic training job ID to use")
    year_start: Optional[int] = Field(
        None,
        ge=2000,
        le=2030,
        description="Start year (optional). If omitted, uses minimum available year from dataset.",
    )
    year_end: Optional[int] = Field(
        None,
        ge=2000,
        le=2030,
        description="End year (optional). If omitted, uses maximum available year from dataset.",
    )
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


class TopicInferenceRequest(BaseModel):
    """Request to infer the most relevant BERTopic topic from free text."""
    text: str = Field(
        ...,
        min_length=3,
        max_length=5000,
        description="Query text to map into BERTopic topic space",
    )
    top_n_topics: int = Field(
        5,
        ge=1,
        le=10,
        description="Number of similar topics to return",
    )

    @field_validator("text")
    @classmethod
    def validate_text(cls, value: str) -> str:
        normalized = value.strip()
        if len(normalized) < 3:
            raise ValueError("text must contain at least 3 non-space characters")
        return normalized


class TopicInferenceItem(BaseModel):
    """One inferred topic candidate for the given query."""
    topic_id: int
    similarity: float = Field(..., ge=0.0, le=1.0)
    top_words: List[str] = Field(default_factory=list)


class TopicInferenceResponse(BaseModel):
    """Inference response containing primary and candidate BERTopic matches."""
    job_id: str
    query: str
    topic_id: int
    topic_similarity: float = Field(..., ge=0.0, le=1.0)
    top_words: List[str] = Field(default_factory=list)
    topic_distribution: List[TopicInferenceItem] = Field(default_factory=list)


class TopicCurationSuggestionRequest(BaseModel):
    """Request to generate AI suggestion for topic curation."""

    keywords: List[str] = Field(
        ...,
        min_length=1,
        max_length=20,
        description="Top keywords from extracted topic representation",
    )
    topic_id: Optional[int] = Field(
        None,
        ge=-1,
        description="Internal topic id from topic modeling result",
    )
    topic_doc_count: Optional[int] = Field(
        None,
        ge=0,
        description="Number of documents mapped to this topic",
    )
    model_type: Optional[str] = Field(
        None,
        max_length=32,
        description="Topic model type, e.g. bertopic or lda",
    )
    representative_titles: List[str] = Field(
        default_factory=list,
        description="Reference document titles mapped to this topic",
    )
    representative_abstracts: List[str] = Field(
        default_factory=list,
        description="Sample abstract snippets mapped to this topic",
    )
    broader_terms: List[str] = Field(
        default_factory=list,
        description="Supporting terms/subjects to provide broader topic context",
    )

    @staticmethod
    def _clean_string_list(value: List[str], max_items: int, max_chars: int) -> List[str]:
        cleaned: List[str] = []

        for item in value or []:
            text = str(item).strip()
            if not text:
                continue

            normalized = text[:max_chars]
            if normalized not in cleaned:
                cleaned.append(normalized)

            if len(cleaned) >= max_items:
                break

        return cleaned

    @field_validator("keywords")
    @classmethod
    def validate_keywords(cls, value: List[str]) -> List[str]:
        cleaned = [str(keyword).strip() for keyword in value if str(keyword).strip()]
        if not cleaned:
            raise ValueError("keywords cannot be empty")
        return cleaned[:20]

    @field_validator("model_type")
    @classmethod
    def validate_model_type(cls, value: Optional[str]) -> Optional[str]:
        if value is None:
            return None

        normalized = value.strip().lower()
        return normalized or None

    @field_validator("representative_titles")
    @classmethod
    def validate_representative_titles(cls, value: List[str]) -> List[str]:
        return cls._clean_string_list(value, max_items=2000, max_chars=180)

    @field_validator("representative_abstracts")
    @classmethod
    def validate_representative_abstracts(cls, value: List[str]) -> List[str]:
        return cls._clean_string_list(value, max_items=4, max_chars=320)

    @field_validator("broader_terms")
    @classmethod
    def validate_broader_terms(cls, value: List[str]) -> List[str]:
        return cls._clean_string_list(value, max_items=20, max_chars=80)


class TopicCurationSuggestionResponse(BaseModel):
    """AI-generated topic curation suggestion."""

    custom_name: str = Field(..., min_length=1, max_length=150)
    representation_description: str = Field(..., min_length=1, max_length=2000)


class TitleRecommendationItem(BaseModel):
    """One title recommendation generated by AI."""

    title: str = Field(..., min_length=8, max_length=240)
    rationale: str = Field(..., min_length=1, max_length=600)


class TitleRecommendationRequest(BaseModel):
    """Request payload for generating skripsi title recommendations."""

    topic_id: Optional[int] = Field(
        None,
        ge=-1,
        description="Internal topic id from topic modeling result",
    )
    topic_label: Optional[str] = Field(
        None,
        max_length=180,
        description="Human-readable topic label",
    )
    topic_keywords: List[str] = Field(
        ...,
        min_length=1,
        max_length=20,
        description="Main topic keywords used as context",
    )
    mapped_titles: List[str] = Field(
        default_factory=list,
        description="Reference skripsi titles mapped to the selected topic",
    )
    user_prompt: str = Field(
        ...,
        min_length=3,
        max_length=1200,
        description="User intent/prompt for title recommendation",
    )
    recommendations_count: int = Field(
        5,
        ge=3,
        le=10,
        description="How many title recommendations to generate",
    )
    strict_context: bool = Field(
        True,
        description="Reject prompts that are outside skripsi/topic context",
    )

    @staticmethod
    def _clean_string_list(value: List[str], max_items: int, max_chars: int) -> List[str]:
        cleaned: List[str] = []

        for item in value or []:
            text = str(item).strip()
            if not text:
                continue

            normalized = text[:max_chars]
            if normalized not in cleaned:
                cleaned.append(normalized)

            if len(cleaned) >= max_items:
                break

        return cleaned

    @field_validator("topic_label")
    @classmethod
    def validate_topic_label(cls, value: Optional[str]) -> Optional[str]:
        if value is None:
            return None

        normalized = value.strip()
        return normalized or None

    @field_validator("topic_keywords")
    @classmethod
    def validate_topic_keywords(cls, value: List[str]) -> List[str]:
        cleaned = cls._clean_string_list(value, max_items=20, max_chars=80)
        if not cleaned:
            raise ValueError("topic_keywords cannot be empty")
        return cleaned

    @field_validator("mapped_titles")
    @classmethod
    def validate_mapped_titles(cls, value: List[str]) -> List[str]:
        return cls._clean_string_list(value, max_items=80, max_chars=220)

    @field_validator("user_prompt")
    @classmethod
    def validate_user_prompt(cls, value: str) -> str:
        normalized = value.strip()
        if len(normalized) < 3:
            raise ValueError("user_prompt must contain at least 3 non-space characters")
        return normalized


class TitleRecommendationResponse(BaseModel):
    """AI-generated title recommendation response."""

    topic_id: Optional[int] = None
    topic_label: Optional[str] = None
    context_ok: bool = True
    context_message: Optional[str] = None
    recommendations: List[TitleRecommendationItem] = Field(default_factory=list)


class HealthResponse(BaseModel):
    """Health check response."""
    status: str = "ok"
    app_name: str
    version: str
    environment: str
    embedding_model: str = Field(
        description="Active sentence-transformer model (IndoSBERT)"
    )
