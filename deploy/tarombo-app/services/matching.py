"""Person duplicate matching — kunci: nama orang + nama ayah + nama ibu (bukan marga)."""

from __future__ import annotations

_UNKNOWN_PARENT = frozenset({"", "tidak diketahui", "unknown", "-"})


def normalize_name(name: str) -> str:
    if not name:
        return ""
    return " ".join(name.strip().lower().split())


def normalize_parent_name(name: str | None) -> str:
    n = normalize_name(name or "")
    if n in _UNKNOWN_PARENT:
        return ""
    return n


def person_identity_fields(
    full_name: str,
    marga: str,
    father_name: str | None,
    birth_year: int | None,
    mother_name: str | None = None,
):
    return {
        "name_normalized": normalize_name(full_name),
        "marga": (marga or "Samosir").strip(),
        "father_name_normalized": normalize_parent_name(father_name),
        "mother_name_normalized": normalize_parent_name(mother_name),
        "birth_year": birth_year,
    }


def identity_merge_eligible(ident: dict) -> bool:
    """
    Boleh digabung hanya jika nama + ayah + ibu terisi (bukan homonim nama saja).
    Ayah/ibu di sini = teks pada kartu orang itu, bukan record orang tua terpisah.
    """
    return bool(
        ident.get("name_normalized")
        and ident.get("father_name_normalized")
        and ident.get("mother_name_normalized")
    )


def identity_match_clause(db, alias: str = "") -> str:
    """Cocok: nama orang + nama ayah + nama ibu (tanpa marga)."""
    p = f"{alias}." if alias else ""
    return f"""
            {p}name_normalized = ?
            AND COALESCE({p}father_name_normalized, '') = ?
            AND COALESCE({p}mother_name_normalized, '') = ?
        """


def find_existing_person(
    db,
    full_name: str,
    marga: str,
    father_name: str | None = None,
    birth_year: int | None = None,
    mother_name: str | None = None,
    approved_only: bool = True,
):
    """
    Cari duplikat orang yang sama: nama + nama ayah + nama ibu persis sama.
    Tanpa ayah & ibu lengkap → tidak dianggap duplikat (bisa saja homonim nama).
    """
    ident = person_identity_fields(full_name, marga, father_name, birth_year, mother_name)
    if not identity_merge_eligible(ident):
        return None, None

    approved_clause = ""
    if approved_only:
        approved_clause = "AND approved IS TRUE" if db.backend == "postgres" else "AND approved = 1"

    params_base = (
        ident["name_normalized"],
        ident["father_name_normalized"],
        ident["mother_name_normalized"],
    )
    clause = identity_match_clause(db)

    if ident["birth_year"]:
        row = db.fetchone(
            f"""
            SELECT * FROM people
            WHERE {clause}
              AND birth_year = ?
              {approved_clause}
            ORDER BY approved DESC, id ASC LIMIT 1
            """,
            (*params_base, ident["birth_year"]),
        )
        if row:
            return row, "strict"

    row = db.fetchone(
        f"""
        SELECT * FROM people
        WHERE {clause}
          {approved_clause}
        ORDER BY approved DESC, id ASC LIMIT 1
        """,
        params_base,
    )
    if row:
        return row, "identity"

    if not approved_only:
        row = db.fetchone(
            f"""
            SELECT * FROM people
            WHERE {clause}
            ORDER BY approved DESC, id ASC LIMIT 1
            """,
            params_base,
        )
        if row:
            return row, "identity"

    return None, None


def find_person_loose(
    db,
    full_name: str,
    marga: str = "Samosir",
    *,
    gender: str | None = None,
    approved_only: bool = True,
):
    """
    Cari record yang sudah ada dari nama (+ marga, opsional gender).
    Dipakai saat ayah/ibu belum lengkap sehingga kunci identitas ketat tidak dipakai.
    """
    nn = normalize_name(full_name)
    if not nn:
        return None
    approved_clause = ""
    if approved_only:
        approved_clause = "AND approved IS TRUE" if db.backend == "postgres" else "AND approved = 1"
    marga_lower = (marga or "Samosir").strip().lower()
    params: list = [nn, marga_lower]
    gender_clause = ""
    if gender:
        gender_clause = "AND LOWER(gender) = ?"
        params.append(gender.lower())
    return db.fetchone(
        f"""
        SELECT * FROM people
        WHERE name_normalized = ?
          AND LOWER(TRIM(COALESCE(marga, ''))) = ?
          {gender_clause}
          AND full_name != 'Tidak Diketahui'
          {approved_clause}
        ORDER BY approved DESC, id ASC
        LIMIT 1
        """,
        tuple(params),
    )


def find_all_matching_people(
    db,
    full_name: str,
    marga: str,
    father_name: str | None = None,
    mother_name: str | None = None,
):
    ident = person_identity_fields(full_name, marga, father_name, birth_year=None, mother_name=mother_name)
    if not identity_merge_eligible(ident):
        return []
    clause = identity_match_clause(db)
    return db.fetchall(
        f"""
        SELECT * FROM people
        WHERE {clause}
        ORDER BY approved DESC, id ASC
        """,
        (
            ident["name_normalized"],
            ident["father_name_normalized"],
            ident["mother_name_normalized"],
        ),
    )
