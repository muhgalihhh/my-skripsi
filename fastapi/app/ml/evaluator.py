"""
Evaluation Service
Computes Topic Coherence (C_v) and Topic Diversity metrics
for both BERTopic and LDA models.
"""

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
        if tokenized_docs is None:
            tokenized_docs = [doc.split() for doc in documents]

        # Extract topic word lists
        topic_words = []
        for topic_id in model.get_topics():
            if topic_id == -1:
                continue
            words = [w for w, _ in model.get_topic(topic_id)]
            topic_words.append(words)

        # Compute metrics
        coherence = self.compute_coherence_from_topics(
            topics=topic_words,
            tokenized_docs=tokenized_docs,
        )
        diversity = self.compute_topic_diversity(topic_words)

        return {
            "coherence_cv": coherence,
            "topic_diversity": diversity,
            "num_topics": len(topic_words),
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

        return {
            "coherence_cv": coherence,
            "topic_diversity": diversity,
            "num_topics": model.num_topics,
        }

    def compare_models(
        self,
        bertopic_metrics: Dict[str, Any],
        lda_metrics: Dict[str, Any],
    ) -> Dict[str, Any]:
        """
        Compare BERTopic vs LDA evaluation results.

        Returns:
            Comparison summary dictionary
        """
        return {
            "bertopic": bertopic_metrics,
            "lda": lda_metrics,
            "coherence_winner": (
                "bertopic"
                if bertopic_metrics["coherence_cv"] > lda_metrics["coherence_cv"]
                else "lda"
            ),
            "diversity_winner": (
                "bertopic"
                if bertopic_metrics["topic_diversity"] > lda_metrics["topic_diversity"]
                else "lda"
            ),
            "coherence_diff": round(
                bertopic_metrics["coherence_cv"] - lda_metrics["coherence_cv"], 4
            ),
            "diversity_diff": round(
                bertopic_metrics["topic_diversity"] - lda_metrics["topic_diversity"], 4
            ),
        }
