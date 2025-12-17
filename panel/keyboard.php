<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/../function.php';

// احراز هویت ادمین
$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
$query->execute();
$result = $query->fetch(PDO::FETCH_ASSOC);

if( !isset($_SESSION["user"]) || !$result ){
    header('Location: login.php');
    exit;
}

// محاسبه مسیر پایه برای API
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$scriptDirectory = str_replace('\\', '/', dirname($scriptName));
$applicationBasePath = str_replace('\\', '/', dirname($scriptDirectory));
$applicationBasePath = rtrim($applicationBasePath, '/');
if ($applicationBasePath === '/' || $applicationBasePath === '.' || $applicationBasePath === '\\') {
    $applicationBasePath = '';
}

// --- منطق ذخیره‌سازی (AJAX POST) ---
$inputJSON = file_get_contents("php://input");
$inputData = json_decode($inputJSON, true);
$method = $_SERVER['REQUEST_METHOD'];

if($method == "POST" && is_array($inputData)){
    $keyboardStructure = ['keyboard' => $inputData];
    // ذخیره در دیتابیس (با فرض اینکه تابع update شما درست کار می‌کند)
    update("setting", "keyboardmain", json_encode($keyboardStructure, JSON_UNESCAPED_UNICODE), null, null);
    
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'message' => 'کیبورد با موفقیت ذخیره شد']);
    exit;
}

// --- منطق ریست کردن (GET) ---
$action = filter_input(INPUT_GET, 'action');
if($action === "reaset"){
    $defaultKeyboard = '{"keyboard":[[{"text":"text_sell"},{"text":"text_extend"}],[{"text":"text_usertest"},{"text":"text_wheel_luck"}],[{"text":"text_Purchased_services"},{"text":"accountwallet"}],[{"text":"text_affiliates"},{"text":"text_Tariff_list"}],[{"text":"text_support"},{"text":"text_help"}]]}';
    update("setting", "keyboardmain", $defaultKeyboard, null, null);
    header('Location: keyboard.php');
    exit;
}

// --- دریافت اطلاعات فعلی ---
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
        // Fallback default
         $def = json_decode('{"keyboard":[[{"text":"text_sell"},{"text":"text_extend"}],[{"text":"text_usertest"},{"text":"text_wheel_luck"}],[{"text":"text_Purchased_services"},{"text":"accountwallet"}],[{"text":"text_affiliates"},{"text":"text_Tariff_list"}],[{"text":"text_support"},{"text":"text_help"}]]}', true);
         $currentKeyboardJSON = json_encode($def['keyboard']);
    }
} catch (Exception $e) { 
    $currentKeyboardJSON = '[]'; 
}
?>

