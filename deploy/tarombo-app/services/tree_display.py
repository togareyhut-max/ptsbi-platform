"""Pohon tarombo: satu orang = satu node, garis patrilineal (anak), pasangan di samping."""

from __future__ import annotations

from collections import defaultdict

from services.batak_names import format_person_display_name, format_spouse_display_name
from services.matching import normalize_name
from services.opung import sort_person_ids
from services.opung import (
    build_children_by_parent,
    panggoaran_for_tree_card,
)
from services.partuturan import effective_partuturan_code, partuturan_detail
from services.sibling_order import order_label


def _infer_marga_from_name(full_name: str) -> str:
    if not full_name:
        return ""
    parts = [p for p in full_name.strip().split() if p]
    return parts[-1] if parts else ""


def _invalid_spouse_name(name: str) -> bool:
    return not name or name.strip().lower() in {"tidak diketahui", "unknown", "-"}


def _is_samosir_marga(marga: str | None) -> bool:
    return (marga or "").strip().lower() == "samosir"


def build_spouse_lookup(people_by_id, relationships) -> dict[int, int]:
    """Pasangan dua arah: dari relasi DB + field spouse_name/spouse_marga."""
    spouse_lookup: dict[int, int] = {}
    for rel in relationships:
        if rel["relation_type"] != "spouse":
            continue
        a, b = rel["source_person_id"], rel["target_person_id"]
        if a in people_by_id and b in people_by_id:
            spouse_lookup[a] = b
            spouse_lookup[b] = a

    by_name_marga: dict[tuple[str, str], list[int]] = {}
    by_name: dict[str, list[int]] = {}
    for pid, person in people_by_id.items():
        nn = normalize_name(person.get("full_name") or "")
        marga = (person.get("marga") or "").strip().lower()
        by_name_marga.setdefault((nn, marga), []).append(pid)
        by_name.setdefault(nn, []).append(pid)

    def resolve_spouse_id(spouse_name: str, spouse_marga: str | None) -> int | None:
        nn = normalize_name(spouse_name)
        marga = (spouse_marga or _infer_marga_from_name(spouse_name) or "").strip().lower()
        if marga:
            hits = by_name_marga.get((nn, marga), [])
            if len(hits) == 1:
                return hits[0]
        hits = by_name.get(nn, [])
        if len(hits) == 1:
            return hits[0]
        return None

    for pid, person in people_by_id.items():
        if spouse_lookup.get(pid) in people_by_id:
            continue
        spouse_name = (person.get("spouse_name") or "").strip()
        if _invalid_spouse_name(spouse_name):
            continue
        spouse_marga = person.get("spouse_marga")
        sid = resolve_spouse_id(spouse_name, spouse_marga)
        if sid and sid != pid:
            spouse_lookup[pid] = sid
            spouse_lookup[sid] = pid

    return spouse_lookup


def build_lineage_maps(relationships, people_by_id):
    """Hanya relasi parent dari ayah (laki-laki) untuk turunan pohon."""
    spouse_lookup = build_spouse_lookup(people_by_id, relationships)
    children_by_father: dict[int, list[int]] = {}
    for rel in relationships:
        if rel["relation_type"] == "parent":
            parent = people_by_id.get(rel["source_person_id"])
            if parent and parent.get("gender") == "male":
                children_by_father.setdefault(rel["source_person_id"], []).append(
                    rel["target_person_id"]
                )
    augment_children_from_father_names(people_by_id, children_by_father)
    for father_id, child_ids in list(children_by_father.items()):
        deduped = []
        seen_c: set[int] = set()
        for cid in child_ids:
            if cid not in seen_c and cid in people_by_id:
                seen_c.add(cid)
                deduped.append(cid)
        children_by_father[father_id] = deduped
    return spouse_lookup, children_by_father


def _male_name_index(people_by_id: dict[int, dict]) -> dict[str, list[int]]:
    idx: dict[str, list[int]] = defaultdict(list)
    for pid, person in people_by_id.items():
        if person.get("gender") != "male":
            continue
        nn = normalize_name(person.get("full_name") or "")
        if nn:
            idx[nn].append(pid)
    return idx


