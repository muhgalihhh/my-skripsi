"""
Preprocessing Routes
Endpoints for text preprocessing operations.
"""

from app.models.schemas import PreprocessingRequest, PreprocessingResponse
from app.services.preprocessing import TextPreprocessor
from app.services.training import TrainingService

from fastapi import APIRouter, HTTPException

router = APIRouter(prefix="/preprocessing", tags=["Preprocessing"])


@router.post("/run", response_model=PreprocessingResponse)
async def run_preprocessing(request: PreprocessingRequest):
    """
    Run text preprocessing on the raw data.

    Loads raw_data.csv, applies cleaning + tokenization + stopword removal + stemming,
    and saves the result as processed_data.csv.
    """
    try:
        service = TrainingService()

        # Override preprocessor settings
        service.preprocessor = TextPreprocessor(
            remove_stopwords=request.remove_stopwords,
            use_stemming=request.use_stemming,
            min_word_length=request.min_word_length,
            language=request.language,
        )

        # Load raw data
        df = service.load_data()

        total_tokens_before = df["abstract"].fillna("").str.split().str.len().sum()

        # Preprocess (dual pipeline: cleaned_text + processed_text)
        processed_df = service.preprocess_data(df)

        total_tokens_after_cleaned = (
            processed_df["cleaned_text"].fillna("").str.split().str.len().sum()
        )
        total_tokens_after_processed = (
            processed_df["processed_text"].fillna("").str.split().str.len().sum()
        )

        return PreprocessingResponse(
            status="success",
            total_documents=len(processed_df),
            total_tokens_before=int(total_tokens_before),
            total_tokens_after_cleaned=int(total_tokens_after_cleaned),
            total_tokens_after_processed=int(total_tokens_after_processed),
            message=(
                f"Preprocessing complete. {len(processed_df)} documents processed. "
                f"cleaned_text (BERTopic/IndoSBERT): {int(total_tokens_after_cleaned)} tokens, "
                f"processed_text (LDA): {int(total_tokens_after_processed)} tokens."
            ),
        )

    except FileNotFoundError as e:
        raise HTTPException(status_code=404, detail=str(e))
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Preprocessing failed: {str(e)}")
