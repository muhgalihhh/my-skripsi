"""Stopword utilities.

Centralized stopword list for Indonesian topic modeling outputs.
Used by:
- LDA preprocessing pipeline (bag-of-words)
- BERTopic topic representation (c-TF-IDF keywords)

Goal: ensure displayed keywords are meaningful (remove common/function words).
"""

from __future__ import annotations

import os
from functools import lru_cache
from typing import Set

from loguru import logger


ACADEMIC_STOPWORDS: Set[str] = {
    "penelitian",
    "menggunakan",
    "digunakan",
    "berdasarkan",
    "hasil",
    "menunjukkan",
    "bahwa",
    "dapat",
    "dilakukan",
    "metode",
    "sistem",
    "data",
    "proses",
    "dalam",
    "dengan",
    "untuk",
    "pada",
    "dari",
    "yang",
    "ini",
    "tersebut",
    "adalah",
    "merupakan",
    "yaitu",
    "juga",
    "serta",
    "atau",
    "dan",
    "di",
    "ke",
    "se",
    "ber",
    "ter",
    "per",
    "skripsi",
    "tugas",
    "akhir",
    "universitas",
    "mahasiswa",
    "informatika",
    "jurusan",
    "program",
    "studi",
}


@lru_cache(maxsize=8)
def load_stopwords(language: str = "indonesian", include_academic: bool = True) -> Set[str]:
    """Load stopwords for a language, optionally adding academic stopwords.

    Returns a lowercase set suitable for sklearn CountVectorizer(stop_words=...).
    """

    stopwords: Set[str] = set()

    try:
        import nltk
        from nltk.corpus import stopwords as nltk_stopwords

        # Prefer loading from pre-bundled NLTK_DATA (set in Dockerfile).
        # Only download if the corpus is genuinely missing.
        try:
            stopwords.update(w.lower() for w in nltk_stopwords.words(language))
        except LookupError:
            writable_dir = os.getenv("NLTK_DATA_WRITABLE", "/app/data/nltk_data")
            try:
                os.makedirs(writable_dir, exist_ok=True)
                nltk.download("stopwords", download_dir=writable_dir, quiet=True)
                stopwords.update(w.lower() for w in nltk_stopwords.words(language))
            except Exception as e:
                logger.warning(
                    f"Could not download NLTK stopwords to {writable_dir} ({language}): {e}"
                )
    except Exception as e:
        logger.warning(f"Could not load NLTK stopwords ({language}): {e}")

    if include_academic:
        stopwords.update(ACADEMIC_STOPWORDS)

    return stopwords
