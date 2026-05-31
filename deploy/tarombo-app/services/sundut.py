"""Sundut (generasi) — berurutan per lapisan: tiap turunan patrilineal = sundut ayah + 1.

Sundut diisi manual per orang (admin / pendaftar leluhur). Sinkron hanya melengkapi
keturunan di bawah (+1) tanpa menimpa nilai manual atau baris ber-kunci (sundut_locked).
"""

from __future__ import annotations

_ROMAN = (
    "",
    "I",
    "II",
    "III",
    "IV",
    "V",
    "VI",
    "VII",
    "VIII",
    "IX",
    "X",
    "XI",
    "XII",
    "XIII",
    "XIV",
    "XV",
    "XVI",
    "XVII",
    "XVIII",
    "XIX",
    "XX",
    "XXI",
    "XXII",
    "XXIII",
    "XXIV",
    "XXV",
    "XXVI",
    "XXVII",
    "XXVIII",
    "XXIX",
    "XXX",
)


def _roman(n: int) -> str:
    if 0 < n < len(_ROMAN):
        return _ROMAN[n]
    return str(n)


def _default_entry(n: int) -> tuple[int, str, str]:
    if n == 1:
        return (1, "Sundut I — Leluhur", "Generasi tertinggi (nomor terkecil di puncak pohon).")
    return (n, f"Sundut {_roman(n)}", f"Keturunan tingkat {n}.")


DEFAULT_SUNDUT_ENTRIES = [_default_entry(n) for n in range(1, 9)]


def ensure_sundut_catalog(db, *, max_number: int = 30) -> None:
    """Pastikan entri Sundut I … XXX ada di tabel (untuk label kartu pohon)."""
    for n in range(1, max_number + 1):
        exists = db.fetchone("SELECT id FROM sundut_entries WHERE sundut_number = ?", (n,))
        if exists:
            continue
        num, title, desc = _default_entry(n)
        db.execute(
            """
            INSERT INTO sundut_entries (sundut_number, title, description)
            VALUES (?, ?, ?)
            """,
            (num, title, desc),
        )
    db.commit()


def seed_sundut_entries(db):
    row = db.fetchone("SELECT COUNT(*) AS c FROM sundut_entries")
    if row and int(row["c"]) > 0:
        ensure_sundut_catalog(db)
        return
    for number, title, description in DEFAULT_SUNDUT_ENTRIES:
        db.execute(
            """
            INSERT INTO sundut_entries (sundut_number, title, description)
            VALUES (?, ?, ?)
            """,
            (number, title, description),
        )
    ensure_sundut_catalog(db)


def list_sundut_entries(db):
    ensure_sundut_catalog(db)
    return db.fetchall("SELECT * FROM sundut_entries ORDER BY sundut_number ASC")


def sundut_title_map(db) -> dict[int, str]:
    rows = list_sundut_entries(db)
    return {int(r["sundut_number"]): r["title"] for r in rows}


def effective_sundut(person: dict, fallback_map: dict | None = None) -> int | None:
    if person.get("sundut") is not None:
        try:
            return int(person["sundut"])
        except (TypeError, ValueError):
            pass
    if fallback_map:
        return fallback_map.get(person["id"])
    return None


def _fetch_approved_people(db, sql_approved_fn):
    return db.fetchall(
        f"""
        SELECT id, full_name, name_normalized, gender, marga, birth_year,
               father_name, mother_name, spouse_name, spouse_marga,
               sundut, sundut_locked, panggoaran
        FROM people
        WHERE {sql_approved_fn(db)} AND full_name != 'Tidak Diketahui'
        ORDER BY id ASC
        """
    )


def build_patrilineal_children_map(db, sql_approved_fn):
    """Peta anak per ayah (relasi DB + kolom father_name), selaras dengan pohon."""
    from services.tree_display import (
        augment_children_from_father_names,
        build_father_of,
        build_lineage_maps,
    )

    people = _fetch_approved_people(db, sql_approved_fn)
    if not people:
        return {}, {}, set(), {}

    people_by_id = {p["id"]: dict(p) for p in people}
    allowed = set(people_by_id.keys())
    rels = db.fetchall(
        """
        SELECT source_person_id, target_person_id, relation_type
        FROM relationships
        WHERE relation_type IN ('parent', 'spouse')
        """
    )
    rels = [
        r
        for r in rels
        if r["source_person_id"] in allowed and r["target_person_id"] in allowed
    ]
    spouse_lookup, children_by_father = build_lineage_maps(rels, people_by_id)
    augment_children_from_father_names(people_by_id, children_by_father)
    father_of = build_father_of(children_by_father)
    child_ids = set(father_of.keys())
    return children_by_father, people_by_id, child_ids, father_of


def _father_chain_has_sundut(
    person_id: int, father_of: dict[int, int], people_by_id: dict[int, dict]
) -> bool:
    seen: set[int] = set()
    current = person_id
    while current in father_of:
        if current in seen:
            break
        seen.add(current)
        father_id = father_of[current]
        father = people_by_id.get(father_id)
        if father and father.get("sundut") is not None:
            return True
        current = father_id
    return False


