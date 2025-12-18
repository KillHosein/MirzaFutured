const STORAGE_KEYS={
  mode:"tg_ui_theme_mode",
  custom:"tg_ui_custom_colors",
  reduceMotion:"tg_ui_reduce_motion",
  fontScale:"tg_ui_font_scale",
  supportLink:"tg_ui_support_link"
};

const DEFAULT_CUSTOM_COLORS={
  background:"#ffffff",
  foreground:"#0f172a",
  primary:"#2563eb",
  primaryForeground:"#ffffff",
  card:"#ffffff",
  muted:"#f1f5f9",
  mutedForeground:"#64748b",
  border:"#e2e8f0",
  ring:"#2563eb"
};

let activeDialogCleanup=null;

function safeJsonParse(value,fallback){
  try{
    if(typeof value!=="string"||value.trim()==="")return fallback;
    return JSON.parse(value);
  }catch{
    return fallback;
  }
}

function normalizeHex(hex){
  if(typeof hex!=="string")return null;
  const cleaned=hex.trim();
  if(/^#[0-9a-fA-F]{3}$/.test(cleaned)){
    return "#"+cleaned.slice(1).split("").map(c=>c+c).join("");
  }
  if(/^#[0-9a-fA-F]{6}$/.test(cleaned))return cleaned.toLowerCase();
  return null;
}

function hexToRgb(hex){
  const n=normalizeHex(hex);
  if(!n)return null;
  const r=parseInt(n.slice(1,3),16);
  const g=parseInt(n.slice(3,5),16);
  const b=parseInt(n.slice(5,7),16);
  return{r,g,b};
}

function rgbToHsl({r,g,b}){
  const r1=r/255,g1=g/255,b1=b/255;
  const max=Math.max(r1,g1,b1),min=Math.min(r1,g1,b1);
  let h=0,s=0;
  const l=(max+min)/2;
  if(max!==min){
    const d=max-min;
    s=l>0.5?d/(2-max-min):d/(max+min);
    switch(max){
      case r1:h=(g1-b1)/d+(g1<b1?6:0);break;
      case g1:h=(b1-r1)/d+2;break;
      default:h=(r1-g1)/d+4;break;
    }
    h/=6;
  }
  return{
    h:Math.round(h*360),
    s:Math.round(s*100),
    l:Math.round(l*100)
  };
}

function hexToHslVar(hex){
  const rgb=hexToRgb(hex);
  if(!rgb)return null;
  const hsl=rgbToHsl(rgb);
  return `${hsl.h} ${hsl.s}% ${hsl.l}%`;
}

function mixHex(a,b,ratio){
  const ra=hexToRgb(a),rb=hexToRgb(b);
  if(!ra||!rb)return null;
  const t=Math.min(1,Math.max(0,ratio));
  const r=Math.round(ra.r*(1-t)+rb.r*t);
  const g=Math.round(ra.g*(1-t)+rb.g*t);
  const b2=Math.round(ra.b*(1-t)+rb.b*t);
  return "#"+[r,g,b2].map(v=>v.toString(16).padStart(2,"0")).join("");
}

function setMetaThemeColor(hex){
  const n=normalizeHex(hex);
  if(!n)return;
  let meta=document.querySelector('meta[name="theme-color"]');
  if(!meta){
    meta=document.createElement("meta");
    meta.setAttribute("name","theme-color");
    document.head.appendChild(meta);
  }
  meta.setAttribute("content",n);
}

function getTelegramWebApp(){
  return window.Telegram&&window.Telegram.WebApp?window.Telegram.WebApp:null;
}

function readSettings(){
  const mode=(localStorage.getItem(STORAGE_KEYS.mode)||"telegram").toLowerCase();
  const custom=safeJsonParse(localStorage.getItem(STORAGE_KEYS.custom),DEFAULT_CUSTOM_COLORS);
  const reduceMotion=localStorage.getItem(STORAGE_KEYS.reduceMotion)==="1";
  const fontScaleRaw=parseFloat(localStorage.getItem(STORAGE_KEYS.fontScale)||"1");
  const fontScale=Number.isFinite(fontScaleRaw)?Math.min(1.25,Math.max(.9,fontScaleRaw)):1;
  const supportLink=localStorage.getItem(STORAGE_KEYS.supportLink)||"";
  return{mode,custom,reduceMotion,fontScale,supportLink};
}

function persistSettings(next){
  localStorage.setItem(STORAGE_KEYS.mode,next.mode);
  localStorage.setItem(STORAGE_KEYS.custom,JSON.stringify(next.custom));
  localStorage.setItem(STORAGE_KEYS.reduceMotion,next.reduceMotion?"1":"0");
  localStorage.setItem(STORAGE_KEYS.fontScale,String(next.fontScale));
  localStorage.setItem(STORAGE_KEYS.supportLink,next.supportLink||"");
}

function applyVars(vars){
  const root=document.documentElement;
  for(const[k,v]of Object.entries(vars)){
    if(typeof v==="string"&&v.trim()!==""){
      root.style.setProperty(`--${k}`,v);
    }
  }
}

function applyTheme(mode,custom){
  const tg=getTelegramWebApp();
  const root=document.documentElement;
  const resolvedMode=(mode==="light"||mode==="dark"||mode==="custom")?mode:"telegram";
  const tgScheme=tg&&typeof tg.colorScheme==="string"?tg.colorScheme.toLowerCase():"";
  const useDark=resolvedMode==="dark"||(resolvedMode==="telegram"&&tgScheme==="dark");

  root.classList.toggle("dark",useDark);
  root.setAttribute("data-tg-ui-mode",resolvedMode);
  root.setAttribute("data-tg-ui-scheme",useDark?"dark":"light");

  if(resolvedMode==="telegram"&&tg){
    const p=tg.themeParams||{};
    const bg=normalizeHex(p.bg_color)||"#ffffff";
    const fg=normalizeHex(p.text_color)||"#0f172a";
    const primary=normalizeHex(p.button_color)||"#2563eb";
    const primaryFg=normalizeHex(p.button_text_color)||"#ffffff";
    const card=normalizeHex(p.secondary_bg_color)||mixHex(bg,fg,.06)||bg;
    const muted=normalizeHex(p.section_bg_color)||mixHex(bg,fg,.04)||bg;
    const border=mixHex(bg,fg,useDark ? .22 : .14)||"#e2e8f0";
    const ring=normalizeHex(p.link_color)||primary;

    applyVars({
      background:hexToHslVar(bg),
      foreground:hexToHslVar(fg),
      card:hexToHslVar(card),
      "card-foreground":hexToHslVar(fg),
      popover:hexToHslVar(card),
      "popover-foreground":hexToHslVar(fg),
      primary:hexToHslVar(primary),
      "primary-foreground":hexToHslVar(primaryFg),
      secondary:hexToHslVar(mixHex(bg,fg,useDark ? .10 : .06)||card),
      "secondary-foreground":hexToHslVar(fg),
      muted:hexToHslVar(muted),
      "muted-foreground":hexToHslVar(normalizeHex(p.hint_color)||mixHex(fg,bg,.45)||"#64748b"),
      accent:hexToHslVar(mixHex(bg,primary,useDark ? .12 : .08)||muted),
      "accent-foreground":hexToHslVar(fg),
      border:hexToHslVar(border),
      input:hexToHslVar(border),
      ring:hexToHslVar(ring)
    });

    setMetaThemeColor(bg);
    return;
  }

  if(resolvedMode==="custom"){
    const bg=normalizeHex(custom.background)||DEFAULT_CUSTOM_COLORS.background;
    const fg=normalizeHex(custom.foreground)||DEFAULT_CUSTOM_COLORS.foreground;
    const primary=normalizeHex(custom.primary)||DEFAULT_CUSTOM_COLORS.primary;
    const primaryFg=normalizeHex(custom.primaryForeground)||DEFAULT_CUSTOM_COLORS.primaryForeground;
    const card=normalizeHex(custom.card)||bg;
    const muted=normalizeHex(custom.muted)||mixHex(bg,fg,.06)||bg;
    const mutedFg=normalizeHex(custom.mutedForeground)||mixHex(fg,bg,.4)||DEFAULT_CUSTOM_COLORS.mutedForeground;
    const border=normalizeHex(custom.border)||mixHex(bg,fg,.16)||DEFAULT_CUSTOM_COLORS.border;
    const ring=normalizeHex(custom.ring)||primary;
    applyVars({
      background:hexToHslVar(bg),
      foreground:hexToHslVar(fg),
      card:hexToHslVar(card),
      "card-foreground":hexToHslVar(fg),
      popover:hexToHslVar(card),
      "popover-foreground":hexToHslVar(fg),
      primary:hexToHslVar(primary),
      "primary-foreground":hexToHslVar(primaryFg),
      secondary:hexToHslVar(mixHex(bg,fg,.06)||muted),
      "secondary-foreground":hexToHslVar(fg),
      muted:hexToHslVar(muted),
      "muted-foreground":hexToHslVar(mutedFg),
      accent:hexToHslVar(mixHex(bg,primary,.10)||muted),
      "accent-foreground":hexToHslVar(fg),
      border:hexToHslVar(border),
      input:hexToHslVar(border),
      ring:hexToHslVar(ring)
    });
    setMetaThemeColor(bg);
    return;
  }

  const bg=resolvedMode==="dark"?"#0b1220":"#ffffff";
  const fg=resolvedMode==="dark"?"#e5e7eb":"#0f172a";
  setMetaThemeColor(bg);
  applyVars({
    background:hexToHslVar(bg),
    foreground:hexToHslVar(fg),
    card:hexToHslVar(mixHex(bg,fg,resolvedMode==="dark" ? .08 : .02)||bg),
    "card-foreground":hexToHslVar(fg),
    popover:hexToHslVar(mixHex(bg,fg,resolvedMode==="dark" ? .10 : .02)||bg),
    "popover-foreground":hexToHslVar(fg),
    primary:hexToHslVar(resolvedMode==="dark"?"#60a5fa":"#2563eb"),
    "primary-foreground":hexToHslVar("#ffffff"),
    secondary:hexToHslVar(mixHex(bg,fg,resolvedMode==="dark" ? .12 : .06)||bg),
    "secondary-foreground":hexToHslVar(fg),
    muted:hexToHslVar(mixHex(bg,fg,resolvedMode==="dark" ? .14 : .06)||bg),
    "muted-foreground":hexToHslVar(mixHex(fg,bg,.45)||"#64748b"),
    accent:hexToHslVar(mixHex(bg,resolvedMode==="dark"?"#60a5fa":"#2563eb",.10)||bg),
    "accent-foreground":hexToHslVar(fg),
    border:hexToHslVar(mixHex(bg,fg,resolvedMode==="dark" ? .20 : .12)||"#e2e8f0"),
    input:hexToHslVar(mixHex(bg,fg,resolvedMode==="dark" ? .20 : .12)||"#e2e8f0"),
    ring:hexToHslVar(resolvedMode==="dark"?"#60a5fa":"#2563eb")
  });
}

function setReduceMotion(enabled){
  document.documentElement.setAttribute("data-tg-ui-reduce-motion",enabled?"1":"0");
}

function setFontScale(scale){
  document.documentElement.style.setProperty("--tg-ui-font-scale",String(scale));
}

function createEl(tag,attrs={},children=[]){
  const el=document.createElement(tag);
  for(const[k,v]of Object.entries(attrs)){
    if(v===null||v===undefined)continue;
    if(k==="class")el.className=String(v);
    else if(k==="text")el.textContent=String(v);
    else if(k.startsWith("on")&&typeof v==="function")el.addEventListener(k.slice(2).toLowerCase(),v);
    else if(k==="dataset"&&typeof v==="object"){
      for(const[dk,dv]of Object.entries(v))el.dataset[dk]=String(dv);
    }else el.setAttribute(k,String(v));
  }
  for(const child of children){
    if(child===null||child===undefined)continue;
    el.appendChild(typeof child==="string"?document.createTextNode(child):child);
  }
  return el;
}

function copyText(text){
  if(typeof text!=="string"||text.trim()==="")return Promise.resolve(false);
  if(navigator.clipboard&&navigator.clipboard.writeText){
    return navigator.clipboard.writeText(text).then(()=>true,()=>false);
  }
  const ta=createEl("textarea",{class:"tg-ui-input"});
  ta.value=text;
  ta.style.position="fixed";
  ta.style.left="-9999px";
  document.body.appendChild(ta);
  ta.select();
  try{
    const ok=document.execCommand("copy");
    return Promise.resolve(!!ok);
  }finally{
    ta.remove();
  }
}

function openSupport(link){
  const tg=getTelegramWebApp();
  const url=(link||"").trim();
  if(!url)return;
  if(tg&&typeof tg.openTelegramLink==="function"&&/^https:\/\/t\.me\//i.test(url)){
    tg.openTelegramLink(url);
    return;
  }
  window.open(url,"_blank","noopener,noreferrer");
}

function tgHaptic(type,style){
  const tg=getTelegramWebApp();
  try{
    if(!tg||!tg.HapticFeedback)return;
    if(type==="impact"&&typeof tg.HapticFeedback.impactOccurred==="function"){
      tg.HapticFeedback.impactOccurred(style||"light");
    }else if(type==="notification"&&typeof tg.HapticFeedback.notificationOccurred==="function"){
      tg.HapticFeedback.notificationOccurred(style||"success");
    }
  }catch{}
}

function createSettingsIcon(){
  const svg=createEl("svg",{width:"20",height:"20",viewBox:"0 0 24 24",fill:"none","aria-hidden":"true"});
  svg.appendChild(createEl("path",{d:"M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z",stroke:"currentColor","stroke-width":"2","stroke-linecap":"round","stroke-linejoin":"round"}));
  svg.appendChild(createEl("path",{d:"M19.4 15a1.8 1.8 0 0 0 .36 1.98l.05.05a2.2 2.2 0 0 1-1.56 3.76 2.2 2.2 0 0 1-1.56-.64l-.05-.05a1.8 1.8 0 0 0-1.98-.36 1.8 1.8 0 0 0-1.08 1.65V21a2.2 2.2 0 0 1-4.4 0v-.07a1.8 1.8 0 0 0-1.08-1.65 1.8 1.8 0 0 0-1.98.36l-.05.05a2.2 2.2 0 1 1-3.12-3.12l.05-.05A1.8 1.8 0 0 0 3 15a1.8 1.8 0 0 0-1.65-1.08H1.3a2.2 2.2 0 0 1 0-4.4h.05A1.8 1.8 0 0 0 3 8.44a1.8 1.8 0 0 0-.36-1.98l-.05-.05A2.2 2.2 0 1 1 5.71 3.3l.05.05A1.8 1.8 0 0 0 7.74 3a1.8 1.8 0 0 0 1.08-1.65V1.3a2.2 2.2 0 0 1 4.4 0v.05A1.8 1.8 0 0 0 14.3 3a1.8 1.8 0 0 0 1.98-.36l.05-.05A2.2 2.2 0 1 1 19.45 5.7l-.05.05A1.8 1.8 0 0 0 19 7.74c0 .7.42 1.32 1.08 1.65h.07a2.2 2.2 0 0 1 0 4.4h-.07A1.8 1.8 0 0 0 19.4 15Z",stroke:"currentColor","stroke-width":"2","stroke-linecap":"round","stroke-linejoin":"round"}));
  return svg;
}

function showSettingsUI(){
  if(activeDialogCleanup){
    activeDialogCleanup();
    return;
  }

  const prevFocus=document.activeElement;
  const state=readSettings();
  const previousOverflow=document.body.style.overflow;

  const close=()=>{
    document.body.style.overflow=previousOverflow;
    document.documentElement.removeAttribute("data-tg-ui-dialog");
    backdrop.remove();
    window.removeEventListener("keydown",onKeyDown);
    activeDialogCleanup=null;
    tgHaptic("impact","light");
    if(prevFocus&&prevFocus.focus)prevFocus.focus();
  };

  const dialogTitleId="tg-ui-title";
  const backdrop=createEl("div",{id:"tg-ui-backdrop",class:"tg-ui-backdrop",role:"dialog","aria-modal":"true","aria-labelledby":dialogTitleId,onClick:e=>{if(e.target===backdrop)close()}});
  const panel=createEl("div",{class:"tg-ui-panel",tabindex:"-1"});
  const header=createEl("header",{},[
    createEl("div",{class:"tg-ui-title",id:dialogTitleId,text:"تنظیمات ظاهر و دسترسی‌پذیری"}),
    createEl("button",{type:"button",class:"tg-ui-close","aria-label":"بستن",onClick:close},["×"])
  ]);

  const themeSelect=createEl("select",{class:"tg-ui-select","aria-label":"انتخاب تم"});
  [
    ["telegram","همگام با تلگرام"],
    ["light","روشن"],
    ["dark","تاریک"],
    ["custom","سفارشی"]
  ].forEach(([value,label])=>{
    themeSelect.appendChild(createEl("option",{value,text:label}));
  });
  themeSelect.value=(state.mode==="light"||state.mode==="dark"||state.mode==="custom")?state.mode:"telegram";

  const reduceBtn=createEl("button",{type:"button",class:"tg-ui-switch",role:"switch","aria-checked":state.reduceMotion?"true":"false","aria-label":"کاهش انیمیشن‌ها",onClick:()=>{
    const next=reduceBtn.getAttribute("aria-checked")!=="true";
    reduceBtn.setAttribute("aria-checked",next?"true":"false");
    state.reduceMotion=next;
    setReduceMotion(next);
    persistSettings(state);
  }});

  const fontScale=createEl("input",{class:"tg-ui-input",type:"range",min:"0.9",max:"1.25",step:"0.05",value:String(state.fontScale),"aria-label":"اندازه فونت"});
  const fontLabel=createEl("span",{text:`${Math.round(state.fontScale*100)}%`});
  fontScale.addEventListener("input",()=>{
    const v=parseFloat(fontScale.value);
    if(!Number.isFinite(v))return;
    state.fontScale=v;
    fontLabel.textContent=`${Math.round(v*100)}%`;
    setFontScale(v);
  });
  fontScale.addEventListener("change",()=>persistSettings(state));

  const supportInput=createEl("input",{class:"tg-ui-input",type:"url",placeholder:"https://t.me/YourSupport",value:state.supportLink,"aria-label":"لینک پشتیبانی"});
  supportInput.addEventListener("change",()=>{
    state.supportLink=supportInput.value.trim();
    persistSettings(state);
  });

  const customWrap=createEl("div",{});
  const colorRows=[
    ["primary","رنگ اصلی"],
    ["background","پس‌زمینه"],
    ["foreground","متن"],
    ["card","کارت"],
    ["border","حاشیه"]
  ].map(([key,label])=>{
    const input=createEl("input",{type:"color","aria-label":label,value:normalizeHex(state.custom[key])||DEFAULT_CUSTOM_COLORS[key]||"#ffffff"});
    input.addEventListener("input",()=>{
      state.custom[key]=input.value;
      applyTheme("custom",state.custom);
    });
    input.addEventListener("change",()=>persistSettings(state));
    return createEl("div",{class:"tg-ui-row"},[
      createEl("label",{text:label}),
      createEl("div",{class:"tg-ui-control"},[input])
    ]);
  });
  colorRows.forEach(r=>customWrap.appendChild(r));

  const themeSection=createEl("section",{class:"tg-ui-section"},[
    createEl("h3",{text:"تم"}),
    createEl("div",{class:"tg-ui-row"},[
      createEl("label",{},[
        createEl("span",{text:"حالت نمایش"}),
        createEl("small",{text:"تم را همگام با تلگرام یا دستی انتخاب کنید"})
      ]),
      createEl("div",{class:"tg-ui-control"},[themeSelect])
    ]),
    customWrap
  ]);

  const accessibilitySection=createEl("section",{class:"tg-ui-section"},[
    createEl("h3",{text:"دسترس‌پذیری"}),
    createEl("div",{class:"tg-ui-row"},[
      createEl("label",{},[
        createEl("span",{text:"کاهش انیمیشن"}),
        createEl("small",{text:"برای کاربران حساس به حرکت"})
      ]),
      createEl("div",{class:"tg-ui-control"},[reduceBtn])
    ]),
    createEl("div",{class:"tg-ui-row"},[
      createEl("label",{},[
        createEl("span",{text:"اندازه فونت"}),
        createEl("small",{text:"نمایش خواناتر در موبایل"})
      ]),
      createEl("div",{class:"tg-ui-control"},[fontScale,fontLabel])
    ])
  ]);

  const actionsSection=createEl("section",{class:"tg-ui-section"},[
    createEl("h3",{text:"ابزارهای سریع"}),
    createEl("div",{class:"tg-ui-row"},[
      createEl("label",{},[
        createEl("span",{text:"پشتیبانی"}),
        createEl("small",{text:"لینک را ذخیره کنید تا با یک کلیک باز شود"})
      ]),
      createEl("div",{class:"tg-ui-control"},[
        createEl("button",{type:"button",class:"tg-ui-button",text:"باز کردن",onClick:()=>openSupport(state.supportLink)})
      ])
    ]),
    createEl("div",{class:"tg-ui-row"},[
      createEl("label",{},[createEl("span",{text:"لینک پشتیبانی"}),createEl("small",{text:"مثال: https://t.me/YourSupport"})]),
      createEl("div",{class:"tg-ui-control"},[supportInput])
    ]),
    createEl("div",{class:"tg-ui-row"},[
      createEl("label",{},[createEl("span",{text:"کپی لینک صفحه"})]),
      createEl("div",{class:"tg-ui-control"},[
        createEl("button",{type:"button",class:"tg-ui-button primary",text:"کپی",onClick:async()=>{
          const ok=await copyText(location.href);
          const tg=getTelegramWebApp();
          if(tg&&tg.HapticFeedback&&tg.HapticFeedback.notificationOccurred){
            tg.HapticFeedback.notificationOccurred(ok?"success":"error");
          }
        }})
      ])
    ]),
    createEl("div",{class:"tg-ui-row"},[
      createEl("label",{},[createEl("span",{text:"رفتن به بالا"})]),
      createEl("div",{class:"tg-ui-control"},[
        createEl("button",{type:"button",class:"tg-ui-button",text:"بالا",onClick:()=>window.scrollTo({top:0,behavior:"smooth"})})
      ])
    ]),
    createEl("div",{class:"tg-ui-row"},[
      createEl("label",{},[createEl("span",{text:"بازنشانی تنظیمات"}),createEl("small",{text:"برگرداندن به حالت پیش‌فرض"})]),
      createEl("div",{class:"tg-ui-control"},[
        createEl("button",{type:"button",class:"tg-ui-button",text:"ریست",onClick:()=>{
          localStorage.removeItem(STORAGE_KEYS.mode);
          localStorage.removeItem(STORAGE_KEYS.custom);
          localStorage.removeItem(STORAGE_KEYS.reduceMotion);
          localStorage.removeItem(STORAGE_KEYS.fontScale);
          localStorage.removeItem(STORAGE_KEYS.supportLink);
          const reset=readSettings();
          setReduceMotion(reset.reduceMotion);
          setFontScale(reset.fontScale);
          applyTheme(reset.mode,reset.custom);
          close();
        }})
      ])
    ])
  ]);

  themeSelect.addEventListener("change",()=>{
    state.mode=themeSelect.value;
    customWrap.style.display=state.mode==="custom"?"block":"none";
    applyTheme(state.mode,state.custom);
    persistSettings(state);
  });
  customWrap.style.display=state.mode==="custom"?"block":"none";

  panel.appendChild(header);
  panel.appendChild(themeSection);
  panel.appendChild(accessibilitySection);
  panel.appendChild(actionsSection);
  backdrop.appendChild(panel);
  document.body.appendChild(backdrop);
  document.body.style.overflow="hidden";
  document.documentElement.setAttribute("data-tg-ui-dialog","1");
  tgHaptic("impact","medium");

  const focusableSelector=[
    'button:not([disabled])',
    'a[href]',
    'input:not([disabled])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])'
  ].join(",");

  const onKeyDown=e=>{
    if(e.key==="Escape"){
      e.preventDefault();
      close();
      return;
    }
    if(e.key!=="Tab")return;
    const focusables=Array.from(panel.querySelectorAll(focusableSelector)).filter(el=>el.offsetParent!==null);
    if(focusables.length===0){
      e.preventDefault();
      panel.focus();
      return;
    }
    const first=focusables[0];
    const last=focusables[focusables.length-1];
    const active=document.activeElement;
    if(e.shiftKey){
      if(active===first||active===panel){
        e.preventDefault();
        last.focus();
      }
    }else{
      if(active===last){
        e.preventDefault();
        first.focus();
      }
    }
  };
  window.addEventListener("keydown",onKeyDown);
  activeDialogCleanup=close;

  setTimeout(()=>themeSelect.focus(),0);
}

function boot(){
  const settings=readSettings();
  setReduceMotion(settings.reduceMotion);
  setFontScale(settings.fontScale);
  applyTheme(settings.mode,settings.custom);

  const tg=getTelegramWebApp();
  if(tg&&typeof tg.ready==="function"){
    try{tg.ready()}catch{}
  }
  if(tg&&typeof tg.expand==="function"){
    try{tg.expand()}catch{}
  }
  if(tg&&tg.SettingsButton){
    try{
      if(typeof tg.SettingsButton.show==="function")tg.SettingsButton.show();
      if(typeof tg.SettingsButton.onClick==="function"){
        tg.SettingsButton.onClick(showSettingsUI);
      }
    }catch{}
  }
  if(tg&&typeof tg.onEvent==="function"){
    try{
      tg.onEvent("themeChanged",()=>{
        const s=readSettings();
        if((s.mode||"telegram")==="telegram"){
          applyTheme("telegram",s.custom);
        }
      });
    }catch{}
  }

  const skip=createEl("a",{href:"#root",class:"tg-ui-skip-link",text:"پرش به محتوا"});
  document.body.appendChild(skip);

  const btn=createEl("button",{type:"button",class:"tg-ui-fab","aria-label":"تنظیمات رابط کاربری",onClick:showSettingsUI},[createSettingsIcon()]);
  document.body.appendChild(btn);
}

if(document.readyState==="loading"){
  document.addEventListener("DOMContentLoaded",boot,{once:true});
}else{
  boot();
}
