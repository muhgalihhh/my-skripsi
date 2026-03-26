"""
Text Preprocessing Service
Handles text cleaning, tokenization, stopword removal, and stemming
specifically for Indonesian academic text (abstracts/titles).
"""

import re
from typing import List, Optional

import pandas as pd
from loguru import logger


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

        try:
            import nltk

            nltk.download("stopwords", quiet=True)
            from nltk.corpus import stopwords as nltk_stopwords

            self._stopwords = set(nltk_stopwords.words("indonesian"))
        except Exception:
            logger.warning("Could not load NLTK stopwords, using empty set")
            self._stopwords = set()

        # Add common academic stopwords (Indonesian)
        academic_stopwords = {
            "penelitian", "menggunakan", "digunakan", "berdasarkan",
            "hasil", "menunjukkan", "bahwa", "dapat", "dilakukan",
            "metode", "sistem", "data", "proses", "dalam",
            "dengan", "untuk", "pada", "dari", "yang",
            "ini", "tersebut", "adalah", "merupakan", "yaitu",
            "juga", "serta", "atau", "dan", "di",
            "ke", "se", "ber", "ter", "per",
            "skripsi", "tugas", "akhir", "universitas", "mahasiswa",
            "informatika", "jurusan", "program", "studi",
        }
        self._stopwords.update(academic_stopwords)
        return self._stopwords

    def _load_stemmer(self):
        """Load Sastrawi Indonesian stemmer."""
        if self._stemmer is not None:
            return self._stemmer

        try:
            from Sastrawi.Stemmer.StemmerFactory import StemmerFactory

            factory = StemmerFactory()
            self._stemmer = factory.createStemmer()
        except ImportError:
            logger.warning("Sastrawi not installed, stemming will be skipped")
            self._stemmer = None

        return self._stemmer

    def clean_text(self, text: str) -> str:
        """Basic text cleaning."""
        if not text or not isinstance(text, str):
            return ""

        # Lowercase
        text = text.lower()
        # Remove URLs
        text = re.sub(r"http\S+|www\.\S+", "", text)
        # Remove emails
        text = re.sub(r"\S+@\S+", "", text)
        # Remove numbers
        text = re.sub(r"\d+", "", text)
        # Remove punctuation and special characters
        text = re.sub(r"[^\w\s]", " ", text)
        # Remove extra whitespace
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

    def preprocess(self, text: str) -> str:
        """Full preprocessing pipeline for a single text."""
        # Clean
        cleaned = self.clean_text(text)
        # Tokenize
        tokens = self.tokenize(cleaned)
        # Filter by length
        tokens = self.filter_by_length(tokens)
        # Remove stopwords
        if self.remove_stopwords:
            tokens = self.remove_stopwords_from_tokens(tokens)
        # Stemming
        if self.use_stemming:
            tokens = self.stem_tokens(tokens)
        # Filter again after stemming
        tokens = self.filter_by_length(tokens)

        return " ".join(tokens)

    def preprocess_cleaned(self, text: str) -> str:
        """
        Cleaned-only pipeline for BERTopic / IndoSBERT.

        IndoSBERT sudah di-train pada teks natural Bahasa Indonesia,
        sehingga TIDAK perlu stemming. Hanya perlu:
          1. Clean (lowercase, hapus URL/email/angka/tanda baca)
          2. Tokenize
          3. Filter by length
          4. Remove stopwords

        Output: teks bersih, BUKAN stemmed.
        """
        cleaned = self.clean_text(text)
        tokens = self.tokenize(cleaned)
        tokens = self.filter_by_length(tokens)
        if self.remove_stopwords:
            tokens = self.remove_stopwords_from_tokens(tokens)
        return " ".join(tokens)

    def preprocess_dataframe(
        self,
        df: pd.DataFrame,
        text_column: str = "abstract",
    ) -> pd.DataFrame:
        """
        Preprocess all texts in a DataFrame with DUAL output:

        - cleaned_text : untuk BERTopic (IndoSBERT) — cleaned, tanpa stemming
        - processed_text : untuk LDA — cleaned + tokenized + stopword removal + stemmed

        Kedua kolom dihasilkan dalam satu pass agar konsisten.
        """
        logger.info(f"Preprocessing {len(df)} documents (dual pipeline)...")

        df = df.copy()

        # Pipeline 1: cleaned only (for BERTopic / IndoSBERT)
        df["cleaned_text"] = df[text_column].apply(self.preprocess_cleaned)

        # Pipeline 2: full preprocessing (for LDA)
        df["processed_text"] = df[text_column].apply(self.preprocess)

        # Remove documents where BOTH outputs are empty
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
        return df
