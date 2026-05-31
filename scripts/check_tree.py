"""Cek struktur pohon: duplikat, jumlah akar, orphan."""
import os
import sys

os.environ.setdefault("USE_SQLITE", "1")
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from app import app, build_heritage_tree_payload, get_db, resync_graph


def main():
    with app.app_context():
        db = get_db()
        resync_graph(db)
        p = build_heritage_tree_payload(db, sync=False)
        roots = p["roots"]
        seen = set()
        dup = []

        def walk(n):
            pid = n.get("id")
            if pid in seen:
                dup.append((pid, n.get("name")))
            elif pid:
                seen.add(pid)
            for c in n.get("children") or []:
                walk(c)

        for r in roots:
            walk(r)

        lines = [
            f"roots={len(roots)}",
            f"nodes_in_tree={len(seen)}",
            f"duplicates={len(dup)}",
        ]
        if dup:
            lines.append("dup_ids: " + ", ".join(f"{a}:{b}" for a, b in dup[:20]))
        for i, r in enumerate(roots):
            lines.append(f"root[{i}] id={r.get('id')} name={r.get('name')} children={len(r.get('children') or [])}")
        out = "\n".join(lines)
        print(out)
        path = os.path.join(os.path.dirname(__file__), "tree_check.txt")
        with open(path, "w", encoding="utf-8") as f:
            f.write(out)


if __name__ == "__main__":
    main()
