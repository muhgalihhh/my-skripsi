
FROM python:3.11-slim AS builder

WORKDIR /build


RUN apt-get update && apt-get install -y --no-install-recommends \
    build-essential \
    gcc \
    g++ \
    && rm -rf /var/lib/apt/lists/*


RUN python -m venv /build/venv
ENV PATH="/build/venv/bin:$PATH"

COPY requirements.txt .

RUN pip install --no-cache-dir --upgrade pip && \
    pip install --no-cache-dir --default-timeout=300 --retries=10 --resume-retries=20 \
        torch==2.11.0+cpu --extra-index-url https://download.pytorch.org/whl/cpu && \
    pip install --no-cache-dir --default-timeout=300 --retries=10 --resume-retries=20 \
        -r requirements.txt


RUN python -c "import nltk; nltk.download('stopwords', download_dir='/build/nltk_data')"

FROM builder AS cleaner

WORKDIR /build/venv

RUN find lib/python3.11/site-packages/torch -type d -name "test" -exec rm -rf {} + 2>/dev/null || true && \
    rm -rf lib/python3.11/site-packages/torch/include 2>/dev/null || true && \
    rm -rf lib/python3.11/site-packages/torch/share/cmake 2>/dev/null || true && \
    find lib/python3.11/site-packages/torch -name "*.pyi" -delete 2>/dev/null || true && \
    find lib/python3.11/site-packages/torch -name "*.h" -delete 2>/dev/null || true


RUN find lib/python3.11/site-packages/transformers -name "*.pyi" -delete 2>/dev/null || true && \
    find lib/python3.11/site-packages/transformers -type d -name "benchmark*" -exec rm -rf {} + 2>/dev/null || true


RUN find lib/python3.11/site-packages -type d -name "__pycache__" -exec rm -rf {} + 2>/dev/null || true && \
    find lib/python3.11/site-packages -name "*.pyc" -delete 2>/dev/null || true && \
    find lib/python3.11/site-packages -name "*.pyo" -delete 2>/dev/null || true


RUN find lib/python3.11/site-packages -type d \( \
        -name "tests" -o \
        -name "test" \
    \) -exec rm -rf {} + 2>/dev/null || true

RUN rm -rf lib/python3.11/site-packages/matplotlib/mpl-data/sample_data 2>/dev/null || true && \
    find lib/python3.11/site-packages/matplotlib -name "*.pyc" -delete 2>/dev/null || true

RUN find lib/python3.11/site-packages/numpy -type d -name "*.pyi" -delete 2>/dev/null || true

FROM python:3.11-slim AS runtime

LABEL maintainer="Galih <galih@unsoed.ac.id>"
LABEL description="FastAPI ML Microservice - Topic Modeling Skripsi UNSOED"

WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    libgomp1 \
    && rm -rf /var/lib/apt/lists/*


COPY --from=cleaner /build/venv /app/venv


COPY --from=builder /build/nltk_data /usr/share/nltk_data


ENV PATH="/app/venv/bin:$PATH" \
    PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PYTHONPATH="/app" \
    NLTK_DATA="/usr/share/nltk_data" \
    TOKENIZERS_PARALLELISM=false \
    HF_HUB_DISABLE_PROGRESS_BARS=1 \
    HF_HUB_DISABLE_TELEMETRY=1 \
    HF_HOME="/app/data/.cache/huggingface" \
    TRANSFORMERS_CACHE="/app/data/.cache/huggingface/transformers" \
    SENTENCE_TRANSFORMERS_HOME="/app/data/.cache/sentence_transformers"


RUN groupadd -r appuser && useradd -r -g appuser -d /app -s /sbin/nologin appuser


COPY . .


RUN mkdir -p data/raw data/processed data/results data/embeddings \
    data/.cache/huggingface data/.cache/sentence_transformers \
    app/ml/trained_models logs \
    && chown -R appuser:appuser /app/data /app/logs /app/app/ml/trained_models \
    && chmod -R 755 /app/data /app/logs /app/app/ml/trained_models

USER appuser

EXPOSE 8000

HEALTHCHECK --interval=30s --timeout=15s --start-period=60s --retries=5 \
    CMD curl -f -m 5 http://localhost:8000/api/v1/health || exit 1

CMD ["python", "-m", "granian", \
    "--interface", "asgi", \
    "--host", "0.0.0.0", \
    "--port", "8000", \
    "--workers", "1", \
    "--threads", "4", \
    "--backpressure", "24", \
    "--log-level", "info", \
    "app.main:app"]
