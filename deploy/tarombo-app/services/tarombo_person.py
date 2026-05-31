"""Tambah anggota tarombo tanpa akun login (input admin)."""

from __future__ import annotations

from db import coerce_submitted_by, insert_returning_id
from services.sibling_order import parse_child_order


def parse_tarombo_person_form(form) -> dict:
    birth_year_raw = (form.get("birth_year") or "").strip()
    person_name = (form.get("person_name") or form.get("full_name") or "").strip()
    return {
        "person_name": person_name,
        "gender": (form.get("gender") or "").strip(),
        "marga": (form.get("marga") or "").strip(),
        "birth_year": int(birth_year_raw) if birth_year_raw.isdigit() else None,
        "father_name": (form.get("father_name") or "").strip(),
        "mother_name": (form.get("mother_name") or "").strip(),
        "tarombo_status": (form.get("tarombo_status") or "").strip(),
        "is_married": (form.get("is_married") or "no").strip(),
        "spouse_name": (form.get("spouse_name") or "").strip(),
        "spouse_marga": (form.get("spouse_marga") or "").strip(),
        "reference_female_line_name": (form.get("reference_female_line_name") or "").strip(),
        "child_names": form.getlist("child_name"),
        "child_genders": form.getlist("child_gender"),
        "child_years": form.getlist("child_birth_year"),
        "child_orders": form.getlist("child_order"),
        "child_order": (form.get("child_order") or "").strip(),
    }


def add_tarombo_person(
    db,
    data: dict,
    *,
    bool_db,
    admin_user_id: int | None,
    person_identity_fields,
    normalize_spouse_fields,
    save_submitted_children,
    find_existing_person=None,
    check_duplicates: bool = True,
) -> dict:
    if not data.get("person_name"):
        raise ValueError("Nama lengkap wajib diisi.")

    if check_duplicates and find_existing_person:
        existing, match_type = find_existing_person(
            db,
            data["person_name"],
            data["marga"],
            father_name=data["father_name"],
            mother_name=data["mother_name"],
            birth_year=data["birth_year"],
            approved_only=False,
        )
        if existing and match_type in ("strict", "identity"):
            raise ValueError(
                f"Orang ini sudah terdaftar (nama + nama ayah + nama ibu sama): "
                f"{existing['full_name']} (ID {existing['id']}). "
                f"Gunakan edit atau Gabung Duplikat di admin."
            )

    spouse_name, spouse_marga = normalize_spouse_fields(
        data["gender"],
        data["marga"],
        data["is_married"],
        data.get("spouse_name") or "",
        data.get("spouse_marga") or "",
    )
    ident = person_identity_fields(
        data["person_name"],
        data["marga"],
        data["father_name"],
        data["birth_year"],
        data["mother_name"],
    )
    person_id = insert_returning_id(
        db,
        """
        INSERT INTO people (
          full_name, name_normalized, gender, marga, birth_year, child_order,
          father_name, father_name_normalized, mother_name, mother_name_normalized,
          tarombo_status, reference_female_line_name, spouse_name, spouse_marga,
          submitted_by, approved
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        """,
        (
            data["person_name"],
            ident["name_normalized"],
            data["gender"],
            data["marga"],
            data["birth_year"],
            parse_child_order(data.get("child_order")),
            data["father_name"],
            ident["father_name_normalized"],
            data["mother_name"],
            ident["mother_name_normalized"],
            data["tarombo_status"],
            data.get("reference_female_line_name") or None,
            spouse_name,
            spouse_marga,
            coerce_submitted_by(db, admin_user_id),
            bool_db(db, True),
        ),
    )
    save_submitted_children(
        db,
        person_id,
        data.get("child_names") or [],
        data.get("child_genders") or [],
        data.get("child_years") or [],
        data.get("child_orders") or [],
    )
    db.commit()
    return {"person_id": person_id, "full_name": data["person_name"]}
