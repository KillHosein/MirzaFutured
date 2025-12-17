<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/../function.php';

// --- Authentication Check ---
$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
$query->execute();
$result = $query->fetch(PDO::FETCH_ASSOC);

// استعلام فاکتورها (جهت جلوگیری از خطا اگر در هدر استفاده می‌شود)
$query = $pdo->prepare("SELECT * FROM invoice");
$query->execute();
$listinvoice = $query->fetchAll();

if( !isset($_SESSION["user"]) || !$result ){
    header('Location: login.php');
    exit;
}

// --- PHP Logic: Handle Save & Reset ---
$inputData = json_decode(file_get_contents("php://input"), true);
$method = $_SERVER['REQUEST_METHOD'];

// Save Logic
if($method == "POST" && is_array($inputData)){
    $keyboardStruct = ['keyboard' => $inputData];
    update("setting", "keyboardmain", json_encode($keyboardStruct), null, null);
    
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'timestamp' => time()]);
    exit;
}

// Reset Logic
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

// --- Fetch Current Data ---
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
         // Default fallback
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
} catch (Exception $e) { $currentKeyboardJSON = '[]'; }
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>مدیریت کیبورد | پنل حرفه‌ای</title>
    
    <!-- Libraries -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <style>
        /* --- Modern Dark Theme Variables --- */
        :root {
            --bg-main: #09090b;       /* Zinc 950 */
            --bg-panel: #18181b;      /* Zinc 900 */
            --bg-card: #27272a;       /* Zinc 800 */
            --border: rgba(255, 255, 255, 0.08);
            --accent: #6366f1;        /* Indigo 500 */
            --text-primary: #f4f4f5;
            --text-secondary: #a1a1aa;
            --danger: #ef4444;
        }

        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: var(--bg-main);
            color: var(--text-primary);
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            background-image: linear-gradient(var(--border) 1px, transparent 1px),
            linear-gradient(90deg, var(--border) 1px, transparent 1px);
            background-size: 30px 30px;
        }

        /* --- Scrollbar --- */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #3f3f46; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #52525b; }

        /* --- Header --- */
        .app-header {
            height: 70px;
            background: rgba(9, 9, 11, 0.8);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            z-index: 50;
        }

        .action-btn {
            height: 40px; padding: 0 20px; border-radius: 10px;
            font-size: 13px; font-weight: 600; cursor: pointer;
            transition: all 0.2s;
            display: flex; align-items: center; gap: 8px; border: 1px solid var(--border);
            background: rgba(255, 255, 255, 0.03); color: var(--text-secondary);
        }
        .action-btn:hover { background: rgba(255, 255, 255, 0.08); color: white; }
        
        .btn-primary {
            background: var(--accent); color: white; border: none;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }
        .btn-primary:hover { transform: translateY(-2px); background: #4f46e5; }
        .btn-primary:disabled { background: #3f3f46; color: var(--text-secondary); cursor: not-allowed; transform: none; box-shadow: none; }

        /* --- Workspace --- */
        .workspace { display: flex; flex: 1; overflow: hidden; }

        /* --- Preview (Left) --- */
        .preview-pane {
            width: 460px;
            background: rgba(0, 0, 0, 0.2);
            border-left: 1px solid var(--border);
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            position: relative;
            backdrop-filter: blur(5px);
        }
        
        .device-wrapper {
            position: relative; width: 360px; height: 720px;
            background: #000; border-radius: 50px;
            box-shadow: 0 0 0 12px #1f1f1f, 0 40px 80px -20px rgba(0,0,0,0.8);
            overflow: hidden; display: flex; flex-direction: column;
        }

        .dynamic-island {
            position: absolute; top: 11px; left: 50%; transform: translateX(-50%);
            width: 120px; height: 35px; background: #000; border-radius: 20px; z-index: 20;
        }

        .tg-ui-header {
            padding: 50px 20px 15px; background: #1c1c1e;
            display: flex; align-items: center; gap: 12px;
            border-bottom: 1px solid #2c2c2e; z-index: 10;
        }

        .tg-ui-body {
            flex: 1; background: #000;
            background-image: url("data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M20 20.5V18H0v-2h20v-2H0v-2h20v-2H0V8h20V6H0V4h20V2H0V0h21.5v21.5h-1.5z' fill='%231c1c1e' fill-opacity='0.4' fill-rule='evenodd'/%3E%3C/svg%3E");
            display: flex; flex-direction: column; justify-content: flex-end; padding-bottom: 10px;
        }

        .tg-keyboard-container {
            background: #1c1c1e; padding: 6px; min-height: 200px;
            box-shadow: 0 -10px 40px rgba(0,0,0,0.5);
        }

        .tg-key {
            background: #2c2c2e; color: #fff; border-radius: 6px;
            padding: 12px 5px; font-size: 13px; text-align: center;
            box-shadow: 0 1px 0 rgba(0,0,0,0.4);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            display: flex; align-items: center; justify-content: center;
            border-top: 1px solid rgba(255,255,255,0.05);
        }

        /* --- Editor (Right) --- */
        .editor-pane { flex: 1; display: flex; flex-direction: column; background: transparent; }
        .editor-content { flex: 1; overflow-y: auto; padding: 40px 60px; }

        .row-container {
            background: rgba(39, 39, 42, 0.6);
            border: 1px solid var(--border); border-radius: 16px;
            padding: 16px; margin-bottom: 20px;
            display: flex; flex-wrap: wrap; gap: 12px;
            position: relative; transition: all 0.2s ease;
            backdrop-filter: blur(8px);
        }
        .row-container:hover {
            border-color: rgba(255,255,255,0.15); background: rgba(39, 39, 42, 0.9);
            transform: translateY(-2px); box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .row-handle {
            position: absolute; left: -30px; top: 50%; transform: translateY(-50%);
            color: var(--text-secondary); cursor: grab; padding: 10px; opacity: 0; transition: 0.2s;
        }
        .row-container:hover .row-handle { opacity: 1; left: -35px; }

        .btn-card {
            flex: 1; min-width: 140px; background: #18181b;
            border: 1px solid var(--border); border-radius: 12px;
            padding: 12px 16px; display: flex; flex-direction: column; gap: 4px;
            position: relative; cursor: grab; transition: all 0.2s;
        }
        .btn-card:hover { border-color: var(--accent); background: #202023; }
        
        .btn-code {
            font-family: monospace; font-size: 13px; color: var(--accent);
            direction: ltr; text-align: right;
        }
        .btn-label { font-size: 12px; color: var(--text-secondary); }

        .btn-controls {
            position: absolute; top: 8px; left: 8px; display: flex; gap: 6px; opacity: 0; transition: 0.2s;
        }
        .btn-card:hover .btn-controls { opacity: 1; }
        
        .mini-act {
            width: 24px; height: 24px; border-radius: 6px; background: rgba(255,255,255,0.1); color: white;
            display: flex; align-items: center; justify-content: center; font-size: 10px; cursor: pointer;
        }
        .mini-act:hover { background: var(--accent); }
        .mini-act.danger:hover { background: var(--danger); }

        .floating-add {
            margin-top: 20px; padding: 20px; border: 2px dashed var(--border); border-radius: 16px;
            display: flex; align-items: center; justify-content: center; gap: 10px;
            color: var(--text-secondary); font-weight: 600; cursor: pointer; transition: all 0.2s;
        }
        .floating-add:hover { border-color: var(--accent); color: white; background: rgba(99, 102, 241, 0.05); }

        @media (max-width: 1024px) { .preview-pane { display: none; } .editor-content { padding: 20px; } }
    </style>
</head>
<body>

    <header class="app-header">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-indigo-600 rounded-xl flex items-center justify-center shadow-lg shadow-indigo-500/30">
                <i class="fa-solid fa-keyboard text-white"></i>
            </div>
            <div>
                <h1 class="text-white font-bold text-lg">MirzaBot <span class="text-xs opacity-50 font-normal">Keyboard</span></h1>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <a href="index.php" class="action-btn">
                <i class="fa-solid fa-chevron-right text-xs"></i> بازگشت
            </a>
            <a href="keyboard.php?action=reaset" onclick="return confirm('آیا مطمئن هستید؟')" class="action-btn" style="color: var(--danger); border-color: rgba(239,68,68,0.3);">
                <i class="fa-solid fa-rotate-right"></i> ریست
            </a>
            <button id="btn-save" onclick="saveKeyboard()" class="action-btn btn-primary" disabled>
                <i class="fa-solid fa-floppy-disk"></i> ذخیره تغییرات
            </button>
        </div>
    </header>

    <div class="workspace">
        
        <!-- Live Preview (Left) -->
        <div class="preview-pane">
            <div class="absolute top-8 left-8 text-[10px] text-gray-500 font-bold uppercase tracking-widest">Live Preview</div>
            
            <div class="device-wrapper animate__animated animate__fadeInUp">
                <div class="dynamic-island"></div>
                
                <div class="tg-ui-header">
                    <i class="fa-solid fa-arrow-right text-white opacity-70"></i>
                    <div class="flex-1">
                        <div class="text-white font-bold text-sm">Mirza Bot</div>
                        <div class="text-[#58a6ff] text-xs">bot</div>
                    </div>
                    <i class="fa-solid fa-ellipsis-vertical text-white opacity-70"></i>
                </div>

                <div class="tg-ui-body">
                    <div class="bg-[#2c2c2e] text-white text-[13px] px-4 py-3 rounded-2xl rounded-tl-sm ml-3 mb-4 max-w-[85%]">
                        سلام! منوی ربات به شکل زیر است. 👇
                    </div>
                </div>

                <div id="preview-render" class="tg-keyboard-container flex flex-col justify-end"></div>
            </div>
        </div>

        <!-- Editor (Right) -->
        <div class="editor-pane">
            <div class="editor-content">
                <div id="editor-render" class="max-w-5xl mx-auto pb-8"></div>
                
                <div class="max-w-5xl mx-auto pb-20">
                    <div onclick="addRow()" class="floating-add">
                        <i class="fa-solid fa-plus-circle text-xl"></i> افزودن سطر جدید
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Application Logic -->
    <script>
        // دیکشنری ترجمه برای نمایش فارسی در پیش‌نمایش و کارت‌ها
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

        // State Initialization
        let keyboardData = <?php echo $currentKeyboardJSON ?: '[]'; ?>;
        if (!Array.isArray(keyboardData)) keyboardData = [];
        let initialSnapshot = JSON.stringify(keyboardData);

        const editorEl = document.getElementById('editor-render');
        const previewEl = document.getElementById('preview-render');
        const saveBtn = document.getElementById('btn-save');

        // SweetAlert Config
        const SwalPro = Swal.mixin({
            background: '#18181b',
            color: '#f4f4f5',
            confirmButtonColor: '#6366f1',
            cancelButtonColor: '#ef4444',
            customClass: { popup: 'rounded-2xl border border-[#3f3f46]' }
        });

        // --- Core Functions ---

        function render() {
            renderEditor();
            renderPreview();
            checkDirty();
        }

        function renderEditor() {
            editorEl.innerHTML = '';
            
            if(keyboardData.length === 0) {
                editorEl.innerHTML = `
                <div class="flex flex-col items-center justify-center py-20 opacity-40">
                    <i class="fa-solid fa-layer-group text-6xl mb-4"></i>
                    <p>هیچ دکمه‌ای تعریف نشده است.</p>
                </div>`;
            }

            keyboardData.forEach((row, rIdx) => {
                const rowDiv = document.createElement('div');
                rowDiv.className = 'row-container animate__animated animate__fadeIn';
                
                // Drag Handle
                rowDiv.innerHTML += `<div class="row-handle"><i class="fa-solid fa-grip-vertical text-xl"></i></div>`;

                // Render Buttons
                row.forEach((btn, bIdx) => {
                    const label = translations[btn.text] || 'دکمه سفارشی';
                    const btnCard = document.createElement('div');
                    btnCard.className = 'btn-card';
                    btnCard.innerHTML = `
                        <div class="btn-code" title="${btn.text}">${btn.text}</div>
                        <div class="btn-label">${label}</div>
                        <div class="btn-controls">
                            <div class="mini-act" onclick="editKey(${rIdx}, ${bIndex})"><i class="fa-solid fa-pen"></i></div>
                            <div class="mini-act danger" onclick="deleteKey(${rIdx}, ${bIndex})"><i class="fa-solid fa-times"></i></div>
                        </div>
                    `;
                    rowDiv.appendChild(btnCard);
                });

                // Add button to row
                if(row.length < 8) {
                    const addPlace = document.createElement('div');
                    addPlace.className = 'w-[50px] border border-dashed border-gray-700 rounded-xl flex items-center justify-center cursor-pointer hover:border-indigo-500 hover:text-indigo-500 transition text-gray-500';
                    addPlace.innerHTML = '<i class="fa-solid fa-plus"></i>';
                    addPlace.onclick = () => addKeyToRow(rIdx);
                    rowDiv.appendChild(addPlace);
                }

                // Delete Row Logic
                if(row.length === 0) {
                    const delRow = document.createElement('div');
                    delRow.className = 'w-full text-center text-xs text-red-400 py-2 border border-dashed border-red-500/20 rounded cursor-pointer hover:bg-red-500/10 transition';
                    delRow.innerHTML = 'حذف سطر خالی';
                    delRow.onclick = () => deleteRow(rIdx);
                    rowDiv.appendChild(delRow);
                }

                editorEl.appendChild(rowDiv);
            });

            initSortable();
        }

        function renderPreview() {
            previewEl.innerHTML = '';
            keyboardData.forEach(row => {
                const rDiv = document.createElement('div');
                rDiv.className = 'flex w-full gap-2 mb-2';
                
                row.forEach(btn => {
                    const bDiv = document.createElement('div');
                    bDiv.className = 'tg-key flex-1 truncate';
                    // Show translated text in preview
                    bDiv.innerText = translations[btn.text] || btn.text; 
                    rDiv.appendChild(bDiv);
                });
                
                if(row.length > 0) previewEl.appendChild(rDiv);
            });
        }

        function initSortable() {
            // Sort Rows
            new Sortable(editorEl, {
                animation: 200, handle: '.row-handle', ghostClass: 'opacity-50',
                onEnd: (evt) => {
                    const item = keyboardData.splice(evt.oldIndex, 1)[0];
                    keyboardData.splice(evt.newIndex, 0, item);
                    render();
                }
            });

            // Sort Buttons
            document.querySelectorAll('.row-container').forEach(el => {
                new Sortable(el, {
                    group: 'shared', animation: 200, draggable: '.btn-card', ghostClass: 'opacity-50',
                    onEnd: () => rebuildData()
                });
            });
        }

        function rebuildData() {
            const newData = [];
            const rows = editorEl.querySelectorAll('.row-container');
            rows.forEach(row => {
                const btns = [];
                row.querySelectorAll('.btn-code').forEach(c => btns.push({text: c.innerText}));
                if(btns.length > 0 || row.querySelector('.fa-plus')) newData.push(btns);
            });
            keyboardData = newData;
            render();
        }

        // --- Interaction Logic ---

        function checkDirty() {
            const current = JSON.stringify(keyboardData);
            if(current !== initialSnapshot) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> ذخیره تغییرات *';
            } else {
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<i class="fa-solid fa-check"></i> تغییرات ذخیره شد';
            }
        }

        function addRow() {
            keyboardData.push([{text: 'text_new'}]);
            render();
            setTimeout(() => document.querySelector('.editor-content').scrollTo({ top: 9999, behavior: 'smooth' }), 50);
        }

        function deleteRow(idx) {
            keyboardData.splice(idx, 1);
            render();
        }

        async function addKeyToRow(rIdx) {
            const { value: text } = await SwalPro.fire({
                title: 'افزودن دکمه جدید',
                input: 'text',
                inputValue: 'text_new',
                inputLabel: 'نام متغیر (انگلیسی)',
                showCancelButton: true,
                confirmButtonText: 'افزودن'
            });
            if(text) {
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
                title: 'ویرایش کد دکمه',
                input: 'text',
                inputValue: current,
                showCancelButton: true,
                confirmButtonText: 'بروزرسانی'
            });
            if(text) {
                keyboardData[rIdx][bIdx].text = text;
                render();
            }
        }

        function saveKeyboard() {
            const oldHtml = saveBtn.innerHTML;
            saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> ...';
            saveBtn.disabled = true;

            fetch('keyboard.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(keyboardData)
            })
            .then(r => r.json())
            .then(res => {
                if(res.status === 'success') {
                    initialSnapshot = JSON.stringify(keyboardData);
                    checkDirty();
                    const Toast = Swal.mixin({
                        toast: true, position: 'top-end', showConfirmButton: false, 
                        timer: 3000, background: '#18181b', color: '#fff'
                    });
                    Toast.fire({icon: 'success', title: 'تغییرات ذخیره شد'});
                }
            })
            .catch(() => {
                saveBtn.innerHTML = oldHtml;
                saveBtn.disabled = false;
                SwalPro.fire({icon: 'error', title: 'خطا در ارتباط'});
            });
        }

        // Init
        render();

    </script>
</body>
</html>