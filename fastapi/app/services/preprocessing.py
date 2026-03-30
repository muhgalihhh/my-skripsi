"""
Text Preprocessing Service
Handles text cleaning, tokenization, stopword removal, and stemming
specifically for Indonesian academic text (abstracts/titles).

Dual preprocessing pipeline:
  - BERTopic pipeline (preprocess_cleaned): teks NATURAL untuk IndoSBERT
    → Hanya basic clean: lowercase, hapus URL/email/karakter aneh
    → TIDAK hapus stopword → IndoSBERT sudah punya konteks semantik sendiri
    → TIDAK filter kata pendek → terlalu agresif untuk sentence embedding
    → TIDAK stemming → IndoSBERT dilatih pada teks natural
  - LDA pipeline (preprocess): teks STEMMED + FILTERED untuk bag-of-words
    → Full clean + stopword removal + stemming (Sastrawi)
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

        # Tambah stopword akademik umum (untuk LDA pipeline)
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
            # Sastrawi Python package menggunakan snake_case: create_stemmer()
            self._stemmer = factory.create_stemmer()
        except ImportError:
            logger.warning("Sastrawi not installed, stemming will be skipped")
            self._stemmer = None
        except Exception as e:
            logger.warning(f"Error loading Sastrawi stemmer: {e}")
            self._stemmer = None

        return self._stemmer

    def clean_text_for_embedding(self, text: str) -> str:
        """
        Pipeline MINIMAL untuk BERTopic / IndoSBERT embedding.

        IndoSBERT (Siamese dari IndoBERT-large) dilatih pada kalimat Bahasa Indonesia
        yang NATURAL — termasuk tanda baca. Titik dan koma penting untuk model karena:
          - Menandai akhir kalimat → mempengaruhi sentence boundary detection
          - Berkontribusi pada konteks semantik kalimat (misal: "baik, namun" ≠ "baik namun")

        Pipeline (sesuai notebook analisis):
          1. Lowercase
          2. Hapus URL & email
          3. Hapus artifact PDF & karakter tidak lazim (karakter kontrol, dsb)
          4. PERTAHANKAN tanda baca standar (. , ! ? : ; - ())
          5. Normalisasi whitespace

        TIDAK:
          - TIDAK hapus stopword → IndoSBERT sudah paham konteks gramatikal
          - TIDAK filter kata pendek → "di", "ke", "ia" punya peran gramatikal
          - TIDAK stemming → IndoSBERT butuh kata dalam bentuk aslinya
          - Tanda baca DIPERTAHANKAN untuk kualitas embedding kalimat
        """
        if not text or not isinstance(text, str):
            return ""

        # Lowercase
        text = text.lower()
        # Hapus URL
        text = re.sub(r"https?://\S+|www\.\S+", "", text)
        # Hapus email
        text = re.sub(r"[\w.+-]+@[\w-]+\.[\w.-]+", "", text)
        # Hapus karakter kontrol dan non-printable (bukan tanda baca lazim)
        # Pertahankan: huruf, angka, spasi, tanda baca standar (.,!?:;-()[]"')
        text = re.sub(r"[^\w\s.,!?:;\-()\[\]\"']", " ", text)
        # Normalisasi whitespace (tapi jangan hapus newline yang jadi spasi)
        text = re.sub(r"\s+", " ", text).strip()

        return text

    def clean_text(self, text: str) -> str:
        """
        Basic text cleaning untuk LDA pipeline.
        Lebih agresif: hapus angka, hapus tanda baca.
        """
        if not text or not isinstance(text, str):
            return ""

        # Lowercase
        text = text.lower()
        # Hapus URL
        text = re.sub(r"http\S+|www\.\S+", "", text)
        # Hapus email
        text = re.sub(r"\S+@\S+", "", text)
        # Hapus angka
        text = re.sub(r"\d+", "", text)
        # Hapus tanda baca dan karakter khusus
        text = re.sub(r"[^\w\s]", " ", text)
        # Normalisasi whitespace
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
        """
        Pipeline BERTopic / IndoSBERT — teks NATURAL tanpa stemming.

        Hanya clean ringan: lowercase, hapus URL/email/simbol berlebih.
        TIDAK hapus stopword, TIDAK filter kata pendek, TIDAK stemming.
        IndoSBERT sudah di-train pada kalimat natural Bahasa Indonesia.

        Output: teks bersih tapi masih natural (kata-kata tetap lengkap).
        """
        return self.clean_text_for_embedding(text)

    def preprocess(self, text: str) -> str:
        """
        Full preprocessing pipeline untuk LDA (bag-of-words).

        Pipeline:
          1. Clean (hapus URL/email/angka/tanda baca)
          2. Tokenize
          3. Filter by length (min_word_length)
          4. Remove stopwords
          5. Stemming (Sastrawi)
          6. Filter by length lagi (setelah stemming)

        Output: teks yang sudah di-stem dan di-filter.
        """
        # Clean agresif untuk LDA
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
        # Filter lagi setelah stemming
        tokens = self.filter_by_length(tokens)

        return " ".join(tokens)

    def preprocess_dataframe(
        self,
        df: pd.DataFrame,
        text_column: str = "abstract",
    ) -> pd.DataFrame:
        """
        Preprocess semua teks dalam DataFrame dengan DUAL pipeline:

        - cleaned_text  : untuk BERTopic (IndoSBERT)
                          → clean ringan, TIDAK stemming, TIDAK hapus stopword
        - processed_text: untuk LDA
                          → clean agresif + stopword removal + stemming

        Kedua kolom dihasilkan dalam satu pass untuk konsistensi.
        """
        logger.info(f"Preprocessing {len(df)} documents (dual pipeline)...")

        df = df.copy()

        # Pipeline 1: clean ringan — untuk BERTopic / IndoSBERT
        logger.info("  [BERTopic pipeline] Soft clean (no stopword removal, no stemming)...")
        df["cleaned_text"] = df[text_column].apply(self.preprocess_cleaned)

        # Pipeline 2: full preprocessing — untuk LDA
        logger.info("  [LDA pipeline] Full clean + stopword removal + stemming...")
        df["processed_text"] = df[text_column].apply(self.preprocess)

        # Hapus dokumen di mana KEDUA output kosong
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
