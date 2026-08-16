#!/usr/bin/env bash
# همان کار start-panel.bat برای لینوکس و مک: اجرا با ./start-panel.sh
set -u
# از پوشه‌ی والد اجرا می‌کنیم تا مسیرهای داخل config (مثل
# content_pipeline/data/runs.db) همان‌طور که در README آمده حل شوند.
cd "$(dirname "$0")/.."

echo
echo "=========================================="
echo "   پنل مدیریت خوراک محتوایی"
echo "=========================================="
echo

PY=""
for candidate in python3 python; do
  if command -v "$candidate" >/dev/null 2>&1; then PY="$candidate"; break; fi
done
if [ -z "$PY" ]; then
  echo "[x] پایتون پیدا نشد. اول python3 را نصب کنید."
  exit 1
fi

if ! "$PY" -c "import typer, yaml" >/dev/null 2>&1; then
  echo "[...] نصب وابستگی‌های لازم. یک بار انجام می‌شود."
  "$PY" -m pip install --quiet --disable-pip-version-check typer pyyaml || {
    echo "[x] نصب وابستگی‌ها شکست خورد."
    exit 1
  }
fi

if [ ! -f "config.yaml" ]; then
  echo "[+] ساخت config.yaml از روی نمونه."
  cp content_pipeline/config.example.yaml config.yaml
fi

echo "[+] تنظیمات: $(pwd)/config.yaml"
echo "[+] پنل در حال بالا آمدن است؛ مرورگر خودش باز می‌شود."
echo "    برای بستن، همین‌جا Ctrl+C بزنید."
echo

exec "$PY" -m content_pipeline.run panel -c config.yaml
