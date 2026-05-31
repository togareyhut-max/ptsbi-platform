"""Panggoaran Opung — gelar marga Samosir (Op. [cucu pertama] Doli / Boru)."""

from __future__ import annotations

from collections import defaultdict

from services.matching import normalize_name


def first_token(full_name: str) -> str:
    if not full_name:
        return ""
    return full_name.strip().split()[0]


from services.sibling_order import sort_person_ids  # noqa: F401 — urutan anak + tahun lahir


def _is_samosir_marga(marga: str | None) -> bool:
    return (marga or "").strip().lower() == "samosir"


def resolve_opung_grandchild(person_id, people_by_id, children_by_parent):
    """
    Menentukan cucu pertama untuk panggoaran Opung.

    Tetap (permanent):
      Cucu pertama dari anak laki-laki pertama (urutan saudara).

    Sementara (temporary) — hanya jika anak pertama orang ini perempuan:
      Cucu pertama dari anak perempuan pertama, sampai anak laki-laki pertama
      (adik laki-laki dari anak perempuan pertama) punya anak; cucu dari
      anak laki-laki pertama itulah yang menjadi acuan permanent.
    """
    child_ids = sort_person_ids(children_by_parent.get(person_id, []), people_by_id)
    if not child_ids:
        return None, None, None

    sons = [cid for cid in child_ids if people_by_id.get(cid, {}).get("gender") == "male"]
    first_child_id = child_ids[0]
    first_child = people_by_id.get(first_child_id, {})
    first_child_is_female = first_child.get("gender") == "female"

    if sons:
        first_son_id = sons[0]
        grandchild_ids = sort_person_ids(
            children_by_parent.get(first_son_id, []), people_by_id
        )
        if grandchild_ids:
            return grandchild_ids[0], "permanent", "son"

    if first_child_is_female:
        grandchild_ids = sort_person_ids(
            children_by_parent.get(first_child_id, []), people_by_id
        )
        if grandchild_ids:
            return grandchild_ids[0], "temporary", "daughter"

    return None, None, None


def build_panggoaran_label(gender: str, grandchild_first_name: str) -> str:
    """
    Laki-laki Samosir: Op. [nama depan cucu pertama] Doli
    Pasangannya: Op. [nama depan cucu pertama] Boru
    """
    depan = first_token(grandchild_first_name)
    if not depan:
        return ""
    if gender == "female":
        return f"Op. {depan} Boru"
    return f"Op. {depan} Doli"


def format_panggoaran_display(person: dict) -> str:
    pang = person.get("panggoaran") or ""
    if not pang:
        return ""
    if person.get("panggoaran_type") == "temporary":
        return f"{pang} (sementara)"
    return pang


def _male_name_index(people_by_id: dict[int, dict]) -> dict[str, list[int]]:
    idx: dict[str, list[int]] = defaultdict(list)
    for pid, person in people_by_id.items():
        if person.get("gender") != "male":
            continue
        nn = normalize_name(person.get("full_name") or "")
        if nn:
            idx[nn].append(pid)
    return idx


def _resolve_father_id_by_name(
    person: dict, people_by_id: dict[int, dict], male_idx: dict[str, list[int]]
) -> int | None:
    father_name = (person.get("father_name") or "").strip()
    if not father_name or father_name.lower() in {"tidak diketahui", "unknown", "-"}:
        return None
    nn = normalize_name(father_name)
    hits = male_idx.get(nn, [])
    if not hits:
        return None
    marga = (person.get("marga") or "").strip().lower()
    samosir_hits = [h for h in hits if _is_samosir_marga(people_by_id[h].get("marga"))]
    if len(samosir_hits) == 1:
        return samosir_hits[0]
    if len(hits) == 1:
        return hits[0]
    if marga == "samosir" and samosir_hits:
        return min(samosir_hits)
    return min(hits)


def _augment_children_from_father_names(
    people_by_id: dict[int, dict], children_by_parent: dict[int, list[int]]
) -> None:
    """Lengkapi anak–ayah dari kolom father_name (sama seperti pohon tarombo)."""
    male_idx = _male_name_index(people_by_id)
    for pid, person in people_by_id.items():
        father_id = _resolve_father_id_by_name(person, people_by_id, male_idx)
        if not father_id or father_id == pid:
            continue
        bucket = children_by_parent.setdefault(father_id, [])
        if pid not in bucket:
            bucket.append(pid)


