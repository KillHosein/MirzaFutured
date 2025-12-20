const API = {
  async verify(initData) {
    const res = await fetch("../api/verify.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ initData }),
    });
    const json = await res.json().catch(() => null);
    if (!res.ok || !json?.obj?.token) {
      const msg = json?.msg || "احراز هویت ناموفق بود";
      throw new Error(msg);
    }
    return { token: json.obj.token, userId: json.obj.user_id };
  },
  async miniappGet(token, userId, params) {
    const url = new URL("../api/miniapp.php", window.location.href);
    url.searchParams.set("actions", params.actions);
    url.searchParams.set("user_id", String(userId));
    for (const [k, v] of Object.entries(params)) {
      if (k === "actions") continue;
      if (v === undefined || v === null) continue;
      url.searchParams.set(k, String(v));
    }
    const res = await fetch(url.toString(), {
      method: "GET",
      headers: { Authorization: `Bearer ${token}` },
    });
    const json = await res.json().catch(() => null);
    if (!res.ok || !json) {
      throw new Error(json?.msg || "خطا در ارتباط با سرور");
    }
    return json;
  },
  async miniappPost(token, userId, body) {
    const res = await fetch("../api/miniapp.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${token}`,
      },
      body: JSON.stringify({ ...body, user_id: userId }),
    });
    const json = await res.json().catch(() => null);
    if (!res.ok || !json) {
      throw new Error(json?.msg || json?.message || "خطا در ارتباط با سرور");
    }
    return json;
  },
};

const state = {
  token: null,
  userId: null,
  route: "home",
  userInfo: null,
  profile: null,
  admin: false,
  buy: {
    country: null,
    categoryId: 0,
    timeRangeDay: 0,
    countries: [],
    categories: [],
    timeRanges: [],
    services: [],
    cart: [],
  },
  wallet: {
    payments: [],
    topup: null,
  },
};

const el = {
  content: document.getElementById("content"),
  topbarStatus: document.getElementById("topbarStatus"),
  adminTab: document.getElementById("adminTab"),
  modal: document.getElementById("modal"),
  modalTitle: document.getElementById("modalTitle"),
  modalBody: document.getElementById("modalBody"),
  modalClose: document.getElementById("modalClose"),
};

function fmtPrice(n) {
  const v = Number(n || 0);
  return v.toLocaleString("fa-IR");
}

function pillStatus(v) {
  const s = String(v || "").toLowerCase();
  if (s === "paid" || s === "active") return `<span class="pill ok">✅ ${v}</span>`;
  if (s === "waiting" || s === "unpaid") return `<span class="pill warn">⏳ ${v}</span>`;
  if (s === "reject" || s === "block") return `<span class="pill bad">⛔ ${v}</span>`;
  return `<span class="pill">${v}</span>`;
}

function setStatus(text) {
  el.topbarStatus.textContent = text;
}

function openModal(title, html) {
  el.modalTitle.textContent = title;
  el.modalBody.innerHTML = html;
  el.modal.classList.remove("hidden");
}

function closeModal() {
  el.modal.classList.add("hidden");
  el.modalTitle.textContent = "";
  el.modalBody.innerHTML = "";
}

el.modalClose.addEventListener("click", closeModal);
el.modal.addEventListener("click", (e) => {
  if (e.target === el.modal) closeModal();
});

function toast(msg) {
  if (window.Telegram?.WebApp?.showAlert) {
    window.Telegram.WebApp.showAlert(msg);
    return;
  }
  openModal("پیام", `<div class="muted">${escapeHtml(msg)}</div><div style="margin-top:12px"><button class="btn primary" id="okBtn" type="button">باشه</button></div>`);
  document.getElementById("okBtn")?.addEventListener("click", closeModal);
}

