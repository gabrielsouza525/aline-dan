/* ============================================================
   Aline Dan · Página de login e cadastro
   Parâmetros de URL:
     ?tab=cadastro   → abre direto na aba de criar conta
     ?next=agendar   → depois de entrar, volta para o agendamento
     ?next=conta     → depois de entrar, vai para a área do usuário
     ?next=admin     → depois de entrar, vai para o painel administrativo
   Sem "next": cliente vai para a conta; administradora, para o painel.
   ============================================================ */
(function () {
  "use strict";
  const { api, setLoading, attachPhoneMask, setupPasswordToggles } = window.AD;
  const $ = (sel) => document.querySelector(sel);

  const params = new URLSearchParams(location.search);

  /** Destinos permitidos (evita redirecionamento para fora do site). */
  function nextURL(user) {
    switch (params.get("next")) {
      case "agendar": return "index.html#agendar";
      case "inicio":  return "index.html";
      case "admin":   return "admin.html";
      default:
        // Administradora sem destino definido vai direto para o painel.
        return user && user.role === "admin" ? "admin.html" : "minha-conta.html";
    }
  }

  function goNext(user) {
    location.href = nextURL(user);
  }

  function switchTab(mode) {
    const isLogin = mode !== "register";
    $("#tabLogin").classList.toggle("is-active", isLogin);
    $("#tabLogin").setAttribute("aria-selected", String(isLogin));
    $("#tabRegister").classList.toggle("is-active", !isLogin);
    $("#tabRegister").setAttribute("aria-selected", String(!isLogin));
    $("#loginForm").hidden = !isLogin;
    $("#registerForm").hidden = isLogin;
    $("#authTitle").textContent = isLogin ? "Bem-vinda de volta" : "Crie sua conta";
    ($("#" + (isLogin ? "loginEmail" : "regName"))).focus();
  }

  async function handleLogin(ev) {
    ev.preventDefault();
    const errEl = $("#loginError");
    errEl.hidden = true;
    const btn = $("#btnLogin");
    setLoading(btn, true, "Entrando…");
    const res = await api.login({ email: $("#loginEmail").value.trim(), password: $("#loginPassword").value });
    setLoading(btn, false);
    if (!res.ok) {
      errEl.textContent = res.data.error || "Não foi possível entrar.";
      errEl.hidden = false;
      return;
    }
    goNext(res.data.user);
  }

  async function handleRegister(ev) {
    ev.preventDefault();
    const errEl = $("#registerError");
    errEl.hidden = true;
    const btn = $("#btnRegister");
    setLoading(btn, true, "Criando conta…");
    const res = await api.register({
      name: $("#regName").value.trim(),
      email: $("#regEmail").value.trim(),
      phone: $("#regPhone").value.trim(),
      password: $("#regPassword").value,
    });
    setLoading(btn, false);
    if (!res.ok) {
      errEl.textContent = res.data.error || "Não foi possível criar a conta.";
      errEl.hidden = false;
      return;
    }
    goNext(res.data.user);
  }

  async function init() {
    // Já está logada? Vai direto para o destino.
    const res = await api.me();
    if (res.ok && res.data.user) { goNext(res.data.user); return; }

    // Avisos contextuais
    const notice = $("#authNotice");
    if (params.get("next") === "agendar") {
      notice.textContent = "Falta pouco! Entre ou crie sua conta para confirmar o seu horário — sua escolha fica guardada.";
      notice.hidden = false;
    } else if (params.get("verified") === "1") {
      notice.textContent = "E-mail confirmado com sucesso! Entre para continuar. ✓";
      notice.hidden = false;
    } else if (params.get("verified") === "0") {
      notice.textContent = "Este link de confirmação expirou ou já foi usado. Entre e peça um novo na sua conta.";
      notice.hidden = false;
    } else if (params.get("reset") === "1") {
      notice.textContent = "Senha redefinida com sucesso! Entre com a sua nova senha.";
      notice.hidden = false;
    }

    if (params.get("tab") === "cadastro") switchTab("register");

    $("#tabLogin").addEventListener("click", () => switchTab("login"));
    $("#tabRegister").addEventListener("click", () => switchTab("register"));
    document.querySelectorAll("[data-switch-tab]").forEach((btn) => {
      btn.addEventListener("click", () => switchTab(btn.dataset.switchTab));
    });

    $("#loginForm").addEventListener("submit", handleLogin);
    $("#registerForm").addEventListener("submit", handleRegister);
    attachPhoneMask($("#regPhone"));
    setupPasswordToggles();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