def resolve_father_id_by_name(person: dict, people_by_id: dict[int, dict], male_idx: dict[str, list[int]]) -> int | None:
    """Cocokkan father_name ke record ayah laki-laki di DB."""
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


def augment_children_from_father_names(people_by_id: dict[int, dict], children_by_father: dict[int, list[int]]) -> None:
    """Lengkapi anak–ayah dari kolom father_name jika edge DB belum ada."""
    male_idx = _male_name_index(people_by_id)
    for pid, person in people_by_id.items():
        father_id = resolve_father_id_by_name(person, people_by_id, male_idx)
        if not father_id or father_id == pid:
            continue
        bucket = children_by_father.setdefault(father_id, [])
        if pid not in bucket:
            bucket.append(pid)


def build_father_of(children_by_father) -> dict[int, int]:
    father_of: dict[int, int] = {}
    for father_id, child_ids in children_by_father.items():
        for child_id in child_ids:
            father_of[child_id] = father_id
    return father_of


def patrilineal_components(father_of: dict[int, int]) -> list[set[int]]:
    """Komponen terhubung pada graf ayah–anak (garis patrilineal)."""
    adj: dict[int, set[int]] = defaultdict(set)
    nodes: set[int] = set()
    for child_id, father_id in father_of.items():
        nodes.add(child_id)
        nodes.add(father_id)
        adj[child_id].add(father_id)
        adj[father_id].add(child_id)

    components: list[set[int]] = []
    seen: set[int] = set()
    for start in nodes:
        if start in seen:
            continue
        stack = [start]
        comp: set[int] = set()
        while stack:
            node = stack.pop()
            if node in seen:
                continue
            seen.add(node)
            comp.add(node)
            for nb in adj[node]:
                if nb not in seen:
                    stack.append(nb)
        components.append(comp)
    return components


def stub_spouse_from_fields(person):
    """Tampilkan pasangan di pohon meski belum ada record orang terpisah."""
    spouse_name = (person.get("spouse_name") or "").strip()
    spouse_marga = (person.get("spouse_marga") or _infer_marga_from_name(spouse_name) or "").strip()
    if _invalid_spouse_name(spouse_name):
        return None
    stub_person = {
        "full_name": spouse_name,
        "gender": "female" if person.get("gender") == "male" else "male",
        "marga": spouse_marga or "Samosir",
        "tarombo_status": "",
        "spouse_marga": person.get("marga"),
    }
    stub_person["tarombo_status"] = effective_partuturan_code(stub_person, person)
    display = format_spouse_display_name(stub_person, person)
    pt = partuturan_detail(stub_person, person)
    return {
        "id": f"spouse-ref-{person['id']}",
        "name": display,
        "title": display,
        "panggoaran": None,
        "birth_year": None,
        "gender": "female" if person.get("gender") == "male" else "male",
        "marga": spouse_marga or None,
        "is_spouse_stub": True,
        "partuturan": pt,
    }


def is_couple_only_person(person_id, people_by_id, spouse_lookup) -> bool:
    """
    Perempuan yang tampil hanya di samping suami Samosir (boru), bukan node pohon sendiri.
    Contoh: istri Henri Samosir — Jerlin Sinurat.
    """
    person = people_by_id.get(person_id)
    if not person or person.get("gender") != "female":
        return False
    spouse_id = spouse_lookup.get(person_id)
    spouse = people_by_id.get(spouse_id) if spouse_id else None
    if spouse and spouse.get("gender") == "male" and _is_samosir_marga(spouse.get("marga")):
        return True
    if person.get("tarombo_status") in ("boru", "spouse_of_boru"):
        spouse_name = (person.get("spouse_name") or "").strip()
        if spouse_name and not _invalid_spouse_name(spouse_name):
            nn = normalize_name(spouse_name)
            hits = _male_name_index(people_by_id).get(nn, [])
            for sid in hits:
                if _is_samosir_marga(people_by_id.get(sid, {}).get("marga")):
                    return True
    return False


def _has_patrilineal_father(person_id: int, people_by_id: dict[int, dict], male_idx: dict[str, list[int]]) -> bool:
    person = people_by_id.get(person_id)
    if not person:
        return False
    return resolve_father_id_by_name(person, people_by_id, male_idx) is not None


