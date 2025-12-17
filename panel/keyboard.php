<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/../function.php';

// --- بررسی دسترسی ---
$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
$query->execute();
$result = $query->fetch(PDO::FETCH_ASSOC);

// جلوگیری از خطا در صورت استفاده در هدر
$query = $pdo->prepare("SELECT * FROM invoice");
$query->execute();
$listinvoice = $query->fetchAll();

if( !isset($_SESSION["user"]) || !$result ){
    header('Location: login.php');
    exit;
}

// --- مدیریت درخواست‌ها ---
$inputJSON = file_get_contents("php://input");
$inputData = json_decode($inputJSON, true);
$method = $_SERVER['REQUEST_METHOD'];

// ذخیره‌سازی
if($method == "POST" && !empty($inputData)){
    $keyboardStruct = ['keyboard' => $inputData];
    update("setting", "keyboardmain", json_encode($keyboardStruct), null, null);
    
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success']);
    exit;
}

// ریست پیش‌فرض
if(isset($_GET['action']) && $_GET['action'] == "reaset"){
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

// --- دریافت اطلاعات ---
$currentKeyboardJSON = '[]';
try {
    $stmt = $pdo->prepare("SELECT * FROM setting LIMIT 1");
    $stmt->execute();
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($settings && isset($settings['keyboardmain'])) {
        $decoded = json_decode($settings['keyboardmain'], true);
        if(isset($decoded['keyboard'])) {
            $currentKeyboardJSON = json_encode($decoded['keyboard']);
        }
    } else {
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
    
    <!-- کتابخانه‌ها -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <style>
        /* --- Next-Gen Theme Variables --- */
        :root {
            --bg-deep: #030014;
            --bg-surface: #0f0b29;
            --bg-card: rgba(30, 27, 75, 0.4);
            --border-color: rgba(124, 58, 237, 0.15);
            --border-highlight: rgba(124, 58, 237, 0.5);
            
            --primary: #8b5cf6; /* Violet */
            --primary-glow: rgba(139, 92, 246, 0.5);
            --secondary: #ec4899; /* Pink */
            
            --text-main: #e2e8f0;
            --text-muted: #94a3b8;
            
            --glass: rgba(15, 11, 41, 0.7);
        }

        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: var(--bg-deep);
            color: var(--text-main);
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            /* Cyberpunk Grid Background */
            background-image: 
                linear-gradient(rgba(139, 92, 246, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(139, 92, 246, 0.03) 1px, transparent 1px);
            background-size: 50px 50px;
            background-position: center;
        }
        
        body::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; height: 100%;
            background: radial-gradient(circle at 50% 0%, rgba(88, 28, 135, 0.15), transparent 60%);
            pointer-events: none;
            z-index: 0;
        }

        /* --- Scrollbar Styling --- */
        ::-webkit-scrollbar { width: 4px; height: 4px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(139, 92, 246, 0.3); border-radius: 99px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(139, 92, 246, 0.6); }

        /* --- Header --- */
        .cyber-header {
            height: 70px;
            background: var(--glass);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 40px;
            z-index: 50;
            position: relative;
        }

        .brand-logo {
            display: flex; align-items: center; gap: 14px;
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
        .logo-box i { position: relative; z-index: 2; color: white; font-size: 18px; }
        
        .brand-text h1 {
            font-size: 18px; font-weight: 800; letter-spacing: -0.5px;
            background: linear-gradient(to right, #fff, #a5b4fc);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }

        .header-actions { display: flex; gap: 12px; align-items: center; }

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
        .btn-neon::before {
            content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: 0.5s;
        }
        .btn-neon:hover {
            box-shadow: 0 0 30px var(--primary-glow); transform: translateY(-1px);
        }
        .btn-neon:hover::before { left: 100%; }
        .btn-neon:disabled { background: #333; box-shadow: none; cursor: not-allowed; opacity: 0.7; }

        /* --- Workspace --- */
        .workspace {
            display: grid;
            grid-template-columns: 480px 1fr;
            height: calc(100vh - 70px);
            overflow: hidden;
            position: relative; z-index: 10;
        }

        /* --- Preview (Left) --- */
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
            box-shadow: 
                0 0 0 6px #333, /* Bezel */
                0 0 0 9px #1a1a1a, /* Frame */
                0 20px 60px -10px rgba(0,0,0,0.8);
            overflow: hidden;
            display: flex; flex-direction: column;
            position: relative;
            transform: scale(0.85);
            border: 1px solid #333;
        }
        
        .island {
            position: absolute; top: 12px; left: 50%; transform: translateX(-50%);
            width: 126px; height: 37px; background: #000; border-radius: 20px; z-index: 20;
            transition: all 0.3s ease;
        }

        .tg-header {
            background: #1c1c1e; padding: 50px 20px 15px;
            display: flex; align-items: center; gap: 12px;
            border-bottom: 1px solid #2c2c2e; z-index: 10;
        }
        
        .tg-body {
            flex: 1; background: #000;
            /* Authentic Dark Pattern */
            background-image: url("data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M20 20.5V18H0v-2h20v-2H0v-2h20v-2H0V8h20V6H0V4h20V2H0V0h21.5v21.5h-1.5z' fill='%231c1c1e' fill-opacity='0.6' fill-rule='evenodd'/%3E%3C/svg%3E");
            display: flex; flex-direction: column; justify-content: flex-end; padding-bottom: 10px;
        }

        .tg-message {
            background: linear-gradient(135deg, #2b5278, #244263);
            color: white; padding: 10px 14px;
            border-radius: 18px; border-top-left-radius: 4px;
            max-width: 85%; margin: 0 15px 12px; font-size: 14px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
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

        /* --- Editor (Right) --- */
        .editor-zone {
            display: flex; flex-direction: column;
            position: relative; background: transparent;
        }

        .editor-scroll {
            flex: 1; overflow-y: auto; padding: 40px 80px;
        }

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

        .drag-indicator {
            position: absolute; left: -32px; top: 50%; transform: translateY(-50%);
            color: var(--text-muted); cursor: grab; padding: 8px;
            opacity: 0; transition: 0.2s; font-size: 18px;
        }
        .row-module:hover .drag-indicator { opacity: 1; left: -36px; }

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
        .key-unit:active { transform: scale(0.98); }

        .key-code {
            font-family: 'JetBrains Mono', monospace; font-size: 12px;
            color: var(--primary); text-align: right; direction: ltr;
            letter-spacing: -0.5px;
        }
        .key-name {
            font-size: 12px; color: var(--text-muted);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            font-weight: 500;
        }

        .unit-actions {
            position: absolute; top: 8px; left: 8px;
            display: flex; gap: 6px; opacity: 0; transition: 0.2s;
        }
        .key-unit:hover .unit-actions { opacity: 1; }

        .action-dot {
            width: 24px; height: 24px; border-radius: 8px;
            background: rgba(255,255,255,0.05); color: var(--text-main);
            display: flex; align-items: center; justify-content: center;
            font-size: 11px; cursor: pointer; backdrop-filter: blur(4px);
            transition: 0.2s;
        }
        .action-dot:hover { background: var(--primary); color: white; }
        .action-dot.del:hover { background: #ef4444; }

        /* Add Button */
        .add-unit {
            width: 45px; border: 1px dashed var(--text-muted);
            border-radius: 12px; display: flex; align-items: center; justify-content: center;
            color: var(--text-muted); cursor: pointer; transition: 0.2s; opacity: 0.5;
        }
        .add-unit:hover {
            border-color: var(--primary); color: var(--primary); opacity: 1; background: rgba(139, 92, 246, 0.1);
        }

        /* Main Add Button */
        .add-row-fab {
            width: 100%; padding: 20px; margin-top: 30px;
            border: 2px dashed var(--border-color); border-radius: 16px;
            color: var(--text-muted); font-weight: 600; font-size: 14px;
            display: flex; align-items: center; justify-content: center; gap: 10px;
            cursor: pointer; transition: 0.2s;
        }
        .add-row-fab:hover {
            border-color: var(--primary); color: var(--primary);
            background: rgba(139, 92, 246, 0.05); transform: translateY(-2px);
        }

        /* Delete Row Strip */
        .delete-strip {
            width: 100%; text-align: center; font-size: 11px; color: #ef4444;
            padding: 8px; border-radius: 8px; margin-top: 8px;
            cursor: pointer; transition: 0.2s; opacity: 0; height: 0; overflow: hidden;
        }
        .row-module:hover .delete-strip { opacity: 0.6; height: auto; margin-top: 8px; }
        .delete-strip:hover { opacity: 1; background: rgba(239, 68, 68, 0.1); }

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
        <div class="brand-logo">
            <div class="logo-box">
                <i class="fa-solid fa-code"></i>
            </div>
            <div class="brand-text">
                <h1>MirzaBot <span style="font-weight: 300; opacity: 0.7;">Studio</span></h1>
            </div>
        </div>

        <div class="header-actions">
            <a href="index.php" class="btn-glass">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
                <span class="hidden sm:block">خروج</span>
            </a>
            <a href="keyboard.php?action=reaset" onclick="return confirm('آیا از بازنشانی کامل تنظیمات اطمینان دارید؟')" class="btn-glass" style="color: #f87171; border-color: rgba(248, 113, 113, 0.2);">
                <i class="fa-solid fa-rotate-right"></i>
            </a>
            <button onclick="saveKeyboard()" id="btn-save" class="btn-neon" disabled>
                <i class="fa-regular fa-floppy-disk"></i>
                <span>ذخیره تغییرات</span>
            </button>
        </div>
    </header>

    <!-- Workspace -->
    <div class="workspace">
        
        <!-- Left: Preview -->
        <div class="preview-zone">
            <div class="phone-frame animate__animated animate__fadeInLeft">
                <div class="island"></div>
                
                <div class="tg-header">
                    <i class="fa-solid fa-arrow-right text-gray-400"></i>
                    <div class="flex-1">
                        <div class="text-white font-bold text-sm">Mirza Bot</div>
                        <div class="text-blue-400 text-xs">bot</div>
                    </div>
                    <i class="fa-solid fa-ellipsis-vertical text-gray-400"></i>
                </div>

                <div class="tg-body">
                    <div class="tg-message">
                        منوی ربات به صورت زنده در حال ویرایش است. 👇
                    </div>
                </div>

                <div id="preview-render" class="tg-kb-wrapper flex flex-col justify-end">
                    <!-- Buttons Render Here -->
                </div>
            </div>
        </div>

        <!-- Right: Editor -->
        <div class="editor-zone">
            <div class="editor-scroll">
                <div id="editor-render" class="max-w-5xl mx-auto pb-8">
                    <!-- Rows Render Here -->
                </div>
                
                <div class="max-w-5xl mx-auto pb-24">
                    <div onclick="addRow()" class="add-row-fab">
                        <i class="fa-solid fa-plus text-lg"></i>
                        افزودن سطر جدید
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Application Logic -->
    <script>
        // دیکشنری هوشمند برای ترجمه کدهای فنی
        const translations = {
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
        };

        // بارگذاری داده‌ها
        let keyboardData = <?php echo $currentKeyboardJSON ?: '[]'; ?>;
        if (!Array.isArray(keyboardData)) keyboardData = [];
        
        let initialSnapshot = JSON.stringify(keyboardData);

        // DOM Elements
        const editorEl = document.getElementById('editor-render');
        const previewEl = document.getElementById('preview-render');
        const saveBtn = document.getElementById('btn-save');

        // Config SweetAlert Pro
        const SwalPro = Swal.mixin({
            background: '#0f0b29',
            color: '#e2e8f0',
            confirmButtonColor: '#8b5cf6',
            cancelButtonColor: '#ef4444',
            customClass: { popup: 'border border-[#7c3aed] border-opacity-20 rounded-2xl' }
        });

        // --- Core Functions ---

        function render() {
            renderEditor();
            renderPreview();
            checkChanges();
        }

        function renderEditor() {
            editorEl.innerHTML = '';
            
            if (keyboardData.length === 0) {
                editorEl.innerHTML = `
                    <div class="flex flex-col items-center justify-center py-32 opacity-20 select-none text-white">
                        <i class="fa-solid fa-layer-group text-7xl mb-6"></i>
                        <p class="text-xl font-light tracking-wide">هیچ دکمه‌ای وجود ندارد</p>
                    </div>`;
            }

            keyboardData.forEach((row, rIdx) => {
                const rowDiv = document.createElement('div');
                rowDiv.className = 'row-module animate__animated animate__fadeIn';
                
                // Drag Indicator
                rowDiv.innerHTML += `<div class="drag-indicator"><i class="fa-solid fa-grip-vertical"></i></div>`;

                row.forEach((btn, bIdx) => {
                    const label = translations[btn.text] || 'دکمه سفارشی';
                    const keyDiv = document.createElement('div');
                    keyDiv.className = 'key-unit';
                    keyDiv.innerHTML = `
                        <div class="key-code" title="${btn.text}">${btn.text}</div>
                        <div class="key-name">${label}</div>
                        <div class="unit-actions">
                            <div class="action-dot" onclick="editKey(${rIdx}, ${bIdx})"><i class="fa-solid fa-pen"></i></div>
                            <div class="action-dot del" onclick="deleteKey(${rIdx}, ${bIdx})"><i class="fa-solid fa-xmark"></i></div>
                        </div>
                    `;
                    rowDiv.appendChild(keyDiv);
                });

                // Add Button inside Row
                if (row.length < 8) {
                    const addBtn = document.createElement('div');
                    addBtn.className = 'add-unit';
                    addBtn.innerHTML = '<i class="fa-solid fa-plus text-xs"></i>';
                    addBtn.onclick = () => addKeyToRow(rIdx);
                    rowDiv.appendChild(addBtn);
                }

                // Delete Row Strip
                if (row.length === 0) {
                    const delStrip = document.createElement('div');
                    delStrip.className = 'w-full text-center text-red-400 text-xs py-2 cursor-pointer hover:bg-red-500/10 rounded border border-dashed border-red-500/20';
                    delStrip.innerHTML = 'حذف سطر خالی';
                    delStrip.onclick = () => deleteRow(rIdx);
                    rowDiv.appendChild(delStrip);
                } else {
                    // Hidden delete option for populated rows (optional UX)
                    // For now, simpler is better.
                }

                editorEl.appendChild(rowDiv);
            });

            initSortable();
        }

        function renderPreview() {
            previewEl.innerHTML = '';
            keyboardData.forEach(row => {
                const rowDiv = document.createElement('div');
                rowDiv.className = 'flex w-full gap-1 mb-1';
                
                row.forEach(btn => {
                    const btnDiv = document.createElement('div');
                    btnDiv.className = 'tg-btn-render flex-1 truncate';
                    btnDiv.innerText = translations[btn.text] || btn.text; 
                    rowDiv.appendChild(btnDiv);
                });
                
                if(row.length > 0) previewEl.appendChild(rowDiv);
            });
        }

        function initSortable() {
            // Rows
            new Sortable(editorEl, {
                animation: 250, handle: '.drag-indicator', ghostClass: 'opacity-40',
                onEnd: (evt) => {
                    const item = keyboardData.splice(evt.oldIndex, 1)[0];
                    keyboardData.splice(evt.newIndex, 0, item);
                    render();
                }
            });

            // Keys
            document.querySelectorAll('.row-module').forEach(el => {
                new Sortable(el, {
                    group: 'shared', animation: 200, draggable: '.key-unit', ghostClass: 'opacity-40',
                    onEnd: () => rebuildData() 
                });
            });
        }

        function rebuildData() {
            const newData = [];
            const rows = editorEl.querySelectorAll('.row-module');
            rows.forEach(row => {
                const btns = [];
                row.querySelectorAll('.key-code').forEach(el => {
                    btns.push({ text: el.innerText });
                });
                if (btns.length > 0 || row.querySelector('.fa-plus')) {
                    newData.push(btns);
                }
            });
            keyboardData = newData;
            render();
        }

        // --- Logic ---

        function checkChanges() {
            const current = JSON.stringify(keyboardData);
            if (current !== initialSnapshot) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fa-solid fa-check"></i> ذخیره تغییرات';
                saveBtn.classList.add('animate-pulse');
            } else {
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<i class="fa-regular fa-floppy-disk"></i> ذخیره شد';
                saveBtn.classList.remove('animate-pulse');
            }
        }

        function addRow() {
            keyboardData.push([{text: 'text_new'}]);
            render();
            setTimeout(() => document.querySelector('.editor-scroll').scrollTo({ top: 9999, behavior: 'smooth' }), 50);
        }

        function deleteRow(idx) {
            keyboardData.splice(idx, 1);
            render();
        }

        async function addKeyToRow(rIdx) {
            const { value: text } = await SwalPro.fire({
                title: 'افزودن دکمه',
                input: 'text',
                inputValue: 'text_new',
                inputLabel: 'کد متغیر (انگلیسی)',
                showCancelButton: true,
                confirmButtonText: 'افزودن'
            });
            if (text) {
                keyboardData[rIdx].push({text});
                render();
            }
        }

        function deleteKey(rIdx, bIdx) {
            keyboardData[rIdx].splice(bIdx, 1);
            render();
        }

        async function editKey(rIdx, bIdx) {
            const current = keyboardData[rIdx][bIdx].text;
            const { value: text } = await SwalPro.fire({
                title: 'ویرایش کد',
                input: 'text',
                inputValue: current,
                showCancelButton: true,
                confirmButtonText: 'تایید'
            });
            if (text) {
                keyboardData[rIdx][bIdx].text = text;
                render();
            }
        }

        function saveKeyboard() {
            const originalText = saveBtn.innerHTML;
            saveBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> ...';
            saveBtn.disabled = true;

            fetch('keyboard.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(keyboardData)
            })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    initialSnapshot = JSON.stringify(keyboardData);
                    checkChanges();
                    const Toast = Swal.mixin({
                        toast: true, position: 'top-end', showConfirmButton: false, 
                        timer: 3000, background: '#0f0b29', color: '#fff'
                    });
                    Toast.fire({icon: 'success', title: 'با موفقیت ذخیره شد'});
                }
            })
            .catch(err => {
                checkChanges();
                SwalPro.fire({icon: 'error', title: 'خطا در ارتباط با سرور'});
            });
        }

        // Start
        render();

    </script>
</body>
</html>