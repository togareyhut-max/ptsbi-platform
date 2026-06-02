"""REST API v1 — bridge WordPress Premium Organization ↔ Tarombo (PostgreSQL master)."""

from __future__ import annotations

import json
import os
import secrets
from datetime import datetime, timezone
from functools import wraps
from typing import Any, Callable

from flask import Blueprint, jsonify, request
from itsdangerous import BadSignature, SignatureExpired, URLSafeTimedSerializer
from werkzeug.security import check_password_hash, generate_password_hash

bp = Blueprint("wp_integration_api", __name__, url_prefix="/v1")

TOKEN_MAX_AGE = 86400 * 7


def _integration_key() -> str:
    return (
        os.environ.get("WP_INTEGRATION_KEY", "").strip()
        or "ptsbi-wp-bridge-2026"
    )


def _token_serializer(secret_key: str) -> URLSafeTimedSerializer:
    return URLSafeTimedSerializer(secret_key, salt="ptprm-wp-api-v1")


def _require_integration_key(view: Callable):
    @wraps(view)
    def wrapped(*args, **kwargs):
        sent = (request.headers.get("X-Integration-Key") or "").strip()
        expected = _integration_key()
        if not sent or not secrets.compare_digest(sent, expected):
            return jsonify({"error": "invalid_integration_key"}), 401
        return view(*args, **kwargs)

    return wrapped


def _profile_row_to_dict(row: dict | None) -> dict[str, Any]:
    if not row:
        return {}
    return {
        "family_no": row.get("family_no") or "",
        "kepala_keluarga": row.get("kepala_keluarga") or "",
        "nama_istri": row.get("nama_istri") or "",
        "tarombo": row.get("tarombo") or "",
        "oppu": row.get("oppu") or "",
        "nomor_sundut": row.get("nomor_sundut") or "",
        "hula_boru": row.get("hula_boru") or "",
        "phone": row.get("phone") or "",
        "country_name": row.get("country_name") or "Indonesia",
        "country_code": row.get("country_code") or "ID",
        "province": row.get("province") or "",
        "city": row.get("city") or "",
        "district": row.get("district") or "",
        "subdistrict": row.get("subdistrict") or "",
        "postal_code": row.get("postal_code") or "",
        "state_city": row.get("state_city") or "",
        "address_detail": row.get("address_detail") or "",
        "street_name": row.get("street_name") or "",
        "house_number": row.get("house_number") or "",
        "rt": row.get("rt") or "",
        "rw": row.get("rw") or "",
        "is_overseas": bool(row.get("is_overseas")),
        "profile_complete": bool(row.get("profile_complete")),
    }


def _compute_profile_complete(data: dict[str, Any]) -> bool:
    kepala = (data.get("kepala_keluarga") or "").strip()
    phone = (data.get("phone") or "").strip()
    if not kepala or not phone:
        return False
    if data.get("is_overseas"):
        return bool((data.get("address_detail") or "").strip())
    return bool(
        (data.get("province") or "").strip()
        and (data.get("city") or "").strip()
        and (data.get("district") or "").strip()
    )


def _user_payload(row: dict, profile: dict | None = None) -> dict[str, Any]:
    prof = _profile_row_to_dict(profile) if profile else {}
    return {
        "id": int(row["id"]),
        "email": row.get("email") or "",
        "full_name": row.get("full_name") or "",
        "role": row.get("role") or "member",
        "member_status": row.get("member_status") or "pending",
        "wp_user_id": int(row["wp_user_id"]) if row.get("wp_user_id") else None,
        "phone": row.get("phone") or prof.get("phone") or "",
        "profile": prof,
        "profile_complete": prof.get("profile_complete", False),
    }


def _find_user_by_email(db, email: str):
    return db.fetchone("SELECT * FROM users WHERE LOWER(email) = LOWER(?)", (email.strip(),))


def _find_user_by_wp_id(db, wp_user_id: int):
    return db.fetchone("SELECT * FROM users WHERE wp_user_id = ?", (int(wp_user_id),))


def _get_profile(db, user_id: int):
    return db.fetchone("SELECT * FROM member_profiles WHERE user_id = ?", (int(user_id),))


