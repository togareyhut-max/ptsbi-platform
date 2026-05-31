"""Logo situs — unggah PNG oleh admin, simpan di data/uploads (persisten saat deploy)."""

from __future__ import annotations

from pathlib import Path

from flask import url_for
from werkzeug.utils import secure_filename

BASE_DIR = Path(__file__).resolve().parents[1]
UPLOAD_DIR = BASE_DIR / "data" / "uploads"
LOGO_FILENAME = "site-logo.png"
DEFAULT_STATIC_LOGO = "logo-ptsbi.svg"
PNG_SIGNATURE = b"\x89PNG\r\n\x1a\n"
MAX_LOGO_BYTES = 2 * 1024 * 1024


def ensure_upload_dir() -> Path:
    UPLOAD_DIR.mkdir(parents=True, exist_ok=True)
    return UPLOAD_DIR


def custom_logo_path() -> Path | None:
    path = UPLOAD_DIR / LOGO_FILENAME
    if path.is_file() and path.stat().st_size > 0:
        return path
    return None


def has_custom_logo() -> bool:
    return custom_logo_path() is not None


def _is_png(data: bytes) -> bool:
    return len(data) >= len(PNG_SIGNATURE) and data[: len(PNG_SIGNATURE)] == PNG_SIGNATURE


def validate_logo_upload(file_storage) -> str | None:
    if not file_storage or not file_storage.filename:
        return "Pilih berkas logo (PNG)."
    name = secure_filename(file_storage.filename).lower()
    if not name.endswith(".png"):
        return "Logo harus berformat PNG (.png)."
    file_storage.stream.seek(0, 2)
    size = file_storage.stream.tell()
    file_storage.stream.seek(0)
    if size <= 0:
        return "Berkas logo kosong."
    if size > MAX_LOGO_BYTES:
        return f"Ukuran logo maksimal {MAX_LOGO_BYTES // (1024 * 1024)} MB."
    header = file_storage.stream.read(len(PNG_SIGNATURE))
    file_storage.stream.seek(0)
    if not _is_png(header):
        return "Berkas bukan gambar PNG yang valid."
    return None


def save_logo_upload(file_storage) -> Path:
    err = validate_logo_upload(file_storage)
    if err:
        raise ValueError(err)
    ensure_upload_dir()
    dest = UPLOAD_DIR / LOGO_FILENAME
    file_storage.save(dest)
    if not _is_png(dest.read_bytes()[: len(PNG_SIGNATURE)]):
        dest.unlink(missing_ok=True)
        raise ValueError("Berkas bukan gambar PNG yang valid.")
    return dest


def remove_custom_logo() -> bool:
    path = custom_logo_path()
    if not path:
        return False
    path.unlink()
    return True


def site_logo_url() -> str:
    """URL logo untuk template (butuh application context)."""
    custom = custom_logo_path()
    if custom:
        version = int(custom.stat().st_mtime)
        return url_for("site_logo", v=version)
    return url_for("static", filename=DEFAULT_STATIC_LOGO)


def logo_info() -> dict:
    custom = custom_logo_path()
    if custom:
        st = custom.stat()
        return {
            "has_custom": True,
            "filename": LOGO_FILENAME,
            "size_kb": round(st.st_size / 1024, 1),
            "url": site_logo_url(),
        }
    return {
        "has_custom": False,
        "filename": DEFAULT_STATIC_LOGO,
        "size_kb": None,
        "url": site_logo_url(),
    }
