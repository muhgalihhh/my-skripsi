"""
BERTopic Trainer
Handles training BERTopic models with configurable hyperparameters.

Arsitektur embedding:
    - Model      : denaya/indoSBERT-large
    - Fondasi    : IndoBERT-large (indobenchmark/indobert-large-p1)
    - Training   : Siamese Network (sentence-transformers framework)
    - Output dim : 768-dimensional sentence embeddings
    - Bahasa     : Optimized untuk Bahasa Indonesia

BERTopic pipeline:
    IndoSBERT → UMAP → HDBSCAN → c-TF-IDF → Topic Representation
"""

import json
import time
from datetime import datetime
from pathlib import Path
from typing import Any, Dict, List, Optional, Tuple

import numpy as np
import pandas as pd
from app.core.config import (bertopic_settings, hdbscan_settings,
                             path_settings, umap_settings)
from app.models.schemas import BERTopicHyperparameters
from loguru import logger


class BERTopicTrainer:
    """
    Trainer for BERTopic model with IndoSBERT-large embeddings.

    Pipeline:
        1. Load IndoSBERT-large (Siamese from IndoBERT-large) via sentence-transformers
        2. Compute 768-dim sentence embeddings
        3. UMAP dimensionality reduction
        4. HDBSCAN density-based clustering
        5. c-TF-IDF topic representation + CountVectorizer
        6. Evaluate with Coherence (C_v) and Topic Diversity
    """

    def __init__(self, params: Optional[BERTopicHyperparameters] = None):
        if params is None:
            from app.models.schemas import (HDBSCANHyperparameters,
                                            UMAPHyperparameters)

            params = BERTopicHyperparameters(
                embedding_model=bertopic_settings.BERTOPIC_EMBEDDING_MODEL,
                min_topic_size=bertopic_settings.BERTOPIC_MIN_TOPIC_SIZE,
                nr_topics=bertopic_settings.get_nr_topics(),
                top_n_words=bertopic_settings.BERTOPIC_TOP_N_WORDS,
                embedding_batch_size=bertopic_settings.BERTOPIC_EMBEDDING_BATCH_SIZE,
                seed=bertopic_settings.BERTOPIC_SEED,
                umap_params=UMAPHyperparameters(
                    n_neighbors=umap_settings.UMAP_N_NEIGHBORS,
                    n_components=umap_settings.UMAP_N_COMPONENTS,
                    min_dist=umap_settings.UMAP_MIN_DIST,
                    metric=umap_settings.UMAP_METRIC,
                    random_state=umap_settings.UMAP_RANDOM_STATE,
                ),
                hdbscan_params=HDBSCANHyperparameters(
                    min_cluster_size=hdbscan_settings.HDBSCAN_MIN_CLUSTER_SIZE,
                    min_samples=hdbscan_settings.HDBSCAN_MIN_SAMPLES,
                    cluster_selection_method=hdbscan_settings.HDBSCAN_CLUSTER_SELECTION_METHOD,
                ),
            )

        self.params = params
        self.model = None
        self.topics = None
        self.probabilities = None
        self.embeddings = None
        self.topic_info = None

    def _build_embedding_model(self):
        """
        Load the IndoSBERT-large sentence transformer.

        denaya/indoSBERT-large is IndoBERT-large re-trained using
        Siamese Network approach, producing high quality sentence
        embeddings for Indonesian text.
        """
        from sentence_transformers import SentenceTransformer

        logger.info(
            f"Loading sentence encoder: {self.params.embedding_model} "
            f"(IndoBERT-large + Siamese Network)"
        )
        return SentenceTransformer(self.params.embedding_model)

    def _build_umap_model(self):
        """Configure UMAP dimensionality reduction."""
        from umap import UMAP

        p = self.params.umap_params
        return UMAP(
            n_neighbors=p.n_neighbors,
            n_components=p.n_components,
            min_dist=p.min_dist,
            metric=p.metric,
            random_state=p.random_state,
        )

    def _build_hdbscan_model(self):
        """Configure HDBSCAN clustering."""
        from hdbscan import HDBSCAN

        p = self.params.hdbscan_params
        return HDBSCAN(
            min_cluster_size=p.min_cluster_size,
            min_samples=p.min_samples,
            cluster_selection_method=p.cluster_selection_method,
            prediction_data=True,
        )

    def _build_vectorizer(self):
        """Configure CountVectorizer for c-TF-IDF topic representation."""
        from sklearn.feature_extraction.text import CountVectorizer

        n_gram_range = tuple(self.params.n_gram_range)
        return CountVectorizer(
            ngram_range=n_gram_range,
            stop_words=None,  # Already handled in preprocessing
        )

    def _build_model(self):
        """Build the full BERTopic pipeline."""
        from bertopic import BERTopic

        embedding_model = self._build_embedding_model()
        umap_model = self._build_umap_model()
        hdbscan_model = self._build_hdbscan_model()
        vectorizer_model = self._build_vectorizer()

        self.model = BERTopic(
            embedding_model=embedding_model,
            umap_model=umap_model,
            hdbscan_model=hdbscan_model,
            vectorizer_model=vectorizer_model,
            top_n_words=self.params.top_n_words,
            nr_topics=self.params.nr_topics,
            min_topic_size=self.params.min_topic_size,
            verbose=True,
        )

        logger.info("BERTopic model pipeline built successfully")
        return self.model

    def compute_embeddings(self, documents: List[str]) -> np.ndarray:
        """
        Pre-compute sentence embeddings using IndoSBERT-large.

        CATATAN: BERTopic membutuhkan teks yang sudah di-clean tapi
        TIDAK di-stem, karena IndoSBERT dilatih pada teks natural
        Bahasa Indonesia. Kolom 'cleaned_text' dari preprocessing.
        """
        from sentence_transformers import SentenceTransformer

        logger.info(
            f"Computing sentence embeddings for {len(documents)} documents "
            f"using {self.params.embedding_model}..."
        )
        embedding_model = SentenceTransformer(self.params.embedding_model)
        embeddings = embedding_model.encode(
            documents,
            show_progress_bar=True,
            batch_size=self.params.embedding_batch_size,
        )
        self.embeddings = embeddings
        logger.info(f"Embeddings shape: {embeddings.shape} (dim={embeddings.shape[1]})")
        return embeddings

    def train(
        self,
        documents: List[str],
        embeddings: Optional[np.ndarray] = None,
        timestamps: Optional[List[int]] = None,
    ) -> Dict[str, Any]:
        """
        Train BERTopic model.

        Args:
            documents: List of CLEANED text (not stemmed!) — for IndoSBERT
            embeddings: Pre-computed embeddings (optional, will compute if None)
            timestamps: List of years for Dynamic Topic Analysis (optional)

        Returns:
            Dictionary with training results and metrics
        """
        start_time = time.time()
        logger.info(f"Starting BERTopic training on {len(documents)} documents")
        logger.info(f"Embedding model: {self.params.embedding_model}")

        # Build model
        self._build_model()

        # Compute embeddings if not provided
        if embeddings is None:
            embeddings = self.compute_embeddings(documents)

        # Fit the model
        logger.info("Fitting BERTopic model (UMAP → HDBSCAN → c-TF-IDF)...")
        self.topics, self.probabilities = self.model.fit_transform(
            documents, embeddings=embeddings
        )

        # Get topic info
        self.topic_info = self.model.get_topic_info()

        duration = time.time() - start_time
        num_topics = len(self.topic_info) - 1  # exclude outlier topic -1
        outlier_count = int((np.array(self.topics) == -1).sum())

        logger.info(
            f"BERTopic training complete in {duration:.2f}s. "
            f"Found {num_topics} topics. "
            f"Outliers: {outlier_count}/{len(documents)} documents"
        )

        # Build result
        result = {
            "model_type": "bertopic",
            "num_topics": num_topics,
            "num_outliers": outlier_count,
            "training_duration_seconds": round(duration, 2),
            "embedding_model": self.params.embedding_model,
            "hyperparameters": self.params.model_dump(),
            "topic_info": self._extract_topic_info(),
        }

        # Dynamic Topic Analysis if timestamps provided
        if timestamps is not None:
            logger.info("Running Dynamic Topic Analysis (topics_over_time)...")
            topics_over_time = self.model.topics_over_time(
                documents, timestamps=timestamps
            )
            result["topics_over_time"] = topics_over_time.to_dict(orient="records")

        return result

    def _extract_topic_info(self) -> List[Dict[str, Any]]:
        """Extract topic information in a serializable format."""
        if self.model is None:
            return []

        topics = []
        for topic_id in self.model.get_topics():
            if topic_id == -1:
                continue  # Skip outlier topic
            words_scores = self.model.get_topic(topic_id)
            topics.append({
                "topic_id": topic_id,
                "top_words": [w for w, _ in words_scores],
                "word_scores": [round(float(s), 4) for _, s in words_scores],
                "count": int(
                    self.topic_info[self.topic_info["Topic"] == topic_id]["Count"].values[0]
                )
                if self.topic_info is not None
                else 0,
            })

        return topics

    def save_model(self, job_id: str) -> str:
        """Save trained model and embeddings to disk."""
        if self.model is None:
            raise ValueError("No trained model to save")

        path_settings.ensure_dirs()
        model_dir = path_settings.get_models_dir() / f"bertopic_{job_id}"
        model_dir.mkdir(parents=True, exist_ok=True)

        # Save BERTopic model
        model_path = str(model_dir / "model")
        self.model.save(model_path, serialization="safetensors", save_ctfidf=True)

        # Save embeddings separately (for reuse / DTA)
        if self.embeddings is not None:
            np.save(str(model_dir / "embeddings.npy"), self.embeddings)
            # Also save to shared embeddings dir for cross-job reuse
            embeddings_dir = path_settings.get_embeddings_dir()
            np.save(str(embeddings_dir / f"embeddings_{job_id}.npy"), self.embeddings)

        # Save metadata
        metadata = {
            "job_id": job_id,
            "model_type": "bertopic",
            "embedding_model": self.params.embedding_model,
            "embedding_model_note": (
                "IndoSBERT-large = IndoBERT-large re-trained with Siamese Network. "
                "Produces 768-dim sentence embeddings for Bahasa Indonesia."
            ),
            "hyperparameters": self.params.model_dump(),
            "saved_at": datetime.now().isoformat(),
        }
        with open(model_dir / "metadata.json", "w") as f:
            json.dump(metadata, f, indent=2, ensure_ascii=False)

        logger.info(f"BERTopic model saved to {model_dir}")
        return str(model_dir)

    def load_model(self, job_id: str):
        """Load a previously trained model."""
        from bertopic import BERTopic

        model_dir = path_settings.get_models_dir() / f"bertopic_{job_id}"
        model_path = str(model_dir / "model")

        if not model_dir.exists():
            raise FileNotFoundError(f"Model not found: {model_dir}")

        self.model = BERTopic.load(model_path)

        # Load embeddings if available
        embeddings_path = model_dir / "embeddings.npy"
        if embeddings_path.exists():
            self.embeddings = np.load(str(embeddings_path))

        logger.info(f"BERTopic model loaded from {model_dir}")
        return self.model