def _upsert_profile(db, user_id: int, wp_user_id: int | None, payload: dict[str, Any]):
    data = _profile_row_to_dict(payload)
    data["profile_complete"] = _compute_profile_complete(data)
    existing = _get_profile(db, user_id)
    cols = [
        "family_no", "kepala_keluarga", "nama_istri", "tarombo", "oppu", "nomor_sundut",
        "hula_boru", "phone", "country_name", "country_code", "province", "city",
        "district", "subdistrict", "postal_code", "state_city", "address_detail",
        "street_name", "house_number", "rt", "rw", "is_overseas", "profile_complete",
    ]
    values = [data.get(c) for c in cols]
    if existing:
        set_clause = ", ".join(f"{c} = ?" for c in cols)
        db.execute(
            f"UPDATE member_profiles SET {set_clause}, wp_user_id = ?, updated_at = ? WHERE user_id = ?",
            values + [wp_user_id, datetime.now(timezone.utc).isoformat(), user_id],
        )
    else:
        placeholders = ", ".join("?" for _ in cols)
        col_names = ", ".join(cols)
        db.execute(
            f"""
            INSERT INTO member_profiles (user_id, wp_user_id, {col_names}, updated_at)
            VALUES (?, ?, {placeholders}, ?)
            """,
            [user_id, wp_user_id] + values + [datetime.now(timezone.utc).isoformat()],
        )
    if data.get("kepala_keluarga"):
        db.execute(
            "UPDATE users SET full_name = ?, phone = COALESCE(NULLIF(?, ''), phone) WHERE id = ?",
            (data["kepala_keluarga"], data.get("phone") or "", user_id),
        )
    return data


def _map_wp_role(tarombo_role: str) -> str:
    role = (tarombo_role or "member").lower()
    if role in ("admin", "developer"):
        return "administrator"
    if role == "pengurus":
        return "pengurus"
    return "anggota"


@bp.route("/ping", methods=["GET"])
def ping():
    return jsonify({"ok": True, "service": "tarombo-wp-bridge", "version": 1})


@bp.route("/health/db", methods=["GET"])
def health_db():
    """
    Healthcheck database yang ringan.
    Dipakai watchdog/server ops untuk membedakan API hidup vs DB down.
    """
    from flask import current_app

    try:
        db = current_app.ensure_db()
        row = db.fetchone("SELECT 1 AS ok", ())
        ok = bool(row and (row.get("ok") == 1 or row.get("ok") is True))
        if not ok:
            return jsonify({"ok": False, "error": "db_not_ready"}), 503
        return jsonify({"ok": True, "db": db.backend}), 200
    except Exception as e:
        return jsonify({"ok": False, "error": "db_down", "message": str(e)}), 503


@bp.route("/auth/login", methods=["POST"])
@_require_integration_key
def auth_login():
    from flask import current_app

    db = current_app.ensure_db()
    body = request.get_json(silent=True) or {}
    email = (body.get("email") or "").strip()
    password = body.get("password") or ""
    if not email or not password:
        return jsonify({"error": "email_password_required"}), 400

    user = _find_user_by_email(db, email)
    if not user or not check_password_hash(user["password_hash"], password):
        return jsonify({"error": "invalid_credentials"}), 401

    profile = _get_profile(db, int(user["id"]))
    ser = _token_serializer(current_app.config["SECRET_KEY"])
    token = ser.dumps({"uid": int(user["id"])})
    db.commit()
    return jsonify(
        {
            "access_token": token,
            "user": _user_payload(user, profile),
            "role": user.get("role") or "member",
            "wp_role": _map_wp_role(user.get("role") or "member"),
        }
    )


@bp.route("/registrations", methods=["POST"])
@_require_integration_key
def registrations():
    from flask import current_app

    db = current_app.ensure_db()
    body = request.get_json(silent=True) or {}
    email = (body.get("email") or "").strip().lower()
    full_name = (body.get("full_name") or "").strip()
    password = body.get("password") or ""
    phone = (body.get("phone_wa") or body.get("phone") or "").strip()
    wp_user_id = body.get("wp_user_id")
    auto_approve = bool(body.get("auto_approve"))

    if not email or not full_name or not password:
        return jsonify({"error": "missing_fields"}), 400

    existing = _find_user_by_email(db, email)
    if existing:
        user_id = int(existing["id"])
        if wp_user_id:
            db.execute(
                "UPDATE users SET wp_user_id = ? WHERE id = ?",
                (int(wp_user_id), user_id),
            )
        profile = _get_profile(db, user_id)
        db.commit()
        return jsonify({"user": _user_payload(existing, profile), "created": False})

    from db import insert_returning_id

    status = "active" if auto_approve else "pending"
    role = "member"
    user_id = insert_returning_id(
        db,
        """
        INSERT INTO users (full_name, email, password_hash, role, member_status, phone, wp_user_id, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        """,
        (
            full_name,
            email,
            generate_password_hash(password),
            role,
            status,
            phone,
            int(wp_user_id) if wp_user_id else None,
            datetime.now(timezone.utc).isoformat(),
        ),
    )
    if phone or full_name:
        _upsert_profile(
            db,
            user_id,
            int(wp_user_id) if wp_user_id else None,
            {"kepala_keluarga": full_name, "phone": phone},
        )
    user = db.fetchone("SELECT * FROM users WHERE id = ?", (user_id,))
    profile = _get_profile(db, user_id)
    db.commit()
    return jsonify({"user": _user_payload(user, profile), "created": True}), 201


