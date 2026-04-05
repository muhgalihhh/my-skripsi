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
            # Sastrawi Python package menggunakan snake_case: create_stemmer()
            self._stemmer = factory.create_stemmer()
        except ImportError:
            logger.warning("Sastrawi not installed, stemming will be skipped")
            self._stemmer = None
        except Exception as e:
            logger.warning(f"Error loading Sastrawi stemmer: {e}")
            self._stemmer = None

        return self._stemmer

    def deep_clean_pdf_text(self, text: str) -> str:
        """Remove common PDF/scraping artifacts and normalize whitespace."""
        if not text or not isinstance(text, str):
            return ""

        text = text.replace("\\n", "\n")
        text = text.replace("\r\n", "\n").replace("\r", "\n")

        text = re.sub(r"Item\s+Type\s*:.*", "", text, flags=re.DOTALL | re.IGNORECASE)

        metadata_patterns = [
            r"Nomor\s+Inventaris\s*:\s*\S+",
            r"Uncontrolled\s+Keywords\s*:.*?(?=\n[A-Z]|\Z)",
            r"Subjects\s*:[A-Z\s>]+[A-Z0-9\s]+",
            r"Divisions\s*:.*?(?=\n[A-Z]|\Z)",
            r"Depositing\s+User\s*:.*?(?=\n|\Z)",
            r"Date\s+Deposited\s*:.*?(?=\n|\Z)",
            r"Last\s+Modified\s*:.*?(?=\n|\Z)",
            r"URI\s*:\s*http\S*",
            r"[A-Z][a-z]+\s+[A-Z][a-z]+\s*:\s*[A-Z0-9\s]+(?=\n|$)",
        ]
        for pattern in metadata_patterns:
            text = re.sub(pattern, " ", text, flags=re.IGNORECASE)

        text = re.sub(r"^\s*\d{1,4}\s*$", "", text, flags=re.MULTILINE)
        text = re.sub(r"(?:^|\n)\s*\d{1,4}\s*(?:\n|$)", "\n", text)

        text = re.sub(r"\bBAB\s+[IVXLCDM]+\b", "", text, flags=re.IGNORECASE)

        section_headers = [
            r"KESIMPULAN\s+DAN\s+SARAN",
            r"KESIMPULAN\s*&\s*SARAN",
            r"PENUTUP",
            r"KESIMPULAN",
            r"SARAN",
            r"DAFTAR\s+PUSTAKA",
            r"DAFTAR\s+REFERENSI",
            r"ABSTRAK",
            r"ABSTRACT",
            r"KATA\s+PENGANTAR",
            r"DAFTAR\s+ISI",
            r"DAFTAR\s+GAMBAR",
            r"DAFTAR\s+TABEL",
            r"DAFTAR\s+LAMPIRAN",
            r"PENDAHULUAN",
            r"TINJAUAN\s+PUSTAKA",
            r"LANDASAN\s+TEORI",
            r"METODOLOGI\s+PENELITIAN",
            r"METODE\s+PENELITIAN",
            r"HASIL\s+DAN\s+PEMBAHASAN",
        ]
        for header in section_headers:
            text = re.sub(rf"(?:^|\n)\s*{header}\s*(?:\n|$)", "\n", text, flags=re.IGNORECASE)

        text = re.sub(r"(?:^|\n)\s*\d+\.\d+\.?\s*", "\n", text)
        text = re.sub(r"(?:^|\n)\s*\d+\.\s+", "\n", text)
        text = re.sub(r"(?:^|\n)\s*[a-z]\.\s+", "\n", text)

        text = re.sub(r"(?:Gambar|Tabel|Lampiran)\s+\d+[\.\-]?\s*\d*", "", text, flags=re.IGNORECASE)
        text = re.sub(r"(?:halaman|hal\.?|hlm\.?)\s+\d+[\-–]?\d*", "", text, flags=re.IGNORECASE)

        text = re.sub(r"PDF\s*\([^)]*\)", "", text, flags=re.IGNORECASE)
        text = re.sub(r"https?://\S+|www\.\S+", "", text)
        text = re.sub(r"\S+@\S+\.\S+", "", text)
        text = re.sub(r"(?:doi|DOI)\s*:\s*\S+", "", text)
        text = re.sub(r"https?://doi\.org/\S+", "", text)

        text = text.replace("\u2013", "-").replace("\u2014", "-")
        text = text.replace("\u201c", '"').replace("\u201d", '"')
        text = text.replace("\u2018", "'").replace("\u2019", "'")

        text = re.sub(r"\n+", " ", text)
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

        text = self.deep_clean_pdf_text(text)
        text = text.lower()
        text = re.sub(r"[^\x20-\x7E\u00C0-\u024F\u1E00-\u1EFF\w\s.,;:!?\"'()\-/]", " ", text)
        text = re.sub(r"\s+", " ", text).strip()

        return text

    def clean_text(self, text: str) -> str:
        """
        Basic text cleaning untuk LDA pipeline.
        Lebih agresif: hapus angka, hapus tanda baca.
        """
        if not text or not isinstance(text, str):
            return ""

        text = self.deep_clean_pdf_text(text)
        text = text.lower()
        text = re.sub(r"\d+", "", text)
        text = re.sub(r"[^\w\s]", " ", text)
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
        title_column: str = "title",
        conclusion_column: str = "conclusion",
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

        # Build combined text first to align with notebook: title + abstract (+ valid conclusion)
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

        # Pipeline 1: clean ringan — untuk BERTopic / IndoSBERT
        logger.info("  [BERTopic pipeline] Soft clean (no stopword removal, no stemming)...")
        df["cleaned_text"] = df["combined_text"].apply(self.preprocess_cleaned)

        # Pipeline 2: full preprocessing — untuk LDA
        logger.info("  [LDA pipeline] Full clean + stopword removal + stemming...")
        df["processed_text"] = df["combined_text"].apply(self.preprocess)

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
