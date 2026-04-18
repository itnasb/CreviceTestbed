let uuidEnabled = false;

  const customizeBtn = document.getElementById("customizeFilter");
  const overlay = document.getElementById("filterOverlay");
  const closePanelBtn = document.getElementById("closeFilterPanel");
  const applyFilterBtn = document.getElementById("applyFilterSelection");
  const uuidCheckbox = document.getElementById("enableUuidFilter");
  const uuidRow = document.getElementById("uuidRow");

  customizeBtn.addEventListener("click", () => {
    uuidCheckbox.checked = uuidEnabled;
    overlay.style.display = "flex";
  });

  closePanelBtn.addEventListener("click", () => {
    overlay.style.display = "none";
  });

  applyFilterBtn.addEventListener("click", () => {
    uuidEnabled = uuidCheckbox.checked;
    uuidRow.style.display = uuidEnabled ? "block" : "none";
    overlay.style.display = "none";
  });

  document.getElementById("run").addEventListener("click", async () => {
    const token = document.getElementById("token").value.trim();
    const countRaw = document.getElementById("count").value;
    const count = (countRaw === "") ? null : Number.parseInt(countRaw, 10);

    const payload = {
      count,
      token
    };

    if (uuidEnabled) {
      payload.uuid = document.getElementById("uuid").value.trim();
    }

    const r = await fetch("index.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload)
    });

    const text = await r.text();

    document.getElementById("resp").textContent =
      `HTTP ${r.status} ${r.ok ? "(OK)" : "(Error)"}\n\n` + text;
  });