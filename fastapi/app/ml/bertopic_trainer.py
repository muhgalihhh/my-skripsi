"""
BERTopic Trainer
Handles training BERTopic models with configurable hyperparameters.

Arsitektur embedding:
    - Model      : denaya/indoSBERT-large
    - Fondasi    : IndoBERT-large (indobenchmark/indobert-large-p1)
    - Training   : Siamese Network (sentence-transformers framework)
    - Output dim : 256-dimensional sentence embeddings
    - Bahasa     : Optimized untuk Bahasa Indonesia

BERTopic pipeline:
    IndoSBERT → UMAP → HDBSCAN → c-TF-IDF → Topic Representation → Reduce Outliers

Catatan preprocessing:
    BERTopic membutuhkan teks yang di-clean RINGAN (tidak stemming, tidak hapus
    stopword agresif) karena IndoSBERT dilatih pada kalimat natural Bahasa Indonesia.
    Gunakan kolom 'cleaned_text' dari preprocessing pipeline, BUKAN 'processed_text'.
"""

import json
import os
import time
from datetime import datetime
from pathlib import Path
from typing import Any, Dict, List, Optional, Tuple

# Fix for "Matplotlib created a temporary cache directory ... Errno 13 Permission Denied"
# Matplotlib attempts to write to /app/.config which appuser cannot write to.
os.environ['MPLCONFIGDIR'] = '/tmp/matplotlib'

import numpy as np
import pandas as pd
import torch

# Fix for "Unable to find torch_shm_manager" inside restrictive Docker environments
torch.multiprocessing.set_sharing_strategy('file_system')

from app.core.config import (bertopic_settings, hdbscan_settings,
                             path_settings, umap_settings)
from app.models.schemas import BERTopicHyperparameters
from loguru import logger


class BERTopicTrainer:
    """
    Trainer for BERTopic model with IndoSBERT-large embeddings.

    Pipeline:
        1. Load IndoSBERT-large (Siamese dari IndoBERT-large) via sentence-transformers
        2. Compute 256-dim sentence embeddings
        3. UMAP dimensionality reduction
        4. HDBSCAN density-based clustering
        5. c-TF-IDF topic representation + CountVectorizer
        6. Reduce outliers (paksa dokumen outlier ke topik terdekat)
        7. Evaluate dengan Coherence (C_v) dan Topic Diversity
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

        denaya/indoSBERT-large adalah IndoBERT-large yang dilatih ulang
        menggunakan Siamese Network, menghasilkan 256-dim sentence embeddings
        berkualitas tinggi untuk Bahasa Indonesia.
        """
        from sentence_transformers import SentenceTransformer

        logger.info(
            f"Loading sentence encoder: {self.params.embedding_model} "
            f"(IndoBERT-large + Siamese Network, output: 256-dim)"
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
        """
        Configure CountVectorizer untuk c-TF-IDF topic representation.

        Parameter:
          - min_df=2   : kata harus muncul minimal di 2 dokumen (toleran untuk cluster kecil)
          - max_df=0.95: hapus kata yang muncul di >95% dokumen (terlalu umum)
          - token_pattern: ambil kata dengan minimal 3 huruf
          - stop_words=None: stopword sudah ditangani di preprocessing
        """
        from sklearn.feature_extraction.text import CountVectorizer

        n_gram_range = tuple(self.params.n_gram_range)
        return CountVectorizer(
            ngram_range=n_gram_range,
            stop_words=None,           # Sudah di-handle di preprocessing
            min_df=2,                  # Toleran untuk cluster kecil (fix dari eksperimen)
            max_df=0.95,               # Toleran untuk kata umum (fix dari eksperimen)
            token_pattern=r"(?u)\b\w{3,}\b",  # Minimal 3 huruf per token
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
        Pre-compute sentence embeddings menggunakan IndoSBERT-large.

        CATATAN: Gunakan 'cleaned_text' dari preprocessing (bukan 'processed_text').
        IndoSBERT membutuhkan teks natural tanpa stemming/stopword-removal agresif.
        Output: 256-dimensional embedding vectors per dokumen.
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
            documents: List teks CLEANED (soft clean, bukan stemmed!) untuk IndoSBERT
            embeddings: Pre-computed embeddings (opsional, akan dihitung jika None)
            timestamps: List tahun per dokumen untuk Dynamic Topic Analysis (opsional)

        Returns:
            Dictionary berisi training results dan metrics
        """
        start_time = time.time()
        logger.info(f"Starting BERTopic training on {len(documents)} documents")
        logger.info(f"Embedding model: {self.params.embedding_model} (256-dim)")

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

        # Hitung outlier sebelum reduce
        outlier_count_before = int((np.array(self.topics) == -1).sum())
        logger.info(f"Outliers before reduce: {outlier_count_before}/{len(documents)}")

        # Reduce outliers: paksa semua dokumen outlier ke topik terdekat
        # Sesuai dengan hasil eksperimen notebook (outlier 42 → 0)
        logger.info("Reducing outliers (assigning outlier docs to nearest topic)...")
        self.topics = self.model.reduce_outliers(
            documents, self.topics, strategy="c-tf-idf"
        )
        # Update representasi topik setelah reduce outliers
        self.model.update_topics(
            documents,
            topics=self.topics,
            vectorizer_model=self._build_vectorizer(),
        )

        outlier_count_after = int((np.array(self.topics) == -1).sum())
        logger.info(f"Outliers after reduce: {outlier_count_after}/{len(documents)}")

        # Get topic info setelah update
        self.topic_info = self.model.get_topic_info()

        duration = time.time() - start_time
        num_topics = len(self.topic_info) - 1  # exclude outlier topic -1

        logger.info(
            f"BERTopic training complete in {duration:.2f}s. "
            f"Found {num_topics} topics. "
            f"Outliers: before={outlier_count_before}, after={outlier_count_after}"
        )

        # Build result
        result = {
            "model_type": "bertopic",
            "num_topics": num_topics,
            "num_outliers": outlier_count_after,
            "num_outliers_before_reduce": outlier_count_before,
            "training_duration_seconds": round(duration, 2),
            "embedding_model": self.params.embedding_model,
            "embedding_dim": 256,
            "hyperparameters": self.params.model_dump(),
            "topic_info": self._extract_topic_info(),
        }

        # Dynamic Topic Analysis jika timestamps tersedia
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

        # Save embeddings (untuk reuse / DTA)
        if self.embeddings is not None:
            np.save(str(model_dir / "embeddings.npy"), self.embeddings)
            # Juga simpan di shared embeddings dir untuk reuse antar job
            embeddings_dir = path_settings.get_embeddings_dir()
            np.save(str(embeddings_dir / f"embeddings_{job_id}.npy"), self.embeddings)

        # Save metadata
        metadata = {
            "job_id": job_id,
            "model_type": "bertopic",
            "embedding_model": self.params.embedding_model,
            "embedding_dim": 256,
            "embedding_model_note": (
                "IndoSBERT-large = IndoBERT-large re-trained with Siamese Network. "
                "Produces 256-dim sentence embeddings for Bahasa Indonesia."
            ),
            "preprocessing_note": (
                "Gunakan 'cleaned_text' (soft clean, no stemming, no stopword removal) "
                "bukan 'processed_text' untuk input BERTopic."
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

        # Load embeddings jika ada
        embeddings_path = model_dir / "embeddings.npy"
        if embeddings_path.exists():
            self.embeddings = np.load(str(embeddings_path))

        logger.info(f"BERTopic model loaded from {model_dir}")
        return self.model
