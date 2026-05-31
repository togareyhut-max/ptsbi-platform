"""Uji aturan cucu pertama untuk panggoaran Opung."""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from services.opung import build_panggoaran_label, resolve_opung_grandchild


def _people(*rows):
    return {r["id"]: r for r in rows}


def _parent_map(*pairs):
    m = {}
    for parent, child in pairs:
        m.setdefault(parent, []).append(child)
    return m


def test_permanent_from_first_son():
    # Urutan: laki-laki dulu → cucu dari anak laki-laki pertama
    p = _people(
        {"id": 1, "gender": "male", "full_name": "Opung"},
        {"id": 2, "gender": "male", "full_name": "Anak Laki"},
        {"id": 3, "gender": "male", "full_name": "Cucu Permanen"},
    )
    ch = _parent_map((1, 2), (2, 3))
    gc, typ, src = resolve_opung_grandchild(1, p, ch)
    assert gc == 3 and typ == "permanent" and src == "son"


def test_temporary_only_when_first_child_female():
    # Urutan: perempuan dulu, laki-laki kedua belum punya anak
    p = _people(
        {"id": 1, "gender": "male", "full_name": "Opung"},
        {"id": 2, "gender": "female", "full_name": "Anak Perempuan"},
        {"id": 3, "gender": "male", "full_name": "Adik Laki"},
        {"id": 4, "gender": "male", "full_name": "Cucu Sementara"},
    )
    ch = _parent_map((1, 2), (1, 3), (2, 4))
    gc, typ, src = resolve_opung_grandchild(1, p, ch)
    assert gc == 4 and typ == "temporary" and src == "daughter"


def test_no_temporary_when_first_child_male_without_grandchild():
    # Anak pertama laki-laki, belum punya anak — tidak ambil garis perempuan di belakang
    p = _people(
        {"id": 1, "gender": "male", "full_name": "Opung"},
        {"id": 2, "gender": "male", "full_name": "Anak Pertama"},
        {"id": 3, "gender": "female", "full_name": "Anak Kedua"},
        {"id": 4, "gender": "male", "full_name": "Cucu dari Perempuan"},
    )
    ch = _parent_map((1, 2), (1, 3), (3, 4))
    gc, typ, src = resolve_opung_grandchild(1, p, ch)
    assert gc is None


def test_permanent_replaces_temporary_when_son_has_child():
    p = _people(
        {"id": 1, "gender": "male", "full_name": "Opung"},
        {"id": 2, "gender": "female", "full_name": "Anak Perempuan"},
        {"id": 3, "gender": "male", "full_name": "Adik Laki"},
        {"id": 4, "gender": "male", "full_name": "Cucu Sementara"},
        {"id": 5, "gender": "male", "full_name": "Cucu Permanen"},
    )
    ch = _parent_map((1, 2), (1, 3), (2, 4), (3, 5))
    gc, typ, src = resolve_opung_grandchild(1, p, ch)
    assert gc == 5 and typ == "permanent" and src == "son"


def test_labels():
    assert build_panggoaran_label("male", "Budi Marpaung") == "Op. Budi Doli"
    assert build_panggoaran_label("female", "Budi Marpaung") == "Op. Budi Boru"


if __name__ == "__main__":
    test_permanent_from_first_son()
    test_temporary_only_when_first_child_female()
    test_no_temporary_when_first_child_male_without_grandchild()
    test_permanent_replaces_temporary_when_son_has_child()
    test_labels()
    print("all opung rules ok")
