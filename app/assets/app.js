const tg = window.Telegram?.WebApp ?? null;
const appEl = document.getElementById("app");

const state = {
  token: null,
  userId: null,
  username: null,
  page: "home",
  loading: false,
  cache: new Map(),
  supportContact: null,
};

const routes = [
  { key: "home", label: "خانه" },
  { key: "wallet", label: "کیف پول" },
  { key: "shop", label: "خرید" },
  { key: "services", label: "سرویس‌ها" },
];

function toFaDigits(input) {
  const s = String(input ?? "");
  const map = { 0: "۰", 1: "۱", 2: "۲", 3: "۳", 4: "۴", 5: "۵", 6: "۶", 7: "۷", 8: "۸", 9: "۹" };
  return s.replace(/[0-9]/g, (d) => map[d]);
}

function fmtToman(value) {
  const n = Number(value ?? 0);
  const text = Number.isFinite(n) ? n.toLocaleString("fa-IR") : "۰";
  return `${text} تومان`;
}

function qs(params) {
  const sp = new URLSearchParams();
  for (const [k, v] of Object.entries(params ?? {})) {
    if (v === undefined || v === null || v === "") continue;
    sp.set(k, String(v));
  }
  return sp.toString();
}

function apiUrl(relativePath, queryParams) {
  const base = new URL(relativePath, window.location.href);
  if (queryParams) base.search = qs(queryParams);
  return base.toString();
}

async function apiFetchJson(url, options) {
  const res = await fetch(url, options);
  const text = await res.text();
  let json = null;
  try {
    json = text ? JSON.parse(text) : null;
  } catch {
    json = null;
  }
  if (!res.ok) {
    const msg = json?.msg || `HTTP ${res.status}`;
    throw new Error(msg);
  }
  if (json && json.status === false) {
    throw new Error(json.msg || "خطا");
  }
  return json;
}

async function verifyToken() {
  if (!tg) throw new Error("این صفحه باید داخل تلگرام باز شود.");
  const initData = tg.initData;
  const initUser = tg.initDataUnsafe?.user;
  if (!initData || !initUser?.id) throw new Error("اطلاعات ورود تلگرام دریافت نشد.");
  const json = await apiFetchJson(apiUrl("../api/verify_miniapp.php"), {
    method: "POST",
    headers: { "Content-Type": "text/plain; charset=utf-8" },
    body: initData,
  });
  if (!json?.token) throw new Error(json?.msg || "احراز هویت ناموفق بود.");
  state.token = String(json.token);
  state.userId = initUser.id;
  state.username = initUser.username || null;
}

function authHeaders() {
  return state.token ? { Authorization: `Bearer ${state.token}` } : {};
}

async function miniappGet(action, params) {
  const query = { actions: action, user_id: state.userId, ...params };
  return apiFetchJson(apiUrl("../api/miniapp.php", query), { headers: { ...authHeaders() } });
}

async function miniappPost(action, body) {
  const payload = { actions: action, user_id: state.userId, ...body };
  return apiFetchJson(apiUrl("../api/miniapp.php"), {
    method: "POST",
    headers: { "Content-Type": "application/json", ...authHeaders() },
    body: JSON.stringify(payload),
  });
}

async function walletGet(action, params) {
  const query = { actions: action, user_id: state.userId, ...params };
  return apiFetchJson(apiUrl("../api/miniapp_wallet.php", query), { headers: { ...authHeaders() } });
}

async function walletPost(action, body) {
  const payload = { actions: action, user_id: state.userId, ...body };
  return apiFetchJson(apiUrl("../api/miniapp_wallet.php"), {
    method: "POST",
    headers: { "Content-Type": "application/json", ...authHeaders() },
    body: JSON.stringify(payload),
  });
}

async function miniappExtGet(action, params) {
  const query = { actions: action, user_id: state.userId, ...params };
  return apiFetchJson(apiUrl("../api/miniapp_ext.php", query), { headers: { ...authHeaders() } });
}

function toast(message, hint) {
  const root = document.createElement("div");
  root.className = "toast";
  root.innerHTML = `<div><div>${escapeHtml(message)}</div><div class="hint">${escapeHtml(hint || "")}</div></div>`;
  document.body.appendChild(root);
  setTimeout(() => root.remove(), 3200);
}

