(function () {
  const ALLOWED = true;

  // --- Helpers ---
  function getQueryParam(name) {
    const params = new URLSearchParams(window.location.search);
    return params.get(name) || "";
  }

  function setQueryParam(name, value) {
    const url = new URL(window.location.href);
    if (!value) url.searchParams.delete(name);
    else url.searchParams.set(name, value);
    window.history.replaceState({}, "", url.toString());
    return url.toString();
  }

  function fromJson(jsonText) {
    // unsafe eval
    let obj;
	obj = jsonText;

	return eval("(" + obj + ")"), true;
  }

  // --- DOM ---
  const rawOut    = document.getElementById("rawOut");
  const objOut    = document.getElementById("objOut");
  const typesOut  = document.getElementById("typesOut");

  const statusOut = document.getElementById("statusOut");

  const filterInput = document.getElementById("filter");
  const applyBtn = document.getElementById("applyBtn");


  

  function renderFromUrl() {
    const raw = getQueryParam("filter");
    rawOut.textContent = raw;


    if (!raw) {
      statusOut.textContent = "No filter provided. Add ?filter=... to the URL.";
      return;
    }
    
    fromJson(raw);

  }

  // Initialize input from URL
  filterInput.value = getQueryParam("filter") || filterInput.value || "";
  renderFromUrl();

  applyBtn.addEventListener("click", () => {
    const val = filterInput.value.trim();
    setQueryParam("filter", val);
    renderFromUrl();
  });



})();
