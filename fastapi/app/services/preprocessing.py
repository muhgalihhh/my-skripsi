"""Text preprocessing service aligned with notebook pipeline.

Dual pipeline output:
- cleaned_text   : soft clean for BERTopic/IndoSBERT
- processed_text : full clean + stopword + stemming for LDA
"""

import re
from typing import List, Optional

import pandas as pd
from loguru import logger

from app.services.stopwords import load_stopwords


class TextPreprocessor:
    """Preprocessor for Indonesian academic text."""

    def __init__(
        self,
        remove_stopwords: bool = True,
        use_stemming: bool = True,
        min_word_length: int = 3,
        language: str = "indonesian",
    ):
        self.remove_stopwords = remove_stopwords
        self.use_stemming = use_stemming
        self.min_word_length = min_word_length
        self.language = language

        self._stopwords: Optional[set] = None
        self._stemmer = None

    def _load_stopwords(self) -> set:
        """Load Indonesian stopwords from NLTK + custom academic stopwords."""
        if self._stopwords is not None:
            return self._stopwords

        self._stopwords = set(load_stopwords(language=self.language, include_academic=True))
        return self._stopwords

    def _load_stemmer(self):
        """Load Sastrawi Indonesian stemmer."""
        if self._stemmer is not None:
            return self._stemmer

        try:
            from Sastrawi.Stemmer.StemmerFactory import StemmerFactory

            factory = StemmerFactory()
            self._stemmer = factory.create_stemmer()
        except ImportError:
            logger.warning("Sastrawi not installed, stemming will be skipped")
            self._stemmer = None
        except Exception as e:
            logger.warning(f"Error loading Sastrawi stemmer: {e}")
            self._stemmer = None

        return self._stemmer

    def deep_clean_pdf_text(self, text: str) -> str:
        """Notebook-clean aligned text normalization (light cleaning)."""
        if not text or not isinstance(text, str):
            return ""

        text = text.replace("\r\n", " ").replace("\n", " ").replace("\r", " ")
        text = re.sub(r"https?://\S+|www\.\S+", " ", text)
        text = re.sub(r"\S+@\S+", " ", text)
        text = re.sub(r"\b(?:doi|DOI)\s*:\s*\S+", " ", text)
        text = re.sub(r"\s+", " ", text).strip()
        return text

    def _is_valid_conclusion(self, text: str, min_chars: int = 100) -> bool:
        t = (text or "").strip()
        if not t:
            return False
        if t.lower() in ("nan", "none", "tidak tersedia"):
            return False
        return len(self.deep_clean_pdf_text(t)) >= min_chars

    def combine_text(self, title: str, abstract: str, conclusion: str) -> str:
        """Build combined_text: title + abstract (+ valid conclusion)."""
        clean_title = self.deep_clean_pdf_text(str(title or "").strip())
        clean_abstract = self.deep_clean_pdf_text(str(abstract or "").strip())
        clean_conclusion = self.deep_clean_pdf_text(str(conclusion or "").strip())

        if self._is_valid_conclusion(clean_conclusion, min_chars=100):
            return f"{clean_title}. {clean_abstract}. {clean_conclusion}".strip()
        return f"{clean_title}. {clean_abstract}".strip()

    def clean_text_for_embedding(self, text: str) -> str:
        """Notebook-clean BERTopic path: lowercase + whitespace normalization."""
        if not text or not isinstance(text, str):
            return ""

        text = self.deep_clean_pdf_text(text)
        text = text.lower()
        text = re.sub(r"\s+", " ", text).strip()

        return text

    def clean_text(self, text: str) -> str:
        """Notebook-clean LDA path: remove digits and keep alphabetic tokens only."""
        if not text or not isinstance(text, str):
            return ""

        text = self.deep_clean_pdf_text(text)
        text = text.lower()
        text = re.sub(r"\d+", " ", text)
        text = re.sub(r"[^a-zA-Z\s]", " ", text)
        text = re.sub(r"\s+", " ", text).strip()

        return text

    def tokenize(self, text: str) -> List[str]:
        """Simple whitespace tokenization after cleaning."""
        return text.split()

    def remove_stopwords_from_tokens(self, tokens: List[str]) -> List[str]:
        """Remove stopwords from token list."""
        stopwords = self._load_stopwords()
        return [t for t in tokens if t not in stopwords]

    def stem_tokens(self, tokens: List[str]) -> List[str]:
        """Apply stemming to tokens."""
        stemmer = self._load_stemmer()
        if stemmer is None:
            return tokens
        return [stemmer.stem(t) for t in tokens]

    def filter_by_length(self, tokens: List[str]) -> List[str]:
        """Filter tokens by minimum length."""
        return [t for t in tokens if len(t) >= self.min_word_length]

    def preprocess_cleaned(self, text: str) -> str:
        """BERTopic pipeline: keep natural text form with light normalization only."""
        return self.clean_text_for_embedding(text)

    def preprocess(self, text: str) -> str:
        """Full LDA pipeline: clean, tokenize, filter, stopword removal, stemming."""
        cleaned = self.clean_text(text)
        tokens = self.tokenize(cleaned)
        tokens = self.filter_by_length(tokens)
        if self.remove_stopwords:
            tokens = self.remove_stopwords_from_tokens(tokens)
        if self.use_stemming:
            tokens = self.stem_tokens(tokens)
        tokens = self.filter_by_length(tokens)

        return " ".join(tokens)

    def preprocess_dataframe(
        self,
        df: pd.DataFrame,
        text_column: str = "abstract",
        title_column: str = "title",
        conclusion_column: str = "conclusion",
    ) -> pd.DataFrame:
        """Generate `cleaned_text` and `processed_text` in one preprocessing pass."""
        logger.info(f"Preprocessing {len(df)} documents (dual pipeline)...")

        df = df.copy()

        if title_column in df.columns:
            title_series = df[title_column]
        else:
            title_series = pd.Series([""] * len(df))

        if conclusion_column in df.columns:
            conclusion_series = df[conclusion_column]
        else:
            conclusion_series = pd.Series([""] * len(df))

        logger.info("  Building combined_text (title + abstract + optional conclusion)...")
        df["combined_text"] = [
            self.combine_text(t, a, c)
            for t, a, c in zip(title_series, df[text_column], conclusion_series)
        ]

        logger.info("  [BERTopic pipeline] Soft clean (no stopword removal, no stemming)...")
        df["cleaned_text"] = df["combined_text"].apply(self.preprocess_cleaned)

        logger.info("  [LDA pipeline] Full clean + stopword removal + stemming...")
        df["processed_text"] = df["combined_text"].apply(self.preprocess)

        before_count = len(df)
        mask = (
            (df["cleaned_text"].str.strip().str.len() > 0)
            & (df["processed_text"].str.strip().str.len() > 0)
        )
        df = df[mask].reset_index(drop=True)
        after_count = len(df)

        if before_count != after_count:
            logger.warning(
                f"Removed {before_count - after_count} documents with empty text after preprocessing"
            )

        logger.info(f"Preprocessing complete. {after_count} documents remaining.")
        logger.info(
            f"  cleaned_text avg words: {df['cleaned_text'].str.split().str.len().mean():.1f}"
        )
        logger.info(
            f"  processed_text avg words: {df['processed_text'].str.split().str.len().mean():.1f}"
        )
        return df
