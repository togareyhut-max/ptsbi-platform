"""Penamaan tampilan Batak di pohon — Boru untuk perempuan, nama biasa untuk suami boru."""

from __future__ import annotations


def get_first_token(full_name: str) -> str:
    if not full_name:
        return ""
    return full_name.strip().split()[0]


def infer_marga_from_name(full_name: str) -> str | None:
    if not full_name:
        return None
    parts = [p for p in full_name.strip().split() if p]
    return parts[-1] if parts else None


def _marga_origin_for_married_in_female(person: dict) -> str:
    """Marga asal perempuan yang menikah ke garis Samosir (mis. Sinurat)."""
    marga = (person.get("marga") or "").strip()
    if marga and marga.lower() != "samosir":
        return marga
    from_name = infer_marga_from_name(person.get("full_name") or "")
    if from_name and from_name.lower() != "samosir":
        return from_name
    spouse_marga = (person.get("spouse_marga") or "").strip()
    if spouse_marga and spouse_marga.lower() != "samosir":
        return spouse_marga
    return marga or from_name or ""


def boru_display_marga(person: dict, partner: dict | None) -> str | None:
    """
    Marga setelah 'Boru' dalam nama tampilan, atau None jika tidak pakai format Boru.

    - Perempuan bermarga Samosir (status boru): ... Boru Samosir
    - Istri laki-laki Samosir (marga asal lain): ... Boru Sinurat
    - Suami perempuan boru (laki-laki luar): tidak pakai Boru di nama (return None)
    """
    if person.get("gender") != "female":
        return None

    status = (person.get("tarombo_status") or "").lower()
    marga = (person.get("marga") or "").strip().lower()

    if status == "boru" and marga == "samosir":
        return "Samosir"

    if partner and partner.get("gender") == "male" and (partner.get("marga") or "").strip().lower() == "samosir":
        return _marga_origin_for_married_in_female(person)

    if status == "boru":
        origin = _marga_origin_for_married_in_female(person)
        return origin or "Samosir"

    return None


def format_person_display_name(person: dict, partner: dict | None = None) -> str:
    """
    Nama tampilan di pohon / detail.

    Perempuan masuk ke garis Samosir: Jerlin Sinurat → Jerlin Boru Sinurat.
    Suami perempuan boru (Toga Sitompul): tetap Toga Sitompul.
    Laki-laki Samosir: nama lengkap biasa.
    """
    full_name = (person.get("full_name") or "").strip()
    if not full_name:
        return ""

    if person.get("gender") == "male":
        return full_name

    boru_marga = boru_display_marga(person, partner)
    if boru_marga:
        return f"{get_first_token(full_name)} Boru {boru_marga}"
    return full_name


def format_spouse_display_name(spouse: dict, partner: dict) -> str:
    """Nama pasangan di samping kartu utama (dengan konteks pasangan)."""
    return format_person_display_name(spouse, partner)
