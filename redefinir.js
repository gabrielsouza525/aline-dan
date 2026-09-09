/* ============================================================
   Aline Dan · Recuperação de senha (redefinir-senha.html)
   Sem ?token= → pede o e-mail. Com ?token= → define a nova senha.
   ============================================================ */
(function () {
  "use strict";
  const { api, setLoading, setupPasswordToggles } = window.AD;
  const $ = (sel) => document.querySelector(sel);

  const token = new URLSearchParams(location.search).get("token") || "";

  function showNotice(msg) {
    const el = $("#resetNotice");
    el.textContent = msg;
    el.hidden = false;
  }

  async function handleRequest(ev) {
    ev.preventDefault();
    const errEl = $("#requestError");
    errEl.hidden = true;
    const btn = $("#btnRequest");
    setLoading(btn, true, "Enviando…");
    const res = await api.forgotPassword({ email: $("#fpEmail").value.trim() });
    setLoading(btn, false);
    if (!res.ok) {
      errEl.textContent = res.data.error || "Não foi possível enviar. Tente de novo.";
      errEl.hidden = false;
      return;
    }
    $("#requestForm").hidden = true;
    $("#resetTitle").textContent = "Confira seu e-mail";
    showNotice("Se este e-mail estiver cadastrado, você vai receber um link para criar uma nova senha. O link vale por 1 hora.");
  }

  async function handleReset(ev) {
    ev.preventDefault();
    const errEl = $("#resetError");
    errEl.hidden = true;

    const nw = $("#rpNew").value;
    if (nw.length < 6) {
      errEl.textContent = "A nova senha precisa ter pelo menos 6 caracteres.";
      errEl.hidden = false;
      return;
    }
    if (nw !== $("#rpConfirm").value) {
      errEl.textContent = "A confirmação não confere com a nova senha.";
      errEl.hidden = false;
      return;
    }

    const btn = $("#btnReset");
    setLoading(btn, true, "Salvando…");
    const res = await api.resetPassword({ token, new_password: nw });
    setLoading(btn, false);
    if (!res.ok) {
      errEl.textContent = res.data.error || "Não foi possível redefinir. Peça um novo link.";
      errEl.hidden = false;
      return;
    }
    location.href = "login.html?reset=1";
  }

  function init() {
    if (token) {
      $("#requestForm").hidden = true;
      $("#resetForm").hidden = false;
      $("#resetTitle").textContent = "Crie sua nova senha";
    }
    $("#requestForm").addEventListener("submit", handleRequest);
    $("#resetForm").addEventListener("submit", handleReset);
    setupPasswordToggles();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
