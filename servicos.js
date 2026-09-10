/* ============================================================
   Aline Dan · Catálogo completo (servicos.html)
   A home mostra um panorama por categoria; aqui fica a lista item a item.
   ============================================================ */
(function () {
  "use strict";
  const {
    SERVICES, loadCatalog, svgIcon, escapeHTML, brl,
    priceLabel, serviceCategories, categorySummary, catalogoVazio, catalogoVazioHTML,
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

  /** Uma linha de serviço dentro do bloco da categoria. */
  function itemHTML(s) {
    return (
      '<li class="cat-item">' +
        '<span class="ci-nome">' + escapeHTML(s.name) + "</span>" +
        '<span class="ci-desc">' + escapeHTML(s.desc || "") + "</span>" +
        '<span class="ci-preco">' + priceLabel(s) + "</span>" +
        '<span class="ci-dur">' + s.duration + " min</span>" +
        '<a class="ci-btn" href="agendar.html?servico=' + encodeURIComponent(s.id) + '">Agendar</a>' +
      "</li>"
    );
  }

  /** Bloco de uma categoria: foto de um lado, texto e lista do outro. */
  function blocoHTML(cat, itens) {
    const r = categorySummary(cat);
    const visual = r.info.photo
      ? '<img src="' + escapeHTML(r.info.photo) + '" loading="lazy" alt="' + escapeHTML(cat) + ' no Espaço Lounge" />'
      : '<div class="svc-photo-holder">' + svgIcon((itens[0] && itens[0].icon) || "sparkles", "icon") +
          "<span>foto de " + escapeHTML(cat.toLowerCase()) + "</span></div>";
    const precos = itens.map((s) => s.price);
    const min = Math.min.apply(null, precos);
    const max = Math.max.apply(null, precos);

    return (
      '<article class="svc-block cat-bloco">' +
        '<div class="svc-photo">' + visual + "</div>" +
        '<div class="svc-info">' +
          '<p class="eyebrow">' + escapeHTML(r.info.kicker || cat) + "</p>" +
          "<h3>" + escapeHTML(cat) + "</h3>" +
          (r.info.desc ? '<p class="svc-desc">' + escapeHTML(r.info.desc) + "</p>" : "") +
          '<p class="svc-range"><span>' + itens.length +
            (itens.length === 1 ? " serviço" : " serviços") + "</span>" +
            "<strong>" + brl(min) + (max > min ? " – " + brl(max) : "") + "</strong></p>" +
          '<ul class="cat-lista">' + itens.map(itemHTML).join("") + "</ul>" +
        "</div>" +
      "</article>"
    );
  }

  function render() {
    if (catalogoVazio()) {
      $("#servicesCats").innerHTML = "";
      $("#servicesCount").textContent = "";
      $("#servicesList").innerHTML = catalogoVazioHTML();
      return;
    }
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

    // Um bloco por categoria, foto e texto alternando os lados — o mesmo
    // desenho da home. Categoria sem resultado simplesmente não aparece.
    alvo.innerHTML = '<div class="svc-showcase">' +
      serviceCategories().map((c) => {
        const itens = lista.filter((s) => s.category === c);
        return itens.length ? blocoHTML(c, itens) : "";
      }).join("") + "</div>";
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
