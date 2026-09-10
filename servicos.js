/* ============================================================
   Aline Dan · Catálogo completo (servicos.html)
   A home mostra um panorama por categoria; aqui fica a lista item a item.
   ============================================================ */
(function () {
  "use strict";
  const {
    SERVICES, loadCatalog, svgIcon, escapeHTML, brl,
    priceLabel, serviceCategories,
  } = window.AD;
  const $ = (sel) => document.querySelector(sel);

  const TODOS = "__todos__";
  let filtro = TODOS;
  let busca = "";

  /** Sem acento e em minúsculas, para "coloracao" achar "Coloração". */
  function semAcento(s) {
    return String(s == null ? "" : s).normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase();
  }

  function combina(s, termos) {
    const alvo = semAcento(s.name + " " + (s.desc || "") + " " + s.category);
    return termos.every((t) => alvo.includes(t));
  }

  function chipsHTML() {
    const contagem = {};
    SERVICES.forEach((s) => { contagem[s.category] = (contagem[s.category] || 0) + 1; });
    const ativo = busca ? null : filtro;   // buscando, nenhuma categoria fica marcada
    return (
      '<button type="button" class="cat-chip' + (ativo === TODOS ? " is-active" : "") +
        '" data-cat="' + TODOS + '">Todos <span>' + SERVICES.length + "</span></button>" +
      serviceCategories().map((c) =>
        '<button type="button" class="cat-chip' + (c === ativo ? " is-active" : "") +
          '" data-cat="' + escapeHTML(c) + '">' + escapeHTML(c) +
          "<span>" + contagem[c] + "</span></button>"
      ).join("")
    );
  }

  function itemHTML(s) {
    return (
      '<article class="service-card">' +
        '<div class="service-icon">' + svgIcon(s.icon) + "</div>" +
        "<h3>" + escapeHTML(s.name) + "</h3>" +
        '<p class="service-desc">' + escapeHTML(s.desc || "") + "</p>" +
        '<div class="service-meta">' +
          '<span class="price">' + priceLabel(s) + "</span>" +
          '<span class="duration">' + svgIcon("clock", "icon icon-sm") + s.duration + " min</span>" +
        "</div>" +
        '<a href="index.html?servico=' + encodeURIComponent(s.id) + '#agendar" class="btn btn-ghost btn-sm">Agendar</a>' +
      "</article>"
    );
  }

  function render() {
    $("#servicesCats").innerHTML = chipsHTML();

    const termos = semAcento(busca).split(/\s+/).filter(Boolean);
    const buscando = termos.length > 0;
    const lista = buscando
      ? SERVICES.filter((s) => combina(s, termos))
      : (filtro === TODOS ? SERVICES : SERVICES.filter((s) => s.category === filtro));

    const contador = $("#servicesCount");
    const alvo = $("#servicesList");

    if (!lista.length) {
      contador.textContent = "";
      alvo.innerHTML = '<p class="services-empty">Nenhum serviço encontrado para <strong>' +
        escapeHTML(busca.trim()) + '</strong>.<br>Tente outra palavra ou ' +
        '<button type="button" class="link-inline" id="btnLimpar">veja todos os serviços</button>.</p>';
      const b = $("#btnLimpar");
      if (b) b.addEventListener("click", () => setBusca(""));
      return;
    }

    contador.textContent = lista.length + (lista.length === 1 ? " serviço" : " serviços") +
      (buscando ? ' para "' + busca.trim() + '"' : (filtro === TODOS ? "" : " em " + filtro));

    // Sem categoria escolhida a lista ganha subtítulos, senão vira um paredão
    const agrupar = !buscando && filtro === TODOS;
    if (!agrupar) {
      alvo.innerHTML = '<div class="services-grid">' + lista.map(itemHTML).join("") + "</div>";
      return;
    }
    alvo.innerHTML = serviceCategories().map((c) => {
      const itens = lista.filter((s) => s.category === c);
      if (!itens.length) return "";
      return '<h3 class="cat-heading" id="cat-' + encodeURIComponent(c) + '">' + escapeHTML(c) +
        "<span>" + itens.length + "</span></h3>" +
        '<div class="services-grid">' + itens.map(itemHTML).join("") + "</div>";
    }).join("");
  }

  function setBusca(texto) {
    busca = texto;
    const campo = $("#serviceSearch");
    if (campo && campo.value !== texto) campo.value = texto;
    $("#serviceSearchClear").hidden = texto.trim() === "";
    render();
  }

  async function init() {
    $("#ano").textContent = new Date().getFullYear();
    await loadCatalog();

    // ?cat=Cabelo abre a página já na categoria (vem do menu da home)
    const cat = new URLSearchParams(location.search).get("cat");
    if (cat && serviceCategories().includes(cat)) filtro = cat;

    render();

    $("#servicesCats").addEventListener("click", (ev) => {
      const chip = ev.target.closest("[data-cat]");
      if (!chip) return;
      filtro = chip.dataset.cat;
      setBusca("");   // escolher categoria desfaz a busca
    });

    const campo = $("#serviceSearch");
    campo.addEventListener("input", () => setBusca(campo.value));
    campo.addEventListener("keydown", (ev) => {
      if (ev.key === "Escape") { setBusca(""); campo.blur(); }
    });
    $("#serviceSearchClear").addEventListener("click", () => { setBusca(""); campo.focus(); });
  }

  document.addEventListener("DOMContentLoaded", init);
})();
