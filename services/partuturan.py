"""Definisi status partuturan Tarombo Marga Samosir (aturan dikunci)."""

from __future__ import annotations

PARTUTURAN_STATUSES = {
    "anak": {
        "label": "Anak",
        "summary": "Keturunan laki-laki garis patrilineal (satu marga).",
        "description": (
            "Keturunan kandung berjenis kelamin laki-laki dari garis patrilineal (satu marga). "
            "Dalam konteks adat yang lebih luas, anak juga mencakup keturunan laki-laki "
            "dari saudara kandung laki-laki."
        ),
    },
    "boru": {
        "label": "Boru",
        "summary": "Perempuan marga sama; atau marga yang mengambil istri dari marga kita.",
        "description": (
            "Memiliki dua makna. Pertama, secara biologis merujuk pada anak perempuan atau "
            "saudara perempuan kandung yang berasal dari marga yang sama. Kedua, secara struktural "
            "dalam adat, boru adalah sebutan komunal untuk pihak (marga) yang mengambil istri "
            "dari marga kita."
        ),
    },
    "bere": {
        "label": "Bere",
        "summary": "Keponakan dari saudara perempuan (Ito); pihak laki-laki memanggil Bere.",
        "description": (
            "Keponakan yang terlahir dari saudara perempuan kandung (disebut Ito oleh pihak laki-laki). "
            "Anak-anak dari saudara perempuan akan memanggil pihak laki-laki dengan sebutan Tulang (paman), "
            "dan sebagai balasannya pihak laki-laki menyebut mereka Bere."
        ),
    },
    "ibebere": {
        "label": "Ibebere",
        "summary": "Cucu dari jalur saudara perempuan; anak kandung seorang Bere.",
        "description": (
            "Cucu dari jalur saudara perempuan. Secara harfiah, ibebere adalah keturunan "
            "atau anak kandung dari seorang Bere."
        ),
    },
}

# Kode lama di DB → status tampilan kanonik
_LEGACY_STATUS_MAP = {
    "spouse_of_boru": "boru",
    "spouse_of_bere": "bere",
}


def _is_samosir_marga(marga: str | None) -> bool:
    return (marga or "").strip().lower() == "samosir"


def _normalize_raw_status(raw: str | None) -> str:
    code = (raw or "").strip().lower()
    return _LEGACY_STATUS_MAP.get(code, code)


def effective_partuturan_code(person: dict, partner: dict | None = None) -> str:
    """
    Status partuturan efektif untuk panel detail / pohon (Marga Samosir).

    Penempatan di silsilah:
    - Anak: laki-laki patrilineal bermarga Samosir; pasangan masuk garis anak.
    - Boru: perempuan bermarga Samosir; pasangan/marga pengantin masuk garis boru.
    - Bere: garis keponakan dari saudara perempuan (Ito).
    - Ibebere: cucu dari garis Bere.
    """
    gender = (person.get("gender") or "").strip().lower()
    raw = _normalize_raw_status(person.get("tarombo_status"))
    marga_samosir = _is_samosir_marga(person.get("marga"))

    if raw in ("bere", "ibebere"):
        code = raw
    elif gender == "female" and marga_samosir:
        code = "boru"
    elif gender == "male" and marga_samosir:
        code = "anak"
    elif raw in PARTUTURAN_STATUSES:
        code = raw
    elif gender == "male":
        code = "anak"
    elif gender == "female":
        code = "bere"
    else:
        code = "anak"

    if not partner:
        return code

    partner_code = effective_partuturan_code(partner, None)

    if gender == "female" and marga_samosir:
        return "boru"

    if partner_code in ("anak", "boru", "bere", "ibebere"):
        return partner_code

    return code


def partuturan_label(status_code: str | None) -> str:
    code = _normalize_raw_status(status_code)
    if not code:
        return "-"
    info = PARTUTURAN_STATUSES.get(code)
    return info["label"] if info else code


def partuturan_summary(status_code: str | None, *, person: dict | None = None, partner: dict | None = None) -> str:
    code = effective_partuturan_code(person, partner) if person else _normalize_raw_status(status_code)
    if not code:
        return "-"
    info = PARTUTURAN_STATUSES.get(code)
    return info["summary"] if info else code


def partuturan_description(status_code: str | None, *, person: dict | None = None, partner: dict | None = None) -> str:
    code = effective_partuturan_code(person, partner) if person else _normalize_raw_status(status_code)
    if not code:
        return "Status partuturan belum diisi."
    info = PARTUTURAN_STATUSES.get(code)
    return info["description"] if info else ""


def partuturan_detail(person: dict, partner: dict | None = None) -> dict:
    """Payload untuk panel detail pohon / admin."""
    from services.batak_names import format_person_display_name, format_spouse_display_name

    code = effective_partuturan_code(person, partner)
    spouse_name = person.get("spouse_name") or ""
    spouse_marga = person.get("spouse_marga") or ""
    spouse_display = None
    if spouse_name:
        stub = {
            "full_name": spouse_name,
            "gender": "female" if person.get("gender") == "male" else "male",
            "marga": spouse_marga or spouse_name.split()[-1] if spouse_name else "",
            "tarombo_status": code,
            "spouse_marga": person.get("marga"),
        }
        spouse_display = format_spouse_display_name(stub, person)
        spouse_code = effective_partuturan_code(stub, person)
    else:
        spouse_code = None

    return {
        "code": code,
        "label": partuturan_label(code),
        "summary": partuturan_summary(code, person=person, partner=partner),
        "description": partuturan_description(code, person=person, partner=partner),
        "spouse_name": spouse_name or None,
        "spouse_marga": spouse_marga or None,
        "spouse_display": spouse_display,
        "spouse_partuturan_label": partuturan_label(spouse_code) if spouse_code else None,
        "display_name": format_person_display_name(person, partner),
    }
