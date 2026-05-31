(() => {
  const mountEl = document.getElementById("tarombo-tree-root");
  const statsEl = document.getElementById("heritage-stats");
  const detailPanel = document.getElementById("person-detail-panel");
  const searchInput = document.getElementById("tree-search");
  const expandAllBtn = document.getElementById("expand-all-btn");
  const collapseAllBtn = document.getElementById("collapse-all-btn");
  if (!mountEl) return;

  const editMode = !!window.TAROMBO_EDIT_MODE;
  const treeApiUrl = window.TAROMBO_API_URL || "/api/tarombo-tree";

  let currentRoots = window.TAROMBO_TREE || [];
  let nodeById = new Map();
  let viewport;
  let stage;
  let panLayer;
  let contentEl;
  let zoomLabel;

  let scale = 1;
  let panX = 0;
  let panY = 0;
  let isPanning = false;
  let panStartX = 0;
  let panStartY = 0;
  let panOriginX = 0;
  let panOriginY = 0;

  const MIN_SCALE = 0.5;
  const MAX_SCALE = 2;
  const FIT_MIN_SCALE = 0.85;
  const SCALE_STEP = 0.05;

  function snapScale(value) {
    return Math.round(value / SCALE_STEP) * SCALE_STEP;
  }

  function snapPan(value) {
    return Math.round(value);
  }

  /** Ukuran konten pohon tanpa transform skala (agar zoom tidak mengubah lebar grid / panel samping). */
  function measureContentSize() {
    const prevTransform = contentEl.style.transform;
    contentEl.style.transform = "none";
    const size = {
      width: contentEl.offsetWidth,
      height: contentEl.offsetHeight,
    };
    contentEl.style.transform = prevTransform;
    return size;
  }

  function escapeHtml(text) {
    return String(text || "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;");
  }

  function indexNodes(nodes) {
    nodeById = new Map();
    const walk = (node) => {
      nodeById.set(String(node.id), node);
      if (node.spouse && node.spouse.id != null) {
        nodeById.set(String(node.spouse.id), node.spouse);
      }
      (node.children || []).forEach(walk);
    };
    nodes.forEach(walk);
  }

  function buildViewport() {
    mountEl.classList.remove("heritage-tree", "tarombo-tree");
    mountEl.innerHTML = "";
    mountEl.classList.add("tree-panel");

    const zoomBar = document.createElement("div");
    zoomBar.className = "tree-zoom-bar";
    zoomBar.innerHTML = `
      <button type="button" class="btn btn-dark btn-sm" data-zoom="out" title="Perkecil">−</button>
      <span class="tree-zoom-level" id="tree-zoom-level">100%</span>
      <button type="button" class="btn btn-dark btn-sm" data-zoom="in" title="Perbesar">+</button>
      <button type="button" class="btn btn-dark btn-sm" data-zoom="reset">Reset</button>
      <button type="button" class="btn btn-gold btn-sm" data-zoom="fit">Muat Layar</button>
    `;

    viewport = document.createElement("div");
    viewport.className = "tree-viewport";
    viewport.id = "tree-viewport";

    stage = document.createElement("div");
    stage.className = "tree-stage";

    panLayer = document.createElement("div");
    panLayer.className = "tree-pan-layer";

    contentEl = document.createElement("div");
    contentEl.className = "tree-content";

    panLayer.appendChild(contentEl);
    stage.appendChild(panLayer);
    viewport.appendChild(stage);

    const hint = document.createElement("p");
    hint.className = "tree-hint";
    hint.textContent = editMode
      ? "Seret ⋮⋮ ke kartu lain · geser pohon · simpan perubahan di toolbar"
      : "Geser untuk pindah · roda mouse untuk zoom · arahkan kursor ke nama untuk detail · klik ▸ buka/tutup cabang";

    mountEl.appendChild(zoomBar);
    mountEl.appendChild(viewport);
    mountEl.appendChild(hint);

    if (window.TAROMBO_FULLSCREEN_PAGE) {
      viewport.classList.add("tree-viewport--fs");
    }

    zoomLabel = mountEl.querySelector("#tree-zoom-level");
    mountEl.querySelectorAll("[data-zoom]").forEach((btn) => {
      btn.addEventListener("click", () => {
        const action = btn.getAttribute("data-zoom");
        if (action === "in") zoomBy(1.15);
        else if (action === "out") zoomBy(1 / 1.15);
        else if (action === "reset") resetView();
        else if (action === "fit") fitToView();
      });
    });

    bindPanZoom();
  }

  function applyTransform() {
    const s = snapScale(scale);
    scale = s;
    panX = snapPan(panX);
    panY = snapPan(panY);
    panLayer.style.transform = `translate3d(${panX}px, ${panY}px, 0)`;
    /* Hanya transform (bukan CSS zoom): skala tidak membesarkan layout → panel detail tidak bergeser. */
    contentEl.style.transform = `scale(${s})`;
    contentEl.style.transformOrigin = "0 0";
    if (zoomLabel) zoomLabel.textContent = `${Math.round(s * 100)}%`;
  }

  function zoomBy(factor, centerX, centerY) {
    const rect = stage.getBoundingClientRect();
    const cx = centerX != null ? centerX - rect.left : rect.width / 2;
    const cy = centerY != null ? centerY - rect.top : rect.height / 2;
    const next = snapScale(Math.min(MAX_SCALE, Math.max(MIN_SCALE, scale * factor)));
    const ratio = next / scale;
    panX = snapPan(cx - (cx - panX) * ratio);
    panY = snapPan(cy - (cy - panY) * ratio);
    scale = next;
    applyTransform();
  }

  function resetView() {
    scale = 1;
    panX = 0;
    panY = 0;
    applyTransform();
  }

  function fitToView() {
    const pad = 48;
    const { width: cw, height: ch } = measureContentSize();
    const vw = stage.clientWidth;
    const vh = stage.clientHeight;
    if (!cw || !ch) return;
    let nextScale = Math.min((vw - pad) / cw, (vh - pad) / ch, 1);
    nextScale = Math.max(FIT_MIN_SCALE, Math.min(MAX_SCALE, nextScale));
    scale = snapScale(nextScale);
    panX = snapPan((vw - cw * scale) / 2);
    panY = snapPan(Math.max(pad / 2, (vh - ch * scale) / 2));
    applyTransform();
  }

  function bindPanZoom() {
    stage.addEventListener(
      "wheel",
      (e) => {
        e.preventDefault();
        zoomBy(e.deltaY < 0 ? 1.1 : 1 / 1.1, e.clientX, e.clientY);
      },
      { passive: false }
    );

    stage.addEventListener("mousedown", (e) => {
      if (e.button !== 0) return;
      if (e.target.closest("button, summary, .person-card, .tree-toggle, .tree-drag-handle")) return;
      isPanning = true;
      panStartX = e.clientX;
      panStartY = e.clientY;
      panOriginX = panX;
      panOriginY = panY;
      stage.classList.add("is-panning");
    });

    window.addEventListener("mousemove", (e) => {
      if (!isPanning) return;
      panX = snapPan(panOriginX + (e.clientX - panStartX));
      panY = snapPan(panOriginY + (e.clientY - panStartY));
      applyTransform();
    });

    window.addEventListener("mouseup", () => {
      isPanning = false;
      stage.classList.remove("is-panning");
    });

    let pinchDist = 0;
    stage.addEventListener(
      "touchstart",
      (e) => {
        if (e.touches.length === 2) {
          pinchDist = Math.hypot(
            e.touches[0].clientX - e.touches[1].clientX,
            e.touches[0].clientY - e.touches[1].clientY
          );
        } else if (
          e.touches.length === 1 &&
          !e.target.closest("button, .person-card, .tree-toggle, .tree-drag-handle")
        ) {
          isPanning = true;
          panStartX = e.touches[0].clientX;
          panStartY = e.touches[0].clientY;
          panOriginX = panX;
          panOriginY = panY;
        }
      },
      { passive: true }
    );

    stage.addEventListener(
      "touchmove",
      (e) => {
        if (e.touches.length === 2 && pinchDist > 0) {
          const dist = Math.hypot(
            e.touches[0].clientX - e.touches[1].clientX,
            e.touches[0].clientY - e.touches[1].clientY
          );
          zoomBy(dist / pinchDist, (e.touches[0].clientX + e.touches[1].clientX) / 2, (e.touches[0].clientY + e.touches[1].clientY) / 2);
          pinchDist = dist;
          e.preventDefault();
        } else if (isPanning && e.touches.length === 1) {
          panX = panOriginX + (e.touches[0].clientX - panStartX);
          panY = panOriginY + (e.touches[0].clientY - panStartY);
          applyTransform();
        }
      },
      { passive: false }
    );

    stage.addEventListener("touchend", () => {
      isPanning = false;
      pinchDist = 0;
    });
  }

  function renderStats(stats) {
    if (!statsEl || !stats) return;
    statsEl.innerHTML = `
      <span>Total Anggota: <strong>${stats.total_people || 0}</strong></span>
      <span>Keluarga Utama: <strong>${stats.root_families || 0}</strong></span>
      <span>Generasi Maks: <strong>${stats.max_generation || 0}</strong></span>
    `;
  }

  function personCard(node, clickable = true) {
    const isStubSpouse = node.is_spouse_stub || String(node.id || "").startsWith("spouse-ref-");
    const year = node.birth_year ? `<small>${node.birth_year}</small>` : "";
    const sundutNum = node.sundut ?? node.generation;
    const sundutText = node.sundut_label || (sundutNum != null ? `Sundut ${sundutNum}` : "");
    const gen = sundutText ? `<small class="gen-tag">${escapeHtml(sundutText)}</small>` : "";
    const orderTag = node.child_order_label
      ? `<small class="order-tag">${escapeHtml(node.child_order_label)}</small>`
      : "";
    const pang = node.panggoaran || "";
    const isTemp = pang.includes("(sementara)");
    const pangHtml = pang
      ? `<span class="panggoaran-tag${isTemp ? " is-temporary" : ""}">${escapeHtml(pang)}</span>`
      : "";
    const canClick = clickable && !isStubSpouse && node.id != null;
    const idAttr = node.id != null ? `data-person-id="${node.id}"` : "";
    const tag = canClick ? "button" : "div";
    const dragHandle =
      editMode && canClick
        ? `<span class="tree-drag-handle" draggable="true" data-drag-person-id="${node.id}" title="Seret ke orang lain">⋮⋮</span>`
        : "";
    return `
      <${tag} type="${canClick ? "button" : ""}" class="person-card ${canClick ? "is-clickable" : "is-spouse-stub"}" ${idAttr} title="${escapeHtml(node.title || node.name)}">
        ${dragHandle}
        ${pangHtml}
        <span class="person-name">${escapeHtml(node.name)}</span>
        ${year}
        ${orderTag}
        ${gen}
      </${tag}>
    `;
  }

  function nodeBlock(node) {
    const hasChildren = node.children && node.children.length > 0;
    const wrap = document.createElement("div");
    wrap.className = "tree-org-node";
    wrap.dataset.personId = String(node.id);

    const cardRow = document.createElement("div");
    cardRow.className = "tree-org-cardrow";

    const cardHeader = document.createElement("div");
    cardHeader.className = "tree-card-header";

    if (hasChildren) {
      const toggle = document.createElement("button");
      toggle.type = "button";
      toggle.className = "tree-toggle";
      toggle.setAttribute("aria-expanded", "false");
      toggle.title = "Buka / tutup satu generasi ke bawah";
      toggle.textContent = "▸";
      toggle.addEventListener("click", (e) => {
        e.stopPropagation();
        setNodeOpen(wrap, !wrap.classList.contains("is-open"));
      });
      cardHeader.appendChild(toggle);
    } else {
      const spacer = document.createElement("span");
      spacer.className = "tree-toggle-spacer";
      cardHeader.appendChild(spacer);
    }

    const couple = document.createElement("div");
    couple.className = "couple-wrap";
    couple.innerHTML = `
      ${personCard(node)}
      ${node.spouse ? `<span class="couple-sep">&</span>${personCard(node.spouse)}` : ""}
    `;
    cardHeader.appendChild(couple);
    cardRow.appendChild(cardHeader);

    const meta = document.createElement("div");
    meta.className = "node-meta";
    meta.innerHTML = `
      <small>${node.child_count || 0} anak</small>
      <small>${node.descendant_count || 0} keturunan</small>
    `;
    cardRow.appendChild(meta);
    wrap.appendChild(cardRow);

    if (hasChildren) {
      const childrenWrap = document.createElement("div");
      childrenWrap.className = "tree-org-children";

      const multiSiblings = node.children.length > 1;
      const stem = document.createElement("div");
      stem.className = multiSiblings ? "tree-link-stem" : "tree-link-stem tree-link-stem--solo";
      stem.setAttribute("aria-hidden", "true");
      childrenWrap.appendChild(stem);

      const childList = document.createElement("div");
      childList.className = "tree-org-childlist tree-org-siblings";
      if (multiSiblings) childList.classList.add("has-siblings");
      childList.setAttribute("role", "group");
      childList.setAttribute("aria-label", "Saudara kandung");

      node.children.forEach((child) => {
        const col = document.createElement("div");
        col.className = "tree-org-childcol";
        if (multiSiblings) {
          const drop = document.createElement("div");
          drop.className = "tree-link-drop";
          drop.setAttribute("aria-hidden", "true");
          col.appendChild(drop);
        }
        col.appendChild(nodeBlock(child));
        childList.appendChild(col);
      });
      childrenWrap.appendChild(childList);
      wrap.appendChild(childrenWrap);
    }

    return wrap;
  }

  function renderTree(roots) {
    currentRoots = roots || [];
    indexNodes(currentRoots);
    contentEl.innerHTML = "";

    if (!currentRoots.length) {
      contentEl.innerHTML = '<p class="tree-empty">Data tarombo belum tersedia.</p>';
      return;
    }

    const forest = document.createElement("div");
    forest.className = "tree-org-forest";
    if (currentRoots.length > 1) forest.classList.add("tree-org-forest--multi");
    currentRoots.forEach((rootNode) => {
      forest.appendChild(nodeBlock(rootNode));
    });
    contentEl.appendChild(forest);
    bindTreeEvents();
    openRootBranches();
    if (editMode) {
      mountEl.classList.add("tree-edit-mode");
      document.dispatchEvent(new CustomEvent("tarombo:rendered"));
    }
    requestAnimationFrame(() => {
      fitToView();
      scrollTreeToTop();
    });
  }

  /** Buka/tutup hanya node ini; cabang lain tidak berubah kecuali diklik tombolnya sendiri. */
  function setNodeOpen(el, open) {
    if (!el) return;
    el.classList.toggle("is-open", open);
    const toggle = el.querySelector(":scope > .tree-org-cardrow .tree-toggle");
    if (toggle) {
      toggle.setAttribute("aria-expanded", open ? "true" : "false");
      toggle.textContent = open ? "▾" : "▸";
    }
  }

  /** Semua cabang tertutup; setiap ▸ membuka satu generasi ke bawah. */
  function openRootBranches() {
    contentEl.querySelectorAll(".tree-org-node").forEach((el) => setNodeOpen(el, false));
  }

  /** Buka rantai induk satu lapis per node agar jalur ke orang target terlihat. */
  function openAncestorBranches(personId) {
    const block = contentEl.querySelector(`.tree-org-node[data-person-id="${personId}"]`);
    if (!block) return;
    const chain = [];
    let cursor = block.parentElement?.closest(".tree-org-node");
    while (cursor) {
      chain.unshift(cursor);
      cursor = cursor.parentElement?.closest(".tree-org-node");
    }
    chain.forEach((node) => setNodeOpen(node, true));
  }

  function scrollTreeToTop() {
    if (!viewport || !contentEl) return;
    const firstRoot = contentEl.querySelector(".tree-org-forest > .tree-org-node");
    if (!firstRoot) return;
    const vpRect = viewport.getBoundingClientRect();
    const rootRect = firstRoot.getBoundingClientRect();
    panY = snapPan(panY + (vpRect.top + 48 - rootRect.top));
    applyTransform();
  }

  /** Hanya gelar/label partuturan — tanpa penjelasan panjang (pengguna umumnya sudah familier). */
  function partuturanLine(pt, label) {
    if (!pt || !pt.label) return "";
    return `<p class="detail-partuturan-line"><strong>${escapeHtml(label)}</strong> ${escapeHtml(pt.label)}</p>`;
  }

  function detailPanelPlaceholder() {
    if (!detailPanel) return;
    detailPanel.classList.add("is-empty");
    detailPanel.classList.remove("is-visible");
    detailPanel.setAttribute("aria-hidden", "true");
    detailPanel.innerHTML = `
      <h3>Detail Anggota</h3>
      <p class="hint">Arahkan kursor ke kotak nama di pohon.</p>
    `;
  }

  function clearPersonDetail() {
    if (!detailPanel || editMode) return;
    contentEl?.querySelectorAll(".person-card.is-detail-hover").forEach((c) => {
      c.classList.remove("is-detail-hover");
    });
    detailPanelPlaceholder();
  }

  function findLineagePartner(node) {
    if (!node || node.id == null) return null;
    const id = String(node.id);
    for (const main of nodeById.values()) {
      if (!main?.spouse) continue;
      const sid = String(main.spouse.id ?? "");
      if (sid === id) return main;
    }
    if (id.startsWith("spouse-ref-")) {
      return nodeById.get(id.slice("spouse-ref-".length)) || null;
    }
    return null;
  }

  /** Anak/keturunan pasangan mengikuti garis pasangan di pohon (bukan 0). */
  function lineageCounts(node) {
    const partner = findLineagePartner(node);
    if (partner) {
      return {
        child_count: partner.child_count ?? 0,
        descendant_count: partner.descendant_count ?? 0,
        partner_name: partner.name,
      };
    }
    return {
      child_count: node.child_count ?? 0,
      descendant_count: node.descendant_count ?? 0,
      partner_name: null,
    };
  }

  function resolveCardNode(card) {
    const id = card.getAttribute("data-person-id");
    if (!id) return null;
    const hit = nodeById.get(String(id));
    if (hit) return hit;
    if (String(id).startsWith("spouse-ref-")) {
      const parentId = String(id).slice("spouse-ref-".length);
      const parent = nodeById.get(parentId);
      if (parent?.spouse) {
        const spouse = { ...parent.spouse, name: parent.spouse.name || parent.spouse.title };
        if (spouse.child_count == null) spouse.child_count = parent.child_count;
        if (spouse.descendant_count == null) spouse.descendant_count = parent.descendant_count;
        return spouse;
      }
    }
    return null;
  }

  function useHoverDetail() {
    return !editMode && window.matchMedia("(hover: hover) and (pointer: fine)").matches;
  }

  function showPersonDetail(node) {
    if (!detailPanel || !node) return;
    detailPanel.classList.remove("is-empty");
    detailPanel.classList.add("is-visible");
    detailPanel.setAttribute("aria-hidden", "false");
    const spouseLine = node.spouse
      ? `<p><strong>Pasangan:</strong> ${escapeHtml(node.spouse.name)}</p>`
      : "";
    const pangLine = node.panggoaran
      ? `<p><strong>Panggoaran:</strong> ${escapeHtml(node.panggoaran)}</p>`
      : "";
    const pt = node.partuturan || {};
    const spousePt = node.spouse?.partuturan;
    const ptSpouseLabel = pt.spouse_display || pt.spouse_name;
    const ptSpouse =
      !node.spouse && ptSpouseLabel
        ? `<p><strong>Pasangan (data):</strong> ${escapeHtml(ptSpouseLabel)}</p>`
        : "";
    const spousePtBlock =
      node.spouse && spousePt?.label
        ? partuturanLine(spousePt, "Partuturan pasangan:")
        : "";
    const father = escapeHtml(node.father_name || "-");
    const mother = escapeHtml(node.mother_name || "-");
    const lineage = lineageCounts(node);
    const lineageNote = lineage.partner_name
      ? `<small class="hint">Melalui garis ${escapeHtml(lineage.partner_name)}</small>`
      : "";
    detailPanel.innerHTML = `
      <h3>Detail Anggota</h3>
      <p><strong>Nama:</strong> ${escapeHtml(node.name)}</p>
      ${pangLine}
      <p><strong>Marga / Sundut:</strong> ${escapeHtml(node.marga || "-")} · ${escapeHtml(node.sundut_label || (node.sundut != null ? `Sundut ${node.sundut}` : "-"))}</p>
      ${partuturanLine(pt, "Partuturan:")}
      ${spousePtBlock}
      ${ptSpouse}
      <p><strong>Lahir:</strong> ${node.birth_year || "-"}${node.child_order_label ? ` · ${escapeHtml(node.child_order_label)}` : ""}</p>
      <p><strong>Ortu:</strong> ${father} · ${mother}</p>
      ${spouseLine}
      <p><strong>Anak:</strong> ${lineage.child_count} · <strong>Keturunan:</strong> ${lineage.descendant_count}</p>
      ${lineageNote}
    `;
  }

  function bindTreeEvents() {
    const hoverMode = useHoverDetail();
    const cards = contentEl.querySelectorAll(".person-card[data-person-id]");

    cards.forEach((card) => {
      if (editMode) {
        if (!card.classList.contains("is-clickable")) return;
        card.addEventListener("click", (e) => {
          e.stopPropagation();
          const node = resolveCardNode(card);
          if (!node) return;
          document.dispatchEvent(new CustomEvent("tarombo:person-click", { detail: { node } }));
        });
        return;
      }

      if (hoverMode) {
        card.addEventListener("mouseenter", () => {
          const node = resolveCardNode(card);
          if (!node) return;
          contentEl.querySelectorAll(".person-card.is-detail-hover").forEach((c) => {
            c.classList.remove("is-detail-hover");
          });
          card.classList.add("is-detail-hover");
          showPersonDetail(node);
        });
        card.addEventListener("mouseleave", () => {
          card.classList.remove("is-detail-hover");
          clearPersonDetail();
        });
      } else {
        card.addEventListener("click", (e) => {
          e.stopPropagation();
          const node = resolveCardNode(card);
          if (!node) return;
          contentEl.querySelectorAll(".person-card.is-detail-hover").forEach((c) => {
            c.classList.remove("is-detail-hover");
          });
          card.classList.add("is-detail-hover");
          showPersonDetail(node);
        });
      }
    });

    if (!editMode && hoverMode && viewport) {
      viewport.addEventListener("mouseleave", clearPersonDetail);
    } else if (!editMode && !hoverMode) {
      document.addEventListener(
        "click",
        (e) => {
          if (!e.target.closest(".person-card[data-person-id]")) clearPersonDetail();
        },
        true
      );
    }
  }

  function setAllBranches(open) {
    contentEl.querySelectorAll(".tree-org-node").forEach((el) => {
      const hasKids = el.querySelector(":scope > .tree-org-children");
      if (!hasKids) return;
      setNodeOpen(el, open);
    });
    if (open) requestAnimationFrame(() => fitToView());
  }

  function centerOnElement(el) {
    if (!el || !stage) return;
    const stageRect = stage.getBoundingClientRect();
    const elRect = el.getBoundingClientRect();
    panX += stageRect.left + stageRect.width / 2 - (elRect.left + elRect.width / 2);
    panY += stageRect.top + stageRect.height / 2 - (elRect.top + elRect.height / 2);
    applyTransform();
  }

  function openPathToPerson(personId) {
    const block = contentEl.querySelector(`.tree-org-node[data-person-id="${personId}"]`);
    if (!block) return;
    openAncestorBranches(personId);
    const card = contentEl.querySelector(`.person-card[data-person-id="${personId}"]`);
    if (card) {
      card.classList.add("is-match");
      requestAnimationFrame(() => centerOnElement(card));
    }
    if (!useHoverDetail()) {
      const node = nodeById.get(String(personId));
      if (node) showPersonDetail(node);
    }
  }

  function applySearch(query) {
    const q = query.trim().toLowerCase();
    contentEl.querySelectorAll(".person-card").forEach((card) => {
      const name = card.querySelector(".person-name")?.textContent?.toLowerCase() || "";
      card.classList.toggle("is-match", !!q && name.includes(q));
      card.classList.toggle("is-dim", q && !name.includes(q));
    });
    if (!q) return;
    const hit =
      (window.TAROMBO_INDEX || []).find((p) => p.name.toLowerCase().includes(q)) ||
      [...nodeById.values()].find((n) => n.name.toLowerCase().includes(q));
    if (hit) openPathToPerson(hit.id);
  }

  buildViewport();
  detailPanelPlaceholder();
  renderStats(window.TAROMBO_STATS || {});
  renderTree(currentRoots);

  if (expandAllBtn) expandAllBtn.addEventListener("click", () => setAllBranches(true));
  if (collapseAllBtn) collapseAllBtn.addEventListener("click", () => setAllBranches(false));
  if (searchInput) searchInput.addEventListener("input", () => applySearch(searchInput.value));

  window.addEventListener("resize", () => {
    if (contentEl?.offsetWidth) fitToView();
  });

  async function refreshTree() {
    if (!treeApiUrl) return;
    try {
      const res = await fetch(treeApiUrl, { headers: { Accept: "application/json" } });
      if (!res.ok) return;
      const payload = await res.json();
      renderStats(payload.stats || {});
      renderTree(payload.roots || []);
      if (searchInput?.value.trim()) applySearch(searchInput.value);
    } catch (_err) {
      /* ignore */
    }
  }

  window.TaromboTree = {
    renderTree,
    renderStats,
    refreshTree,
    getNode: (id) => nodeById.get(String(id)),
    getRoots: () => currentRoots,
  };
})();
