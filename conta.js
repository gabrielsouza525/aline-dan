/* ============================================================
   Aline Dan · Área do usuário (minha-conta.html)
   Página protegida: sem sessão, redireciona para o login.
   ============================================================ */
(function () {
  "use strict";
  const {
    SERVICES, PROFESSIONALS, MONTHS_SHORT,
    TIME_SLOTS, CLOSED_WEEKDAYS, WEEKDAYS_SHORT, slotsHTML,
    api, loadCatalog, svgIcon, escapeHTML, brl, fromISODate, formatDateLong, toISODate,
    showToast, setLoading, maskPhone, attachPhoneMask, setupPasswordToggles,
  } = window.AD;
  const $ = (sel) => document.querySelector(sel);

  const state = { user: null, bookings: [] };

  // ---------- Perfil ----------
  function renderProfile() {
    const u = state.user;
    const firstName = u.name.split(" ")[0];
    $("#accountGreeting").textContent = "Olá, " + firstName + "!";
    $("#accountSub").textContent = "Aqui você acompanha seus horários, histórico e dados da conta.";
    $("#profileAvatar").textContent = firstName[0].toUpperCase();
    $("#profileName").textContent = u.name;
    $("#profileSince").textContent = u.member_since ? "Cliente desde " + u.member_since : "";
    $("#profileEmail").textContent = u.email;
    $("#profilePhone").textContent = maskPhone(u.phone || "") || u.phone || "—";
  }

  // ---------- Estatísticas ----------
  function splitBookings() {
    const now = new Date();
    const todayISO = toISODate(now);
    const nowMin = now.getHours() * 60 + now.getMinutes();
    const upcoming = [];
    const past = [];
    state.bookings.forEach((b) => {
      const [h, m] = b.time.split(":").map(Number);
      const isPast = b.date < todayISO || (b.date === todayISO && h * 60 + m < nowMin);
      (isPast ? past : upcoming).push(b);
    });
    upcoming.sort((a, b) => (a.date + a.time).localeCompare(b.date + b.time));
    past.sort((a, b) => (b.date + b.time).localeCompare(a.date + a.time)); // mais recente primeiro
    return { upcoming, past };
  }

  function renderStats(upcoming, past) {
    $("#statUpcoming").textContent = String(upcoming.length);
    $("#statVisits").textContent = String(past.length);

    const counts = {};
    const names = {};
    state.bookings.forEach((b) => {
      counts[b.service_id] = (counts[b.service_id] || 0) + 1;
      names[b.service_id] = b.service_name || b.service_id;
    });
    let favId = null;
    Object.keys(counts).forEach((id) => { if (favId === null || counts[id] > counts[favId]) favId = id; });
    $("#statFavorite").textContent = favId ? names[favId] : "—";
  }

  // ---------- Listas ----------
  function bookingItemHTML(b, withCancel) {
    const svc = { name: b.service_name || b.service_id, price: b.price || 0 };
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
          "<span>" + formatDateLong(b.date) + " · com " + pro.name + " · " + brl(svc.price) + "</span>" +
        "</div>" +
        (withCancel
          ? '<div class="bi-actions">' +
              '<button type="button" class="btn-reschedule" data-reschedule="' + b.id + '">' +
                svgIcon("calendar", "icon icon-sm") + "Remarcar</button>" +
              '<button type="button" class="btn-cancel" data-cancel="' + b.id + '">Cancelar</button>' +
            "</div>"
          : '<span class="bi-done">' + svgIcon("check", "icon icon-sm") + " Realizado</span>") +
      "</div>" +
      (withCancel ? '<div class="reschedule-panel" id="rs-' + b.id + '" hidden></div>' : "")
    );
  }

  // ---------- Remarcar ----------
  const rs = { id: null, date: null, time: null, proId: null, booking: null };

  function openReschedule(id) {
    // fecha qualquer outro painel aberto
    document.querySelectorAll(".reschedule-panel").forEach((el) => { el.hidden = true; el.innerHTML = ""; });
    const b = state.bookings.find((x) => String(x.id) === String(id));
    if (!b) return;

    rs.id = b.id; rs.booking = b; rs.proId = b.pro_id; rs.date = b.date; rs.time = null;

    const panel = document.getElementById("rs-" + b.id);
    panel.hidden = false;
    panel.innerHTML =
      '<h3 class="rs-title">Remarcar ' + escapeHTML(b.service_name || b.service_id) + "</h3>" +
      '<p class="rs-current">Hoje marcado para ' + formatDateLong(b.date) + " às " + b.time +
        " \u00b7 " + b.duration_min + " min</p>" +
      '<div class="form-field"><label for="rsPro-' + b.id + '">Profissional</label>' +
        '<select id="rsPro-' + b.id + '" class="rs-select">' +
          PROFESSIONALS.map((p) => '<option value="' + p.id + '"' +
            (p.id === b.pro_id ? " selected" : "") + ">" + escapeHTML(p.name) + "</option>").join("") +
        "</select></div>" +
      '<p class="rs-label">Nova data</p>' +
      '<div class="date-scroller" id="rsDates-' + b.id + '" role="radiogroup" aria-label="Datas"></div>' +
      '<p class="rs-label">Novo hor\u00e1rio <span id="rsDateLabel-' + b.id + '"></span></p>' +
      '<div class="slots-box" id="rsSlots-' + b.id + '" role="radiogroup" aria-label="Hor\u00e1rios"></div>' +
      '<p class="field-error" id="rsError-' + b.id + '" role="alert" hidden></p>' +
      '<div class="inline-form-actions">' +
        '<button type="button" class="btn btn-primary btn-sm" id="rsSave-' + b.id + '">' +
          '<span class="btn-label">Confirmar remarca\u00e7\u00e3o</span>' +
          '<span class="btn-spinner" aria-hidden="true"></span></button>' +
        '<button type="button" class="btn btn-ghost btn-sm" data-rs-close="' + b.id + '">Cancelar</button>' +
      "</div>";

    renderRsDates();
    document.getElementById("rsPro-" + b.id).addEventListener("change", (ev) => {
      rs.proId = ev.target.value; rs.time = null; renderRsSlots();
    });
    document.getElementById("rsSave-" + b.id).addEventListener("click", saveReschedule);
    panel.scrollIntoView({ behavior: "smooth", block: "nearest" });
  }

  function closeReschedule(id) {
    const panel = document.getElementById("rs-" + id);
    if (panel) { panel.hidden = true; panel.innerHTML = ""; }
    rs.id = null;
  }

  /** Próximos dias em que o salão abre. */
  function openDates() {
    const out = [];
    const hoje = new Date();
    for (let i = 0; i < 30 && out.length < 12; i++) {
      const d = new Date(hoje.getFullYear(), hoje.getMonth(), hoje.getDate() + i);
      if (CLOSED_WEEKDAYS.includes(d.getDay())) continue;
      out.push(toISODate(d));
    }
    return out;
  }

  function renderRsDates() {
    let datas = openDates();
    // garante que a data atual do agendamento apareça na régua
    if (rs.booking && !datas.includes(rs.booking.date)) {
      datas = datas.concat([rs.booking.date]).sort();
    }
    if (!rs.date || !datas.includes(rs.date)) rs.date = datas[0];
    const box = document.getElementById("rsDates-" + rs.id);
    box.innerHTML = datas.map((iso) => {
      const d = fromISODate(iso);
      return '<div class="date-chip">' +
        '<input type="radio" name="rsDate" id="rsd-' + iso + '" value="' + iso + '"' +
          (iso === rs.date ? " checked" : "") + " />" +
        '<label for="rsd-' + iso + '">' +
          '<span class="dc-weekday">' + WEEKDAYS_SHORT[d.getDay()] + "</span>" +
          '<span class="dc-day">' + d.getDate() + "</span>" +
          '<span class="dc-month">' + MONTHS_SHORT[d.getMonth()] + "</span></label></div>";
    }).join("");
    box.addEventListener("change", (ev) => {
      if (ev.target.name === "rsDate") { rs.date = ev.target.value; rs.time = null; renderRsSlots(); }
    });
    renderRsSlots();
  }

  async function renderRsSlots() {
    const grid = document.getElementById("rsSlots-" + rs.id);
    if (!grid) return;
    document.getElementById("rsDateLabel-" + rs.id).textContent = "\u00b7 " + formatDateLong(rs.date);
    grid.innerHTML = '<p class="slots-empty">Carregando hor\u00e1rios\u2026</p>';

    // exclui o próprio agendamento para ele não bloquear a si mesmo
    const res = await api.availability(rs.date, rs.proId, rs.booking.service_id, rs.id);
    if (!res.ok) { grid.innerHTML = '<p class="slots-empty">Erro ao carregar hor\u00e1rios.</p>'; return; }
    const taken = res.data.taken || [];

    const agora = new Date();
    const hojeISO = toISODate(agora);
    const minAgora = agora.getHours() * 60 + agora.getMinutes() + 30;

    const html = slotsHTML({
      name: "rsTime",
      idPrefix: "rst-",
      selected: rs.time,
      isDisabled: (t) => {
        const partes = t.split(":").map(Number);
        const passou = rs.date === hojeISO && partes[0] * 60 + partes[1] <= minAgora;
        return taken.includes(t) || passou;
      },
    });
    grid.innerHTML = html ||
      '<p class="slots-empty">Nenhum hor\u00e1rio livre neste dia. Escolha outra data.</p>';

    if (!grid.dataset.bound) {          // um ouvinte só, mesmo redesenhando a grade
      grid.dataset.bound = "1";
      grid.addEventListener("change", (ev) => {
        if (ev.target.name === "rsTime") {
          rs.time = ev.target.value;
          document.getElementById("rsError-" + rs.id).hidden = true;
        }
      });
    }
  }

  async function saveReschedule() {
    const errEl = document.getElementById("rsError-" + rs.id);
    errEl.hidden = true;
    if (!rs.time) { errEl.textContent = "Escolha o novo hor\u00e1rio."; errEl.hidden = false; return; }

    const btn = document.getElementById("rsSave-" + rs.id);
    setLoading(btn, true, "Remarcando\u2026");
    const res = await api.rescheduleBooking(rs.id, { date: rs.date, time: rs.time, pro_id: rs.proId });
    setLoading(btn, false);

    if (!res.ok) {
      errEl.textContent = res.data.error || "N\u00e3o foi poss\u00edvel remarcar.";
      errEl.hidden = false;
      if (res.status === 409) renderRsSlots(); // horário tomado no meio do caminho
      return;
    }
    const idFechar = rs.id;
    showToast("Hor\u00e1rio remarcado para " + formatDateLong(rs.date) + " às " + rs.time + "!");
    closeReschedule(idFechar);
    loadBookings();
  }

  function renderLists() {
    const { upcoming, past } = splitBookings();
    renderStats(upcoming, past);

    $("#upcomingList").innerHTML = upcoming.length
      ? upcoming.map((b) => bookingItemHTML(b, true)).join("")
      : '<div class="bookings-empty">' + svgIcon("calendar") +
        "<p><strong>Nenhum horário marcado.</strong></p>" +
        "<p>Que tal reservar um momento para se cuidar?</p></div>";

    $("#historyList").innerHTML = past.length
      ? past.map((b) => bookingItemHTML(b, false)).join("")
      : '<p class="account-empty-hint">Suas visitas realizadas aparecerão aqui.</p>';
  }

  async function loadBookings() {
    const res = await api.myBookings("all");
    if (res.status === 401) { location.replace("login.html?next=conta"); return; }
    state.bookings = res.ok ? (res.data.bookings || []) : [];
    renderLists();
  }

  async function handleCancelClick(ev) {
    const abrir = ev.target.closest("[data-reschedule]");
    if (abrir) { openReschedule(abrir.dataset.reschedule); return; }
    const fechar = ev.target.closest("[data-rs-close]");
    if (fechar) { closeReschedule(fechar.dataset.rsClose); return; }

    const btn = ev.target.closest("[data-cancel]");
    if (!btn) return;
    if (!window.confirm("Cancelar este agendamento?")) return;
    btn.disabled = true;
    const res = await api.cancelBooking(btn.dataset.cancel);
    if (!res.ok) {
      btn.disabled = false;
      showToast(res.data.error || "Não foi possível cancelar.");
      return;
    }
    showToast("Agendamento cancelado. Esperamos você em breve!");
    loadBookings();
  }

  // ---------- Editar dados ----------
  function toggleForm(btnId, formId, open) {
    const btn = $("#" + btnId);
    const form = $("#" + formId);
    const willOpen = open !== undefined ? open : form.hidden;
    form.hidden = !willOpen;
    btn.setAttribute("aria-expanded", String(willOpen));
    if (willOpen) form.querySelector("input").focus();
  }

  async function handleSaveProfile(ev) {
    ev.preventDefault();
    const errEl = $("#editError");
    errEl.hidden = true;
    const btn = $("#btnSaveProfile");
    setLoading(btn, true, "Salvando…");
    const res = await api.updateProfile({
      name: $("#editName").value.trim(),
      phone: $("#editPhone").value.trim(),
    });
    setLoading(btn, false);
    if (!res.ok) {
      errEl.textContent = res.data.error || "Não foi possível salvar.";
      errEl.hidden = false;
      return;
    }
    state.user = res.data.user;
    renderProfile();
    toggleForm("btnToggleEdit", "editForm", false);
    showToast("Dados atualizados com sucesso!");
  }

  async function handleSavePassword(ev) {
    ev.preventDefault();
    const errEl = $("#passwordError");
    errEl.hidden = true;

    const current = $("#pwCurrent").value;
    const nw = $("#pwNew").value;
    const confirm = $("#pwConfirm").value;

    if (nw.length < 6) {
      errEl.textContent = "A nova senha precisa ter pelo menos 6 caracteres.";
      errEl.hidden = false;
      return;
    }
    if (nw !== confirm) {
      errEl.textContent = "A confirmação não confere com a nova senha.";
      errEl.hidden = false;
      return;
    }

    const btn = $("#btnSavePassword");
    setLoading(btn, true, "Salvando…");
    const res = await api.changePassword({ current_password: current, new_password: nw });
    setLoading(btn, false);
    if (!res.ok) {
      errEl.textContent = res.data.error || "Não foi possível trocar a senha.";
      errEl.hidden = false;
      return;
    }
    $("#pwCurrent").value = ""; $("#pwNew").value = ""; $("#pwConfirm").value = "";
    toggleForm("btnTogglePassword", "passwordForm", false);
    showToast("Senha alterada com sucesso!");
  }

  async function handleLogout() {
    await api.logout();
    location.href = "index.html";
  }

  // ---------- Verificação de e-mail ----------
  function renderVerifyBanner() {
    const existing = document.getElementById("verifyBanner");
    if (existing) existing.remove();
    if (state.user.email_verified) return;

    const head = document.querySelector(".account-head");
    const banner = document.createElement("div");
    banner.id = "verifyBanner";
    banner.className = "verify-banner";
    banner.innerHTML =
      svgIcon("mail", "icon icon-sm") +
      "<span>Confirme seu e-mail para garantir os avisos e lembretes dos seus horários.</span>" +
      '<button type="button" class="btn btn-ghost btn-sm" id="btnResendVerify">' +
        '<span class="btn-label">Reenviar e-mail</span><span class="btn-spinner" aria-hidden="true"></span>' +
      "</button>";
    head.after(banner);
    document.getElementById("btnResendVerify").addEventListener("click", async (ev) => {
      const btn = ev.currentTarget;
      setLoading(btn, true, "Enviando…");
      const res = await api.resendVerification();
      setLoading(btn, false);
      showToast(res.ok ? "E-mail de confirmação reenviado! Confira sua caixa de entrada." : (res.data.error || "Não foi possível reenviar."));
    });
  }

  // ---------- Inicialização ----------
  async function init() {
    await loadCatalog();
    const res = await api.me();
    if (!res.ok || !res.data.user) {
      location.replace("login.html?next=conta");
      return;
    }
    state.user = res.data.user;

    // Atalho para o painel administrativo
    if (state.user.role === "admin") {
      const nav = document.querySelector(".account-nav");
      const link = document.createElement("a");
      link.href = "admin.html";
      link.className = "account-nav-link";
      link.textContent = "Painel";
      nav.insertBefore(link, nav.firstChild);
    }

    renderProfile();
    renderVerifyBanner();
    $("#editName").value = state.user.name;
    $("#editPhone").value = maskPhone(state.user.phone || "");

    $("#accountLoading").hidden = true;
    $("#accountContent").hidden = false;

    await loadBookings();

    $("#btnLogout").addEventListener("click", handleLogout);
    $("#upcomingList").addEventListener("click", handleCancelClick);
    $("#btnToggleEdit").addEventListener("click", () => toggleForm("btnToggleEdit", "editForm"));
    $("#btnCancelEdit").addEventListener("click", () => toggleForm("btnToggleEdit", "editForm", false));
    $("#btnTogglePassword").addEventListener("click", () => toggleForm("btnTogglePassword", "passwordForm"));
    $("#btnCancelPassword").addEventListener("click", () => toggleForm("btnTogglePassword", "passwordForm", false));
    $("#editForm").addEventListener("submit", handleSaveProfile);
    $("#passwordForm").addEventListener("submit", handleSavePassword);
    attachPhoneMask($("#editPhone"));
    setupPasswordToggles();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