def build_children_by_parent(
    people_by_id: dict[int, dict], relationships
) -> tuple[dict[int, int], dict[int, list[int]]]:
    """Pasangan + semua anak per orang (relasi DB + father_name), untuk hitung cucu pertama."""
    spouse_lookup: dict[int, int] = {}
    children_by_parent: dict[int, list[int]] = defaultdict(list)
    for rel in relationships:
        if rel["relation_type"] == "spouse":
            a, b = rel["source_person_id"], rel["target_person_id"]
            if a in people_by_id and b in people_by_id:
                spouse_lookup[a] = b
                spouse_lookup[b] = a
        elif rel["relation_type"] == "parent":
            src, tgt = rel["source_person_id"], rel["target_person_id"]
            if src in people_by_id and tgt in people_by_id:
                children_by_parent[src].append(tgt)

    _augment_children_from_father_names(people_by_id, children_by_parent)

    for parent_id, child_ids in list(children_by_parent.items()):
        seen: set[int] = set()
        deduped = []
        for cid in child_ids:
            if cid not in seen and cid in people_by_id:
                seen.add(cid)
                deduped.append(cid)
        children_by_parent[parent_id] = sort_person_ids(deduped, people_by_id)

    return spouse_lookup, dict(children_by_parent)


def build_relationship_maps(relationships):
    spouse_lookup = {}
    children_by_parent = {}
    for rel in relationships:
        if rel["relation_type"] == "spouse":
            spouse_lookup[rel["source_person_id"]] = rel["target_person_id"]
        elif rel["relation_type"] == "parent":
            children_by_parent.setdefault(rel["source_person_id"], []).append(
                rel["target_person_id"]
            )
    return spouse_lookup, children_by_parent


def _compute_panggoaran_label(
    person: dict,
    spouse: dict | None,
    people_by_id: dict[int, dict],
    children_by_parent: dict[int, list[int]],
    spouse_lookup: dict[int, int],
) -> tuple[str, str | None]:
    """Return (label tanpa suffix, panggoaran_type) atau ('', None)."""
    if not _eligible_for_opung_panggoaran(person, people_by_id, spouse_lookup):
        return "", None
    line_id = _opung_line_person_id(person, people_by_id, spouse_lookup)
    if not line_id:
        return "", None
    gc_id, ptype, _source = resolve_opung_grandchild(
        line_id, people_by_id, children_by_parent
    )
    if not gc_id or gc_id not in people_by_id:
        return "", None
    gc = people_by_id[gc_id]
    label = build_panggoaran_label(person["gender"], gc.get("full_name", ""))
    return label, ptype


def panggoaran_for_tree_card(
    person: dict,
    spouse: dict | None,
    people_by_id: dict[int, dict],
    children_by_parent: dict[int, list[int]],
    spouse_lookup: dict[int, int],
) -> str:
    """Gelar untuk kartu pohon: DB dulu, lalu hitung dari cucu pertama."""
    stored = format_panggoaran_display(person)
    if stored:
        return stored
    label, ptype = _compute_panggoaran_label(
        person, spouse, people_by_id, children_by_parent, spouse_lookup
    )
    if not label:
        return ""
    if ptype == "temporary":
        return f"{label} (sementara)"
    return label


def enrich_people_panggoaran_for_tree(people: list, relationships) -> list:
    """Isi panggoaran di memori agar gelar tampil di silsilah tanpa menunggu sinkron manual."""
    people_by_id = {p["id"]: dict(p) for p in people}
    spouse_lookup, children_by_parent = build_children_by_parent(people_by_id, relationships)
    for person in people_by_id.values():
        spouse_id = spouse_lookup.get(person["id"])
        spouse = people_by_id.get(spouse_id) if spouse_id else None
        label, ptype = _compute_panggoaran_label(
            person, spouse, people_by_id, children_by_parent, spouse_lookup
        )
        if label:
            person["panggoaran"] = label
            person["panggoaran_type"] = ptype
    return list(people_by_id.values())


