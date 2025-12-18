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
    <title>پنل پیشرفته مدیریت محتوا</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    
    <style>
        :root {
            --bg-deep: #020617;
            --accent-primary: #8b5cf6;
            --accent-admin: #f43f5e;
            --accent-user: #10b981;
            --glass-panel: rgba(15, 23, 42, 0.75);
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
                radial-gradient(circle at 0% 0%, rgba(139, 92, 246, 0.05) 0%, transparent 50%),
                radial-gradient(circle at 100% 100%, rgba(244, 63, 94, 0.05) 0%, transparent 50%);
        }

        /* Custom Scrollbar */
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: var(--accent-primary); }

        .glass-card {
            background: var(--glass-panel);
            backdrop-filter: blur(24px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
        }

        .sidebar-item {
            cursor: pointer;
            padding: 14px 18px;
            border-radius: 16px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 6px;
            color: var(--text-muted);
            border: 1px solid transparent;
        }
        .sidebar-item:hover { background: rgba(255, 255, 255, 0.05); color: #fff; }
        .sidebar-item.active {
            background: rgba(139, 92, 246, 0.1);
            border-color: rgba(139, 92, 246, 0.2);
            color: var(--accent-primary);
        }
        .sidebar-item.active i { color: var(--accent-primary); }

        .text-input {
            width: 100%;
            background: rgba(2, 6, 23, 0.4);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 12px;
            color: #fff;
            transition: all 0.2s;
            font-size: 0.85rem;
            line-height: 1.6;
            resize: none;
        }
        .text-input:focus {
            outline: none;
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }

        .section-header {
            cursor: pointer;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 18px;
            border: 1px solid transparent;
            transition: 0.2s;
        }
        .section-container.active .section-header {
            background: rgba(255, 255, 255, 0.04);
            border-bottom-left-radius: 0;
            border-bottom-right-radius: 0;
        }
        .section-content {
            padding: 18px;
            display: none;
            background: rgba(255, 255, 255, 0.01);
            border-bottom-left-radius: 18px;
            border-bottom-right-radius: 18px;
            border: 1px solid rgba(255, 255, 255, 0.03);
            border-top: none;
        }
        .section-container.active .section-content { display: block; }

        @keyframes fadeIn { from { opacity: 0; transform: translateX(10px); } to { opacity: 1; transform: translateX(0); } }
        .animate-tab { animation: fadeIn 0.4s ease forwards; }

        .grid-layout {
            display: grid;
            grid-template-columns: repeat(1, 1fr);
            gap: 16px;
            align-items: start;
        }
        @media (min-width: 1280px) { .grid-layout { grid-template-columns: repeat(2, 1fr); } }
    </style>
</head>
<body class="flex flex-col">

    <!-- Top Header -->
    <header class="h-[70px] border-b border-white/5 bg-[#020617]/80 backdrop-blur-xl flex items-center justify-between px-8 z-50">
        <div class="flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl bg-violet-600/20 flex items-center justify-center border border-violet-500/20">
                <i class="fa-solid fa-feather-pointed text-violet-400"></i>
            </div>
            <div>
                <h1 class="text-xl font-black text-white">مدیریت <span class="text-violet-400">هوشمند</span></h1>
                <p class="text-[10px] text-slate-500 font-bold uppercase tracking-widest leading-none mt-1">Robot Content Engine</p>
            </div>
        </div>

        <div class="flex items-center gap-5">
            <div class="hidden md:flex items-center gap-3 glass-card px-4 py-1.5 border-white/5">
                <i class="fa-regular fa-calendar text-slate-500 text-xs"></i>
                <span class="text-xs text-slate-300 font-medium"><?php echo $todayDate; ?></span>
            </div>
            <button onclick="App.save()" id="btn-save" class="h-10 px-6 rounded-xl bg-gradient-to-r from-violet-600 to-indigo-600 text-white text-sm font-bold shadow-lg shadow-violet-600/20 disabled:opacity-30 disabled:grayscale transition-all" disabled>
                <i class="fa-solid fa-cloud-arrow-up ml-2"></i> ذخیره تغییرات
            </button>
        </div>
    </header>

    <main class="flex-1 flex overflow-hidden">
        
        <!-- Sidebar Navigation -->
        <aside class="w-[280px] border-l border-white/5 p-6 flex flex-col gap-8 overflow-y-auto custom-scrollbar">
            <div>
                <h3 class="text-[10px] font-black text-slate-500 uppercase tracking-[0.2em] mb-4 px-2">دسته‌بندی‌ها</h3>
                <nav id="sidebar-nav">
                    <div class="sidebar-item active" onclick="App.setTab('all', this)">
                        <i class="fa-solid fa-border-all"></i>
                        <span class="text-sm font-bold">همه پیام‌ها</span>
                    </div>
                    <div class="sidebar-item" onclick="App.setTab('admin', this)">
                        <i class="fa-solid fa-shield-halved text-rose-400"></i>
                        <span class="text-sm font-bold">بخش مدیریت</span>
                    </div>
                    <div class="sidebar-item" onclick="App.setTab('user', this)">
                        <i class="fa-solid fa-user-astronaut text-emerald-400"></i>
                        <span class="text-sm font-bold">پیام‌های کاربران</span>
                    </div>
                    <div class="sidebar-item" onclick="App.setTab('service', this)">
                        <i class="fa-solid fa-server text-blue-400"></i>
                        <span class="text-sm font-bold">سرویس و مالی</span>
                    </div>
                    <div class="sidebar-item" onclick="App.setTab('other', this)">
                        <i class="fa-solid fa-ellipsis"></i>
                        <span class="text-sm font-bold">سایر موارد</span>
                    </div>
                </nav>
            </div>

            <div class="mt-auto pt-6 border-t border-white/5 space-y-3">
                <button onclick="App.openRaw()" class="w-full py-3 rounded-xl bg-white/5 border border-white/5 text-slate-400 text-xs font-bold hover:bg-white/10 transition flex items-center justify-center gap-2">
                    <i class="fa-solid fa-code"></i> ویرایشگر خام
                </button>
                <button onclick="App.export()" class="w-full py-3 rounded-xl bg-white/5 border border-white/5 text-slate-400 text-xs font-bold hover:bg-white/10 transition flex items-center justify-center gap-2">
                    <i class="fa-solid fa-download"></i> خروجی بکاپ
                </button>
            </div>
        </aside>

        <!-- Content Area -->
        <section class="flex-1 flex flex-col overflow-hidden bg-slate-950/20">
            
            <!-- Context Toolbar -->
            <div class="p-6 border-b border-white/5 flex flex-col md:flex-row items-center justify-between gap-4">
                <div class="relative w-full md:w-[400px]">
                    <i class="fa-solid fa-magnifying-glass absolute right-4 top-1/2 -translate-y-1/2 text-slate-500"></i>
                    <input type="text" id="searchField" placeholder="جستجوی سریع..." class="w-full bg-slate-900/50 border border-white/5 rounded-xl py-2.5 pr-11 pl-4 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500/30 transition-all">
                </div>
                
                <div class="flex items-center gap-6">
                    <div class="flex flex-col items-end">
                        <span class="text-[9px] text-slate-500 font-bold uppercase">بخش فعال</span>
                        <span id="active-tab-label" class="text-xs text-violet-400 font-black">همه پیام‌ها</span>
                    </div>
                    <div class="w-px h-8 bg-white/10"></div>
                    <div class="flex flex-col items-end">
                        <span class="text-[9px] text-slate-500 font-bold uppercase">تعداد کلیدها</span>
                        <span id="stat-keys" class="text-xs text-white font-black">0</span>
                    </div>
                </div>
            </div>

            <!-- Editor Grid Container -->
            <div class="flex-1 overflow-y-auto p-8 custom-scrollbar">
                <div id="editor-grid" class="grid-layout animate-tab">
                    <!-- Dynamic Content -->
                </div>
            </div>
        </section>

    </main>

    <!-- Modal for Raw JSON -->
    <div id="rawModal" class="fixed inset-0 z-[2000] hidden flex items-center justify-center p-8 bg-black/90 backdrop-blur-md">
        <div class="glass-card w-full max-w-5xl h-[80vh] flex flex-col overflow-hidden">
            <div class="p-5 border-b border-white/5 flex justify-between items-center bg-white/5">
                <h3 class="font-bold">ویرایش مستقیم ساختار JSON</h3>
                <button onclick="App.closeRaw()" class="w-8 h-8 rounded-full hover:bg-white/10 transition"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="flex-1 bg-slate-950/50 p-4">
                <textarea id="rawTextarea" class="w-full h-full bg-transparent border-none font-mono text-sm text-blue-300 outline-none resize-none custom-scrollbar" dir="ltr" spellcheck="false"></textarea>
            </div>
            <div class="p-5 border-t border-white/5 flex justify-end gap-3">
                <button onclick="App.closeRaw()" class="px-6 py-2 text-slate-400 text-sm font-bold">انصراف</button>
                <button onclick="App.applyRaw()" class="px-8 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-bold rounded-xl transition">بروزرسانی</button>
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
                } catch (e) { console.error("Data load failed"); }
            },

            updateStats() {
                let count = 0;
                const counter = (o) => Object.values(o).forEach(v => typeof v === 'object' ? counter(v) : count++);
                counter(this.data);
                document.getElementById('stat-keys').innerText = count.toLocaleString();
            },

            getCategory(section) {
                const s = section.toLowerCase();
                if (s.includes('admin') || s.includes('panel') || s.includes('broadcast') || s.includes('stats')) return 'admin';
                if (s.includes('sell') || s.includes('buy') || s.includes('money') || s.includes('wallet') || s.includes('tariff') || s.includes('pay')) return 'service';
                if (s.includes('user') || s.includes('profile') || s.includes('help') || s.includes('start') || s.includes('welcome')) return 'user';
                return 'other';
            },

            setTab(tab, el) {
                this.activeTab = tab;
                document.querySelectorAll('.sidebar-item').forEach(i => i.classList.remove('active'));
                el.classList.add('active');
                document.getElementById('active-tab-label').innerText = el.innerText.trim();
                
                const grid = document.getElementById('editor-grid');
                grid.classList.remove('animate-tab');
                void grid.offsetWidth; // Trigger reflow
                grid.classList.add('animate-tab');
                
                this.render();
            },

            render() {
                const container = document.getElementById('editor-grid');
                container.innerHTML = '';
                
                Object.entries(this.data).forEach(([section, contents]) => {
                    const category = this.getCategory(section);
                    if (this.activeTab !== 'all' && this.activeTab !== category) return;

                    const sectionDiv = document.createElement('div');
                    sectionDiv.className = 'section-container glass-card overflow-hidden';
                    
                    const header = document.createElement('div');
                    header.className = 'section-header';
                    const icon = category === 'admin' ? 'fa-shield-halved text-rose-400' : (category === 'service' ? 'fa-credit-card text-blue-400' : 'fa-message text-violet-400');
                    
                    header.innerHTML = `
                        <div class="flex items-center gap-3">
                            <i class="fa-solid ${icon} opacity-60"></i>
                            <span class="font-bold text-slate-200 text-sm tracking-tight">${section}</span>
                        </div>
                        <i class="fa-solid fa-chevron-down text-slate-600 text-[10px] transition-transform"></i>
                    `;
                    header.onclick = () => {
                        sectionDiv.classList.toggle('active');
                        header.querySelector('.fa-chevron-down').classList.toggle('rotate-180');
                    };

                    const contentDiv = document.createElement('div');
                    contentDiv.className = 'section-content space-y-5';
                    this.buildFields(contents, contentDiv, section);

                    sectionDiv.appendChild(header);
                    sectionDiv.appendChild(contentDiv);
                    container.appendChild(sectionDiv);
                });
                
                this.autoResizeAll();
            },

            buildFields(obj, parent, path) {
                Object.entries(obj).forEach(([key, val]) => {
                    const fullPath = `${path}.${key}`;
                    if (typeof val === 'object' && val !== null) {
                        const sub = document.createElement('div');
                        sub.className = 'mr-4 border-r border-white/5 pr-4 mt-2';
                        sub.innerHTML = `<div class="text-[9px] font-black uppercase text-slate-500 mb-2">${key}</div>`;
                        this.buildFields(val, sub, fullPath);
                        parent.appendChild(sub);
                    } else {
                        const field = document.createElement('div');
                        field.className = 'field-item group';
                        field.dataset.search = (fullPath + ' ' + val).toLowerCase();
                        field.innerHTML = `
                            <div class="flex flex-col gap-1.5">
                                <div class="flex items-center justify-between">
                                    <label class="text-[10px] font-mono text-slate-500 group-hover:text-violet-400" dir="ltr">${key}</label>
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
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin ml-2"></i> در حال ذخیره...';

                try {
                    const res = await fetch('text.php', { method: 'POST', body: JSON.stringify(this.data) });
                    const result = await res.json();
                    if (result.status === 'success') {
                        this.original = JSON.stringify(this.data);
                        this.checkChanges();
                        Swal.fire({ icon: 'success', title: 'تغییرات ثبت شد', toast: true, position: 'top-end', timer: 2000, showConfirmButton: false, background: '#020617', color: '#fff' });
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
                const a = document.createElement('a'); a.href = url; a.download = 'backup_text.json'; a.click();
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