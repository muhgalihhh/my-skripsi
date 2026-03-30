# ============================================
# Dockerfile - FastAPI ML Microservice
# ============================================
# Multi-stage build (3 stages):
#   1. builder   - compile native deps (build-essential)
#   2. cleaner   - strip bloat dari venv (torch test, CUDA stubs, cache)
#   3. runtime   - slim final image, hanya artifacts yang dibutuhkan
#
# Target size: ~1.5-2GB (dari 5.58GB)
# Strategi:
#   - Hapus torch/test, torch/bin debug tools, CUDA stubs (tidak dipakai CPU)
#   - Hapus __pycache__, *.pyc, *.pyi, test dirs di semua packages
#   - Hapus transformers/tokenizer unused backends
#   - Hapus *.dist-info docs yang tidak dipakai runtime
#   - libgomp1 untuk OpenMP (numpy, scikit-learn, hdbscan native threads)

# ==========================================
# Stage 1: Builder (install + compile deps)
# ==========================================
FROM python:3.11-slim AS builder

WORKDIR /build

# Install build tools (hanya di builder stage)
RUN apt-get update && apt-get install -y --no-install-recommends \
    build-essential \
    gcc \
    g++ \
    && rm -rf /var/lib/apt/lists/*

# Buat venv dan install semua deps
RUN python -m venv /build/venv
ENV PATH="/build/venv/bin:$PATH"

COPY requirements.txt .

# Install PyTorch CPU-only DULU (terpisah agar tidak conflict dengan PyPI index)
# --default-timeout=300: timeout 5 menit per request
# --retries=10 --resume-retries=20: retry banyak untuk handle koneksi lambat/putus
RUN pip install --no-cache-dir --upgrade pip && \
    pip install --no-cache-dir --default-timeout=300 --retries=10 --resume-retries=20 \
        torch==2.6.0+cpu --extra-index-url https://download.pytorch.org/whl/cpu && \
    pip install --no-cache-dir --default-timeout=300 --retries=10 --resume-retries=20 \
        -r requirements.txt

# Download NLTK stopwords (kecil, ~30KB)
RUN python -c "import nltk; nltk.download('stopwords', download_dir='/build/nltk_data')"

# ==========================================
# Stage 2: Cleaner (hapus bloat dari venv)
# ==========================================
FROM builder AS cleaner

WORKDIR /build/venv

# --- Hapus PyTorch bloat (terbesar ~800MB saving) ---
# torch/test: unit test PyTorch (tidak dipakai di produksi)
# torch/bin: debug tools (torch_shm_manager, dll)
# CUDA stubs: file .so untuk CUDA (kita pakai CPU-only)
# caffe2: framework lama yang bundled di torch (tidak dipakai)
RUN find lib/python3.11/site-packages/torch -type d -name "test" -exec rm -rf {} + 2>/dev/null || true && \
    rm -rf lib/python3.11/site-packages/torch/include 2>/dev/null || true && \
    rm -rf lib/python3.11/site-packages/torch/share/cmake 2>/dev/null || true && \
    find lib/python3.11/site-packages/torch -name "*.pyi" -delete 2>/dev/null || true && \
    find lib/python3.11/site-packages/torch -name "*.h" -delete 2>/dev/null || true

# --- Hapus transformers bloat (~200MB saving) ---
# Hapus tokenizer backends yang tidak dipakai (TensorFlow, Flax, dll)
# Hapus contoh/benchmark
RUN rm -rf lib/python3.11/site-packages/transformers/models/deprecated 2>/dev/null || true && \
    find lib/python3.11/site-packages/transformers -name "*.pyi" -delete 2>/dev/null || true && \
    find lib/python3.11/site-packages/transformers -type d -name "benchmark*" -exec rm -rf {} + 2>/dev/null || true

# --- Hapus __pycache__ dan *.pyc di SEMUA packages (~100-200MB saving) ---
# Docker image menyimpan .pyc files yang bisa di-regenerate saat runtime
RUN find lib/python3.11/site-packages -type d -name "__pycache__" -exec rm -rf {} + 2>/dev/null || true && \
    find lib/python3.11/site-packages -name "*.pyc" -delete 2>/dev/null || true && \
    find lib/python3.11/site-packages -name "*.pyo" -delete 2>/dev/null || true

# --- Hapus test directories di semua packages (~50MB saving) ---
RUN find lib/python3.11/site-packages -type d \( \
        -name "tests" -o \
        -name "test" \
    \) -exec rm -rf {} + 2>/dev/null || true

# --- Hapus dokumentasi dan metadata yang tidak diperlukan (~30MB saving) ---
RUN find lib/python3.11/site-packages -name "METADATA" -delete 2>/dev/null || true && \
    find lib/python3.11/site-packages -name "RECORD" -delete 2>/dev/null || true && \
    find lib/python3.11/site-packages -name "*.dist-info" -type d \
        -exec sh -c 'rm -f "$1/METADATA" "$1/RECORD" "$1/WHEEL" "$1/top_level.txt"' _ {} \; 2>/dev/null || true

# --- Hapus matplotlib data yang tidak dipakai (font caches, sample data) ---
RUN rm -rf lib/python3.11/site-packages/matplotlib/mpl-data/sample_data 2>/dev/null || true && \
    find lib/python3.11/site-packages/matplotlib -name "*.pyc" -delete 2>/dev/null || true

# --- Hapus numpy doc strings internal (tidak dipakai runtime) ---
RUN find lib/python3.11/site-packages/numpy -type d -name "*.pyi" -delete 2>/dev/null || true

# ==========================================
# Stage 3: Runtime (image final yang slim)
# ==========================================
FROM python:3.11-slim AS runtime

LABEL maintainer="Galih <galih@unsoed.ac.id>"
LABEL description="FastAPI ML Microservice - Topic Modeling Skripsi UNSOED"

WORKDIR /app

# Runtime system deps saja (no build tools)
# libgomp1: OpenMP untuk numpy/scikit-learn/hdbscan parallel processing
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    libgomp1 \
    && rm -rf /var/lib/apt/lists/*

# Copy venv yang sudah di-strip dari cleaner stage
COPY --from=cleaner /build/venv /app/venv

# Copy NLTK data
COPY --from=builder /build/nltk_data /usr/share/nltk_data

# Environment variables
ENV PATH="/app/venv/bin:$PATH" \
    PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PYTHONPATH="/app" \
    NLTK_DATA="/usr/share/nltk_data" \
    # Torch: matikan telemetry dan progress bar saat import
    TOKENIZERS_PARALLELISM=false \
    HF_HUB_DISABLE_PROGRESS_BARS=1 \
    HF_HUB_DISABLE_TELEMETRY=1

# Non-root user untuk keamanan
RUN groupadd -r appuser && useradd -r -g appuser -d /app -s /sbin/nologin appuser

# Copy kode aplikasi (exclude data dirs via .dockerignore)
COPY . .

# Buat directory yang diperlukan + set ownership
# PENTING: Hanya chown folder yang perlu ditulis (data, logs, ml models)
# JANGAN chown -R /app karena akan scan seluruh venv (PyTorch, Transformers, dll)
# dan memakan waktu 10+ menit. Library di venv hanya perlu read-only.
RUN mkdir -p data/raw data/processed data/results data/embeddings \
        app/ml/trained_models logs \
    && chown -R appuser:appuser /app/data /app/logs /app/app/ml/trained_models \
    && chmod -R 755 /app/data /app/logs /app/app/ml/trained_models

USER appuser

EXPOSE 8000

HEALTHCHECK --interval=30s --timeout=15s --start-period=60s --retries=5 \
    CMD curl -f -m 5 http://localhost:8000/api/v1/health || exit 1

# Granian ASGI server (Rust-based, lebih cepat dari uvicorn)
# python -m granian: bypass shebang path issue dari builder stage
# workers=1: karena ScrapingJobManager singleton harus shared di 1 process
# Scraping berjalan di background thread sehingga health endpoint tetap responsif
CMD ["python", "-m", "granian", \
    "--interface", "asgi", \
    "--host", "0.0.0.0", \
    "--port", "8000", \
    "--workers", "1", \
    "--threads", "4", \
    "--backpressure", "24", \
    "--log-level", "info", \
    "app.main:app"]
