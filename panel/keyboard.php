<?php
/**
 * Keyboard Editor - Aurora Glass Edition
 * Ultra Professional UI/UX
 */

session_start();

// 1. Load Configurations
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/../function.php';

// 2. Authentication Check
if (!isset($_SESSION["user"])) {
    header('Location: login.php');
    exit;
}

try {
    $authStmt = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
    $authStmt->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
    $authStmt->execute();
    $adminRow = $authStmt->fetch(PDO::FETCH_ASSOC);

    if (!$adminRow) {
        header('Location: login.php');
        exit;
    }
} catch (Exception $e) {
    die("Database Error.");
}

// 3. API Handler (Save Logic)
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST') {
    $inputJSON = file_get_contents("php://input");
    $inputData = json_decode($inputJSON, true);

    if (is_array($inputData)) {
        $keyboardStruct = ['keyboard' => $inputData];
        update("setting", "keyboardmain", json_encode($keyboardStruct, JSON_UNESCAPED_UNICODE), null, null);
        
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'ts' => time()]);
        exit;
    }
}

// 4. Reset Logic
if (isset($_GET['action']) && $_GET['action'] === 'reaset') {
    $defaultKeyboard = json_encode([
        "keyboard" => [
            [["text" => "text_sell"], ["text" => "text_extend"]],
            [["text" => "text_usertest"], ["text" => "text_wheel_luck"]],
            [["text" => "text_Purchased_services"], ["text" => "accountwallet"]],
            [["text" => "text_affiliates"], ["text" => "text_Tariff_list"]],
            [["text" => "text_support"], ["text" => "text_help"]]
        ]
    ], JSON_UNESCAPED_UNICODE);
    
    update("setting", "keyboardmain", $defaultKeyboard, null, null);
    header('Location: keyboard.php');
    exit;
}

