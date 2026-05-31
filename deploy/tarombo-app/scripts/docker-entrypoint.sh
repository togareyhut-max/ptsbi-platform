#!/bin/sh
set -e

echo "Menunggu PostgreSQL..."
until python -c "
import os, sys
import psycopg
psycopg.connect(os.environ['DATABASE_URL']).close()
" 2>/dev/null; do
  sleep 2
done

echo "Inisialisasi schema..."
python -c "
from app import app, init_db, seed_data
with app.app_context():
    init_db()
    seed_data()
"

echo "Menjalankan aplikasi..."
exec "$@"