function escapeHtml(s) {
  return String(s ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

async function copyText(text) {
  try {
    await navigator.clipboard.writeText(String(text ?? ""));
    toast("کپی شد", "در کلیپ‌بورد ذخیره شد");
  } catch {
    toast("کپی نشد", "دسترسی کلیپ‌بورد محدود است");
  }
}

function openLink(url) {
  if (!url) return;
  if (tg?.openLink) tg.openLink(url);
  else window.open(url, "_blank", "noopener,noreferrer");
}

function setPage(pageKey) {
  state.page = pageKey;
  window.location.hash = `#/${pageKey}`;
  render();
}

function currentHashPage() {
  const hash = window.location.hash || "";
  const m = hash.match(/^#\/([a-z]+)/i);
  return m?.[1] || "home";
}

function renderTopbar(subtitleRight) {
  const right = subtitleRight ? `<span>${escapeHtml(subtitleRight)}</span>` : `<span class="muted">آماده</span>`;
  return `
    <div class="topbar">
      <div class="title">
        <h1>مینی‌اپ مدیریت و خرید</h1>
        <p>${escapeHtml(state.username ? `@${state.username}` : `شناسه: ${toFaDigits(state.userId)}`)}</p>
      </div>
      <div class="pill">${right}</div>
    </div>
  `;
}

function renderDock() {
  const tabs = routes
    .map((r) => {
      const active = r.key === state.page ? "active" : "";
      return `<div class="tab ${active}" data-tab="${r.key}">${escapeHtml(r.label)}</div>`;
    })
    .join("");
  return `<div class="dock" role="navigation">${tabs}</div>`;
}

function bindDockEvents() {
  document.querySelectorAll(".tab[data-tab]").forEach((el) => {
    el.addEventListener("click", () => setPage(el.getAttribute("data-tab")));
  });
}

function renderSkeleton(title) {
  return `
    ${renderTopbar("در حال دریافت...")}
    <div class="card">
      <h2>${escapeHtml(title)}</h2>
      <div class="muted">لطفاً کمی صبر کنید.</div>
    </div>
  `;
}

async function renderHome() {
  appEl.innerHTML = renderSkeleton("داشبورد");
  bindDockEvents();
  try {
    const [info, meta] = await Promise.all([miniappGet("user_info"), walletGet("meta")]);
    state.supportContact = meta?.obj?.support_contact || meta?.obj?.Channel_Support || meta?.obj?.support_username || null;
    const o = info.obj || {};
    const supportBtn = state.supportContact
      ? `<button class="btn full" id="btnSupport">پشتیبانی</button>`
      : `<button class="btn full" id="btnSupportDisabled" disabled>پشتیبانی</button>`;
    appEl.innerHTML = `
      ${renderTopbar(fmtToman(o.balance))}
      <div class="grid cols-2">
        <div class="card">
          <h2>کیف پول</h2>
          <div class="row"><div class="muted">موجودی</div><div>${fmtToman(o.balance)}</div></div>
          <div class="row"><div class="muted">تراکنش‌ها</div><div>${toFaDigits(o.count_payment ?? 0)}</div></div>
          <div class="row" style="margin-top:10px">
            <button class="btn primary full" id="goWallet">شارژ کیف پول</button>
          </div>
        </div>
        <div class="card">
          <h2>خدمات</h2>
          <div class="row"><div class="muted">سفارش‌ها</div><div>${toFaDigits(o.count_order ?? 0)}</div></div>
          <div class="row"><div class="muted">گروه کاربری</div><div>${escapeHtml(o.group_type ?? "—")}</div></div>
          <div class="row" style="margin-top:10px">
            <button class="btn primary full" id="goShop">خرید سرویس</button>
          </div>
        </div>
      </div>
      <div class="card">
        <h2>دعوت و معرفی</h2>
        <div class="row"><div class="muted">کد دعوت</div><div><span id="invCode">${escapeHtml(o.codeInvitation ?? "—")}</span></div></div>
        <div class="row"><div class="muted">تعداد زیرمجموعه</div><div>${toFaDigits(o.affiliatescount ?? 0)}</div></div>
        <div class="row" style="margin-top:10px; gap:10px">
          <button class="btn" id="copyInv">کپی کد</button>
          ${supportBtn}
        </div>
      </div>
      ${renderDock()}
    `;
    document.getElementById("goWallet")?.addEventListener("click", () => setPage("wallet"));
    document.getElementById("goShop")?.addEventListener("click", () => setPage("shop"));
    document.getElementById("copyInv")?.addEventListener("click", () => copyText(o.codeInvitation ?? ""));
    document.getElementById("btnSupport")?.addEventListener("click", () => {
      const value = String(state.supportContact || "").trim();
      const url = value.startsWith("http") ? value : value.startsWith("@") ? `https://t.me/${value.slice(1)}` : `https://t.me/${value}`;
      openLink(url);
    });
    bindDockEvents();
  } catch (e) {
    appEl.innerHTML = `
      ${renderTopbar("خطا")}
      <div class="card">
        <h2>عدم دسترسی</h2>
        <div class="muted">${escapeHtml(e?.message || "خطا")}</div>
        <div style="margin-top:12px" class="row">
          <button class="btn primary" id="retryHome">تلاش مجدد</button>
        </div>
      </div>
      ${renderDock()}
    `;
    document.getElementById("retryHome")?.addEventListener("click", () => renderHome());
    bindDockEvents();
  }
}

async function renderServices() {
  appEl.innerHTML = renderSkeleton("سرویس‌های من");
  bindDockEvents();
  try {
    const list = await miniappGet("invoices", { page: 1, limit: 10 });
    const rows = Array.isArray(list.obj) ? list.obj : [];
    const items = rows
      .map((it) => {
        const st = String(it.status || "").toLowerCase();
        const badge =
          st === "active" || st === "online"
            ? `<span class="badge green">فعال</span>`
            : st.includes("end")
              ? `<span class="badge amber">در آستانه پایان</span>`
              : `<span class="badge red">نامشخص</span>`;
        return `
          <div class="item" data-username="${escapeHtml(it.username)}">
            <div class="row">
              <div style="font-weight:900">${escapeHtml(it.username)}</div>
              ${badge}
            </div>
            <div class="meta">
              <span>${escapeHtml(it.expire ?? "—")}</span>
              <span>${escapeHtml(it.note ?? "")}</span>
            </div>
            <button class="btn full" data-open="${escapeHtml(it.username)}">نمایش جزئیات</button>
          </div>
        `;
      })
      .join("");
    appEl.innerHTML = `
      ${renderTopbar("سرویس‌های من")}
      <div class="card">
        <h2>لیست سرویس‌ها</h2>
        <div class="muted">برای مشاهده کانفیگ و وضعیت، سرویس را باز کنید.</div>
        <div style="height:12px"></div>
        <div class="list">${items || `<div class="muted">سرویسی یافت نشد.</div>`}</div>
      </div>
      <div class="card" id="serviceDetail" style="display:none"></div>
      ${renderDock()}
    `;
    document.querySelectorAll("button[data-open]").forEach((btn) => {
      btn.addEventListener("click", async () => {
        const username = btn.getAttribute("data-open");
        await openService(username);
      });
    });
    bindDockEvents();
  } catch (e) {
    appEl.innerHTML = `
      ${renderTopbar("خطا")}
      <div class="card">
        <h2>خطا در دریافت سرویس‌ها</h2>
        <div class="muted">${escapeHtml(e?.message || "خطا")}</div>
        <div style="margin-top:12px" class="row">
          <button class="btn primary" id="retryServices">تلاش مجدد</button>
        </div>
      </div>
      ${renderDock()}
    `;
    document.getElementById("retryServices")?.addEventListener("click", () => renderServices());
    bindDockEvents();
  }
}

async function openService(username) {
  const detailEl = document.getElementById("serviceDetail");
  if (!detailEl) return;
  detailEl.style.display = "block";
  detailEl.innerHTML = `<h2>جزئیات</h2><div class="muted">در حال دریافت...</div>`;
  try {
    const res = await miniappExtGet("service_detail", { username });
    const o = res.obj || {};
    const outputs = Array.isArray(o.service_output) ? o.service_output : [];
    const outHtml = outputs
      .map((x, idx) => {
        if (x.type === "link") {
          return `
            <div class="item">
              <div class="row"><div style="font-weight:900">لینک اشتراک</div><span class="badge blue">Sublink</span></div>
              <div class="muted" style="word-break:break-all">${escapeHtml(x.value)}</div>
              <div class="row" style="margin-top:10px; gap:10px">
                <button class="btn" data-copy="${escapeHtml(x.value)}">کپی</button>
                <button class="btn primary" data-openlink="${escapeHtml(x.value)}">باز کردن</button>
              </div>
            </div>
          `;
        }
        if (x.type === "password") {
          return `
            <div class="item">
              <div class="row"><div style="font-weight:900">رمز عبور</div><span class="badge blue">Password</span></div>
              <div class="muted" style="word-break:break-all">${escapeHtml(x.value)}</div>
              <div class="row" style="margin-top:10px">
                <button class="btn full" data-copy="${escapeHtml(x.value)}">کپی</button>
              </div>
            </div>
          `;
        }
        if (x.type === "file") {
          return `
            <div class="item">
              <div class="row"><div style="font-weight:900">فایل کانفیگ</div><span class="badge blue">${escapeHtml(x.filename || "config")}</span></div>
              <div class="muted" style="word-break:break-all">${escapeHtml(x.value)}</div>
              <div class="row" style="margin-top:10px">
                <button class="btn primary full" data-openlink="${escapeHtml(x.value)}">دانلود</button>
              </div>
            </div>
          `;
        }
        if (x.type === "config") {
          const value = Array.isArray(x.value) ? x.value.join("\n") : String(x.value || "");
          return `
            <div class="item">
              <div class="row"><div style="font-weight:900">کانفیگ‌ها</div><span class="badge blue">Configs</span></div>
              <textarea class="input" style="min-height:120px; resize:vertical; font-family:ui-monospace, SFMono-Regular, Menlo, monospace" readonly>${escapeHtml(
                value,
              )}</textarea>
              <div class="row" style="margin-top:10px">
                <button class="btn full" data-copy="${escapeHtml(value)}">کپی همه</button>
              </div>
            </div>
          `;
        }
        return `<div class="item"><div class="muted">خروجی ${toFaDigits(idx + 1)} نامشخص است.</div></div>`;
      })
      .join("");
    const statusBadge =
      String(o.status || "").toLowerCase() === "active" ? `<span class="badge green">فعال</span>` : `<span class="badge red">غیرفعال</span>`;
    detailEl.innerHTML = `
      <h2>جزئیات سرویس</h2>
      <div class="row">
        <div style="font-weight:900">${escapeHtml(o.username || username)}</div>
        ${statusBadge}
      </div>
      <div style="height:10px"></div>
      <div class="item">
        <div class="meta">
          <span>حجم کل: ${toFaDigits(o.total_traffic_gb ?? "—")} GB</span>
          <span>مصرف: ${toFaDigits(o.used_traffic_gb ?? "—")} GB</span>
        </div>
        <div class="meta">
          <span>باقی‌مانده: ${toFaDigits(o.remaining_traffic_gb ?? "—")} GB</span>
          <span>انقضا: ${escapeHtml(o.expiration_time ?? "—")}</span>
        </div>
        <div class="meta">
          <span>آخرین آپدیت: ${escapeHtml(o.last_subscription_update ?? "—")}</span>
          <span>وضعیت اتصال: ${escapeHtml(o.online_at ?? "—")}</span>
        </div>
      </div>
      <div style="height:12px"></div>
      <div class="stack">${outHtml || `<div class="muted">خروجی قابل ارائه‌ای یافت نشد.</div>`}</div>
      <div style="height:12px"></div>
      <button class="btn full" id="closeDetail">بستن</button>
    `;
    detailEl.querySelectorAll("[data-copy]").forEach((el) => {
      el.addEventListener("click", () => copyText(el.getAttribute("data-copy")));
    });
    detailEl.querySelectorAll("[data-openlink]").forEach((el) => {
      el.addEventListener("click", () => openLink(el.getAttribute("data-openlink")));
    });
    detailEl.querySelector("#closeDetail")?.addEventListener("click", () => {
      detailEl.style.display = "none";
      detailEl.innerHTML = "";
    });
  } catch (e) {
    detailEl.innerHTML = `<h2>جزئیات</h2><div class="muted">${escapeHtml(e?.message || "خطا")}</div>`;
  }
}

async function renderShop() {
  appEl.innerHTML = renderSkeleton("خرید سرویس");
  bindDockEvents();
  try {
    const countries = await miniappGet("countries");
    const list = Array.isArray(countries.obj) ? countries.obj : [];
    const countryOptions = list
      .map((c) => `<option value="${escapeHtml(c.id)}">${escapeHtml(c.name)}</option>`)
      .join("");
    appEl.innerHTML = `
      ${renderTopbar("خرید سرویس")}
      <div class="card">
        <h2>انتخاب موقعیت و سرویس</h2>
        <div class="field">
          <div class="label">موقعیت سرویس</div>
          <select class="select" id="countrySel">
            <option value="">انتخاب کنید...</option>
            ${countryOptions}
          </select>
        </div>
        <div style="height:12px"></div>
        <div class="field">
          <div class="label">دسته‌بندی</div>
          <select class="select" id="catSel" disabled><option value="">ابتدا موقعیت را انتخاب کنید</option></select>
        </div>
        <div style="height:12px"></div>
        <div class="field">
          <div class="label">بازه زمانی</div>
          <select class="select" id="timeSel" disabled><option value="">اختیاری</option></select>
        </div>
        <div style="height:12px"></div>
        <button class="btn primary full" id="loadServices" disabled>نمایش سرویس‌ها</button>
      </div>
      <div class="card" id="servicesCard" style="display:none"></div>
      ${renderDock()}
    `;
    const countrySel = document.getElementById("countrySel");
    const catSel = document.getElementById("catSel");
    const timeSel = document.getElementById("timeSel");
    const loadBtn = document.getElementById("loadServices");
    const servicesCard = document.getElementById("servicesCard");

    const countryById = new Map(list.map((x) => [String(x.id), x]));

    countrySel.addEventListener("change", async () => {
      const id = countrySel.value;
      catSel.disabled = true;
      timeSel.disabled = true;
      loadBtn.disabled = true;
      catSel.innerHTML = `<option value="">در حال دریافت...</option>`;
      timeSel.innerHTML = `<option value="0">همه</option>`;
      if (!id) {
        catSel.innerHTML = `<option value="">ابتدا موقعیت را انتخاب کنید</option>`;
        timeSel.innerHTML = `<option value="">اختیاری</option>`;
        return;
      }
      try {
        const [cats, times] = await Promise.all([miniappGet("categories", { country_id: id }), miniappGet("time_ranges", { country_id: id })]);
        const cList = Array.isArray(cats.obj) ? cats.obj : [];
        const tList = Array.isArray(times.obj) ? times.obj : [];
        catSel.innerHTML =
          `<option value="0">همه</option>` +
          cList.map((c) => `<option value="${escapeHtml(c.id)}">${escapeHtml(c.name)}</option>`).join("");
        timeSel.innerHTML =
          `<option value="0">همه</option>` +
          tList.map((t) => `<option value="${escapeHtml(t.day)}">${escapeHtml(t.name)}</option>`).join("");
        catSel.disabled = false;
        timeSel.disabled = false;
        loadBtn.disabled = false;
      } catch (e) {
        toast("خطا در دریافت فیلترها", e?.message || "");
        catSel.innerHTML = `<option value="">خطا</option>`;
        timeSel.innerHTML = `<option value="">خطا</option>`;
      }
    });

    loadBtn.addEventListener("click", async () => {
      const id = countrySel.value;
      if (!id) return;
      servicesCard.style.display = "block";
      servicesCard.innerHTML = `<h2>سرویس‌ها</h2><div class="muted">در حال دریافت...</div>`;
      try {
        const data = await miniappGet("services", {
          country_id: id,
          category_id: catSel.value || 0,
          time_range_day: timeSel.value || 0,
        });
        const items = Array.isArray(data.obj) ? data.obj : [];
        const country = countryById.get(String(id));
        const isCustom = !!country?.is_custom;
        const customBlock = isCustom
          ? `
            <div class="item" id="customBox">
              <div class="row"><div style="font-weight:900">سرویس دلخواه</div><span class="badge blue">Custom</span></div>
              <div class="muted">حجم و زمان را انتخاب کنید و قیمت را آنلاین ببینید.</div>
              <div style="height:10px"></div>
              <div class="grid cols-2">
                <div class="field">
                  <div class="label">حجم (GB)</div>
                  <input id="cTraffic" class="input" inputmode="numeric" placeholder="مثلاً ۵۰" />
                </div>
                <div class="field">
                  <div class="label">زمان (روز)</div>
                  <input id="cTime" class="input" inputmode="numeric" placeholder="مثلاً ۳۰" />
                </div>
              </div>
              <div style="height:10px"></div>
              <div class="row"><div class="muted">قیمت</div><div id="cPrice">—</div></div>
              <div style="height:10px"></div>
              <button class="btn primary full" id="buyCustom" disabled>خرید سرویس دلخواه</button>
            </div>
          `
          : "";
        const html = items
          .map((p) => {
            return `
              <div class="item">
                <div class="row">
                  <div style="font-weight:900">${escapeHtml(p.name)}</div>
                  <span class="badge green">${fmtToman(p.price)}</span>
                </div>
                <div class="muted">${escapeHtml(p.description || "")}</div>
                <div class="meta">
                  <span>حجم: ${toFaDigits(p.traffic_gb)} GB</span>
                  <span>زمان: ${toFaDigits(p.time_days)} روز</span>
                </div>
                <button class="btn full" data-buy="${escapeHtml(p.id)}">خرید</button>
              </div>
            `;
          })
          .join("");
        servicesCard.innerHTML = `
          <h2>سرویس‌ها</h2>
          <div class="muted">پس از خرید، سرویس در بخش «سرویس‌ها» قابل مشاهده است.</div>
          <div style="height:12px"></div>
          <div class="stack">${customBlock}${html || `<div class="muted">سرویسی برای این فیلتر یافت نشد.</div>`}</div>
        `;
        servicesCard.querySelectorAll("button[data-buy]").forEach((btn) => {
          btn.addEventListener("click", async () => {
            const serviceId = btn.getAttribute("data-buy");
            await purchaseService({ countryId: id, serviceId });
          });
        });
        if (isCustom) {
          const cTraffic = document.getElementById("cTraffic");
          const cTime = document.getElementById("cTime");
          const cPrice = document.getElementById("cPrice");
          const buyCustom = document.getElementById("buyCustom");
          let limits = null;
          let latest = 0;
          const refresh = async () => {
            const traffic = Number(cTraffic.value);
            const time = Number(cTime.value);
            if (!Number.isFinite(traffic) || !Number.isFinite(time) || traffic <= 0 || time < 0) {
              cPrice.textContent = "—";
              buyCustom.disabled = true;
              return;
            }
            const stamp = Date.now();
            latest = stamp;
            try {
              const r = await miniappGet("custom_price", { country_id: id, traffic_gb: traffic, time_days: time });
              if (latest !== stamp) return;
              limits = r.obj || null;
              const price = limits?.price;
              if (!price) {
                cPrice.textContent = "ناموجود";
                buyCustom.disabled = true;
                return;
              }
              cPrice.textContent = fmtToman(price);
              buyCustom.disabled = false;
            } catch {
              cPrice.textContent = "خطا";
              buyCustom.disabled = true;
            }
          };
          cTraffic.addEventListener("input", refresh);
          cTime.addEventListener("input", refresh);
          buyCustom.addEventListener("click", async () => {
            const traffic = Number(cTraffic.value);
            const time = Number(cTime.value);
            await purchaseService({ countryId: id, customService: { traffic_gb: traffic, time_days: time } });
          });
        }
      } catch (e) {
        servicesCard.innerHTML = `<h2>سرویس‌ها</h2><div class="muted">${escapeHtml(e?.message || "خطا")}</div>`;
      }
    });
    bindDockEvents();
  } catch (e) {
    appEl.innerHTML = `
      ${renderTopbar("خطا")}
      <div class="card">
        <h2>خطا در دریافت موقعیت‌ها</h2>
        <div class="muted">${escapeHtml(e?.message || "خطا")}</div>
        <div style="margin-top:12px" class="row">
          <button class="btn primary" id="retryShop">تلاش مجدد</button>
        </div>
      </div>
      ${renderDock()}
    `;
    document.getElementById("retryShop")?.addEventListener("click", () => renderShop());
    bindDockEvents();
  }
}

async function purchaseService({ countryId, serviceId, customService }) {
  const confirmText = customService
    ? `خرید سرویس دلخواه (${toFaDigits(customService.traffic_gb)}GB / ${toFaDigits(customService.time_days)}روز) انجام شود؟`
    : "خرید این سرویس انجام شود؟";
  if (tg?.showConfirm) {
    const ok = await new Promise((resolve) => tg.showConfirm(confirmText, resolve));
    if (!ok) return;
  } else if (!window.confirm(confirmText)) {
    return;
  }
  try {
    const res = await miniappPost("purchase", {
      country_id: countryId,
      service_id: serviceId,
      custom_service: customService || null,
      custom_username: null,
      custom_note: null,
    });
    toast("خرید با موفقیت انجام شد", `کد: ${res.order_id ? toFaDigits(res.order_id) : "—"}`);
    setPage("services");
  } catch (e) {
    toast("خرید ناموفق بود", e?.message || "");
  }
}

async function renderWallet() {
  appEl.innerHTML = renderSkeleton("کیف پول");
  bindDockEvents();
  try {
    const [meta, methods, history, info] = await Promise.all([
      walletGet("meta"),
      walletGet("methods"),
      walletGet("history"),
      miniappGet("user_info"),
    ]);
    state.supportContact = meta?.obj?.support_contact || meta?.obj?.Channel_Support || meta?.obj?.support_username || null;
    const methodList = Array.isArray(methods.obj) ? methods.obj : [];
    const methodOptions = methodList.map((m) => `<option value="${escapeHtml(m.key)}">${escapeHtml(m.title)}</option>`).join("");
    const payments = Array.isArray(history.obj) ? history.obj : [];
    const rows = payments
      .map((p) => {
        const st = String(p.payment_Status || p.payment_status || "").toLowerCase();
        const badge =
          st === "paid" ? `<span class="badge green">پرداخت‌شده</span>` : st === "unpaid" ? `<span class="badge red">پرداخت‌نشده</span>` : `<span class="badge blue">در انتظار</span>`;
        return `
          <div class="item">
            <div class="row">
              <div style="font-weight:900">${escapeHtml(p.id_order || p.id || "—")}</div>
              ${badge}
            </div>
            <div class="meta">
              <span>${escapeHtml(p.time || "—")}</span>
              <span>${fmtToman(p.price)}</span>
            </div>
            <div class="meta">
              <span class="muted">${escapeHtml(p.Payment_Method || p.method || "")}</span>
              <button class="btn" data-check="${escapeHtml(p.id_order || "")}">بررسی</button>
            </div>
          </div>
        `;
      })
      .join("");
    appEl.innerHTML = `
      ${renderTopbar(fmtToman(info?.obj?.balance))}
      <div class="card">
        <h2>شارژ کیف پول</h2>
        <div class="muted">پس از پرداخت، موجودی به‌صورت خودکار شارژ می‌شود.</div>
        <div style="height:12px"></div>
        <div class="field">
          <div class="label">مبلغ (تومان)</div>
          <input id="amount" class="input" inputmode="numeric" placeholder="مثلاً ۱۰۰۰۰۰" />
        </div>
        <div style="height:12px"></div>
        <div class="field">
          <div class="label">روش پرداخت</div>
          <select id="method" class="select">
            <option value="">انتخاب کنید...</option>
            ${methodOptions}
          </select>
        </div>
        <div style="height:12px"></div>
        <button class="btn primary full" id="createPay" disabled>ایجاد لینک پرداخت</button>
      </div>
      <div class="card">
        <h2>تراکنش‌های اخیر</h2>
        <div class="list">${rows || `<div class="muted">تراکنشی ثبت نشده است.</div>`}</div>
      </div>
      ${renderDock()}
    `;
    const amountEl = document.getElementById("amount");
    const methodEl = document.getElementById("method");
    const createBtn = document.getElementById("createPay");
    const enableBtn = () => {
      const a = Number(String(amountEl.value).replaceAll(",", "").trim());
      createBtn.disabled = !methodEl.value || !Number.isFinite(a) || a <= 0;
    };
    amountEl.addEventListener("input", enableBtn);
    methodEl.addEventListener("change", enableBtn);
    createBtn.addEventListener("click", async () => {
      const amount = Number(String(amountEl.value).replaceAll(",", "").trim());
      const method = methodEl.value;
      if (!method || !Number.isFinite(amount) || amount <= 0) return;
      try {
        const res = await walletPost("create", { amount, method });
        toast("لینک پرداخت آماده شد", `کد: ${toFaDigits(res.obj?.order_id || "—")}`);
        openLink(res.obj?.payment_url);
      } catch (e) {
        toast("ساخت لینک ناموفق بود", e?.message || "");
      }
    });
    document.querySelectorAll("[data-check]").forEach((btn) => {
      btn.addEventListener("click", async () => {
        const id = btn.getAttribute("data-check");
        try {
          const st = await walletGet("status", { order_id: id });
          toast("وضعیت تراکنش", st.obj?.payment_status || st.obj?.payment_Status || "—");
        } catch (e) {
          toast("خطا در بررسی", e?.message || "");
        }
      });
    });
    bindDockEvents();
  } catch (e) {
    appEl.innerHTML = `
      ${renderTopbar("خطا")}
      <div class="card">
        <h2>خطا در کیف پول</h2>
        <div class="muted">${escapeHtml(e?.message || "خطا")}</div>
        <div style="margin-top:12px" class="row">
          <button class="btn primary" id="retryWallet">تلاش مجدد</button>
        </div>
      </div>
      ${renderDock()}
    `;
    document.getElementById("retryWallet")?.addEventListener("click", () => renderWallet());
    bindDockEvents();
  }
}

async function render() {
  const page = currentHashPage();
  state.page = routes.some((r) => r.key === page) ? page : "home";
  if (state.page === "home") return renderHome();
  if (state.page === "wallet") return renderWallet();
  if (state.page === "shop") return renderShop();
  if (state.page === "services") return renderServices();
  return renderHome();
}

function startBackground() {
  const canvas = document.getElementById("bg");
  if (!canvas) return;
  const ctx = canvas.getContext("2d", { alpha: true });
  if (!ctx) return;
  let w = 0;
  let h = 0;
  const DPR = Math.min(window.devicePixelRatio || 1, 2);
  const particles = [];
  const count = 64;
  const resize = () => {
    w = Math.floor(window.innerWidth);
    h = Math.floor(window.innerHeight);
    canvas.width = Math.floor(w * DPR);
    canvas.height = Math.floor(h * DPR);
    canvas.style.width = `${w}px`;
    canvas.style.height = `${h}px`;
    ctx.setTransform(DPR, 0, 0, DPR, 0, 0);
  };
  resize();
  window.addEventListener("resize", resize);
  for (let i = 0; i < count; i++) {
    particles.push({
      x: Math.random() * w,
      y: Math.random() * h,
      r: 1 + Math.random() * 2.2,
      vx: (Math.random() - 0.5) * 0.22,
      vy: (Math.random() - 0.5) * 0.22,
      t: Math.random() * 1000,
    });
  }
  const frame = () => {
    ctx.clearRect(0, 0, w, h);
    ctx.globalCompositeOperation = "lighter";
    for (const p of particles) {
      p.t += 0.007;
      p.x += p.vx;
      p.y += p.vy;
      if (p.x < -20) p.x = w + 20;
      if (p.x > w + 20) p.x = -20;
      if (p.y < -20) p.y = h + 20;
      if (p.y > h + 20) p.y = -20;
      const a = 0.35 + 0.25 * Math.sin(p.t);
      const g = ctx.createRadialGradient(p.x, p.y, 0, p.x, p.y, 120);
      g.addColorStop(0, `rgba(0,242,255,${a})`);
      g.addColorStop(0.5, `rgba(192,38,211,${a * 0.55})`);
      g.addColorStop(1, "rgba(0,0,0,0)");
      ctx.fillStyle = g;
      ctx.beginPath();
      ctx.arc(p.x, p.y, 120, 0, Math.PI * 2);
      ctx.fill();
    }
    ctx.globalCompositeOperation = "source-over";
    requestAnimationFrame(frame);
  };
  requestAnimationFrame(frame);
}

async function bootstrap() {
  startBackground();
  try {
    if (tg) {
      tg.ready();
      tg.expand?.();
      tg.setHeaderColor?.("#050509");
      tg.setBackgroundColor?.("#050509");
    }
    await verifyToken();
    await render();
  } catch (e) {
    appEl.innerHTML = `
      ${renderTopbar("نیاز به تلگرام")}
      <div class="card">
        <h2>دسترسی محدود</h2>
        <div class="muted">${escapeHtml(e?.message || "خطا")}</div>
      </div>
    `;
  }
}

window.addEventListener("hashchange", () => render());
bootstrap();