def _is_tree_root_candidate(person_id, people_by_id, spouse_lookup) -> bool:
    person = people_by_id.get(person_id)
    if not person:
        return False
    if person.get("full_name") == "Tidak Diketahui":
        return False
    if is_couple_only_person(person_id, people_by_id, spouse_lookup):
        return False
    if person.get("gender") != "male":
        return False
    return _is_samosir_marga(person.get("marga"))


def patrilineal_top_ancestor(person_id: int, father_of: dict[int, int]) -> int:
    """Naik garis ayah sampai leluhur tertinggi dalam komponen."""
    seen: set[int] = set()
    current = person_id
    while current in father_of:
        if current in seen:
            break
        seen.add(current)
        current = father_of[current]
    return current


def _root_sort_key(pid: int, people_by_id: dict) -> tuple:
    """Sundut terkecil / tahun lahir terawal = generasi tertua di atas pohon."""
    person = people_by_id.get(pid) or {}
    sundut = person.get("sundut")
    try:
        sundut_key = int(sundut) if sundut is not None else 9999
    except (TypeError, ValueError):
        sundut_key = 9999
    birth = person.get("birth_year")
    try:
        birth_key = int(birth) if birth is not None else 9999
    except (TypeError, ValueError):
        birth_key = 9999
    return (sundut_key, birth_key, pid)


def pick_lineage_roots(people, children_by_father, people_by_id, spouse_lookup):
    """
    Satu akar per komponen patrilineal = leluhur tertinggi (mis. Op. … Doli / Boru di puncak).
    Beberapa akar hanya jika garis keluarga benar-benar terpisah di data.
    """
    father_of = build_father_of(children_by_father)
    components = patrilineal_components(father_of)
    root_ids: list[int] = []
    seen_roots: set[int] = set()

    for comp in components:
        candidates = [
            pid for pid in comp if _is_tree_root_candidate(pid, people_by_id, spouse_lookup)
        ]
        if not candidates:
            continue
        # Satu akar per komponen — saudara tidak boleh jadi pohon terpisah (vertikal)
        top_ids = {patrilineal_top_ancestor(pid, father_of) for pid in candidates}
        eligible = [tid for tid in top_ids if _is_tree_root_candidate(tid, people_by_id, spouse_lookup)]
        if not eligible:
            eligible = list(candidates)
        best_root = min(eligible, key=lambda pid: _root_sort_key(pid, people_by_id))
        if best_root not in seen_roots:
            seen_roots.add(best_root)
            root_ids.append(best_root)

    male_idx = _male_name_index(people_by_id)
    graph_nodes = set(father_of.keys()) | set(father_of.values())
    for person in people:
        pid = person["id"]
        if pid in seen_roots or pid in graph_nodes:
            continue
        if _has_patrilineal_father(pid, people_by_id, male_idx):
            continue
        if _is_tree_root_candidate(pid, people_by_id, spouse_lookup):
            seen_roots.add(pid)
            root_ids.append(pid)

    if not root_ids:
        for person in people:
            pid = person["id"]
            if pid in seen_roots or is_couple_only_person(pid, people_by_id, spouse_lookup):
                continue
            if person.get("gender") == "male" and _is_samosir_marga(person.get("marga")):
                seen_roots.add(pid)
                root_ids.append(pid)

    return sorted(root_ids, key=lambda pid: _root_sort_key(pid, people_by_id))


def _patrilineal_subtree_size(
    root_id: int, children_by_father: dict[int, list[int]], people_by_id: dict[int, dict]
) -> int:
    """Banyaknya orang (unik) di bawah root mengikuti edge ayah → anak."""
    seen: set[int] = set()
    stack = [root_id]
    while stack:
        pid = stack.pop()
        if pid in seen or pid not in people_by_id:
            continue
        seen.add(pid)
        for cid in children_by_father.get(pid, []):
            if cid in people_by_id:
                stack.append(cid)
    return len(seen)


