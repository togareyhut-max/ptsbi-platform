"""Dynamic registration form configuration."""

from __future__ import annotations

import json

DEFAULT_FIELDS = [
    ("account", "full_name", "Nama Lengkap (Akun)", "text", True, 10),
    ("account", "email", "Email", "email", True, 20),
    ("account", "password", "Password", "password", True, 30),
    ("person", "person_name", "Nama Individu Tarombo", "text", True, 40),
    ("person", "gender", "Jenis Kelamin", "select", True, 50),
    ("person", "marga", "Marga", "text", True, 60),
    ("person", "birth_year", "Tahun Lahir", "number", False, 70),
    ("person", "father_name", "Nama Ayah", "text", True, 80),
    ("person", "mother_name", "Nama Ibu", "text", True, 90),
    ("tarombo", "tarombo_status", "Status Tarombo", "select", True, 100),
    ("tarombo", "is_married", "Status Menikah", "select", False, 110),
    ("tarombo", "reference_female_line_name", "Acuan Garis Perempuan (Bere/Ibebere)", "text", False, 120),
    ("marriage", "spouse_name", "Nama Pasangan", "text", False, 130),
    ("marriage", "spouse_marga", "Marga Pasangan", "text", False, 140),
]

SELECT_OPTIONS = {
    "gender": [{"value": "", "label": "Pilih"}, {"value": "male", "label": "Laki-laki"}, {"value": "female", "label": "Perempuan"}],
    "tarombo_status": [
        {"value": "", "label": "Pilih"},
        {"value": "anak", "label": "Anak — laki-laki Samosir; pasangan ikut anak"},
        {"value": "boru", "label": "Boru — perempuan Samosir; pasangan ikut boru"},
        {"value": "bere", "label": "Bere — anak garis perempuan Samosir; pasangan ikut bere"},
        {"value": "ibebere", "label": "Ibebere — cucu garis perempuan Samosir; pasangan ikut ibebere"},
    ],
    "is_married": [{"value": "no", "label": "Belum"}, {"value": "yes", "label": "Sudah"}],
}

SECTION_TITLES = {
    "account": "Data Akun",
    "person": "Data Individu Tarombo",
    "tarombo": "Status Tarombo",
    "marriage": "Data Pasangan",
    "children": "Data Anak",
}


def seed_form_fields(db):
    try:
        existing = db.fetchone("SELECT COUNT(*) AS c FROM form_field_configs")
    except Exception:
        return
    if not existing:
        return
    count = existing["c"] if hasattr(existing, "keys") else existing[0]
    if count and int(count) > 0:
        return
    for section, key, label, ftype, required, order in DEFAULT_FIELDS:
        options = SELECT_OPTIONS.get(key)
        db.execute(
            """
            INSERT INTO form_field_configs (
              section, field_key, label, field_type, is_required, is_visible, display_order, options_json, default_value
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            """,
            (
                section,
                key,
                label,
                ftype,
                bool_db_flag(db, required),
                bool_db_flag(db, True),
                order,
                json.dumps(options, ensure_ascii=False) if options else None,
                "Samosir" if key == "marga" else None,
            ),
        )
    db.commit()


def bool_db_flag(db, value: bool):
    if db.backend == "postgres":
        return value
    return 1 if value else 0


def row_to_bool(db, row, key):
    val = row[key]
    if db.backend == "postgres":
        return bool(val)
    return bool(val)


def get_fields_by_section(db):
    visible = "is_visible IS TRUE" if db.backend == "postgres" else "is_visible = 1"
    rows = db.fetchall(
        f"""
        SELECT * FROM form_field_configs
        WHERE {visible}
        ORDER BY section, display_order, id
        """
    )
    grouped = {}
    for row in rows:
        section = row["section"]
        field = dict(row)
        field["is_required"] = row_to_bool(db, row, "is_required")
        field["is_visible"] = row_to_bool(db, row, "is_visible")
        if field.get("options_json"):
            try:
                field["options"] = json.loads(field["options_json"])
            except json.JSONDecodeError:
                field["options"] = []
        else:
            field["options"] = SELECT_OPTIONS.get(field["field_key"], [])
        grouped.setdefault(section, []).append(field)
    return grouped, SECTION_TITLES


def get_all_fields_admin(db):
    rows = db.fetchall(
        "SELECT * FROM form_field_configs ORDER BY section, display_order, id"
    )
    result = []
    for row in rows:
        field = dict(row)
        field["is_required"] = row_to_bool(db, row, "is_required")
        field["is_visible"] = row_to_bool(db, row, "is_visible")
        result.append(field)
    return result, SECTION_TITLES


def validate_submission_from_config(db, form_data):
    """Return first error message or None."""
    fields, _ = get_fields_by_section(db)
    for section_fields in fields.values():
        for field in section_fields:
            if not field["is_required"]:
                continue
            key = field["field_key"]
            val = (form_data.get(key) or "").strip()
            if not val:
                return f"{field['label']} wajib diisi."
    return None
