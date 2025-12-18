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
    <title>مدیریت هوشمند محتوا</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    
    <style>
        :root {
            --bg-deep: #020617;
            --accent-primary: #8b5cf6;
            --accent-secondary: #06b6d4;
            --glass-panel: rgba(15, 23, 42, 0.75);
            --glass-border: rgba(255, 255, 255, 0.08);
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
        }

        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: var(--bg-deep);
            color: var(--text-main);
            min-height: 100vh;
            background-image: 
                radial-gradient(circle at 0% 0%, rgba(139, 92, 246, 0.08) 0%, transparent 50%),
                radial-gradient(circle at 100% 100%, rgba(6, 182, 212, 0.08) 0%, transparent 50%);
            background-attachment: fixed;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--accent-primary); }

        .glass-card {
            background: var(--glass-panel);
            backdrop-filter: blur(24px);
            border: 1px solid var(--glass-border);
            border-radius: 28px;
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.5);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .text-input {
            width: 100%;
            background: rgba(2, 6, 23, 0.5);
            border: 1px solid var(--glass-border);
            border-radius: 14px;
            padding: 12px 14px;
            color: #fff;
            transition: all 0.2s;
            font-size: 0.9rem;
            line-height: 1.6;
            resize: none;
        }
        .text-input:focus {
            outline: none;
            border-color: var(--accent-primary);
            background: rgba(2, 6, 23, 0.8);
            box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.15);
        }

        /* Section Container with Grid Support */
        .section-container {
            height: fit-content;
            break-inside: avoid;
        }

        .section-header {
            cursor: pointer;
            padding: 18px 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 20px;
            border: 1px solid transparent;
            transition: all 0.2s;
        }
        .section-header:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.05);
        }

        .section-container.active .section-header {
            background: linear-gradient(to left, rgba(139, 92, 246, 0.1), transparent);
            border-color: rgba(139, 92, 246, 0.25);
            border-bottom-left-radius: 0;
            border-bottom-right-radius: 0;
        }

        .section-content {
            padding: 20px;
            display: none;
            border: 1px solid rgba(139, 92, 246, 0.1);
            border-top: none;
            border-bottom-left-radius: 20px;
            border-bottom-right-radius: 20px;
            background: rgba(139, 92, 246, 0.02);
        }
        .section-container.active .section-content { display: block; }

        .btn-modern {
            padding: 10px 24px;
            border-radius: 14px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
            cursor: pointer;
        }
        .btn-save {
            background: linear-gradient(135deg, var(--accent-primary), #6366f1);
            color: white;
            box-shadow: 0 8px 20px -6px rgba(139, 92, 246, 0.5);
        }
        .btn-save:hover:not(:disabled) { transform: translateY(-2px); filter: brightness(1.1); }
        .btn-save:disabled { opacity: 0.4; cursor: not-allowed; filter: grayscale(0.5); }

        .floating-nav {
            position: fixed; bottom: 25px; left: 50%; transform: translateX(-50%);
            background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border); padding: 10px 14px;
            border-radius: 22px; display: flex; gap: 10px; z-index: 1000;
            box-shadow: 0 15px 35px rgba(0,0,0,0.4);
        }

        .nav-item {
            width: 46px; height: 46px; display: flex; align-items: center; justify-content: center;
            border-radius: 15px; color: var(--text-muted); transition: 0.2s;
        }
        .nav-item:hover { background: rgba(255,255,255,0.06); color: #fff; }
        .nav-item.active { background: var(--accent-primary); color: #fff; }

        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .animate-slide { animation: slideUp 0.5s ease forwards; }

        /* Multi-column grid for dashboard feel */
        .editor-grid {
            display: grid;
            grid-template-columns: repeat(1, 1fr);
            gap: 20px;
            align-items: start;
        }
        @media (min-width: 1024px) { .editor-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (min-width: 1536px) { .editor-grid { grid-template-columns: repeat(3, 1fr); } }
    </style>
</head>
<body class="pb-36">

    <div class="max-w-[1600px] mx-auto px-6 pt-10">
        
        <!-- Header & Stats Overview -->
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-8 mb-10">
            <div>
                <div class="flex items-center gap-4 mb-3">
                    <div class="w-14 h-14 rounded-2xl bg-violet-600/20 flex items-center justify-center border border-violet-500/30 shadow-lg shadow-violet-500/10">
                        <i class="fa-solid fa-wand-magic-sparkles text-violet-400 text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-black text-white leading-none">مدیریت محتوای <span class="text-violet-400">هوشمند</span></h1>
                        <p class="text-slate-500 text-xs font-bold mt-2 uppercase tracking-widest">Bot Text & Language Engine</p>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-4">
                <!-- Mini Stats to fill space -->
                <div class="glass-card px-5 py-3 flex items-center gap-4">
                    <div class="text-left">
                        <div class="text-[10px] text-slate-500 font-bold uppercase">Total Keys</div>
                        <div id="stat-keys" class="text-lg font-black text-white">0</div>
                    </div>
                    <div class="w-px h-8 bg-white/10"></div>
                    <div class="text-left">
                        <div class="text-[10px] text-slate-500 font-bold uppercase">Sections</div>
                        <div id="stat-sections" class="text-lg font-black text-violet-400">0</div>
                    </div>
                </div>

                <button onclick="App.save()" id="btn-save" class="btn-modern btn-save" disabled>
                    <i class="fa-solid fa-cloud-arrow-up text-lg"></i>
                    <span>ذخیره نهایی</span>
                </button>
            </div>
        </div>

        <!-- Master Toolbar -->
        <div class="glass-card p-5 mb-10 flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="relative w-full md:w-1/2">
                <i class="fa-solid fa-filter absolute right-4 top-1/2 -translate-y-1/2 text-slate-500"></i>
                <input type="text" id="searchField" placeholder="جستجوی سریع در کلیدها، دسته‌ها یا محتوا..." class="w-full bg-slate-900/60 border border-white/5 rounded-2xl py-3 pr-12 pl-4 text-sm focus:ring-2 focus:ring-violet-500/40 outline-none transition-all placeholder:text-slate-600">
            </div>
            
            <div class="flex items-center gap-3 w-full md:w-auto">
                <button onclick="App.openRaw()" class="flex-1 md:flex-none h-12 px-5 rounded-xl bg-white/5 border border-white/5 flex items-center justify-center gap-3 text-slate-400 hover:text-white hover:bg-violet-600/20 hover:border-violet-500/30 transition group">
                    <i class="fa-solid fa-terminal text-sm group-hover:animate-pulse"></i>
                    <span class="text-sm font-bold">ویرایش کد</span>
                </button>
                <button onclick="App.export()" class="h-12 w-12 rounded-xl bg-white/5 border border-white/5 flex items-center justify-center text-slate-400 hover:text-cyan-400 hover:bg-cyan-400/10 transition" title="خروجی فایل">
                    <i class="fa-solid fa-file-export"></i>
                </button>
            </div>
        </div>

        <!-- Grid Editor -->
        <div id="editor-grid" class="editor-grid">
            <!-- Loading Skeleton -->
            <div class="col-span-full py-32 flex flex-col items-center justify-center opacity-30">
                <div class="relative w-16 h-16 mb-6">
                    <div class="absolute inset-0 border-4 border-violet-500/20 rounded-full"></div>
                    <div class="absolute inset-0 border-4 border-t-violet-500 rounded-full animate-spin"></div>
                </div>
                <p class="font-mono text-xs tracking-widest uppercase">Fetching Localization Data...</p>
            </div>
        </div>

    </div>

    <!-- Direct Code Editor Modal -->
    <div id="rawModal" class="fixed inset-0 z-[2000] hidden flex items-center justify-center p-4 md:p-10 bg-black/90 backdrop-blur-md">
        <div class="glass-card w-full max-w-5xl max-h-[85vh] flex flex-col overflow-hidden shadow-[0_0_100px_rgba(139,92,246,0.15)]">
            <div class="p-6 border-b border-white/5 flex justify-between items-center bg-white/5">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-emerald-500/20 flex items-center justify-center text-emerald-400"><i class="fa-solid fa-code"></i></div>
                    <h3 class="font-bold text-lg tracking-tight">ساختار خام JSON</h3>
                </div>
                <button onclick="App.closeRaw()" class="w-10 h-10 rounded-full flex items-center justify-center hover:bg-white/10 text-slate-400 transition"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="p-4 bg-slate-950/50 flex-1 overflow-hidden">
                <textarea id="rawTextarea" class="w-full h-full bg-transparent border-none p-4 font-mono text-sm text-blue-300 focus:outline-none resize-none custom-scrollbar" dir="ltr" spellcheck="false"></textarea>
            </div>
            <div class="p-6 border-t border-white/5 bg-white/5 flex justify-end gap-4">
                <button onclick="App.closeRaw()" class="text-sm font-bold text-slate-500 hover:text-white transition px-4">لغو</button>
                <button onclick="App.applyRaw()" class="btn-modern bg-emerald-600 hover:bg-emerald-500 text-white text-sm">بروزرسانی مخزن</button>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="floating-nav">
        <a href="index.php" class="nav-item" title="داشبورد"><i class="fa-solid fa-gauge-high"></i></a>
        <a href="users.php" class="nav-item" title="کاربران"><i class="fa-solid fa-user-group"></i></a>
        <a href="service.php" class="nav-item" title="سرویس‌ها"><i class="fa-solid fa-server"></i></a>
        <div class="w-px h-6 bg-white/10 my-auto mx-1"></div>
        <a href="text.php" class="nav-item active" title="متون"><i class="fa-solid fa-language"></i></a>
        <a href="settings.php" class="nav-item" title="تنظیمات"><i class="fa-solid fa-sliders"></i></a>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const App = {
            data: {},
            original: '',
            
            init() {
                this.load();
                this.setupSearch();
            },

            async load() {
                try {
                    const res = await fetch('text.php?action=get_json&t=' + Date.now());
                    this.data = await res.json();
                    this.original = JSON.stringify(this.data);
                    this.render();
                    this.updateStats();
                } catch (e) {
                    console.error("Critical: Data load failed", e);
                }
            },

            updateStats() {
                let count = 0;
                const countKeys = (obj) => {
                    Object.values(obj).forEach(v => {
                        if (typeof v === 'object' && v !== null) countKeys(v);
                        else count++;
                    });
                };
                countKeys(this.data);
                document.getElementById('stat-keys').innerText = count.toLocaleString();
                document.getElementById('stat-sections').innerText = Object.keys(this.data).length;
            },

            render() {
                const container = document.getElementById('editor-grid');
                container.innerHTML = '';
                
                Object.entries(this.data).forEach(([section, contents], idx) => {
                    const sectionDiv = document.createElement('div');
                    sectionDiv.className = `section-container glass-card overflow-hidden animate-slide`;
                    sectionDiv.style.animationDelay = `${idx * 0.05}s`;
                    
                    const header = document.createElement('div');
                    header.className = 'section-header';
                    header.innerHTML = `
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-xl bg-white/5 flex items-center justify-center text-violet-400 border border-white/5">
                                <i class="fa-solid fa-box-archive text-sm"></i>
                            </div>
                            <div>
                                <span class="block font-black text-slate-200 tracking-tight text-sm">${section}</span>
                                <span class="text-[10px] text-slate-500 font-bold uppercase tracking-widest">${Object.keys(contents).length} Keys</span>
                            </div>
                        </div>
                        <i class="fa-solid fa-plus text-slate-600 text-[10px] transition-transform duration-300"></i>
                    `;
                    
                    header.onclick = () => {
                        sectionDiv.classList.toggle('active');
                        header.querySelector('i.fa-solid').classList.toggle('rotate-45');
                    };

                    const contentDiv = document.createElement('div');
                    contentDiv.className = 'section-content space-y-5';

                    this.buildFields(contents, contentDiv, section);

                    sectionDiv.appendChild(header);
                    sectionDiv.appendChild(contentDiv);
                    container.appendChild(sectionDiv);
                });
            },

            buildFields(obj, parent, path) {
                Object.entries(obj).forEach(([key, val]) => {
                    const fullPath = `${path}.${key}`;
                    if (typeof val === 'object' && val !== null) {
                        const sub = document.createElement('div');
                        sub.className = 'mt-4 border-r-2 border-violet-500/10 pr-4';
                        sub.innerHTML = `<div class="text-[9px] font-black uppercase text-violet-400/60 mb-3 tracking-widest flex items-center gap-2"><i class="fa-solid fa-caret-down"></i> ${key}</div>`;
                        this.buildFields(val, sub, fullPath);
                        parent.appendChild(sub);
                    } else {
                        const field = document.createElement('div');
                        field.className = 'field-item group';
                        field.dataset.search = (fullPath + ' ' + val).toLowerCase();
                        
                        field.innerHTML = `
                            <div class="flex flex-col gap-1.5">
                                <div class="flex items-center justify-between opacity-60 group-hover:opacity-100 transition-opacity">
                                    <label class="text-[10px] font-mono text-slate-400 group-hover:text-violet-400 transition-colors" dir="ltr">${key}</label>
                                    <button onclick="App.copy('${fullPath}')" class="text-[10px] hover:text-white transition-colors">
                                        <i class="fa-regular fa-clone"></i>
                                    </button>
                                </div>
                                <textarea class="text-input custom-scrollbar" oninput="App.update('${fullPath}', this.value)" rows="1">${val}</textarea>
                            </div>
                        `;
                        parent.appendChild(field);
                    }
                });
                this.autoResizeAll();
            },

            update(path, value) {
                const keys = path.split('.');
                let current = this.data;
                for (let i = 0; i < keys.length - 1; i++) {
                    current = current[keys[i]];
                }
                current[keys[keys.length - 1]] = value;
                this.checkChanges();
            },

            checkChanges() {
                const isChanged = JSON.stringify(this.data) !== this.original;
                const btn = document.getElementById('btn-save');
                btn.disabled = !isChanged;
                if(isChanged) btn.classList.add('animate-pulse');
                else btn.classList.remove('animate-pulse');
            },

            setupSearch() {
                document.getElementById('searchField').oninput = (e) => {
                    const query = e.target.value.toLowerCase();
                    const fields = document.querySelectorAll('.field-item');
                    const sections = document.querySelectorAll('.section-container');

                    fields.forEach(f => {
                        const visible = f.dataset.search.includes(query);
                        f.style.display = visible ? 'block' : 'none';
                    });

                    sections.forEach(s => {
                        const hasVisibleChild = Array.from(s.querySelectorAll('.field-item')).some(f => f.style.display !== 'none');
                        const sectionNameMatch = s.innerText.toLowerCase().includes(query);
                        
                        s.style.display = (hasVisibleChild || sectionNameMatch) ? 'block' : 'none';
                        if (query.length > 2 && (hasVisibleChild || sectionNameMatch)) s.classList.add('active');
                        else if (query.length === 0) s.classList.remove('active');
                    });
                };
            },

            async save() {
                const btn = document.getElementById('btn-save');
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-sync fa-spin"></i>';

                try {
                    const res = await fetch('text.php', {
                        method: 'POST',
                        body: JSON.stringify(this.data)
                    });
                    const result = await res.json();
                    if (result.status === 'success') {
                        this.original = JSON.stringify(this.data);
                        this.checkChanges();
                        Swal.fire({ 
                            icon: 'success', title: 'داده‌ها همگام‌سازی شد', toast: true, position: 'top-end', 
                            timer: 3000, showConfirmButton: false, background: '#020617', color: '#fff' 
                        });
                    }
                } catch (e) {
                    Swal.fire({ icon: 'error', title: 'خطا در ذخیره‌سازی' });
                } finally {
                    btn.innerHTML = '<i class="fa-solid fa-cloud-arrow-up text-lg"></i> <span>ذخیره نهایی</span>';
                }
            },

            openRaw() {
                document.getElementById('rawTextarea').value = JSON.stringify(this.data, null, 4);
                document.getElementById('rawModal').classList.remove('hidden');
            },

            closeRaw() { document.getElementById('rawModal').classList.add('hidden'); },

            applyRaw() {
                try {
                    const parsed = JSON.parse(document.getElementById('rawTextarea').value);
                    this.data = parsed;
                    this.render();
                    this.updateStats();
                    this.closeRaw();
                    this.checkChanges();
                } catch (e) {
                    Swal.fire({ icon: 'error', title: 'ساختار JSON نامعتبر است' });
                }
            },

            export() {
                const blob = new Blob([JSON.stringify(this.data, null, 4)], {type: 'application/json'});
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url; a.download = 'backup_texts.json'; a.click();
            },

            copy(text) {
                navigator.clipboard.writeText(text);
                Swal.fire({ title: 'کلید کپی شد', toast: true, position: 'bottom-end', timer: 1500, showConfirmButton: false, background: '#1e293b', color: '#fff' });
            },

            autoResizeAll() {
                document.querySelectorAll('textarea.text-input').forEach(t => {
                    t.style.height = 'auto';
                    t.style.height = (t.scrollHeight) + 'px';
                    t.addEventListener('input', function() {
                        this.style.height = 'auto';
                        this.style.height = (this.scrollHeight) + 'px';
                    });
                });
            }
        };

        App.init();
    </script>
</body>
</html>