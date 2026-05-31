import tarfile
import os
from pathlib import Path

archive = Path(r"C:\Users\togar\.cursor\projects\empty-window\data\server-inventory-20260522-080419.tgz")
out = Path(r"C:\Users\togar\.cursor\projects\empty-window\data\extracted")
out.mkdir(parents=True, exist_ok=True)

print("exists:", archive.exists(), "size:", archive.stat().st_size if archive.exists() else 0)
with tarfile.open(archive, "r:gz") as t:
    t.extractall(out)
    print("extracted to:", out)
    for root, dirs, files in os.walk(out):
        for f in files:
            print(os.path.join(root, f))
