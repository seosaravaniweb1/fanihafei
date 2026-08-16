# ابزار اتوماسیون استخراج و تحلیل خوراک محتوایی

پایپ‌لاین CLI پایتونی و **موضوع‌محور (Topic-Agnostic)**: یک «موضوع هدف» و لیستی از
سایت‌های رقیب می‌گیرد و تا خروجی نهایی در گوگل‌شیت پیش می‌رود.

| ستون خروجی | توضیح |
|---|---|
| `canonical_title` | عنوان یکپارچه‌شده پس از ادغام نام‌های مشابه |
| `suggest_status` | `has_suggest` یا `no_suggest` |
| `lsi_keywords` | کلمات پیشنهادی گوگل برای همان محصول |
| `benchmark_url` | لینک بهترین محتوای وب برای آن عنوان |
| `image_url` | تصویر مربعی و بدون واترمارک (یا خالی) |
| `source_urls` | لینک‌های اصلی استخراج (برای ردیابی) |
| `confidence` | امتیاز اطمینان ادغام، برای بازبینی دستی |

---

## نصب

```bash
python -m venv .venv && source .venv/bin/activate
pip install -r content_pipeline/requirements.txt
playwright install chromium        # فقط اگر سایت JS-heavy دارید

cp content_pipeline/config.example.yaml config.yaml
export ANTHROPIC_API_KEY=...       # برای فازهای ۲، ۴ و ۵
export SERP_API_KEY=...            # برای فازهای ۳، ۴ و ۵
```

**حداقل وابستگی‌ها `typer` و `PyYAML` است.** بقیه اختیاری‌اند و پایپ‌لاین بدون
آن‌ها هم اجرا می‌شود، ولی با کیفیت پایین‌تر:

| بسته | نبود آن یعنی |
|---|---|
| `curl_cffi` | برگشت به `requests`/`urllib` — احتمال بلاک شدن با Cloudflare |
| `trafilatura` | استخراج متن ساده‌تر (کیفیت بنچمارک پایین‌تر) |
| `rapidfuzz` | شباهت با `difflib` (کندتر) |
| `sentence-transformers` | انکودر جایگزین hashing tf-idf — تشخیص موضوعی ضعیف‌تر |
| `Pillow` | خواندن ابعاد از هدر فایل (PNG/JPEG/GIF/WebP) |
| `openpyxl` | خروجی به‌جای xlsx، چند فایل CSV |
| `anthropic` | داوری ادغام، بنچمارک کیفی و چک واترمارک انجام نمی‌شود |

---

## اجرا

```bash
# ساخت دیتابیس
python -m content_pipeline.run init-db -c config.yaml

# تخمین هزینه بدون زدن هیچ کالی
python -m content_pipeline.run start -c config.yaml --dry-run

# اجرای کامل با سقف هزینه
python -m content_pipeline.run start -c config.yaml --max-cost 20

# ادامه از فاز ۳ روی آخرین اجرا (فازهای ۱ و ۲ تکرار نمی‌شوند)
python -m content_pipeline.run start -c config.yaml --resume-from 3

# فقط چند فاز مشخص
python -m content_pipeline.run start -c config.yaml --phases 1,2,6

# یک فاز روی یک اجرای مشخص
python -m content_pipeline.run phase 3 -c config.yaml --run-id 20260816-101500-ab12cd

# وضعیت، فهرست اجراها و خروجی مجدد
python -m content_pipeline.run status  -c config.yaml
python -m content_pipeline.run runs    -c config.yaml
python -m content_pipeline.run export  -c config.yaml --no-sheets

# ابزار کمکی: دیدن خروجی فاز ۰
python -m content_pipeline.run normalize "دانلود رمان تاوان خیانت آوا PDF ۱۴۰۴"
```

> `start` بدون `--run-id` و بدون `--resume-from` همیشه یک اجرای **جدید** می‌سازد.
> با `--resume-from N` آخرین اجرای همان موضوع ادامه پیدا می‌کند.

---

## معماری