def _propagate_from_anchor(
    anchor_id: int,
    base_sundut: int,
    children_by_father: dict[int, list[int]],
    people_by_id: dict[int, dict],
    set_sundut,
) -> int:
    """
    Turun garis ayah: anak = sundut induk + 1.
    Nilai sundut manual pada anak dihormati (lanjut BFS dari angka itu).
    sundut_locked tidak ditimpa.
    """
    updated = 0
    queue = [(anchor_id, base_sundut)]
    seen: set[int] = set()
    while queue:
        parent_id, parent_gen = queue.pop(0)
        if parent_id in seen:
            continue
        seen.add(parent_id)
        for child_id in children_by_father.get(parent_id, []):
            if child_id not in people_by_id:
                continue
            child = people_by_id[child_id]
            next_gen = parent_gen + 1

            if child.get("sundut_locked"):
                if child.get("sundut") is not None:
                    try:
                        queue.append((child_id, int(child["sundut"])))
                    except (TypeError, ValueError):
                        pass
                continue

            if child.get("sundut") is not None:
                try:
                    manual = int(child["sundut"])
                    queue.append((child_id, manual))
                    continue
                except (TypeError, ValueError):
                    pass

            if set_sundut(child_id, next_gen):
                updated += 1
            queue.append((child_id, next_gen))
    return updated


def _sync_spouse_sundut(db, people_by_id: dict[int, dict], sql_approved_fn) -> int:
    """Pasangan boru: sundut sama dengan suami (generasi yang sama di pohon)."""
    rels = db.fetchall(
        f"""
        SELECT r.source_person_id, r.target_person_id
        FROM relationships r
        JOIN people p ON p.id = r.source_person_id AND {sql_approved_fn(db, 'p')}
        JOIN people w ON w.id = r.target_person_id AND {sql_approved_fn(db, 'w')}
        WHERE r.relation_type = 'spouse' AND p.gender = 'male'
        """
    )
    updated = 0
    for rel in rels:
        husband_id = rel["source_person_id"]
        wife_id = rel["target_person_id"]
        husband = people_by_id.get(husband_id)
        wife = people_by_id.get(wife_id)
        if not husband or not wife:
            continue
        try:
            hs = int(husband["sundut"]) if husband.get("sundut") is not None else None
        except (TypeError, ValueError):
            hs = None
        if hs is None or wife.get("sundut_locked") or wife.get("sundut") is not None:
            continue
        if wife.get("sundut") != hs:
            db.execute("UPDATE people SET sundut = ? WHERE id = ?", (hs, wife_id))
            wife["sundut"] = hs
            updated += 1
    return updated


def sync_sundut_from_anchors(db, sql_approved_fn, *, assign_roots: bool = True) -> dict:
    """
    Setiap orang yang sudah punya sundut (manual) = titik acuan.
    Propagasi +1 ke bawah lewat ayah; generasi di atas diisi manual nanti.
    """
    ensure_sundut_catalog(db)
    children_by_father, people_by_id, child_ids, father_of = build_patrilineal_children_map(
        db, sql_approved_fn
    )
    if not people_by_id:
        return {"updated": 0, "anchors": 0, "spouses": 0, "message": "Tidak ada data."}

    people = list(people_by_id.values())
    updated = 0

    def set_sundut(person_id, value: int) -> bool:
        person = people_by_id.get(person_id)
        if not person or person.get("sundut_locked"):
            return False
        if person.get("sundut") == value:
            return False
        db.execute("UPDATE people SET sundut = ? WHERE id = ?", (value, person_id))
        person["sundut"] = value
        return True

    anchors = [p for p in people if p.get("sundut") is not None]
    anchors.sort(
        key=lambda p: (int(p["sundut"]) if p.get("sundut") is not None else 9999, p["id"])
    )

    for anchor in anchors:
        try:
            base = int(anchor["sundut"])
        except (TypeError, ValueError):
            continue
        updated += _propagate_from_anchor(
            anchor["id"], base, children_by_father, people_by_id, set_sundut
        )

    if assign_roots and not anchors:
        min_entry = db.fetchone("SELECT MIN(sundut_number) AS m FROM sundut_entries")
        root_sundut = int(min_entry["m"]) if min_entry and min_entry["m"] is not None else 1
        for person in people:
            pid = person["id"]
            if pid in child_ids:
                continue
            if person.get("sundut_locked") or person.get("sundut") is not None:
                continue
            if _father_chain_has_sundut(pid, father_of, people_by_id):
                continue
            if set_sundut(pid, root_sundut):
                updated += 1
            updated += _propagate_from_anchor(
                pid, root_sundut, children_by_father, people_by_id, set_sundut
            )

    spouse_updated = _sync_spouse_sundut(db, people_by_id, sql_approved_fn)
    updated += spouse_updated

    if updated:
        db.commit()

    anchor_names = [
        f"{a.get('full_name')} (Sundut {a.get('sundut')})" for a in anchors[:6]
    ]

    return {
        "updated": updated,
        "anchors": len(anchors),
        "spouses": spouse_updated,
        "anchor_names": anchor_names,
        "message": (
            f"{updated} orang diperbarui; {len(anchors)} titik acuan manual"
            + (f" ({', '.join(anchor_names[:3])}…)" if anchor_names else "")
        ),
    }


def run_sundut_sync(db, sql_approved_fn, *, rebuild_relationships_fn=None) -> dict:
    """Rebuild relasi ayah (opsional) lalu propagasi sundut +1 dari semua nilai manual."""
    if rebuild_relationships_fn:
        rebuild_relationships_fn(db)
    ensure_sundut_catalog(db)
    has_manual = db.fetchone(
        f"""
        SELECT id FROM people
        WHERE {sql_approved_fn(db)} AND sundut IS NOT NULL
        LIMIT 1
        """
    )
    result = sync_sundut_from_anchors(
        db, sql_approved_fn, assign_roots=(has_manual is None)
    )
    return result