@bp.route("/members/by-wp-user/<int:wp_user_id>", methods=["GET"])
@_require_integration_key
def member_by_wp_user(wp_user_id: int):
    from flask import current_app

    db = current_app.ensure_db()
    user = _find_user_by_wp_id(db, wp_user_id)
    if not user:
        user = db.fetchone(
            "SELECT * FROM users WHERE id = (SELECT user_id FROM member_profiles WHERE wp_user_id = ? LIMIT 1)",
            (wp_user_id,),
        )
    if not user:
        return jsonify({"error": "not_found"}), 404
    profile = _get_profile(db, int(user["id"]))
    return jsonify({"user": _user_payload(user, profile)})


@bp.route("/members/by-wp-user/<int:wp_user_id>/profile", methods=["PUT"])
@_require_integration_key
def member_profile_upsert(wp_user_id: int):
    from flask import current_app

    db = current_app.ensure_db()
    body = request.get_json(silent=True) or {}
    user = _find_user_by_wp_id(db, wp_user_id)
    if not user:
        email = (body.get("email") or "").strip()
        if email:
            user = _find_user_by_email(db, email)
            if user:
                db.execute(
                    "UPDATE users SET wp_user_id = ? WHERE id = ?",
                    (wp_user_id, int(user["id"])),
                )
    if not user:
        return jsonify({"error": "user_not_linked"}), 404

    data = _upsert_profile(db, int(user["id"]), wp_user_id, body)
    profile = _get_profile(db, int(user["id"]))
    db.commit()
    return jsonify({"profile": _profile_row_to_dict(profile), "profile_complete": data.get("profile_complete", False)})


@bp.route("/members/by-wp-user/<int:wp_user_id>/approve", methods=["POST"])
@_require_integration_key
def member_approve(wp_user_id: int):
    from flask import current_app

    db = current_app.ensure_db()
    user = _find_user_by_wp_id(db, wp_user_id)
    if not user:
        return jsonify({"error": "not_found"}), 404
    db.execute(
        "UPDATE users SET member_status = 'active' WHERE id = ?",
        (int(user["id"]),),
    )
    if db.backend == "sqlite":
        db.execute(
            "UPDATE people SET approved = 1 WHERE submitted_by = ?",
            (int(user["id"]),),
        )
    else:
        db.execute(
            "UPDATE people SET approved = TRUE WHERE submitted_by = ?",
            (int(user["id"]),),
        )
    user = db.fetchone("SELECT * FROM users WHERE id = ?", (int(user["id"]),))
    profile = _get_profile(db, int(user["id"]))
    db.commit()
    return jsonify({"user": _user_payload(user, profile)})


@bp.route("/members/export", methods=["GET"])
@_require_integration_key
def members_export():
    from flask import current_app

    db = current_app.ensure_db()
    rows = db.fetchall(
        """
        SELECT u.id, u.wp_user_id, u.full_name, u.email, u.phone, u.member_status,
               p.*
        FROM users u
        LEFT JOIN member_profiles p ON p.user_id = u.id
        ORDER BY u.id ASC
        """
    )
    out = []
    for row in rows:
        prof = _profile_row_to_dict(row)
        out.append(
            {
                "id": row.get("family_no") or row.get("id"),
                "kepala_keluarga": prof.get("kepala_keluarga") or row.get("full_name") or "",
                "nama_istri": prof.get("nama_istri") or "",
                "tarombo": prof.get("tarombo") or "",
                "oppu": prof.get("oppu") or "",
                "nomor_sundut": prof.get("nomor_sundut") or "",
                "address_detail": prof.get("address_detail") or "",
                "rt": prof.get("rt") or "",
                "rw": prof.get("rw") or "",
                "district": prof.get("district") or "",
                "subdistrict": prof.get("subdistrict") or "",
                "city": prof.get("city") or "",
                "province": prof.get("province") or "",
                "postal_code": prof.get("postal_code") or "",
                "hula_boru": prof.get("hula_boru") or "",
                "phone": prof.get("phone") or row.get("phone") or "",
                "email": row.get("email") or "",
                "member_status": row.get("member_status") or "",
                "wp_user_id": row.get("wp_user_id"),
            }
        )
    return jsonify({"rows": out, "count": len(out)})


def register_wp_api(app):
    """Mount blueprint; attach ensure_db helper."""

    def ensure_db():
        from flask import g

        if "db" not in g:
            from db import connect

            g.db = connect()
        return g.db

    app.ensure_db = ensure_db  # type: ignore[attr-defined]
    app.register_blueprint(bp)
