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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>مدیریت پیشرفته محتوای هوشمند</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    
    <style>
        :root {
            --bg-deep: #020617;
            --accent-primary: #8b5cf6;
            --accent-admin: #f43f5e;
            --accent-user: #10b981;
            --accent-fin: #3b82f6;
            --glass-panel: rgba(15, 23, 42, 0.7);
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
            background-image: 
                radial-gradient(circle at 10% 10%, rgba(139, 92, 246, 0.08) 0%, transparent 40%),
                radial-gradient(circle at 90% 90%, rgba(244, 63, 94, 0.08) 0%, transparent 40%);
        }

        .custom-scrollbar::-webkit-scrollbar { width: 5px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: var(--accent-primary); }

        .glass-card {
            background: var(--glass-panel);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .glass-card:hover { border-color: rgba(255,255,255,0.15); transform: translateY(-2px); }

        .sidebar-item {
            cursor: pointer; padding: 12px 16px; border-radius: 14px;
            transition: all 0.2s; display: flex; align-items: center; gap: 12px;
            margin-bottom: 4px; color: var(--text-muted); border: 1px solid transparent;
        }
        .sidebar-item:hover { background: rgba(255, 255, 255, 0.04); color: #fff; }
        .sidebar-item.active {
            background: linear-gradient(to left, rgba(139, 92, 246, 0.15), transparent);
            border-color: rgba(139, 92, 246, 0.2); color: #fff;
        }
        .sidebar-item.active i { color: var(--accent-primary); filter: drop-shadow(0 0 5px var(--accent-primary)); }

        .text-input {
            width: 100%; background: rgba(2, 6, 23, 0.5);
            border: 1px solid var(--glass-border); border-radius: 14px;
            padding: 12px; color: #fff; transition: all 0.2s;
            font-size: 0.85rem; line-height: 1.6; resize: none;
        }
        .text-input:focus {
            outline: none; border-color: var(--accent-primary);
            box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.1);
            background: rgba(2, 6, 23, 0.8);
        }

        .section-header {
            cursor: pointer; padding: 18px 22px;
            display: flex; align-items: center; justify-content: space-between;
            background: rgba(255, 255, 255, 0.02); border-radius: 20px;
            border: 1px solid transparent; transition: 0.2s;
        }
        .section-container.active .section-header {
            background: rgba(255, 255, 255, 0.05); border-bottom-left-radius: 0; border-bottom-right-radius: 0;
            border-color: rgba(255, 255, 255, 0.05);
        }
        .section-content {
            padding: 20px; display: none; background: rgba(255, 255, 255, 0.01);
            border-bottom-left-radius: 20px; border-bottom-right-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.03); border-top: none;
        }
        .section-container.active .section-content { display: block; }

        @keyframes slideIn { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
        .animate-tab { animation: slideIn 0.4s ease-out forwards; }

        .dashboard-stat {
            background: linear-gradient(135deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.01) 100%);
            border: 1px solid var(--glass-border); border-radius: 20px; padding: 20px;
            flex: 1; min-width: 200px;
        }

        .category-badge {
            font-size: 9px; padding: 2px 8px; border-radius: 6px; font-weight: 800; text-transform: uppercase;
        }
    </style>
