"""
Hyperparameter Configuration
Provides preset hyperparameter configurations and grid search ranges
for BERTopic (IndoSBERT-large) and LDA (Gensim) models.

Nilai default preset diambil dari hasil grid search di folder analysis/:
  - BERTopic best: BT_031 — coherence=0.6250, diversity=0.9333
    (umap_n_neighbors=5, umap_n_components=5, hdbscan_min_cluster_size=5,
     min_samples=1, nr_topics=10, min_topic_size=5)
  - LDA best: LDA_039 — coherence=0.4001, diversity=0.7067
    (num_topics=15, passes=20, alpha='symmetric', eta='auto')

Embedding model:
    - "denaya/indoSBERT-large" (default)
      → IndoBERT-large re-trained dengan Siamese Network
      → 256-dim sentence embeddings, best quality untuk Bahasa Indonesia
"""

from typing import Any, Dict, List

from app.models.schemas import (BERTopicHyperparameters,
                                HDBSCANHyperparameters, LDAHyperparameters,
                                UMAPHyperparameters)

# ============================================
# BERTopic Presets (semua pakai IndoSBERT-large)
# Default diambil dari BT_031 — hasil terbaik grid search
# ============================================

BERTOPIC_PRESETS: Dict[str, BERTopicHyperparameters] = {
    "default": BERTopicHyperparameters(
        # BT_031: n_neighbors=5, n_components=5, hdbscan_min_cluster_size=5,
        #         min_samples=1, nr_topics=10 → Coherence=0.6250, Diversity=0.9333
        embedding_model="denaya/indoSBERT-large",
        min_topic_size=5,
        nr_topics=10,
        top_n_words=10,
        n_gram_range=[1, 2],
        embedding_batch_size=16,
        seed=42,
        umap_params=UMAPHyperparameters(
            n_neighbors=5,
            n_components=5,
            min_dist=0.0,
            metric="cosine",
            random_state=42,
        ),
        hdbscan_params=HDBSCANHyperparameters(
            min_cluster_size=5,
            min_samples=1,
            cluster_selection_method="eom",
        ),
    ),
    "fine_grained": BERTopicHyperparameters(
        # Lebih banyak topik, lebih detail per topik
        embedding_model="denaya/indoSBERT-large",
        min_topic_size=5,
        nr_topics=15,
        top_n_words=15,
        n_gram_range=[1, 3],
        embedding_batch_size=16,
        seed=42,
        umap_params=UMAPHyperparameters(
            n_neighbors=5,
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
        # Lebih sedikit topik, lebih broad
        embedding_model="denaya/indoSBERT-large",
        min_topic_size=10,
        nr_topics=8,
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
            min_samples=5,
            cluster_selection_method="eom",
        ),
    ),
}

# Grid search ranges — sesuai actual grid yang dipakai di eksperimen notebook
BERTOPIC_GRID: Dict[str, List[Any]] = {
    # UMAP — dari step1_hyperparameter_grid_config.csv
    "umap_n_neighbors": [5, 10, 15],
    "umap_n_components": [5, 10],
    "umap_min_dist": [0.0],
    "umap_metric": ["cosine"],
    # HDBSCAN
    "hdbscan_min_cluster_size": [5, 8, 10, 15],
    "hdbscan_min_samples": [1, 3],
    "hdbscan_cluster_selection_method": ["eom"],
    # BERTopic
    "nr_topics": [8, 10, 12, 15],
    "min_topic_size": [5],
    "top_n_words": [10],
}


# ============================================
# LDA Presets — baseline tradisional
# Default diambil dari LDA_039 — hasil terbaik grid search
# ============================================

LDA_PRESETS: Dict[str, LDAHyperparameters] = {
    "default": LDAHyperparameters(
        # LDA_039: num_topics=15, passes=20, alpha='symmetric', eta='auto'
        # → Coherence=0.4001, Diversity=0.7067
        num_topics=15,
        passes=20,
        iterations=400,
        chunksize=100,
        random_state=42,
        alpha="symmetric",
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
        alpha="symmetric",
        eta="auto",
        no_below=3,
        no_above=0.6,
    ),
    "fewer_topics": LDAHyperparameters(
        num_topics=8,
        passes=20,
        iterations=400,
        chunksize=100,
        random_state=42,
        alpha="symmetric",
        eta="symmetric",
        no_below=5,
        no_above=0.5,
    ),
}

# Grid search ranges — sesuai actual grid di eksperimen notebook
LDA_GRID: Dict[str, List[Any]] = {
    "num_topics": [5, 8, 10, 12, 15],
    "passes": [15, 20],
    "alpha": ["auto", "symmetric"],
    "eta": ["auto", "symmetric"],
    "no_below": [5],
    "no_above": [0.5],
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
