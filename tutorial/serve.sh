#!/usr/bin/env bash
# Start the MkDocs development server
# Usage: ./serve.sh

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR"
exec "$SCRIPT_DIR/venv/bin/python" -m mkdocs serve -a 0.0.0.0:8001