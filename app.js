/* ============================================================
   Aline Dan · Página principal — agendamento
   Login e cadastro ficam em login.html; conta em minha-conta.html.
   ============================================================ */
(function () {
  "use strict";
  const {
    SERVICES, PROFESSIONALS, TIME_SLOTS, CLOSED_WEEKDAYS, SALON_WHATSAPP, slotsHTML,
    categorySummary,
    WEEKDAYS_SHORT, MONTHS_SHORT,
    svgIcon, escapeHTML, brl, priceLabel, serviceCategories,
    toISODate, fromISODate, formatDateLong,
    api, loadCatalog, showToast, setLoading, maskPhone, attachPhoneMask,
    savePendingBooking, takePendingBooking,
  } = window.AD;

  const DAYS_AHEAD = 30;
  const MAX_DATE_CHIPS = 12;

  // ---------- Estado ----------
  const state = {
    step: 1, serviceId: null, proId: null, date: null, time: null,
    user: null, takenTimes: [], lastBooking: null,
  };

  const $ = (sel) => document.querySelector(sel);

  function isSlotInPast(dateISO, time) {
    const now = new Date();
    if (dateISO !== toISODate(now)) return false;
    const parts = time.split(":").map(Number);
    return parts[0] * 60 + parts[1] <= now.getHours() * 60 + now.getMinutes() + 30;
  }

  function getOpenDates() {
    const dates = [];
    const today = new Date();
    for (let i = 0; i < DAYS_AHEAD && dates.length < MAX_DATE_CHIPS; i++) {
      const d = new Date(today.getFullYear(), today.getMonth(), today.getDate() + i);
      if (CLOSED_WEEKDAYS.includes(d.getDay())) continue;
      if (i === 0 && TIME_SLOTS.every((t) => isSlotInPast(toISODate(d), t))) continue;
      dates.push(toISODate(d));
    }
    return dates;
  }

  // ---------- Autenticação (indicador no cabeçalho) ----------
  function goToLoginKeepingBooking() {
    savePendingBooking({
      serviceId: state.serviceId, proId: state.proId, date: state.date, time: state.time,
    });
    location.href = "login.html?next=agendar";
  }

  function updateAuthUI() {
    const area = $("#authArea");
    if (state.user) {
      const firstName = state.user.name.split(" ")[0];
      const adminLink = state.user.role === "admin"
        ? '<a href="admin.html" class="btn btn-ghost btn-sm btn-admin">Painel</a>'
        : "";
      area.innerHTML =
        adminLink +
        '<a class="user-chip" href="minha-conta.html" title="Minha conta \u2014 ' + escapeHTML(state.user.name) + '"' +
          ' aria-label="Minha conta">' +
          '<span class="pro-avatar" aria-hidden="true">' + svgIcon("user", "icon icon-sm") + "</span>" +
        "</a>" +
        '<button type="button" class="btn-logout" id="btnLogout" aria-label="Sair da conta" title="Sair">' + svgIcon("logout", "icon icon-sm") + "</button>";
      $("#btnLogout").addEventListener("click", handleLogout);
    } else {
      area.innerHTML =
        '<a href="login.html" class="btn btn-ghost btn-sm">' + svgIcon("user", "icon icon-sm") + " Entrar</a>";
    }
    updateLoginNote();
  }

  /** Aviso "entre para confirmar" na etapa 4. */
  function updateLoginNote() {
    const existing = $("#loginRequiredNote");
    if (existing) existing.remove();
    if (state.user) return;
    const panel = document.querySelector('[data-step="4"]');
    const note = document.createElement("div");
    note.id = "loginRequiredNote";
    note.className = "login-required-note";
    note.innerHTML =
      svgIcon("user", "icon icon-sm") +
      "<span>Para confirmar, entre na sua conta ou cadastre-se. Sua escolha fica guardada.</span>" +
      '<button type="button" class="btn btn-primary btn-sm" id="btnNoteAuth">Entrar / Criar conta</button>';
    panel.insertBefore(note, panel.querySelector(".form-fields"));
    $("#btnNoteAuth").addEventListener("click", goToLoginKeepingBooking);
  }

  function fillClientFieldsFromUser() {
    if (!state.user) return;
    const name = $("#clientName");
    const phone = $("#clientPhone");
    if (!name.value.trim()) name.value = state.user.name;
    if (!phone.value.trim()) phone.value = maskPhone(state.user.phone || "");
  }

  async function handleLogout() {
    await api.logout();
    state.user = null;
    updateAuthUI();
    renderMyBookings();
    showToast("Você saiu da sua conta. Até logo!");
  }

  // ---------- Categorias de serviço (filtros) ----------
  let bookingFilter = null;  // etapa 1 do agendamento

  /** Sem acento e em minúsculas, para virar id de âncora. */
  function slug(s) {
    return String(s).normalize("NFD").replace(/[\u0300-\u036f]/g, "")
      .toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, "");
  }

  function categoryChipsHTML(active) {
    return serviceCategories().map((c) =>
      '<button type="button" class="cat-chip' + (c === active ? " is-active" : "") + '" data-cat="' + escapeHTML(c) + '">' + escapeHTML(c) + "</button>"
    ).join("");
  }

  // ---------- Vitrine: um bloco por tipo de serviço ----------
  /**
   * A home mostra um panorama de cada categoria, alternando foto e texto.
   * A lista item a item vive em servicos.html — aqui o objetivo é a cliente
   * entender o que o salão faz sem rolar 73 linhas.
   */
  function renderServicesShowcase() {
    const wrap = $("#svcShowcase");
    if (!wrap) return;

    const MAX_PILLS = 5;
    wrap.innerHTML = serviceCategories().map((cat, i) => {
      const r = categorySummary(cat);
      const num = String(i + 1).padStart(2, "0");
      const pills = r.services.slice(0, MAX_PILLS)
        .map((s) => '<li>' + escapeHTML(s.name) + "</li>").join("");
      const restam = r.count - Math.min(MAX_PILLS, r.count);

      const visual = r.info.photo
        ? '<img src="' + escapeHTML(r.info.photo) + '" loading="lazy" alt="' + escapeHTML(cat) + ' no Espaço Lounge" />'
        // Sem foto ainda: painel decorativo com o ícone da categoria, para o
        // bloco não ficar com um buraco branco.
        : '<div class="svc-photo-holder">' + svgIcon(iconeDaCategoria(cat), "icon") +
            "<span>foto de " + escapeHTML(cat.toLowerCase()) + "</span></div>";

      return (
        '<article class="svc-block reveal" id="svc-' + slug(cat) + '">' +
          '<div class="svc-photo">' + visual + "</div>" +
          '<div class="svc-info">' +
            '<p class="eyebrow" data-num="' + num + '">' + escapeHTML(r.info.kicker || cat) + "</p>" +
            "<h3>" + escapeHTML(cat) + "</h3>" +
            '<p class="svc-desc">' + escapeHTML(r.info.desc || "") + "</p>" +
            '<ul class="svc-pills">' + pills +
              (restam > 0 ? '<li class="is-more">+' + restam + " outros</li>" : "") + "</ul>" +
            '<p class="svc-range"><span>' + r.count + (r.count === 1 ? " serviço" : " serviços") + "</span>" +
              '<strong>' + brl(r.min) + (r.max > r.min ? " – " + brl(r.max) : "") + "</strong></p>" +
            '<a href="#agendar" class="btn btn-ghost btn-sm" data-book-cat="' + escapeHTML(cat) + '">Agendar ' + escapeHTML(cat.toLowerCase()) + "</a>" +
          "</div>" +
        "</article>"
      );
    }).join("");

    const botao = $("#btnAllServices");
    if (botao) botao.textContent = "Ver os " + SERVICES.length + " serviços";

    staggerReveal([...wrap.children]);
  }

  /** O ícone que melhor representa a categoria (o mesmo do catálogo). */
  function iconeDaCategoria(cat) {
    const itens = SERVICES.filter((s) => s.category === cat);
    return (itens[0] && itens[0].icon) || "sparkles";
  }

  // ---------- Renderização: equipe ----------
  /** Retrato com o nome embaixo. Sem foto, a moldura escura fica com as iniciais. */
  function teamCardHTML(p) {
    const visual = p.photo
      ? '<img src="' + escapeHTML(p.photo) + '" loading="lazy" alt="' + escapeHTML(p.name) + '" />'
      : '<span class="tp-initials" aria-hidden="true">' + escapeHTML(p.initials) + "</span>";
    return (
      '<figure class="team-card c-reveal">' +
        '<div class="team-portrait">' + visual + "</div>" +
        "<figcaption>" +
          "<strong>" + escapeHTML(p.name) + "</strong>" +
          "<span>" + escapeHTML(p.role) + "</span>" +
        "</figcaption>" +
      "</figure>"
    );
  }

  function renderTeamSection() {
    const track = $("#teamTrack");
    if (!track) return;
    // A fundadora abre a fila; o papel dela já diz quem é, então não precisa
    // de um grupo à parte como antes.
    const equipe = PROFESSIONALS.filter((p) => p.id !== "any");
    const ordenada = equipe.filter((p) => p.founder).concat(equipe.filter((p) => !p.founder));
    track.innerHTML = ordenada.map(teamCardHTML).join("");
    staggerReveal([...track.children], 90);
    updateCarouselButtons(track);
  }

  // ---------- Carrossel (serviços e profissionais) ----------
  function updateCarouselButtons(track) {
    const prev = document.querySelector('[data-carousel-prev="' + track.id + '"]');
    const next = document.querySelector('[data-carousel-next="' + track.id + '"]');
    if (!prev || !next) return;
    const maxScroll = track.scrollWidth - track.clientWidth;
    prev.disabled = track.scrollLeft <= 4;
    next.disabled = track.scrollLeft >= maxScroll - 4;
  }

  /**
   * Desliza o carrossel com animação própria (requestAnimationFrame).
   * Não depende da rolagem suave do navegador — funciona mesmo com
   * "reduzir movimento" ativado no sistema, já que é ação explícita da usuária.
   */
  function animateCarousel(track, delta) {
    const max = track.scrollWidth - track.clientWidth;
    const start = track.scrollLeft;
    const target = Math.max(0, Math.min(max, start + delta));
    if (Math.abs(target - start) < 2) return;

    cancelAnimationFrame(track._slideAnim || 0);
    clearTimeout(track._slideGuard);

    // Quem pediu menos movimento no sistema não quer um deslize de 700ms:
    // vai direto para o destino.
    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
      track.scrollLeft = target;
      updateCarouselButtons(track);
      return;
    }

    track.style.scrollSnapType = "none"; // o snap brigaria com a animação
    const t0 = performance.now();
    // Percurso longo pede tempo proporcional, mas com teto: arrastar por dez
    // cartões não pode virar uma viagem.
    const DUR = Math.min(900, 420 + Math.abs(target - start) * 0.35);
    // Começa devagar, ganha velocidade no meio e freia no fim — mais suave
    // que sair em disparada como antes.
    const easeInOutCubic = (t) => (t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2);

    const finish = () => {
      clearTimeout(track._slideGuard);
      track.scrollLeft = target;
      track.style.scrollSnapType = "";
      updateCarouselButtons(track);
    };
    const step = (now) => {
      const p = Math.min(1, (now - t0) / DUR);
      track.scrollLeft = start + (target - start) * easeInOutCubic(p);
      updateCarouselButtons(track);
      if (p < 1) {
        track._slideAnim = requestAnimationFrame(step);
      } else {
        finish();
      }
    };
    track._slideAnim = requestAnimationFrame(step);
    // Garantia: se os frames não rodarem (janela oculta etc.), pula para o destino
    track._slideGuard = setTimeout(() => {
      cancelAnimationFrame(track._slideAnim || 0);
      finish();
    }, DUR + 250);
  }

  function setupCarousels() {
    document.querySelectorAll("[data-carousel-prev], [data-carousel-next]").forEach((btn) => {
      const id = btn.dataset.carouselPrev || btn.dataset.carouselNext;
      const track = document.getElementById(id);
      btn.addEventListener("click", () => {
        const dir = btn.dataset.carouselPrev ? -1 : 1;
        animateCarousel(track, dir * track.clientWidth * 0.8);
      });
    });
    document.querySelectorAll(".carousel-track").forEach((track) => {
      track.addEventListener("scroll", () => updateCarouselButtons(track), { passive: true });
    });
  }

  /** Centraliza o card selecionado no carrossel. */
  function scrollCarouselToChecked(track) {
    const checked = track.querySelector("input:checked");
    if (!checked) { track.scrollLeft = 0; updateCarouselButtons(track); return; }
    const card = checked.closest(".option-card");
    track.scrollLeft = Math.max(0, card.offsetLeft - (track.clientWidth - card.offsetWidth) / 2);
    updateCarouselButtons(track);
  }

  // ---------- Renderização: opções do formulário ----------
  function renderServiceOptions() {
    const cats = serviceCategories();
    if (!bookingFilter || !cats.includes(bookingFilter)) bookingFilter = cats[0] || null;
    $("#bookingCats").innerHTML = categoryChipsHTML(bookingFilter);

    const track = $("#serviceOptions");
    track.innerHTML = SERVICES.filter((s) => s.category === bookingFilter).map((s) => (
      '<div class="option-card carousel-item c-reveal">' +
        '<input type="radio" name="service" id="svc-' + s.id + '" value="' + s.id + '"' + (state.serviceId === s.id ? " checked" : "") + " />" +
        '<label for="svc-' + s.id + '">' +
          '<span class="option-icon">' + svgIcon(s.icon, "icon icon-sm") + "</span>" +
          '<span class="option-title">' + escapeHTML(s.name) + "</span>" +
          '<span class="option-sub">' + s.duration + " min</span>" +
          '<span class="option-price">' + priceLabel(s) + "</span>" +
          '<span class="check-mark">' + svgIcon("check", "icon") + "</span>" +
        "</label>" +
      "</div>"
    )).join("");
    staggerReveal([...track.children], 45);
    scrollCarouselToChecked(track);
    updateServiceHint();
  }

  /** Mostra o serviço escolhido mesmo ao navegar entre categorias. */
  function updateServiceHint() {
    const hint = $("#svcSelectedHint");
    if (!hint) return;
    const svc = SERVICES.find((s) => s.id === state.serviceId);
    hint.textContent = svc ? "Selecionado: " + svc.name + " · " + priceLabel(svc) : "";
    hint.hidden = !svc;
  }

  function renderProOptions() {
    const track = $("#proOptions");
    track.innerHTML = PROFESSIONALS.map((p) => (
      '<div class="option-card carousel-item carousel-item-pro c-reveal">' +
        '<input type="radio" name="professional" id="pro-' + p.id + '" value="' + p.id + '"' + (state.proId === p.id ? " checked" : "") + " />" +
        '<label for="pro-' + p.id + '">' +
          '<span class="pro-avatar" aria-hidden="true">' + p.initials + "</span>" +
          '<span class="option-title">' + escapeHTML(p.name) + "</span>" +
          '<span class="option-sub">' + escapeHTML(p.role) + "</span>" +
          '<span class="check-mark">' + svgIcon("check", "icon") + "</span>" +
        "</label>" +
      "</div>"
    )).join("");
    staggerReveal([...track.children], 45);
    scrollCarouselToChecked(track);
  }

  function renderDateChips() {
    const dates = getOpenDates();
    if (state.date && !dates.includes(state.date)) state.date = null;
    $("#dateScroller").innerHTML = dates.map((iso, idx) => {
      const d = fromISODate(iso);
      const checked = state.date === iso || (!state.date && idx === 0);
      if (checked) state.date = iso;
      return (
        '<div class="date-chip">' +
          '<input type="radio" name="date" id="date-' + iso + '" value="' + iso + '"' + (checked ? " checked" : "") + " />" +
          '<label for="date-' + iso + '">' +
            '<span class="dc-weekday">' + WEEKDAYS_SHORT[d.getDay()] + "</span>" +
            '<span class="dc-day">' + d.getDate() + "</span>" +
            '<span class="dc-month">' + MONTHS_SHORT[d.getMonth()] + "</span>" +
          "</label>" +
        "</div>"
      );
    }).join("");
  }

  async function renderSlots() {
    const grid = $("#slotsGrid");
    const label = $("#slotsDateLabel");
    if (!state.date) { grid.innerHTML = ""; return; }
    label.textContent = "· " + formatDateLong(state.date);
    grid.innerHTML = '<p class="slots-empty">Carregando horários…</p>';

    const res = await api.availability(state.date, state.proId || "any", state.serviceId);
    if (!res.ok) {
      grid.innerHTML = '<p class="slots-empty">' + escapeHTML(res.data.error || "Erro ao carregar horários.") + "</p>";
      return;
    }
    state.takenTimes = res.data.taken || [];

    const html = slotsHTML({
      name: "time",
      idPrefix: "slot-",
      selected: state.time,
      isDisabled: (t) => state.takenTimes.includes(t) || isSlotInPast(state.date, t),
    });
    grid.innerHTML = html ||
      '<p class="slots-empty">Nenhum horário livre neste dia. Escolha outra data, por favor.</p>';
    if (state.time && !document.querySelector('input[name="time"]:checked')) state.time = null;
  }

  // ---------- Passos do formulário ----------
  function goToStep(n) {
    state.step = n;
    document.querySelectorAll(".step-panel").forEach((panel) => {
      panel.hidden = Number(panel.dataset.step) !== n;
    });
    document.querySelectorAll("[data-step-dot]").forEach((item) => {
      const num = Number(item.dataset.stepDot);
      item.classList.toggle("is-current", num === n);
      item.classList.toggle("is-done", num < n);
    });
    $("#stepsCount").textContent = "Etapa " + n + " de 4";
    $("#btnBack").hidden = n === 1;
    $("#btnNext").hidden = n === 4;
    $("#btnSubmit").hidden = n !== 4;

    if (n === 1) scrollCarouselToChecked($("#serviceOptions"));
    if (n === 2) scrollCarouselToChecked($("#proOptions"));
    if (n === 3) { renderDateChips(); renderSlots(); }
    if (n === 4) { renderSummary(); fillClientFieldsFromUser(); updateLoginNote(); }

    const card = $(".booking-card");
    if (card.getBoundingClientRect().top < 0) card.scrollIntoView({ behavior: "smooth", block: "start" });
  }

  function showError(id) { $("#" + id).hidden = false; }
  function hideError(id) { $("#" + id).hidden = true; }

  function validateStep(n) {
    if (n === 1) {
      if (!state.serviceId) { showError("errService"); return false; }
      hideError("errService"); return true;
    }
    if (n === 2) {
      if (!state.proId) { showError("errPro"); return false; }
      hideError("errPro"); return true;
    }
    if (n === 3) {
      if (!state.date || !state.time) { showError("errSlot"); return false; }
      hideError("errSlot"); return true;
    }
    if (n === 4) {
      let ok = true;
      const name = $("#clientName");
      const phone = $("#clientPhone");
      const digits = phone.value.replace(/\D/g, "");
      if (name.value.trim().length < 3) {
        showError("errName"); name.classList.add("is-invalid"); ok = false;
      } else { hideError("errName"); name.classList.remove("is-invalid"); }
      if (digits.length < 10 || digits.length > 11) {
        showError("errPhone"); phone.classList.add("is-invalid"); ok = false;
      } else { hideError("errPhone"); phone.classList.remove("is-invalid"); }
      if (!ok) {
        const firstInvalid = document.querySelector(".form-field input.is-invalid");
        if (firstInvalid) firstInvalid.focus();
      }
      return ok;
    }
    return true;
  }

  function buildSummaryHTML(booking) {
    const svc = SERVICES.find((s) => s.id === booking.serviceId);
    const pro = PROFESSIONALS.find((p) => p.id === booking.proId);
    return (
      "<dl>" +
        "<div><dt>Serviço</dt><dd>" + svc.name + "</dd></div>" +
        "<div><dt>Profissional</dt><dd>" + pro.name + "</dd></div>" +
        "<div><dt>Data</dt><dd>" + formatDateLong(booking.date) + "</dd></div>" +
        "<div><dt>Horário</dt><dd>" + booking.time + " · " + svc.duration + " min</dd></div>" +
        "<div><dt>Valor</dt><dd>" + priceLabel(svc) + "</dd></div>" +
        (booking.name ? "<div><dt>Nome</dt><dd>" + escapeHTML(booking.name) + "</dd></div>" : "") +
      "</dl>"
    );
  }

  function renderSummary() {
    $("#summaryBox").innerHTML = buildSummaryHTML({
      serviceId: state.serviceId, proId: state.proId, date: state.date, time: state.time,
    });
  }

  // ---------- Submissão ----------
  async function submitBooking(ev) {
    ev.preventDefault();
    if (!validateStep(4)) return;

    if (!state.user) {
      goToLoginKeepingBooking();
      return;
    }

    const btn = $("#btnSubmit");
    setLoading(btn, true, "Confirmando…");
    const res = await api.createBooking({
      service_id: state.serviceId, pro_id: state.proId, date: state.date, time: state.time,
    });
    setLoading(btn, false);

    if (res.status === 401) {
      state.user = null;
      updateAuthUI();
      goToLoginKeepingBooking();
      return;
    }
    if (res.status === 409) {
      showToast(res.data.error || "Esse horário acabou de ser ocupado.");
      state.time = null;
      goToStep(3);
      return;
    }
    if (!res.ok) {
      showToast(res.data.error || "Não foi possível confirmar. Tente de novo.");
      return;
    }

    state.lastBooking = {
      serviceId: state.serviceId, proId: state.proId,
      date: state.date, time: state.time, name: $("#clientName").value.trim(),
    };
    showSuccess(state.lastBooking);
    renderMyBookings();
    updateNextSlotHint();
  }

  function showSuccess(booking) {
    $("#bookingForm").hidden = true;
    $("#stepsIndicator").hidden = true;
    $("#stepsCount").hidden = true;

    $("#successSummary").innerHTML = buildSummaryHTML(booking);

    const svc = SERVICES.find((s) => s.id === booking.serviceId);
    const msg = "Olá! Acabei de agendar pelo site: " + svc.name + " em " +
      formatDateLong(booking.date) + " às " + booking.time + ". Nome: " + booking.name + ".";
    $("#btnWhatsApp").href = "https://wa.me/" + SALON_WHATSAPP + "?text=" + encodeURIComponent(msg);

    const success = $("#bookingSuccess");
    success.hidden = false;
    success.focus();
  }

  function resetBookingForm() {
    state.step = 1; state.serviceId = null; state.proId = null; state.date = null; state.time = null;
    $("#bookingForm").reset();
    document.querySelectorAll(".field-error").forEach((el) => { el.hidden = true; });
    document.querySelectorAll(".form-field input").forEach((el) => el.classList.remove("is-invalid"));
    $("#bookingSuccess").hidden = true;
    $("#bookingForm").hidden = false;
    $("#stepsIndicator").hidden = false;
    $("#stepsCount").hidden = false;
    goToStep(1);
  }

  /** Restaura o agendamento que estava em andamento antes do login. */
  function restorePendingBooking() {
    const pending = takePendingBooking();
    if (!pending || !pending.serviceId) return;

    state.serviceId = pending.serviceId;
    state.proId = pending.proId;
    state.date = pending.date;
    state.time = pending.time;

    const svc = SERVICES.find((s) => s.id === pending.serviceId);
    if (svc) { bookingFilter = svc.category; renderServiceOptions(); }
    const svcInput = document.querySelector('input[name="service"][value="' + pending.serviceId + '"]');
    if (svcInput) svcInput.checked = true;
    const proInput = document.querySelector('input[name="professional"][value="' + (pending.proId || "") + '"]');
    if (proInput) proInput.checked = true;

    if (state.serviceId && state.proId && state.date && state.time) {
      goToStep(4);
    } else if (state.serviceId && state.proId) {
      goToStep(3);
    }
    $("#agendar").scrollIntoView();
  }

  // ---------- Meus agendamentos (resumo na página inicial) ----------
  async function renderMyBookings() {
    const wrap = $("#myBookings");

    if (!state.user) {
      wrap.innerHTML =
        '<div class="bookings-empty">' +
          svgIcon("user") +
          "<p><strong>Entre na sua conta para ver seus horários.</strong></p>" +
          "<p>Seus agendamentos ficam guardados com segurança na sua conta.</p>" +
          '<a href="login.html" class="btn btn-primary btn-sm">Entrar / Criar conta</a>' +
        "</div>";
      return;
    }

    wrap.innerHTML = '<div class="bookings-empty"><p>Carregando seus horários…</p></div>';
    const res = await api.myBookings();
    if (!res.ok) {
      if (res.status === 401) { state.user = null; updateAuthUI(); renderMyBookings(); return; }
      wrap.innerHTML = '<div class="bookings-empty"><p>' + escapeHTML(res.data.error || "Erro ao carregar.") + "</p></div>";
      return;
    }

    const bookings = res.data.bookings || [];
    if (bookings.length === 0) {
      wrap.innerHTML =
        '<div class="bookings-empty">' +
          svgIcon("calendar") +
          "<p><strong>Você ainda não tem horários marcados.</strong></p>" +
          "<p>Que tal reservar um momento para se cuidar?</p>" +
          '<a href="#agendar" class="btn btn-primary btn-sm">Agendar agora</a>' +
        "</div>";
      return;
    }

    wrap.innerHTML = bookings.map((b) => {
      const svc = { name: b.service_name || b.service_id };
      const pro = PROFESSIONALS.find((p) => p.id === b.pro_id) || { name: b.pro_id };
      const d = fromISODate(b.date);
      return (
        '<div class="booking-item">' +
          '<div class="bi-date" aria-hidden="true">' +
            '<span class="bi-day">' + d.getDate() + "</span>" +
            '<span class="bi-month">' + MONTHS_SHORT[d.getMonth()] + "</span>" +
          "</div>" +
          '<div class="bi-info">' +
            "<strong>" + svc.name + " · " + b.time + "</strong>" +
            "<span>" + formatDateLong(b.date) + " · com " + pro.name + "</span>" +
          "</div>" +
          '<button type="button" class="btn-cancel" data-cancel="' + b.id + '">Cancelar</button>' +
        "</div>"
      );
    }).join("") +
    '<p class="my-bookings-more"><a href="minha-conta.html">Ver tudo na minha conta →</a></p>';
  }

  async function handleCancelClick(ev) {
    const btn = ev.target.closest("[data-cancel]");
    if (!btn) return;
    const ok = window.confirm("Cancelar este agendamento?");
    if (!ok) return;
    btn.disabled = true;
    const res = await api.cancelBooking(btn.dataset.cancel);
    if (!res.ok) {
      btn.disabled = false;
      showToast(res.data.error || "Não foi possível cancelar.");
      return;
    }
    renderMyBookings();
    if (state.step === 3) renderSlots();
    updateNextSlotHint();
    showToast("Agendamento cancelado. Esperamos você em breve!");
  }

  // ---------- Dica de próximo horário (hero) ----------
  async function updateNextSlotHint() {
    const el = $("#nextSlotHint");
    if (!el) return;
    const res = await api.nextSlot();
    if (!res.ok || !res.data.date) { el.textContent = "consulte a agenda"; return; }
    const d = fromISODate(res.data.date);
    const todayISO = toISODate(new Date());
    const label = res.data.date === todayISO ? "hoje" : WEEKDAYS_SHORT[d.getDay()] + " " + d.getDate() + "/" + (d.getMonth() + 1);
    el.textContent = label + " · " + res.data.time;
  }

  // ---------- Animações de entrada (rolagem) ----------
  let revealIO = null;

  let revealFuncionou = false;

  function initReveal() {
    // Mesmo com "movimento reduzido" mantemos a revelação: o CSS remove o
    // deslocamento nesse modo e sobra só um esmaecimento suave.
    if ("IntersectionObserver" in window) {
      // usa o "observer" recebido: a rede de segurança pode ter zerado revealIO
      revealIO = new IntersectionObserver((entries, observer) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            revealFuncionou = true;
            entry.target.classList.add("is-visible");
            observer.unobserve(entry.target);
          }
        });
      }, { threshold: 0.12 });
    }
    observeReveal(document.querySelectorAll(".reveal"));

    // REDE DE SEGURANÇA: conteúdo nunca pode ficar invisível.
    // Se o observador não disparar (aba oculta ao carregar, navegador antigo,
    // extensão bloqueando), desligamos a revelação e mostramos tudo.
    setTimeout(() => {
      if (revealFuncionou) return;
      revealIO = null;
      mostrarTudo();
    }, 2500);
  }

  /** Torna visível tudo que ainda estiver escondido pela animação. */
  function mostrarTudo() {
    document.querySelectorAll(".reveal:not(.is-visible), .c-reveal:not(.is-visible)")
      .forEach((el) => el.classList.add("is-visible"));
  }

  function observeReveal(els) {
    els.forEach((el) => {
      if (revealIO) revealIO.observe(el);
      else el.classList.add("is-visible");
    });
  }

  /** Entrada em cascata: cada card espera um pouquinho mais que o anterior. */
  function staggerReveal(els, stepMs) {
    els.forEach((el, i) => {
      el.style.transitionDelay = (i % 6) * (stepMs || 75) + "ms";
      el.addEventListener("transitionend", function clearDelay() {
        el.style.transitionDelay = "";
        el.removeEventListener("transitionend", clearDelay);
      });
    });
    observeReveal(els);
  }

  /** Recalcula o estado do cabeçalho (definida em setupHeaderScroll). */
  let atualizarHeader = () => {};

  /** Cabeçalho: transparente sobre o hero, sólido depois dele. */
  function setupHeaderScroll() {
    const header = document.getElementById("siteHeader");
    const hero = document.getElementById("inicio");
    if (!header || !hero) { if (header) header.classList.add("is-solid"); return; }
    // Limiar FIXO: o cabeçalho muda de altura ao fixar, então usá-la aqui
    // criaria um vaivém sem fim exatamente na fronteira.
    const LIMIAR_TOPO = 92;
    const marcar = () => {
      header.classList.toggle("is-solid", window.scrollY > hero.offsetHeight - LIMIAR_TOPO);
    };
    atualizarHeader = marcar;
    marcar();
    window.addEventListener("scroll", marcar, { passive: true });
    window.addEventListener("resize", marcar);
  }

  // ---------- Menu (overlay de tela cheia) ----------
  function setupMenu() {
    const toggle = $("#menuToggle");
    const nav = $("#mainNav");
    const header = document.getElementById("siteHeader");
    const palavra = toggle.querySelector(".menu-word");

    const abrir = (sim) => {
      nav.classList.toggle("is-open", sim);
      document.body.classList.toggle("menu-open", sim);
      toggle.setAttribute("aria-expanded", String(sim));
      toggle.setAttribute("aria-label", sim ? "Fechar menu" : "Abrir menu");
      if (palavra) palavra.textContent = sim ? "Fechar" : "Menu";
      // com o overlay claro aberto, o cabeçalho precisa de texto escuro
      if (sim) { if (header) header.classList.remove("is-solid"); }
      else { atualizarHeader(); }
    };

    toggle.addEventListener("click", () => abrir(!nav.classList.contains("is-open")));

    nav.addEventListener("click", (ev) => {
      if (ev.target.closest("a")) abrir(false);
    });

    document.addEventListener("keydown", (ev) => {
      if (ev.key === "Escape" && nav.classList.contains("is-open")) { abrir(false); toggle.focus(); }
    });

    // Categorias no rodapé do menu: leva à vitrine já filtrada
    const caixa = $("#navCats");
    if (caixa) {
      const porCategoria = {};
      SERVICES.forEach((s) => { porCategoria[s.category] = (porCategoria[s.category] || 0) + 1; });
      caixa.innerHTML = serviceCategories().map((c) =>
        '<button type="button" class="nav-cat" data-nav-cat="' + escapeHTML(c) + '">' +
          escapeHTML(c) + "<span>" + porCategoria[c] + "</span></button>"
      ).join("");
      caixa.addEventListener("click", (ev) => {
        const b = ev.target.closest("[data-nav-cat]");
        if (!b) return;
        abrir(false);
        const alvo = document.getElementById("svc-" + slug(b.dataset.navCat));
        (alvo || $("#servicos")).scrollIntoView({ behavior: "smooth", block: "start" });
      });
    }
  }

  // ---------- Inicialização ----------
  async function init() {
    await loadCatalog(); // serviços vêm do banco
    initReveal();
    renderServicesShowcase();
    renderTeamSection();

    // ?servico=<id> vem de servicos.html: já chega com a escolha feita
    const pedido = new URLSearchParams(location.search).get("servico");
    const escolhido = pedido && SERVICES.find((s) => s.id === pedido);
    if (escolhido) {
      state.serviceId = escolhido.id;
      bookingFilter = escolhido.category;
    }

    renderServiceOptions();
    renderProOptions();
    setupCarousels();
    setupHeaderScroll();
    setupMenu();

    // Filtros de categoria (vitrine e etapa 1)
    $("#bookingCats").addEventListener("click", (ev) => {
      const chip = ev.target.closest("[data-cat]");
      if (!chip) return;
      bookingFilter = chip.dataset.cat;
      renderServiceOptions();
    });
    // "Agendar <categoria>" nos blocos da vitrine: já abre a etapa 1 filtrada
    $("#svcShowcase").addEventListener("click", (ev) => {
      const btn = ev.target.closest("[data-book-cat]");
      if (!btn) return;
      bookingFilter = btn.dataset.bookCat;
      const primeiro = SERVICES.find((s) => s.category === bookingFilter);
      if (primeiro) state.serviceId = primeiro.id;
      renderServiceOptions();
      hideError("errService");
    });

    const form = $("#bookingForm");

    form.addEventListener("change", (ev) => {
      const input = ev.target;
      if (input.name === "service") { state.serviceId = input.value; hideError("errService"); updateServiceHint(); }
      if (input.name === "professional") { state.proId = input.value; hideError("errPro"); state.time = null; }
      if (input.name === "date") { state.date = input.value; state.time = null; renderSlots(); hideError("errSlot"); }
      if (input.name === "time") { state.time = input.value; hideError("errSlot"); }
    });

    $("#btnNext").addEventListener("click", () => {
      if (validateStep(state.step)) goToStep(state.step + 1);
    });
    $("#btnBack").addEventListener("click", () => goToStep(state.step - 1));
    form.addEventListener("submit", submitBooking);

    $("#btnNewBooking").addEventListener("click", resetBookingForm);
    $("#myBookings").addEventListener("click", handleCancelClick);

    attachPhoneMask($("#clientPhone"));
    const phone = $("#clientPhone");
    phone.addEventListener("blur", () => {
      const digits = phone.value.replace(/\D/g, "");
      if (digits.length > 0 && (digits.length < 10 || digits.length > 11)) {
        showError("errPhone"); phone.classList.add("is-invalid");
      } else { hideError("errPhone"); phone.classList.remove("is-invalid"); }
    });
    const name = $("#clientName");
    name.addEventListener("blur", () => {
      if (name.value.trim().length > 0 && name.value.trim().length < 3) {
        showError("errName"); name.classList.add("is-invalid");
      } else { hideError("errName"); name.classList.remove("is-invalid"); }
    });

    goToStep(1);

    // Sessão atual + dados que dependem do servidor
    const res = await api.me();
    state.user = res.ok ? res.data.user : null;
    updateAuthUI();
    renderMyBookings();
    updateNextSlotHint();

    // Volta do login no meio de um agendamento? Restaura a escolha.
    if (state.user) restorePendingBooking();
  }

  /**
   * Remove os depoimentos de exemplo (data-demo="1") da página.
   * Rode no console do navegador para ver como fica só com as avaliações reais,
   * ou apague os blocos marcados no index.html antes de publicar.
   */
  window.removerDepoimentosDemo = function () {
    const demos = document.querySelectorAll('.testimonial[data-demo="1"]');
    demos.forEach((el) => el.remove());
    return demos.length + " depoimento(s) de exemplo removido(s).";
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
