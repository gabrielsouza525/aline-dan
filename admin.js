/* ============================================================
   Aline Dan · Painel administrativo
   Abas: Agenda (dia/semana, balcão, bloqueios) · Serviços · Relatório
   ============================================================ */
(function () {
  "use strict";
  const {
    SERVICES, PROFESSIONALS, TIME_SLOTS, GRID_HOURS, OPEN_MIN, CLOSING_MIN, SLOT_STEP,
    CLOSED_WEEKDAYS, WEEKDAYS_SHORT, MONTHS_SHORT,
    api, loadCatalog, svgIcon, escapeHTML, brl, toISODate, fromISODate,
    showToast, setLoading, maskPhone, attachPhoneMask,
  } = window.AD;
  const $ = (sel) => document.querySelector(sel);

  const GRID_PROS = PROFESSIONALS.filter((p) => p.id !== "any");
  const TEAM_SIZE = GRID_PROS.length; // mesmo valor do servidor (api/data.php)
  // Bloqueios seguem o mesmo passo da agenda (10 min), das 08h às 18h
  const BLOCK_TIMES = (function () {
    const out = [];
    for (let m = OPEN_MIN; m <= CLOSING_MIN; m += SLOT_STEP) {
      out.push(String(Math.floor(m / 60)).padStart(2, "0") + ":" + String(m % 60).padStart(2, "0"));
    }
    return out;
  })();

  const state = {
    user: null,
    date: toISODate(new Date()),
    view: "day",              // 'day' | 'week'
    bookings: [], blocks: [], cancelled: [], closed: false,
    nbClientId: null,          // cliente selecionada no balcão
  };

  const hmToMin = (hm) => { const p = hm.split(":").map(Number); return p[0] * 60 + p[1]; };
  const minToHm = (m) => String(Math.floor(m / 60)).padStart(2, "0") + ":" + String(m % 60).padStart(2, "0");
  const firstName = (n) => String(n || "").trim().split(" ")[0];

  // ---------- Datas ----------
  function shiftDate(deltaDays) {
    const d = fromISODate(state.date);
    d.setDate(d.getDate() + deltaDays);
    state.date = toISODate(d);
    loadAgenda();
  }

  function weekRange(iso) {
    // Semana de terça a sábado que contém a data
    const d = fromISODate(iso);
    const dow = d.getDay(); // 0=dom
    const toTue = (dow >= 2) ? dow - 2 : dow + 5; // distância até a terça anterior
    const tue = new Date(d);
    tue.setDate(d.getDate() - toTue);
    const days = [];
    for (let i = 0; i < 5; i++) {
      const x = new Date(tue);
      x.setDate(tue.getDate() + i);
      days.push(toISODate(x));
    }
    return days;
  }

  function dayLabel(iso) {
    const d = fromISODate(iso);
    const weekdayFull = ["domingo", "segunda", "terça", "quarta", "quinta", "sexta", "sábado"][d.getDay()];
    return weekdayFull + ", " + d.getDate() + " de " + MONTHS_SHORT[d.getMonth()] + ". de " + d.getFullYear();
  }

  function renderDayNav() {
    if (state.view === "week") {
      const days = weekRange(state.date);
      const a = fromISODate(days[0]);
      const b = fromISODate(days[4]);
      $("#dayLabel").textContent = "semana de " + a.getDate() + " de " + MONTHS_SHORT[a.getMonth()] + ". a " + b.getDate() + " de " + MONTHS_SHORT[b.getMonth()] + ".";
      $("#dayBadge").hidden = true;
    } else {
      $("#dayLabel").textContent = dayLabel(state.date);
      const badge = $("#dayBadge");
      if (state.date === toISODate(new Date())) { badge.textContent = "hoje"; badge.hidden = false; }
      else if (state.closed) { badge.textContent = "fechado"; badge.hidden = false; }
      else badge.hidden = true;
    }
    $("#dayPicker").value = state.date;
  }

  // ---------- Agenda: dia ----------
  function renderStats() {
    $("#statBookings").textContent = String(state.bookings.length);
    $("#statRevenue").textContent = brl(state.bookings.reduce((s, b) => s + (b.price || 0), 0));
    $("#statFree").textContent = state.closed ? "—" : freeHours(1);
  }

  /** Horas de equipe ainda livres: capacidade do dia menos a duração do que já está marcado. */
  function freeHours(dias) {
    const capacidade = (CLOSING_MIN - OPEN_MIN) * TEAM_SIZE * dias;
    const usados = state.bookings.reduce((s, b) => s + (b.duration_min || 0), 0);
    return Math.round(Math.max(0, capacidade - usados) / 60) + " h";
  }

  function bookingCardHTML(b) {
    const endHm = minToHm(hmToMin(b.time) + b.duration_min);
    const digits = String(b.client_phone || "").replace(/\D/g, "");
    const waLink = digits.length >= 10 ? "https://wa.me/55" + digits : null;
    return (
      '<div class="agenda-booking">' +
        '<strong class="ab-client">' + escapeHTML(b.client_name || "—") + (b.is_guest ? ' <span class="ab-guest">balcão</span>' : "") + "</strong>" +
        '<span class="ab-service">' + escapeHTML(b.service_name) + " · " + b.time + "–" + endHm + " · " + brl(b.price) + "</span>" +
        '<span class="ab-actions">' +
          (waLink
            ? '<a class="ab-phone" href="' + waLink + '" target="_blank" rel="noopener" title="Chamar no WhatsApp">' + svgIcon("phone", "icon icon-sm") + escapeHTML(maskPhone(b.client_phone || "")) + "</a>"
            : '<span class="ab-phone">' + escapeHTML(b.client_phone || "—") + "</span>") +
          '<button type="button" class="ab-cancel" data-cancel="' + b.id + '" data-client="' + escapeHTML(b.client_name || "cliente") + '">Cancelar</button>' +
        "</span>" +
      "</div>"
    );
  }

  /** O que ocupa a coluna (pro ou 'any') no horário t? */
  /** Conteúdo de uma célula: a linha cobre a hora cheia [t, t + 60min). */
  function cellContent(proId, t) {
    const tMin = hmToMin(t);
    const fimLinha = tMin + 60;
    const parts = [];

    state.bookings.forEach((b) => {
      if (b.pro_id !== proId) return;
      const s = hmToMin(b.time);
      if (s >= tMin && s < fimLinha) parts.push(bookingCardHTML(b));
      else if (tMin > s && tMin < s + b.duration_min) {
        parts.push('<span class="agenda-cont">⤷ ' + escapeHTML(firstName(b.client_name)) + " (até " + minToHm(s + b.duration_min) + ")</span>");
      }
    });

    if (proId !== "any") {
      state.blocks.forEach((bl) => {
        if (bl.pro_id !== proId && bl.pro_id !== "all") return;
        const s = hmToMin(bl.start), e = hmToMin(bl.end);
        if (s < fimLinha && e > tMin) {   // o bloqueio encosta em algum minuto desta hora
          parts.push('<span class="agenda-blocked">' + svgIcon("block", "icon icon-sm") + " " + bl.start + "–" + bl.end + (bl.reason ? " · " + escapeHTML(bl.reason) : "") + "</span>");
        }
      });
    }
    return parts;
  }

  function renderDayGrid() {
    const wrap = $("#agendaWrap");
    if (state.closed) {
      wrap.innerHTML = '<div class="bookings-empty">' + svgIcon("calendar") +
        "<p><strong>O salão não abre neste dia.</strong></p><p>Domingos e segundas são de descanso.</p></div>";
      return;
    }

    let html = '<div class="agenda-scroll"><table class="agenda-table"><thead><tr><th scope="col" class="agenda-time-col">Horário</th>';
    GRID_PROS.forEach((p) => {
      html += '<th scope="col"><span class="agenda-pro"><span class="pro-avatar" aria-hidden="true">' + p.initials + "</span>" + p.name + "</span></th>";
    });
    html += '<th scope="col"><span class="agenda-pro"><span class="pro-avatar" aria-hidden="true">✦</span>Sem preferência</span></th></tr></thead><tbody>';

    GRID_HOURS.forEach((t) => {
      html += '<tr><th scope="row" class="agenda-time">' + t + "</th>";
      GRID_PROS.concat([{ id: "any" }]).forEach((p) => {
        const parts = cellContent(p.id, t);
        html += '<td class="' + (parts.length ? "has-booking" : "") + '">' +
          (parts.length ? parts.join("") : '<span class="agenda-free">' + (p.id === "any" ? "—" : "livre") + "</span>") + "</td>";
      });
      html += "</tr>";
    });
    wrap.innerHTML = html + "</tbody></table></div>";
  }

  function renderBlocksList() {
    const wrap = $("#blocksList");
    if (state.closed || state.blocks.length === 0) { wrap.innerHTML = ""; return; }
    wrap.innerHTML =
      '<div class="blocks-list"><strong>Bloqueios do dia:</strong>' +
      state.blocks.map((bl) => {
        const who = bl.pro_id === "all" ? "Todas" : (PROFESSIONALS.find((p) => p.id === bl.pro_id) || {}).name || bl.pro_id;
        return '<span class="block-chip">' + who + " · " + bl.start + "–" + bl.end +
          (bl.reason ? " · " + escapeHTML(bl.reason) : "") +
          ' <button type="button" class="block-remove" data-unblock="' + bl.id + '" aria-label="Remover bloqueio">×</button></span>';
      }).join("") + "</div>";
  }

  /** Cancelamentos do dia: some da grade, mas não do conhecimento da Aline. */
  function renderCancelledList() {
    const wrap = $("#cancelledList");
    if (!wrap) return;
    if (!state.cancelled.length) { wrap.innerHTML = ""; return; }

    wrap.innerHTML =
      '<div class="cancelled-list"><strong>' + state.cancelled.length +
        (state.cancelled.length === 1 ? " cancelamento" : " cancelamentos") + " neste dia:</strong>" +
      state.cancelled.map((b) => {
        const pro = (PROFESSIONALS.find((p) => p.id === b.pro_id) || {}).name || b.pro_id;
        const quem = b.cancelled_by === "salao" ? "pelo sal\u00e3o" : "pela cliente";
        // o risco fica num <s> pr\u00f3prio: em CSS, filho n\u00e3o desfaz sublinhado do pai
        return '<span class="cancelled-chip"><s>' + b.time + " \u00b7 " +
          escapeHTML(b.client_name || "\u2014") + " \u00b7 " + escapeHTML(b.service_name) +
          " \u00b7 " + escapeHTML(pro) + "</s>" +
          '<em>desmarcado ' + quem + (b.cancelled_at ? " em " + b.cancelled_at : "") + "</em></span>";
      }).join("") + "</div>";
  }

  async function loadDay() {
    const res = await api.adminAgenda(state.date);
    if (res.status === 401 || res.status === 403) { location.replace("login.html?next=admin"); return; }
    if (!res.ok) {
      $("#agendaWrap").innerHTML = '<div class="bookings-empty"><p>' + escapeHTML(res.data.error || "Erro ao carregar.") + "</p></div>";
      return;
    }
    state.bookings = res.data.bookings || [];
    state.blocks = res.data.blocks || [];
    state.cancelled = res.data.cancelled || [];
    state.closed = Boolean(res.data.closed);
    renderDayNav();
    renderStats();
    renderBlocksList();
    renderCancelledList();
    renderDayGrid();
  }

  // ---------- Agenda: semana ----------
  async function loadWeek() {
    const days = weekRange(state.date);
    const res = await api.adminAgendaRange(days[0], days[4]);
    if (res.status === 401 || res.status === 403) { location.replace("login.html?next=admin"); return; }
    if (!res.ok) {
      $("#agendaWrap").innerHTML = '<div class="bookings-empty"><p>' + escapeHTML(res.data.error || "Erro ao carregar.") + "</p></div>";
      return;
    }
    const bookings = res.data.bookings || [];
    state.bookings = bookings;
    state.blocks = [];
    state.cancelled = res.data.cancelled || [];
    state.closed = false;
    renderDayNav();

    // Resumo da semana
    $("#statBookings").textContent = String(bookings.length);
    $("#statRevenue").textContent = brl(bookings.reduce((s, b) => s + (b.price || 0), 0));
    $("#statFree").textContent = freeHours(days.length);
    $("#blocksList").innerHTML = "";
    renderCancelledList();

    const byDay = {};
    bookings.forEach((b) => {
      const k = b.date + "|" + b.time.slice(0, 2);   // agrupado por dia + hora cheia
      (byDay[k] = byDay[k] || []).push(b);
    });

    let html = '<div class="agenda-scroll"><table class="agenda-table agenda-week"><thead><tr><th scope="col" class="agenda-time-col">Horário</th>';
    days.forEach((iso) => {
      const d = fromISODate(iso);
      const today = iso === toISODate(new Date());
      html += '<th scope="col"><button type="button" class="week-day-btn' + (today ? " is-today" : "") + '" data-goto-day="' + iso + '">' +
        WEEKDAYS_SHORT[d.getDay()] + " " + d.getDate() + "/" + (d.getMonth() + 1) + "</button></th>";
    });
    html += "</tr></thead><tbody>";

    GRID_HOURS.forEach((t) => {
      html += '<tr><th scope="row" class="agenda-time">' + t + "</th>";
      days.forEach((iso) => {
        const items = (byDay[iso + "|" + t.slice(0, 2)] || []).slice().sort((x, y) => x.time.localeCompare(y.time));
        html += '<td class="' + (items.length ? "has-booking" : "") + '">' +
          (items.length
            ? items.map((b) => '<span class="week-chip" title="' + escapeHTML(b.service_name) + '">' +
                '<b>' + b.time + "</b> " + escapeHTML(firstName(b.client_name)) +
                ' <em>' + escapeHTML((PROFESSIONALS.find((p) => p.id === b.pro_id) || { initials: "✦" }).initials) + "</em></span>").join("")
            : '<span class="agenda-free">·</span>') + "</td>";
      });
      html += "</tr>";
    });
    $("#agendaWrap").innerHTML = html + "</tbody></table></div>";
  }

  function loadAgenda() {
    $("#agendaWrap").innerHTML = '<div class="bookings-empty"><p>Carregando a agenda…</p></div>';
    renderDayNav();
    return state.view === "week" ? loadWeek() : loadDay();
  }

  function setView(view) {
    state.view = view;
    $("#btnViewDay").classList.toggle("is-active", view === "day");
    $("#btnViewDay").setAttribute("aria-pressed", String(view === "day"));
    $("#btnViewWeek").classList.toggle("is-active", view === "week");
    $("#btnViewWeek").setAttribute("aria-pressed", String(view === "week"));
    loadAgenda();
  }

  // ---------- Cancelamento (admin) ----------
  async function handleAgendaClick(ev) {
    const goto = ev.target.closest("[data-goto-day]");
    if (goto) { state.date = goto.dataset.gotoDay; setView("day"); return; }

    const unblock = ev.target.closest("[data-unblock]");
    if (unblock) {
      if (!window.confirm("Remover este bloqueio?")) return;
      const res = await api.deleteBlock(unblock.dataset.unblock);
      showToast(res.ok ? "Bloqueio removido." : (res.data.error || "Não foi possível remover."));
      loadAgenda();
      return;
    }

    const btn = ev.target.closest("[data-cancel]");
    if (!btn) return;
    if (!window.confirm("Cancelar o horário de " + btn.dataset.client + "? A cliente não será avisada automaticamente.")) return;
    btn.disabled = true;
    const res = await api.cancelBooking(btn.dataset.cancel);
    if (!res.ok) { btn.disabled = false; showToast(res.data.error || "Não foi possível cancelar."); return; }
    showToast("Horário cancelado.");
    loadAgenda();
  }

  // ---------- Balcão: novo agendamento ----------
  function fillSelect(sel, options) {
    sel.innerHTML = options.map((o) => '<option value="' + o.value + '">' + escapeHTML(o.label) + "</option>").join("");
  }

  function setupBookingForm() {
    fillSelect($("#nbService"), SERVICES.map((s) => ({ value: s.id, label: s.category + " · " + s.name + " (" + s.duration + " min · " + brl(s.price) + ")" })));
    fillSelect($("#nbPro"), PROFESSIONALS.map((p) => ({ value: p.id, label: p.name })));
    $("#nbDate").value = state.date;
    attachPhoneMask($("#nbGuestPhone"));

    const refreshTimes = async () => {
      const date = $("#nbDate").value;
      const timeSel = $("#nbTime");
      if (!date) { fillSelect(timeSel, [{ value: "", label: "Escolha a data" }]); return; }
      const res = await api.availability(date, $("#nbPro").value, $("#nbService").value);
      const taken = res.ok ? (res.data.taken || []) : [];
      const free = TIME_SLOTS.filter((t) => !taken.includes(t));
      if (!free.length) { fillSelect(timeSel, [{ value: "", label: "Sem horários livres" }]); return; }
      // 60 horários por dia: agrupa por hora para dar para achar
      let html = "", hora = "";
      free.forEach((t) => {
        const h = t.slice(0, 2);
        if (h !== hora) { if (hora) html += "</optgroup>"; hora = h; html += '<optgroup label="' + h + 'h">'; }
        html += '<option value="' + t + '">' + t + "</option>";
      });
      timeSel.innerHTML = html + "</optgroup>";
    };
    ["nbService", "nbPro", "nbDate"].forEach((id) => $("#" + id).addEventListener("change", refreshTimes));
    refreshTimes();

    // Alternância cadastrada / balcão
    document.querySelectorAll('input[name="nbClientMode"]').forEach((r) => {
      r.addEventListener("change", () => {
        const guest = r.value === "guest" && r.checked;
        $("#nbRegisteredBox").hidden = guest;
        $("#nbGuestBox").hidden = !guest;
      });
    });

    // Busca de clientes com debounce
    const search = $("#nbClientSearch");
    const results = $("#nbClientResults");
    let t = null;
    search.addEventListener("input", () => {
      state.nbClientId = null;
      $("#nbClientChosen").textContent = "Nenhuma cliente selecionada.";
      clearTimeout(t);
      const q = search.value.trim();
      if (q.length < 2) { results.hidden = true; return; }
      t = setTimeout(async () => {
        const res = await api.searchClients(q);
        const list = res.ok ? (res.data.clients || []) : [];
        results.innerHTML = list.length
          ? list.map((c) => '<button type="button" class="client-result" data-client-id="' + c.id + '" data-client-name="' + escapeHTML(c.name) + '">' +
              escapeHTML(c.name) + ' <span>' + escapeHTML(c.email) + "</span></button>").join("")
          : '<p class="client-none">Nenhuma cliente encontrada. Use a opção “Sem cadastro”.</p>';
        results.hidden = false;
      }, 300);
    });
    results.addEventListener("click", (ev) => {
      const b = ev.target.closest("[data-client-id]");
      if (!b) return;
      state.nbClientId = Number(b.dataset.clientId);
      $("#nbClientChosen").textContent = "Selecionada: " + b.dataset.clientName;
      search.value = b.dataset.clientName;
      results.hidden = true;
    });

    $("#newBookingForm").addEventListener("submit", async (ev) => {
      ev.preventDefault();
      const errEl = $("#nbError");
      errEl.hidden = true;

      const guestMode = document.querySelector('input[name="nbClientMode"]:checked').value === "guest";
      const payload = {
        service_id: $("#nbService").value,
        pro_id: $("#nbPro").value,
        date: $("#nbDate").value,
        time: $("#nbTime").value,
      };
      if (!payload.time) { errEl.textContent = "Escolha um horário livre."; errEl.hidden = false; return; }
      if (guestMode) {
        payload.guest_name = $("#nbGuestName").value.trim();
        payload.guest_phone = $("#nbGuestPhone").value.trim();
      } else {
        if (!state.nbClientId) { errEl.textContent = "Busque e selecione a cliente (ou use “Sem cadastro”)."; errEl.hidden = false; return; }
        payload.user_id = state.nbClientId;
      }

      const btn = $("#nbSubmit");
      setLoading(btn, true, "Confirmando…");
      const res = await api.adminBooking(payload);
      setLoading(btn, false);
      if (!res.ok) { errEl.textContent = res.data.error || "Não foi possível agendar."; errEl.hidden = false; return; }
      showToast("Agendamento criado!");
      state.date = payload.date;
      refreshTimes();
      loadAgenda();
    });

    $("#nbCancel").addEventListener("click", () => toggleAdminForm("newBookingForm", "btnNewBooking", false));
  }

  // ---------- Bloqueios ----------
  function setupBlockForm() {
    fillSelect($("#blkPro"), [{ value: "all", label: "Todas as profissionais" }]
      .concat(GRID_PROS.map((p) => ({ value: p.id, label: p.name }))));
    $("#blkDate").value = state.date;
    fillSelect($("#blkStart"), BLOCK_TIMES.slice(0, -1).map((t) => ({ value: t, label: t })));
    fillSelect($("#blkEnd"), BLOCK_TIMES.slice(1).map((t) => ({ value: t, label: t })));
    $("#blkEnd").value = "18:00";

    $("#blkAllDay").addEventListener("change", (ev) => {
      const on = ev.target.checked;
      $("#blkStart").value = "09:00";
      $("#blkEnd").value = "18:00";
      $("#blkStart").disabled = on;
      $("#blkEnd").disabled = on;
    });

    $("#newBlockForm").addEventListener("submit", async (ev) => {
      ev.preventDefault();
      const errEl = $("#blkError");
      errEl.hidden = true;
      const payload = {
        pro_id: $("#blkPro").value,
        date: $("#blkDate").value,
        start: $("#blkStart").value,
        end: $("#blkEnd").value,
        reason: $("#blkReason").value.trim(),
      };
      if (!payload.date) { errEl.textContent = "Escolha a data."; errEl.hidden = false; return; }
      if (hmToMin(payload.start) >= hmToMin(payload.end)) { errEl.textContent = "O início precisa ser antes do fim."; errEl.hidden = false; return; }

      const btn = $("#blkSubmit");
      setLoading(btn, true, "Bloqueando…");
      const res = await api.createBlock(payload);
      setLoading(btn, false);
      if (!res.ok) { errEl.textContent = res.data.error || "Não foi possível bloquear."; errEl.hidden = false; return; }
      showToast("Horário bloqueado.");
      state.date = payload.date;
      loadAgenda();
    });

    $("#blkCancel").addEventListener("click", () => toggleAdminForm("newBlockForm", "btnNewBlock", false));
  }

  function toggleAdminForm(formId, btnId, open) {
    const form = $("#" + formId);
    const willOpen = open !== undefined ? open : form.hidden;
    // fecha o outro formulário
    ["newBookingForm", "newBlockForm"].forEach((id) => { $("#" + id).hidden = true; });
    ["btnNewBooking", "btnNewBlock"].forEach((id) => $("#" + id).setAttribute("aria-expanded", "false"));
    form.hidden = !willOpen;
    $("#" + btnId).setAttribute("aria-expanded", String(willOpen));
    if (willOpen && formId === "newBookingForm") $("#nbDate").value = state.date;
    if (willOpen && formId === "newBlockForm") $("#blkDate").value = state.date;
  }

  // ---------- Serviços ----------
  const DURATIONS = [10, 15, 20, 30, 40, 50, 60, 70, 80, 90, 100, 110, 120, 130, 140, 150, 160, 170, 180, 190, 200, 210, 220, 225, 230, 240, 250, 260, 270, 280, 290, 300];

  function serviceRowHTML(s) {
    const durOpts = DURATIONS.map((d) => '<option value="' + d + '"' + (d === s.duration_min ? " selected" : "") + ">" + d + " min</option>").join("");
    return (
      '<form class="service-row' + (s.active ? "" : " is-inactive") + '" data-service-id="' + (s.id || "") + '">' +
        '<div class="service-row-grid">' +
          '<div class="form-field"><label>Nome</label><input type="text" name="name" value="' + escapeHTML(s.name || "") + '" minlength="3" required /></div>' +
          '<div class="form-field"><label>Categoria</label><input type="text" name="category" value="' + escapeHTML(s.category || "") + '" maxlength="40" list="categoryList" required /></div>' +
          '<div class="form-field"><label>Preço (R$)</label><input type="number" name="price" value="' + (s.price || "") + '" min="1" step="0.01" required /></div>' +
          '<div class="form-field"><label>Duração</label><select name="duration_min">' + durOpts + "</select></div>" +
        "</div>" +
        '<div class="form-field"><label>Descrição</label><input type="text" name="description" value="' + escapeHTML(s.description || "") + '" maxlength="255" /></div>' +
        '<div class="service-row-flags">' +
          '<label class="client-mode-option"><input type="checkbox" name="price_from"' + (s.price_from ? " checked" : "") + " /> Preço “a partir de”</label>" +
          '<label class="client-mode-option"><input type="checkbox" name="active"' + (s.active ? " checked" : "") + " /> Ativo (visível no site)</label>" +
        "</div>" +
        '<p class="field-error" role="alert" hidden></p>' +
        '<div class="inline-form-actions">' +
          '<button type="submit" class="btn btn-primary btn-sm"><span class="btn-label">' + (s.id ? "Salvar" : "Criar serviço") + '</span><span class="btn-spinner" aria-hidden="true"></span></button>' +
        "</div>" +
      "</form>"
    );
  }

  async function loadServicesEditor() {
    const wrap = $("#servicesEditor");
    wrap.innerHTML = '<div class="bookings-empty"><p>Carregando serviços…</p></div>';
    const res = await api.services(true); // ?all=1 (inclui inativos)
    if (!res.ok) { wrap.innerHTML = '<div class="bookings-empty"><p>Erro ao carregar serviços.</p></div>'; return; }
    const services = res.data.services || [];
    const cats = [...new Set(services.map((s) => s.category))];
    wrap.innerHTML =
      '<datalist id="categoryList">' + cats.map((c) => '<option value="' + escapeHTML(c) + '">').join("") + "</datalist>" +
      services.map(serviceRowHTML).join("");
  }

  async function handleServiceSubmit(ev) {
    const form = ev.target.closest(".service-row");
    if (!form) return;
    ev.preventDefault();
    const errEl = form.querySelector(".field-error");
    errEl.hidden = true;

    const id = form.dataset.serviceId;
    const service = {
      id: id || undefined,
      name: form.querySelector('[name="name"]').value.trim(),
      category: form.querySelector('[name="category"]').value.trim(),
      description: form.querySelector('[name="description"]').value.trim(),
      duration_min: Number(form.querySelector('[name="duration_min"]').value),
      price: Number(form.querySelector('[name="price"]').value),
      price_from: form.querySelector('[name="price_from"]').checked,
      icon: "sparkles",
      active: form.querySelector('[name="active"]').checked,
    };

    const btn = form.querySelector('button[type="submit"]');
    setLoading(btn, true, "Salvando…");
    const res = await api.saveService(id ? "update" : "create", service);
    setLoading(btn, false);
    if (!res.ok) { errEl.textContent = res.data.error || "Não foi possível salvar."; errEl.hidden = false; return; }
    showToast(id ? "Serviço atualizado!" : "Serviço criado!");
    await loadCatalog();
    loadServicesEditor();
  }

  // ---------- Relatório ----------
  function deltaHTML(cur, prev, money) {
    if (prev === 0) return "";
    const pct = Math.round(((cur - prev) / prev) * 100);
    const cls = pct >= 0 ? "delta-up" : "delta-down";
    return '<span class="report-delta ' + cls + '">' + (pct >= 0 ? "+" : "") + pct + "% vs mês anterior</span>";
  }

  async function loadReport() {
    const month = $("#reportMonth").value;
    const wrap = $("#reportWrap");
    wrap.innerHTML = '<div class="bookings-empty"><p>Calculando…</p></div>';
    const res = await api.adminReport(month);
    if (!res.ok) { wrap.innerHTML = '<div class="bookings-empty"><p>' + escapeHTML(res.data.error || "Erro.") + "</p></div>"; return; }

    const { current, previous, top_services, by_pro } = res.data;
    const maxCount = Math.max(1, ...top_services.map((s) => s.count));

    wrap.innerHTML =
      '<div class="stat-grid">' +
        '<div class="stat-card"><span class="stat-value">' + current.bookings + "</span><span class=\"stat-label\">Agendamentos</span>" + deltaHTML(current.bookings, previous.bookings) + "</div>" +
        '<div class="stat-card"><span class="stat-value">' + brl(current.revenue) + "</span><span class=\"stat-label\">Faturamento</span>" + deltaHTML(current.revenue, previous.revenue) + "</div>" +
        '<div class="stat-card"><span class="stat-value">' + current.new_clients + "</span><span class=\"stat-label\">Clientes novas</span>" + deltaHTML(current.new_clients, previous.new_clients) + "</div>" +
      "</div>" +
      '<div class="account-card"><h3 class="account-card-title">Serviços mais pedidos</h3>' +
        (top_services.length === 0 ? '<p class="account-empty-hint">Sem agendamentos neste mês.</p>' :
          top_services.map((s) =>
            '<div class="report-bar-row"><span class="report-bar-label">' + escapeHTML(s.name) + "</span>" +
            '<span class="report-bar"><span class="report-bar-fill" style="width:' + Math.round((s.count / maxCount) * 100) + '%"></span></span>' +
            '<span class="report-bar-value">' + s.count + "x · " + brl(s.revenue) + "</span></div>").join("")) +
      "</div>" +
      '<div class="account-card"><h3 class="account-card-title">Por profissional</h3>' +
        (by_pro.length === 0 ? '<p class="account-empty-hint">Sem dados.</p>' :
          by_pro.map((p) => {
            const pro = PROFESSIONALS.find((x) => x.id === p.pro_id) || { name: p.pro_id };
            return '<div class="report-bar-row"><span class="report-bar-label">' + escapeHTML(pro.name) + "</span><span class=\"report-bar-value\">" + p.count + " atendimentos</span></div>";
          }).join("")) +
      "</div>";
  }

  // ---------- Abas ----------
  function setupTabs() {
    document.querySelectorAll("[data-admin-tab]").forEach((btn) => {
      btn.addEventListener("click", () => {
        document.querySelectorAll("[data-admin-tab]").forEach((b) => {
          const active = b === btn;
          b.classList.toggle("is-active", active);
          b.setAttribute("aria-selected", String(active));
        });
        ["agenda", "servicos", "relatorio"].forEach((t) => { $("#tab-" + t).hidden = t !== btn.dataset.adminTab; });
        if (btn.dataset.adminTab === "servicos") loadServicesEditor();
        if (btn.dataset.adminTab === "relatorio") loadReport();
      });
    });
  }

  // ---------- Inicialização ----------
  async function init() {
    const res = await api.me();
    const user = res.ok ? res.data.user : null;
    if (!user) { location.replace("login.html?next=admin"); return; }
    if (user.role !== "admin") { location.replace("minha-conta.html"); return; }
    state.user = user;

    await loadCatalog();

    $("#adminLoading").hidden = true;
    $("#adminContent").hidden = false;

    setupTabs();
    setupBookingForm();
    setupBlockForm();

    $("#btnPrevDay").addEventListener("click", () => shiftDate(state.view === "week" ? -7 : -1));
    $("#btnNextDay").addEventListener("click", () => shiftDate(state.view === "week" ? 7 : 1));
    $("#btnToday").addEventListener("click", () => { state.date = toISODate(new Date()); loadAgenda(); });
    $("#btnRefresh").addEventListener("click", loadAgenda);
    $("#dayPicker").addEventListener("change", (ev) => { if (ev.target.value) { state.date = ev.target.value; loadAgenda(); } });
    $("#btnViewDay").addEventListener("click", () => setView("day"));
    $("#btnViewWeek").addEventListener("click", () => setView("week"));
    $("#btnNewBooking").addEventListener("click", () => toggleAdminForm("newBookingForm", "btnNewBooking"));
    $("#btnNewBlock").addEventListener("click", () => toggleAdminForm("newBlockForm", "btnNewBlock"));
    $("#agendaWrap").addEventListener("click", handleAgendaClick);
    $("#blocksList").addEventListener("click", handleAgendaClick);
    $("#servicesEditor").addEventListener("submit", handleServiceSubmit);
    $("#btnAddService").addEventListener("click", () => {
      if (document.querySelector('.service-row[data-service-id=""]')) return;
      $("#servicesEditor").insertAdjacentHTML("afterbegin",
        serviceRowHTML({ id: "", name: "", category: "", description: "", duration_min: 60, price: "", price_from: false, active: true }));
    });
    const now = new Date();
    $("#reportMonth").value = now.getFullYear() + "-" + String(now.getMonth() + 1).padStart(2, "0");
    $("#reportMonth").addEventListener("change", loadReport);
    $("#btnLogout").addEventListener("click", async () => { await api.logout(); location.href = "index.html"; });

    await loadAgenda();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
