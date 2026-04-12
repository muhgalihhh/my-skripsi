"""Preset and grid hyperparameters for BERTopic and LDA."""

from typing import Any, Dict, List

from app.models.schemas import (BERTopicHyperparameters,
                                HDBSCANHyperparameters, LDAHyperparameters,
                                UMAPHyperparameters)

BERTOPIC_PRESETS: Dict[str, BERTopicHyperparameters] = {
    "default": BERTopicHyperparameters(
        # Notebook-aligned baseline
        embedding_model="denaya/indoSBERT-large",
        min_topic_size=10,
        nr_topics=8,
        top_n_words=15,
        n_gram_range=[1, 2],
        vectorizer_min_df=2,
        vectorizer_max_df=0.95,
        embedding_batch_size=16,
        seed=42,
        umap_params=UMAPHyperparameters(
            n_neighbors=40,
            n_components=5,
            min_dist=0.0,
            metric="cosine",
            random_state=42,
        ),
        hdbscan_params=HDBSCANHyperparameters(
            min_cluster_size=8,
            min_samples=1,
            cluster_selection_method="eom",
        ),
    ),
    "fine_grained": BERTopicHyperparameters(
        # Lebih banyak topik, lebih detail per topik
        embedding_model="denaya/indoSBERT-large",
        min_topic_size=10,
        nr_topics=None,
        top_n_words=15,
        n_gram_range=[1, 2],
        vectorizer_min_df=2,
        vectorizer_max_df=0.9,
        embedding_batch_size=16,
        seed=42,
        umap_params=UMAPHyperparameters(
            n_neighbors=30,
            n_components=5,
            min_dist=0.0,
            metric="cosine",
            random_state=42,
        ),
        hdbscan_params=HDBSCANHyperparameters(
            min_cluster_size=8,
            min_samples=1,
            cluster_selection_method="eom",
        ),
    ),
    "coarse": BERTopicHyperparameters(
        # Lebih sedikit topik, lebih broad
        embedding_model="denaya/indoSBERT-large",
        min_topic_size=20,
        nr_topics=10,
        top_n_words=15,
        n_gram_range=[1, 2],
        vectorizer_min_df=3,
        vectorizer_max_df=0.95,
        embedding_batch_size=16,
        seed=42,
        umap_params=UMAPHyperparameters(
            n_neighbors=75,
            n_components=10,
            min_dist=0.1,
            metric="cosine",
            random_state=42,
        ),
        hdbscan_params=HDBSCANHyperparameters(
            min_cluster_size=16,
            min_samples=2,
            cluster_selection_method="eom",
        ),
    ),
}

# Grid search ranges — selaras notebook clean terbaru
BERTOPIC_GRID: Dict[str, List[Any]] = {
    # UMAP
    "umap_n_neighbors": [40, 55, 70],
    "umap_n_components": [5],
    "umap_min_dist": [0.0, 0.05, 0.1],
    "umap_metric": ["cosine"],
    # HDBSCAN
    "hdbscan_min_cluster_size": [8, 12, 16],
    "hdbscan_min_samples": [1, 2],
    "hdbscan_cluster_selection_method": ["eom"],
    # BERTopic
    "nr_topics": [8],
    "min_topic_size": [8, 10, 12],
    "top_n_words": [15],
    # Vectorizer
    "vectorizer_ngram_range": [(1, 2)],
    "vectorizer_min_df": [1, 2],
    "vectorizer_max_df": [0.9, 0.95, 1.0],
}

LDA_PRESETS: Dict[str, LDAHyperparameters] = {
    "default": LDAHyperparameters(
        num_topics=12,
        passes=20,
        iterations=300,
        chunksize=100,
        random_state=42,
        alpha="asymmetric",
        eta=None,
        no_below=2,
        no_above=0.95,
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

# Grid search ranges — selaras notebook clean terbaru
LDA_GRID: Dict[str, List[Any]] = {
    "num_topics": [8, 10, 12],
    "passes": [10, 20],
    "iterations": [200, 300],
    "alpha": ["symmetric", "asymmetric"],
    "eta": [None],
    "no_below": [2],
    "no_above": [0.95],
}

