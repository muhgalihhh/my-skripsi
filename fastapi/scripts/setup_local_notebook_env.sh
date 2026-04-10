#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

if [[ -n "${PYTHON_BIN:-}" ]]; then
  SELECTED_PYTHON_BIN="$PYTHON_BIN"
elif command -v python3.11 >/dev/null 2>&1; then
  SELECTED_PYTHON_BIN="python3.11"
elif command -v python3 >/dev/null 2>&1; then
  SELECTED_PYTHON_BIN="python3"
else
  echo "ERROR: Python tidak ditemukan (python3.11/python3)." >&2
  exit 1
fi

PYTHON_BIN="$SELECTED_PYTHON_BIN"
VENV_DIR="${VENV_DIR:-$ROOT_DIR/.venv-fastapi-notebook}"

if ! command -v "$PYTHON_BIN" >/dev/null 2>&1; then
  echo "ERROR: $PYTHON_BIN tidak ditemukan. Set env PYTHON_BIN ke path python yang valid." >&2
  exit 1
fi

PY_VERSION="$("$PYTHON_BIN" -c 'import sys; print(f"{sys.version_info.major}.{sys.version_info.minor}")')"
if [[ "$PY_VERSION" != "3.11" ]]; then
  echo "WARNING: Python aktif = $PY_VERSION. FastAPI container pakai 3.11." >&2
  echo "WARNING: Tetap lanjut setup lokal, tapi hasil bisa sedikit berbeda." >&2
fi

PY_VERSION_NODOT="${PY_VERSION/./}"
KERNEL_NAME="${KERNEL_NAME:-fastapi-local-py${PY_VERSION_NODOT}}"
KERNEL_DISPLAY_NAME="${KERNEL_DISPLAY_NAME:-FastAPI Local (Py${PY_VERSION})}"

if [[ -x "$VENV_DIR/bin/python" ]]; then
  EXISTING_VENV_VERSION="$("$VENV_DIR/bin/python" -c 'import sys; print(f"{sys.version_info.major}.{sys.version_info.minor}")')"
  if [[ "$EXISTING_VENV_VERSION" != "$PY_VERSION" ]]; then
    echo "[info] Venv lama terdeteksi dengan Python $EXISTING_VENV_VERSION, recreate ke Python $PY_VERSION"
    rm -rf "$VENV_DIR"
  fi
fi

echo "Python terpilih: $PYTHON_BIN ($PY_VERSION)"

echo "[1/4] Membuat virtual environment di: $VENV_DIR"
"$PYTHON_BIN" -m venv "$VENV_DIR"

echo "[2/4] Upgrade pip, setuptools, wheel"
"$VENV_DIR/bin/python" -m pip install --upgrade pip setuptools wheel

echo "[3/4] Install paket notebook (sinkron dengan FastAPI container)"
"$VENV_DIR/bin/python" -m pip install -r "$ROOT_DIR/requirements.notebook-local.txt"

echo "[4/4] Register kernel Jupyter untuk VS Code"
"$VENV_DIR/bin/python" -m ipykernel install --user --name "$KERNEL_NAME" --display-name "$KERNEL_DISPLAY_NAME"

cat <<EOF

Selesai.
Interpreter lokal: $VENV_DIR/bin/python
Kernel notebook: $KERNEL_DISPLAY_NAME (name: $KERNEL_NAME)

Di VS Code notebook:
1. Klik Select Kernel
2. Pilih "$KERNEL_DISPLAY_NAME"
EOF
