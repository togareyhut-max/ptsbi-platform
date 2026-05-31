"""Urutan anak (sibling order) — manual + fallback tahun lahir."""

from __future__ import annotations


def _int_or_none(value) -> int | None:
    if value is None or value == "":
        return None
    try:
        return int(value)
    except (TypeError, ValueError):
        return None


def sibling_sort_key(person: dict) -> tuple:
    """
    Urutan tampilan: child_order eksplisit dulu, lalu tahun lahir, lalu id.
    Tanpa urutan/tahun → di akhir (bisa diisi belakangan).
    """
    order = _int_or_none(person.get("child_order"))
    year = _int_or_none(person.get("birth_year"))
    pid = person.get("id") or 0
    return (
        0 if order is not None else 1,
        order if order is not None else 9999,
        0 if year is not None else 1,
        year if year is not None else 9999,
        pid,
    )


def sort_people_list(people: list[dict]) -> list[dict]:
    return sorted(people, key=sibling_sort_key)


def sort_person_ids(person_ids, people_by_id) -> list:
    def key_fn(pid):
        p = people_by_id.get(pid) or {}
        return sibling_sort_key(p)

    return sorted(person_ids, key=key_fn)


def parse_child_order(raw) -> int | None:
    if raw is None:
        return None
    text = str(raw).strip()
    if not text or not text.isdigit():
        return None
    val = int(text)
    return val if val > 0 else None


def find_father_person(db, person: dict, get_person_by_name):
    """Cari record ayah (laki-laki) dari nama ayah anak."""
    father_name = (person.get("father_name") or "").strip()
    if not father_name or father_name.lower() in {"tidak diketahui", "unknown", "-"}:
        return None
    marga = (person.get("marga") or "Samosir").strip()
    row = get_person_by_name(db, father_name, marga)
    if row and row.get("gender") == "male":
        return row
    row = get_person_by_name(db, father_name, "Samosir")
    if row and row.get("gender") == "male":
        return row
    return row


def get_siblings_by_father_name(db, person: dict, sql_approved_fn) -> list[dict]:
    """Semua anak dengan ayah yang sama (termasuk orang ini)."""
    fn = person.get("father_name_normalized") or ""
    if not fn:
        return []
    return db.fetchall(
        f"""
        SELECT *
        FROM people
        WHERE father_name_normalized = ?
          AND {sql_approved_fn(db)}
          AND full_name != 'Tidak Diketahui'
        """,
        (fn,),
    )


def get_siblings_for_person(db, person_id: int, sql_approved_fn, get_person_by_name) -> list[dict]:
    person = db.fetchone("SELECT * FROM people WHERE id = ?", (person_id,))
    if not person:
        return []
    siblings = get_siblings_by_father_name(db, person, sql_approved_fn)
    return sort_people_list([dict(s) for s in siblings])


def assign_orders_from_birth_year(siblings: list[dict]) -> dict[int, int]:
    """
    Isi urutan otomatis dari tahun lahir (hanya yang belum punya child_order).
    Yang sudah punya urutan manual tidak diubah.
    """
    without_order = [s for s in siblings if _int_or_none(s.get("child_order")) is None]
    with_year = [s for s in without_order if _int_or_none(s.get("birth_year")) is not None]
    without_year = [s for s in without_order if _int_or_none(s.get("birth_year")) is None]

    with_year.sort(key=lambda s: (_int_or_none(s.get("birth_year")), s.get("id", 0)))

    used = {_int_or_none(s.get("child_order")) for s in siblings if _int_or_none(s.get("child_order"))}
    next_slot = 1
    while next_slot in used:
        next_slot += 1

    updates: dict[int, int] = {}
    for s in with_year + without_year:
        while next_slot in used:
            next_slot += 1
        updates[s["id"]] = next_slot
        used.add(next_slot)
        next_slot += 1
    return updates


def apply_child_orders(db, order_by_id: dict[int, int]):
    for pid, order in order_by_id.items():
        db.execute("UPDATE people SET child_order = ? WHERE id = ?", (order, pid))


def order_label(person: dict) -> str | None:
    n = _int_or_none(person.get("child_order"))
    if n is None:
        return None
    return f"Anak ke-{n}"
