"""Generate ulos-toba-list-vertical.svg — lis vertikal persis pola ulos Toba."""
from pathlib import Path

# Skala 2×: tampilan halus, tetap kotak tenun
W, H, CELL = 88, 96, 4
MAROON = "#4a0e0e"
MAROON_EDGE = "#3a0a0a"
GOLD = "#d4a03a"
GOLD_LIGHT = "#e8c96a"
GOLD_DIM = "#c67d2b"
DARK = "#2e0808"
YELLOW = "#e8d88e"
ORANGE = "#b86520"
ORANGE_MID = "#c67d2b"
ORANGE_HI = "#d4923a"
EDGE_ORANGE = "#9a5520"

DIAMOND_COLS = 11
DIAMOND_HALF = DIAMOND_COLS // 2
DIAMOND_CX = 44
DIAMOND_CYS = (24, 72)

PALETTE = {
    0: YELLOW,
    1: DARK,
    2: EDGE_ORANGE,
    3: ORANGE,
    4: ORANGE_MID,
    5: ORANGE_HI,
    6: GOLD_DIM,
}


def pixel_diamond(cx: int, cy: int) -> list[str]:
    ox = cx - DIAMOND_HALF * CELL
    oy = cy - DIAMOND_HALF * CELL
    out: list[str] = []
    for i in range(DIAMOND_COLS):
        for j in range(DIAMOND_COLS):
            man = abs(i - DIAMOND_HALF) + abs(j - DIAMOND_HALF)
            if man > 6:
                continue
            c = PALETTE[man]
            x = ox + j * CELL
            y = oy + i * CELL
            out.append(
                f'<rect x="{x}" y="{y}" width="{CELL}" height="{CELL}" fill="{c}"/>'
            )
    return out


def side_bands() -> list[str]:
    lines: list[str] = []
    # Tepi: coretan horizontal emas
    for y in range(0, H, 5):
        lines.append(
            f'<rect x="2" y="{y}" width="5" height="2" fill="{GOLD}" opacity="0.92"/>'
        )
        lines.append(
            f'<rect x="{W - 7}" y="{y}" width="5" height="2" fill="{GOLD}" opacity="0.92"/>'
        )
    # Garis sirara vertikal tipis
    lines.append(f'<rect x="8" y="0" width="1" height="{H}" fill="{GOLD_LIGHT}" opacity="0.4"/>')
    lines.append(
        f'<rect x="{W - 9}" y="0" width="1" height="{H}" fill="{GOLD_LIGHT}" opacity="0.4"/>'
    )
    # Ketupat kecil berongga (wajik, seperti foto)
    for y in range(3, H, 12):
        lines.append(
            f'<path d="M13.5 {y}l3 3-3 3-3-3z" fill="none" '
            f'stroke="{GOLD_LIGHT}" stroke-width="0.75" opacity="0.95"/>'
        )
        lines.append(
            f'<path d="M74.5 {y}l3 3-3 3-3-3z" fill="none" '
            f'stroke="{GOLD_LIGHT}" stroke-width="0.75" opacity="0.95"/>'
        )
    # Pita X dan titik
    for y in range(0, H, 12):
        lines.append(
            f'<path d="M17 {y + 1}l2.2 2.2M17 {y + 1}l2.2-2.2" '
            f'stroke="{GOLD_LIGHT}" stroke-width="0.8" stroke-linecap="round" opacity="0.9"/>'
        )
        lines.append(
            f'<circle cx="18" cy="{y + 7}" r="1" fill="{GOLD_LIGHT}" opacity="0.85"/>'
        )
        lines.append(
            f'<path d="M{W - 17} {y + 1}l2.2 2.2M{W - 17} {y + 1}l2.2-2.2" '
            f'stroke="{GOLD_LIGHT}" stroke-width="0.8" stroke-linecap="round" opacity="0.9"/>'
        )
        lines.append(
            f'<circle cx="{W - 18}" cy="{y + 7}" r="1" fill="{GOLD_LIGHT}" opacity="0.85"/>'
        )
    return lines


def main() -> None:
    lines = [
        '<?xml version="1.0" encoding="UTF-8"?>',
        f'<svg xmlns="http://www.w3.org/2000/svg" width="{W}" height="{H}" '
        f'viewBox="0 0 {W} {H}" shape-rendering="crispEdges">',
        f'<rect width="{W}" height="{H}" fill="{MAROON}"/>',
        f'<rect x="0" y="0" width="7" height="{H}" fill="{MAROON_EDGE}"/>',
        f'<rect x="{W - 7}" y="0" width="7" height="{H}" fill="{MAROON_EDGE}"/>',
    ]
    lines.extend(side_bands())
    lines.append('<g id="diamonds">')
    for cy in DIAMOND_CYS:
        lines.extend(pixel_diamond(DIAMOND_CX, cy))
    lines.append("</g>")
    lines.append("</svg>")
    out = Path(__file__).resolve().parents[1] / "static" / "ulos" / "ulos-toba-list-vertical.svg"
    out.write_text("\n".join(lines), encoding="utf-8")
    print(f"Wrote {out} ({len(lines)} lines)")


if __name__ == "__main__":
    main()
