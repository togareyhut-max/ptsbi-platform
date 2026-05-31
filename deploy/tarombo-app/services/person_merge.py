"""Gabungkan record orang duplikat (nama + ayah + ibu sama)."""

from __future__ import annotations

from services.matching import person_identity_fields


def _score_person(row: dict) -> tuple:
    """Pilih kanonik: approved dulu, data lebih lengkap, id terkecil."""
    def filled(*keys):
        n = 0
        for k in keys:
            v = row.get(k)
            if v is not None and str(v).strip() not in ("", "Tidak Diketahui"):
                n += 1
        return n

    approved = 1 if row.get("approved") in (True, 1) else 0
    return (
        -approved,
        -filled(
            "birth_year",
            "child_order",
            "panggoaran",
            "spouse_name",
            "father_name",
            "mother_name",
        ),
        row.get("id") or 0,
    )


def pick_canonical_person(rows: list[dict]) -> dict:
    if not rows:
        raise ValueError("Tidak ada baris untuk dipilih.")
    return min(rows, key=_score_person)


def find_duplicate_identity_groups(db):
    """
    Kelompok duplikat: nama orang + nama ayah + nama ibu sama (bukan marga).
    Hanya jika ketiganya terisi — nama sama saja tanpa orang tua lengkap tidak digabung.
    """
    return db.fetchall(
        """
        SELECT
          name_normalized,
          COALESCE(father_name_normalized, '') AS father_name_normalized,
          COALESCE(mother_name_normalized, '') AS mother_name_normalized,
          MIN(full_name) AS sample_name,
          MIN(father_name) AS sample_father,
          MIN(mother_name) AS sample_mother,
          COUNT(*) AS total
        FROM people
        WHERE full_name != 'Tidak Diketahui'
          AND TRIM(name_normalized) != ''
          AND TRIM(COALESCE(father_name_normalized, '')) != ''
          AND TRIM(COALESCE(mother_name_normalized, '')) != ''
        GROUP BY name_normalized,
                 COALESCE(father_name_normalized, ''),
                 COALESCE(mother_name_normalized, '')
        HAVING COUNT(*) > 1
        ORDER BY total DESC, sample_name ASC
        """
    )


def _merge_scalar_fields(db, kept_id: int, dup: dict):
    """Isi kolom kanonik yang masih kosong dari duplikat."""
    kept = db.fetchone("SELECT * FROM people WHERE id = ?", (kept_id,))
    if not kept:
        return
    updates = {}
    for field in (
        "full_name",
        "marga",
        "birth_year",
        "child_order",
        "panggoaran",
        "panggoaran_type",
        "opung_source",
        "sundut",
        "spouse_name",
        "spouse_marga",
        "reference_female_line_name",
        "tarombo_status",
    ):
        kv = kept.get(field)
        dv = dup.get(field)
        if (kv is None or str(kv).strip() in ("", "Tidak Diketahui")) and dv not in (
            None,
            "",
        ):
            updates[field] = dv
    if dup.get("approved") in (True, 1) and kept.get("approved") not in (True, 1):
        updates["approved"] = dup.get("approved")
    if dup.get("sundut_locked") in (True, 1) and kept.get("sundut_locked") not in (True, 1):
        updates["sundut_locked"] = dup.get("sundut_locked")
        if kept.get("sundut") is None and dup.get("sundut") is not None:
            updates["sundut"] = dup.get("sundut")
    if not updates:
        return
    sets = ", ".join(f"{k} = ?" for k in updates)
    db.execute(
        f"UPDATE people SET {sets} WHERE id = ?",
        (*updates.values(), kept_id),
    )


def _rewire_relationships(db, kept_id: int, dup_id: int, insert_relationship_ignore):
    if kept_id == dup_id:
        return
    rels = db.fetchall(
        """
        SELECT source_person_id, target_person_id, relation_type
        FROM relationships
        WHERE source_person_id = ? OR target_person_id = ?
        """,
        (dup_id, dup_id),
    )
    for rel in rels:
        src = kept_id if rel["source_person_id"] == dup_id else rel["source_person_id"]
        tgt = kept_id if rel["target_person_id"] == dup_id else rel["target_person_id"]
        if src != tgt:
            insert_relationship_ignore(db, src, tgt, rel["relation_type"])
    db.execute(
        "DELETE FROM relationships WHERE source_person_id = ? OR target_person_id = ?",
        (dup_id, dup_id),
    )