```
content_pipeline/
├── run.py                  نقطه ورود CLI
├── config.example.yaml     کلیدها، آستانه‌ها و لیست سایت‌ها
├── core/
│   ├── db.py               اسکیما و لایه‌ی state
│   ├── normalizer.py       فاز ۰ — نرمال‌سازی فارسی
│   ├── entities.py         مرحله B فاز ۲ — توکن تمایزدهنده
│   ├── similarity.py       شباهت متنی (rapidfuzz / difflib)
│   ├── embeddings.py       بردارسازی (e5 / hashing tf-idf)
│   ├── extract.py          پارس HTML، sitemap و معیارهای محتوا
│   ├── http.py             curl_cffi + robots + rate limit + Playwright
│   ├── cache.py            کش API
│   ├── cost_guard.py       کنترل هزینه
│   └── config.py           بارگذاری config
├── phases/                 p1_crawl … p5_image + p6_export
├── clients/                serp_client، llm_client، sheets_client
├── tests/                  ۹۱ تست، بدون نیاز به شبکه
└── data/                   runs.db و خروجی‌ها (در git نیست)
```

اصول غیرقابل مذاکره‌ای که در کد اعمال شده‌اند:

1. **پنج فاز = پنج ماژول مستقل.** هیچ فازی تابع فاز دیگر را صدا نمی‌زند؛ ارتباط
   فقط از طریق دیتابیس است.
2. **SQLite لایه‌ی state است.** هر فاز نتیجه را بلافاصله می‌نویسد.
3. **Resume.** `--resume-from 3` بدون تکرار فاز ۱ و ۲ کار می‌کند.
4. **Idempotency.** `UNIQUE` + `INSERT OR IGNORE` در هر جدول.
5. **گوگل‌شیت فقط خروجی است** — یک batch write در انتها.
6. **هر کال خارجی کش می‌شود** با کلید `sha256(provider+endpoint+params)`.

---

## فازها

### فاز ۰ — نرمال‌سازی فارسی (پیش‌نیاز حیاتی)

`core/normalizer.py`. هر عنوان قبل از هر پردازشی از این خط عبور می‌کند:

- `ي`/`ك` عربی → `ی`/`ک` (به‌علاوه `ة`، `أ`، `إ`، `ؤ`، `ئ`، `ۀ`)
- یکسان‌سازی نیم‌فاصله و حذف نیم‌فاصله‌های اضافه (و «ه‌ی/ه‌ای» اضافه)
- یکسان‌سازی ارقام (پیش‌فرض فارسی، با `normalizer.digit_target` قابل تغییر)
- حذف اعراب و کشیده
- حذف کلمات ایستای تجاری: دانلود، خرید، رایگان، pdf، فایل، کامل، جدید و سال‌ها
- نرمال‌سازی فاصله‌های چندگانه

دو خروجی دارد: `normalize_display` (خوانا، برای `canonical_title`) و `normalize`
(شکل تطبیق، ستون `normalized_title`). `raw_title` **هرگز** تغییر نمی‌کند.

### فاز ۱ — کراول و تشخیص موضوعی

`robots.txt` → `sitemap.xml` (+ ایندکس‌ها) → در نبودشان کراول محدود با
`max_depth`. هر صفحه با `curl_cffi` و `impersonate="chrome"`؛ روی ۴۰۳/۴۲۹/۵۰۳ یا
صفحه‌ی پوسته‌ای، fallback به Playwright. حداکثر ۱ درخواست در ثانیه به هر دامنه و
احترام به `robots.txt`.

بردار مرجع از `topic.target` + نمونه‌عنوان‌ها ساخته می‌شود؛
`topic_score >= threshold` یعنی مرتبط، و بازه‌ی `review_threshold..threshold` در
`data/review/<run_id>_borderline.csv` برای بازبینی دستی ذخیره می‌شود.

### فاز ۲ — Entity Resolution (حساس‌ترین فاز)

| مرحله | کار |
|---|---|
| A | بلاک‌بندی با نادرترین توکن‌ها (به‌جای مقایسه‌ی O(n²)) |
| B | استخراج توکن تمایزدهنده: عدد، توکن لاتین، نام خاص، «جلد ۲»، نامِ پس از «از/نوشته/اثر» |
| C | قانون ادغام |

```
اگر similarity(base_title) < 0.80  →  ادغام نکن
اگر similarity >= 0.80:
    entity_tokens یکسان                 →  ادغام (confidence بالا)
    یکی از دو طرف خالی                  →  داوری LLM
    متفاوت و هر دو پر                   →  ادغام نکن (خط قرمز)
    LLM گفت «مطمئن نیستم»               →  needs_review، ادغام نکن
```

