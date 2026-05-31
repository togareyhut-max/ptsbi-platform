"""Database layer — PostgreSQL untuk server Ubuntu production."""

from __future__ import annotations

import os
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent
POSTGRES_SCHEMA_PATH = BASE_DIR / "schema.sql"
DATABASE_URL = os.environ.get("DATABASE_URL", "").strip()


def _as_dict(row):
    if row is None:
        return None
    if isinstance(row, dict):
        return row
    return dict(row)


class DbConnection:
    def __init__(self, conn):
        self._conn = conn
        self.backend = "postgres"

    def execute(self, sql: str, params=None):
        params = params or ()
        cur = self._conn.execute(sql.replace("?", "%s"), params)
        return cur

    def fetchone(self, sql: str, params=None):
        return _as_dict(self.execute(sql, params).fetchone())

    def fetchall(self, sql: str, params=None):
        return [_as_dict(r) for r in self.execute(sql, params).fetchall()]

    def commit(self):
        self._conn.commit()

    def rollback(self):
        self._conn.rollback()


def connect() -> DbConnection:
    if not DATABASE_URL:
        raise RuntimeError("DATABASE_URL wajib diset (lihat .env.example)")
    import psycopg
    from psycopg.rows import dict_row

    conn = psycopg.connect(DATABASE_URL, row_factory=dict_row)
    return DbConnection(conn)


def insert_returning_id(db: DbConnection, sql: str, params) -> int:
    if "RETURNING" not in sql.upper():
        sql = sql.rstrip().rstrip(";") + " RETURNING id"
    return int(db.fetchone(sql, params)["id"])


def sql_approved(db: DbConnection, alias: str = "") -> str:
    prefix = f"{alias}." if alias else ""
    return f"{prefix}approved IS TRUE"


def sql_not_approved(db: DbConnection, alias: str = "") -> str:
    prefix = f"{alias}." if alias else ""
    return f"{prefix}approved IS NOT TRUE"


def init_schema(db: DbConnection):
    sql = POSTGRES_SCHEMA_PATH.read_text(encoding="utf-8")
    statements = [s.strip() for s in sql.split(";") if s.strip()]
    with db._conn.cursor() as cur:
        for stmt in statements:
            cur.execute(stmt)
    db.commit()


def migrate_schema(db: DbConnection):
    alters = [
        "ALTER TABLE people ADD COLUMN IF NOT EXISTS name_normalized TEXT",
        "ALTER TABLE people ADD COLUMN IF NOT EXISTS father_name_normalized TEXT",
        "ALTER TABLE people ADD COLUMN IF NOT EXISTS birth_date DATE",
        "ALTER TABLE people ADD COLUMN IF NOT EXISTS panggoaran TEXT",
        "ALTER TABLE people ADD COLUMN IF NOT EXISTS panggoaran_type TEXT",
        "ALTER TABLE people ADD COLUMN IF NOT EXISTS opung_source TEXT",
        "ALTER TABLE people ADD COLUMN IF NOT EXISTS last_synced_at TIMESTAMPTZ",
    ]
    for stmt in alters:
        try:
            db.execute(stmt)
        except Exception:
            pass
    try:
        db.execute(
            """
            CREATE TABLE IF NOT EXISTS form_field_configs (
              id SERIAL PRIMARY KEY,
              section TEXT NOT NULL,
              field_key TEXT NOT NULL UNIQUE,
              label TEXT NOT NULL,
              field_type TEXT NOT NULL DEFAULT 'text',
              placeholder TEXT,
              is_required BOOLEAN NOT NULL DEFAULT TRUE,
              is_visible BOOLEAN NOT NULL DEFAULT TRUE,
              display_order INTEGER NOT NULL DEFAULT 0,
              options_json TEXT,
              help_text TEXT,
              default_value TEXT,
              updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
            """
        )
    except Exception:
        pass
    db.commit()
