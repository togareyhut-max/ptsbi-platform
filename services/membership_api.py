"""Membership API v1 — sinkron data WordPress ↔ database Tarombo."""

from __future__ import annotations

import json
import os
from datetime import datetime, timezone
from typing import Any

from flask import Blueprint, jsonify, request
from werkzeug.security import check_password_hash, generate_password_hash

from db import connect, migrate_schema

bp = Blueprint("membership_api", __name__, url_prefix="/v1")


def _integration_key() -> str:
    return os.environ.get("MEMBERSHIP_API_INTEGRATION_KEY", "").strip()


def _require_integration_key():
    expected = _integration_key()
    if not expected:
        return jsonify({"error": "Membership API tidak dikonfigurasi di server."}), 503
    got = (request.headers.get("X-Integration-Key") or "").strip()
    if got != expected:
        return jsonify({"error": "Integration key tidak valid."}), 401
    return None


def _now_iso() -> str:
    return datetime.now(timezone.utc).isoformat()


def _upsert_app_setting(db, key: str, value: str) -> None:
    if db.backend == "postgres":
        db.execute(
            """
            INSERT INTO app_settings (key, value, updated_at)
            VALUES (?, ?, NOW())
            ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value, updated_at = NOW()
            """,
            (key, value),
        )
    else:
        db.execute(
            """
            INSERT OR REPLACE INTO app_settings (key, value, updated_at)
            VALUES (?, ?, CURRENT_TIMESTAMP)
            """,
            (key, value),
        )


def upsert_member_profile_record(db, *, email: str, profile: dict[str, Any], wp_user_id: int | None = None) -> None:
    """Simpan profil anggota ke tabel member_profiles (dipakai API & route Tarombo)."""
    _ensure_member_profiles_table(db)
    payload = json.dumps(profile, ensure_ascii=False)
    email = email.strip().lower()
    existing = db.fetchone("SELECT id FROM member_profiles WHERE LOWER(email) = ?", (email,))
    if existing:
        db.execute(
            "UPDATE member_profiles SET profile_json = ?, wp_user_id = COALESCE(?, wp_user_id), updated_at = ? WHERE id = ?",
            (payload, wp_user_id, _now_iso(), existing["id"]),
        )
    else:
        db.execute(
            "INSERT INTO member_profiles (wp_user_id, email, profile_json, updated_at) VALUES (?, ?, ?, ?)",
            (wp_user_id, email, payload, _now_iso()),
        )


def _ensure_member_profiles_table(db) -> None:
    if db.backend == "postgres":
        db.execute(
            """
            CREATE TABLE IF NOT EXISTS member_profiles (
              id SERIAL PRIMARY KEY,
              wp_user_id INTEGER,
              email TEXT NOT NULL UNIQUE,
              profile_json TEXT NOT NULL DEFAULT '{}',
              updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
            """
        )
        db.execute(
            "CREATE INDEX IF NOT EXISTS idx_member_profiles_wp_user ON member_profiles (wp_user_id)"
        )
    else:
        db.execute(
            """
            CREATE TABLE IF NOT EXISTS member_profiles (
              id INTEGER PRIMARY KEY AUTOINCREMENT,
              wp_user_id INTEGER,
              email TEXT NOT NULL UNIQUE,
              profile_json TEXT NOT NULL DEFAULT '{}',
              updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
            """
        )


@bp.before_request
def _migrate_once():
    if getattr(bp, "_schema_ready", False):
        return None
    db = connect()
    migrate_schema(db)
    _ensure_member_profiles_table(db)
    db.commit()
    bp._schema_ready = True  # type: ignore[attr-defined]
    return None


