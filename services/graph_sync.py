"""Satu pintu masuk: sinkron graf silsilah, panggoaran, sundut, dan penempatan anggota baru."""

from __future__ import annotations

from services.person_merge import (
    backfill_mother_name_normalized,
    delete_approved_people_not_in_tree,
    merge_all_duplicate_people,
)
from services.sundut import ensure_sundut_catalog, sync_sundut_from_anchors


def _app_hooks():
    from app import (  # noqa: PLC0415
        backfill_normalized_names,
        build_collapsible_tree,
        fix_spouse_boru_status,
        rebuild_all_parent_relationships,
        recalculate_all_panggoaran,
    )

    return (
        backfill_normalized_names,
        rebuild_all_parent_relationships,
        fix_spouse_boru_status,
        recalculate_all_panggoaran,
        build_collapsible_tree,
    )


def sync_tarombo(
    db,
    sql_approved_fn,
    insert_relationship_ignore,
    *,
    backfill: bool = True,
    merge: bool = False,
    purge: bool = False,
    relationships: bool = True,
    panggoaran: bool = True,
    sundut: bool = True,
) -> dict:
    """
    Sinkron tarombo: relasi ayah, panggoaran, sundut (+1 dari nilai manual).

    merge / purge hanya jika diminta (tombol Gabung & Bersihkan).
    """
    (
        backfill_normalized_names,
        rebuild_all_parent_relationships,
        fix_spouse_boru_status,
        recalculate_all_panggoaran,
        build_collapsible_tree,
    ) = _app_hooks()

    out = {"merge": None, "purge": None, "sundut": None}

    if backfill:
        backfill_normalized_names(db)
        backfill_mother_name_normalized(db)

    if merge:
        out["merge"] = merge_all_duplicate_people(
            db, insert_relationship_ignore=insert_relationship_ignore
        )

    if relationships:
        rebuild_all_parent_relationships(db)
        fix_spouse_boru_status(db)

    if panggoaran:
        people = db.fetchall(
            f"""
            SELECT id, full_name, marga, gender, birth_year, tarombo_status, father_name, mother_name,
                   spouse_name, spouse_marga, submitted_by, panggoaran, panggoaran_type
            FROM people
            WHERE {sql_approved_fn(db)} AND full_name != 'Tidak Diketahui'
            ORDER BY id ASC
            """
        )
        rels = db.fetchall(
            "SELECT source_person_id, target_person_id, relation_type FROM relationships"
        )
        recalculate_all_panggoaran(db, people, rels)

    if sundut:
        ensure_sundut_catalog(db)
        has_manual = db.fetchone(
            f"""
            SELECT id FROM people
            WHERE {sql_approved_fn(db)} AND sundut IS NOT NULL
            LIMIT 1
            """
        )
        out["sundut"] = sync_sundut_from_anchors(
            db, sql_approved_fn, assign_roots=(has_manual is None)
        )

    if purge:
        from services.tree_display import collect_tree_person_ids

        roots = build_collapsible_tree(db, sync=False)
        tree_ids = collect_tree_person_ids(roots)
        out["purge"] = delete_approved_people_not_in_tree(
            db, tree_ids, sql_approved_fn=sql_approved_fn
        )
        if out["purge"].get("deleted"):
            rebuild_all_parent_relationships(db)
            fix_spouse_boru_status(db)
            people = db.fetchall(
                f"""
                SELECT id, full_name, marga, gender, birth_year, tarombo_status, father_name,
                       mother_name, spouse_name, spouse_marga, submitted_by, panggoaran, panggoaran_type
                FROM people
                WHERE {sql_approved_fn(db)} AND full_name != 'Tidak Diketahui'
                """
            )
            rels = db.fetchall(
                "SELECT source_person_id, target_person_id, relation_type FROM relationships"
            )
            recalculate_all_panggoaran(db, people, rels)
            has_manual = db.fetchone(
                f"""
                SELECT id FROM people
                WHERE {sql_approved_fn(db)} AND sundut IS NOT NULL
                LIMIT 1
                """
            )
            out["sundut"] = sync_sundut_from_anchors(
                db, sql_approved_fn, assign_roots=(has_manual is None)
            )

    return out


def place_person_in_tree(db, person_id: int, *, promote_children: bool = True) -> None:
    """Hubungkan anggota baru ke silsilah (ayah/ibu/pasangan/anak) lalu sinkron tampilan."""
    from app import (  # noqa: PLC0415
        build_relationships_for_person,
        create_children_for_person,
        insert_relationship_ignore,
        sql_approved,
    )

    build_relationships_for_person(db, person_id)
    if promote_children:
        create_children_for_person(db, person_id)
    db.commit()
    sync_tarombo(
        db,
        sql_approved,
        insert_relationship_ignore,
        backfill=False,
        merge=False,
        purge=False,
    )


def reconcile_tree(db, sql_approved_fn, insert_relationship_ignore) -> dict:
    """Gabung duplikat identitas → sinkron → hapus entri di luar pohon → sinkron ulang."""
    from services.tree_display import collect_tree_person_ids

    backfill_mother_name_normalized(db)
    merge_result = merge_all_duplicate_people(
        db, insert_relationship_ignore=insert_relationship_ignore
    )
    sync_tarombo(
        db,
        sql_approved_fn,
        insert_relationship_ignore,
        backfill=True,
        merge=False,
        purge=False,
    )
    _, _, _, _, build_collapsible_tree = _app_hooks()
    roots = build_collapsible_tree(db, sync=False)
    tree_ids = collect_tree_person_ids(roots)
    purge_result = delete_approved_people_not_in_tree(
        db, tree_ids, sql_approved_fn=sql_approved_fn
    )
    if purge_result.get("deleted"):
        sync_tarombo(
            db,
            sql_approved_fn,
            insert_relationship_ignore,
            backfill=False,
            merge=False,
            purge=False,
        )
        roots = build_collapsible_tree(db, sync=False)
        tree_ids = collect_tree_person_ids(roots)
    return {
        "merge": merge_result,
        "purge": purge_result,
        "tree_people": len(tree_ids),
    }