// 5. Fetch Current Data
$currentKeyboardJSON = '[]';
try {
    $stmt = $pdo->prepare("SELECT keyboardmain FROM setting LIMIT 1");
    $stmt->execute();
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($settings && !empty($settings['keyboardmain'])) {
        $decoded = json_decode($settings['keyboardmain'], true);
        if (isset($decoded['keyboard'])) {
            $currentKeyboardJSON = json_encode($decoded['keyboard']);
        }
    }
    
    if ($currentKeyboardJSON == '[]' || $currentKeyboardJSON == 'null') {
         $def = [
            "keyboard" => [
                [["text" => "text_sell"], ["text" => "text_extend"]],
                [["text" => "text_usertest"], ["text" => "text_wheel_luck"]],
                [["text" => "text_Purchased_services"], ["text" => "accountwallet"]],
                [["text" => "text_affiliates"], ["text" => "text_Tariff_list"]],
                [["text" => "text_support"], ["text" => "text_help"]]
            ]
         ];
         $currentKeyboardJSON = json_encode($def['keyboard']);
    }
} catch (Exception $e) { 
    $currentKeyboardJSON = '[]'; 
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>استودیو طراحی کیبورد | MirzaBot</title>
    
    <!-- Libraries -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Fonts & Icons -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <style>
        /* --- AURORA THEME SYSTEM --- */
        :root {
            --bg-void: #02040a;
            --glass-panel: rgba(13, 17, 30, 0.65);
            --glass-card: rgba(255, 255, 255, 0.03);
            --border-subtle: rgba(255, 255, 255, 0.06);
            --border-highlight: rgba(99, 102, 241, 0.3);
            
            --primary: #6366f1; /* Indigo */
            --primary-glow: rgba(99, 102, 241, 0.5);
            --secondary: #ec4899; /* Pink */
            --accent: #06b6d4; /* Cyan */
            
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
            
            --blur-md: blur(12px);
            --blur-lg: blur(24px);
        }

        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: var(--bg-void);
            color: var(--text-main);
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        /* Animated Background */
        .aurora-bg {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1;
            background: 
                radial-gradient(circle at 0% 0%, rgba(99, 102, 241, 0.15), transparent 40%),
                radial-gradient(circle at 100% 0%, rgba(236, 72, 153, 0.1), transparent 40%),
                radial-gradient(circle at 50% 100%, rgba(6, 182, 212, 0.1), transparent 50%);
            filter: blur(60px); opacity: 0.8;
        }
        .grid-overlay {
            position: fixed; inset: 0; z-index: -1;
            background-image: linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 40px 40px; mask-image: linear-gradient(to bottom, black 40%, transparent 100%);
        }

        /* --- UI COMPONENTS --- */

        /* 1. Glass Header */
        .studio-header {
            height: 72px;
            background: var(--glass-panel);
            backdrop-filter: var(--blur-lg);
            border-bottom: 1px solid var(--border-subtle);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 40px; z-index: 50;
        }

        .logo-section { display: flex; align-items: center; gap: 14px; }
        .logo-mark {
            width: 44px; height: 44px;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(168, 85, 247, 0.2));
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 0 30px rgba(99, 102, 241, 0.2);
            position: relative; overflow: hidden;
        }
        .logo-mark::before {
            content: ''; position: absolute; inset: 0;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
            transform: translateX(-100%); transition: 0.5s;
        }
        .logo-mark:hover::before { transform: translateX(100%); }
        .logo-icon { font-size: 20px; color: #a5b4fc; filter: drop-shadow(0 0 5px rgba(165, 180, 252, 0.5)); }

        .btn-modern {
            height: 42px; padding: 0 20px; border-radius: 12px;
            font-size: 13px; font-weight: 600; cursor: pointer;
            display: flex; align-items: center; gap: 10px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid transparent;
        }
        .btn-ghost { color: var(--text-muted); background: transparent; border-color: rgba(255,255,255,0.05); }
        .btn-ghost:hover { background: rgba(255,255,255,0.05); color: white; border-color: rgba(255,255,255,0.15); transform: translateY(-1px); }
        
        .btn-danger-soft { color: #f87171; background: rgba(239,68,68,0.05); border-color: rgba(239,68,68,0.1); }
        .btn-danger-soft:hover { background: rgba(239,68,68,0.15); border-color: rgba(239,68,68,0.3); transform: translateY(-1px); }

        .btn-primary-glow {
            background: linear-gradient(135deg, var(--primary), #4f46e5);
            color: white; box-shadow: 0 0 20px var(--primary-glow);
            position: relative; overflow: hidden;
        }
        .btn-primary-glow:hover {
            box-shadow: 0 0 35px var(--primary-glow); transform: translateY(-1px); filter: brightness(1.1);
        }
        .btn-primary-glow:disabled { background: #1e1e24; color: #52525b; box-shadow: none; cursor: not-allowed; filter: grayscale(1); }

        /* 2. Workspace */
        .workspace-grid {
            display: grid; grid-template-columns: 480px 1fr;
            height: calc(100vh - 72px); overflow: hidden; position: relative; z-index: 10;
        }

        /* 3. Preview Column (Left) */
        .preview-col {
            background: rgba(2, 4, 10, 0.4);
            border-left: 1px solid var(--border-subtle);
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            position: relative; backdrop-filter: blur(8px);
        }

        .phone-mockup {
            width: 370px; height: 750px;
            background: #000;
            border-radius: 56px;
            box-shadow: 
                0 0 0 8px #1a1a1a, /* Inner Frame */
                0 0 0 12px #3f3f46, /* Titanium Frame */
                0 40px 100px -20px rgba(0,0,0,0.9);
            overflow: hidden; display: flex; flex-direction: column;
            position: relative; transform: scale(0.9);
            border: 1px solid #000;
        }
        
        .dynamic-island {
            position: absolute; top: 12px; left: 50%; transform: translateX(-50%);
            width: 120px; height: 35px; background: #000; border-radius: 100px; z-index: 20;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .phone-mockup:hover .dynamic-island { width: 140px; height: 40px; top: 10px; }

        .tg-app-header {
            padding: 55px 20px 15px; background: #1c1c1e;
            display: flex; align-items: center; gap: 12px;
            border-bottom: 1px solid #000; color: white;
        }
        
        .tg-chat-bg {
            flex: 1; background: #0f0f0f;
            background-image: url("data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M20 20.5V18H0v-2h20v-2H0v-2h20v-2H0V8h20V6H0V4h20V2H0V0h21.5v21.5h-1.5z' fill='%231a1a1a' fill-opacity='0.6' fill-rule='evenodd'/%3E%3C/svg%3E");
            display: flex; flex-direction: column; justify-content: flex-end; padding-bottom: 12px;
        }

        .tg-bubble {
            background: linear-gradient(135deg, #2b5278, #23364d);
            color: white; padding: 12px 16px;
            border-radius: 18px; border-top-left-radius: 4px;
            max-width: 85%; margin: 0 16px 14px; font-size: 14px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.2); line-height: 1.5;
        }

        .tg-kb-area {
            background: #1c1c1e; padding: 8px; min-height: 240px;
            border-top: 1px solid #000;
        }

        .tg-btn {
            background: linear-gradient(180deg, #353537 0%, #2a2a2c 100%);
            color: #fff; border-radius: 8px; margin: 3px;
            padding: 12px 4px; font-size: 13px; text-align: center;
            box-shadow: 0 1px 0 rgba(0,0,0,0.5), inset 0 1px 0 rgba(255,255,255,0.05);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            display: flex; align-items: center; justify-content: center;
            border: 1px solid transparent;
        }

        /* 4. Editor Column (Right) */
        .editor-col {
            flex: 1; display: flex; flex-direction: column;
            background: transparent; position: relative;
        }
        .editor-content { flex: 1; overflow-y: auto; padding: 50px 80px; }

        /* Row Card */
        .row-module {
            background: var(--glass-card);
            border: 1px solid var(--border-subtle);
            border-radius: 18px; padding: 16px; margin-bottom: 24px;
            display: flex; flex-wrap: wrap; gap: 12px;
            position: relative; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            backdrop-filter: blur(10px);
        }
        .row-module:hover {
            border-color: rgba(99, 102, 241, 0.3);
            background: rgba(30, 30, 45, 0.6);
            box-shadow: 0 15px 40px -10px rgba(0,0,0,0.3);
            transform: translateY(-2px);
        }

        .drag-handle {
            position: absolute; left: -36px; top: 50%; transform: translateY(-50%);
            color: var(--text-muted); cursor: grab; padding: 8px;
            opacity: 0; transition: 0.2s; font-size: 20px;
        }
        .row-module:hover .drag-handle { opacity: 0.6; left: -32px; }
        .drag-handle:hover { opacity: 1 !important; color: white; }

        /* Key Unit */
        .key-unit {
            flex: 1; min-width: 150px;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border-subtle);
            border-radius: 12px; padding: 14px;
            position: relative; cursor: grab;
            display: flex; flex-direction: column; gap: 6px;
            transition: all 0.2s;
        }
        .key-unit:hover {
            background: rgba(99, 102, 241, 0.05);
            border-color: var(--primary);
            box-shadow: inset 0 0 0 1px var(--primary-glow);
        }
        .key-unit:active { transform: scale(0.98); }

        .key-code {
            font-family: 'JetBrains Mono', monospace; font-size: 12px;
            color: #a5b4fc; text-align: right; direction: ltr; font-weight: 600;
        }
        .key-label { font-size: 11px; color: var(--text-muted); font-weight: 500; }

        /* Key Actions (Hover) */
        .unit-actions {
            position: absolute; top: 8px; left: 8px;
            display: flex; gap: 6px; opacity: 0; transition: 0.2s; transform: translateY(5px);
        }
        .key-unit:hover .unit-actions { opacity: 1; transform: translateY(0); }

        .mini-btn {
            width: 24px; height: 24px; border-radius: 6px;
            background: rgba(0,0,0,0.4); color: white;
            display: flex; align-items: center; justify-content: center;
            font-size: 11px; cursor: pointer; backdrop-filter: blur(4px);
            transition: 0.2s;
        }
        .mini-btn:hover { background: var(--primary); transform: scale(1.1); }
        .mini-btn.del:hover { background: var(--danger); }

        /* Add Buttons */
        .add-in-row {
            width: 48px; border: 1px dashed var(--text-muted); border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            color: var(--text-muted); cursor: pointer; transition: 0.2s; opacity: 0.4;
        }
        .add-in-row:hover {
            border-color: var(--primary); color: var(--primary); opacity: 1;
            background: rgba(99, 102, 241, 0.1);
        }

        .add-row-fab {
            width: 100%; padding: 24px; margin-top: 30px;
            border: 2px dashed var(--border-subtle); border-radius: 20px;
            color: var(--text-muted); font-weight: 600; font-size: 15px;
            display: flex; align-items: center; justify-content: center; gap: 10px;
            cursor: pointer; transition: 0.2s;
        }
        .add-row-fab:hover {
            border-color: var(--primary); color: var(--primary);
            background: rgba(99, 102, 241, 0.03); transform: translateY(-2px);
            box-shadow: 0 10px 30px -10px rgba(99, 102, 241, 0.1);
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 99px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }

        @media (max-width: 1024px) {
            .workspace-grid { grid-template-columns: 1fr; }
            .preview-col { display: none; }
            .editor-content { padding: 30px 20px; }
        }
    </style>
</head>
<body>

    <div class="aurora-bg"></div>
    <div class="grid-overlay"></div>

    <!-- Header -->
    <header class="studio-header">
        <div class="logo-section">
            <div class="logo-mark">
                <i class="fa-solid fa-cube logo-icon"></i>
            </div>
            <div>
                <h1 class="text-white font-bold text-lg tracking-tight">MirzaBot <span class="text-xs text-indigo-300 font-light opacity-80">STUDIO</span></h1>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <a href="index.php" class="btn-modern btn-ghost">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
                <span class="hidden sm:block">خروج</span>
            </a>
            <a href="keyboard.php?action=reaset" onclick="return confirm('تنظیمات به حالت اولیه بازگردد؟')" class="btn-modern btn-danger-soft">
                <i class="fa-solid fa-rotate-right"></i>
            </a>
            <div class="w-px h-8 bg-white/10 mx-2"></div>
            <button onclick="App.save()" id="btn-save" class="btn-modern btn-primary-glow" disabled>
                <i class="fa-regular fa-floppy-disk"></i>
                <span>ذخیره تغییرات</span>
            </button>
        </div>
    </header>

    <!-- Workspace -->
    <div class="workspace-grid">
        
        <!-- Left: Preview -->
        <div class="preview-col">
            <div class="absolute top-8 left-8 text-[10px] font-bold text-indigo-400 uppercase tracking-[3px] opacity-80 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-indigo-500 animate-pulse"></span> Live Preview
            </div>
            
            <div class="phone-mockup animate__animated animate__fadeInUp">
                <div class="dynamic-island"></div>
                
                <div class="tg-app-header">
                    <i class="fa-solid fa-arrow-right text-gray-400"></i>
                    <div class="flex-1 font-bold text-sm text-white">Mirza Bot <span class="text-xs text-blue-400 font-normal">bot</span></div>
                    <i class="fa-solid fa-ellipsis-vertical text-gray-400"></i>
                </div>

                <div class="tg-chat-bg">
                    <div class="tg-bubble">
                        پیش‌نمایش زنده منوی ربات 👇
                    </div>
                </div>

                <div id="preview-render" class="tg-kb-area flex flex-col justify-end">
                    <!-- Buttons Render Here -->
                </div>
            </div>
        </div>

        <!-- Right: Editor -->
        <div class="editor-col">
            <div class="editor-content">
                <div id="editor-render" class="max-w-5xl mx-auto pb-8">
                    <!-- Rows Render Here -->
                </div>
                
                <div class="max-w-5xl mx-auto pb-24">
                    <div onclick="App.addRow()" class="add-row-fab">
                        <i class="fa-solid fa-plus text-xl"></i>
                        افزودن سطر جدید
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Logic -->
    <script>
        const App = {
            data: {
                keyboard: <?php echo $currentKeyboardJSON ?: '[]'; ?>,
                initialSnapshot: '',
                labels: {
                    'text_sell': '🛍 خرید سرویس',
                    'text_extend': '🔄 تمدید سرویس',
                    'text_usertest': '🔥 تست رایگان',
                    'text_wheel_luck': '🎰 گردونه شانس',
                    'text_Purchased_services': '👤 سرویس‌های من',
                    'accountwallet': '💳 کیف پول',
                    'text_affiliates': '🤝 همکاری در فروش',
                    'text_Tariff_list': '📋 لیست تعرفه‌ها',
                    'text_support': '🎧 پشتیبانی',
                    'text_help': '📚 راهنما'
                }
            },

            dom: {
                editor: document.getElementById('editor-render'),
                preview: document.getElementById('preview-render'),
                saveBtn: document.getElementById('btn-save')
            },

            init() {
                if (!Array.isArray(this.data.keyboard)) this.data.keyboard = [];
                this.data.initialSnapshot = JSON.stringify(this.data.keyboard);
                
                // Config SweetAlert (Aurora Theme)
                this.swal = Swal.mixin({
                    background: '#0b0b1e',
                    color: '#f1f5f9',
                    confirmButtonColor: '#6366f1',
                    cancelButtonColor: '#ef4444',
                    customClass: { popup: 'border border-indigo-500/30 rounded-2xl shadow-2xl backdrop-blur-xl' },
                    buttonsStyling: true
                });

                this.render();
            },

            render() {
                this.renderEditor();
                this.renderPreview();
                this.checkChanges();
            },

            renderEditor() {
                const { editor } = this.dom;
                editor.innerHTML = '';

                if (this.data.keyboard.length === 0) {
                    editor.innerHTML = `
                        <div class="flex flex-col items-center justify-center py-32 opacity-30 select-none text-indigo-300">
                            <i class="fa-solid fa-cubes-stacked text-7xl mb-6"></i>
                            <p class="text-lg font-light tracking-wide">هیچ دکمه‌ای تعریف نشده است</p>
                        </div>`;
                    return;
                }

                this.data.keyboard.forEach((row, rIdx) => {
                    const rowEl = document.createElement('div');
                    rowEl.className = 'row-module animate__animated animate__fadeIn';
                    rowEl.innerHTML = `<div class="drag-handle"><i class="fa-solid fa-grip-vertical"></i></div>`;

                    row.forEach((btn, bIdx) => {
                        const label = this.data.labels[btn.text] || 'دکمه سفارشی';
                        const keyEl = document.createElement('div');
                        keyEl.className = 'key-unit';
                        keyEl.innerHTML = `
                            <div class="key-code" title="${btn.text}">${btn.text}</div>
                            <div class="key-label">${label}</div>
                            <div class="unit-actions">
                                <div class="mini-btn" onclick="App.editKey(${rIdx}, ${bIdx})"><i class="fa-solid fa-pen"></i></div>
                                <div class="mini-btn del" onclick="App.deleteKey(${rIdx}, ${bIdx})"><i class="fa-solid fa-xmark"></i></div>
                            </div>
                        `;
                        rowEl.appendChild(keyEl);
                    });

                    // Inline Add
                    if (row.length < 8) {
                        const addBtn = document.createElement('div');
                        addBtn.className = 'add-in-row';
                        addBtn.innerHTML = '<i class="fa-solid fa-plus text-sm"></i>';
                        addBtn.onclick = () => this.addKeyToRow(rIdx);
                        rowEl.appendChild(addBtn);
                    }

                    // Delete Row
                    if (row.length === 0) {
                        const delRow = document.createElement('div');
                        delRow.className = 'w-full text-center text-xs text-red-400 py-3 border border-dashed border-red-500/20 rounded-lg cursor-pointer hover:bg-red-500/10 transition';
                        delRow.innerHTML = 'حذف سطر خالی';
                        delRow.onclick = () => this.deleteRow(rIdx);
                        rowEl.appendChild(delRow);
                    }

                    editor.appendChild(rowEl);
                });

                this.initSortable();
            },

            renderPreview() {
                const { preview } = this.dom;
                preview.innerHTML = '';
                
                this.data.keyboard.forEach(row => {
                    const rowDiv = document.createElement('div');
                    rowDiv.className = 'flex w-full gap-1 mb-1';
                    
                    row.forEach(btn => {
                        const btnDiv = document.createElement('div');
                        btnDiv.className = 'tg-btn flex-1 truncate';
                        // Show Persian Label
                        btnDiv.innerText = this.data.labels[btn.text] || btn.text; 
                        rowDiv.appendChild(btnDiv);
                    });
                    
                    if (row.length > 0) preview.appendChild(rowDiv);
                });
            },

            initSortable() {
                new Sortable(this.dom.editor, {
                    animation: 300, handle: '.drag-handle', ghostClass: 'opacity-40',
                    onEnd: (evt) => {
                        const item = this.data.keyboard.splice(evt.oldIndex, 1)[0];
                        this.data.keyboard.splice(evt.newIndex, 0, item);
                        this.render();
                    }
                });

                document.querySelectorAll('.row-module').forEach(el => {
                    new Sortable(el, {
                        group: 'shared', animation: 200, draggable: '.key-unit', ghostClass: 'opacity-40',
                        onEnd: () => this.rebuildData()
                    });
                });
            },

            rebuildData() {
                const newRows = [];
                this.dom.editor.querySelectorAll('.row-module').forEach(row => {
                    const btns = [];
                    row.querySelectorAll('.key-code').forEach(el => btns.push({ text: el.innerText }));
                    if (btns.length > 0 || row.querySelector('.fa-plus')) newRows.push(btns);
                });
                this.data.keyboard = newRows;
                this.render();
            },

            checkChanges() {
                const current = JSON.stringify(this.data.keyboard);
                const isDirty = current !== this.data.initialSnapshot;
                const { saveBtn } = this.dom;
                
                if (isDirty) {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '<i class="fa-solid fa-check"></i> ذخیره تغییرات';
                    saveBtn.classList.add('animate-pulse');
                } else {
                    saveBtn.disabled = true;
                    saveBtn.innerHTML = '<i class="fa-regular fa-floppy-disk"></i> ذخیره شد';
                    saveBtn.classList.remove('animate-pulse');
                }
            },

            addRow() {
                this.data.keyboard.push([{text: 'text_new'}]);
                this.render();
                setTimeout(() => document.querySelector('.editor-content').scrollTop = 99999, 50);
            },

            deleteRow(idx) {
                this.data.keyboard.splice(idx, 1);
                this.render();
            },

            async addKeyToRow(rIdx) {
                const { value: text } = await this.swal.fire({
                    title: 'افزودن دکمه',
                    input: 'text',
                    inputValue: 'text_new',
                    inputLabel: 'کد متغیر (انگلیسی)',
                    showCancelButton: true,
                    confirmButtonText: 'افزودن'
                });
                if (text) {
                    this.data.keyboard[rIdx].push({text});
                    this.render();
                }
            },

            deleteKey(rIdx, bIdx) {
                this.data.keyboard[rIdx].splice(bIdx, 1);
                this.render();
            },

            async editKey(rIdx, bIdx) {
                const current = this.data.keyboard[rIdx][bIdx].text;
                const { value: text } = await this.swal.fire({
                    title: 'ویرایش کد',
                    input: 'text',
                    inputValue: current,
                    showCancelButton: true,
                    confirmButtonText: 'تایید'
                });
                if (text) {
                    this.data.keyboard[rIdx][bIdx].text = text;
                    this.render();
                }
            },

            save() {
                const { saveBtn } = this.dom;
                const originalText = saveBtn.innerHTML;
                saveBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> ...';
                saveBtn.disabled = true;

                fetch('keyboard.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(this.data.keyboard)
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        this.data.initialSnapshot = JSON.stringify(this.data.keyboard);
                        this.checkChanges();
                        const Toast = Swal.mixin({
                            toast: true, position: 'top-end', showConfirmButton: false, 
                            timer: 3000, background: '#0b0b1e', color: '#fff'
                        });
                        Toast.fire({icon: 'success', title: 'تنظیمات با موفقیت ذخیره شد'});
                    }
                })
                .catch(err => {
                    saveBtn.innerHTML = originalText;
                    saveBtn.disabled = false;
                    this.swal.fire({icon: 'error', title: 'خطا در ارتباط'});
                });
            }
        };

        App.init();
    </script>
</body>
</html>