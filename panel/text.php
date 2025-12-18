<?php
session_start();
// Error Reporting
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once '../config.php';
require_once '../function.php';
if (file_exists('../jdf.php')) require_once '../jdf.php';

// Auth
$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
$query->execute();
$result = $query->fetch(PDO::FETCH_ASSOC);

if (!isset($_SESSION["user"]) || !$result) {
    header('Location: login.php');
    exit;
}

// Handle Save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jsonData = file_get_contents('php://input');
    $dataArray = json_decode($jsonData, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        file_put_contents('../text.json', json_encode($dataArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success']);
        exit;
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
        exit;
    }
}

// Handle Get JSON
if (isset($_GET['action']) && $_GET['action'] === 'get_json') {
    $jsonPath = '../text.json';
    if (file_exists($jsonPath)) {
        header('Content-Type: application/json');
        readfile($jsonPath);
    } else {
        echo json_encode([]);
    }
    exit;
}

$todayDate = function_exists('jdate') ? jdate('l، j F Y') : date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل مدیریت محتوای Cosmic</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    
    <style>
        :root {
            --bg-deep: #020617;
            --accent-primary: #8b5cf6;
            --accent-glow: rgba(139, 92, 246, 0.4);
            --glass-panel: rgba(15, 23, 42, 0.6);
            --glass-border: rgba(255, 255, 255, 0.08);
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
        }

        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: var(--bg-deep);
            color: var(--text-main);
            height: 100vh;
            overflow: hidden;
            display: flex; flex-direction: column;
        }

        /* --- COSMIC BACKGROUND --- */
        .cosmic-bg {
            position: fixed; inset: 0; z-index: -2;
            background: radial-gradient(circle at 50% 120%, #2e1065, #020617 50%);
        }
        .star-field {
            position: fixed; inset: 0; z-index: -1; opacity: 0.3;
            background-image: 
                radial-gradient(1px 1px at 20px 30px, #fff, transparent),
                radial-gradient(1px 1px at 50px 160px, #fff, transparent),
                radial-gradient(2px 2px at 90px 40px, #fff, transparent);
            background-size: 250px 250px;
            animation: starMove 120s linear infinite;
        }
        @keyframes starMove { from { background-position: 0 0; } to { background-position: 0 1000px; } }

        /* --- FLOATING ANIMATION --- */
        .floating-card {
            background: var(--glass-panel);
            backdrop-filter: blur(15px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .floating-card:hover {
            transform: translateY(-8px) scale(1.02);
            border-color: rgba(139, 92, 246, 0.4);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 0 25px rgba(139, 92, 246, 0.2);
            background: rgba(30, 41, 59, 0.7);
        }

        .sidebar-item {
            cursor: pointer; padding: 14px 20px; border-radius: 16px;
            transition: all 0.3s; display: flex; align-items: center; gap: 12px;
            margin-bottom: 8px; color: var(--text-muted); font-weight: 700;
        }
        .sidebar-item:hover { background: rgba(255, 255, 255, 0.05); color: #fff; }
        .sidebar-item.active {
            background: linear-gradient(to left, rgba(139, 92, 246, 0.2), transparent);
            border: 1px solid rgba(139, 92, 246, 0.3); color: #fff;
            box-shadow: 10px 0 20px -10px var(--accent-primary);
        }

        .text-input {
            width: 100%; background: rgba(2, 6, 23, 0.5);
            border: 1px solid var(--glass-border); border-radius: 12px;
            padding: 12px; color: #fff; transition: all 0.2s;
            font-size: 0.85rem; line-height: 1.6; resize: none;
        }
        .text-input:focus {
            outline: none; border-color: var(--accent-primary);
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }

        .section-header {
            padding: 18px 22px; display: flex; align-items: center; justify-content: space-between;
            background: rgba(255, 255, 255, 0.03); border-radius: 20px;
        }

        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }

        @keyframes pageIn { from { opacity: 0; transform: scale(0.98) translateY(10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
        .page-animation { animation: pageIn 0.5s cubic-bezier(0.4, 0, 0.2, 1) forwards; }

        .stat-badge {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--glass-border);
            padding: 8px 16px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
    </style>
</head>
<body>

    <div class="cosmic-bg"></div>
    <div class="star-field"></div>

    <!-- Top Navigation -->
    <header class="h-[80px] border-b border-white/5 bg-[#020617]/80 backdrop-blur-xl flex items-center justify-between px-10 z-[100] sticky top-0">
        <div class="flex items-center gap-5">
            <div class="w-12 h-12 rounded-2xl bg-violet-600/20 flex items-center justify-center border border-violet-500/25 shadow-lg shadow-violet-500/10">
                <i class="fa-solid fa-feather-pointed text-violet-400 text-xl"></i>
            </div>
            <div>
                <h1 class="text-xl font-black text-white leading-none tracking-tight">پنل مدیریت <span class="text-violet-400">محتوا</span></h1>
                <p class="text-[9px] text-slate-500 font-bold uppercase tracking-[0.2em] mt-1">Robot Intelligence Core</p>
            </div>
        </div>

        <div class="flex items-center gap-4">
            <div class="hidden md:flex stat-badge">
                <i class="fa-regular fa-clock text-violet-400 text-xs"></i>
                <span class="text-xs text-slate-300 font-bold"><?php echo $todayDate; ?></span>
            </div>
            <div class="w-px h-8 bg-white/10 mx-2"></div>
            <button onclick="App.save()" id="btn-save" class="h-11 px-8 rounded-xl bg-gradient-to-r from-violet-600 to-indigo-600 text-white text-sm font-black shadow-xl shadow-violet-600/30 disabled:opacity-20 transition-all hover:scale-105 active:scale-95" disabled>
                <i class="fa-solid fa-cloud-arrow-up ml-2"></i> ذخیره تغییرات
            </button>
        </div>
    </header>

    <main class="flex-1 flex overflow-hidden">
        
        <!-- Sidebar Navigation -->
        <aside class="w-[300px] border-l border-white/5 p-8 flex flex-col bg-[#020617]/30">
            <h3 class="text-[10px] font-black text-slate-500 uppercase tracking-[0.25em] mb-6 px-4">منوی ناوبری</h3>
            <nav id="sidebar-nav" class="flex-1 overflow-y-auto custom-scrollbar">
                <div class="sidebar-item active" onclick="App.switchPage('all', this)">
                    <i class="fa-solid fa-grid-2 text-blue-400"></i>
                    <span class="text-sm">همه پیام‌ها</span>
                </div>
                <div class="sidebar-item" onclick="App.switchPage('admin', this)">
                    <i class="fa-solid fa-shield-halved text-rose-400"></i>
                    <span class="text-sm">مدیریت (Admin)</span>
                </div>
                <div class="sidebar-item" onclick="App.switchPage('user', this)">
                    <i class="fa-solid fa-user-astronaut text-emerald-400"></i>
                    <span class="text-sm">کاربری (User)</span>
                </div>
                <div class="sidebar-item" onclick="App.switchPage('service', this)">
                    <i class="fa-solid fa-credit-card text-blue-400"></i>
                    <span class="text-sm">سرویس و مالی</span>
                </div>
                <div class="sidebar-item" onclick="App.switchPage('other', this)">
                    <i class="fa-solid fa-ellipsis text-slate-400"></i>
                    <span class="text-sm">سایر موارد</span>
                </div>
            </nav>

            <div class="mt-8 space-y-3">
                <button onclick="App.openRaw()" class="w-full py-3.5 rounded-xl bg-white/5 border border-white/5 text-slate-400 text-xs font-black hover:bg-white/10 hover:text-white transition-all flex items-center justify-center gap-3">
                    <i class="fa-solid fa-code"></i> ویرایش خام JSON
                </button>
                <button onclick="App.export()" class="w-full py-3.5 rounded-xl bg-white/5 border border-white/5 text-slate-400 text-xs font-black hover:bg-white/10 transition-all flex items-center justify-center gap-3">
                    <i class="fa-solid fa-download"></i> خروجی پشتیبان
                </button>
            </div>
        </aside>

        <!-- Main Viewport -->
        <section class="flex-1 flex flex-col overflow-hidden bg-slate-900/10">
            
            <!-- Context Header -->
            <div class="px-10 py-6 border-b border-white/5 flex items-center justify-between bg-slate-950/20">
                <div class="flex items-center gap-8">
                    <div>
                        <span id="page-title" class="text-2xl font-black text-white tracking-tight">تمامی متن‌ها</span>
                        <p id="page-desc" class="text-xs text-slate-500 mt-1">مدیریت متمرکز تمامی پاسخ‌های ربات</p>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <div class="relative w-[300px]">
                        <i class="fa-solid fa-magnifying-glass absolute right-4 top-1/2 -translate-y-1/2 text-slate-600"></i>
                        <input type="text" id="searchField" placeholder="جستجو در این صفحه..." class="w-full bg-slate-900/40 border border-white/5 rounded-xl py-2.5 pr-11 pl-4 text-xs focus:outline-none focus:ring-2 focus:ring-violet-500/20 transition-all">
                    </div>
                    <div class="w-px h-8 bg-white/10 mx-2"></div>
                    <div class="text-left">
                        <span class="block text-[9px] text-slate-500 font-black uppercase tracking-widest">موجودی مخزن</span>
                        <span id="stat-keys" class="text-xs text-violet-400 font-black">0 کلید</span>
                    </div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="flex-1 overflow-y-auto p-10 custom-scrollbar" id="main-content">
                <div id="editor-container" class="page-animation grid grid-cols-1 xl:grid-cols-2 2xl:grid-cols-3 gap-8">
                    <!-- Dynamic sections will render here -->
                </div>
            </div>
        </section>

    </main>

    <!-- Raw Data Modal -->
    <div id="rawModal" class="fixed inset-0 z-[2000] hidden flex items-center justify-center p-10 bg-black/95 backdrop-blur-2xl">
        <div class="floating-card w-full max-w-6xl h-[80vh] flex flex-col overflow-hidden shadow-[0_0_100px_rgba(139,92,246,0.1)]">
            <div class="p-6 border-b border-white/5 flex justify-between items-center bg-white/5">
                <div class="flex items-center gap-3">
                    <i class="fa-solid fa-terminal text-emerald-400"></i>
                    <h3 class="font-black">ویرایش مستقیم پایگاه داده JSON</h3>
                </div>
                <button onclick="App.closeRaw()" class="w-10 h-10 rounded-full hover:bg-white/10 transition-all text-slate-400"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="flex-1 bg-slate-950 p-6">
                <textarea id="rawTextarea" class="w-full h-full bg-transparent border-none font-mono text-sm text-blue-300 outline-none resize-none custom-scrollbar" dir="ltr" spellcheck="false"></textarea>
            </div>
            <div class="p-6 border-t border-white/5 flex justify-end gap-4">
                <button onclick="App.closeRaw()" class="px-6 py-2.5 text-slate-500 text-sm font-bold">لغو</button>
                <button onclick="App.applyRaw()" class="px-10 py-2.5 bg-emerald-600 text-white text-sm font-black rounded-xl transition-all">به‌روزرسانی مخزن</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const App = {
            data: {},
            original: '',
            activeTab: 'all',
            
            init() {
                this.load();
                this.setupSearch();
            },

            async load() {
                try {
                    const res = await fetch('text.php?action=get_json&v=' + Date.now());
                    this.data = await res.json();
                    this.original = JSON.stringify(this.data);
                    this.render();
                    this.updateStats();
                } catch (e) { console.error("Critical: Cannot fetch database"); }
            },

            updateStats() {
                let count = 0;
                const counter = (o) => Object.values(o).forEach(v => typeof v === 'object' ? counter(v) : count++);
                counter(this.data);
                document.getElementById('stat-keys').innerText = count + " کلید فعال";
            },

            getCategory(section) {
                const s = section.toLowerCase();
                if (s.includes('admin') || s.includes('panel') || s.includes('broadcast') || s.includes('backup')) return 'admin';
                if (s.includes('sell') || s.includes('buy') || s.includes('money') || s.includes('wallet') || s.includes('tariff')) return 'service';
                if (s.includes('user') || s.includes('profile') || s.includes('welcome') || s.includes('help') || s.includes('support')) return 'user';
                return 'other';
            },

            switchPage(tab, el) {
                this.activeTab = tab;
                document.querySelectorAll('.sidebar-item').forEach(i => i.classList.remove('active'));
                el.classList.add('active');
                
                // Update UI Headings
                const titles = { all: 'تمامی متن‌ها', admin: 'مدیریت و امنیت', user: 'ارتباطات کاربری', service: 'سرویس و مالی', other: 'سایر موارد' };
                const descs = { all: 'مدیریت متمرکز تمامی پاسخ‌های ربات', admin: 'تنظیمات پیام‌های ادمین و سیستم', user: 'پاسخ‌های خودکار و خوش‌آمدگویی کاربران', service: 'پیام‌های تراکنش، خرید و تمدید سرویس', other: 'تنظیمات متفرقه و کلیدهای عمومی' };
                
                document.getElementById('page-title').innerText = titles[tab];
                document.getElementById('page-desc').innerText = descs[tab];

                // Re-render with animation
                const container = document.getElementById('editor-container');
                container.classList.remove('page-animation');
                void container.offsetWidth;
                container.classList.add('page-animation');
                
                this.render();
                document.getElementById('main-content').scrollTop = 0;
            },

            render() {
                const container = document.getElementById('editor-container');
                container.innerHTML = '';
                
                let found = 0;
                Object.entries(this.data).forEach(([section, contents]) => {
                    const category = this.getCategory(section);
                    if (this.activeTab !== 'all' && this.activeTab !== category) return;
                    found++;

                    const sectionDiv = document.createElement('div');
                    sectionDiv.className = 'floating-card overflow-hidden flex flex-col h-fit';
                    
                    const header = document.createElement('div');
                    header.className = 'section-header';
                    const iconColor = category === 'admin' ? 'rose' : (category === 'service' ? 'blue' : 'emerald');
                    
                    header.innerHTML = `
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-xl bg-${iconColor}-500/10 flex items-center justify-center text-${iconColor}-400 border border-${iconColor}-500/10 shadow-inner">
                                <i class="fa-solid fa-folder-tree"></i>
                            </div>
                            <div>
                                <span class="block font-black text-slate-200 text-sm tracking-tight">${section}</span>
                                <span class="text-[9px] text-slate-500 font-bold uppercase tracking-widest">${Object.keys(contents).length} ورودی</span>
                            </div>
                        </div>
                    `;

                    const contentDiv = document.createElement('div');
                    contentDiv.className = 'p-6 space-y-6 flex-1';
                    this.buildFields(contents, contentDiv, section);

                    sectionDiv.appendChild(header);
                    sectionDiv.appendChild(contentDiv);
                    container.appendChild(sectionDiv);
                });

                if (found === 0) {
                    container.innerHTML = `
                        <div class="col-span-full py-20 flex flex-col items-center justify-center opacity-30">
                            <i class="fa-solid fa-box-open text-6xl mb-4"></i>
                            <span class="font-bold">در این صفحه متنی یافت نشد</span>
                        </div>`;
                }
                
                this.autoResizeAll();
            },

            buildFields(obj, parent, path) {
                Object.entries(obj).forEach(([key, val]) => {
                    const fullPath = `${path}.${key}`;
                    if (typeof val === 'object' && val !== null) {
                        const sub = document.createElement('div');
                        sub.className = 'mr-4 border-r-2 border-white/5 pr-4 mt-2 mb-4';
                        sub.innerHTML = `<div class="text-[9px] font-black uppercase text-violet-400/60 mb-3 flex items-center gap-2"><i class="fa-solid fa-caret-down"></i> ${key}</div>`;
                        this.buildFields(val, sub, fullPath);
                        parent.appendChild(sub);
                    } else {
                        const field = document.createElement('div');
                        field.className = 'field-item group';
                        field.dataset.search = (fullPath + ' ' + val).toLowerCase();
                        field.innerHTML = `
                            <div class="flex flex-col gap-2">
                                <div class="flex items-center justify-between">
                                    <label class="text-[10px] font-mono text-slate-500 group-hover:text-violet-400 transition-colors" dir="ltr">${key}</label>
                                    <button onclick="App.copy('${fullPath}')" class="text-[10px] text-slate-700 hover:text-white transition-opacity opacity-0 group-hover:opacity-100"><i class="fa-regular fa-clone"></i></button>
                                </div>
                                <textarea class="text-input custom-scrollbar" oninput="App.update('${fullPath}', this.value)" rows="1">${val}</textarea>
                            </div>`;
                        parent.appendChild(field);
                    }
                });
            },

            update(path, value) {
                const keys = path.split('.');
                let current = this.data;
                for (let i = 0; i < keys.length - 1; i++) current = current[keys[i]];
                current[keys[keys.length - 1]] = value;
                this.checkChanges();
            },

            checkChanges() {
                const isChanged = JSON.stringify(this.data) !== this.original;
                document.getElementById('btn-save').disabled = !isChanged;
            },

            setupSearch() {
                document.getElementById('searchField').oninput = (e) => {
                    const query = e.target.value.toLowerCase();
                    document.querySelectorAll('.field-item').forEach(f => {
                        f.style.display = f.dataset.search.includes(query) ? 'block' : 'none';
                    });
                    document.querySelectorAll('.floating-card').forEach(s => {
                        const hasVisible = Array.from(s.querySelectorAll('.field-item')).some(f => f.style.display !== 'none');
                        s.style.display = hasVisible ? 'flex' : 'none';
                    });
                };
            },

            async save() {
                const btn = document.getElementById('btn-save');
                btn.disabled = true;
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin ml-2"></i> در حال همگام‌سازی...';

                try {
                    const res = await fetch('text.php', { method: 'POST', body: JSON.stringify(this.data) });
                    const result = await res.json();
                    if (result.status === 'success') {
                        this.original = JSON.stringify(this.data);
                        this.checkChanges();
                        Swal.fire({ icon: 'success', title: 'تغییرات با موفقیت ذخیره شد', toast: true, position: 'top-end', timer: 3000, showConfirmButton: false, background: '#020617', color: '#fff' });
                    }
                } finally { btn.innerHTML = originalText; }
            },

            openRaw() {
                document.getElementById('rawTextarea').value = JSON.stringify(this.data, null, 4);
                document.getElementById('rawModal').classList.remove('hidden');
            },
            closeRaw() { document.getElementById('rawModal').classList.add('hidden'); },
            applyRaw() {
                try {
                    this.data = JSON.parse(document.getElementById('rawTextarea').value);
                    this.render(); this.updateStats(); this.closeRaw(); this.checkChanges();
                } catch (e) { Swal.fire({ icon: 'error', title: 'خطا در ساختار JSON' }); }
            },
            export() {
                const blob = new Blob([JSON.stringify(this.data, null, 4)], {type: 'application/json'});
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a'); a.href = url; a.download = 'robot_text_backup.json'; a.click();
            },
            copy(text) { navigator.clipboard.writeText(text); },
            autoResizeAll() {
                document.querySelectorAll('textarea.text-input').forEach(t => {
                    t.style.height = 'auto';
                    t.style.height = (t.scrollHeight) + 'px';
                    t.addEventListener('input', function() { this.style.height = 'auto'; this.style.height = (this.scrollHeight) + 'px'; });
                });
            }
        };

        App.init();
    </script>
</body>
</html>