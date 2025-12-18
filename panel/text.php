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
if (!isset($_SESSION["user"])) {
    header('Location: login.php');
    exit;
}

// Handle Save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jsonData = file_get_contents('php://input');
    $dataArray = json_decode($jsonData, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        // Backup before save (optional but good practice)
        // copy('../text.json', '../text.json.bak');
        
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
    <title>مدیریت متن‌ها | Mirza Panel</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    
    <style>
        :root {
            --bg-deep: #020617;
            --accent-primary: #8b5cf6;
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

        /* --- BACKGROUND --- */
        .cosmic-bg {
            position: fixed; inset: 0; z-index: -2;
            background: radial-gradient(circle at 50% 120%, #2e1065, #020617 60%);
        }
        .star-field {
            position: fixed; inset: 0; z-index: -1; opacity: 0.3;
            background-image: radial-gradient(1px 1px at 20px 30px, #fff, transparent), radial-gradient(2px 2px at 90px 40px, #fff, transparent);
            background-size: 300px 300px;
        }

        /* --- SIDEBAR --- */
        .sidebar-item {
            cursor: pointer; padding: 14px 20px; border-radius: 16px;
            transition: all 0.2s; display: flex; align-items: center; gap: 12px;
            margin-bottom: 6px; color: var(--text-muted); font-weight: 600; font-size: 0.9rem;
        }
        .sidebar-item:hover { background: rgba(255, 255, 255, 0.03); color: #fff; }
        .sidebar-item.active {
            background: rgba(139, 92, 246, 0.15);
            border: 1px solid rgba(139, 92, 246, 0.2);
            color: #fff;
        }
        .sidebar-count {
            margin-right: auto; background: rgba(0,0,0,0.2);
            padding: 2px 8px; border-radius: 8px; font-size: 0.75rem; opacity: 0.7;
        }

        /* --- CARDS & INPUTS --- */
        .floating-card {
            background: var(--glass-panel);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            overflow: hidden;
            display: flex; flex-direction: column;
        }
        
        .text-input {
            width: 100%; background: rgba(2, 6, 23, 0.4);
            border: 1px solid var(--glass-border); border-radius: 12px;
            padding: 12px; color: #fff; transition: all 0.2s;
            font-size: 0.9rem; line-height: 1.6; resize: none;
            min-height: 50px;
        }
        .text-input:focus {
            outline: none; border-color: var(--accent-primary);
            background: rgba(2, 6, 23, 0.8);
            box-shadow: 0 0 0 2px rgba(139, 92, 246, 0.1);
        }

        .field-group {
            background: rgba(255,255,255,0.02);
            border-radius: 16px; padding: 16px;
            border: 1px solid transparent;
            transition: 0.2s;
        }
        .field-group:hover {
            border-color: rgba(255,255,255,0.05);
            background: rgba(255,255,255,0.04);
        }

        .custom-scrollbar::-webkit-scrollbar { width: 5px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
        
        /* Animations */
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .animate-in { animation: fadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1); }

        /* Back Button Style */
        .btn-back-panel {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: #94a3b8;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            overflow: hidden;
        }
        .btn-back-panel:hover {
            transform: translateY(-4px) scale(1.02);
            box-shadow: 0 10px 25px -10px rgba(139, 92, 246, 0.4);
            border-color: rgba(139, 92, 246, 0.4);
            color: #fff;
            background: rgba(139, 92, 246, 0.15);
        }
        .btn-back-panel i { transition: transform 0.3s; }
        .btn-back-panel:hover i { transform: translateX(-3px); }
    </style>
</head>
<body>

    <div class="cosmic-bg"></div>
    <div class="star-field"></div>

    <!-- Header -->
    <header class="h-[70px] border-b border-white/5 bg-[#020617]/80 backdrop-blur-xl flex items-center justify-between px-6 z-50">
        <div class="flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl bg-violet-500/10 flex items-center justify-center border border-violet-500/20">
                <i class="fa-solid fa-language text-violet-400"></i>
            </div>
            <div>
                <h1 class="font-bold text-lg text-white">مدیریت متن‌ها</h1>
                <p class="text-[11px] text-slate-500">ویرایشگر پیشرفته JSON</p>
            </div>
        </div>

        <div class="flex-1 max-w-xl mx-8 relative group">
            <i class="fa-solid fa-magnifying-glass absolute right-4 top-1/2 -translate-y-1/2 text-slate-500 group-focus-within:text-violet-400 transition-colors"></i>
            <input type="text" id="searchInput" placeholder="جستجو در کل متن‌ها..." 
                   class="w-full bg-slate-900/50 border border-white/10 rounded-xl py-2.5 pr-12 pl-4 text-sm focus:outline-none focus:border-violet-500/50 transition-all">
        </div>

        <div class="flex items-center gap-3">
            <a href="index.php" class="btn-back-panel h-10 px-4 rounded-xl flex items-center gap-2 text-xs font-bold" title="بازگشت به داشبورد">
                <i class="fa-solid fa-house"></i>
                <span class="hidden md:inline">پنل اصلی</span>
            </a>
            <div class="w-px h-6 bg-white/10 mx-1"></div>
            <button onclick="App.openRaw()" class="h-10 px-4 rounded-xl bg-white/5 border border-white/5 text-slate-400 hover:text-white hover:bg-white/10 text-xs font-bold transition-all" title="ویرایش خام">
                <i class="fa-solid fa-code"></i>
            </button>
            <button onclick="App.export()" class="h-10 px-4 rounded-xl bg-white/5 border border-white/5 text-slate-400 hover:text-white hover:bg-white/10 text-xs font-bold transition-all" title="خروجی JSON">
                <i class="fa-solid fa-download"></i>
            </button>
            <button onclick="App.save()" id="btn-save" class="h-10 px-6 rounded-xl bg-violet-600 text-white text-sm font-bold hover:bg-violet-500 shadow-lg shadow-violet-600/20 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                <i class="fa-solid fa-save ml-2"></i> ذخیره
            </button>
        </div>
    </header>

    <main class="flex-1 flex overflow-hidden">
        <!-- Sidebar -->
        <aside class="w-[280px] flex flex-col border-l border-white/5 bg-[#020617]/30 backdrop-blur-sm">
            <div class="p-6 pb-2">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">دسته‌بندی‌ها</span>
            </div>
            <nav id="sidebarList" class="flex-1 overflow-y-auto p-4 pt-2 custom-scrollbar space-y-1">
                <!-- Categories will be injected here -->
            </nav>
            <div class="p-4 border-t border-white/5">
                <div class="bg-white/5 rounded-xl p-4 border border-white/5">
                    <div class="flex justify-between text-xs mb-2">
                        <span class="text-slate-400">کلیدهای بارگذاری شده</span>
                        <span class="text-white font-bold" id="totalKeys">0</span>
                    </div>
                    <div class="w-full h-1 bg-white/10 rounded-full overflow-hidden">
                        <div class="h-full bg-violet-500 w-full opacity-50"></div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Content -->
        <section class="flex-1 flex flex-col bg-slate-900/10 relative">
            <!-- Scrollable Area -->
            <div id="contentArea" class="flex-1 overflow-y-auto p-8 custom-scrollbar">
                <div id="fieldsContainer" class="grid grid-cols-1 xl:grid-cols-2 gap-6 max-w-7xl mx-auto">
                    <!-- Fields will be injected here -->
                </div>
            </div>
        </section>
    </main>

    <!-- Raw Editor Modal -->
    <div id="rawModal" class="fixed inset-0 z-[100] hidden bg-black/80 backdrop-blur-sm flex items-center justify-center p-6">
        <div class="bg-[#0f172a] border border-white/10 w-full max-w-5xl h-[85vh] rounded-2xl flex flex-col shadow-2xl">
            <div class="p-4 border-b border-white/5 flex justify-between items-center">
                <h3 class="font-bold text-white">ویرایشگر خام (JSON)</h3>
                <button onclick="App.closeRaw()" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark text-xl"></i></button>
            </div>
            <div class="flex-1 p-0 relative">
                <textarea id="rawTextarea" class="w-full h-full bg-[#020617] text-emerald-400 font-mono text-sm p-6 outline-none resize-none" spellcheck="false"></textarea>
            </div>
            <div class="p-4 border-t border-white/5 flex justify-end gap-3 bg-slate-900/50">
                <button onclick="App.closeRaw()" class="px-6 py-2 rounded-lg text-slate-400 hover:text-white text-sm font-bold">انصراف</button>
                <button onclick="App.applyRaw()" class="px-6 py-2 rounded-lg bg-emerald-600 text-white text-sm font-bold hover:bg-emerald-500">اعمال تغییرات</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const App = {
            data: {},
            originalStr: '',
            categories: {},
            currentCategory: 'all',
            searchQuery: '',

            init() {
                this.loadData();
                
                // Search Listener
                document.getElementById('searchInput').addEventListener('input', (e) => {
                    this.searchQuery = e.target.value.toLowerCase();
                    this.renderFields();
                });

                // Auto-save check (optional visual cue)
                setInterval(() => {
                    const currentStr = JSON.stringify(this.data);
                    const btn = document.getElementById('btn-save');
                    if (currentStr !== this.originalStr) {
                        btn.disabled = false;
                        btn.classList.add('animate-pulse');
                    } else {
                        btn.disabled = true;
                        btn.classList.remove('animate-pulse');
                    }
                }, 1000);
            },

            async loadData() {
                try {
                    const res = await fetch('text.php?action=get_json&v=' + Date.now());
                    this.data = await res.json();
                    this.originalStr = JSON.stringify(this.data);
                    
                    this.categorizeData();
                    this.renderSidebar();
                    this.renderFields();
                    
                    // Update total count
                    let count = 0;
                    const countRec = (obj) => Object.values(obj).forEach(v => typeof v === 'object' ? countRec(v) : count++);
                    countRec(this.data);
                    document.getElementById('totalKeys').innerText = count;

                } catch (e) {
                    console.error(e);
                    Swal.fire('خطا', 'عدم توانایی در دریافت اطلاعات', 'error');
                }
            },

            categorizeData() {
                // Reset categories
                this.categories = {
                    'all': { label: 'همه موارد', icon: 'fa-layer-group', keys: [] },
                    'general': { label: 'عمومی', icon: 'fa-globe', keys: [] },
                    'button': { label: 'دکمه‌ها', icon: 'fa-stop', keys: [] },
                    'message': { label: 'پیام‌ها', icon: 'fa-message', keys: [] },
                    'error': { label: 'خطاها', icon: 'fa-triangle-exclamation', keys: [] },
                    'admin': { label: 'مدیریت', icon: 'fa-shield-halved', keys: [] },
                    'finance': { label: 'مالی', icon: 'fa-credit-card', keys: [] },
                    'other': { label: 'سایر', icon: 'fa-ellipsis', keys: [] }
                };

                // Flatten keys for categorization if nested, or just iterate if flat
                // We'll support 1 level of nesting visualization, but categorize by top-level key or prefix
                
                const processKey = (key, value, path) => {
                    const fullPath = path ? `${path}.${key}` : key;
                    const item = { key: fullPath, value: value, path: fullPath };
                    
                    this.categories.all.keys.push(item);

                    // Auto-detect category based on key name
                    const k = fullPath.toLowerCase();
                    if (k.includes('btn') || k.includes('button') || k.includes('keyboard')) this.categories.button.keys.push(item);
                    else if (k.includes('err') || k.includes('fail')) this.categories.error.keys.push(item);
                    else if (k.includes('msg') || k.includes('text') || k.includes('desc')) this.categories.message.keys.push(item);
                    else if (k.includes('admin') || k.includes('panel')) this.categories.admin.keys.push(item);
                    else if (k.includes('price') || k.includes('pay') || k.includes('card')) this.categories.finance.keys.push(item);
                    else if (k.includes('title') || k.includes('name')) this.categories.general.keys.push(item);
                    else this.categories.other.keys.push(item);
                };

                const traverse = (obj, path = '') => {
                    Object.entries(obj).forEach(([k, v]) => {
                        if (typeof v === 'object' && v !== null) {
                            traverse(v, path ? `${path}.${k}` : k);
                        } else {
                            processKey(k, v, path);
                        }
                    });
                };

                traverse(this.data);
            },

            renderSidebar() {
                const nav = document.getElementById('sidebarList');
                nav.innerHTML = '';
                
                Object.entries(this.categories).forEach(([id, cat]) => {
                    if (cat.keys.length === 0 && id !== 'all') return;
                    
                    const el = document.createElement('div');
                    el.className = `sidebar-item ${this.currentCategory === id ? 'active' : ''}`;
                    el.onclick = () => {
                        this.currentCategory = id;
                        this.renderSidebar(); // Re-render to update active class
                        this.renderFields();
                    };
                    
                    el.innerHTML = `
                        <i class="fa-solid ${cat.icon} w-5 text-center ${this.currentCategory === id ? 'text-violet-400' : 'opacity-50'}"></i>
                        <span>${cat.label}</span>
                        <span class="sidebar-count">${cat.keys.length}</span>
                    `;
                    nav.appendChild(el);
                });
            },

            renderFields() {
                const container = document.getElementById('fieldsContainer');
                container.innerHTML = '';
                
                const catData = this.categories[this.currentCategory];
                if (!catData) return;

                let visibleCount = 0;

                catData.keys.forEach(item => {
                    // Search Filter
                    if (this.searchQuery && !item.key.toLowerCase().includes(this.searchQuery) && !String(item.value).toLowerCase().includes(this.searchQuery)) {
                        return;
                    }
                    visibleCount++;

                    // Create Field Card
                    const card = document.createElement('div');
                    card.className = 'field-group animate-in';
                    
                    // Label formatting (remove dots for cleaner look)
                    const labelDisplay = item.key.split('.').map(s => `<span class="opacity-50 text-[10px] mx-1">/</span>${s}`).join('').replace(/^<span.*?\/span>/, '');

                    card.innerHTML = `
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-xs font-mono text-violet-300 break-all" dir="ltr">${item.key}</label>
                            <button onclick="App.copy('${item.key}')" class="text-slate-500 hover:text-white transition-colors" title="کپی کلید">
                                <i class="fa-regular fa-copy text-xs"></i>
                            </button>
                        </div>
                        <textarea class="text-input custom-scrollbar" rows="${String(item.value).length > 60 ? 3 : 1}"
                            oninput="App.updateValue('${item.key}', this.value)">${item.value}</textarea>
                    `;
                    container.appendChild(card);
                });

                if (visibleCount === 0) {
                    container.innerHTML = `
                        <div class="col-span-full flex flex-col items-center justify-center py-20 opacity-30">
                            <i class="fa-solid fa-wind text-5xl mb-4"></i>
                            <span>موردی یافت نشد</span>
                        </div>
                    `;
                }
            },

            updateValue(path, value) {
                const keys = path.split('.');
                let current = this.data;
                for (let i = 0; i < keys.length - 1; i++) {
                    current = current[keys[i]];
                }
                current[keys[keys.length - 1]] = value;
            },

            copy(text) {
                navigator.clipboard.writeText(text);
                const toast = Swal.mixin({
                    toast: true, position: 'bottom-start',
                    showConfirmButton: false, timer: 1500,
                    background: '#1e293b', color: '#fff'
                });
                toast.fire({ icon: 'success', title: 'کپی شد' });
            },

            save() {
                const btn = document.getElementById('btn-save');
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin ml-2"></i> در حال ذخیره...';

                fetch('text.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.data)
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        this.originalStr = JSON.stringify(this.data);
                        Swal.fire({
                            icon: 'success', title: 'ذخیره شد',
                            text: 'تغییرات با موفقیت اعمال گردید',
                            background: '#020617', color: '#fff',
                            showConfirmButton: false, timer: 2000
                        });
                    } else {
                        Swal.fire('خطا', 'مشکلی پیش آمد', 'error');
                    }
                })
                .catch(() => Swal.fire('خطا', 'عدم ارتباط با سرور', 'error'))
                .finally(() => {
                    btn.innerHTML = '<i class="fa-solid fa-save ml-2"></i> ذخیره';
                });
            },

            openRaw() {
                document.getElementById('rawTextarea').value = JSON.stringify(this.data, null, 4);
                document.getElementById('rawModal').classList.remove('hidden');
            },
            closeRaw() {
                document.getElementById('rawModal').classList.add('hidden');
            },
            applyRaw() {
                try {
                    const raw = document.getElementById('rawTextarea').value;
                    const parsed = JSON.parse(raw);
                    this.data = parsed;
                    this.categorizeData();
                    this.renderSidebar();
                    this.renderFields();
                    this.closeRaw();
                    Swal.fire({ icon: 'success', title: 'بروزرسانی شد', toast: true, position: 'bottom-end', timer: 2000, showConfirmButton: false });
                } catch (e) {
                    Swal.fire('خطا', 'فرمت JSON نامعتبر است', 'error');
                }
            },
            export() {
                const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(this.data, null, 2));
                const node = document.createElement('a');
                node.setAttribute("href", dataStr);
                node.setAttribute("download", "text_backup.json");
                document.body.appendChild(node);
                node.click();
                node.remove();
            }
        };

        App.init();
    </script>
</body>
</html>
