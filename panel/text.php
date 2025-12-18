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
    <title>مدیریت محتوای Cosmic Glass</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    
    <style>
        :root {
            --bg-deep: #020617;
            --accent-primary: #8b5cf6;
            --accent-glow: rgba(139, 92, 246, 0.4);
            --glass-panel: rgba(15, 23, 42, 0.65);
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

        /* --- GLASS CARDS --- */
        .glass-card {
            background: var(--glass-panel);
            backdrop-filter: blur(15px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* --- FLOATING HOVER EFFECT --- */
        .floating-card:hover {
            transform: translateY(-10px) scale(1.02);
            border-color: rgba(139, 92, 246, 0.4);
            box-shadow: 0 30px 60px -15px rgba(0, 0, 0, 0.7), 0 0 30px rgba(139, 92, 246, 0.2);
            background: rgba(30, 41, 59, 0.8);
            z-index: 20;
        }

        .sidebar-item {
            cursor: pointer; padding: 14px 20px; border-radius: 16px;
            transition: all 0.3s; display: flex; align-items: center; gap: 12px;
            margin-bottom: 6px; color: var(--text-muted);
        }
        .sidebar-item:hover { background: rgba(255, 255, 255, 0.05); color: #fff; }
        .sidebar-item.active {
            background: linear-gradient(to left, rgba(139, 92, 246, 0.2), transparent);
            border: 1px solid rgba(139, 92, 246, 0.3); color: #fff;
        }
        .sidebar-item.active i { color: var(--accent-primary); filter: drop-shadow(0 0 8px var(--accent-primary)); }

        .text-input {
            width: 100%; background: rgba(2, 6, 23, 0.5);
            border: 1px solid var(--glass-border); border-radius: 14px;
            padding: 14px; color: #fff; transition: all 0.2s;
            font-size: 0.9rem; line-height: 1.6; resize: none;
        }
        .text-input:focus {
            outline: none; border-color: var(--accent-primary);
            box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.15);
            background: rgba(2, 6, 23, 0.8);
        }

        .section-header {
            cursor: pointer; padding: 18px 22px;
            display: flex; align-items: center; justify-content: space-between;
            background: rgba(255, 255, 255, 0.03); border-radius: 20px;
            transition: background 0.3s;
        }
        .section-header:hover { background: rgba(255, 255, 255, 0.07); }
        .section-container.active .section-header {
            background: rgba(255, 255, 255, 0.08); border-bottom-left-radius: 0; border-bottom-right-radius: 0;
        }
        .section-content {
            padding: 24px; display: none; background: rgba(255, 255, 255, 0.01);
            border-bottom-left-radius: 20px; border-bottom-right-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.03); border-top: none;
        }
        .section-container.active .section-content { display: block; }

        .custom-scrollbar::-webkit-scrollbar { width: 5px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }

        .category-wrapper {
            margin-bottom: 60px;
            animation: fadeIn 0.6s ease-out forwards;
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        .category-title {
            position: relative;
            padding-right: 24px;
            margin-bottom: 30px;
            font-weight: 900;
            font-size: 1.25rem;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 15px;
            letter-spacing: -0.02em;
        }
        .category-title::before {
            content: '';
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 5px;
            height: 28px;
            background: var(--accent-primary);
            border-radius: 3px;
            box-shadow: 0 0 15px var(--accent-primary);
        }

        .btn-modern {
            padding: 10px 24px;
            border-radius: 14px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
        }
        .btn-save {
            background: linear-gradient(135deg, var(--accent-primary), #6366f1);
            color: white;
            box-shadow: 0 10px 25px -5px rgba(139, 92, 246, 0.4);
        }
        .btn-save:hover:not(:disabled) { transform: scale(1.05); filter: brightness(1.1); box-shadow: 0 15px 30px -5px rgba(139, 92, 246, 0.6); }
        .btn-save:disabled { opacity: 0.3; cursor: not-allowed; }
    </style>
</head>
<body>

    <div class="cosmic-bg"></div>
    <div class="star-field"></div>

    <!-- Main Header -->
    <header class="h-[80px] border-b border-white/5 bg-[#020617]/85 backdrop-blur-2xl flex items-center justify-between px-10 z-[100] sticky top-0">
        <div class="flex items-center gap-5">
            <div class="w-12 h-12 rounded-2xl bg-violet-600/20 flex items-center justify-center border border-violet-500/25 shadow-[0_0_20px_rgba(139,92,246,0.2)]">
                <i class="fa-solid fa-feather-pointed text-violet-400 text-xl"></i>
            </div>
            <div>
                <h1 class="text-2xl font-black text-white leading-none">مدیریت <span class="text-violet-400 font-black">محتوا</span></h1>
                <p class="text-[10px] text-slate-500 font-bold uppercase tracking-[0.2em] mt-2">Cosmic System Engine</p>
            </div>
        </div>

        <div class="flex items-center gap-6">
            <div class="hidden lg:flex items-center gap-3 bg-white/5 px-4 py-2 rounded-xl border border-white/5">
                <i class="fa-regular fa-calendar-check text-violet-400 text-xs"></i>
                <span class="text-xs text-slate-300 font-bold"><?php echo $todayDate; ?></span>
            </div>
            <button onclick="App.save()" id="btn-save" class="btn-modern btn-save" disabled>
                <i class="fa-solid fa-cloud-arrow-up text-lg"></i>
                <span>ذخیره نهایی</span>
            </button>
        </div>
    </header>

    <main class="flex-1 flex overflow-hidden">
        
        <!-- Smart Sidebar Navigation -->
        <aside class="w-[320px] border-l border-white/5 p-8 flex flex-col overflow-y-auto custom-scrollbar bg-[#020617]/30">
            <div class="mb-12">
                <h3 class="text-[10px] font-black text-slate-500 uppercase tracking-[0.25em] mb-6 px-4">دسته‌بندی‌های اصلی</h3>
                <nav id="sidebar-nav">
                    <div class="sidebar-item active" onclick="App.setTab('all', this)">
                        <i class="fa-solid fa-layer-group"></i>
                        <span class="text-sm font-black">تمامی متن‌ها</span>
                    </div>
                    <div class="sidebar-item" onclick="App.setTab('admin', this)">
                        <i class="fa-solid fa-shield-halved text-rose-400"></i>
                        <span class="text-sm font-black">مدیریت (Admin)</span>
                    </div>
                    <div class="sidebar-item" onclick="App.setTab('user', this)">
                        <i class="fa-solid fa-user-astronaut text-emerald-400"></i>
                        <span class="text-sm font-black">کاربری (User)</span>
                    </div>
                    <div class="sidebar-item" onclick="App.setTab('service', this)">
                        <i class="fa-solid fa-credit-card text-blue-400"></i>
                        <span class="text-sm font-black">سرویس و مالی</span>
                    </div>
                </nav>
            </div>

            <div class="mt-auto space-y-3">
                <div class="p-5 rounded-2xl bg-violet-600/5 border border-violet-500/10 mb-4">
                    <p class="text-[10px] text-violet-300/60 leading-relaxed text-center font-bold">تغییرات شما تا زمان کلیک بر روی دکمه ذخیره، نهایی نخواهند شد.</p>
                </div>
                <button onclick="App.openRaw()" class="w-full py-4 rounded-xl bg-white/5 border border-white/5 text-slate-400 text-xs font-black hover:bg-white/10 hover:text-white transition-all flex items-center justify-center gap-3">
                    <i class="fa-solid fa-terminal"></i> ویرایش کد خام
                </button>
                <button onclick="App.export()" class="w-full py-4 rounded-xl bg-white/5 border border-white/5 text-slate-400 text-xs font-black hover:bg-white/10 hover:text-white transition-all flex items-center justify-center gap-3">
                    <i class="fa-solid fa-file-export"></i> خروجی بکاپ
                </button>
            </div>
        </aside>

        <!-- Editor Viewport -->
        <section class="flex-1 flex flex-col overflow-hidden">
            
            <div class="px-12 py-8 border-b border-white/5 flex items-center justify-between gap-8 bg-slate-900/10">
                <div class="relative w-[500px]">
                    <i class="fa-solid fa-magnifying-glass absolute right-5 top-1/2 -translate-y-1/2 text-slate-600"></i>
                    <input type="text" id="searchField" placeholder="جستجوی سریع کلیدها یا محتوای متنی..." class="w-full bg-slate-950/40 border border-white/5 rounded-2xl py-3.5 pr-14 pl-5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500/20 transition-all placeholder:text-slate-700">
                </div>
                
                <div class="flex items-center gap-8">
                    <div class="text-right">
                        <span class="block text-[9px] text-slate-500 font-black uppercase tracking-widest">Scope فعال</span>
                        <span id="active-tab-label" class="text-sm text-violet-400 font-black">تمامی متن‌ها</span>
                    </div>
                    <div class="w-px h-10 bg-white/10"></div>
                    <div class="flex items-center gap-3">
                         <div class="w-10 h-10 rounded-xl bg-white/5 flex items-center justify-center text-slate-400" id="stat-icon">
                            <i class="fa-solid fa-tags"></i>
                         </div>
                         <div id="stat-keys" class="text-sm text-white font-black">0 کلید مدیریت می‌شود</div>
                    </div>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto p-12 custom-scrollbar" id="main-viewport">
                <div id="editor-container" class="max-w-[1400px] mx-auto">
                    <!-- Categories will dynamically render here -->
                </div>
            </div>
        </section>

    </main>

    <!-- Raw Data Modal -->
    <div id="rawModal" class="fixed inset-0 z-[2000] hidden flex items-center justify-center p-10 bg-black/90 backdrop-blur-2xl">
        <div class="glass-card w-full max-w-6xl h-[85vh] flex flex-col overflow-hidden shadow-[0_0_100px_rgba(139,92,246,0.1)]">
            <div class="p-6 border-b border-white/5 flex justify-between items-center bg-white/5">
                <div class="flex items-center gap-4">
                    <i class="fa-solid fa-code text-emerald-400 text-xl"></i>
                    <h3 class="font-black text-lg">ویرایش مستقیم ساختار JSON</h3>
                </div>
                <button onclick="App.closeRaw()" class="w-12 h-12 rounded-full hover:bg-white/10 transition-all text-slate-400 hover:text-white"><i class="fa-solid fa-xmark text-xl"></i></button>
            </div>
            <div class="flex-1 bg-[#010409] p-6">
                <textarea id="rawTextarea" class="w-full h-full bg-transparent border-none font-mono text-sm text-blue-300 outline-none resize-none custom-scrollbar" dir="ltr" spellcheck="false"></textarea>
            </div>
            <div class="p-6 border-t border-white/5 bg-white/5 flex justify-end gap-5">
                <button onclick="App.closeRaw()" class="px-8 py-3 text-slate-500 text-sm font-black hover:text-white transition">انصراف</button>
                <button onclick="App.applyRaw()" class="px-12 py-3 bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-black rounded-xl shadow-lg shadow-emerald-600/20 transition-all">بروزرسانی هسته</button>
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
                } catch (e) { console.error("Database connection error"); }
            },

            updateStats() {
                let count = 0;
                const counter = (o) => Object.values(o).forEach(v => typeof v === 'object' ? counter(v) : count++);
                counter(this.data);
                document.getElementById('stat-keys').innerText = count.toLocaleString() + " کلید مدیریت می‌شود";
            },

            getCategory(section) {
                const s = section.toLowerCase();
                if (s.includes('admin') || s.includes('panel') || s.includes('stats') || s.includes('broadcast')) return 'admin';
                if (s.includes('sell') || s.includes('buy') || s.includes('money') || s.includes('wallet') || s.includes('pay') || s.includes('invoice') || s.includes('tariff')) return 'service';
                if (s.includes('user') || s.includes('profile') || s.includes('welcome') || s.includes('help') || s.includes('start') || s.includes('support')) return 'user';
                return 'other';
            },

            setTab(tab, el) {
                this.activeTab = tab;
                document.querySelectorAll('.sidebar-item').forEach(i => i.classList.remove('active'));
                el.classList.add('active');
                document.getElementById('active-tab-label').innerText = el.innerText.trim();
                
                // Visual Switch
                const container = document.getElementById('editor-container');
                container.style.opacity = '0';
                setTimeout(() => {
                    this.render();
                    container.style.opacity = '1';
                }, 150);
                
                document.getElementById('main-viewport').scrollTop = 0;
            },

            render() {
                const container = document.getElementById('editor-container');
                container.innerHTML = '';
                
                const categories = {
                    admin: { title: 'مدیریت و امنیت (Admin)', icon: 'fa-shield-halved', color: 'rose', data: {} },
                    user: { title: 'ارتباطات کاربری (User)', icon: 'fa-user-astronaut', color: 'emerald', data: {} },
                    service: { title: 'سرویس‌ها و تراکنش‌های مالی', icon: 'fa-credit-card', color: 'blue', data: {} },
                    other: { title: 'پیکربندی‌های متفرقه', icon: 'fa-ellipsis-h', color: 'violet', data: {} }
                };

                // Filter and group
                Object.entries(this.data).forEach(([section, contents]) => {
                    const category = this.getCategory(section);
                    if (this.activeTab === 'all' || this.activeTab === category) {
                        categories[category].data[section] = contents;
                    }
                });

                // Render loops
                Object.keys(categories).forEach(key => {
                    const cat = categories[key];
                    if (Object.keys(cat.data).length > 0) {
                        const catWrapper = document.createElement('div');
                        catWrapper.className = 'category-wrapper';
                        
                        // Header
                        const title = document.createElement('div');
                        title.className = 'category-title';
                        title.innerHTML = `<i class="fa-solid ${cat.icon} text-${cat.color}-400"></i> ${cat.title}`;
                        catWrapper.appendChild(title);

                        // Grid for sections
                        const grid = document.createElement('div');
                        grid.className = 'grid grid-cols-1 xl:grid-cols-2 gap-8';
                        
                        Object.entries(cat.data).forEach(([section, contents]) => {
                            const sectionDiv = document.createElement('div');
                            sectionDiv.className = 'section-container glass-card floating-card overflow-hidden';
                            
                            const header = document.createElement('div');
                            header.className = 'section-header';
                            
                            header.innerHTML = `
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 rounded-2xl bg-${cat.color}-500/10 flex items-center justify-center text-${cat.color}-400 border border-${cat.color}-500/10">
                                        <i class="fa-solid fa-folder-tree"></i>
                                    </div>
                                    <div>
                                        <span class="block font-black text-slate-200 text-sm tracking-tight">${section}</span>
                                        <span class="text-[9px] text-slate-500 font-bold uppercase tracking-widest">${Object.keys(contents).length} کلید فعال</span>
                                    </div>
                                </div>
                                <i class="fa-solid fa-chevron-down text-slate-700 text-[10px] transition-transform duration-300"></i>
                            `;
                            header.onclick = () => {
                                sectionDiv.classList.toggle('active');
                                header.querySelector('.fa-chevron-down').classList.toggle('rotate-180');
                            };

                            const contentDiv = document.createElement('div');
                            contentDiv.className = 'section-content space-y-8';
                            this.buildFields(contents, contentDiv, section);

                            sectionDiv.appendChild(header);
                            sectionDiv.appendChild(contentDiv);
                            grid.appendChild(sectionDiv);
                        });

                        catWrapper.appendChild(grid);
                        container.appendChild(catWrapper);
                    }
                });
                
                this.autoResizeAll();
            },

            buildFields(obj, parent, path) {
                Object.entries(obj).forEach(([key, val]) => {
                    const fullPath = `${path}.${key}`;
                    if (typeof val === 'object' && val !== null) {
                        const sub = document.createElement('div');
                        sub.className = 'mr-4 border-r-2 border-white/5 pr-6 mt-4 mb-6';
                        sub.innerHTML = `<div class="text-[9px] font-black uppercase text-violet-400/60 mb-4 tracking-widest flex items-center gap-2"><i class="fa-solid fa-caret-down"></i> ${key}</div>`;
                        this.buildFields(val, sub, fullPath);
                        parent.appendChild(sub);
                    } else {
                        const field = document.createElement('div');
                        field.className = 'field-item group';
                        field.dataset.search = (fullPath + ' ' + val).toLowerCase();
                        field.innerHTML = `
                            <div class="flex flex-col gap-2.5">
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
                    document.querySelectorAll('.section-container').forEach(s => {
                        const hasVisible = Array.from(s.querySelectorAll('.field-item')).some(f => f.style.display !== 'none');
                        s.style.display = hasVisible ? 'block' : 'none';
                        if (query.length > 2 && hasVisible) s.classList.add('active');
                    });
                    
                    document.querySelectorAll('.category-wrapper').forEach(w => {
                        const hasVisibleChild = Array.from(w.querySelectorAll('.section-container')).some(s => s.style.display !== 'none');
                        w.style.display = hasVisibleChild ? 'block' : 'none';
                    });
                };
            },

            async save() {
                const btn = document.getElementById('btn-save');
                btn.disabled = true;
                const originalContent = btn.innerHTML;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin ml-2"></i> همگام‌سازی...';

                try {
                    const res = await fetch('text.php', { method: 'POST', body: JSON.stringify(this.data) });
                    const result = await res.json();
                    if (result.status === 'success') {
                        this.original = JSON.stringify(this.data);
                        this.checkChanges();
                        Swal.fire({ icon: 'success', title: 'تمامی تغییرات با موفقیت ذخیره شدند', toast: true, position: 'top-end', timer: 3000, showConfirmButton: false, background: '#020617', color: '#fff' });
                    }
                } finally { btn.innerHTML = originalContent; }
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
                } catch (e) { Swal.fire({ icon: 'error', title: 'فرمت داده‌های JSON نامعتبر است' }); }
            },
            export() {
                const blob = new Blob([JSON.stringify(this.data, null, 4)], {type: 'application/json'});
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a'); a.href = url; a.download = 'backup_texts.json'; a.click();
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