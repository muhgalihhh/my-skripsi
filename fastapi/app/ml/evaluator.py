"""
Evaluation Service
Computes Topic Coherence (C_v) and Topic Diversity metrics
for both BERTopic and LDA models.
"""

import math
from typing import Any, Dict, List, Optional

from loguru import logger


class TopicEvaluator:
    """
    Evaluator for topic models.

    Metrics:
        - Topic Coherence (C_v): Measures how interpretable topics are
        - Topic Diversity: Measures uniqueness of topics (% unique words across topics)
    """

    def compute_coherence_gensim(
        self,
        model,
        tokenized_docs: List[List[str]],
        dictionary,
        coherence_type: str = "c_v",
    ) -> float:
        """
        Compute coherence score using Gensim's CoherenceModel.
        Works directly with Gensim LDA models.

        Args:
            model: Trained Gensim LDA model
            tokenized_docs: List of tokenized documents
            dictionary: Gensim dictionary
            coherence_type: Type of coherence ('c_v', 'c_npmi', 'u_mass')

        Returns:
            Coherence score
        """
        from gensim.models.coherencemodel import CoherenceModel

        logger.info(f"Computing {coherence_type} coherence for LDA...")

        coherence_model = CoherenceModel(
            model=model,
            texts=tokenized_docs,
            dictionary=dictionary,
            coherence=coherence_type,
        )

        score = coherence_model.get_coherence()
        logger.info(f"LDA Coherence ({coherence_type}): {score:.4f}")
        return round(float(score), 4)

    def compute_coherence_from_topics(
        self,
        topics: List[List[str]],
        tokenized_docs: List[List[str]],
        dictionary=None,
        coherence_type: str = "c_v",
    ) -> float:
        """
        Compute coherence score from a list of topic word lists.
        Works with BERTopic or any model that outputs topic words.

        Args:
            topics: List of topics, each topic is a list of words
            tokenized_docs: List of tokenized documents
            dictionary: Gensim dictionary (will be created if None)
            coherence_type: Type of coherence ('c_v', 'c_npmi', 'u_mass')

        Returns:
            Coherence score
        """
        from gensim.corpora import Dictionary
        from gensim.models.coherencemodel import CoherenceModel

        logger.info(f"Computing {coherence_type} coherence from topic words...")

        if len(topics) < 2:
            return float("nan")

        if dictionary is None:
            dictionary = Dictionary(tokenized_docs)

        coherence_model = CoherenceModel(
            topics=topics,
            texts=tokenized_docs,
            dictionary=dictionary,
            coherence=coherence_type,
        )

        score = coherence_model.get_coherence()
        logger.info(f"Coherence ({coherence_type}): {score:.4f}")
        return round(float(score), 4)

    def compute_topic_diversity(
        self,
        topics: List[List[str]],
        top_n: int = 10,
    ) -> float:
        """
        Compute Topic Diversity.

        Topic Diversity = (number of unique words) / (total number of words across all topics)
        Range: 0 (all topics have same words) to 1 (all topics have unique words)

        Args:
            topics: List of topics, each topic is a list of top-N words
            top_n: Number of top words to consider per topic

        Returns:
            Topic diversity score (0-1)
        """
        if not topics:
            return 0.0

        # Take only top_n words per topic
        truncated = [t[:top_n] for t in topics]

        # Flatten all words
        all_words = [word for topic in truncated for word in topic]
        unique_words = set(all_words)

        if len(all_words) == 0:
            return 0.0

        diversity = len(unique_words) / len(all_words)
        logger.info(
            f"Topic Diversity: {diversity:.4f} "
            f"({len(unique_words)} unique / {len(all_words)} total words)"
        )
        return round(float(diversity), 4)

    def evaluate_bertopic(
        self,
        model,
        documents: List[str],
        tokenized_docs: Optional[List[List[str]]] = None,
        topics: Optional[List[int]] = None,
        vectorizer_model=None,
        coherence_type: str = "c_v",
        coherence_tokenization: str = "vectorizer",
        coherence_dict_no_below: int = 3,
        coherence_dict_no_above: float = 0.95,
        top_n_words: int = 10,
    ) -> Dict[str, Any]:
        """
        Full evaluation of a BERTopic model.

        Args:
            model: Trained BERTopic model
            documents: Original (preprocessed) documents
            tokenized_docs: Pre-tokenized documents (optional)

        Returns:
            Dictionary with evaluation metrics
        """
        if coherence_tokenization.strip().lower() != "vectorizer":
            logger.warning(
                f"coherence_tokenization={coherence_tokenization} ignored; forced to 'vectorizer'"
            )

        if vectorizer_model is not None:
            analyzer = vectorizer_model.build_analyzer()
            tokenized_docs = [analyzer(doc) for doc in documents]
        elif tokenized_docs is None:
            tokenized_docs = [doc.split() for doc in documents]

        # Extract topic word lists
        topic_words = []
        for topic_id in model.get_topics():
            if topic_id == -1:
                continue
            words = [w for w, _ in model.get_topic(topic_id)[:top_n_words]]
            topic_words.append(words)

        # Compute metrics
        from gensim.corpora import Dictionary

        dictionary = Dictionary(tokenized_docs)
        dictionary.filter_extremes(
            no_below=coherence_dict_no_below,
            no_above=coherence_dict_no_above,
        )

        coherence = self.compute_coherence_from_topics(
            topics=topic_words,
            tokenized_docs=tokenized_docs,
            dictionary=dictionary,
            coherence_type=coherence_type,
        )
        diversity = self.compute_topic_diversity(topic_words, top_n=top_n_words)

        if topics is None:
            model_topics = getattr(model, "topics_", None)
            if model_topics is not None and len(model_topics) == len(documents):
                topics = [int(t) for t in model_topics]

        if topics:
            total_docs = len(topics)
            outlier_count = int(sum(int(t) == -1 for t in topics))
        else:
            total_docs = len(documents)
            outlier_count = 0

        outlier_pct = round(outlier_count / max(total_docs, 1) * 100.0, 2)

        coh_safe = 0.0 if not isinstance(coherence, (int, float)) or math.isnan(coherence) else float(coherence)
        div_safe = 0.0 if not isinstance(diversity, (int, float)) or math.isnan(diversity) else float(diversity)

        score = round(coh_safe * div_safe * (1 - outlier_pct / 100.0), 6)
        score_hmean_cv_td = round((2 * coh_safe * div_safe) / (coh_safe + div_safe + 1e-12), 6)
        score_cv_td = round((0.65 * coh_safe + 0.35 * div_safe) * (1 - outlier_pct / 100.0), 6)

        return {
            "coherence_cv": coherence,
            "topic_diversity": diversity,
            "num_topics": len(topic_words),
            "num_outliers": outlier_count,
            "outlier_pct": outlier_pct,
            "score": score,
            "score_hmean_cv_td": score_hmean_cv_td,
            "score_cv_td": score_cv_td,
        }

    def evaluate_lda(
        self,
        model,
        tokenized_docs: List[List[str]],
        dictionary,
    ) -> Dict[str, Any]:
        """
        Full evaluation of an LDA model.

        Args:
            model: Trained Gensim LDA model
            tokenized_docs: Tokenized documents
            dictionary: Gensim dictionary

        Returns:
            Dictionary with evaluation metrics
        """
        # Coherence
        coherence = self.compute_coherence_gensim(
            model=model,
            tokenized_docs=tokenized_docs,
            dictionary=dictionary,
        )

        # Topic words for diversity
        topic_words = []
        for topic_id in range(model.num_topics):
            words = [w for w, _ in model.show_topic(topic_id, topn=10)]
            topic_words.append(words)

        diversity = self.compute_topic_diversity(topic_words)

        coh_safe = 0.0 if not isinstance(coherence, (int, float)) or math.isnan(coherence) else float(coherence)
        div_safe = 0.0 if not isinstance(diversity, (int, float)) or math.isnan(diversity) else float(diversity)
        score = round(coh_safe * div_safe, 6)

        return {
            "coherence_cv": coherence,
            "topic_diversity": diversity,
            "num_topics": model.num_topics,
            "score": score,
        }
