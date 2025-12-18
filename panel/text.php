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
    <title>ویرایش متون ربات</title>
    
    <!-- Fonts & Icons -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link href="css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            /* Theme Core */
            --bg-body: #050509;
            --bg-card: rgba(23, 23, 30, 0.75);
            --bg-glass: rgba(20, 20, 25, 0.85);
            --bg-dock: rgba(10, 10, 15, 0.95);
            
            /* Neons */
            --neon-blue: #00f2ff;
            --neon-purple: #c026d3;
            --neon-green: #00ffa3;
            --neon-red: #ff2a6d;
            --neon-amber: #fbbf24;
            
            /* Text */
            --text-pri: #ffffff;
            --text-sec: #94a3b8;
            
            /* Borders */
            --border-subtle: 1px solid rgba(255, 255, 255, 0.08);
            --border-highlight: 1px solid rgba(255, 255, 255, 0.2);
            --shadow-card: 0 15px 50px rgba(0,0,0,0.6);
            
            --radius-main: 28px;
            --radius-lg: 24px;
        }

        /* --- Global Reset --- */
        * { box-sizing: border-box; outline: none; }
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.25); }

        body {
            background-color: var(--bg-body);
            color: var(--text-pri);
            font-family: 'Vazirmatn', sans-serif;
            margin: 0; padding: 0;
            min-height: 100vh;
            overflow-x: hidden;
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(34, 211, 238, 0.08) 0%, transparent 45%),
                radial-gradient(circle at 90% 80%, rgba(192, 38, 211, 0.08) 0%, transparent 45%);
            background-attachment: fixed;
            padding-bottom: 150px;
            display: flex; flex-direction: column;
        }

        /* --- Full Height Container --- */
        .container-fluid-custom {
            width: 100%; padding: 30px 4%; max-width: 1200px; margin: 0 auto;
            flex-grow: 1;
            display: flex; flex-direction: column; gap: 30px;
        }

        /* --- Header Bigger --- */
        .page-header {
            display: flex; justify-content: space-between; align-items: center;
            padding-bottom: 25px; border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .page-title h1 {
            font-size: 3rem; font-weight: 900; margin: 0; color: #fff;
            text-shadow: 0 0 30px rgba(255,255,255,0.1);
        }
        .page-title p { color: var(--text-sec); font-size: 1.2rem; margin-top: 5px; font-weight: 400; }
        
        .info-pill {
            background: rgba(255,255,255,0.03); border: var(--border-subtle);
            padding: 12px 25px; border-radius: 18px;
            display: flex; align-items: center; gap: 10px; font-size: 1.1rem;
            backdrop-filter: blur(10px); color: var(--text-sec);
        }

        /* --- Glass Panel --- */
        .glass-panel {
            background: var(--bg-card); border: var(--border-subtle); border-radius: var(--radius-main);
            padding: 40px;
            flex-grow: 1;
            display: flex; flex-direction: column;
            backdrop-filter: blur(20px); box-shadow: var(--shadow-card);
        }

        /* --- Inputs --- */
        .form-group { margin-bottom: 25px; position: relative; }
        .form-group label { display: block; color: var(--text-sec); font-size: 1.1rem; margin-bottom: 10px; font-weight: 600; direction: ltr; text-align: right; }
        
        .input-readable {
            width: 100%; height: 60px;
            background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1);
            color: #fff; padding: 0 20px 0 50px; border-radius: 16px;
            font-family: inherit; font-size: 1.1rem; transition: 0.3s;
        }
        .input-readable:focus { background: rgba(0,0,0,0.5); border-color: var(--neon-blue); outline: none; box-shadow: 0 0 20px rgba(0, 242, 255, 0.2); }

        /* --- Buttons --- */
        .btn-act {
            height: 50px; padding: 0 25px;
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
            border-radius: 14px; color: var(--text-sec); font-size: 1rem; font-weight: 600;
            cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 10px;
            transition: 0.3s; text-decoration: none;
        }
        .btn-act:hover { transform: translateY(-3px); color: #fff; background: rgba(255,255,255,0.1); }
        
        .btn-green-glow { 
            background: var(--neon-green); color: #000; border: none; font-weight: 800; 
            height: 60px; width: 100%; font-size: 1.3rem; border-radius: 18px;
        }
        .btn-green-glow:hover { background: #fff; box-shadow: 0 0 30px var(--neon-green); color: #000; }
        
        .btn-cyan { border-color: var(--neon-blue); color: var(--neon-blue); }
        .btn-cyan:hover { background: rgba(0, 242, 255, 0.1); box-shadow: 0 0 20px var(--neon-blue); }

        /* --- Copy Button --- */
        .btn-copy-field {
            position: absolute; left: 5px; top: 38px; width: 40px; height: 40px;
            background: rgba(255,255,255,0.1); border: none; color: #fff; border-radius: 10px;
            cursor: pointer; transition: 0.2s; display: flex; align-items: center; justify-content: center;
        }
        .btn-copy-field:hover { background: rgba(255,255,255,0.2); color: var(--neon-blue); }

        /* --- Accordion --- */
        .json-section {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        .json-summary {
            padding: 15px 20px;
            background: rgba(255, 255, 255, 0.05);
            cursor: pointer;
            display: flex; align-items: center; justify-content: space-between;
            font-weight: 700; color: var(--neon-blue);
            transition: 0.3s;
        }
        .json-summary:hover { background: rgba(255, 255, 255, 0.08); }
        .json-summary::after {
            content: '\f107'; font-family: 'Font Awesome 6 Free'; font-weight: 900;
            transition: 0.3s;
        }
        details[open] .json-summary::after { transform: rotate(180deg); }
        .json-content { padding: 20px; }

        /* --- Search Bar --- */
        .search-container {
            position: sticky; top: 0; z-index: 100;
            background: rgba(23, 23, 30, 0.95);
            backdrop-filter: blur(10px);
            padding: 15px 0; margin-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
    </style>
</head>
<body>

    <div class="container-fluid-custom">
        
        <!-- Header -->
        <header class="page-header anim">
            <div class="page-title">
                <h1>ویرایش متون ربات</h1>
                <p>
                    <i class="fa-solid fa-file-pen" style="color: var(--neon-blue);"></i>
                    مدیریت متن‌ها و پیام‌های ربات
                </p>
            </div>
            <div class="info-pill">
                <i class="fa-regular fa-calendar"></i>
                <span><?php echo $todayDate; ?></span>
            </div>
        </header>

        <!-- Main Panel -->
        <div class="glass-panel anim d-1">
            
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                <div class="d-flex gap-2">
                    <button type="button" id="toggleRaw" class="btn-act" style="border-color: var(--neon-purple); color: var(--neon-purple);">
                        <i class="fa-solid fa-code"></i> ویرایشگر خام
                    </button>
                    <input type="file" id="importFile" accept=".json" style="display: none;">
                    <button type="button" onclick="document.getElementById('importFile').click()" class="btn-act" style="border-color: var(--neon-amber); color: var(--neon-amber);">
                        <i class="fa-solid fa-file-import"></i> وارد کردن JSON
                    </button>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" id="copyAllJson" class="btn-act btn-cyan">
                        <i class="fa-solid fa-copy"></i> کپی کل
                    </button>
                    <button type="button" id="exportJson" class="btn-act">
                        <i class="fa-solid fa-file-export"></i> خروجی
                    </button>
                </div>
            </div>

            <div class="search-container">
                <div class="form-group" style="margin: 0;">
                    <input type="text" id="searchField" class="input-readable" placeholder="جستجو در متن‌ها..." style="border-color: var(--neon-blue);">
                    <i class="fa-solid fa-search" style="position: absolute; left: 20px; top: 20px; color: var(--text-sec);"></i>
                </div>
            </div>

            <form id="jsonForm"></form>
            
            <button type="button" onclick="saveChanges()" class="btn-act btn-green-glow" style="margin-top: 30px;">
                <i class="fa-solid fa-floppy-disk"></i> ذخیره تغییرات
            </button>
            
        </div>

    </div>

    <!-- Raw Editor Modal -->
    <div class="modal fade" id="rawModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content" style="background: var(--bg-card); border: 1px solid rgba(255,255,255,0.1); border-radius: 20px;">
                <div class="modal-header" style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <h5 class="modal-title" style="color: #fff;">ویرایشگر خام JSON</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <textarea id="rawJsonText" class="input-readable" style="height: 400px; font-family: monospace; line-height: 1.5; resize: vertical;"></textarea>
                </div>
                <div class="modal-footer" style="border-top: 1px solid rgba(255,255,255,0.1);">
                    <button type="button" class="btn-act" data-bs-dismiss="modal">انصراف</button>
                    <button type="button" onclick="applyRawChanges()" class="btn-act btn-green-glow" style="height: 45px; font-size: 1rem;">اعمال تغییرات</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating Dock -->
    <div class="dock-container anim d-3">
        <div class="dock">
            <a href="index.php" class="dock-item">
                <div class="dock-icon"><i class="fa-solid fa-house-chimney"></i></div>
                <span class="dock-label">داشبورد</span>
            </a>
            <a href="invoice.php" class="dock-item">
                <div class="dock-icon"><i class="fa-solid fa-file-invoice-dollar"></i></div>
                <span class="dock-label">سفارشات</span>
            </a>
            <a href="users.php" class="dock-item">
                <div class="dock-icon"><i class="fa-solid fa-users"></i></div>
                <span class="dock-label">کاربران</span>
            </a>
            <a href="product.php" class="dock-item">
                <div class="dock-icon"><i class="fa-solid fa-box-open"></i></div>
                <span class="dock-label">محصولات</span>
            </a>
            <a href="service.php" class="dock-item">
                <div class="dock-icon"><i class="fa-solid fa-server"></i></div>
                <span class="dock-label">سرویس‌ها</span>
            </a>
            <div class="dock-divider"></div>
            <a href="text.php" class="dock-item active">
                <div class="dock-icon"><i class="fa-solid fa-file-pen"></i></div>
                <span class="dock-label">متون</span>
            </a>
            <a href="settings.php" class="dock-item">
                <div class="dock-icon"><i class="fa-solid fa-gear"></i></div>
                <span class="dock-label">تنظیمات</span>
            </a>
            <a href="login.php" class="dock-item" style="color: var(--neon-red);">
                <div class="dock-icon"><i class="fa-solid fa-power-off"></i></div>
                <span class="dock-label">خروج</span>
            </a>
        </div>
    </div>

    <!-- Scripts -->
    <script src="js/jquery.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        let currentData = {};

        // 1. Create Form (Recursive with Accordion)
        function createForm(data, parentElement = null, parentKey = '') {
            const container = parentElement || document.getElementById('jsonForm');
            if(!parentElement) container.innerHTML = ''; // Clear only if root

            Object.keys(data).forEach((key) => {
                const fullKey = parentKey ? `${parentKey}.${key}` : key;
                const value = data[key];

                if (typeof value === 'object' && value !== null) {
                    // Create Accordion Section
                    const details = document.createElement('details');
                    details.className = 'json-section';
                    // Open by default if it's a top-level section or small enough
                    if(!parentKey) details.open = true;

                    const summary = document.createElement('summary');
                    summary.className = 'json-summary';
                    summary.innerText = fullKey;
                    
                    const content = document.createElement('div');
                    content.className = 'json-content';

                    details.appendChild(summary);
                    details.appendChild(content);
                    container.appendChild(details);

                    createForm(value, content, fullKey);
                } else {
                    // Create Input Field
                    const group = document.createElement('div');
                    group.className = 'form-group field-item';
                    group.dataset.key = fullKey.toLowerCase();
                    group.dataset.value = String(value).toLowerCase();
                    
                    const label = document.createElement('label');
                    label.innerText = fullKey;
                    
                    const input = document.createElement('input');
                    input.type = 'text';
                    input.className = 'input-readable';
                    input.value = value;
                    input.name = fullKey;
                    input.id = 'field_' + fullKey.replace(/\./g, '_');
                    
                    // Auto-expand textarea if long? For now keep as input.
                    
                    const copyBtn = document.createElement('button');
                    copyBtn.type = 'button';
                    copyBtn.className = 'btn-copy-field';
                    copyBtn.innerHTML = '<i class="fa-solid fa-copy"></i>';
                    copyBtn.onclick = () => copyToClipboard(input.value, copyBtn);
                    
                    group.appendChild(label);
                    group.appendChild(input);
                    group.appendChild(copyBtn);
                    container.appendChild(group);
                }
            });
        }

        // 2. Load Data
        function loadData() {
            fetch('text.php?action=get_json&v=' + new Date().getTime())
                .then(response => response.json())
                .then(data => {
                    currentData = data;
                    createForm(data);
                })
                .catch(error => console.error('Error loading JSON:', error));
        }
        loadData();

        // 3. Search Functionality
        document.getElementById('searchField').addEventListener('input', function(e) {
            const term = e.target.value.toLowerCase();
            const items = document.querySelectorAll('.field-item');
            const sections = document.querySelectorAll('details.json-section');
            
            items.forEach(item => {
                const key = item.dataset.key;
                const val = item.dataset.value;
                if (key.includes(term) || val.includes(term)) {
                    item.style.display = 'block';
                    // Ensure parent details is open
                    let parent = item.closest('details');
                    while(parent) {
                        parent.open = true;
                        parent = parent.parentElement.closest('details');
                    }
                } else {
                    item.style.display = 'none';
                }
            });

            // Hide empty sections if needed (optional refinement)
        });

        // 4. Raw Editor
        const rawModal = new bootstrap.Modal(document.getElementById('rawModal'));
        document.getElementById('toggleRaw').addEventListener('click', () => {
            // Get current form values to ensure latest edits are captured
            const formValues = getFormData();
            document.getElementById('rawJsonText').value = JSON.stringify(formValues, null, 4);
            rawModal.show();
        });

        window.applyRawChanges = function() {
            try {
                const raw = document.getElementById('rawJsonText').value;
                const parsed = JSON.parse(raw);
                currentData = parsed;
                createForm(parsed);
                rawModal.hide();
                Swal.fire({
                    icon: 'success',
                    title: 'JSON بروز شد',
                    text: 'برای ذخیره نهایی دکمه "ذخیره تغییرات" را بزنید.',
                    background: '#1e1e2d', color: '#fff', timer: 2000, showConfirmButton: false
                });
            } catch (e) {
                Swal.fire('Error', 'فرمت JSON نامعتبر است:\n' + e.message, 'error');
            }
        };

        // 5. Import File
        document.getElementById('importFile').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    const json = JSON.parse(e.target.result);
                    currentData = json;
                    createForm(json);
                    Swal.fire({
                        icon: 'success',
                        title: 'فایل بارگذاری شد',
                        background: '#1e1e2d', color: '#fff', timer: 1500, showConfirmButton: false
                    });
                } catch (err) {
                    Swal.fire('Error', 'فایل JSON نامعتبر است', 'error');
                }
            };
            reader.readAsText(file);
            // Reset input
            this.value = '';
        });

        // 6. Save Changes
        window.saveChanges = function() {
            // Get data from form (in case user edited inputs)
            const updatedJson = getFormData();

            fetch('text.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(updatedJson)
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire({
                        icon: 'success', title: 'تغییرات ذخیره شد',
                        showConfirmButton: false, timer: 1500,
                        background: '#1e1e2d', color: '#fff'
                    });
                    currentData = updatedJson;
                } else {
                    Swal.fire('Error', 'خطا در ذخیره سازی', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error', 'خطا در ارتباط با سرور', 'error');
            });
        };

        // Helper: Get Form Data as Nested Object
        function getFormData() {
            const form = document.getElementById('jsonForm');
            const formData = new FormData(form); // This only gets inputs, not recursive structure directly
            // Actually, since we generated inputs with name="key.subkey", we can reconstruct easily
            // But wait, FormData only grabs inputs inside a <form>. 
            // Our createForm appends to #jsonForm which IS a form tag.
            // However, details/summary structure might affect it? No, standard HTML form collection works deep.
            
            const result = {};
            // We need to iterate all inputs manually to handle hierarchy correctly if we want to support dynamic structure changes from Raw Editor
            // But for simple value updates, iterating inputs is fine.
            
            // Better approach: start with currentData structure and update values from inputs
            // OR reconstruct from inputs. Reconstruct is safer for "what you see is what you get".
            
            const inputs = form.querySelectorAll('input');
            inputs.forEach(input => {
                const keys = input.name.split('.');
                let temp = result;
                while (keys.length > 1) {
                    const k = keys.shift();
                    if (!temp[k]) temp[k] = {};
                    temp = temp[k];
                }
                temp[keys[0]] = input.value;
            });
            
            // Merge with currentData to keep keys that might be hidden/missing? 
            // No, the form represents the full state.
            return result;
        }

        // 7. Export JSON
        document.getElementById('exportJson').addEventListener('click', () => {
            const data = getFormData();
            const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(data, null, 2));
            const node = document.createElement('a');
            node.setAttribute("href", dataStr);
            node.setAttribute("download", "text_settings.json");
            document.body.appendChild(node);
            node.click();
            node.remove();
        });

        // 8. Copy All
        document.getElementById('copyAllJson').addEventListener('click', function() {
            const data = getFormData();
            const jsonStr = JSON.stringify(data, null, 2);
            navigator.clipboard.writeText(jsonStr).then(() => {
                const originalText = this.innerHTML;
                this.innerHTML = '<i class="fa-solid fa-check"></i> کپی شد';
                this.className = 'btn-act btn-green-glow';
                setTimeout(() => {
                    this.innerHTML = originalText;
                    this.className = 'btn-act btn-cyan';
                }, 2000);
            });
        });

        // Helper: Copy Single Field
        function copyToClipboard(text, btn) {
            navigator.clipboard.writeText(text).then(() => {
                const originalIcon = btn.innerHTML;
                btn.innerHTML = '<i class="fa-solid fa-check"></i>';
                btn.style.color = '#00ffa3';
                setTimeout(() => {
                    btn.innerHTML = originalIcon;
                    btn.style.color = '';
                }, 2000);
            });
        }
    </script>

</body>
</html>