@bp.route("/auth/login", methods=["POST"])
def auth_login():
    denied = _require_integration_key()
    if denied:
        return denied

    data = request.get_json(silent=True) or {}
    email = (data.get("email") or "").strip().lower()
    password = data.get("password") or ""
    if not email or not password:
        return jsonify({"error": "Email dan password wajib."}), 400

    db = connect()
    user = db.fetchone(
        "SELECT id, email, password_hash, role, member_status, full_name FROM users WHERE LOWER(email) = ?",
        (email,),
    )
    if not user or not check_password_hash(user["password_hash"], password):
        return jsonify({"error": "Email atau password salah."}), 401
    if user.get("member_status") == "rejected":
        return jsonify({"error": "Akun ditolak."}), 403

    token = f"tarombo-{user['id']}-{int(datetime.now(timezone.utc).timestamp())}"
    return jsonify(
        {
            "access_token": token,
            "user": {
                "id": user["id"],
                "email": user["email"],
                "role": user["role"],
                "full_name": user.get("full_name") or "",
            },
            "role": user["role"],
        }
    )


@bp.route("/registrations", methods=["POST"])
def registrations():
    denied = _require_integration_key()
    if denied:
        return denied

    data = request.get_json(silent=True) or {}
    email = (data.get("email") or "").strip().lower()
    full_name = (data.get("full_name") or "").strip()
    password = data.get("password") or ""
    if not email or not full_name or not password:
        return jsonify({"error": "Data pendaftaran tidak lengkap."}), 400

    db = connect()
    existing = db.fetchone("SELECT id FROM users WHERE LOWER(email) = ?", (email,))
    if existing:
        return jsonify({"ok": True, "user_id": existing["id"], "existing": True})

    pwd_hash = generate_password_hash(password)
    if db.backend == "postgres":
        row = db.fetchone(
            """
            INSERT INTO users (full_name, email, password_hash, role, member_status)
            VALUES (?, ?, ?, 'member', 'pending')
            RETURNING id
            """,
            (full_name, email, pwd_hash),
        )
        user_id = row["id"] if row else None
    else:
        db.execute(
            """
            INSERT INTO users (full_name, email, password_hash, role, member_status)
            VALUES (?, ?, ?, 'member', 'pending')
            """,
            (full_name, email, pwd_hash),
        )
        user_id = db.lastrowid

    db.commit()
    return jsonify({"ok": True, "user_id": user_id})


@bp.route("/members/profile", methods=["POST", "PUT"])
def members_profile():
    denied = _require_integration_key()
    if denied:
        return denied

    data = request.get_json(silent=True) or {}
    email = (data.get("email") or "").strip().lower()
    wp_user_id = data.get("wp_user_id")
    profile = data.get("profile")
    if not email or not isinstance(profile, dict):
        return jsonify({"error": "Email dan profile wajib."}), 400

    db = connect()
    wp_id = int(wp_user_id) if wp_user_id is not None and str(wp_user_id).isdigit() else None
    upsert_member_profile_record(db, email=email, profile=profile, wp_user_id=wp_id)
    db.commit()
    return jsonify({"ok": True})


@bp.route("/settings/options", methods=["POST", "PUT"])
def settings_options():
    denied = _require_integration_key()
    if denied:
        return denied

    data = request.get_json(silent=True) or {}
    patch = data.get("options")
    if not isinstance(patch, dict):
        return jsonify({"error": "options wajib berupa objek."}), 400

    db = connect()
    for key, value in patch.items():
        safe_key = str(key).strip()
        if not safe_key.startswith("ptprm_"):
            continue
        if isinstance(value, (dict, list)):
            stored = json.dumps(value, ensure_ascii=False)
        else:
            stored = str(value)
        _upsert_app_setting(db, safe_key, stored)

    db.commit()
    return jsonify({"ok": True, "keys": list(patch.keys())})


@bp.route("/settings/pdf-items", methods=["POST", "PUT"])
def settings_pdf_items():
    denied = _require_integration_key()
    if denied:
        return denied

    data = request.get_json(silent=True) or {}
    items = data.get("pdf_items")
    if not isinstance(items, list):
        return jsonify({"error": "pdf_items wajib berupa array."}), 400

    db = connect()
    _upsert_app_setting(db, "ptprm_pdf_items", json.dumps(items, ensure_ascii=False))
    db.commit()
    return jsonify({"ok": True, "count": len(items)})
