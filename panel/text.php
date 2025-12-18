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
    <title>مدیریت محتوای ربات</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    
    <style>
        :root {
            --bg-deep: #020617;
            --accent-primary: #8b5cf6;
            --accent-secondary: #06b6d4;
            --glass-panel: rgba(15, 23, 42, 0.7);
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
                radial-gradient(circle at 0% 0%, rgba(139, 92, 246, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 100% 100%, rgba(6, 182, 212, 0.1) 0%, transparent 50%);
            background-attachment: fixed;
        }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }

        /* Panels */
        .glass-card {
            background: var(--glass-panel);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        /* Form Controls */
        .text-input {
            width: 100%;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 12px 16px;
            color: white;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 0.95rem;
            line-height: 1.6;
            resize: none;
        }
        .text-input:focus {
            outline: none;
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.2);
            background: rgba(15, 23, 42, 0.8);
        }

        /* Sections */
        .section-header {
            cursor: pointer;
            padding: 16px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 16px;
            margin-bottom: 12px;
            transition: all 0.2s;
            border: 1px solid transparent;
        }
        .section-header:hover {
            background: rgba(255, 255, 255, 0.06);
            border-color: rgba(255, 255, 255, 0.05);
        }
        .section-content {
            padding: 8px 12px 24px 12px;
            display: none;
        }
        .section-container.active .section-content { display: block; }
        .section-container.active .section-header {
            background: rgba(139, 92, 246, 0.05);
            border-color: rgba(139, 92, 246, 0.2);
            margin-bottom: 4px;
        }

        /* Buttons */
        .btn-modern {
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            cursor: pointer;
        }
        .btn-save {
            background: linear-gradient(135deg, var(--accent-primary), #6366f1);
            color: white;
            box-shadow: 0 10px 20px -5px rgba(139, 92, 246, 0.4);
        }
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 25px -5px rgba(139, 92, 246, 0.5);
        }
        .btn-save:disabled { opacity: 0.6; transform: none; cursor: not-allowed; }

        /* Floating Nav */
        .floating-nav {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(15px);
            border: 1px solid var(--glass-border);
            padding: 8px 12px;
            border-radius: 20px;
            display: flex;
            gap: 8px;
            z-index: 1000;
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
        }

        .nav-item {
            width: 44px; height: 44px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 14px;
            color: var(--text-muted);
            transition: all 0.2s;
        }
        .nav-item:hover { background: rgba(255,255,255,0.05); color: white; }
        .nav-item.active { background: var(--accent-primary); color: white; }

        /* Animations */
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fade { animation: fadeIn 0.4s ease forwards; }
    </style>
