import { writeFileSync } from "fs";
import { join, dirname } from "path";
import { fileURLToPath } from "url";

const W = 88, H = 96, CELL = 4;
const MAROON = "#4a0e0e", MAROON_EDGE = "#3a0a0a", GOLD = "#d4a03a", GOLD_LIGHT = "#e8c96a";
const GOLD_DIM = "#c67d2b", DARK = "#2e0808", YELLOW = "#e8d88e", ORANGE = "#b86520";
const ORANGE_MID = "#c67d2b", ORANGE_HI = "#d4923a", EDGE_ORANGE = "#9a5520";
const PALETTE = [YELLOW, DARK, EDGE_ORANGE, ORANGE, ORANGE_MID, ORANGE_HI, GOLD_DIM];
const DIAMOND_COLS = 11, DIAMOND_HALF = 5, DIAMOND_CX = 44, DIAMOND_CYS = [24, 72];

function pixelDiamond(cx, cy) {
  const ox = cx - DIAMOND_HALF * CELL, oy = cy - DIAMOND_HALF * CELL;
  const out = [];
  for (let i = 0; i < DIAMOND_COLS; i++) {
    for (let j = 0; j < DIAMOND_COLS; j++) {
      const man = Math.abs(i - DIAMOND_HALF) + Math.abs(j - DIAMOND_HALF);
      if (man > 6) continue;
      out.push(`<rect x="${ox + j * CELL}" y="${oy + i * CELL}" width="${CELL}" height="${CELL}" fill="${PALETTE[man]}"/>`);
    }
  }
  return out;
}

function sideBands() {
  const lines = [];
  for (let y = 0; y < H; y += 5) {
    lines.push(`<rect x="2" y="${y}" width="5" height="2" fill="${GOLD}" opacity="0.92"/>`);
    lines.push(`<rect x="${W - 7}" y="${y}" width="5" height="2" fill="${GOLD}" opacity="0.92"/>`);
  }
  lines.push(`<rect x="8" y="0" width="1" height="${H}" fill="${GOLD_LIGHT}" opacity="0.4"/>`);
  lines.push(`<rect x="${W - 9}" y="0" width="1" height="${H}" fill="${GOLD_LIGHT}" opacity="0.4"/>`);
  for (let y = 3; y < H; y += 12) {
    lines.push(`<rect x="11" y="${y}" width="5" height="5" fill="none" stroke="${GOLD_LIGHT}" stroke-width="0.75" opacity="0.95"/>`);
    lines.push(`<rect x="${W - 16}" y="${y}" width="5" height="5" fill="none" stroke="${GOLD_LIGHT}" stroke-width="0.75" opacity="0.95"/>`);
  }
  for (let y = 0; y < H; y += 12) {
    lines.push(`<path d="M17 ${y + 1}l2.2 2.2M17 ${y + 1}l2.2-2.2" stroke="${GOLD_LIGHT}" stroke-width="0.8" stroke-linecap="round" opacity="0.9"/>`);
    lines.push(`<circle cx="18" cy="${y + 7}" r="1" fill="${GOLD_LIGHT}" opacity="0.85"/>`);
    lines.push(`<path d="M${W - 17} ${y + 1}l2.2 2.2M${W - 17} ${y + 1}l2.2-2.2" stroke="${GOLD_LIGHT}" stroke-width="0.8" stroke-linecap="round" opacity="0.9"/>`);
    lines.push(`<circle cx="${W - 18}" cy="${y + 7}" r="1" fill="${GOLD_LIGHT}" opacity="0.85"/>`);
  }
  return lines;
}

const lines = [
  '<?xml version="1.0" encoding="UTF-8"?>',
  `<svg xmlns="http://www.w3.org/2000/svg" width="${W}" height="${H}" viewBox="0 0 ${W} ${H}" shape-rendering="crispEdges">`,
  `<rect width="${W}" height="${H}" fill="${MAROON}"/>`,
  `<rect x="0" y="0" width="7" height="${H}" fill="${MAROON_EDGE}"/>`,
  `<rect x="${W - 7}" y="0" width="7" height="${H}" fill="${MAROON_EDGE}"/>`,
];
lines.push(...sideBands());
lines.push('<g id="diamonds">');
for (const cy of DIAMOND_CYS) lines.push(...pixelDiamond(DIAMOND_CX, cy));
lines.push("</g>", "</svg>");

const out = join(dirname(fileURLToPath(import.meta.url)), "static", "ulos", "ulos-toba-list-vertical.svg");
writeFileSync(out, lines.join("\n"), "utf8");
const rects = lines.filter((l) => l.includes("<rect")).length;
console.log(JSON.stringify({ lines: lines.length, rects, out }));