def filter_roots_drop_disconnected_stubs(
    root_ids: list[int],
    children_by_father: dict[int, list[int]],
    people_by_id: dict[int, dict],
) -> list[int]:
    """
    Hilangkan akar sekunder yang hanya "gumpalan" kecil tidak terhubung ke silsilah utama.

    Data kadang punya beberapa ID laki-laki Samosir tanpa edge ayah yang konsisten; mereka
    muncul sebagai barisan vertikal terpisah di bawah pohon yang benar. Kita simpan akar yang
    subtree-nya cukup besar dibanding pohon terbesar.
    """
    import os

    if os.environ.get("TAROMBO_SHOW_ALL_ROOTS", "").strip() in ("1", "true", "yes"):
        return root_ids
    if len(root_ids) <= 1:
        return root_ids

    sizes = {rid: _patrilineal_subtree_size(rid, children_by_father, people_by_id) for rid in root_ids}
    max_sz = max(sizes.values())
    if max_sz < 8:
        return sorted(root_ids, key=lambda pid: _root_sort_key(pid, people_by_id))

    # Minimal ~6–7 orang atau ~7% dari pohon terbesar (tetap tampil beberapa keluarga besar).
    threshold = max(7, int(0.07 * max_sz))
    kept = [rid for rid in root_ids if sizes[rid] >= threshold]
    if not kept:
        best = max(root_ids, key=lambda r: sizes[r])
        return [best]
    return sorted(kept, key=lambda pid: _root_sort_key(pid, people_by_id))


def _spouse_stub_with_panggoaran(person, card_panggoaran_fn, *, partner_lineage=None):
    stub = stub_spouse_from_fields(person)
    if not stub:
        return None
    if partner_lineage:
        stub["child_count"] = partner_lineage.get("child_count", 0)
        stub["descendant_count"] = partner_lineage.get("descendant_count", 0)
    stub["panggoaran"] = card_panggoaran_fn(
        {
            "id": person["id"],
            "gender": stub["gender"],
            "marga": stub.get("marga") or "",
            "panggoaran": "",
            "panggoaran_type": None,
        },
        person,
    )
    if stub["panggoaran"]:
        stub["title"] = f"{stub['name']} — {stub['panggoaran']}"
    return stub


