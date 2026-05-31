#!/usr/bin/env python3
"""
Bangun data wilayah Indonesia (Kepmendagri via wilayah.id).
Output: assets/data/indonesia-regions/index.json + provinces/{code}.json
"""
from __future__ import annotations

import json
import os
import time
import urllib.error
import urllib.request

BASE = "https://wilayah.id/api"
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT_DIR = os.path.join(ROOT, "assets", "data", "indonesia-regions")
PROV_DIR = os.path.join(OUT_DIR, "provinces")

JAKARTA_DISPLAY = {
    "Kota Administrasi Jakarta Pusat": "Jakarta Pusat",
    "Kota Administrasi Jakarta Utara": "Jakarta Utara",
    "Kota Administrasi Jakarta Barat": "Jakarta Barat",
    "Kota Administrasi Jakarta Selatan": "Jakarta Selatan",
    "Kota Administrasi Jakarta Timur": "Jakarta Timur",
}

JAKARTA_ALLOWED = {
    "Jakarta Pusat",
    "Jakarta Utara",
    "Jakarta Barat",
    "Jakarta Selatan",
    "Jakarta Timur",
}

# Kode kab/kota Jabodetabek (format wilayah.id: PP.RR).
JABODETABEK_REGENCY_CODES = {
    "32.01",  # Kab. Bogor
    "32.16",  # Kab. Bekasi
    "32.71",  # Kota Bogor
    "32.75",  # Kota Bekasi
    "32.76",  # Kota Depok
    "36.03",  # Kab. Tangerang
    "36.71",  # Kota Tangerang
    "36.74",  # Kota Tangerang Selatan
}

SLEEP = 0.02


def strip_name(name: str) -> str:
    return " ".join(str(name or "").split())


def get(url: str, retries: int = 3) -> dict:
    last: Exception | None = None
    for attempt in range(retries):
        try:
            with urllib.request.urlopen(url, timeout=120) as resp:
                return json.loads(resp.read().decode("utf-8"))
        except (urllib.error.URLError, TimeoutError, json.JSONDecodeError) as exc:
            last = exc
            time.sleep(1 + attempt)
    raise RuntimeError(f"Gagal mengambil {url}: {last}")


def city_label(name: str) -> str:
    name = strip_name(name)
    return JAKARTA_DISPLAY.get(name, name)


def is_jabodetabek_city(prov_code: str, reg_code: str, reg_name: str) -> bool:
    if prov_code == "31":
        return city_label(reg_name) in JAKARTA_ALLOWED
    return reg_code in JABODETABEK_REGENCY_CODES


def main() -> None:
    os.makedirs(PROV_DIR, exist_ok=True)

    provinces = get(f"{BASE}/provinces.json")["data"]
    index = {
        "version": 1,
        "source": "wilayah.id (Kepmendagri)",
        "hierarchy": ["wilayah", "province", "city", "district", "subdistrict"],
        "regions": [
            {
                "id": "jabodetabek",
                "name": "Jabodetabek",
                "description": "Jakarta dan penyangga (Bogor, Depok, Bekasi, Tangerang)",
            }
        ],
        "provinces": [],
    }

    jabodetabek_province_codes: set[str] = set()
    jabodetabek_city_codes: list[str] = []

    total = len(provinces)
    for pi, prov in enumerate(provinces, 1):
        prov_code = str(prov["code"])
        prov_name = strip_name(prov["name"])
        print(f"[{pi}/{total}] {prov_name} ({prov_code})")

        prov_node = {"code": prov_code, "name": prov_name, "cities": []}
        regs = get(f"{BASE}/regencies/{prov_code}.json")["data"]

        if prov_code == "31":
            regs = [r for r in regs if city_label(r["name"]) in JAKARTA_ALLOWED]

        for reg in regs:
            reg_code = str(reg["code"])
            reg_name = city_label(reg["name"])
            region = "jabodetabek" if is_jabodetabek_city(prov_code, reg_code, reg["name"]) else ""

            city = {
                "code": reg_code,
                "name": reg_name,
                "districts": [],
            }
            if region:
                city["region"] = region
                jabodetabek_city_codes.append(reg_code)
                jabodetabek_province_codes.add(prov_code)

            dists = get(f"{BASE}/districts/{reg_code}.json")["data"]
            for dist in dists:
                dist_code = str(dist["code"])
                d = {"code": dist_code, "name": strip_name(dist["name"]), "subdistricts": []}
                try:
                    villages = get(f"{BASE}/villages/{dist_code}.json")["data"]
                except Exception as exc:  # noqa: BLE001
                    print(f"  ! kelurahan {dist['name']}: {exc}")
                    villages = []
                for v in villages:
                    leaf = {
                        "code": str(v["code"]),
                        "name": strip_name(v["name"]),
                    }
                    postal = v.get("postal_code")
                    if postal not in (None, "", 0):
                        leaf["postal_code"] = str(postal).zfill(5)[:5]
                    d["subdistricts"].append(leaf)
                city["districts"].append(d)
                time.sleep(SLEEP)

            prov_node["cities"].append(city)

        out_path = os.path.join(PROV_DIR, f"{prov_code}.json")
        with open(out_path, "w", encoding="utf-8") as handle:
            json.dump(prov_node, handle, ensure_ascii=False, separators=(",", ":"))

        index["provinces"].append({"code": prov_code, "name": prov_name})

    index["regions"][0]["province_codes"] = sorted(jabodetabek_province_codes)
    index["regions"][0]["city_codes"] = jabodetabek_city_codes

    index_path = os.path.join(OUT_DIR, "index.json")
    with open(index_path, "w", encoding="utf-8") as handle:
        json.dump(index, handle, ensure_ascii=False, indent=2)

    print(f"Selesai: {index_path} + {len(index['provinces'])} file provinsi di {PROV_DIR}")


if __name__ == "__main__":
    main()
