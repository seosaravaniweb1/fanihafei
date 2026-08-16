@echo off
chcp 65001 >nul
setlocal
rem از پوشه‌ی والد اجرا می‌کنیم تا مسیرهای داخل config (مثل
rem content_pipeline/data/runs.db) همان‌طور که در README آمده حل شوند.
cd /d "%~dp0.."
title پنل مدیریت خوراک محتوایی

echo.
echo ==========================================
echo    پنل مدیریت خوراک محتوایی
echo ==========================================
echo.

where python >nul 2>nul
if errorlevel 1 (
  echo [x] پایتون روی این سیستم پیدا نشد.
  echo     از python.org نصبش کنید و موقع نصب گزینه‌ی
  echo     "Add python.exe to PATH" را تیک بزنید.
  echo.
  pause
  exit /b 1
)

python -c "import typer, yaml" >nul 2>nul
if errorlevel 1 (
  echo [...] نصب وابستگی‌های لازم. یک بار انجام می‌شود و کمی طول می‌کشد.
  python -m pip install --quiet --disable-pip-version-check typer pyyaml
  if errorlevel 1 (
    echo [x] نصب وابستگی‌ها شکست خورد. متن خطای بالا را نگاه کنید.
    echo.
    pause
    exit /b 1
  )
)

if not exist "config.yaml" (
  echo [+] ساخت config.yaml از روی نمونه.
  copy /y "content_pipeline\config.example.yaml" "config.yaml" >nul
)

echo [+] تنظیمات: %CD%\config.yaml
echo [+] پنل در حال بالا آمدن است؛ مرورگر خودش باز می‌شود.
echo     برای بستن، در همین پنجره Ctrl+C بزنید.
echo.

python -m content_pipeline.run panel -c config.yaml

echo.
echo پنل بسته شد.
pause