</head>
<body class="pb-32">

    <!-- Header Section -->
    <div class="max-w-6xl mx-auto px-6 pt-12">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-12">
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-10 h-10 rounded-xl bg-violet-500/20 flex items-center justify-center border border-violet-500/30">
                        <i class="fa-solid fa-feather-pointed text-violet-400"></i>
                    </div>
                    <h1 class="text-3xl font-black text-white tracking-tight">مدیریت محتوای <span class="text-violet-400">ربات</span></h1>
                </div>
                <p class="text-slate-400 text-sm font-medium mr-1">پیکربندی تمامی پیام‌ها و پاسخ‌های خودکار سیستم</p>
            </div>
            
            <div class="flex items-center gap-3">
                <div class="hidden sm:flex flex-col items-end ml-4">
                    <span class="text-[10px] text-slate-500 uppercase tracking-widest font-bold">آخرین همگام‌سازی</span>
                    <span class="text-xs text-slate-300"><?php echo $todayDate; ?></span>
                </div>
                <button onclick="App.save()" id="btn-save" class="btn-modern btn-save" disabled>
                    <i class="fa-regular fa-floppy-disk text-lg"></i>
                    <span>ذخیره تغییرات</span>
                </button>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="glass-card p-4 mb-8 flex flex-wrap items-center justify-between gap-4">
            <div class="relative flex-1 min-w-[300px]">
                <i class="fa-solid fa-magnifying-glass absolute right-4 top-1/2 -translate-y-1/2 text-slate-500"></i>
                <input type="text" id="searchField" placeholder="جستجو در بین کلیدها یا محتوا..." class="w-full bg-slate-900/50 border border-white/5 rounded-xl py-2.5 pr-11 pl-4 text-sm focus:ring-2 focus:ring-violet-500/50 outline-none transition-all">
            </div>
            <div class="flex items-center gap-2">
                <button onclick="App.openRaw()" class="w-11 h-11 rounded-xl bg-white/5 border border-white/5 flex items-center justify-center text-slate-400 hover:text-white hover:bg-white/10 transition" title="ویرایش خام">
                    <i class="fa-solid fa-code"></i>
                </button>
                <button onclick="App.export()" class="w-11 h-11 rounded-xl bg-white/5 border border-white/5 flex items-center justify-center text-slate-400 hover:text-white hover:bg-white/10 transition" title="خروجی JSON">
                    <i class="fa-solid fa-download"></i>
                </button>
            </div>
        </div>

        <!-- Main Editor Container -->
        <div id="editor-container" class="space-y-4">
            <!-- Loading State -->
            <div id="loader" class="flex flex-col items-center justify-center py-24 opacity-50">
                <i class="fa-solid fa-circle-notch fa-spin text-4xl text-violet-500 mb-4"></i>
                <p class="text-sm font-bold tracking-widest uppercase">Initializing Database...</p>
            </div>
        </div>
    </div>

    <!-- Raw Editor Modal (Simplified with Tailwind) -->
    <div id="rawModal" class="fixed inset-0 z-[2000] hidden flex items-center justify-center p-6 bg-black/80 backdrop-blur-sm">
        <div class="glass-card w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden">
            <div class="p-6 border-b border-white/5 flex justify-between items-center bg-white/5">
                <h3 class="font-bold flex items-center gap-2">
                    <i class="fa-solid fa-code text-violet-400"></i>
                    ویرایشگر مستقیم JSON
                </h3>
                <button onclick="App.closeRaw()" class="text-slate-400 hover:text-white transition"><i class="fa-solid fa-xmark text-xl"></i></button>
            </div>
            <div class="p-6 overflow-y-auto">
                <textarea id="rawTextarea" class="w-full h-[500px] bg-slate-950 border border-white/5 rounded-xl p-6 font-mono text-sm text-emerald-400 focus:outline-none focus:ring-2 focus:ring-violet-500/50" dir="ltr"></textarea>
            </div>
            <div class="p-6 border-t border-white/5 bg-white/5 flex justify-end gap-3">
                <button onclick="App.closeRaw()" class="px-6 py-2 rounded-xl text-slate-400 hover:text-white transition">انصراف</button>
                <button onclick="App.applyRaw()" class="px-8 py-2 rounded-xl bg-violet-600 hover:bg-violet-500 text-white font-bold transition">اعمال تغییرات</button>
            </div>
        </div>
    </div>

    <!-- Navigation Bar -->
    <nav class="floating-nav">
        <a href="index.php" class="nav-item" title="داشبورد"><i class="fa-solid fa-house"></i></a>
        <a href="users.php" class="nav-item" title="کاربران"><i class="fa-solid fa-users"></i></a>
        <a href="service.php" class="nav-item" title="سرویس‌ها"><i class="fa-solid fa-server"></i></a>
        <div class="w-px h-6 bg-white/10 my-auto mx-1"></div>
        <a href="text.php" class="nav-item active" title="متون"><i class="fa-solid fa-file-pen"></i></a>
        <a href="settings.php" class="nav-item" title="تنظیمات"><i class="fa-solid fa-gear"></i></a>
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
                } catch (e) {
                    console.error("Load failed", e);
                }
            },

            render() {
                const container = document.getElementById('editor-container');
                container.innerHTML = '';
                
                Object.entries(this.data).forEach(([section, contents]) => {
                    const sectionDiv = document.createElement('div');
                    sectionDiv.className = 'section-container animate-fade';
                    
                    const header = document.createElement('div');
                    header.className = 'section-header';
                    header.innerHTML = `
                        <div class="flex items-center gap-3">
                            <i class="fa-solid fa-folder-open text-violet-400/60 text-sm"></i>
                            <span class="font-bold text-slate-200 tracking-tight">${section}</span>
                        </div>
                        <i class="fa-solid fa-chevron-down text-slate-600 text-xs transition-transform duration-300"></i>
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
                
                document.getElementById('loader').style.display = 'none';
                this.autoResizeAll();
            },

            buildFields(obj, parent, path) {
                Object.entries(obj).forEach(([key, val]) => {
                    const fullPath = `${path}.${key}`;
                    if (typeof val === 'object' && val !== null) {
                        const subGroup = document.createElement('div');
                        subGroup.className = 'mr-6 border-r-2 border-white/5 pr-6 mt-4';
                        subGroup.innerHTML = `<div class="text-[10px] font-black uppercase text-slate-600 mb-4 tracking-[0.2em]">${key}</div>`;
                        this.buildFields(val, subGroup, fullPath);
                        parent.appendChild(subGroup);
                    } else {
                        const field = document.createElement('div');
                        field.className = 'field-item group';
                        field.dataset.search = (fullPath + ' ' + val).toLowerCase();
                        
                        field.innerHTML = `
                            <div class="flex flex-col gap-2">
                                <div class="flex items-center justify-between px-1">
                                    <label class="text-xs font-mono text-slate-500 group-hover:text-violet-400 transition-colors" dir="ltr">${key}</label>
                                    <button onclick="App.copy('${fullPath}')" class="text-[10px] text-slate-600 hover:text-white transition-opacity opacity-0 group-hover:opacity-100">
                                        <i class="fa-regular fa-copy"></i>
                                    </button>
                                </div>
                                <textarea class="text-input" oninput="App.update('${fullPath}', this.value)" rows="1">${val}</textarea>
                            </div>
                        `;
                        parent.appendChild(field);
                    }
                });
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
                document.getElementById('btn-save').disabled = !isChanged;
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
                        s.style.display = hasVisibleChild ? 'block' : 'none';
                        if (query.length > 1 && hasVisibleChild) s.classList.add('active');
                        else if (query.length === 0) s.classList.remove('active');
                    });
                };
            },

            async save() {
                const btn = document.getElementById('btn-save');
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

                try {
                    const res = await fetch('text.php', {
                        method: 'POST',
                        body: JSON.stringify(this.data)
                    });
                    const result = await res.json();
                    if (result.status === 'success') {
                        this.original = JSON.stringify(this.data);
                        this.checkChanges();
                        Swal.fire({ icon: 'success', title: 'تغییرات با موفقیت ذخیره شد', toast: true, position: 'top-end', timer: 3000, showConfirmButton: false, background: '#0f172a', color: '#fff' });
                    }
                } catch (e) {
                    Swal.fire({ icon: 'error', title: 'خطا در ارتباط با سرور' });
                } finally {
                    btn.innerHTML = '<i class="fa-regular fa-floppy-disk text-lg"></i> <span>ذخیره تغییرات</span>';
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
                    this.closeRaw();
                    this.checkChanges();
                } catch (e) {
                    Swal.fire({ icon: 'error', title: 'فرمت JSON نامعتبر است' });
                }
            },

            export() {
                const blob = new Blob([JSON.stringify(this.data, null, 4)], {type: 'application/json'});
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'bot_texts.json';
                a.click();
            },

            copy(text) {
                navigator.clipboard.writeText(text);
                Swal.fire({ title: 'کپی شد!', toast: true, position: 'bottom-end', timer: 1500, showConfirmButton: false });
            },

            autoResizeAll() {
                const textareas = document.querySelectorAll('textarea.text-input');
                textareas.forEach(t => {
                    t.style.height = 'auto';
                    t.style.height = t.scrollHeight + 'px';
                    t.addEventListener('input', function() {
                        this.style.height = 'auto';
                        this.style.height = this.scrollHeight + 'px';
                    });
                });
            }
        };

        App.init();
    </script>
</body>
</html>