def merge_person_into_canonical(db, kept_id: int, dup_id: int, *, insert_relationship_ignore) -> bool:
    if kept_id == dup_id:
        return False
    dup = db.fetchone("SELECT * FROM people WHERE id = ?", (dup_id,))
    if not dup:
        return False
    _merge_scalar_fields(db, kept_id, dup)
    _rewire_relationships(db, kept_id, dup_id, insert_relationship_ignore)
    db.execute(
        "UPDATE submitted_children SET parent_person_id = ? WHERE parent_person_id = ?",
        (kept_id, dup_id),
    )
    db.execute("DELETE FROM people WHERE id = ?", (dup_id,))
    return True


def merge_identity_group(db, rows: list[dict], *, insert_relationship_ignore) -> dict:
    if len(rows) < 2:
        return {"merged": 0, "kept_id": rows[0]["id"] if rows else None}
    canonical = pick_canonical_person(rows)
    kept_id = canonical["id"]
    merged = 0
    for row in rows:
        if row["id"] == kept_id:
            continue
        if merge_person_into_canonical(db, kept_id, row["id"], insert_relationship_ignore=insert_relationship_ignore):
            merged += 1
    return {"merged": merged, "kept_id": kept_id, "name": canonical.get("full_name")}


def merge_all_duplicate_people(db, *, insert_relationship_ignore) -> dict:
    """Gabungkan semua kelompok duplikat (nama + ayah + ibu sama)."""
    groups = find_duplicate_identity_groups(db)
    total_merged = 0
    groups_done = 0
    details = []
    for g in groups:
        rows = db.fetchall(
            """
            SELECT * FROM people
            WHERE name_normalized = ?
              AND COALESCE(father_name_normalized, '') = ?
              AND COALESCE(mother_name_normalized, '') = ?
            ORDER BY id ASC
            """,
            (
                g["name_normalized"],
                g["father_name_normalized"],
                g["mother_name_normalized"],
            ),
        )
        if len(rows) < 2:
            continue
        result = merge_identity_group(db, rows, insert_relationship_ignore=insert_relationship_ignore)
        total_merged += result["merged"]
        groups_done += 1
        details.append(
            {
                "name": result.get("name") or g.get("sample_name"),
                "kept_id": result["kept_id"],
                "merged": result["merged"],
            }
        )
    return {
        "groups": groups_done,
        "people_merged": total_merged,
        "details": details[:20],
    }


def backfill_mother_name_normalized(db):
    rows = db.fetchall(
        """
        SELECT id, full_name, marga, father_name, mother_name, birth_year
        FROM people
        WHERE mother_name_normalized IS NULL
           OR mother_name_normalized = ''
        """
    )
    for row in rows:
        ident = person_identity_fields(
            row["full_name"],
            row["marga"],
            row["father_name"],
            row.get("birth_year"),
            row.get("mother_name"),
        )
        db.execute(
            """
            UPDATE people SET
              name_normalized = ?,
              father_name_normalized = ?,
              mother_name_normalized = ?
            WHERE id = ?
            """,
            (
                ident["name_normalized"],
                ident["father_name_normalized"],
                ident["mother_name_normalized"],
                row["id"],
            ),
        )
    if rows:
        db.commit()


def delete_approved_people_not_in_tree(db, tree_ids: set[int], *, sql_approved_fn) -> dict:
    """
    Hapus record approved yang tidak muncul di pohon silsilah (duplikat / entri yatim).
    Pending dan placeholder 'Tidak Diketahui' tidak disentuh.
    """
    if not tree_ids:
        return {"deleted": 0, "deleted_names": [], "kept": 0}

    approved = db.fetchall(
        f"""
        SELECT id, full_name FROM people
        WHERE {sql_approved_fn(db)} AND full_name != 'Tidak Diketahui'
        ORDER BY id ASC
        """
    )
    to_delete = [p for p in approved if p["id"] not in tree_ids]
    deleted_names = []
    for row in to_delete:
        pid = row["id"]
        db.execute(
            "DELETE FROM relationships WHERE source_person_id = ? OR target_person_id = ?",
            (pid, pid),
        )
        db.execute("DELETE FROM submitted_children WHERE parent_person_id = ?", (pid,))
        db.execute("DELETE FROM people WHERE id = ?", (pid,))
        deleted_names.append(row["full_name"])

    if to_delete:
        db.commit()
    return {
        "deleted": len(to_delete),
        "deleted_names": deleted_names[:40],
        "kept": len(tree_ids),
    }


def reconcile_database_with_tree(db, *, insert_relationship_ignore, sql_approved_fn) -> dict:
    """Delegasi ke services.graph_sync.reconcile_tree."""
    from services.graph_sync import reconcile_tree

    return reconcile_tree(db, sql_approved_fn, insert_relationship_ignore)
