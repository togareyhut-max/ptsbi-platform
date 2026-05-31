"""Operasi penyusunan pohon dari editor visual (drag & panel edit)."""

from __future__ import annotations

from services.matching import person_identity_fields


def _person_row(db, person_id: int):
    return db.fetchone("SELECT * FROM people WHERE id = ?", (person_id,))


def reparent_person(db, person_id: int, father_id: int) -> dict:
    """Jadikan person_id anak dari father_id (garis ayah Samosir)."""
    person = _person_row(db, person_id)
    father = _person_row(db, father_id)
    if not person or not father:
        raise ValueError("Orang atau ayah tidak ditemukan.")
    if father.get("gender") != "male":
        raise ValueError("Target harus laki-laki (garis patrilineal mengikuti ayah).")
    if person_id == father_id:
        raise ValueError("Orang tidak boleh menjadi anak dari dirinya sendiri.")

    father_name = father["full_name"]
    mother_name = (father.get("spouse_name") or "").strip() or "Tidak Diketahui"
    ident = person_identity_fields(
        person["full_name"],
        person.get("marga"),
        father_name,
        person.get("birth_year"),
        mother_name,
    )
    db.execute(
        """
        UPDATE people SET
          father_name = ?,
          father_name_normalized = ?,
          mother_name = ?,
          mother_name_normalized = ?
        WHERE id = ?
        """,
        (
            father_name,
            ident["father_name_normalized"],
            mother_name,
            ident["mother_name_normalized"],
            person_id,
        ),
    )
    return {
        "op": "reparent",
        "person_id": person_id,
        "father_id": father_id,
        "father_name": father_name,
        "mother_name": mother_name,
    }


def swap_parents(db, person_id_a: int, person_id_b: int) -> dict:
    """Tukar posisi di pohon: ayah/ibu kedua orang ditukar (replace slot)."""
    if person_id_a == person_id_b:
        raise ValueError("Pilih dua orang yang berbeda.")
    a = _person_row(db, person_id_a)
    b = _person_row(db, person_id_b)
    if not a or not b:
        raise ValueError("Salah satu orang tidak ditemukan.")

    def pack(row):
        ident = person_identity_fields(
            row["full_name"],
            row.get("marga"),
            row["father_name"],
            row.get("birth_year"),
            row.get("mother_name"),
        )
        return (
            row["father_name"],
            ident["father_name_normalized"],
            row["mother_name"],
            ident["mother_name_normalized"],
        )

    af, afn, am, amn = pack(a)
    bf, bfn, bm, bmn = pack(b)
    db.execute(
        """
        UPDATE people SET
          father_name = ?, father_name_normalized = ?,
          mother_name = ?, mother_name_normalized = ?
        WHERE id = ?
        """,
        (bf, bfn, bm, bmn, person_id_a),
    )
    db.execute(
        """
        UPDATE people SET
          father_name = ?, father_name_normalized = ?,
          mother_name = ?, mother_name_normalized = ?
        WHERE id = ?
        """,
        (af, afn, am, amn, person_id_b),
    )
    return {"op": "swap_parents", "person_id_a": person_id_a, "person_id_b": person_id_b}


def replace_with_person(db, source_id: int, target_id: int) -> dict:
    """
    Orang source mengambil posisi orang target di pohon
    (mengikuti ayah/ibu target). Orang target mengambil ayah/ibu source.
    """
    if source_id == target_id:
        raise ValueError("Pilih orang yang berbeda.")
    return swap_parents(db, source_id, target_id)


def update_person_fields(db, person_id: int, fields: dict) -> dict:
    person = _person_row(db, person_id)
    if not person:
        raise ValueError("Orang tidak ditemukan.")

    full_name = (fields.get("full_name") or person["full_name"]).strip()
    marga = (fields.get("marga") or person["marga"]).strip()
    father_name = (fields.get("father_name") or person["father_name"]).strip()
    mother_name = (fields.get("mother_name") or person["mother_name"]).strip()
    birth_raw = fields.get("birth_year")
    birth_year = person.get("birth_year")
    if birth_raw not in (None, ""):
        try:
            birth_year = int(birth_raw)
        except (TypeError, ValueError):
            birth_year = None

    ident = person_identity_fields(full_name, marga, father_name, birth_year, mother_name)
    db.execute(
        """
        UPDATE people SET
          full_name = ?, name_normalized = ?, marga = ?,
          father_name = ?, father_name_normalized = ?,
          mother_name = ?, mother_name_normalized = ?,
          birth_year = ?,
          spouse_name = ?, spouse_marga = ?,
          tarombo_status = ?
        WHERE id = ?
        """,
        (
            full_name,
            ident["name_normalized"],
            marga,
            father_name,
            ident["father_name_normalized"],
            mother_name,
            ident["mother_name_normalized"],
            birth_year,
            (fields.get("spouse_name") or person.get("spouse_name") or "").strip() or None,
            (fields.get("spouse_marga") or person.get("spouse_marga") or "").strip() or None,
            (fields.get("tarombo_status") or person.get("tarombo_status") or "anak").strip(),
            person_id,
        ),
    )
    return {"op": "update_person", "person_id": person_id, "full_name": full_name}


def apply_tree_changes(db, changes: list[dict]) -> list[dict]:
    applied = []
    for item in changes:
        op = (item.get("op") or "").strip()
        if op == "reparent":
            applied.append(reparent_person(db, int(item["person_id"]), int(item["father_id"])))
        elif op == "swap_parents":
            applied.append(swap_parents(db, int(item["person_id_a"]), int(item["person_id_b"])))
        elif op == "replace_with":
            applied.append(replace_with_person(db, int(item["source_id"]), int(item["target_id"])))
        elif op == "update_person":
            applied.append(update_person_fields(db, int(item["person_id"]), item.get("fields") or {}))
        else:
            raise ValueError(f"Operasi tidak dikenali: {op}")
    return applied