**قانون طلایی در کد اعمال شده است:** خوشه‌بندی حریصانه هرگز دو عضو «خط قرمز» را
در یک خوشه نمی‌گذارد، حتی اگر مسیر انتقالی وجود داشته باشد؛ و بدون کلاینت LLM،
جفت مبهم ادغام نمی‌شود و `needs_review` می‌گیرد.

سناریوی مرجع داکیومنت در `tests/test_resolve.py` قفل شده است:

| عنوان | توکن تمایزدهنده |
|---|---|
| رمان تاوان خیانت آوا | `{آوا}` |
| رمان تاوان خیانت مهسا | `{مهسا}` |
| رمان تاوان خیانت از ناشناس | `{}` |
| رمان تاوان خیانت ۴ برادر | `{۴ برادر}` |

### فاز ۳ — گوگل ساجست

یک کوئری ساجست به ازای هر محصول از طریق SerpApi/DataForSEO
(**نه** `suggestqueries.google.com` — در مقیاس بلاک می‌شوی). هر کلمه یک ردیف در
`lsi_keywords`. اگر یک کلمه به دو محصول نسبت داده شود، فقط برای محصولی می‌ماند که
شباهت متنی بیشتری دارد. نتیجه‌ی خالی → `no_suggest`؛ این رکوردها **حذف
نمی‌شوند** و در تب «آرشیو آینده» می‌آیند.

### فاز ۴ — بنچمارک محتوا

برای محصولات `has_suggest`: ۱۰ نتیجه‌ی ارگانیک → استخراج متن → امتیاز کمّی
(تعداد کلمه، عمق هدینگ، جدول/لیست/تصویر، تاریخ) → فقط **سه** کاندیدای برتر به
LLM برای ارزیابی کیفی ۰ تا ۱۰ (هزینه یک‌سوم می‌شود).

### فاز ۵ — تصویر تمیز

جستجوی تصویر با فیلتر نسبت مربعی → برای ۵ کاندیدای برتر: نسبت ۰.۹ تا ۱.۱ و
حداقل ۵۰۰×۵۰۰ → بررسی Vision («لوگو/واترمارک/آدرس سایت دارد؟») → اولین «خیر»
برنده است. اگر هیچ‌کدام پاس نشد، فیلد خالی می‌ماند.

---

## کنترل هزینه

- قبل از هر فاز تخمین «X کال و $Y» نمایش و تأیید گرفته می‌شود (`--yes` برای رد شدن)
- `--max-cost 20` اجرا را در سقف متوقف می‌کند
- `--dry-run` فقط تخمین می‌زند و هیچ کالی نمی‌زند
- همه‌ی کال‌ها در `api_usage` لاگ می‌شوند؛ کال‌های کش‌شده هزینه‌ای ثبت نمی‌کنند
- هزینه‌ی LLM از توکن‌های واقعیِ پاسخ محاسبه می‌شود، نه از تخمین

---

## تست

```bash
python -m pytest content_pipeline/tests -q
```

هیچ تستی به شبکه، کلید API یا مدل سنگین نیاز ندارد. پوشش شامل: نرمال‌سازی فارسی
روی نمونه‌های واقعی، سناریوی مرجع فاز ۲، idempotency و resume دیتابیس، کش و سقف
هزینه، تداخل LSI، قواعد ابعاد تصویر و تقسیم تب‌های خروجی.

---

## یادداشت‌ها

- **مدل LLM:** داکیومنت `claude-sonnet-4-6` را نام برده بود؛ پیش‌فرض روی
  `claude-sonnet-5` (نسل فعلیِ همان ردهٔ Sonnet) گذاشته شده و از `llm.model` در
  config قابل تغییر است.
- **کلیدهای API** در `config.yaml` نوشته نمی‌شوند؛ از `${ANTHROPIC_API_KEY}` و
  `${SERP_API_KEY}` استفاده کنید. `config.yaml` در `.gitignore` است.
- بسته‌های `cloudscraper`، `newspaper3k`، `puppeteer-extra-stealth` و
  `Google Custom Search JSON API` عمداً استفاده نشده‌اند.
