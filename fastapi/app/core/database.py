import json

import pandas as pd
from app.core.config import app_settings
from loguru import logger
from sqlalchemy import create_engine, text


def _table_exists(conn, table_name: str) -> bool:
    query = text(
        """
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = :table_name
        """
    )
    return int(conn.execute(query, {"table_name": table_name}).scalar() or 0) > 0


def _column_exists(conn, table_name: str, column_name: str) -> bool:
    query = text(
        """
        SELECT COUNT(*)
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = :table_name
          AND column_name = :column_name
        """
    )
    return int(
        conn.execute(
            query,
            {"table_name": table_name, "column_name": column_name},
        ).scalar()
        or 0
    ) > 0


def get_engine():
    """Create and return a SQLAlchemy engine for MySQL."""
    try:
        return create_engine(app_settings.database_url)
    except Exception as e:
        logger.error(f"Failed to create database engine: {e}")
        raise


def load_abstracts_from_db(limit=None):
    """Memuat kolom sumber dari tabel skripsi untuk preprocessing."""
    engine = get_engine()

    try:
        with engine.begin() as conn:
            has_repository_order = _column_exists(conn, "skripsi", "repository_order")

        if has_repository_order:
            query = (
                "SELECT id, title, abstract, conclusion, year FROM skripsi "
                "ORDER BY CASE WHEN repository_order IS NULL THEN 1 ELSE 0 END, "
                "repository_order ASC, id ASC"
            )
        else:
            query = "SELECT id, title, abstract, conclusion, year FROM skripsi ORDER BY id ASC"

        if limit:
            query += f" LIMIT {limit}"

        logger.info("Loading texts from MySQL database...")
        df = pd.read_sql(query, con=engine)
        logger.info(f"Loaded {len(df)} records from database")
        return df
    except Exception as e:
        logger.error(f"Failed to load data from database: {e}")
        raise


def count_skripsi_rows() -> int:
    """Menghitung total baris di tabel skripsi."""
    engine = get_engine()
    query = "SELECT COUNT(*) AS total FROM skripsi"

    try:
        df = pd.read_sql(query, con=engine)
        if df.empty:
            return 0
        return int(df.iloc[0]["total"] or 0)
    except Exception as e:
        logger.error(f"Failed to count skripsi rows: {e}")
        raise


def load_processed_data_from_db(limit=None):
    """Memuat teks yang sudah dipreprocess dari DB untuk pelatihan model."""
    engine = get_engine()

    try:
        logger.info("Loading processed texts from MySQL database...")
        with engine.begin() as conn:
            has_dataset_table = _table_exists(conn, "topic_model_datasets")
            has_repository_order = _column_exists(conn, "skripsi", "repository_order")

        if has_dataset_table:
            dataset_count_query = text("SELECT COUNT(*) FROM topic_model_datasets")
            with engine.begin() as conn:
                dataset_count = int(conn.execute(dataset_count_query).scalar() or 0)
        else:
            dataset_count = 0

        if has_dataset_table and dataset_count > 0:
            if has_repository_order:
                query = (
                    "SELECT d.skripsi_id AS id, d.cleaned_text, d.processed_text, d.year "
                    "FROM topic_model_datasets d "
                    "LEFT JOIN skripsi s ON s.id = d.skripsi_id "
                    "WHERE d.cleaned_text IS NOT NULL AND d.processed_text IS NOT NULL "
                    "ORDER BY CASE WHEN s.repository_order IS NULL THEN 1 ELSE 0 END, "
                    "s.repository_order ASC, d.skripsi_id ASC"
                )
                source = "topic_model_datasets(repository_order)"
            else:
                query = (
                    "SELECT skripsi_id AS id, cleaned_text, processed_text, year "
                    "FROM topic_model_datasets "
                    "WHERE cleaned_text IS NOT NULL AND processed_text IS NOT NULL "
                    "ORDER BY skripsi_id ASC"
                )
                source = "topic_model_datasets"
        else:
            if has_repository_order:
                query = (
                    "SELECT id, cleaned_text, processed_text, year "
                    "FROM skripsi "
                    "WHERE cleaned_text IS NOT NULL AND processed_text IS NOT NULL "
                    "ORDER BY CASE WHEN repository_order IS NULL THEN 1 ELSE 0 END, "
                    "repository_order ASC, id ASC"
                )
                source = "skripsi(repository_order)"
            else:
                query = (
                    "SELECT id, cleaned_text, processed_text, year "
                    "FROM skripsi "
                    "WHERE cleaned_text IS NOT NULL AND processed_text IS NOT NULL "
                    "ORDER BY id ASC"
                )
                source = "skripsi"

        if limit:
            query += f" LIMIT {limit}"

        df = pd.read_sql(query, con=engine)
        logger.info(f"Loaded {len(df)} records for training from {source}")
        return df
    except Exception as e:
        logger.error(f"Failed to load processed data from database: {e}")
        raise


