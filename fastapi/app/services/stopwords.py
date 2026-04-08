"""Stopword utilities for Indonesian topic modeling."""

import os
from functools import lru_cache
from typing import Set

from loguru import logger


ACADEMIC_STOPWORDS: Set[str] = {
    # Keep this list aligned with notebook clean preprocessing for fair CV/TD comparison.
    "penelitian",
    "hasil",
    "metode",
    "sistem",
    "data",
    "skripsi",
    "mahasiswa",
    "menggunakan",
    "dengan",
    "dalam",
    "untuk",
    "pada",
    "dan",
    "yang",
}


@lru_cache(maxsize=8)
def load_stopwords(language: str = "indonesian", include_academic: bool = True) -> Set[str]:
    """Load stopwords for a language, optionally adding academic stopwords."""

    stopwords: Set[str] = set()

    try:
        import nltk
        from nltk.corpus import stopwords as nltk_stopwords

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
