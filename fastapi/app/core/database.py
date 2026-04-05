import pandas as pd
from sqlalchemy import create_engine, text
from app.core.config import app_settings
from loguru import logger


def _table_exists(conn, table_name: str) -> bool:
    query = text(
        """
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = :table_name
        """
    )
    return int(conn.execute(query, {"table_name": table_name}).scalar() or 0) > 0


def get_engine():
    """Create and return a SQLAlchemy engine for MySQL."""
    try:
        engine = create_engine(app_settings.database_url)
        return engine
    except Exception as e:
        logger.error(f"Failed to create database engine: {e}")
        raise e

def load_abstracts_from_db(limit=None):
    """Load source columns needed by notebook-aligned preprocessing."""
    engine = get_engine()
    query = (
        "SELECT id, title, abstract, conclusion, year "
        "FROM skripsi "
        "WHERE abstract IS NOT NULL AND LENGTH(abstract) > 30"
    )
    if limit:
        query += f" LIMIT {limit}"
        
    try:
        logger.info("Loading texts from MySQL database...")
        df = pd.read_sql(query, con=engine)
        logger.info(f"Loaded {len(df)} records from database")
        return df
    except Exception as e:
        logger.error(f"Failed to load data from database: {e}")
        raise e

def load_processed_data_from_db(limit=None):
    """Load fully preprocessed texts for training.

    Preferred source:
      - topic_model_datasets (deduped + filtered snapshot from preprocessing)
    Fallback:
      - skripsi.cleaned_text / processed_text (legacy behavior)
    """
    engine = get_engine()

    try:
        logger.info("Loading processed texts from MySQL database...")
        with engine.begin() as conn:
            has_dataset_table = _table_exists(conn, "topic_model_datasets")

        if has_dataset_table:
            dataset_count_query = text("SELECT COUNT(*) FROM topic_model_datasets")
            with engine.begin() as conn:
                dataset_count = int(conn.execute(dataset_count_query).scalar() or 0)
        else:
            dataset_count = 0

        if has_dataset_table and dataset_count > 0:
            query = (
                "SELECT skripsi_id AS id, cleaned_text, processed_text, year "
                "FROM topic_model_datasets "
                "WHERE cleaned_text IS NOT NULL AND processed_text IS NOT NULL"
            )
            source = "topic_model_datasets"
        else:
            query = (
                "SELECT id, cleaned_text, processed_text, year "
                "FROM skripsi "
                "WHERE cleaned_text IS NOT NULL AND processed_text IS NOT NULL"
            )
            source = "skripsi"

        if limit:
            query += f" LIMIT {limit}"

        df = pd.read_sql(query, con=engine)
        logger.info(f"Loaded {len(df)} records for training from {source}")
        return df
    except Exception as e:
        logger.error(f"Failed to load processed data from database: {e}")
        raise e

def update_processed_texts_in_db(df):
    """
    Update the Skripsi table with the cleaned text and processed text.
    We iterate over the dataframe and run an update query for each record. 
    Alternatively, updating could be batched, but an iteration is fine for this scale if transactions are used.
    """
    engine = get_engine()
    
    # Check if necessary columns exist
    if 'id' not in df.columns or 'cleaned_text' not in df.columns or 'processed_text' not in df.columns:
        logger.error("Missing columns in dataframe. Required: 'id', 'cleaned_text', 'processed_text'")
        return False
        
    try:
        with engine.begin() as conn:  # This uses a transaction
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
        raise e


def replace_preprocessed_dataset_in_db(df):
    """Replace topic_model_datasets content with latest preprocessing snapshot."""
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
        raise e

def get_run_config(run_id: int):
    """Fetch preprocessing configurations from topic_model_runs by run_id."""
    engine = get_engine()
    query = text("SELECT remove_stopwords, min_word_length, language FROM topic_model_runs WHERE id = :id")
    try:
        with engine.begin() as conn:
            result = conn.execute(query, {"id": run_id}).fetchone()
            if result:
                # `result` is a tuple-like object, mapping depends on the sqlalchemy version, 
                # but typically _mapping or index access works.
                return {
                    "remove_stopwords": True if result.remove_stopwords is None else bool(result.remove_stopwords),
                    "min_word_length": int(result.min_word_length or 3),
                    "language": str(result.language or "indonesian")
                }
            return None
    except Exception as e:
        logger.error(f"Failed to get run config for run_id {run_id}: {e}")
        raise e
