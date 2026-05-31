"""Pendaftaran member + data tarombo (publik & admin)."""

from __future__ import annotations

from werkzeug.security import generate_password_hash

from db import coerce_submitted_by, insert_returning_id


def create_member_with_tarombo(
    db,
    *,
    bool_db,
    account_full_name: str,
    email: str,
    password: str,
    person_name: str,
    gender: str,
    marga: str,
    birth_year: int | None,
    father_name: str,
    mother_name: str,
    tarombo_status: str,
    is_married: str,
    spouse_name: str | None,
    spouse_marga: str | None,
    reference_female_line_name: str | None,
    child_names: list,
    child_genders: list,
    child_years: list,
    child_orders: list | None = None,
    member_status: str = "pending",
    approve_immediately: bool = False,
    submitted_by_admin_id: int | None = None,
    person_identity_fields,
    normalize_spouse_fields,
    save_submitted_children,
    find_existing_person=None,
) -> dict:
    """
    Buat user member + record people. Mengembalikan dict hasil atau raises ValueError.
    """
    email = email.strip().lower()
    if db.fetchone("SELECT id FROM users WHERE email = ?", (email,)):
        raise ValueError("Email sudah terdaftar. Gunakan email lain.")

    if find_existing_person and approve_immediately:
        existing, match_type = find_existing_person(
            db,
            person_name,
            marga,
            father_name=father_name,
            mother_name=mother_name,
            birth_year=birth_year,
            approved_only=False,
        )
        if existing and match_type in ("strict", "identity"):
            raise ValueError(
                f"Data sudah ada (nama + nama ayah + nama ibu sama): {existing['full_name']} "
                f"(ID {existing['id']}). Hubungi admin jika ini entri ganda."
            )

    user_id = insert_returning_id(
        db,
        """
        INSERT INTO users (full_name, email, password_hash, role, member_status)
        VALUES (?, ?, ?, 'member', ?)
        """,
        (account_full_name, email, generate_password_hash(password), member_status),
    )

    spouse_name, spouse_marga = normalize_spouse_fields(
        gender, marga, is_married, spouse_name or "", spouse_marga or ""
    )
    ident = person_identity_fields(person_name, marga, father_name, birth_year, mother_name)
    person_id = insert_returning_id(
        db,
        """
        INSERT INTO people (
          full_name, name_normalized, gender, marga, birth_year,
          father_name, father_name_normalized, mother_name, mother_name_normalized,
          tarombo_status, reference_female_line_name, spouse_name, spouse_marga,
          submitted_by, approved
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        """,
        (
            person_name,
            ident["name_normalized"],
            gender,
            marga,
            birth_year,
            father_name,
            ident["father_name_normalized"],
            mother_name,
            ident["mother_name_normalized"],
            tarombo_status,
            reference_female_line_name,
            spouse_name,
            spouse_marga,
            coerce_submitted_by(db, submitted_by_admin_id or user_id),
            bool_db(db, approve_immediately),
        ),
    )

    save_submitted_children(db, person_id, child_names, child_genders, child_years, child_orders or [])
    db.commit()

    return {
        "user_id": user_id,
        "person_id": person_id,
        "email": email,
        "approved": approve_immediately,
        "member_status": member_status,
    }