def update_processed_texts_in_db(df):
    """Memperbarui kolom cleaned/processed text di tabel skripsi dari dataframe."""
    engine = get_engine()

    if "id" not in df.columns or "cleaned_text" not in df.columns or "processed_text" not in df.columns:
        logger.error("Missing columns in dataframe. Required: 'id', 'cleaned_text', 'processed_text'")
        return False

    try:
        with engine.begin() as conn:
            # Reset previous outputs first so dropped/duplicate rows are excluded from next training.
            conn.execute(text("UPDATE skripsi SET cleaned_text = NULL, processed_text = NULL"))

            query = text(
                "UPDATE skripsi "
                "SET cleaned_text = :cleaned, processed_text = :processed "
                "WHERE id = :id"
            )
            payload = [
                {
                    "id": int(row["id"]),
                    "cleaned": row["cleaned_text"],
                    "processed": row["processed_text"],
                }
                for _, row in df.iterrows()
            ]
            if payload:
                conn.execute(query, payload)
        logger.info(f"Successfully updated {len(df)} records in database")
        return True
    except Exception as e:
        logger.error(f"Failed to update database: {e}")
        raise


def replace_preprocessed_dataset_in_db(df):
    """Mengganti isi topic_model_datasets dengan snapshot preprocessing terbaru."""
    engine = get_engine()

    required_cols = {
        "id",
        "title",
        "abstract",
        "conclusion",
        "year",
        "cleaned_text",
        "processed_text",
    }
    missing = required_cols - set(df.columns)
    if missing:
        logger.error(
            "Missing columns for topic_model_datasets update: "
            f"{sorted(missing)}"
        )
        return False

    try:
        with engine.begin() as conn:
            if not _table_exists(conn, "topic_model_datasets"):
                logger.warning(
                    "topic_model_datasets table not found. "
                    "Run Laravel migration first, then rerun preprocessing."
                )
                return False

            conn.execute(text("DELETE FROM topic_model_datasets"))

            insert_query = text(
                """
                INSERT INTO topic_model_datasets
                    (skripsi_id, title, abstract, conclusion, year, cleaned_text, processed_text, created_at, updated_at)
                VALUES
                    (:skripsi_id, :title, :abstract, :conclusion, :year, :cleaned_text, :processed_text, NOW(), NOW())
                """
            )

            payload = []
            for _, row in df.iterrows():
                payload.append(
                    {
                        "skripsi_id": int(row["id"]),
                        "title": None if pd.isna(row["title"]) else str(row["title"]),
                        "abstract": None if pd.isna(row["abstract"]) else str(row["abstract"]),
                        "conclusion": None if pd.isna(row["conclusion"]) else str(row["conclusion"]),
                        "year": None if pd.isna(row["year"]) else int(row["year"]),
                        "cleaned_text": str(row["cleaned_text"]),
                        "processed_text": str(row["processed_text"]),
                    }
                )

            if payload:
                conn.execute(insert_query, payload)

        logger.info(f"Successfully replaced topic_model_datasets with {len(df)} rows")
        return True
    except Exception as e:
        logger.error(f"Failed to replace topic_model_datasets: {e}")
        raise


def _decode_json_object(value):
    """Mendekode payload JSON yang bisa berupa str/bytes/object dari MySQL."""
    if value is None:
        return None

    if isinstance(value, dict):
        return value

    if isinstance(value, (bytes, bytearray)):
        try:
            value = value.decode("utf-8")
        except Exception:
            return None

    if not isinstance(value, str):
        return None

    text = value.strip()
    if text == "":
        return None

    try:
        decoded = json.loads(text)
    except json.JSONDecodeError:
        return None

    return decoded if isinstance(decoded, dict) else None



def get_run_config(run_id: int):
    """Mengembalikan konfigurasi preprocessing default jika run ditemukan."""
    engine = get_engine()
    query = text("SELECT id FROM topic_model_runs WHERE id = :id")
    try:
        with engine.begin() as conn:
            result = conn.execute(query, {"id": run_id}).fetchone()
            if result:
                return {
                    "remove_stopwords": True,
                    "min_word_length": 3,
                    "language": "indonesian",
                }
            return None
    except Exception as e:
        logger.error(f"Failed to get run config for run_id {run_id}: {e}")
        raise
