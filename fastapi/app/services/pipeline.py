"""Pipeline utilities aligned with thesis research stages.

This module centralizes dataset validation/loading for:
- text preprocessing outputs used in training
- dynamic topic analysis (DTA) inputs

By keeping this logic in one place, routes stay thin and the pipeline order
is easier to enforce consistently.
"""

from dataclasses import dataclass
from typing import Any, Dict, List, Optional

import pandas as pd
from app.core import database
from app.models.schemas import ModelType
from loguru import logger


@dataclass
class TrainingPayload:
    """Prepared training payload for one model type."""

    documents: List[str]
    timestamps: Optional[List[int]]
    document_ids: Optional[List[int]]


@dataclass
class DTAPayload:
    """Prepared payload for BERTopic topics-over-time analysis."""

    documents: List[str]
    years: List[int]
    year_range: List[int]


class PipelineService:
    """Helper service for loading and validating pipeline datasets."""

    REQUIRED_COLUMNS = {"id", "cleaned_text", "processed_text"}

    def _normalize_training_dataframe(self, df: pd.DataFrame) -> pd.DataFrame:
        """Apply shared cleaning rules for training datasets."""
        if df.empty:
            raise ValueError(
                "Processed data not found in DB. Run preprocessing first via /api/v1/preprocessing/start"
            )

        missing = self.REQUIRED_COLUMNS - set(df.columns)
        if missing:
            raise ValueError(f"Dataset missing required columns: {sorted(missing)}")

        before = len(df)
        df = df.dropna(subset=["cleaned_text", "processed_text"])
        df["cleaned_text"] = df["cleaned_text"].astype(str)
        df["processed_text"] = df["processed_text"].astype(str)
        df = df[(df["cleaned_text"].str.strip() != "") & (df["processed_text"].str.strip() != "")]

        removed = before - len(df)
        if removed > 0:
            logger.warning(
                "Dropped {}/{} rows due to empty/None cleaned_text/processed_text before training",
                removed,
                before,
            )

        # Preserve DB source order (repository listing order) and keep aligned index.
        df = df.reset_index(drop=True)

        if df.empty:
            raise ValueError("No valid text data found after dropping empty records.")

        return df

    def load_training_dataset(self) -> pd.DataFrame:
        """Load and normalize training-ready dataset from DB."""
        raw_df = database.load_processed_data_from_db()
        return self._normalize_training_dataframe(raw_df)

    def build_training_payload(self, df: pd.DataFrame, model_type: ModelType) -> TrainingPayload:
        """Build model-specific payload from normalized dataframe."""
        timestamps = df["year"].tolist() if "year" in df.columns else None

        if model_type == ModelType.BERTOPIC:
            documents = df["cleaned_text"].tolist()
            document_ids = [int(i) for i in df["id"].tolist()]

            if not documents:
                raise ValueError("No valid cleaned_text documents available for BERTopic training.")

            return TrainingPayload(
                documents=documents,
                timestamps=timestamps,
                document_ids=document_ids,
            )

        if model_type == ModelType.LDA:
            documents = df["processed_text"].tolist()

            if not documents:
                raise ValueError("No valid processed_text documents available for LDA training.")

            return TrainingPayload(
                documents=documents,
                timestamps=timestamps,
                document_ids=None,
            )

        raise ValueError(f"Unsupported model_type: {model_type}")

    def build_dta_payload(self, year_start: Optional[int] = None, year_end: Optional[int] = None) -> DTAPayload:
        """Load BERTopic input documents + years for DTA from DB dataset."""
        df = self.load_training_dataset()

        if "year" not in df.columns:
            raise ValueError("Data does not contain a 'year' column for DTA.")

        years_numeric = pd.to_numeric(df["year"], errors="coerce")
        df = df.assign(year=years_numeric).dropna(subset=["year"]).copy()
        df["year"] = df["year"].astype(int)

        if df.empty:
            raise ValueError("No documents with valid 'year' values found for DTA.")

        resolved_start = int(df["year"].min()) if year_start is None else int(year_start)
        resolved_end = int(df["year"].max()) if year_end is None else int(year_end)

        if resolved_start > resolved_end:
            raise ValueError(
                f"Invalid year range: start ({resolved_start}) must be <= end ({resolved_end})"
            )

        df = df[(df["year"] >= resolved_start) & (df["year"] <= resolved_end)].copy()

        if df.empty:
            raise ValueError(f"No documents found in year range {resolved_start}-{resolved_end}")

        documents = df["cleaned_text"].tolist()
        years = df["year"].tolist()

        if not documents:
            raise ValueError("No valid cleaned_text documents for DTA.")

        year_range = list(range(resolved_start, resolved_end + 1))
        return DTAPayload(documents=documents, years=years, year_range=year_range)

    def summarize_training_dataset(self) -> Dict[str, Any]:
        """Return readiness summary used by UI before training."""
        source_total = int(database.count_skripsi_rows())
        df = database.load_processed_data_from_db()

        dataset_total = int(len(df))
        if dataset_total == 0:
            dropped_from_source = max(source_total, 0)
            return {
                "status": "ok",
                "total": source_total,
                "source_total": source_total,
                "dataset_total": 0,
                "valid_bertopic": 0,
                "valid_lda": 0,
                "valid_both": 0,
                "dropped": dropped_from_source,
                "dropped_from_source": dropped_from_source,
                "dropped_within_dataset": 0,
                "year_min": None,
                "year_max": None,
            }

        cleaned = df["cleaned_text"].fillna("").astype(str).str.strip()
        processed = df["processed_text"].fillna("").astype(str).str.strip()

        valid_bertopic = int((cleaned != "").sum())
        valid_lda = int((processed != "").sum())
        valid_both = int(((cleaned != "") & (processed != "")).sum())
        dropped_within_dataset = int(dataset_total - valid_both)
        dropped_from_source = max(source_total - valid_both, 0)

        year_min = None
        year_max = None
        if "year" in df.columns:
            years = pd.to_numeric(df["year"], errors="coerce").dropna().astype(int)
            if not years.empty:
                year_min = int(years.min())
                year_max = int(years.max())

        return {
            "status": "ok",
            "total": source_total,
            "source_total": source_total,
            "dataset_total": dataset_total,
            "valid_bertopic": valid_bertopic,
            "valid_lda": valid_lda,
            "valid_both": valid_both,
            "dropped": dropped_from_source,
            "dropped_from_source": dropped_from_source,
            "dropped_within_dataset": dropped_within_dataset,
            "year_min": year_min,
            "year_max": year_max,
        }


pipeline_service = PipelineService()
