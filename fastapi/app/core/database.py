import pandas as pd
from sqlalchemy import create_engine, text
from app.core.config import app_settings
from loguru import logger

def get_engine():
    """Create and return a SQLAlchemy engine for MySQL."""
    try:
        engine = create_engine(app_settings.database_url)
        return engine
    except Exception as e:
        logger.error(f"Failed to create database engine: {e}")
        raise e

def load_abstracts_from_db(limit=None):
    """Load valid academic abstracts directly from the MySQL database using pandas."""
    engine = get_engine()
    query = "SELECT id, abstract, year FROM skripsi WHERE abstract IS NOT NULL AND LENGTH(abstract) > 30"
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
    """Load fully preprocessed texts from the MySQL database."""
    engine = get_engine()
    query = "SELECT id, cleaned_text, processed_text, year FROM skripsi WHERE cleaned_text IS NOT NULL AND processed_text IS NOT NULL"
    if limit:
        query += f" LIMIT {limit}"
        
    try:
        logger.info("Loading processed texts from MySQL database...")
        df = pd.read_sql(query, con=engine)
        logger.info(f"Loaded {len(df)} records for training")
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
        with engine.begin() as conn: # This uses a transaction
            for index, row in df.iterrows():
                # Avoid SQL Injection by using parameterized update
                query = text("UPDATE skripsi SET cleaned_text = :cleaned, processed_text = :processed WHERE id = :id")
                conn.execute(query, {"cleaned": row['cleaned_text'], "processed": row['processed_text'], "id": row['id']})
        logger.info(f"Successfully updated {len(df)} records in database")
        return True
    except Exception as e:
        logger.error(f"Failed to update database: {e}")
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
                    "remove_stopwords": bool(result.remove_stopwords),
                    "min_word_length": int(result.min_word_length),
                    "language": str(result.language)
                }
            return None
    except Exception as e:
        logger.error(f"Failed to get run config for run_id {run_id}: {e}")
        raise e
