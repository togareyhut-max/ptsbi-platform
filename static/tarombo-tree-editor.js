(() => {
  if (!window.TAROMBO_EDIT_MODE) return;

  const personApi = window.TAROMBO_PERSON_API || "/api/admin/person";
  const saveApi = window.TAROMBO_SAVE_API || "/api/admin/tree-changes";
  const partuturanOpts = window.TAROMBO_PARTUTURAN || {};

  const badge = document.getElementById("tree-pending-badge");
  const saveBtn = document.getElementById("tree-save-btn");
  const discardBtn = document.getElementById("tree-discard-btn");
  const formHost = document.getElementById("tree-edit-form-host");
  const mountEl = document.getElementById("tarombo-tree-root");
  const dialog = document.getElementById("tree-drop-dialog");
  const dropTitle = document.getElementById("tree-drop-title");
  const dropHint = document.getElementById("tree-drop-hint");
  const dropReparent = document.getElementById("tree-drop-reparent");
  const dropReplace = document.getElementById("tree-drop-replace");
  const dropCancel = document.getElementById("tree-drop-cancel");

  let pendingChanges = [];
  let selectedPersonId = null;
  let dropContext = null;
  let dragPersonId = null;

  function escapeHtml(text) {
    return String(text || "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;");
  }

  function opLabel(change) {
    const op = change.op;
    if (op === "reparent") {
      const father = window.TaromboTree?.getNode(change.father_id);
      return `Pindah ke anak ${father?.name || change.father_id}`;
    }
    if (op === "replace_with") {
      const target = window.TaromboTree?.getNode(change.target_id);
      return `Tukar posisi dengan ${target?.name || change.target_id}`;
    }
    if (op === "swap_parents") {
      const b = window.TaromboTree?.getNode(change.person_id_b);
      return `Tukar ayah/ibu dengan ${b?.name || change.person_id_b}`;
    }
    if (op === "update_person") {
      return `Ubah data: ${change.fields?.full_name || change.person_id}`;
    }
    return op;
  }

  function updateToolbar() {
    const n = pendingChanges.length;
    if (badge) badge.textContent = `${n} perubahan belum disimpan`;
    if (saveBtn) saveBtn.disabled = n === 0;
    if (discardBtn) discardBtn.disabled = n === 0;
    renderPendingList();
  }

  function renderPendingList() {
    let list = formHost?.querySelector(".tree-pending-list");
    if (!list && formHost) {
      list = document.createElement("div");
      list.className = "tree-pending-list";
      formHost.prepend(list);
    }
    if (!list) return;
    if (!pendingChanges.length) {
      list.innerHTML = "";
      list.hidden = true;
      return;
    }
    list.hidden = false;
    list.innerHTML = `
      <h4>Antrian perubahan</h4>
      <ul>${pendingChanges
        .map(
          (c, i) =>
            `<li><span>${escapeHtml(opLabel(c))}</span>
             <button type="button" class="btn-link" data-remove-pending="${i}" title="Hapus dari antrian">×</button></li>`
        )
        .join("")}</ul>
    `;
    list.querySelectorAll("[data-remove-pending]").forEach((btn) => {
      btn.addEventListener("click", () => {
        const idx = Number(btn.getAttribute("data-remove-pending"));
        pendingChanges.splice(idx, 1);
        updateToolbar();
        clearDragHighlight();
      });
    });
  }

  function queueChange(change) {
    pendingChanges.push(change);
    updateToolbar();
  }

  function getContentEl() {
    return mountEl?.querySelector(".tree-content");
  }

  function clearDragHighlight() {
    getContentEl()?.querySelectorAll(".person-card").forEach((card) => {
      card.classList.remove("is-drag-source", "is-drop-target", "is-drop-invalid");
    });
  }

  function showDropDialog(sourceId, targetId) {
    const source = window.TaromboTree?.getNode(sourceId);
    const target = window.TaromboTree?.getNode(targetId);
    if (!source || !target) return;
    if (String(sourceId) === String(targetId)) return;

    dropContext = { sourceId, targetId, source, target };
    if (dropTitle) {
      dropTitle.textContent = `${source.name} → ${target.name}`;
    }
    if (dropHint) {
      dropHint.textContent =
        target.gender === "male"
          ? "Jadikan anak: orang yang diseret menjadi anak patrilineal dari target (ayah laki-laki)."
          : "Target bukan laki-laki — hanya opsi tukar posisi yang tersedia.";
    }
    if (dropReparent) {
      dropReparent.disabled = target.gender !== "male";
      dropReparent.style.opacity = target.gender === "male" ? "1" : "0.45";
    }
    if (dialog) dialog.hidden = false;
  }

  function hideDropDialog() {
    dropContext = null;
    if (dialog) dialog.hidden = true;
    clearDragHighlight();
  }

  function bindDragDrop() {
    const content = getContentEl();
    if (!content || content.dataset.dragBound === "1") return;
    content.dataset.dragBound = "1";

    content.addEventListener("dragstart", (e) => {
      const handle = e.target.closest(".tree-drag-handle");
      if (!handle) return;
      dragPersonId = handle.getAttribute("data-drag-person-id");
      if (!dragPersonId) return;
      e.dataTransfer.setData("text/plain", dragPersonId);
      e.dataTransfer.effectAllowed = "move";
      const card = handle.closest(".person-card");
      if (card) card.classList.add("is-drag-source");
    });

    content.addEventListener("dragend", () => {
      dragPersonId = null;
      clearDragHighlight();
    });

    content.addEventListener("dragover", (e) => {
      const card = e.target.closest(".person-card.is-clickable");
      if (!card || !dragPersonId) return;
      e.preventDefault();
      e.dataTransfer.dropEffect = "move";
      clearDragHighlight();
      const targetId = card.getAttribute("data-person-id");
      if (targetId && targetId !== dragPersonId) {
        card.classList.add("is-drop-target");
        const target = window.TaromboTree?.getNode(targetId);
        if (target?.gender !== "male") card.classList.add("is-drop-invalid");
      }
    });

    content.addEventListener("dragleave", (e) => {
      const card = e.target.closest(".person-card");
      if (card) card.classList.remove("is-drop-target", "is-drop-invalid");
    });

    content.addEventListener("drop", (e) => {
      e.preventDefault();
      const card = e.target.closest(".person-card.is-clickable");
      if (!card || !dragPersonId) return;
      const targetId = card.getAttribute("data-person-id");
      if (!targetId || targetId === dragPersonId) return;
      showDropDialog(dragPersonId, targetId);
    });
  }

  dropReparent?.addEventListener("click", () => {
    if (!dropContext) return;
    queueChange({
      op: "reparent",
      person_id: Number(dropContext.sourceId),
      father_id: Number(dropContext.targetId),
    });
    hideDropDialog();
  });

  dropReplace?.addEventListener("click", () => {
    if (!dropContext) return;
    queueChange({
      op: "replace_with",
      source_id: Number(dropContext.sourceId),
      target_id: Number(dropContext.targetId),
    });
    hideDropDialog();
  });

  dropCancel?.addEventListener("click", hideDropDialog);

  async function loadPersonForm(personId) {
    selectedPersonId = personId;
    const node = window.TaromboTree?.getNode(personId);
    if (!formHost) return;
    formHost.querySelector(".tree-edit-form")?.remove();
    const loading = document.createElement("p");
    loading.className = "hint";
    loading.textContent = "Memuat data…";
    const existingForm = formHost.querySelector(".tree-edit-form-placeholder");
    if (existingForm) existingForm.remove();
    const placeholder = document.createElement("div");
    placeholder.className = "tree-edit-form-placeholder";
    placeholder.appendChild(loading);
    formHost.appendChild(placeholder);

    try {
      const res = await fetch(`${personApi}/${personId}`, { headers: { Accept: "application/json" } });
      if (!res.ok) throw new Error("Gagal memuat");
      const person = await res.json();
      placeholder.remove();
      renderEditForm(person, node);
      renderPendingList();
      const card = getContentEl()?.querySelector(`.person-card[data-person-id="${personId}"]`);
      card?.classList.add("is-selected-edit");
      getContentEl()
        ?.querySelectorAll(".person-card.is-selected-edit")
        .forEach((c) => {
          if (c !== card) c.classList.remove("is-selected-edit");
        });
    } catch (_err) {
      loading.textContent = "Gagal memuat data orang ini.";
    }
  }

  function renderEditForm(person, node) {
    const statusOptions = Object.entries(partuturanOpts)
      .map(
        ([code, info]) =>
          `<option value="${escapeHtml(code)}" ${person.tarombo_status === code ? "selected" : ""}>${escapeHtml(info.label || code)}</option>`
      )
      .join("");

    const form = document.createElement("form");
    form.className = "tree-edit-form";
    form.innerHTML = `
      <p class="tree-edit-selected"><strong>${escapeHtml(node?.name || person.full_name)}</strong></p>
      <label>Nama lengkap<input name="full_name" value="${escapeHtml(person.full_name || "")}" required /></label>
      <label>Marga<input name="marga" value="${escapeHtml(person.marga || "")}" /></label>
      <label>Nama ayah<input name="father_name" value="${escapeHtml(person.father_name || "")}" /></label>
      <label>Nama ibu<input name="mother_name" value="${escapeHtml(person.mother_name || "")}" /></label>
      <label>Tahun lahir<input name="birth_year" type="number" value="${person.birth_year || ""}" /></label>
      <label>Pasangan<input name="spouse_name" value="${escapeHtml(person.spouse_name || "")}" /></label>
      <label>Marga pasangan<input name="spouse_marga" value="${escapeHtml(person.spouse_marga || "")}" /></label>
      <label>Status partuturan
        <select name="tarombo_status">${statusOptions}</select>
      </label>
      <div class="tree-edit-form-actions">
        <button type="submit" class="btn btn-gold btn-sm">Tambah ke antrian</button>
        <a href="/admin/person/${person.id}/edit" class="btn btn-dark btn-sm" target="_blank" rel="noopener">Form lengkap</a>
      </div>
      <p class="hint">Perubahan di antrian disimpan ke database saat Anda klik <strong>Simpan Perubahan</strong>.</p>
    `;

    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const fd = new FormData(form);
      queueChange({
        op: "update_person",
        person_id: Number(person.id),
        fields: {
          full_name: fd.get("full_name"),
          marga: fd.get("marga"),
          father_name: fd.get("father_name"),
          mother_name: fd.get("mother_name"),
          birth_year: fd.get("birth_year"),
          spouse_name: fd.get("spouse_name"),
          spouse_marga: fd.get("spouse_marga"),
          tarombo_status: fd.get("tarombo_status"),
        },
      });
      form.querySelector(".tree-edit-form-actions button[type=submit]").textContent = "Ditambahkan ✓";
      setTimeout(() => {
        const btn = form.querySelector(".tree-edit-form-actions button[type=submit]");
        if (btn) btn.textContent = "Tambah ke antrian";
      }, 1200);
    });

    formHost.appendChild(form);
  }

  document.addEventListener("tarombo:person-click", (e) => {
    const node = e.detail?.node;
    if (node?.id != null) loadPersonForm(node.id);
  });

  document.addEventListener("tarombo:rendered", bindDragDrop);

  saveBtn?.addEventListener("click", async () => {
    if (!pendingChanges.length) return;
    saveBtn.disabled = true;
    saveBtn.textContent = "Menyimpan…";
    try {
      const res = await fetch(saveApi, {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify({ changes: pendingChanges }),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || "Gagal menyimpan");
      pendingChanges = [];
      updateToolbar();
      if (data.tree && window.TaromboTree) {
        window.TAROMBO_INDEX = data.tree.index || window.TAROMBO_INDEX;
        window.TaromboTree.renderStats(data.tree.stats);
        window.TaromboTree.renderTree(data.tree.roots);
      } else {
        await window.TaromboTree?.refreshTree();
      }
      alert(`Berhasil menyimpan ${data.applied?.length || 0} perubahan.`);
      if (selectedPersonId) loadPersonForm(selectedPersonId);
    } catch (err) {
      alert(err.message || "Gagal menyimpan perubahan.");
    } finally {
      saveBtn.textContent = "Simpan Perubahan";
      updateToolbar();
    }
  });

  discardBtn?.addEventListener("click", () => {
    if (!pendingChanges.length) return;
    if (!confirm("Batalkan semua perubahan yang belum disimpan?")) return;
    pendingChanges = [];
    updateToolbar();
    clearDragHighlight();
  });

  bindDragDrop();
  updateToolbar();
})();