</head>
<body class="flex flex-col">

    <!-- Top Navigation Bar -->
    <header class="h-[75px] border-b border-white/5 bg-[#020617]/90 backdrop-blur-2xl flex items-center justify-between px-10 z-[100]">
        <div class="flex items-center gap-5">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-violet-600/30 to-fuchsia-600/30 flex items-center justify-center border border-violet-500/20 shadow-2xl shadow-violet-500/10">
                <i class="fa-solid fa-wand-magic-sparkles text-violet-400 text-xl"></i>
            </div>
            <div>
                <h1 class="text-xl font-black text-white tracking-tight">پنل <span class="text-violet-400">هوشمند</span> محتوا</h1>
                <div class="flex items-center gap-2 mt-0.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span class="text-[9px] text-slate-500 font-bold uppercase tracking-widest">System Online</span>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-6">
            <div class="hidden lg:flex items-center gap-3 bg-white/5 px-4 py-2 rounded-xl border border-white/5">
                <i class="fa-regular fa-clock text-slate-500 text-xs"></i>
                <span class="text-xs text-slate-300 font-bold"><?php echo $todayDate; ?></span>
            </div>
            <button onclick="App.save()" id="btn-save" class="h-11 px-8 rounded-xl bg-gradient-to-r from-violet-600 to-indigo-600 text-white text-sm font-black shadow-xl shadow-violet-600/30 disabled:opacity-20 disabled:grayscale transition-all hover:scale-105 active:scale-95" disabled>
                <i class="fa-solid fa-floppy-disk ml-2"></i> ذخیره تغییرات
            </button>
        </div>
    </header>

    <main class="flex-1 flex overflow-hidden">
        
        <!-- Smart Sidebar -->
        <aside class="w-[300px] border-l border-white/5 bg-[#020617]/40 p-8 flex flex-col overflow-y-auto custom-scrollbar">
            <div class="mb-10">
                <h3 class="text-[10px] font-black text-slate-500 uppercase tracking-[0.2em] mb-6 px-4">منوی اصلی</h3>
                <nav id="sidebar-nav">
                    <div class="sidebar-item active" onclick="App.setTab('dashboard', this)">
                        <i class="fa-solid fa-gauge-high"></i>
                        <span class="text-sm font-bold">داشبورد کلی</span>
                    </div>
                    <div class="sidebar-item" onclick="App.setTab('all', this)">
                        <i class="fa-solid fa-layer-group"></i>
                        <span class="text-sm font-bold">همه پیام‌ها</span>
                    </div>
                </nav>
            </div>

            <div class="mb-10">
                <h3 class="text-[10px] font-black text-slate-500 uppercase tracking-[0.2em] mb-6 px-4">تفکیک محتوا</h3>
                <nav>
                    <div class="sidebar-item group" onclick="App.setTab('admin', this)">
                        <i class="fa-solid fa-shield-halved text-rose-500/70 group-hover:text-rose-500"></i>
                        <span class="text-sm font-bold">بخش ادمین</span>
                    </div>
                    <div class="sidebar-item group" onclick="App.setTab('user', this)">
                        <i class="fa-solid fa-user-gear text-emerald-500/70 group-hover:text-emerald-500"></i>
                        <span class="text-sm font-bold">بخش کاربران</span>
                    </div>
                    <div class="sidebar-item group" onclick="App.setTab('service', this)">
                        <i class="fa-solid fa-credit-card text-blue-500/70 group-hover:text-blue-500"></i>
                        <span class="text-sm font-bold">مالی و فروش</span>
                    </div>
                </nav>
            </div>

            <div class="mt-auto space-y-3">
                <button onclick="App.openRaw()" class="w-full py-3.5 rounded-xl bg-white/5 border border-white/5 text-slate-400 text-xs font-black hover:bg-white/10 hover:text-white transition-all flex items-center justify-center gap-3">
                    <i class="fa-solid fa-code"></i> ویرایشگر خام
                </button>
                <div class="p-4 rounded-2xl bg-violet-600/5 border border-violet-500/10">
                    <p class="text-[10px] text-violet-300/60 leading-relaxed text-center">هرگونه تغییر در ساختار کلیدها ممکن است باعث اختلال در ربات شود.</p>
                </div>
            </div>
        </aside>

        <!-- Dynamic Content Engine -->
        <section class="flex-1 flex flex-col overflow-hidden relative">
            
            <!-- Context Header -->
            <div class="px-10 py-6 border-b border-white/5 flex flex-col md:flex-row items-center justify-between gap-6 bg-slate-900/10">
                <div class="relative w-full md:w-[450px]">
                    <i class="fa-solid fa-filter absolute right-4 top-1/2 -translate-y-1/2 text-slate-600"></i>
                    <input type="text" id="searchField" placeholder="جستجوی سریع کلید یا متن..." class="w-full bg-slate-950/40 border border-white/5 rounded-2xl py-3 pr-12 pl-4 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500/20 transition-all placeholder:text-slate-600">
                </div>
                
                <div class="flex items-center gap-8">
                    <div class="text-right">
                        <span class="block text-[9px] text-slate-500 font-black uppercase tracking-widest">Active Scope</span>
                        <span id="active-tab-label" class="text-sm text-violet-400 font-black">داشبورد مدیریتی</span>
                    </div>
                    <div class="w-px h-10 bg-white/10"></div>
                    <div class="text-right">
                        <span class="block text-[9px] text-slate-500 font-black uppercase tracking-widest">Total Indexed</span>
                        <span id="stat-keys" class="text-sm text-white font-black">0</span>
                    </div>
                </div>
            </div>

            <!-- Scrollable Viewport -->
            <div id="content-viewport" class="flex-1 overflow-y-auto p-10 custom-scrollbar bg-slate-950/10">
                <!-- Dashboard or Editor Grid will render here -->
                <div id="editor-grid" class="grid grid-cols-1 xl:grid-cols-2 gap-8 animate-tab"></div>
            </div>
        </section>

    </main>

    <!-- Raw JSON Modal -->
    <div id="rawModal" class="fixed inset-0 z-[2000] hidden flex items-center justify-center p-10 bg-black/90 backdrop-blur-xl">
        <div class="glass-card w-full max-w-6xl h-[85vh] flex flex-col overflow-hidden shadow-[0_0_100px_rgba(139,92,246,0.1)]">
            <div class="p-6 border-b border-white/5 flex justify-between items-center bg-white/5">
                <div class="flex items-center gap-3">
                    <i class="fa-solid fa-code text-emerald-400"></i>
                    <h3 class="font-black text-lg">ویرایشگر مستقیم ساختار JSON</h3>
                </div>
                <button onclick="App.closeRaw()" class="w-10 h-10 rounded-full hover:bg-white/10 transition-all"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="flex-1 bg-[#010409] p-6">
                <textarea id="rawTextarea" class="w-full h-full bg-transparent border-none font-mono text-sm text-blue-300 outline-none resize-none custom-scrollbar" dir="ltr" spellcheck="false"></textarea>
            </div>
            <div class="p-6 border-t border-white/5 bg-white/5 flex justify-end gap-4">
                <button onclick="App.closeRaw()" class="px-6 py-2.5 text-slate-500 text-sm font-bold hover:text-white transition">انصراف</button>
                <button onclick="App.applyRaw()" class="px-10 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-black rounded-xl shadow-lg shadow-emerald-600/20 transition-all">بروزرسانی هسته</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const App = {
            data: {},
            original: '',
            activeTab: 'dashboard',
            
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
                } catch (e) { console.error("Database connection failed"); }
            },

            updateStats() {
                let count = 0;
                const counter = (o) => Object.values(o).forEach(v => typeof v === 'object' ? counter(v) : count++);
                counter(this.data);
                document.getElementById('stat-keys').innerText = count.toLocaleString() + " کلید";
            },

            getCategory(section) {
                const s = section.toLowerCase();
                if (s.includes('admin') || s.includes('panel') || s.includes('broadcast') || s.includes('stats') || s.includes('backup')) return 'admin';
                if (s.includes('sell') || s.includes('buy') || s.includes('money') || s.includes('wallet') || s.includes('tariff') || s.includes('pay') || s.includes('invoice')) return 'service';
                if (s.includes('user') || s.includes('profile') || s.includes('help') || s.includes('start') || s.includes('welcome') || s.includes('support')) return 'user';
                return 'other';
            },

            setTab(tab, el) {
                this.activeTab = tab;
                document.querySelectorAll('.sidebar-item').forEach(i => i.classList.remove('active'));
                el.classList.add('active');
                document.getElementById('active-tab-label').innerText = el.innerText.trim();
                
                const grid = document.getElementById('editor-grid');
                grid.classList.remove('animate-tab');
                void grid.offsetWidth;
                grid.classList.add('animate-tab');
                
                this.render();
            },

            renderDashboard() {
                const container = document.getElementById('editor-grid');
                container.className = "grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 animate-tab";
                
                let stats = { admin: 0, user: 0, service: 0, other: 0 };
                Object.keys(this.data).forEach(s => stats[this.getCategory(s)]++);

                container.innerHTML = `
                    <div class="col-span-full mb-4">
                        <div class="glass-card p-10 bg-gradient-to-br from-violet-600/10 to-transparent border-violet-500/20">
                            <h2 class="text-3xl font-black text-white mb-2">خوش آمدید، مدیریت</h2>
                            <p class="text-slate-400">در این بخش می‌توانید تمامی متون، پیام‌های خوش‌آمدگویی و پاسخ‌های خودکار ربات را مدیریت کنید.</p>
                        </div>
                    </div>
                    
                    <div class="dashboard-stat">
                        <div class="flex items-center justify-between mb-4">
                            <i class="fa-solid fa-shield-halved text-rose-500 text-2xl"></i>
                            <span class="category-badge bg-rose-500/20 text-rose-400">سیستمی</span>
                        </div>
                        <div class="text-2xl font-black text-white">${stats.admin} سطر</div>
                        <div class="text-xs text-slate-500 mt-1">بخش‌های مدیریتی و پنل</div>
                    </div>

                    <div class="dashboard-stat">
                        <div class="flex items-center justify-between mb-4">
                            <i class="fa-solid fa-user-gear text-emerald-500 text-2xl"></i>
                            <span class="category-badge bg-emerald-500/20 text-emerald-400">تعاملی</span>
                        </div>
                        <div class="text-2xl font-black text-white">${stats.user} سطر</div>
                        <div class="text-xs text-slate-500 mt-1">پیام‌های عمومی و پروفایل</div>
                    </div>

                    <div class="dashboard-stat">
                        <div class="flex items-center justify-between mb-4">
                            <i class="fa-solid fa-credit-card text-blue-500 text-2xl"></i>
                            <span class="category-badge bg-blue-500/20 text-blue-400">مالی</span>
                        </div>
                        <div class="text-2xl font-black text-white">${stats.service} سطر</div>
                        <div class="text-xs text-slate-500 mt-1">تراکنش‌ها و پلن‌های فروش</div>
                    </div>

                    <div class="col-span-full glass-card p-8 mt-4 flex items-center justify-between bg-white/2">
                        <div class="flex items-center gap-6">
                            <div class="w-16 h-16 rounded-2xl bg-white/5 flex items-center justify-center text-3xl text-slate-600">
                                <i class="fa-solid fa-circle-info"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-white">راهنمای سریع</h4>
                                <p class="text-xs text-slate-500 mt-1">از منوی سمت راست برای دسترسی به بخش‌های خاص استفاده کنید.</p>
                            </div>
                        </div>
                        <button onclick="App.setTab('all', document.querySelectorAll('.sidebar-item')[1])" class="px-6 py-2 rounded-xl bg-white/5 border border-white/5 text-xs font-bold hover:bg-white/10 transition">مشاهده همه پیام‌ها</button>
                    </div>
                `;
            },

            render() {
                if (this.activeTab === 'dashboard') {
                    this.renderDashboard();
                    return;
                }

                const container = document.getElementById('editor-grid');
                container.className = "grid grid-cols-1 xl:grid-cols-2 gap-8 animate-tab";
                container.innerHTML = '';
                
                let foundAny = false;
                Object.entries(this.data).forEach(([section, contents]) => {
                    const category = this.getCategory(section);
                    if (this.activeTab !== 'all' && this.activeTab !== category) return;
                    foundAny = true;

                    const sectionDiv = document.createElement('div');
                    sectionDiv.className = 'section-container glass-card overflow-hidden';
                    
                    const header = document.createElement('div');
                    header.className = 'section-header';
                    const color = category === 'admin' ? 'rose' : (category === 'service' ? 'blue' : 'violet');
                    const icon = category === 'admin' ? 'fa-shield-halved' : (category === 'service' ? 'fa-credit-card' : 'fa-message');
                    
                    header.innerHTML = `
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-xl bg-${color}-500/10 flex items-center justify-center text-${color}-400 border border-${color}-500/10">
                                <i class="fa-solid ${icon}"></i>
                            </div>
                            <div>
                                <span class="block font-black text-slate-200 text-sm tracking-tight">${section}</span>
                                <span class="category-badge bg-${color}-500/10 text-${color}-400/80">${category}</span>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-down text-slate-700 text-[10px] transition-transform"></i>
                    `;
                    header.onclick = () => {
                        sectionDiv.classList.toggle('active');
                        header.querySelector('.fa-chevron-down').classList.toggle('rotate-180');
                    };

                    const contentDiv = document.createElement('div');
                    contentDiv.className = 'section-content space-y-6';
                    this.buildFields(contents, contentDiv, section);

                    sectionDiv.appendChild(header);
                    sectionDiv.appendChild(contentDiv);
                    container.appendChild(sectionDiv);
                });

                if(!foundAny) {
                    container.innerHTML = `<div class="col-span-full py-20 text-center opacity-30 flex flex-col items-center"><i class="fa-solid fa-folder-open text-5xl mb-4"></i><span class="font-bold">در این دسته‌بندی پیامی یافت نشد</span></div>`;
                }
                
                this.autoResizeAll();
            },

            buildFields(obj, parent, path) {
                Object.entries(obj).forEach(([key, val]) => {
                    const fullPath = `${path}.${key}`;
                    if (typeof val === 'object' && val !== null) {
                        const sub = document.createElement('div');
                        sub.className = 'mr-4 border-r border-white/5 pr-4 mt-2 mb-4';
                        sub.innerHTML = `<div class="text-[9px] font-black uppercase text-slate-600 mb-3 flex items-center gap-2"><i class="fa-solid fa-caret-down"></i> ${key}</div>`;
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
                    if(this.activeTab === 'dashboard' && query.length > 0) this.setTab('all', document.querySelectorAll('.sidebar-item')[1]);
                    
                    document.querySelectorAll('.field-item').forEach(f => {
                        f.style.display = f.dataset.search.includes(query) ? 'block' : 'none';
                    });
                    document.querySelectorAll('.section-container').forEach(s => {
                        const hasVisible = Array.from(s.querySelectorAll('.field-item')).some(f => f.style.display !== 'none');
                        s.style.display = hasVisible ? 'block' : 'none';
                        if (query.length > 2 && hasVisible) s.classList.add('active');
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
                        Swal.fire({ icon: 'success', title: 'تغییرات با موفقیت در هسته ثبت شد', toast: true, position: 'top-end', timer: 3000, showConfirmButton: false, background: '#020617', color: '#fff' });
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
                } catch (e) { Swal.fire({ icon: 'error', title: 'ساختار JSON دارای خطا است' }); }
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