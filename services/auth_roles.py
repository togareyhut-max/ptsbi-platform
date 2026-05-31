"""Peran pengguna: member/anggota (default), pengurus, admin, developer."""

from __future__ import annotations

from functools import wraps

from flask import flash, redirect, session, url_for

ROLE_MEMBER = "member"
ROLE_PENGURUS = "pengurus"
ROLE_ADMIN = "admin"
ROLE_DEVELOPER = "developer"

# Semua peran berikut boleh masuk panel admin (operasional).
STAFF_ROLES = frozenset({ROLE_PENGURUS, ROLE_ADMIN, ROLE_DEVELOPER})
ALL_ROLES = frozenset({ROLE_MEMBER, ROLE_PENGURUS, ROLE_ADMIN, ROLE_DEVELOPER})


def normalize_role(role: str | None) -> str:
    r = (role or ROLE_MEMBER).strip().lower()
    return r if r in ALL_ROLES else ROLE_MEMBER


def session_role() -> str | None:
    if not session.get("user_id"):
        return None
    return normalize_role(session.get("role"))


def is_member_role(role: str | None = None) -> bool:
    return normalize_role(role or session_role()) == ROLE_MEMBER


def is_staff_role(role: str | None = None) -> bool:
    return normalize_role(role or session_role()) in STAFF_ROLES


def is_admin_role(role: str | None = None) -> bool:
    return normalize_role(role or session_role()) == ROLE_ADMIN


def is_developer_role(role: str | None = None) -> bool:
    return normalize_role(role or session_role()) == ROLE_DEVELOPER


def is_pengurus_role(role: str | None = None) -> bool:
    return normalize_role(role or session_role()) == ROLE_PENGURUS


def user_can_view_tarombo() -> bool:
    """
    Lihat pohon Tarombo:
    - member/anggota: hanya jika member_status = active (disetujui admin)
    - pengurus / admin / developer: selalu
    """
    if not session.get("user_id"):
        return False
    role = session_role()
    if role in STAFF_ROLES:
        return True
    if role == ROLE_MEMBER:
        return session.get("member_status") == "active"
    return False


def member_default_status() -> str:
    return "pending"


def staff_redirect_endpoint() -> str:
    return "admin"


def login_required(fn):
    @wraps(fn)
    def wrapper(*args, **kwargs):
        if not session.get("user_id"):
            return redirect(url_for("login"))
        return fn(*args, **kwargs)

    return wrapper


def staff_required(fn):
    """Admin operasional: konfirmasi, review data, sinkron, gabung duplikat."""

    @wraps(fn)
    @login_required
    def wrapper(*args, **kwargs):
        if not is_staff_role():
            flash("Halaman ini hanya untuk pengurus, admin, atau developer.", "error")
            return redirect(url_for("dashboard"))
        return fn(*args, **kwargs)

    return wrapper


def developer_required(fn):
    """Pengaturan sistem & perbaikan teknis (form, database, logo, audit)."""

    @wraps(fn)
    @login_required
    def wrapper(*args, **kwargs):
        if not is_developer_role():
            flash("Halaman ini hanya untuk developer.", "error")
            if is_staff_role():
                return redirect(url_for("admin"))
            return redirect(url_for("dashboard"))
        return fn(*args, **kwargs)

    return wrapper


def role_label(role: str | None) -> str:
    labels = {
        ROLE_MEMBER: "Anggota",
        ROLE_PENGURUS: "Pengurus",
        ROLE_ADMIN: "Admin",
        ROLE_DEVELOPER: "Developer",
    }
    return labels.get(normalize_role(role), "Anggota")
