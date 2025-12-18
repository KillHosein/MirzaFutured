$ErrorActionPreference = "Stop"
$scriptPath = Split-Path -Parent $MyInvocation.MyCommand.Definition
$appDir = Resolve-Path (Join-Path $scriptPath "..\app")
$errors = @()

Write-Host "Running Smoke Tests (PowerShell)..." -ForegroundColor Cyan
Write-Host "----------------------" -ForegroundColor Gray

# 1. Check Critical Files
$files = @(
    "index.php",
    ".htaccess",
    "static/js/main.js",
    "static/js/vendor.js",
    "static/css/style.css",
    "static/css/theme.css",
    "static/js/theme-loader.js"
)

foreach ($file in $files) {
    $path = Join-Path $appDir $file
    if (-not (Test-Path $path)) {
        $errors += "Missing critical file: $file"
        Write-Host "❌ Missing: $file" -ForegroundColor Red
    } else {
        Write-Host "✅ File exists: $file" -ForegroundColor Green
    }
}

# 2. Check Directory Structure
$dirs = @(
    "static/js",
    "static/css",
    "static/fonts"
)

foreach ($dir in $dirs) {
    $path = Join-Path $appDir $dir
    if (-not (Test-Path $path)) {
        $errors += "Missing directory: $dir"
        Write-Host "❌ Missing directory: $dir" -ForegroundColor Red
    } else {
        Write-Host "✅ Directory exists: $dir" -ForegroundColor Green
    }
}

# 3. Check Index Integrity
$indexPath = Join-Path $appDir "index.php"
if (Test-Path $indexPath) {
    $content = Get-Content $indexPath -Raw
    $requiredStrings = @(
        "static/js/main.js",
        "static/css/style.css",
        "theme-loader.js",
        'dir="rtl"',
        'lang="fa"'
    )

    foreach ($str in $requiredStrings) {
        if ($content -notmatch [regex]::Escape($str)) {
            $errors += "index.php missing required string: '$str'"
            Write-Host "❌ index.php missing: '$str'" -ForegroundColor Red
        }
    }
    if ($errors.Count -eq 0) {
        Write-Host "✅ index.php integrity check passed" -ForegroundColor Green
    }
}

Write-Host "----------------------" -ForegroundColor Gray
if ($errors.Count -eq 0) {
    Write-Host "✅ All tests passed!" -ForegroundColor Green
    exit 0
} else {
    Write-Host "❌ Tests failed:" -ForegroundColor Red
    foreach ($err in $errors) {
        Write-Host " - $err" -ForegroundColor Red
    }
    exit 1
}
