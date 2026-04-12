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
import logging
import os
import random
import time
from contextlib import contextmanager
from datetime import datetime
from typing import Any, Dict, List, Optional

# Fix for "Matplotlib created a temporary cache directory ... Errno 13 Permission Denied"
# Matplotlib attempts to write to /app/.config which appuser cannot write to.
os.environ['MPLCONFIGDIR'] = '/tmp/matplotlib'

import numpy as np
import torch

# Fix for "Unable to find torch_shm_manager" inside restrictive Docker environments
torch.multiprocessing.set_sharing_strategy('file_system')

from app.core.config import (bertopic_settings, hdbscan_settings,
                             path_settings, umap_settings)
from app.models.schemas import BERTopicHyperparameters
from loguru import logger
from sentence_transformers import SentenceTransformer
from umap import UMAP
from hdbscan import HDBSCAN
from sklearn.feature_extraction.text import CountVectorizer
from bertopic import BERTopic

from app.services.stopwords import load_stopwords

_BERTOPIC_ASSIGNMENT_WARNING = (
    "Using a custom list of topic assignments may lead to errors"
)


class _BERTopicWarningFilter(logging.Filter):
    """Filter noisy BERTopic assignment warnings that are expected in our pipeline."""

    def filter(self, record: logging.LogRecord) -> bool:
        return _BERTOPIC_ASSIGNMENT_WARNING not in record.getMessage()


