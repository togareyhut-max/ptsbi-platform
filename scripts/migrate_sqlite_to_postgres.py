#!/usr/bin/env python3
"""
Migrasi data dari tarombo.db (SQLite) ke PostgreSQL.

Usage:
  set DATABASE_URL=postgresql://tarombo:tarombo@localhost:5432/tarombo_ptsbi
  python scripts/migrate_sqlite_to_postgres.py
  python scripts/migrate_sqlite_to_postgres.py --sqlite path/to/tarombo.db --force
"""

from __future__ import annotations

import argparse
import os
import sqlite3
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT))

from dotenv import load_dotenv

load_dotenv(ROOT / ".env")

from services.matching import normalize_name, person_identity_fields


def main():
    parser = argparse.ArgumentParser(description="Migrate SQLite tarombo.db to PostgreSQL")
    parser.add_argument("--sqlite", default=str(ROOT / "tarombo.db"), help="Path to SQLite file")
    parser.add_argument("--force", action="store_true", help="Truncate PostgreSQL tables before import")
    args = parser.parse_args()

    sqlite_path = Path(args.sqlite)
    if not sqlite_path.is_file():
        print(f"File SQLite tidak ditemukan: {sqlite_path}")
        sys.exit(1)

    database_url = os.environ.get("DATABASE_URL", "").strip()
    if not database_url:
        print("Set DATABASE_URL ke PostgreSQL terlebih dahulu.")
        sys.exit(1)

    import psycopg
    from psycopg.rows import dict_row

    src = sqlite3.connect(sqlite_path)
    src.row_factory = sqlite3.Row

    dst = psycopg.connect(database_url, row_factory=dict_row)

    def src_all(table):
        return src.execute(f"SELECT * FROM {table}").fetchall()

    with dst.cursor() as cur:
        if args.force:
            print("Menghapus data lama di PostgreSQL...")
            for table in (
                "audit_logs",
                "submitted_children",
                "relationships",
                "people",
                "form_field_configs",
                "users",
            ):
                cur.execute(f"TRUNCATE TABLE {table} RESTART IDENTITY CASCADE")

        print("Migrasi users...")
        user_id_map = {}
        for row in src_all("users"):
            cur.execute(
                """
                INSERT INTO users (full_name, email, password_hash, role, member_status, created_at)
                VALUES (%s, %s, %s, %s, %s, %s)
                RETURNING id
                """,
                (
                    row["full_name"],
                    row["email"],
                    row["password_hash"],
                    row["role"],
                    row["member_status"],
                    row["created_at"],
                ),
            )
            user_id_map[row["id"]] = cur.fetchone()["id"]

        print("Migrasi people...")
        person_id_map = {}
        for row in src_all("people"):
            approved = bool(row["approved"]) if row["approved"] is not None else False
            ident = person_identity_fields(
                row["full_name"],
                row["marga"],
                row["father_name"],
                row["birth_year"],
            )
            cur.execute(
                """
                INSERT INTO people (
                  full_name, name_normalized, gender, marga, birth_year,
                  father_name, father_name_normalized, mother_name, tarombo_status,
                  reference_female_line_name, spouse_name, spouse_marga,
                  wife_name, wife_marga, submitted_by, approved, created_at
                ) VALUES (
                  %s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s
                ) RETURNING id
                """,
                (
                    row["full_name"],
                    ident["name_normalized"],
                    row["gender"],
                    row["marga"],
                    row["birth_year"],
                    row["father_name"],
                    ident["father_name_normalized"],
                    row["mother_name"],
                    row["tarombo_status"],
                    row["reference_female_line_name"],
                    row["spouse_name"],
                    row["spouse_marga"],
                    row.get("wife_name"),
                    row.get("wife_marga"),
                    user_id_map.get(row["submitted_by"]),
                    approved,
                    row["created_at"],
                ),
            )
            person_id_map[row["id"]] = cur.fetchone()["id"]

        print("Migrasi relationships...")
        for row in src_all("relationships"):
            cur.execute(
                """
                INSERT INTO relationships (source_person_id, target_person_id, relation_type, created_at)
                VALUES (%s, %s, %s, %s)
                ON CONFLICT (source_person_id, target_person_id, relation_type) DO NOTHING
                """,
                (
                    person_id_map.get(row["source_person_id"]),
                    person_id_map.get(row["target_person_id"]),
                    row["relation_type"],
                    row["created_at"],
                ),
            )

        if table_exists(src, "submitted_children"):
            print("Migrasi submitted_children...")
            for row in src_all("submitted_children"):
                parent_id = person_id_map.get(row["parent_person_id"])
                if not parent_id:
                    continue
                cur.execute(
                    """
                    INSERT INTO submitted_children (parent_person_id, child_name, child_gender, child_birth_year, created_at)
                    VALUES (%s, %s, %s, %s, %s)
                    """,
                    (
                        parent_id,
                        row["child_name"],
                        row["child_gender"],
                        row["child_birth_year"],
                        row["created_at"],
                    ),
                )

        for seq_table in ("users", "people", "relationships", "submitted_children", "audit_logs", "form_field_configs"):
            cur.execute(
                f"""
                SELECT setval(
                  pg_get_serial_sequence('{seq_table}', 'id'),
                  COALESCE((SELECT MAX(id) FROM {seq_table}), 1)
                )
                """
            )

    dst.commit()
    src.close()
    dst.close()
    print("Migrasi selesai.")
    print(f"  Users: {len(user_id_map)}")
    print(f"  People: {len(person_id_map)}")
    print("Jalankan resync dari Admin panel atau: flask resync (setelah app start)")


def table_exists(conn, name):
    row = conn.execute(
        "SELECT name FROM sqlite_master WHERE type='table' AND name=?",
        (name,),
    ).fetchone()
    return row is not None


if __name__ == "__main__":
    main()
