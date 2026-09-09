/* ============================================================
   Aline Dan · Código compartilhado entre as páginas
   Os serviços vêm do banco (api/services.php) via loadCatalog().
   ============================================================ */
window.AD = (function () {
  "use strict";

  // Preenchido por loadCatalog() — mantém a MESMA referência de array
  const SERVICES = [];

  // Equipe real (fonte: trinks.com/espaco-lounge-aline-dan)
  const PROFESSIONALS = [
    { id: "aline",     name: "Aline",           role: "Cabeleireira",  initials: "Al" },
    { id: "amanda",    name: "Amanda",          role: "Cabeleireira",  initials: "Am" },
    { id: "sebastian", name: "Sebastian",       role: "Cabeleireiro",  initials: "Se" },
    { id: "dayana",    name: "Dayana",          role: "Especialista",  initials: "Da" },
    { id: "isabelle",  name: "Isabelle",        role: "Manicure",      initials: "Is" },
    { id: "karen",     name: "Karen",           role: "Manicure",      initials: "Ka" },
    { id: "nagila",    name: "Nágila Regina",   role: "Manicure",      initials: "Ná" },
    { id: "vitoria",   name: "Vitoria",         role: "Manicure",      initials: "Vi" },
    { id: "nicolly",   name: "Nicolly",         role: "Manicure",      initials: "Ni" },
    { id: "raissa",    name: "Raissa",          role: "Manicure",      initials: "Ra" },
    { id: "any",       name: "Sem preferência", role: "Primeira profissional disponível", initials: "✦" },
  ];

  // Terça a sábado, 08h às 18h — horários a cada 10 minutos
  const TIME_SLOTS = [
    "08:00", "08:10", "08:20", "08:30", "08:40", "08:50",
    "09:00", "09:10", "09:20", "09:30", "09:40", "09:50",
    "10:00", "10:10", "10:20", "10:30", "10:40", "10:50",
    "11:00", "11:10", "11:20", "11:30", "11:40", "11:50",
    "12:00", "12:10", "12:20", "12:30", "12:40", "12:50",
    "13:00", "13:10", "13:20", "13:30", "13:40", "13:50",
    "14:00", "14:10", "14:20", "14:30", "14:40", "14:50",
    "15:00", "15:10", "15:20", "15:30", "15:40", "15:50",
    "16:00", "16:10", "16:20", "16:30", "16:40", "16:50",
    "17:00", "17:10", "17:20", "17:30", "17:40", "17:50",
  ];
  const CLOSED_WEEKDAYS = [0, 1];
  const SALON_WHATSAPP = "5518996655263";

  const SLOT_STEP = 10;        // minutos entre um horário e o seguinte
  const OPEN_MIN = 8 * 60;     // o salão abre às 08h
  const CLOSING_MIN = 18 * 60; // e fecha às 18h
  const pad2 = (n) => String(n).padStart(2, "0");
  const minToHm = (m) => pad2(Math.floor(m / 60)) + ":" + pad2(m % 60);

  // Linhas do quadro da agenda: uma por hora cheia
  const GRID_HOURS = [];
  for (let m = OPEN_MIN; m < CLOSING_MIN; m += 60) GRID_HOURS.push(minToHm(m));

  const ICONS = {
    scissors: '<path d="M20 4 8.5 15.5M14.5 14.5 20 20M8.5 8.5 12 12"/><circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/>',
    wind: '<path d="M9.6 4.6A2 2 0 1 1 11 8H2m10.6 11.4A2 2 0 1 0 14 16H2m15.7-8.7A2.5 2.5 0 1 1 19.5 12H2"/>',
    palette: '<path d="M12 22a10 10 0 1 1 10-10c0 2-1.5 3.5-3.5 3.5H16a2 2 0 0 0-2 2c0 .5.2 1 .5 1.3.3.4.5.8.5 1.2a2 2 0 0 1-2 2z"/><circle cx="7.5" cy="11.5" r="1"/><circle cx="10.5" cy="7.5" r="1"/><circle cx="15.5" cy="7.5" r="1"/>',
    sparkles: '<path d="M12 3l1.9 5.8a2 2 0 0 0 1.3 1.3L21 12l-5.8 1.9a2 2 0 0 0-1.3 1.3L12 21l-1.9-5.8a2 2 0 0 0-1.3-1.3L3 12l5.8-1.9a2 2 0 0 0 1.3-1.3L12 3z"/>',
    droplet: '<path d="M12 2.7 6.8 8a7.3 7.3 0 1 0 10.4 0L12 2.7z"/>',
    crown: '<path d="M3 8l4 4 5-6 5 6 4-4v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8z"/>',
    clock: '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
    check: '<path d="M20 6 9 17l-5-5"/>',
    calendar: '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
    user: '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
    logout: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
    pencil: '<path d="M17 3a2.8 2.8 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/>',
    lock: '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
    mail: '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
    phone: '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.13.96.36 1.9.7 2.8a2 2 0 0 1-.45 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.45c.9.34 1.84.57 2.8.7A2 2 0 0 1 22 16.9z"/>',
    star: '<path d="M12 2l2.9 6.3 6.9.8-5.1 4.7 1.4 6.8L12 17.2 5.9 20.6l1.4-6.8L2.2 9.1l6.9-.8L12 2z"/>',
    eye: '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>',
    block: '<circle cx="12" cy="12" r="10"/><line x1="4.9" y1="4.9" x2="19.1" y2="19.1"/>',
    plus: '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
    "arrow-left": '<path d="M19 12H5M12 19l-7-7 7-7"/>',
    search: '<circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
    "chevron-down": '<path d="M6 9l6 6 6-6"/>',
    "chevron-up": '<path d="M18 15l-6-6-6 6"/>',
  };

  const WEEKDAYS_SHORT = ["dom", "seg", "ter", "qua", "qui", "sex", "sáb"];
  const MONTHS_SHORT = ["jan", "fev", "mar", "abr", "mai", "jun", "jul", "ago", "set", "out", "nov", "dez"];

  function svgIcon(name, cls) {
    return '<svg class="' + (cls || "icon") + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + (ICONS[name] || ICONS.sparkles) + "</svg>";
  }

  function escapeHTML(s) {
    return String(s).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
  }

  const brl = (v) => Number(v).toLocaleString("pt-BR", { style: "currency", currency: "BRL" });

  function toISODate(d) {
    return d.getFullYear() + "-" + String(d.getMonth() + 1).padStart(2, "0") + "-" + String(d.getDate()).padStart(2, "0");
  }
  function fromISODate(iso) {
    const p = iso.split("-").map(Number);
    return new Date(p[0], p[1] - 1, p[2]);
  }
  function formatDateLong(iso) {
    const d = fromISODate(iso);
    return WEEKDAYS_SHORT[d.getDay()] + ", " + d.getDate() + " de " + MONTHS_SHORT[d.getMonth()] + ".";
  }

  // ---------- API ----------
  async function apiRequest(path, options) {
    const opts = options || {};
    const init = { method: opts.method || "GET", credentials: "same-origin", headers: {} };
    if (opts.body) {
      init.headers["Content-Type"] = "application/json";
      init.body = JSON.stringify(opts.body);
    }
    let res;
    try {
      res = await fetch("api/" + path, init);
    } catch (e) {
      return { ok: false, status: 0, data: { error: "Não foi possível falar com o servidor. Verifique se o XAMPP está ligado." } };
    }
    let data = null;
    try { data = await res.json(); } catch (e) { /* resposta sem corpo */ }
    return { ok: res.ok, status: res.status, data: data || {} };
  }

  const api = {
    me:                 ()          => apiRequest("me.php"),
    login:              (body)      => apiRequest("login.php", { method: "POST", body }),
    register:           (body)      => apiRequest("register.php", { method: "POST", body }),
    logout:             ()          => apiRequest("logout.php", { method: "POST" }),
    myBookings:         (scope)     => apiRequest("bookings.php" + (scope === "all" ? "?scope=all" : "")),
    createBooking:      (body)      => apiRequest("bookings.php", { method: "POST", body }),
    cancelBooking:      (id)        => apiRequest("bookings.php?id=" + encodeURIComponent(id), { method: "DELETE" }),
    rescheduleBooking:  (id, body)  => apiRequest("bookings.php?id=" + encodeURIComponent(id), { method: "PUT", body }),
    availability:       (date, pro, service, excludeId) => apiRequest("availability.php?date=" + date + "&pro=" + encodeURIComponent(pro) + (service ? "&service=" + encodeURIComponent(service) : "") + (excludeId ? "&exclude=" + encodeURIComponent(excludeId) : "")),
    nextSlot:           ()          => apiRequest("availability.php?next=1"),
    updateProfile:      (body)      => apiRequest("update_profile.php", { method: "POST", body }),
    changePassword:     (body)      => apiRequest("change_password.php", { method: "POST", body }),
    forgotPassword:     (body)      => apiRequest("forgot_password.php", { method: "POST", body }),
    resetPassword:      (body)      => apiRequest("reset_password.php", { method: "POST", body }),
    resendVerification: ()          => apiRequest("resend_verification.php", { method: "POST" }),
    services:           (all)       => apiRequest("services.php" + (all ? "?all=1" : "")),
    saveService:        (action, service) => apiRequest("services.php", { method: "POST", body: { action, service } }),
    adminAgenda:        (date)      => apiRequest("admin_agenda.php?date=" + date),
    adminAgendaRange:   (from, to)  => apiRequest("admin_agenda.php?from=" + from + "&to=" + to),
    adminBooking:       (body)      => apiRequest("admin_booking.php", { method: "POST", body }),
    setBookingStatus:   (id, status) => apiRequest("booking_status.php", { method: "POST", body: { id: id, status: status } }),
    adminReport:        (month)     => apiRequest("admin_report.php?month=" + month),
    blocks:             (date)      => apiRequest("blocks.php?date=" + date),
    createBlock:        (body)      => apiRequest("blocks.php", { method: "POST", body }),
    deleteBlock:        (id)        => apiRequest("blocks.php?id=" + encodeURIComponent(id), { method: "DELETE" }),
    searchClients:      (q)         => apiRequest("clients.php?q=" + encodeURIComponent(q)),
  };

  /** Carrega os serviços do banco para dentro de AD.SERVICES. */
  async function loadCatalog() {
    const res = await api.services();
    if (res.ok) {
      SERVICES.length = 0;
      (res.data.services || []).forEach((s) => {
        SERVICES.push({
          id: s.id, name: s.name, category: s.category || "Outros", desc: s.description,
          duration: s.duration_min, price: s.price, priceFrom: Boolean(s.price_from), icon: s.icon,
        });
      });
    }
    return res.ok;
  }

  /** Lista de categorias na ordem do catálogo. */
  function serviceCategories() {
    const cats = [];
    SERVICES.forEach((s) => { if (!cats.includes(s.category)) cats.push(s.category); });
    return cats;
  }

  /** "R$ 45,00" ou "a partir de R$ 60,00". */
  function priceLabel(svc) {
    return (svc.priceFrom ? "a partir de " : "") + brl(svc.price);
  }

  // ---------- UI utilitários ----------
  function showToast(msg) {
    let toast = document.getElementById("toast");
    if (!toast) {
      toast = document.createElement("div");
      toast.id = "toast";
      toast.className = "toast";
      toast.setAttribute("role", "status");
      toast.setAttribute("aria-live", "polite");
      document.body.appendChild(toast);
    }
    toast.textContent = msg;
    requestAnimationFrame(() => toast.classList.add("is-visible"));
    clearTimeout(showToast._t);
    showToast._t = setTimeout(() => toast.classList.remove("is-visible"), 4000);
  }

  function setLoading(btn, loading, labelWhileLoading) {
    const label = btn.querySelector(".btn-label");
    if (loading) {
      btn.dataset.originalLabel = label.textContent;
      label.textContent = labelWhileLoading || "Aguarde…";
      btn.classList.add("is-loading");
      btn.setAttribute("aria-busy", "true");
    } else {
      if (btn.dataset.originalLabel) label.textContent = btn.dataset.originalLabel;
      btn.classList.remove("is-loading");
      btn.removeAttribute("aria-busy");
    }
  }

  function maskPhone(value) {
    const d = value.replace(/\D/g, "").slice(0, 11);
    if (d.length === 0) return "";
    if (d.length <= 2) return "(" + d;
    if (d.length <= 6) return "(" + d.slice(0, 2) + ") " + d.slice(2);
    if (d.length <= 10) return "(" + d.slice(0, 2) + ") " + d.slice(2, 6) + "-" + d.slice(6);
    return "(" + d.slice(0, 2) + ") " + d.slice(2, 7) + "-" + d.slice(7);
  }
  function attachPhoneMask(input) {
    input.addEventListener("input", () => { input.value = maskPhone(input.value); });
  }

  function setupPasswordToggles(root) {
    (root || document).querySelectorAll("[data-toggle-password]").forEach((btn) => {
      btn.addEventListener("click", () => {
        const input = document.getElementById(btn.dataset.togglePassword);
        const show = input.type === "password";
        input.type = show ? "text" : "password";
        btn.setAttribute("aria-pressed", String(show));
        btn.setAttribute("aria-label", show ? "Ocultar senha" : "Mostrar senha");
      });
    });
  }

  // Estado de agendamento pendente (preservado ao ir para a página de login)
  const PENDING_KEY = "alinedan_pending_booking";
  function savePendingBooking(state) {
    try { sessionStorage.setItem(PENDING_KEY, JSON.stringify(state)); } catch (e) { /* sem storage */ }
  }
  function takePendingBooking() {
    try {
      const raw = sessionStorage.getItem(PENDING_KEY);
      if (!raw) return null;
      sessionStorage.removeItem(PENDING_KEY);
      return JSON.parse(raw);
    } catch (e) { return null; }
  }

  /**
   * Grade de horários agrupada por hora, em blocos de manhã e tarde.
   * São 60 horários por dia; mostrar tudo de uma vez cansa a vista, então cada
   * hora vira uma linha ("09h  00 10 20 30 40 50") e as horas lotadas somem.
   * opts: { name, idPrefix, selected, isDisabled(hora) }
   * Devolve "" quando o dia não tem nenhum horário livre.
   */
  function slotsHTML(opts) {
    const horas = [];           // [{ h: "08", itens: [{ t, off }] }]
    let livres = 0;
    TIME_SLOTS.forEach((t) => {
      const off = Boolean(opts.isDisabled(t));
      if (!off) livres++;
      const h = t.slice(0, 2);
      let grupo = horas[horas.length - 1];
      if (!grupo || grupo.h !== h) { grupo = { h: h, itens: [] }; horas.push(grupo); }
      grupo.itens.push({ t: t, off: off });
    });
    if (!livres) return "";

    let html = '<div class="slots-hours">';
    let periodo = "";
    horas.forEach((grupo) => {
      if (grupo.itens.every((i) => i.off)) return;   // hora lotada: nao polui a tela
      const atual = Number(grupo.h) < 12 ? "Manhã" : "Tarde";
      if (atual !== periodo) { periodo = atual; html += '<p class="slots-period">' + periodo + "</p>"; }
      html += '<div class="slot-hour"><span class="sh-label">' + grupo.h + 'h</span><div class="slots-grid">';
      grupo.itens.forEach((i) => {
        const id = opts.idPrefix + i.t.replace(":", "");
        html += '<div class="slot">' +
          '<input type="radio" name="' + opts.name + '" id="' + id + '" value="' + i.t + '"' +
            (i.off ? " disabled" : "") + (opts.selected === i.t && !i.off ? " checked" : "") +
            ' aria-label="' + i.t + '" />' +
          '<label for="' + id + '">' + i.t.slice(3) + "</label></div>";
      });
      html += "</div></div>";
    });
    return html + "</div>";
  }

  return {
    SERVICES, PROFESSIONALS, TIME_SLOTS, CLOSED_WEEKDAYS, SALON_WHATSAPP,
    SLOT_STEP, OPEN_MIN, CLOSING_MIN, GRID_HOURS, slotsHTML,
    ICONS, WEEKDAYS_SHORT, MONTHS_SHORT,
    svgIcon, escapeHTML, brl, priceLabel, serviceCategories,
    toISODate, fromISODate, formatDateLong,
    apiRequest, api, loadCatalog, showToast, setLoading, maskPhone, attachPhoneMask,
    setupPasswordToggles, savePendingBooking, takePendingBooking,
  };
})();
