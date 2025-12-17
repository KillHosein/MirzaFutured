<?php
/**
 * Keyboard Editor Controller
 * * Handles the logic for retrieving and saving the Telegram bot keyboard layout.
 */

// 1. Initialization & Configuration
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/../function.php';

// 2. Authentication Middleware
if (!isset($_SESSION["user"])) {
    header('Location: login.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id FROM admin WHERE username=:username LIMIT 1");
    $stmt->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
    $stmt->execute();
    if (!$stmt->fetch()) {
        header('Location: login.php');
        exit;
    }
} catch (PDOException $e) {
    die("Database Error: Authentication check failed.");
}

// 3. Request Handling (Controller Logic)
$method = $_SERVER['REQUEST_METHOD'];
$action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_SPECIAL_CHARS);

// -> Handle Save Request (API)
if ($method === 'POST') {
    $inputJSON = file_get_contents("php://input");
    $inputData = json_decode($inputJSON, true);

    if (is_array($inputData)) {
        // Validation could go here
        $keyboardStruct = ['keyboard' => $inputData];
        update("setting", "keyboardmain", json_encode($keyboardStruct, JSON_UNESCAPED_UNICODE), null, null);
        
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'timestamp' => time()]);
        exit;
    }
}

// -> Handle Reset Action
if ($method === 'GET' && $action === 'reaset') {
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

// 4. Data Provisioning (View Model)
$currentKeyboardJSON = '[]';
try {
    // Optimized query to fetch only necessary field
    $stmt = $pdo->prepare("SELECT keyboardmain FROM setting LIMIT 1");
    $stmt->execute();
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($settings && !empty($settings['keyboardmain'])) {
        $decoded = json_decode($settings['keyboardmain'], true);
        if (isset($decoded['keyboard'])) {
            $currentKeyboardJSON = json_encode($decoded['keyboard']);
        }
    }
    
    // Fallback / Default State if DB is empty
    if ($currentKeyboardJSON === '[]' || empty($currentKeyboardJSON)) {
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
    // Fail gracefully
    $currentKeyboardJSON = '[]'; 
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>استودیو طراحی کیبورد | MirzaBot</title>
    
    <!-- Core Libraries -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Fonts & Icons -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <style>
        /* * DESIGN SYSTEM: Obsidian Dark
         * A professional, deep dark theme optimized for long usage.
         */
        :root {
            /* Backgrounds */
            --bg-deep: #030014;
            --bg-surface: #0f0b29;
            --bg-card: rgba(30, 27, 75, 0.4);
            --bg-input: rgba(15, 11, 41, 0.7);
            
            /* Borders */
            --border-color: rgba(124, 58, 237, 0.15);
            --border-highlight: rgba(124, 58, 237, 0.5);
            
            /* Colors */
            --primary: #8b5cf6;
            --primary-glow: rgba(139, 92, 246, 0.5);
            --secondary: #ec4899;
            --danger: #ef4444;
            
            /* Typography */
            --text-main: #e2e8f0;
            --text-muted: #94a3b8;
            
            /* Effects */
            --glass: rgba(15, 11, 41, 0.7);
            --blur: blur(20px);
        }

        /* --- Global Reset & Base --- */
        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: var(--bg-deep);
            color: var(--text-main);
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            background-image: 
                linear-gradient(rgba(139, 92, 246, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(139, 92, 246, 0.03) 1px, transparent 1px);
            background-size: 50px 50px;
            background-position: center;
        }
        
        body::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 50% 0%, rgba(88, 28, 135, 0.15), transparent 60%);
            pointer-events: none;
            z-index: 0;
        }

        /* --- UI Components --- */
        
        /* 1. Header */
        .cyber-header {
            height: 70px;
            background: var(--glass);
            backdrop-filter: var(--blur);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 40px;
            z-index: 50;
            position: relative;
        }

        .logo-box {
            width: 42px; height: 42px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 0 25px var(--primary-glow);
            position: relative;
        }
        .logo-box::after {
            content: ''; position: absolute; inset: 1px; background: #000; border-radius: 11px; z-index: 1; opacity: 0.2;
        }
        
        /* 2. Buttons */
        .btn-glass {
            height: 40px; padding: 0 20px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--text-muted);
            font-size: 13px; font-weight: 500;
            display: flex; align-items: center; gap: 8px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .btn-glass:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.2);
            color: white; transform: translateY(-1px);
        }
        
        .btn-neon {
            height: 40px; padding: 0 24px;
            border-radius: 10px;
            background: var(--primary);
            color: white; border: none;
            font-size: 13px; font-weight: 600;
            display: flex; align-items: center; gap: 8px;
            box-shadow: 0 0 20px var(--primary-glow);
            transition: all 0.3s ease;
            cursor: pointer; position: relative; overflow: hidden;
        }
        .btn-neon:hover {
            box-shadow: 0 0 30px var(--primary-glow); transform: translateY(-1px);
        }
        .btn-neon:disabled { background: #333; box-shadow: none; cursor: not-allowed; opacity: 0.7; }

        /* 3. Preview Phone */
        .preview-zone {
            background: rgba(3, 0, 20, 0.4);
            border-left: 1px solid var(--border-color);
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            position: relative;
            backdrop-filter: blur(5px);
        }

        .phone-frame {
            width: 380px; height: 760px;
            background: #000;
            border-radius: 55px;
            box-shadow: 0 0 0 6px #333, 0 0 0 9px #1a1a1a, 0 20px 60px -10px rgba(0,0,0,0.8);
            overflow: hidden;
            display: flex; flex-direction: column;
            position: relative;
            transform: scale(0.85);
            border: 1px solid #333;
        }
        
        .island {
            position: absolute; top: 12px; left: 50%; transform: translateX(-50%);
            width: 126px; height: 37px; background: #000; border-radius: 20px; z-index: 20;
        }

        .tg-kb-wrapper {
            background: #1c1c1e; padding: 6px; min-height: 240px;
            border-top: 1px solid #2c2c2e;
        }

        .tg-btn-render {
            background: linear-gradient(180deg, #3a3a3c 0%, #2c2c2e 100%);
            color: #fff; border-radius: 8px; margin: 3px;
            padding: 12px 4px; font-size: 13px; text-align: center;
            box-shadow: 0 2px 0 rgba(0,0,0,0.4);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            display: flex; align-items: center; justify-content: center;
            border-top: 1px solid rgba(255,255,255,0.08);
        }

        /* 4. Editor Canvas */
        .row-module {
            background: rgba(15, 11, 41, 0.4);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 16px; margin-bottom: 20px;
            display: flex; flex-wrap: wrap; gap: 12px;
            position: relative; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            backdrop-filter: blur(10px);
        }
        .row-module:hover {
            border-color: var(--border-highlight);
            background: rgba(15, 11, 41, 0.7);
            box-shadow: 0 10px 40px -10px rgba(124, 58, 237, 0.1);
            transform: translateY(-2px);
        }

        .key-unit {
            flex: 1; min-width: 140px;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 14px;
            position: relative; cursor: grab;
            display: flex; flex-direction: column; gap: 6px;
            transition: all 0.2s;
        }
        .key-unit:hover {
            border-color: var(--primary);
            background: rgba(139, 92, 246, 0.05);
        }

        .key-code {
            font-family: 'JetBrains Mono', monospace; font-size: 12px;
            color: var(--primary); text-align: right; direction: ltr;
            letter-spacing: -0.5px;
        }

        /* Utilities */
        .workspace { display: grid; grid-template-columns: 480px 1fr; height: calc(100vh - 70px); overflow: hidden; position: relative; z-index: 10; }
        .editor-zone { display: flex; flex-direction: column; position: relative; background: transparent; }
        .editor-scroll { flex: 1; overflow-y: auto; padding: 40px 80px; }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 4px; height: 4px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(139, 92, 246, 0.3); border-radius: 99px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(139, 92, 246, 0.6); }

        @media (max-width: 1024px) {
            .workspace { grid-template-columns: 1fr; }
            .preview-zone { display: none; }
            .editor-scroll { padding: 30px; }
        }
    </style>
</head>
<body>

    <!-- Header -->
    <header class="cyber-header">
        <div class="flex items-center gap-3">
            <div class="logo-box">
                <i class="fa-solid fa-code text-white"></i>
            </div>
            <div>
                <h1 class="text-white font-bold text-lg tracking-tight">MirzaBot <span class="text-xs text-gray-400 font-normal ml-1">Studio</span></h1>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <a href="index.php" class="btn-glass">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
                <span class="hidden sm:block">خروج</span>
            </a>
            <a href="keyboard.php?action=reaset" onclick="return confirm('آیا از بازنشانی کامل تنظیمات اطمینان دارید؟')" class="btn-glass" style="color: #f87171; border-color: rgba(248, 113, 113, 0.2);">
                <i class="fa-solid fa-rotate-right"></i>
            </a>
            <button onclick="App.save()" id="btn-save" class="btn-neon" disabled>
                <i class="fa-regular fa-floppy-disk"></i>
                <span>ذخیره تغییرات</span>
            </button>
        </div>
    </header>

    <!-- Main Workspace -->
    <div class="workspace">
        
        <!-- Preview Panel -->
        <div class="preview-zone">
            <div class="phone-frame animate__animated animate__fadeInLeft">
                <div class="island"></div>
                
                <div style="background: #1c1c1e; padding: 50px 20px 15px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #2c2c2e;">
                    <i class="fa-solid fa-arrow-right text-gray-400"></i>
                    <div class="flex-1">
                        <div class="text-white font-bold text-sm">Mirza Bot</div>
                        <div class="text-blue-400 text-xs">bot</div>
                    </div>
                    <i class="fa-solid fa-ellipsis-vertical text-gray-400"></i>
                </div>

                <div style="flex: 1; background: #000; display: flex; flex-direction: column; justify-content: flex-end; padding-bottom: 10px;">
                    <div style="background: linear-gradient(135deg, #2b5278, #244263); color: white; padding: 10px 14px; border-radius: 18px; border-top-left-radius: 4px; max-width: 85%; margin: 0 15px 12px; font-size: 14px; box-shadow: 0 2px 5px rgba(0,0,0,0.3);">
                        منوی ربات به صورت زنده در حال ویرایش است. 👇
                    </div>
                </div>

                <div id="preview-render" class="tg-kb-wrapper flex flex-col justify-end">
                    <!-- Buttons Rendered via JS -->
                </div>
            </div>
        </div>

        <!-- Editor Panel -->
        <div class="editor-zone">
            <div class="editor-scroll">
                <div id="editor-render" class="max-w-5xl mx-auto pb-8">
                    <!-- Rows Rendered via JS -->
                </div>
                
                <div class="max-w-5xl mx-auto pb-24">
                    <div onclick="App.addRow()" class="w-full p-5 mt-8 border-2 border-dashed border-[#7c3aed30] rounded-2xl text-gray-400 font-semibold cursor-pointer hover:border-[#8b5cf6] hover:text-[#8b5cf6] hover:bg-[#8b5cf60d] transition-all flex justify-center items-center gap-2">
                        <i class="fa-solid fa-plus text-lg"></i>
                        افزودن سطر جدید
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Application Logic -->
    <script>
        /**
         * MirzaBot Keyboard Studio
         * Organized Object-Oriented JS Structure
         */
        const App = {
            // Configuration & Data
            data: {
                keyboard: <?php echo $currentKeyboardJSON ?: '[]'; ?>,
                initialSnapshot: '',
                translations: {
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

            // DOM Elements
            dom: {
                editor: document.getElementById('editor-render'),
                preview: document.getElementById('preview-render'),
                saveBtn: document.getElementById('btn-save')
            },

            // Initialization
            init() {
                if (!Array.isArray(this.data.keyboard)) this.data.keyboard = [];
                this.data.initialSnapshot = JSON.stringify(this.data.keyboard);
                
                this.render();
                
                // Initialize SweetAlert Theme
                this.swal = Swal.mixin({
                    background: '#0f0b29',
                    color: '#e2e8f0',
                    confirmButtonColor: '#8b5cf6',
                    cancelButtonColor: '#ef4444',
                    customClass: { popup: 'border border-[#7c3aed] border-opacity-20 rounded-2xl' }
                });
            },

            // Rendering Logic
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
                        <div class="flex flex-col items-center justify-center py-32 opacity-20 select-none text-white">
                            <i class="fa-solid fa-layer-group text-7xl mb-6"></i>
                            <p class="text-xl font-light tracking-wide">هیچ دکمه‌ای وجود ندارد</p>
                        </div>`;
                    return;
                }

                this.data.keyboard.forEach((row, rIdx) => {
                    const rowDiv = document.createElement('div');
                    rowDiv.className = 'row-module animate__animated animate__fadeIn';
                    rowDiv.innerHTML = `<div class="absolute left-[-32px] top-1/2 -translate-y-1/2 text-gray-500 cursor-grab p-2 hover:text-white transition drag-indicator"><i class="fa-solid fa-grip-vertical"></i></div>`;

                    row.forEach((btn, bIdx) => {
                        const label = this.data.translations[btn.text] || 'دکمه سفارشی';
                        const keyDiv = document.createElement('div');
                        keyDiv.className = 'key-unit';
                        keyDiv.innerHTML = `
                            <div class="key-code" title="${btn.text}">${btn.text}</div>
                            <div class="text-xs text-gray-500 font-medium truncate">${label}</div>
                            <div class="absolute top-2 left-2 flex gap-1 opacity-0 transition group-hover:opacity-100 unit-actions">
                                <div onclick="App.editKey(${rIdx}, ${bIdx})" class="w-6 h-6 rounded bg-white/5 flex items-center justify-center text-[10px] cursor-pointer hover:bg-[#8b5cf6] hover:text-white transition"><i class="fa-solid fa-pen"></i></div>
                                <div onclick="App.deleteKey(${rIdx}, ${bIdx})" class="w-6 h-6 rounded bg-white/5 flex items-center justify-center text-[10px] cursor-pointer hover:bg-red-500 hover:text-white transition"><i class="fa-solid fa-xmark"></i></div>
                            </div>
                        `;
                        rowDiv.appendChild(keyDiv);
                    });

                    // Add Button in Row
                    if (row.length < 8) {
                        const addBtn = document.createElement('div');
                        addBtn.className = 'w-[45px] border border-dashed border-gray-600 rounded-xl flex items-center justify-center cursor-pointer hover:border-[#8b5cf6] hover:text-[#8b5cf6] hover:bg-[#8b5cf610] transition text-gray-500 opacity-50 hover:opacity-100';
                        addBtn.innerHTML = '<i class="fa-solid fa-plus text-xs"></i>';
                        addBtn.onclick = () => this.addKeyToRow(rIdx);
                        rowDiv.appendChild(addBtn);
                    }

                    // Row Delete
                    if (row.length === 0) {
                        const delStrip = document.createElement('div');
                        delStrip.className = 'w-full text-center text-red-400 text-xs py-2 cursor-pointer hover:bg-red-500/10 rounded border border-dashed border-red-500/20';
                        delStrip.innerHTML = 'حذف سطر خالی';
                        delStrip.onclick = () => this.deleteRow(rIdx);
                        rowDiv.appendChild(delStrip);
                    }

                    editor.appendChild(rowDiv);
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
                        btnDiv.className = 'tg-btn-render flex-1 truncate';
                        // Show translation in preview
                        btnDiv.innerText = this.data.translations[btn.text] || btn.text; 
                        rowDiv.appendChild(btnDiv);
                    });
                    
                    if (row.length > 0) preview.appendChild(rowDiv);
                });
            },

            // Sortable Logic
            initSortable() {
                // Rows
                new Sortable(this.dom.editor, {
                    animation: 250,
                    handle: '.drag-indicator',
                    ghostClass: 'opacity-40',
                    onEnd: (evt) => {
                        const item = this.data.keyboard.splice(evt.oldIndex, 1)[0];
                        this.data.keyboard.splice(evt.newIndex, 0, item);
                        this.render();
                    }
                });

                // Keys
                document.querySelectorAll('.row-module').forEach(el => {
                    new Sortable(el, {
                        group: 'shared',
                        animation: 200,
                        draggable: '.key-unit',
                        ghostClass: 'opacity-40',
                        onEnd: () => this.rebuildData()
                    });
                });
            },

            rebuildData() {
                const newRows = [];
                const domRows = this.dom.editor.querySelectorAll('.row-module');
                
                domRows.forEach(row => {
                    const btns = [];
                    row.querySelectorAll('.key-code').forEach(el => {
                        btns.push({ text: el.innerText });
                    });
                    if (btns.length > 0 || row.querySelector('.fa-plus')) {
                        newRows.push(btns);
                    }
                });
                
                this.data.keyboard = newRows;
                this.render();
            },

            // Actions
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
                setTimeout(() => document.querySelector('.editor-scroll').scrollTo({ top: 9999, behavior: 'smooth' }), 50);
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
                    inputLabel: 'کد متغیر (مثال: text_sell)',
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
                            timer: 3000, background: '#0f0b29', color: '#fff'
                        });
                        Toast.fire({icon: 'success', title: 'تنظیمات با موفقیت ذخیره شد'});
                    }
                })
                .catch(err => {
                    this.checkChanges();
                    this.swal.fire({icon: 'error', title: 'خطا در ارتباط با سرور'});
                });
            }
        };

        // Boot
        App.init();
    </script>
</body>
</html>