"""Gabung duplikat + hapus entri approved yang tidak ada di pohon."""
import os
import sys

os.environ.setdefault("USE_SQLITE", "1")
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from app import app, get_db, insert_relationship_ignore, sql_approved  # noqa: E402
from services.graph_sync import reconcile_tree  # noqa: E402


def main():
    with app.app_context():
        db = get_db()
        result = reconcile_tree(db, sql_approved, insert_relationship_ignore)
        print("merge:", result["merge"])
        print("purge:", result["purge"])
        print("tree_people:", result["tree_people"])


if __name__ == "__main__":
    main()
