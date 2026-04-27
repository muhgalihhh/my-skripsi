"""Gensim LDA trainer yang digunakan sebagai baseline topic model."""

import json
import os
import random
import time
from datetime import datetime
from typing import Any, Dict, List, Optional

import numpy as np
import pandas as pd
from app.core.config import lda_settings, path_settings
from app.models.schemas import LDAHyperparameters
from loguru import logger

from app.services.stopwords import load_stopwords


class LDATrainer:
    """
    Trainer untuk Gensim LDA model.
    """

    def __init__(self, params: Optional[LDAHyperparameters] = None):
        self.params = params or LDAHyperparameters(
            num_topics=lda_settings.LDA_NUM_TOPICS,
            passes=lda_settings.LDA_PASSES,
            iterations=lda_settings.LDA_ITERATIONS,
            chunksize=lda_settings.LDA_CHUNKSIZE,
            random_state=lda_settings.LDA_RANDOM_STATE,
            alpha=lda_settings.LDA_ALPHA,
            eta=lda_settings.LDA_ETA,
            no_below=lda_settings.LDA_NO_BELOW,
            no_above=lda_settings.LDA_NO_ABOVE,
        )
        self.model = None
        self.dictionary = None
        self.corpus = None
        self.tokenized_docs = None

    def _tokenize_documents(self, documents: List[str]) -> List[List[str]]:
        """Tokenize dokumen yang sudah diproses (simple whitespace split)."""
        return [doc.split() for doc in documents]

    def _set_reproducibility(self) -> None:
        """Set deterministic seeds and thread settings untuk LDA yang stabil."""
        seed = int(self.params.random_state)

        os.environ.setdefault("PYTHONHASHSEED", str(seed))
        os.environ.setdefault("OMP_NUM_THREADS", "1")
        os.environ.setdefault("MKL_NUM_THREADS", "1")

        random.seed(seed)
        np.random.seed(seed)

    def _build_dictionary(self, tokenized_docs: List[List[str]]):
        """Build Gensim dictionary with filtering."""
        from gensim.corpora import Dictionary

        self.dictionary = Dictionary(tokenized_docs)

        self.dictionary.filter_extremes(
            no_below=self.params.no_below,
            no_above=self.params.no_above,
        )

        logger.info(
            f"Dictionary built: {len(self.dictionary)} unique tokens "
            f"(after filtering: no_below={self.params.no_below}, "
            f"no_above={self.params.no_above})"
        )
        return self.dictionary

    def _build_corpus(self, tokenized_docs: List[List[str]]):
        """Build bag-of-words corpus."""
        self.corpus = [self.dictionary.doc2bow(doc) for doc in tokenized_docs]
        logger.info(f"Corpus built: {len(self.corpus)} documents")
        return self.corpus

    def _parse_alpha_eta(self, value: Any):
        """Parse alpha/eta parameter dari API payload menjadi nilai yang kompatibel dengan Gensim."""
        if value is None:
            return None

        if isinstance(value, (int, float)):
            return float(value)

        text = str(value).strip().lower()
        if text in ("none", "null", ""):
            return None
        if text in ("auto", "symmetric", "asymmetric"):
            return text

        try:
            return float(text)
        except (TypeError, ValueError):
            return "auto"

    def train(
        self,
        documents: List[str],
        timestamps: Optional[List[int]] = None,
    ) -> Dict[str, Any]:
        """Train LDA model dan secara opsional compute per-year topic distribution."""
        from gensim.models import LdaModel

        start_time = time.time()
        logger.info(f"Starting LDA training on {len(documents)} documents")

        self._set_reproducibility()

        self.tokenized_docs = self._tokenize_documents(documents)

        self._build_dictionary(self.tokenized_docs)
        self._build_corpus(self.tokenized_docs)

        alpha = self._parse_alpha_eta(self.params.alpha)
        eta = self._parse_alpha_eta(self.params.eta)

        logger.info(
            f"Training LDA with {self.params.num_topics} topics, "
            f"{self.params.passes} passes "
            f"(model: LdaModel deterministic, alpha={alpha}, eta={eta})..."
        )

        self.model = LdaModel(
            corpus=self.corpus,
            id2word=self.dictionary,
            num_topics=self.params.num_topics,
            passes=self.params.passes,
            iterations=self.params.iterations,
            random_state=self.params.random_state,
            alpha=alpha,
            eta=eta,
            per_word_topics=True,
        )

        duration = time.time() - start_time
        logger.info(f"LDA training complete in {duration:.2f}s")

        result = {
            "model_type": "lda",
            "num_topics": self.params.num_topics,
            "training_duration_seconds": round(duration, 2),
            "hyperparameters": self.params.model_dump(),
            "topic_info": self._extract_topic_info(),
        }

        if timestamps is not None:
            result["topics_per_year"] = self._compute_topics_per_year(
                documents, timestamps
            )

        return result

    def _extract_topic_info(self) -> List[Dict[str, Any]]:
        """Ekstrak informasi topik dalam format yang bisa di-serialize."""
        if self.model is None:
            return []

        stopwords = load_stopwords(language="indonesian", include_academic=True)

        topics = []
        for topic_id in range(self.params.num_topics):
            words_scores = self.model.show_topic(topic_id, topn=10)

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
            })

        return topics

    def _compute_topics_per_year(
        self,
        documents: List[str],
        timestamps: List[int],
    ) -> Dict[str, List[Dict[str, float]]]:
        """Hitung distribusi topik per tahun."""
        if self.model is None or self.corpus is None:
            return {}

        df = pd.DataFrame({"year": timestamps})

        # Ambil distribusi topik untuk setiap dokumen
        topic_distributions = []
        for bow in self.corpus:
            topic_dist = self.model.get_document_topics(bow, minimum_probability=0.0)
            dist_dict = {f"topic_{tid}": prob for tid, prob in topic_dist}
            topic_distributions.append(dist_dict)

        topic_df = pd.DataFrame(topic_distributions)
        df = pd.concat([df, topic_df], axis=1)

        # Aggregate by year
        result = {}
        for year, group in df.groupby("year"):
            topic_cols = [c for c in group.columns if c.startswith("topic_")]
            year_means = group[topic_cols].mean().to_dict()
            result[str(year)] = [
                {"topic_id": int(k.split("_")[1]), "proportion": round(v, 4)}
                for k, v in year_means.items()
            ]

        return result

    def save_model(self, job_id: str) -> str:
        """Simpan model yang sudah dilatih."""
        if self.model is None:
            raise ValueError("No trained model to save")

        path_settings.ensure_dirs()
        model_dir = path_settings.get_models_dir() / f"lda_{job_id}"
        model_dir.mkdir(parents=True, exist_ok=True)

        # Save LDA model
        model_path = str(model_dir / "lda_model")
        self.model.save(model_path)

        # Save dictionary
        dict_path = str(model_dir / "dictionary.dict")
        self.dictionary.save(dict_path)

        # Save metadata
        metadata = {
            "job_id": job_id,
            "model_type": "lda",
            "hyperparameters": self.params.model_dump(),
            "saved_at": datetime.now().isoformat(),
        }
        with open(model_dir / "metadata.json", "w") as f:
            json.dump(metadata, f, indent=2)

        logger.info(f"LDA model saved to {model_dir}")
        return str(model_dir)

    def load_model(self, job_id: str):
        """Load model yang sudah dilatih sebelumnya."""
        from gensim.corpora import Dictionary
        from gensim.models import LdaModel

        model_dir = path_settings.get_models_dir() / f"lda_{job_id}"

        if not model_dir.exists():
            raise FileNotFoundError(f"Model not found: {model_dir}")

        self.model = LdaModel.load(str(model_dir / "lda_model"))
        self.dictionary = Dictionary.load(str(model_dir / "dictionary.dict"))

        logger.info(f"LDA model loaded from {model_dir}")
        return self.model