def _eligible_for_opung_panggoaran(person: dict, people_by_id: dict, spouse_lookup: dict) -> bool:
    """Gelar Op. … Doli/Boru hanya untuk laki-laki Samosir dan pasangannya."""
    if person.get("gender") == "male" and _is_samosir_marga(person.get("marga")):
        return True
    spouse_id = spouse_lookup.get(person["id"])
    if not spouse_id:
        return False
    spouse = people_by_id.get(spouse_id)
    return bool(
        spouse
        and spouse.get("gender") == "male"
        and _is_samosir_marga(spouse.get("marga"))
    )


def _opung_line_person_id(person: dict, people_by_id: dict, spouse_lookup: dict) -> int | None:
    """Orang yang garis keturunannya dipakai untuk cucu pertama (suami Samosir, bukan pasangan)."""
    if person.get("gender") == "male" and _is_samosir_marga(person.get("marga")):
        return person["id"]
    spouse_id = spouse_lookup.get(person["id"])
    if not spouse_id:
        return None
    spouse = people_by_id.get(spouse_id)
    if spouse and spouse.get("gender") == "male" and _is_samosir_marga(spouse.get("marga")):
        return spouse_id
    return None


def recalculate_all_panggoaran(db, people, relationships):
    """Hitung ulang panggoaran laki-laki Samosir dan pasangan (istri/suami)."""
    people_by_id = {p["id"]: dict(p) for p in people}
    spouse_lookup, children_by_parent = build_children_by_parent(people_by_id, relationships)
    now_expr = "NOW()" if db.backend == "postgres" else "CURRENT_TIMESTAMP"

    for person_id, person in people_by_id.items():
        if not _eligible_for_opung_panggoaran(person, people_by_id, spouse_lookup):
            db.execute(
                f"""
                UPDATE people SET panggoaran = NULL, panggoaran_type = NULL,
                  opung_source = NULL, last_synced_at = {now_expr}
                WHERE id = ?
                """,
                (person_id,),
            )
            continue

        line_id = _opung_line_person_id(person, people_by_id, spouse_lookup)
        if not line_id:
            continue
        gc_id, ptype, source = resolve_opung_grandchild(
            line_id, people_by_id, children_by_parent
        )
        if not gc_id or gc_id not in people_by_id:
            db.execute(
                f"""
                UPDATE people SET panggoaran = NULL, panggoaran_type = NULL,
                  opung_source = NULL, last_synced_at = {now_expr}
                WHERE id = ?
                """,
                (person_id,),
            )
            continue

        gc = people_by_id[gc_id]
        label = build_panggoaran_label(person["gender"], gc["full_name"])
        db.execute(
            f"""
            UPDATE people SET panggoaran = ?, panggoaran_type = ?, opung_source = ?,
              last_synced_at = {now_expr}
            WHERE id = ?
            """,
            (label, ptype, source, person_id),
        )
        people_by_id[person_id]["panggoaran"] = label
        people_by_id[person_id]["panggoaran_type"] = ptype

        spouse_id = spouse_lookup.get(person_id)
        if spouse_id and spouse_id in people_by_id:
            spouse = people_by_id[spouse_id]
            if _eligible_for_opung_panggoaran(spouse, people_by_id, spouse_lookup):
                spouse_label = build_panggoaran_label(spouse["gender"], gc["full_name"])
                db.execute(
                    f"""
                    UPDATE people SET panggoaran = ?, panggoaran_type = ?, opung_source = ?,
                      last_synced_at = {now_expr}
                    WHERE id = ?
                    """,
                    (spouse_label, ptype, source, spouse_id),
                )
                people_by_id[spouse_id]["panggoaran"] = spouse_label
                people_by_id[spouse_id]["panggoaran_type"] = ptype

    db.commit()
    return people_by_id


def get_opung_hover_suffix(
    person,
    spouse,
    people_by_id,
    children_by_parent,
    spouse_lookup=None,
):
    """Garis gelar di pohon (sama dengan teks di kartu)."""
    if spouse_lookup is None:
        spouse_lookup = {}
        if spouse and spouse.get("id"):
            spouse_lookup[person["id"]] = spouse["id"]
            spouse_lookup[spouse["id"]] = person["id"]
    return panggoaran_for_tree_card(
        person, spouse, people_by_id, children_by_parent, spouse_lookup
    )