function escapeHtml(s) {
  return String(s ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

function setRoute(route) {
  state.route = route;
  document.querySelectorAll(".tabbar__btn").forEach((b) => {
    b.classList.toggle("active", b.dataset.route === route);
  });
  render();
}

document.querySelectorAll(".tabbar__btn").forEach((btn) => {
  btn.addEventListener("click", () => setRoute(btn.dataset.route));
});

async function loadUserInfo() {
  state.userInfo = await API.miniappGet(state.token, state.userId, { actions: "user_info" }).then((r) => r.obj);
}

async function loadProfile() {
  const r = await API.miniappGet(state.token, state.userId, { actions: "profile_get" });
  state.profile = r.obj;
}

async function detectAdmin() {
  try {
    await API.miniappGet(state.token, state.userId, { actions: "admin_dashboard" });
    state.admin = true;
    el.adminTab.classList.remove("hidden");
  } catch {
    state.admin = false;
    el.adminTab.classList.add("hidden");
  }
}

async function loadBuyLists() {
  const countries = await API.miniappGet(state.token, state.userId, { actions: "countries" }).then((r) => r.obj || []);
  state.buy.countries = countries;
  if (!state.buy.country && countries.length) {
    state.buy.country = countries[0];
  }
  if (state.buy.country) {
    await loadBuyFilters();
  }
}

async function loadBuyFilters() {
  const countryId = state.buy.country?.id;
  if (!countryId) return;
  const categories = await API.miniappGet(state.token, state.userId, { actions: "categories", country_id: countryId }).then((r) => r.obj || []);
  const timeRanges = await API.miniappGet(state.token, state.userId, { actions: "time_ranges", country_id: countryId }).then((r) => r.obj || []);
  state.buy.categories = [{ id: 0, name: "همه" }, ...categories];
  state.buy.timeRanges = [{ day: 0, name: "همه" }, ...timeRanges];
  if (!state.buy.categoryId) state.buy.categoryId = 0;
  if (!state.buy.timeRangeDay) state.buy.timeRangeDay = 0;
  await loadServices();
}

async function loadServices() {
  const countryId = state.buy.country?.id;
  if (!countryId) return;
  const r = await API.miniappGet(state.token, state.userId, {
    actions: "services",
    country_id: countryId,
    category_id: state.buy.categoryId,
    time_range_day: state.buy.timeRangeDay,
  });
  state.buy.services = r.obj || [];
}

async function loadPayments() {
  const r = await API.miniappGet(state.token, state.userId, { actions: "payments", page: 1, limit: 20 });
  state.wallet.payments = r.obj || [];
}

function render() {
  const route = state.route;
  if (route === "home") return renderHome();
  if (route === "buy") return renderBuy();
  if (route === "wallet") return renderWallet();
  if (route === "profile") return renderProfile();
  if (route === "admin") return renderAdmin();
  renderHome();
}

function renderHome() {
  const u = state.userInfo;
  el.content.innerHTML = `
    <div class="stack">
      <div class="card">
        <div class="row">
          <div>
            <div class="title">حساب کاربری</div>
            <div class="muted">@${escapeHtml(window.Telegram?.WebApp?.initDataUnsafe?.user?.username || u?.username || "")}</div>
          </div>
          <div style="text-align:left">
            <div class="muted">موجودی</div>
            <div class="title">${fmtPrice(u?.balance)} تومان</div>
          </div>
        </div>
      </div>
      <div class="card">
        <div class="grid2">
          <button class="btn primary" id="goBuy">خرید سرویس</button>
          <button class="btn" id="goInvoices">سرویس‌های من</button>
          <button class="btn" id="goWallet">افزایش موجودی</button>
          <button class="btn" id="goProfile">تنظیمات</button>
        </div>
      </div>
      <div class="card">
        <div class="row">
          <div class="title">سرویس‌های فعال</div>
          <div class="pill">${escapeHtml(String(u?.count_order ?? 0))}</div>
        </div>
        <div class="row" style="margin-top:8px">
          <div class="muted">تراکنش‌ها</div>
          <div class="pill">${escapeHtml(String(u?.count_payment ?? 0))}</div>
        </div>
      </div>
    </div>
  `;
  document.getElementById("goBuy")?.addEventListener("click", () => setRoute("buy"));
  document.getElementById("goWallet")?.addEventListener("click", () => setRoute("wallet"));
  document.getElementById("goProfile")?.addEventListener("click", () => setRoute("profile"));
  document.getElementById("goInvoices")?.addEventListener("click", async () => {
    try {
      setStatus("در حال دریافت سرویس‌ها...");
      const r = await API.miniappGet(state.token, state.userId, { actions: "invoices", page: 1, limit: 10 });
      const items = r.obj || [];
      openModal("سرویس‌های من", items.length ? `
        <div class="list">
          ${items
            .map(
              (it) => `
              <div class="item">
                <div class="row">
                  <div class="title">${escapeHtml(it.username)}</div>
                  <div>${pillStatus(it.status)}</div>
                </div>
                <div class="muted">انقضا: ${escapeHtml(it.expire)}</div>
                <div class="muted">${escapeHtml(it.note || "")}</div>
              </div>
            `
            )
            .join("")}
        </div>
      ` : `<div class="muted">سرویسی یافت نشد</div>`);
    } catch (e) {
      toast(e.message);
    } finally {
      setStatus("آماده");
    }
  });
}

function renderBuy() {
  const b = state.buy;
  const cartTotal = b.cart.reduce((s, it) => s + Number(it.price || 0), 0);
  el.content.innerHTML = `
    <div class="stack">
      <div class="card">
        <div class="title">انتخاب سرور</div>
        <select class="select" id="countrySel">
          ${b.countries
            .map((c) => `<option value="${escapeHtml(c.id)}" ${b.country?.id === c.id ? "selected" : ""}>${escapeHtml(c.name)}</option>`)
            .join("")}
        </select>
        <div class="grid2" style="margin-top:10px">
          <select class="select" id="catSel">
            ${b.categories.map((c) => `<option value="${c.id}" ${Number(b.categoryId) === Number(c.id) ? "selected" : ""}>${escapeHtml(c.name)}</option>`).join("")}
          </select>
          <select class="select" id="timeSel">
            ${b.timeRanges.map((t) => `<option value="${t.day}" ${Number(b.timeRangeDay) === Number(t.day) ? "selected" : ""}>${escapeHtml(t.name)}</option>`).join("")}
          </select>
        </div>
      </div>
      <div class="card">
        <div class="row">
          <div class="title">سبد خرید</div>
          <div class="title">${fmtPrice(cartTotal)} تومان</div>
        </div>
        <div class="grid2" style="margin-top:10px">
          <button class="btn" id="viewCart">مشاهده سبد</button>
          <button class="btn primary" id="checkout" ${b.cart.length ? "" : "disabled"}>پرداخت با موجودی</button>
        </div>
      </div>
      <div class="card">
        <div class="row">
          <div class="title">لیست سرویس‌ها</div>
          <div class="pill">${escapeHtml(String(b.services.length))}</div>
        </div>
        <div class="list" style="margin-top:10px">
          ${b.services
            .map(
              (s) => `
              <div class="item">
                <div class="row">
                  <div class="title">${escapeHtml(s.name)}</div>
                  <div class="title">${fmtPrice(s.price)} تومان</div>
                </div>
                <div class="muted">${escapeHtml(s.description || "")}</div>
                <div class="row">
                  <div class="pill">حجم: ${escapeHtml(String(s.traffic_gb))} GB</div>
                  <div class="pill">زمان: ${escapeHtml(String(s.time_days))} روز</div>
                </div>
                <div class="grid2" style="margin-top:8px">
                  <button class="btn" data-add="${escapeHtml(s.id)}">افزودن به سبد</button>
                  <button class="btn primary" data-buy="${escapeHtml(s.id)}">خرید سریع</button>
                </div>
              </div>
            `
            )
            .join("")}
        </div>
      </div>
    </div>
  `;

  document.getElementById("countrySel")?.addEventListener("change", async (e) => {
    const id = e.target.value;
    b.country = b.countries.find((c) => String(c.id) === String(id)) || null;
    b.categoryId = 0;
    b.timeRangeDay = 0;
    try {
      setStatus("در حال بارگذاری...");
      await loadBuyFilters();
      renderBuy();
    } catch (err) {
      toast(err.message);
    } finally {
      setStatus("آماده");
    }
  });
  document.getElementById("catSel")?.addEventListener("change", async (e) => {
    b.categoryId = Number(e.target.value);
    try {
      setStatus("در حال بارگذاری...");
      await loadServices();
      renderBuy();
    } catch (err) {
      toast(err.message);
    } finally {
      setStatus("آماده");
    }
  });
  document.getElementById("timeSel")?.addEventListener("change", async (e) => {
    b.timeRangeDay = Number(e.target.value);
    try {
      setStatus("در حال بارگذاری...");
      await loadServices();
      renderBuy();
    } catch (err) {
      toast(err.message);
    } finally {
      setStatus("آماده");
    }
  });

  el.content.querySelectorAll("[data-add]").forEach((btn) => {
    btn.addEventListener("click", () => {
      const id = btn.getAttribute("data-add");
      const s = b.services.find((x) => String(x.id) === String(id));
      if (!s) return;
      b.cart.push(s);
      renderBuy();
    });
  });
  el.content.querySelectorAll("[data-buy]").forEach((btn) => {
    btn.addEventListener("click", async () => {
      const id = btn.getAttribute("data-buy");
      const s = b.services.find((x) => String(x.id) === String(id));
      if (!s) return;
      await purchaseServices([s]);
    });
  });

  document.getElementById("viewCart")?.addEventListener("click", () => {
    const items = b.cart.slice();
    openModal(
      "سبد خرید",
      items.length
        ? `
        <div class="list">
          ${items
            .map(
              (it, idx) => `
            <div class="item">
              <div class="row">
                <div class="title">${escapeHtml(it.name)}</div>
                <div class="title">${fmtPrice(it.price)} تومان</div>
              </div>
              <button class="btn danger" data-remove="${idx}" type="button">حذف</button>
            </div>
          `
            )
            .join("")}
        </div>
        <div style="margin-top:12px" class="grid2">
          <button class="btn" id="closeCart" type="button">بستن</button>
          <button class="btn primary" id="checkoutCart" type="button" ${items.length ? "" : "disabled"}>پرداخت</button>
        </div>
      `
        : `<div class="muted">سبد خرید خالی است</div><div style="margin-top:12px"><button class="btn primary" id="closeCart" type="button">بستن</button></div>`
    );
    document.getElementById("closeCart")?.addEventListener("click", closeModal);
    el.modalBody.querySelectorAll("[data-remove]").forEach((btn) => {
      btn.addEventListener("click", () => {
        const idx = Number(btn.getAttribute("data-remove"));
        b.cart.splice(idx, 1);
        closeModal();
        renderBuy();
      });
    });
    document.getElementById("checkoutCart")?.addEventListener("click", async () => {
      closeModal();
      await purchaseServices(b.cart.slice());
    });
  });

  document.getElementById("checkout")?.addEventListener("click", async () => {
    await purchaseServices(b.cart.slice());
  });
}

async function purchaseServices(items) {
  const countryId = state.buy.country?.id;
  if (!countryId) return toast("سرور انتخاب نشده است");
  if (!items.length) return toast("سبد خرید خالی است");
  const needUsername = Boolean(state.buy.country?.is_username);
  const needNote = Boolean(state.buy.country?.is_note);
  const username = needUsername ? (await promptText("نام کاربری دلخواه", "مثلاً ali")) : null;
  const note = needNote ? (await promptText("یادداشت", "اختیاری")) : null;

  try {
    setStatus("در حال ثبت خرید...");
    const results = [];
    for (const s of items) {
      const r = await API.miniappPost(state.token, state.userId, {
        actions: "purchase",
        country_id: countryId,
        service_id: s.id,
        custom_username: username,
        custom_note: note,
        custom_service: null,
      });
      if (!r.success) {
        throw new Error(r.msg || r.message || "خطا در خرید");
      }
      results.push(r);
    }
    state.buy.cart = [];
    await loadUserInfo();
    openModal(
      "خرید موفق",
      `
        <div class="list">
          ${results
            .map(
              (r) => `
            <div class="item">
              <div class="row">
                <div class="title">کد پیگیری</div>
                <div class="pill ok">${escapeHtml(r.order_id)}</div>
              </div>
              <div class="muted">نام کاربری سرویس: ${escapeHtml(r.service?.username || "")}</div>
            </div>
          `
            )
            .join("")}
        </div>
        <div style="margin-top:12px">
          <button class="btn primary" id="closeSuccess" type="button">باشه</button>
        </div>
      `
    );
    document.getElementById("closeSuccess")?.addEventListener("click", closeModal);
    render();
  } catch (e) {
    toast(e.message);
  } finally {
    setStatus("آماده");
  }
}

function promptText(title, placeholder) {
  return new Promise((resolve) => {
    openModal(
      title,
      `
      <div class="stack">
        <input class="input" id="promptInput" placeholder="${escapeHtml(placeholder)}" />
        <div class="grid2">
          <button class="btn" id="cancelBtn" type="button">انصراف</button>
          <button class="btn primary" id="okBtn" type="button">ثبت</button>
        </div>
      </div>
    `
    );
    document.getElementById("cancelBtn")?.addEventListener("click", () => {
      closeModal();
      resolve(null);
    });
    document.getElementById("okBtn")?.addEventListener("click", () => {
      const v = document.getElementById("promptInput")?.value || "";
      closeModal();
      resolve(v.trim() || null);
    });
  });
}

function renderWallet() {
  const u = state.userInfo;
  const w = state.wallet;
  el.content.innerHTML = `
    <div class="stack">
      <div class="card">
        <div class="row">
          <div class="title">موجودی کیف پول</div>
          <div class="title">${fmtPrice(u?.balance)} تومان</div>
        </div>
        <div class="muted" style="margin-top:8px">افزایش موجودی از طریق کارت به کارت</div>
        <div class="grid2" style="margin-top:10px">
          <input class="input" id="topupAmount" inputmode="numeric" placeholder="مبلغ (تومان)" />
          <button class="btn primary" id="createTopup" type="button">ایجاد درخواست</button>
        </div>
      </div>
      <div class="card">
        <div class="row">
          <div class="title">تراکنش‌ها</div>
          <button class="btn" id="refreshPayments" type="button">بروزرسانی</button>
        </div>
        <div class="list" style="margin-top:10px">
          ${
            w.payments.length
              ? w.payments
                  .map(
                    (p) => `
            <div class="item">
              <div class="row">
                <div class="title">${escapeHtml(p.id_order)}</div>
                <div>${pillStatus(p.payment_Status)}</div>
              </div>
              <div class="row">
                <div class="muted">${escapeHtml(p.Payment_Method || "")}</div>
                <div class="title">${fmtPrice(p.price)} تومان</div>
              </div>
              <div class="muted">${escapeHtml(p.time || "")}</div>
            </div>
          `
                  )
                  .join("")
              : `<div class="muted">تراکنشی یافت نشد</div>`
          }
        </div>
      </div>
    </div>
  `;

  document.getElementById("refreshPayments")?.addEventListener("click", async () => {
    try {
      setStatus("در حال دریافت...");
      await loadPayments();
      renderWallet();
    } catch (e) {
      toast(e.message);
    } finally {
      setStatus("آماده");
    }
  });

  document.getElementById("createTopup")?.addEventListener("click", async () => {
    const amountStr = document.getElementById("topupAmount")?.value || "";
    const amount = Number(String(amountStr).replaceAll(",", "").trim());
    if (!Number.isFinite(amount) || amount <= 0) return toast("مبلغ معتبر نیست");
    try {
      setStatus("در حال ایجاد...");
      const r = await API.miniappPost(state.token, state.userId, { actions: "topup_create", amount: Math.round(amount) });
      const o = r.obj;
      state.wallet.topup = o;
      openModal(
        "کارت به کارت",
        `
        <div class="stack">
          <div class="item">
            <div class="row"><div class="muted">مبلغ</div><div class="title">${fmtPrice(o.amount)} تومان</div></div>
            <div class="row"><div class="muted">شماره کارت</div><div class="title" style="direction:ltr">${escapeHtml(o.card_number)}</div></div>
            <div class="muted">${escapeHtml(o.card_name)}</div>
            <div class="row" style="margin-top:8px">
              <button class="btn" id="copyCard" type="button">کپی کارت</button>
              <button class="btn" id="copyAmount" type="button">کپی مبلغ</button>
            </div>
          </div>
          <div class="item">
            <div class="title">ارسال رسید</div>
            <textarea class="textarea" id="receiptDesc" placeholder="کد رهگیری/توضیحات پرداخت"></textarea>
            <button class="btn ok" id="markPaid" type="button">پرداخت کردم</button>
          </div>
        </div>
      `
      );
      document.getElementById("copyCard")?.addEventListener("click", async () => {
        await navigator.clipboard?.writeText(String(o.card_number)).catch(() => null);
        toast("کپی شد");
      });
      document.getElementById("copyAmount")?.addEventListener("click", async () => {
        await navigator.clipboard?.writeText(String(o.amount)).catch(() => null);
        toast("کپی شد");
      });
      document.getElementById("markPaid")?.addEventListener("click", async () => {
        const desc = document.getElementById("receiptDesc")?.value || "";
        try {
          setStatus("در حال ثبت رسید...");
          await API.miniappPost(state.token, state.userId, { actions: "topup_mark_paid", order_id: o.order_id, description: desc.trim() });
          closeModal();
          await loadPayments();
          renderWallet();
          toast("ثبت شد");
        } catch (e) {
          toast(e.message);
        } finally {
          setStatus("آماده");
        }
      });
    } catch (e) {
      toast(e.message);
    } finally {
      setStatus("آماده");
    }
  });
}

function renderProfile() {
  const p = state.profile;
  el.content.innerHTML = `
    <div class="stack">
      <div class="card">
        <div class="title">اطلاعات</div>
        <div class="row" style="margin-top:10px">
          <div class="muted">آیدی</div>
          <div class="title">${escapeHtml(String(state.userId || ""))}</div>
        </div>
        <div class="row" style="margin-top:8px">
          <div class="muted">شماره</div>
          <div class="title">${escapeHtml(p?.phone || "ثبت نشده")}</div>
        </div>
        <div style="margin-top:10px">
          <button class="btn" id="requestPhone" type="button">دریافت شماره از تلگرام</button>
        </div>
      </div>
      <div class="card">
        <div class="title">تنظیمات</div>
        <div class="muted" style="margin-top:8px">شماره کارت/شبا (اختیاری)</div>
        <input class="input" id="cardpayment" placeholder="مثلاً 6037..." value="${escapeHtml(p?.cardpayment || "")}" />
        <div class="muted" style="margin-top:8px">نام دلخواه (اختیاری)</div>
        <input class="input" id="namecustom" placeholder="نام نمایشی" value="${escapeHtml(p?.namecustom || "")}" />
        <div style="margin-top:10px">
          <button class="btn primary" id="saveProfile" type="button">ذخیره</button>
        </div>
      </div>
    </div>
  `;

  document.getElementById("saveProfile")?.addEventListener("click", async () => {
    const cardpayment = document.getElementById("cardpayment")?.value || "";
    const namecustom = document.getElementById("namecustom")?.value || "";
    try {
      setStatus("در حال ذخیره...");
      await API.miniappPost(state.token, state.userId, { actions: "profile_update", cardpayment, namecustom });
      await loadProfile();
      toast("ذخیره شد");
    } catch (e) {
      toast(e.message);
    } finally {
      setStatus("آماده");
    }
  });

  document.getElementById("requestPhone")?.addEventListener("click", async () => {
    const tg = window.Telegram?.WebApp;
    if (!tg?.requestContact) return toast("این نسخه از تلگرام از دریافت شماره پشتیبانی نمی‌کند");
    try {
      setStatus("در حال درخواست...");
      const r = await tg.requestContact();
      const phone = r?.contact?.phone_number ? `+${String(r.contact.phone_number).replaceAll("+", "")}` : null;
      if (!phone) throw new Error("شماره دریافت نشد");
      await API.miniappPost(state.token, state.userId, { actions: "profile_update", phone });
      await loadProfile();
      toast("شماره ثبت شد");
    } catch (e) {
      toast(e.message);
    } finally {
      setStatus("آماده");
    }
  });
}

function renderAdmin() {
  if (!state.admin) {
    el.content.innerHTML = `<div class="card"><div class="title">دسترسی ندارید</div><div class="muted" style="margin-top:8px">این بخش فقط برای ادمین فعال است.</div></div>`;
    return;
  }
  el.content.innerHTML = `
    <div class="stack">
      <div class="card">
        <div class="row">
          <div class="title">داشبورد ادمین</div>
          <button class="btn" id="refreshAdmin" type="button">بروزرسانی</button>
        </div>
        <div id="adminStats" class="muted" style="margin-top:10px">...</div>
      </div>
    </div>
  `;
  document.getElementById("refreshAdmin")?.addEventListener("click", loadAdminStats);
  loadAdminStats().catch(() => null);
}

async function loadAdminStats() {
  try {
    setStatus("در حال دریافت...");
    const r = await API.miniappGet(state.token, state.userId, { actions: "admin_dashboard" });
    const s = r.obj;
    const box = document.getElementById("adminStats");
    if (box) {
      box.innerHTML = `
        <div class="list">
          <div class="item"><div class="row"><div class="muted">کاربران</div><div class="title">${escapeHtml(String(s.total_users))}</div></div></div>
          <div class="item"><div class="row"><div class="muted">سرویس‌های فعال</div><div class="title">${escapeHtml(String(s.active_services))}</div></div></div>
          <div class="item"><div class="row"><div class="muted">پرداخت‌های تایید شده</div><div class="title">${escapeHtml(String(s.paid_payments))}</div></div></div>
          <div class="item"><div class="row"><div class="muted">در انتظار بررسی</div><div class="title">${escapeHtml(String(s.waiting_payments))}</div></div></div>
        </div>
      `;
    }
  } catch (e) {
    toast(e.message);
  } finally {
    setStatus("آماده");
  }
}

async function bootstrap() {
  try {
    setStatus("در حال اتصال...");
    const tg = window.Telegram?.WebApp;
    tg?.ready?.();
    tg?.expand?.();
    const initData = tg?.initData || "";
    if (!initData) throw new Error("initData در دسترس نیست. مینی‌اپ را داخل تلگرام باز کنید.");
    const { token, userId } = await API.verify(initData);
    state.token = token;
    state.userId = userId;
    await Promise.all([loadUserInfo(), loadProfile()]);
    await detectAdmin();
    await loadBuyLists();
    await loadPayments();
    setStatus("آماده");
    render();
  } catch (e) {
    setStatus("خطا");
    el.content.innerHTML = `
      <div class="card">
        <div class="title">خطا</div>
        <div class="muted" style="margin-top:8px">${escapeHtml(e.message)}</div>
      </div>
    `;
  }
}

bootstrap();