def build_unique_tree(
    people,
    relationships,
    *,
    format_batak_married_name,
    format_panggoaran_display,
    get_opung_hover_suffix,
    effective_sundut,
    fallback_generations,
    sundut_labels,
):
    """
    Bangun hutan pohon: setiap orang maksimal satu node; beberapa akar = beberapa keluarga.
    """
    if not people:
        return []

    people_by_id = {p["id"]: dict(p) for p in people}
    spouse_lookup, children_by_father = build_lineage_maps(relationships, people_by_id)
    opung_spouse_lookup, children_by_parent = build_children_by_parent(
        people_by_id, relationships
    )
    for k, v in opung_spouse_lookup.items():
        spouse_lookup.setdefault(k, v)
    tree_visited = set()

    def card_panggoaran(person, partner=None):
        return panggoaran_for_tree_card(
            person,
            partner,
            people_by_id,
            children_by_parent,
            opung_spouse_lookup,
        )

    def should_render_children(person_id):
        person = people_by_id[person_id]
        if person.get("gender") == "male":
            return True
        spouse_id = spouse_lookup.get(person_id)
        if not spouse_id:
            return person.get("tarombo_status") in ("bere", "ibebere")
        spouse = people_by_id.get(spouse_id)
        return not (spouse and spouse.get("gender") == "male")

    def spouse_payload(spouse_person, partner_person=None, *, partner_lineage=None):
        if not spouse_person:
            return None
        if spouse_person.get("is_spouse_stub"):
            if partner_lineage:
                spouse_person = dict(spouse_person)
                spouse_person["child_count"] = partner_lineage.get("child_count", 0)
                spouse_person["descendant_count"] = partner_lineage.get("descendant_count", 0)
            return spouse_person
        sid = spouse_person["id"]
        tree_visited.add(sid)
        partner = partner_person or people_by_id.get(spouse_lookup.get(sid))
        display = format_spouse_display_name(spouse_person, partner)
        pang = card_panggoaran(spouse_person, partner)
        hover = pang or get_opung_hover_suffix(
            spouse_person, partner, people_by_id, children_by_parent, opung_spouse_lookup
        )
        pt = partuturan_detail(spouse_person, partner)
        lineage = partner_lineage or {}
        return {
            "id": sid,
            "name": display,
            "title": f"{display}{(' — ' + hover) if hover else ''}",
            "panggoaran": pang,
            "birth_year": spouse_person.get("birth_year"),
            "gender": spouse_person["gender"],
            "marga": spouse_person["marga"],
            "partuturan": pt,
            "child_count": lineage.get("child_count", 0),
            "descendant_count": lineage.get("descendant_count", 0),
        }

    def count_descendants(person_id, count_visited=None):
        if count_visited is None:
            count_visited = set()
        if person_id in count_visited:
            return 0
        count_visited.add(person_id)
        total = 0
        for cid in children_by_father.get(person_id, []):
            if cid in people_by_id:
                total += 1 + count_descendants(cid, count_visited)
        return total

    def node_payload(person_id):
        if person_id not in people_by_id or person_id in tree_visited:
            return None
        if is_couple_only_person(person_id, people_by_id, spouse_lookup):
            tree_visited.add(person_id)
            return None

        person = people_by_id[person_id]
        spouse_id = spouse_lookup.get(person_id)
        spouse_person = people_by_id.get(spouse_id) if spouse_id else None

        tree_visited.add(person_id)

        display_name = format_person_display_name(person, spouse_person)
        pang = card_panggoaran(person, spouse_person)
        hover = pang or get_opung_hover_suffix(
            person, spouse_person, people_by_id, children_by_parent, opung_spouse_lookup
        )
        title_parts = [display_name]
        if pang:
            title_parts.insert(0, pang)
        elif hover:
            title_parts.append(hover)

        kids_ids = []
        if should_render_children(person_id):
            kids_ids = [
                cid
                for cid in sort_person_ids(children_by_father.get(person_id, []), people_by_id)
                if cid in people_by_id and cid not in tree_visited
            ]

        kids = []
        for cid in kids_ids:
            child_node = node_payload(cid)
            if child_node:
                kids.append(child_node)

        sundut_num = effective_sundut(person, fallback_generations)
        sundut_display = (
            sundut_labels.get(sundut_num, f"Sundut {sundut_num}") if sundut_num is not None else None
        )
        pt = partuturan_detail(person, spouse_person)
        child_count = len(kids_ids)
        descendant_count = count_descendants(person_id)
        partner_lineage = {
            "child_count": child_count,
            "descendant_count": descendant_count,
        }

        return {
            "id": person_id,
            "name": display_name,
            "panggoaran": pang,
            "title": " — ".join(title_parts),
            "generation": sundut_num,
            "sundut": sundut_num,
            "sundut_label": sundut_display,
            "partuturan": pt,
            "birth_year": person.get("birth_year"),
            "gender": person["gender"],
            "marga": person["marga"],
            "father_name": person["father_name"],
            "mother_name": person["mother_name"],
            "spouse": (
                spouse_payload(spouse_person, person, partner_lineage=partner_lineage)
                if spouse_person
                else _spouse_stub_with_panggoaran(
                    person, card_panggoaran, partner_lineage=partner_lineage
                )
            ),
            "descendant_count": descendant_count,
            "child_count": child_count,
            "child_order": person.get("child_order"),
            "child_order_label": order_label(person),
            "children": kids,
        }

    root_ids = pick_lineage_roots(people, children_by_father, people_by_id, spouse_lookup)
    root_ids = filter_roots_drop_disconnected_stubs(root_ids, children_by_father, people_by_id)
    roots = []
    for rid in root_ids:
        node = node_payload(rid)
        if node:
            roots.append(node)
    return roots


def collect_tree_person_ids(roots) -> set[int]:
    """Semua ID orang yang tampil di pohon (node utama + pasangan, bukan stub)."""
    ids: set[int] = set()

    def walk(node):
        if not node:
            return
        nid = node.get("id")
        if nid is not None:
            try:
                ids.add(int(nid))
            except (TypeError, ValueError):
                pass
        spouse = node.get("spouse")
        if spouse:
            sid = spouse.get("id")
            if sid is not None and not str(sid).startswith("spouse-ref-"):
                try:
                    ids.add(int(sid))
                except (TypeError, ValueError):
                    pass
        for child in node.get("children") or []:
            walk(child)

    for root in roots or []:
        walk(root)
    return ids
