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
        file_put_contents('text.json', json_encode($dataArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success']);
        exit;
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
        exit;
    }
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

        /* --- Floating Dock --- */
        .dock-container {
            position: fixed; bottom: 30px; left: 0; right: 0;
            display: flex; justify-content: center; z-index: 2000; pointer-events: none;
        }
        .dock {
            pointer-events: auto; display: flex; align-items: center; gap: 12px;
            background: rgba(15, 15, 20, 0.9); backdrop-filter: blur(35px);
            border: 1px solid rgba(255,255,255,0.15); border-radius: 30px; padding: 15px;
            box-shadow: 0 30px 80px rgba(0,0,0,0.9);
            max-width: 95vw; overflow-x: auto; scrollbar-width: none;
        }
        .dock::-webkit-scrollbar { display: none; }
        
        .dock-item {
            width: 60px; height: 60px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            border-radius: 20px;
            color: var(--text-sec); font-size: 1.6rem;
            text-decoration: none; position: relative; background: transparent;
            transition: all 0.25s cubic-bezier(0.3, 0.7, 0.4, 1.5);
        }
        .dock-item:hover {
            width: 75px; height: 75px; margin: 0 6px;
            background: rgba(255,255,255,0.1); color: #fff;
            transform: translateY(-12px);
        }
        .dock-item.active {
            color: var(--neon-blue); background: rgba(0, 242, 255, 0.1);
        }
        
        .dock-label { 
            font-size: 0.9rem; font-weight: 600; opacity: 0; position: absolute; 
            bottom: 100%; transition: 0.3s; white-space: nowrap; 
            background: rgba(0,0,0,0.9); padding: 4px 10px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.2);
            color: #fff; pointer-events: none; margin-bottom: 15px;
        }
        .dock-item:hover .dock-label { opacity: 1; bottom: 100%; transform: translateY(-5px); }
        .dock-item.active .dock-label { opacity: 1; bottom: 100%; color: var(--neon-blue); }

        .dock-divider { width: 1px; height: 40px; background: rgba(255,255,255,0.1); margin: 0 6px; flex-shrink: 0; }

        @media (max-width: 768px) {
            .container-fluid-custom { padding: 30px 15px 160px 15px; }
            .dock { width: 95%; justify-content: flex-start; }
            .dock-item { width: 50px; height: 50px; font-size: 1.4rem; }
            .page-title h1 { font-size: 2.5rem; }
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
            
            <div style="display: flex; justify-content: flex-end; gap: 15px; margin-bottom: 30px; flex-wrap: wrap;">
                <button type="button" id="copyAllJson" class="btn-act btn-cyan">
                    <i class="fa-solid fa-copy"></i> کپی کل JSON
                </button>
                <button type="button" id="exportJson" class="btn-act">
                    <i class="fa-solid fa-file-export"></i> خروجی JSON
                </button>
            </div>

            <form id="jsonForm"></form>
            
            <button type="button" onclick="saveChanges()" class="btn-act btn-green-glow" style="margin-top: 30px;">
                <i class="fa-solid fa-floppy-disk"></i> ذخیره تغییرات
            </button>
            
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

        // 1. Create Form
        function createForm(data, parentKey = '') {
            const form = document.getElementById('jsonForm');
            Object.keys(data).forEach((key, index) => {
                const fullKey = parentKey ? `${parentKey}.${key}` : key;
                if (typeof data[key] === 'object' && data[key] !== null) {
                    // Recursive for objects if needed, but simple key-value preferred here
                    // If complex, maybe add a section title?
                    const sectionTitle = document.createElement('h4');
                    sectionTitle.style.color = 'var(--neon-blue)';
                    sectionTitle.style.marginTop = '20px';
                    sectionTitle.innerText = fullKey;
                    form.appendChild(sectionTitle);
                    createForm(data[key], fullKey);
                } else {
                    const group = document.createElement('div');
                    group.className = 'form-group';
                    
                    const label = document.createElement('label');
                    label.innerText = fullKey;
                    
                    const input = document.createElement('input');
                    input.type = 'text';
                    input.className = 'input-readable';
                    input.value = data[key];
                    input.name = fullKey;
                    input.id = 'field_' + fullKey.replace(/\./g, '_');
                    
                    const copyBtn = document.createElement('button');
                    copyBtn.type = 'button';
                    copyBtn.className = 'btn-copy-field';
                    copyBtn.innerHTML = '<i class="fa-solid fa-copy"></i>';
                    copyBtn.onclick = () => copyToClipboard(input.value, copyBtn);
                    
                    group.appendChild(label);
                    group.appendChild(input);
                    group.appendChild(copyBtn);
                    form.appendChild(group);
                }
            });
        }

        // 2. Load Data
        fetch('<?php echo $Pathfile; ?>text.json')
            .then(response => response.json())
            .then(data => {
                currentData = data;
                createForm(data);
            })
            .catch(error => console.error('Error loading JSON:', error));

        // 3. Save Changes
        function saveChanges() {
            const form = document.getElementById('jsonForm');
            const formData = new FormData(form);
            const updatedJson = {};
            
            // Reconstruct JSON from flat keys (dot notation)
            formData.forEach((value, key) => {
                const keys = key.split('.');
                let temp = updatedJson;
                while (keys.length > 1) {
                    const k = keys.shift();
                    if (!temp[k]) temp[k] = {};
                    temp = temp[k];
                }
                temp[keys[0]] = value;
            });

            fetch('text.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(updatedJson)
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'تغییرات ذخیره شد',
                        showConfirmButton: false,
                        timer: 1500,
                        background: '#1e1e2d',
                        color: '#fff'
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
        }

        // 4. Export JSON
        document.getElementById('exportJson').addEventListener('click', () => {
            const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(currentData, null, 2));
            const downloadAnchorNode = document.createElement('a');
            downloadAnchorNode.setAttribute("href", dataStr);
            downloadAnchorNode.setAttribute("download", "text_settings.json");
            document.body.appendChild(downloadAnchorNode);
            downloadAnchorNode.click();
            downloadAnchorNode.remove();
        });

        // 5. Copy All JSON
        document.getElementById('copyAllJson').addEventListener('click', function() {
            const jsonStr = JSON.stringify(currentData, null, 2);
            navigator.clipboard.writeText(jsonStr).then(() => {
                const originalText = this.innerHTML;
                this.innerHTML = '<i class="fa-solid fa-check"></i> کپی شد';
                this.className = 'btn-act btn-green-glow';
                this.style.width = 'auto'; // Reset width if needed
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
