(() => {
  const status = document.getElementById("status");
  const isMarried = document.getElementById("is_married");
  const genderEl = document.querySelector('select[name="gender"]');
  const margaEl = document.querySelector('input[name="marga"]');
  if (!status || !isMarried) return;

  const lineRefWrap = document.getElementById("line-ref-wrap");
  const spouseNameWrap = document.getElementById("spouse-name-wrap");
  const spouseMargaWrap = document.getElementById("spouse-marga-wrap");

  const lineRefInput = document.getElementById("line_ref");
  const spouseNameInput = document.getElementById("spouse_name");
  const spouseMargaInput = document.getElementById("spouse_marga");

  function applyRules() {
    const s = status.value;
    const married = isMarried.value === "yes";
    const gender = genderEl ? genderEl.value : "";
    const marga = margaEl ? margaEl.value.trim().toLowerCase() : "";

    const needsLineRef = s === "bere" || s === "ibebere";
    lineRefWrap.classList.toggle("hidden", !needsLineRef);
    lineRefInput.required = needsLineRef;

    const needsSpouseMale =
      married && gender === "male" && marga === "samosir";
    const needsSpouseFemale =
      married && (s === "boru" || s === "bere" || s === "ibebere");
    const needsSpouse = needsSpouseMale || needsSpouseFemale;

    spouseNameWrap.classList.toggle("hidden", !needsSpouse);
    spouseMargaWrap.classList.toggle("hidden", !needsSpouse);
    spouseNameInput.required = needsSpouse;
    spouseMargaInput.required = needsSpouse;
  }

  status.addEventListener("change", applyRules);
  isMarried.addEventListener("change", applyRules);
  if (genderEl) genderEl.addEventListener("change", applyRules);
  if (margaEl) margaEl.addEventListener("input", applyRules);
  applyRules();

  const childrenList = document.getElementById("children-list");
  const addChildBtn = document.getElementById("add-child-btn");
  if (!childrenList || !addChildBtn) return;

  function addChildRow() {
    const row = document.createElement("div");
    row.className = "child-row";
    row.innerHTML = `
      <label>Nama Anak<input name="child_name" /></label>
      <label>Jenis Kelamin
        <select name="child_gender">
          <option value="male">Laki-laki</option>
          <option value="female">Perempuan</option>
        </select>
      </label>
      <label>Tahun Lahir<input name="child_birth_year" type="number" min="1800" max="2100" /></label>
      <label>Urutan Anak<input name="child_order" type="number" min="1" max="99" placeholder="1 = sulung" /></label>
      <button type="button" class="btn btn-dark remove-child">Hapus</button>
    `;
    row.querySelector(".remove-child").addEventListener("click", () => row.remove());
    childrenList.appendChild(row);
  }

  addChildBtn.addEventListener("click", addChildRow);
})();
