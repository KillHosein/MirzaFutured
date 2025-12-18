<?php
/**
 * Keyboard Builder – Refactored Version
 * Author: Qutumcore
 * Description: Clean, professional UI with better structure and readability
 */
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Keyboard Builder</title>

    <!-- Fonts & Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- SweetAlert -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body {
            font-family: Vazirmatn, system-ui, sans-serif;
        }
        .panel {
            @apply bg-white/5 border border-white/10 rounded-2xl p-4;
        }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-900 to-slate-800 text-white">

<!-- Header -->
<header class="sticky top-0 z-50 backdrop-blur bg-black/30 border-b border-white/10">
    <div class="max-w-7xl mx-auto flex items-center justify-between px-6 py-4">
        <h1 class="text-lg font-bold flex items-center gap-2">
            <i class="fa-solid fa-keyboard text-indigo-400"></i>
            ویرایشگر کیبورد
        </h1>

        <div class="flex items-center gap-3">
            <button onclick="App.reset()" class="h-10 w-10 rounded-xl bg-red-500/10 hover:bg-red-500/20 text-red-400 transition" title="بازنشانی">
                <i class="fa-solid fa-rotate-left"></i>
            </button>

            <button id="btn-save" onclick="App.save()" class="flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 transition disabled:opacity-50">
                <i class="fa-regular fa-floppy-disk"></i>
                <span>ذخیره تغییرات</span>
            </button>
        </div>
    </div>
</header>

<!-- Main Layout -->
<main class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-6 p-6">

    <!-- Preview -->
    <section class="panel lg:col-span-2">
        <h2 class="text-sm text-white/60 mb-3">پیش‌نمایش کیبورد</h2>
        <div id="keyboard-preview" class="space-y-2"></div>
    </section>

    <!-- Controls -->
    <aside class="panel">
        <h2 class="text-sm text-white/60 mb-3">تنظیمات دکمه</h2>
        <div id="editor" class="space-y-3"></div>
    </aside>

</main>

<script>
const App = {
    init() {
        console.log('Keyboard Builder Initialized');
    },

    reset() {
        Swal.fire({
            icon: 'warning',
            title: 'بازنشانی شود؟',
            confirmButtonText: 'بله',
            cancelButtonText: 'خیر',
            showCancelButton: true,
        }).then(res => {
            if (res.isConfirmed) location.reload();
        });
    },

    save() {
        const btn = document.getElementById('btn-save');
        btn.disabled = true;

        fetch('save.php', { method: 'POST', body: '{}' })
            .then(() => Swal.fire({ icon: 'success', title: 'ذخیره شد' }))
            .catch(() => Swal.fire({ icon: 'error', title: 'خطا در ذخیره' }))
            .finally(() => btn.disabled = false);
    }
};

App.init();
</script>

</body>
</html>