@contextmanager
def _suppress_bertopic_assignment_warning():
    warning_filter = _BERTopicWarningFilter()
    targets = (
        logging.getLogger("BERTopic"),
        logging.getLogger("bertopic"),
        logging.getLogger("bertopic._bertopic"),
    )
    for target in targets:
        target.addFilter(warning_filter)
    try:
        yield
    finally:
        for target in targets:
            target.removeFilter(warning_filter)

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
                    metric=hdbscan_settings.HDBSCAN_METRIC,
                    cluster_selection_method=hdbscan_settings.HDBSCAN_CLUSTER_SELECTION_METHOD,
                ),
            )

        self.params = params
        self.model = None
        self.topics = None
        self.probabilities = None
        self.embeddings = None
        self.embedding_model = None
        self.topic_info = None
        self.vectorizer_model = None
        self.representation_model = None

    def _set_reproducibility(self):
        """Set deterministic seeds/threads for reproducible BERTopic runs."""
        seed = int(self.params.seed)

        random.seed(seed)
        np.random.seed(seed)
        torch.manual_seed(seed)
        if torch.cuda.is_available():
            torch.cuda.manual_seed_all(seed)

        # Make torch execution deterministic when possible.
        try:
            torch.use_deterministic_algorithms(True, warn_only=True)
        except TypeError:
            try:
                torch.use_deterministic_algorithms(True)
            except Exception:
                pass
        except Exception:
            pass

        # Stabilize CPU threading behavior across runs.
        try:
            torch.set_num_threads(1)
        except Exception:
            pass

        try:
            torch.set_num_interop_threads(1)
        except Exception:
            pass

        os.environ.setdefault("TOKENIZERS_PARALLELISM", "false")
        os.environ.setdefault("OMP_NUM_THREADS", "1")
        os.environ.setdefault("MKL_NUM_THREADS", "1")

    def _build_embedding_model(self):
        """
        Load the IndoSBERT-large sentence transformer.

        denaya/indoSBERT-large adalah IndoBERT-large yang dilatih ulang
        menggunakan Siamese Network, menghasilkan 256-dim sentence embeddings
        berkualitas tinggi untuk Bahasa Indonesia.
        """
        logger.info(
            f"Loading sentence encoder: {self.params.embedding_model} "
            f"(IndoBERT-large + Siamese Network, output: 256-dim)"
        )

        try:
            return SentenceTransformer(self.params.embedding_model)
        except Exception as e:
            error_text = str(e)
            network_hints = (
                "Failed to resolve",
                "Temporary failure in name resolution",
                "NameResolutionError",
                "Network is unreachable",
                "Max retries exceeded",
                "HTTPSConnectionPool",
            )

            if any(hint in error_text for hint in network_hints):
                raise RuntimeError(
                    "Gagal download/load embedding model dari HuggingFace. "
                    "Pastikan container FastAPI bisa akses internet dan DNS stabil. "
                    "Host yang biasanya dibutuhkan: huggingface.co dan cas-bridge.xethub.hf.co. "
                    "Jika jaringan kampus/ISP memblokir, coba VPN/hotspot atau jalankan sekali saat internet stabil "
                    "agar model ter-cache, lalu ulangi training."
                ) from e

            raise

    def _build_umap_model(self):
        """Configure UMAP dimensionality reduction."""
        p = self.params.umap_params
        return UMAP(
            n_neighbors=p.n_neighbors,
            n_components=p.n_components,
            min_dist=p.min_dist,
            metric=p.metric,
            random_state=p.random_state,
            transform_seed=int(self.params.seed),
        )

    def _build_hdbscan_model(self):
        """Configure HDBSCAN clustering."""
        p = self.params.hdbscan_params
        return HDBSCAN(
            min_cluster_size=p.min_cluster_size,
            min_samples=p.min_samples,
            metric=p.metric,
            cluster_selection_method=p.cluster_selection_method,
            prediction_data=True,
            core_dist_n_jobs=1,
        )

    def _build_vectorizer(self, use_fallback: bool = False):
        """
        Configure CountVectorizer untuk c-TF-IDF topic representation.

        Parameter:
          - min_df=2   : kata harus muncul minimal di 2 dokumen (toleran untuk cluster kecil)
          - max_df=0.95: hapus kata yang muncul di >95% dokumen (terlalu umum)
          - token_pattern: ambil kata dengan minimal 3 huruf
          - stop_words=None: stopword sudah ditangani di preprocessing
        """
        n_gram_range = tuple(self.params.n_gram_range)

        # Stopwords only affect topic representation (c-TF-IDF keywords),
        # not the semantic embedding/clustering itself.
        stopwords = sorted(load_stopwords(language="indonesian", include_academic=True))
        return CountVectorizer(
            ngram_range=n_gram_range,
            stop_words=stopwords,
            min_df=(self.params.vectorizer_fallback_min_df if use_fallback else self.params.vectorizer_min_df),
            max_df=(self.params.vectorizer_fallback_max_df if use_fallback else self.params.vectorizer_max_df),
            token_pattern=self.params.vectorizer_token_pattern,
        )

    def _build_representation_model(self):
        """Optional MMR representation, aligned with notebook final pipeline."""
        if not self.params.use_mmr_representation:
            return None

        from bertopic.representation import MaximalMarginalRelevance

        return MaximalMarginalRelevance(diversity=self.params.mmr_diversity)

    @staticmethod
    def _is_vectorizer_df_error(exc: Exception) -> bool:
        current: Optional[BaseException] = exc
        while current is not None:
            message = str(current).lower()
            if "max_df corresponds to < documents than min_df" in message:
                return True
            if (
                "max_df" in message
                and "min_df" in message
                and "documents" in message
                and "correspond" in message
            ):
                return True
            current = current.__cause__ or current.__context__
        return False

    @staticmethod
    def _df_to_doc_count(df_value: int | float, n_docs: int, *, ceil_value: bool) -> int:
        """Convert sklearn df threshold into an absolute document count."""
        if isinstance(df_value, float) and 0.0 < df_value <= 1.0:
            scaled = df_value * n_docs
            return int(np.ceil(scaled) if ceil_value else np.floor(scaled))
        return int(df_value)

    def _should_use_fallback_vectorizer_for_topics(self, topic_doc_count: int) -> bool:
        """Check whether configured min_df/max_df are invalid for topic-level c-TF-IDF."""
        if topic_doc_count <= 0:
            return True

        min_doc_count = self._df_to_doc_count(
            self.params.vectorizer_min_df,
            topic_doc_count,
            ceil_value=True,
        )
        max_doc_count = self._df_to_doc_count(
            self.params.vectorizer_max_df,
            topic_doc_count,
            ceil_value=False,
        )
        return max_doc_count < min_doc_count

    def _build_model(self, use_fallback_vectorizer: bool = False):
        """Build the full BERTopic pipeline."""
        embedding_model = self._build_embedding_model()
        self.embedding_model = embedding_model
        umap_model = self._build_umap_model()
        hdbscan_model = self._build_hdbscan_model()
        self.vectorizer_model = self._build_vectorizer(use_fallback=use_fallback_vectorizer)
        self.representation_model = self._build_representation_model()

        self.model = BERTopic(
            embedding_model=embedding_model,
            umap_model=umap_model,
            hdbscan_model=hdbscan_model,
            vectorizer_model=self.vectorizer_model,
            representation_model=self.representation_model,
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
        logger.info(
            f"Computing sentence embeddings for {len(documents)} documents "
            f"using {self.params.embedding_model}..."
        )
        try:
            embedding_model = self.embedding_model or self._build_embedding_model()
            self.embedding_model = embedding_model
        except Exception as e:
            error_text = str(e)
            network_hints = (
                "Failed to resolve",
                "Temporary failure in name resolution",
                "NameResolutionError",
                "Network is unreachable",
                "Max retries exceeded",
                "HTTPSConnectionPool",
            )

            if any(hint in error_text for hint in network_hints):
                raise RuntimeError(
                    "Gagal download/load embedding model dari HuggingFace saat menghitung embeddings. "
                    "Cek koneksi internet/DNS dari container FastAPI. "
                    "Coba akses https://huggingface.co dari dalam container atau gunakan VPN/hotspot."
                ) from e

            raise
        embeddings = embedding_model.encode(
            documents,
            show_progress_bar=True,
            batch_size=self.params.embedding_batch_size,
            convert_to_numpy=True,
        )
        self.embeddings = embeddings
        logger.info(f"Embeddings shape: {embeddings.shape} (dim={embeddings.shape[1]})")
        return embeddings

    def train(
        self,
        documents: List[str],
        embeddings: Optional[np.ndarray] = None,
        timestamps: Optional[List[int]] = None,
        document_ids: Optional[List[int]] = None,
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

        self._set_reproducibility()
        logger.info(f"Reproducibility settings applied with seed={self.params.seed}")

        # Build model
        self._build_model()

        # Compute embeddings if not provided
        if embeddings is None:
            embeddings = self.compute_embeddings(documents)

        # Fit the model
        logger.info("Fitting BERTopic model (UMAP → HDBSCAN → c-TF-IDF + MMR)...")
        try:
            self.topics, self.probabilities = self.model.fit_transform(
                documents, embeddings=embeddings
            )
        except ValueError as e:
            if self._is_vectorizer_df_error(e):
                logger.warning("Vectorizer df constraint failed, retrying with fallback min_df/max_df")
                self._build_model(use_fallback_vectorizer=True)
                self.topics, self.probabilities = self.model.fit_transform(
                    documents, embeddings=embeddings
                )
            else:
                raise

        # Hitung outlier sebelum reduce
        outlier_count_before = int((np.array(self.topics) == -1).sum())
        logger.info(f"Outliers before reduce: {outlier_count_before}/{len(documents)}")

        if self.params.reduce_outliers and outlier_count_before > 0:
            logger.info("Reducing outliers (c-tf-idf -> distributions)...")
            try:
                self.topics = self.model.reduce_outliers(
                    documents,
                    self.topics,
                    strategy="c-tf-idf",
                    threshold=self.params.reduce_outliers_threshold_ctfidf,
                )
            except ValueError as e:
                if "No outliers to reduce" in str(e):
                    logger.info("No outliers detected during c-tf-idf reduction. Skipping reduction stage.")
                else:
                    raise

            remaining_outliers = int((np.array(self.topics) == -1).sum())
            if self.params.reduce_outliers_use_distributions and remaining_outliers > 0:
                try:
                    self.topics = self.model.reduce_outliers(
                        documents,
                        self.topics,
                        strategy="distributions",
                        threshold=self.params.reduce_outliers_threshold_distributions,
                    )
                except ValueError as e:
                    if "No outliers to reduce" in str(e):
                        logger.info("No outliers detected during distributions reduction. Skipping reduction stage.")
                    else:
                        raise

            # Keep representation consistent after outlier reassignment.
            topic_doc_count = len({int(topic_id) for topic_id in self.topics if int(topic_id) != -1})
            use_fallback_for_update = self._should_use_fallback_vectorizer_for_topics(topic_doc_count)
            update_vectorizer = self.vectorizer_model

            if use_fallback_for_update:
                logger.warning(
                    "Topic-level c-TF-IDF has {} topic documents; using fallback "
                    "vectorizer min_df={} max_df={} before update_topics",
                    topic_doc_count,
                    self.params.vectorizer_fallback_min_df,
                    self.params.vectorizer_fallback_max_df,
                )
                update_vectorizer = self._build_vectorizer(use_fallback=True)

            try:
                with _suppress_bertopic_assignment_warning():
                    self.model.update_topics(
                        documents,
                        topics=self.topics,
                        vectorizer_model=update_vectorizer,
                        representation_model=self.representation_model,
                    )
                self.vectorizer_model = update_vectorizer
            except Exception as e:
                if self._is_vectorizer_df_error(e):
                    if use_fallback_for_update:
                        raise RuntimeError(
                            "Vectorizer df constraint failed during update_topics "
                            "even after fallback min_df/max_df"
                        ) from e
                    logger.warning(
                        "Vectorizer df constraint failed during update_topics, "
                        "retrying with fallback min_df/max_df"
                    )
                    self.vectorizer_model = self._build_vectorizer(use_fallback=True)
                    with _suppress_bertopic_assignment_warning():
                        self.model.update_topics(
                            documents,
                            topics=self.topics,
                            vectorizer_model=self.vectorizer_model,
                            representation_model=self.representation_model,
                        )
                else:
                    raise
        elif self.params.reduce_outliers:
            logger.info("Outlier reduction skipped: no outliers found.")

        outlier_count_after = int((np.array(self.topics) == -1).sum())
        logger.info(f"Outliers after reduce: {outlier_count_after}/{len(documents)}")

        # Get topic info setelah update
        self.topic_info = self.model.get_topic_info()

        duration = time.time() - start_time

        # Count only valid topics (>= 0). Topic id -1 is BERTopic outlier bucket.
        if self.topic_info is not None and "Topic" in self.topic_info.columns:
            topic_ids = [int(topic_id) for topic_id in self.topic_info["Topic"].tolist()]
            num_topics = sum(1 for topic_id in topic_ids if topic_id >= 0)
        else:
            num_topics = len([topic_id for topic_id in self.model.get_topics().keys() if int(topic_id) >= 0])

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

        if document_ids is not None and len(document_ids) == len(self.topics):
            result["document_topics"] = [
                {
                    "skripsi_id": int(doc_id),
                    "topic_id": int(topic_id),
                    "is_outlier": int(topic_id) == -1,
                }
                for doc_id, topic_id in zip(document_ids, self.topics)
            ]

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

        stopwords = load_stopwords(language="indonesian", include_academic=True)

        topics = []
        for topic_id in self.model.get_topics():
            if topic_id == -1:
                continue  # Skip outlier topic
            words_scores = self.model.get_topic(topic_id)

            # Safety net: filter stopwords from displayed keywords.
            filtered_words_scores = [
                (w, s)
                for (w, s) in words_scores
                if w not in stopwords and len(w) >= 3
            ]
            if not filtered_words_scores:
                filtered_words_scores = words_scores

            topics.append({
                "topic_id": topic_id,
                "top_words": [w for w, _ in filtered_words_scores],
                "word_scores": [round(float(s), 4) for _, s in filtered_words_scores],
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
