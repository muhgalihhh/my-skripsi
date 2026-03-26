"""
Hyperparameter Configuration
Provides preset hyperparameter configurations and grid search ranges
for BERTopic (IndoSBERT-large) and LDA (Gensim) models.

Embedding model presets:
    - "denaya/indoSBERT-large" (default)
      → IndoBERT-large re-trained with Siamese Network
      → 768-dim sentence embeddings, best quality for Indonesian
"""

from typing import Any, Dict, List

from app.models.schemas import (BERTopicHyperparameters,
                                HDBSCANHyperparameters, LDAHyperparameters,
                                UMAPHyperparameters)

# ============================================
# BERTopic Presets (all use IndoSBERT-large)
# ============================================

BERTOPIC_PRESETS: Dict[str, BERTopicHyperparameters] = {
    "default": BERTopicHyperparameters(
        embedding_model="denaya/indoSBERT-large",
        min_topic_size=10,
        nr_topics=None,
        top_n_words=10,
        n_gram_range=[1, 2],
        embedding_batch_size=16,
        seed=42,
        umap_params=UMAPHyperparameters(
            n_neighbors=15,
            n_components=5,
            min_dist=0.0,
            metric="cosine",
            random_state=42,
        ),
        hdbscan_params=HDBSCANHyperparameters(
            min_cluster_size=10,
            min_samples=None,
            cluster_selection_method="eom",
        ),
    ),
    "fine_grained": BERTopicHyperparameters(
        embedding_model="denaya/indoSBERT-large",
        min_topic_size=5,
        nr_topics=None,
        top_n_words=15,
        n_gram_range=[1, 3],
        embedding_batch_size=16,
        seed=42,
        umap_params=UMAPHyperparameters(
            n_neighbors=10,
            n_components=5,
            min_dist=0.0,
            metric="cosine",
            random_state=42,
        ),
        hdbscan_params=HDBSCANHyperparameters(
            min_cluster_size=5,
            min_samples=3,
            cluster_selection_method="eom",
        ),
    ),
    "coarse": BERTopicHyperparameters(
        embedding_model="denaya/indoSBERT-large",
        min_topic_size=20,
        nr_topics=None,
        top_n_words=10,
        n_gram_range=[1, 2],
        embedding_batch_size=16,
        seed=42,
        umap_params=UMAPHyperparameters(
            n_neighbors=20,
            n_components=5,
            min_dist=0.1,
            metric="cosine",
            random_state=42,
        ),
        hdbscan_params=HDBSCANHyperparameters(
            min_cluster_size=20,
            min_samples=10,
            cluster_selection_method="leaf",
        ),
    ),
}

# Grid search ranges for BERTopic hyperparameter tuning
BERTOPIC_GRID: Dict[str, List[Any]] = {
    # UMAP
    "umap_n_neighbors": [5, 10, 15, 20, 30],
    "umap_n_components": [3, 5, 10],
    "umap_min_dist": [0.0, 0.05, 0.1],
    # HDBSCAN
    "hdbscan_min_cluster_size": [5, 10, 15, 20],
    "hdbscan_cluster_selection_method": ["eom", "leaf"],
    # BERTopic
    "min_topic_size": [5, 10, 15, 20],
    "top_n_words": [5, 10, 15],
}


# ============================================
# LDA Presets (baseline tradisional)
# ============================================

LDA_PRESETS: Dict[str, LDAHyperparameters] = {
    "default": LDAHyperparameters(
        num_topics=10,
        passes=15,
        iterations=400,
        chunksize=100,
        random_state=42,
        alpha="auto",
        eta="auto",
        no_below=5,
        no_above=0.5,
    ),
    "more_topics": LDAHyperparameters(
        num_topics=20,
        passes=20,
        iterations=500,
        chunksize=100,
        random_state=42,
        alpha="auto",
        eta="auto",
        no_below=3,
        no_above=0.6,
    ),
    "fewer_topics": LDAHyperparameters(
        num_topics=5,
        passes=25,
        iterations=600,
        chunksize=50,
        random_state=42,
        alpha="symmetric",
        eta="auto",
        no_below=5,
        no_above=0.4,
    ),
}

# Grid search ranges for LDA hyperparameter tuning
LDA_GRID: Dict[str, List[Any]] = {
    "num_topics": [5, 8, 10, 12, 15, 20],
    "passes": [10, 15, 20, 30],
    "alpha": ["auto", "symmetric"],
    "eta": ["auto", "symmetric"],
    "no_below": [3, 5, 10],
    "no_above": [0.3, 0.5, 0.7],
}


def get_bertopic_preset(name: str = "default") -> BERTopicHyperparameters:
    """Get a BERTopic hyperparameter preset by name."""
    if name not in BERTOPIC_PRESETS:
        raise ValueError(
            f"Unknown preset '{name}'. Available: {list(BERTOPIC_PRESETS.keys())}"
        )
    return BERTOPIC_PRESETS[name]


def get_lda_preset(name: str = "default") -> LDAHyperparameters:
    """Get an LDA hyperparameter preset by name."""
    if name not in LDA_PRESETS:
        raise ValueError(
            f"Unknown preset '{name}'. Available: {list(LDA_PRESETS.keys())}"
        )
    return LDA_PRESETS[name]