<!doctype html>
<html lang="fa" dir="rtl" class="dark">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>مدیریت پیشرفته کیبورد | میرزا</title>
    
    <!-- کتابخانه‌ها -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    
    <!-- پیکربندی تم Tailwind -->
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        midnight: {
                            900: '#0f172a',
                            800: '#1e293b',
                            700: '#334155',
                            600: '#475569',
                        },
                        telegram: {
                            bg: '#0f0f0f',
                            header: '#202124',
                            btn: '#2b2b2b',
                            message: '#2b5278'
                        }
                    },
                    fontFamily: {
                        sans: ['Vazirmatn', 'ui-sans-serif', 'system-ui'],
                    }
                }
            }
        }
    </script>

    <style>
        /* استایل‌های اختصاصی و انیمیشن‌ها */
        body {
            background-color: #0f1115; /* Deep Midnight */
            color: #e2e8f0;
            overflow-x: hidden;
        }

        /* الگوی پس‌زمینه تلگرام */
        .chat-pattern {
            background-color: #0f0f0f;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M30 30.5V28H0v-2h30v-2H0v-2h30v-2H0V8h30V6H0V4h30V2H0V0h31.5v31.5h-1.5z' fill='%231a1a1a' fill-opacity='0.5' fill-rule='evenodd'/%3E%3C/svg%3E");
            mask-image: linear-gradient(to bottom, rgba(0,0,0,1) 80%, rgba(0,0,0,0));
        }

        /* دکمه‌های کیبورد */
        .kb-btn {
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            user-select: none;
            border-bottom: 2px solid rgba(0,0,0,0.2);
        }
        .kb-btn:active {
            transform: scale(0.96);
            border-bottom-width: 0;
            margin-top: 2px;
        }
        .kb-btn-hover-effect:hover .btn-actions {
            opacity: 1;
            transform: translateY(0);
        }

        /* ابزارک‌ها */
        .btn-actions {
            opacity: 0;
            transform: translateY(5px);
            transition: all 0.2s ease;
        }

        /* اسکرول‌بار زیبا */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #1e293b; 
        }
        ::-webkit-scrollbar-thumb {
            background: #475569; 
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #64748b; 
        }

        /* انیمیشن پیام */
        @keyframes messagePop {
            0% { opacity: 0; transform: translateY(10px) scale(0.95); }
            100% { opacity: 1; transform: translateY(0) scale(1); }
        }
        .msg-anim {
            animation: messagePop 0.3s ease-out forwards;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col font-sans">

    <!-- Navbar حرفه‌ای -->
    <header class="bg-midnight-800 border-b border-midnight-700 sticky top-0 z-50 shadow-lg backdrop-blur-sm bg-opacity-90">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-600 flex items-center justify-center shadow-lg">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                </div>
                <div>
                    <h1 class="text-lg font-bold text-white tracking-wide">ویرایشگر کیبورد</h1>
                    <p class="text-xs text-slate-400">پنل مدیریت میرزا</p>
                </div>
            </div>
            
            <div class="flex items-center gap-3">
                 <!-- دکمه‌های عملیاتی -->
                 <button onclick="undoChange()" id="undoBtn" class="p-2 text-slate-400 hover:text-white hover:bg-midnight-700 rounded-lg transition disabled:opacity-30" title="بازگشت (Undo)" disabled>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path></svg>
                </button>

                <button onclick="showJSON()" class="hidden md:flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-slate-300 hover:text-white bg-midnight-700 hover:bg-midnight-600 rounded-lg transition border border-midnight-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg>
                    مشاهده JSON
                </button>

                <div class="h-6 w-px bg-midnight-600 mx-2"></div>

                <a href="index.php" class="text-slate-400 hover:text-white text-sm font-medium transition px-2">خروج</a>
            </div>
        </div>
    </header>

    <!-- محیط اصلی -->
    <main class="flex-grow flex items-center justify-center p-4 md:p-8">
        
        <div class="w-full max-w-md bg-black rounded-[40px] shadow-2xl border-[8px] border-midnight-800 overflow-hidden relative flex flex-col h-[800px]">
            
            <!-- نوار وضعیت موبایل (فیک) -->
            <div class="bg-telegram-header h-8 flex items-center justify-between px-6 text-[10px] font-medium text-white select-none pt-2">
                <span>9:41</span>
                <div class="flex gap-1.5">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zm6-4a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zm6-3a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"></path></svg>
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M17.778 8.232c-2.403-1.302-4.11-1.304-6.684-1.938-.95-.235-2.008-.447-3.214.152C5.25 7.79 5.094 9.859 6.48 10.95c.53.418.66.97.228 1.488-.02.023-.27.31-.27.31-1.155 1.346-.99 3.518.735 4.887 2.058 1.63 4.5 1.258 5.753-.162l.27-.31c.365-.414.935-.49 1.393-.162 1.583 1.135 3.738 1.252 5.228.082.903-.708.972-2.115-.178-3.085-.632-.533-1.603-1.077-1.87-1.27z" clip-rule="evenodd"></path></svg>
                </div>
            </div>

            <!-- هدر چت تلگرام -->
            <div class="bg-telegram-header p-3 flex items-center justify-between border-b border-midnight-900 z-10 shadow-md">
                <div class="flex items-center gap-3">
                    <button class="text-slate-300">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    </button>
                    <div class="w-10 h-10 rounded-full bg-indigo-500 flex items-center justify-center text-white font-bold text-lg shadow-sm">
                        M
                    </div>
                    <div class="flex flex-col">
                        <span class="font-bold text-white text-sm">Mirza Bot</span>
                        <span class="text-xs text-blue-400">bot</span>
                    </div>
                </div>
                <div class="text-slate-400">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"></path></svg>
                </div>
            </div>

            <!-- محیط چت (محل نمایش تست دکمه‌ها) -->
            <div id="chat-simulation" class="flex-1 chat-pattern relative p-4 overflow-y-auto flex flex-col gap-3">
                <div class="bg-midnight-800/80 backdrop-blur text-xs text-slate-300 px-3 py-1 rounded-full self-center mb-4 border border-midnight-700">
                    امروز
                </div>
                <!-- پیام خوش‌آمد -->
                <div class="bg-telegram-header p-3 rounded-tr-xl rounded-bl-xl rounded-br-xl max-w-[85%] self-start border border-midnight-700 shadow-sm">
                    <p class="text-sm text-white leading-relaxed">سلام ادمین 👋<br>به ویرایشگر کیبورد خوش آمدید. دکمه‌های پایین را ویرایش کنید و برای تست روی آن‌ها کلیک کنید.</p>
                    <span class="text-[10px] text-slate-400 float-left mt-1 ml-1">10:00</span>
                </div>
            </div>

            <!-- ناحیه کیبورد (Editor Area) -->
            <div class="bg-[#1c1c1d] pb-6 pt-2 border-t border-black shadow-[0_-5px_15px_rgba(0,0,0,0.5)] z-20 relative">
                
                <!-- نوار ابزار کوچک بالای کیبورد -->
                <div class="px-2 mb-2 flex justify-between items-center text-xs text-slate-500">
                    <span>Keyboard Editor</span>
                    <button onclick="resetToDefault()" class="text-red-400 hover:text-red-300 transition">ریست پیشفرض</button>
                </div>

                <!-- کانتینر دراگ و دراپ -->
                <div id="keyboard-container" class="space-y-1 px-1 max-h-[300px] overflow-y-auto">
                    <!-- سطرها اینجا ساخته می‌شوند -->
                </div>

                <!-- دکمه افزودن سطر -->
                <div class="px-2 mt-3">
                    <button onclick="addRow()" class="w-full py-2.5 border border-dashed border-midnight-600 text-slate-400 rounded-lg hover:bg-midnight-800 hover:text-white hover:border-slate-500 transition-all text-sm font-medium flex items-center justify-center gap-2 group">
                        <svg class="w-4 h-4 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        افزودن سطر جدید
                    </button>
                </div>
            </div>

            <!-- دکمه شناور ذخیره (بیرون از گوشی موبایل ولی متصل به پایین صفحه) -->
            <div class="absolute bottom-6 left-0 right-0 flex justify-center z-50 pointer-events-none">
                 <button onclick="saveKeyboard()" class="pointer-events-auto bg-blue-600 hover:bg-blue-500 text-white px-8 py-3 rounded-full shadow-[0_4px_20px_rgba(37,99,235,0.4)] font-bold text-sm flex items-center gap-2 transform transition hover:-translate-y-1 active:scale-95">
                    <span id="save-text">ذخیره تغییرات</span>
                    <svg id="save-icon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    <!-- Loading Spinner -->
                    <svg id="save-spinner" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </button>
            </div>
        </div>

    </main>

    <!-- جاوا اسکریپت -->
    <script>
        // --- وضعیت (State) ---
        let keyboardRows = <?php echo $currentKeyboardJSON ?: '[]'; ?>;
        if (!Array.isArray(keyboardRows)) keyboardRows = [];
        
        let historyStack = []; // برای Undo

        // --- المان‌های DOM ---
        const container = document.getElementById('keyboard-container');
        const chatSim = document.getElementById('chat-simulation');
        const undoBtn = document.getElementById('undoBtn');

        // --- توابع کمکی ---
        function pushToHistory() {
            // ذخیره یک کپی عمیق از وضعیت فعلی
            historyStack.push(JSON.parse(JSON.stringify(keyboardRows)));
            if (historyStack.length > 20) historyStack.shift(); // محدودیت حافظه
            updateUIState();
        }

        function undoChange() {
            if (historyStack.length === 0) return;
            keyboardRows = historyStack.pop();
            updateUIState();
            render();
            showToast('بازگشت به عقب انجام شد', 'info');
        }

        function updateUIState() {
            undoBtn.disabled = historyStack.length === 0;
            undoBtn.classList.toggle('opacity-30', historyStack.length === 0);
        }

        function showToast(title, icon = 'success') {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                background: '#1e293b',
                color: '#fff',
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer)
                    toast.addEventListener('mouseleave', Swal.resumeTimer)
                }
            });
            Toast.fire({ icon: icon, title: title });
        }

        // --- توابع رندرینگ ---
        function render() {
            container.innerHTML = '';
            
            keyboardRows.forEach((row, rowIndex) => {
                const rowDiv = document.createElement('div');
                rowDiv.className = 'flex gap-1 w-full row-container relative group/row';
                rowDiv.dataset.rowIndex = rowIndex;

                // دکمه‌های موجود در سطر
                row.forEach((btn, btnIndex) => {
                    const btnEl = document.createElement('div');
                    btnEl.className = 'kb-btn flex-1 bg-telegram-btn text-white h-[42px] flex items-center justify-center rounded text-sm cursor-grab active:cursor-grabbing relative overflow-hidden kb-btn-hover-effect';
                    btnEl.innerHTML = `
                        <span class="truncate px-2 pointer-events-none w-full text-center" dir="auto">${btn.text}</span>
                        <!-- اکشن‌های هاور -->
                        <div class="btn-actions absolute inset-0 bg-black/80 flex items-center justify-center gap-2 backdrop-blur-[1px]">
                            <button onclick="editButton(${rowIndex}, ${btnIndex})" class="p-1.5 rounded-full bg-blue-600 hover:bg-blue-500 text-white" title="ویرایش">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                            </button>
                            <button onclick="deleteButton(${rowIndex}, ${btnIndex})" class="p-1.5 rounded-full bg-red-600 hover:bg-red-500 text-white" title="حذف">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                            <button onclick="simulateClick('${btn.text}')" class="p-1.5 rounded-full bg-green-600 hover:bg-green-500 text-white" title="تست">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.66