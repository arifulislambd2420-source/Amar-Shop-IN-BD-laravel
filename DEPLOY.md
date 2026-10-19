# Hostinger Deploy Guide — আমারশপ (Laravel)

এই গাইড অনুসরণ করে Laravel app টা Hostinger shared/Business hosting-এ চালু করুন। এটা লোকাল টেস্ট (SQLite) থেকে আলাদা — সার্ভারে **MySQL** ব্যবহার হবে (Next.js আমলে যেটা ছিল, সেই একই database)।

## ১. hPanel-এ যা লাগবে

- **PHP 8.2 বা তার বেশি** (hPanel → Advanced → PHP Configuration এ সেট করুন)
- প্রয়োজনীয় PHP extension চালু আছে কিনা দেখুন: `intl`, `mbstring`, `openssl`, `pdo_mysql`, `curl`, `gd`, `zip`, `bcmath`, `fileinfo` — Hostinger shared hosting-এ এগুলো সাধারণত ডিফল্ট চালু থাকে।
- একটা **MySQL Database** (hPanel → Databases → MySQL Databases) — নাম/ইউজার/পাসওয়ার্ড নোট করে রাখুন।
- **SSH access** (হলে সহজ হয়; না থাকলে hPanel-এর File Manager + একটা "Run Composer"-সদৃশ টুল/Git deploy ফিচার দিয়েও করা যায়)।

## ২. কোড আপলোড

```bash
# লোকাল থেকে GitHub-এ push করুন (এখনো করা না থাকলে)
cd C:\Amar-Shop-IN-BD-laravel
git remote add origin https://github.com/arifulislambd2420-source/Amar-Shop-IN-BD-laravel.git
git push -u origin main
```

সার্ভারে (SSH দিয়ে):

```bash
cd ~/domains/yourdomain.com   # আপনার domain root
git clone https://github.com/arifulislambd2420-source/Amar-Shop-IN-BD-laravel.git laravel-app
cd laravel-app
```

> **গুরুত্বপূর্ণ:** ওয়েবসাইটের public root (যেখানে ব্রাউজার সরাসরি hit করে) হবে এই রিপোর **`public/`** ফোল্ডার, পুরো রিপো না। hPanel → Websites → Manage → **Document Root পরিবর্তন করে** `laravel-app/public` করুন। এটাই Laravel-কে shared hosting-এ নিরাপদে চালানোর standard পদ্ধতি — `.env`, `app/`, `database/` ইত্যাদি ব্রাউজার থেকে সরাসরি অ্যাক্সেসযোগ্য থাকবে না।

## ৩. Dependency ইনস্টল

```bash
composer install --optimize-autoloader --no-dev
npm install
npm run build
```

## ৪. `.env` সেটআপ

```bash
cp .env.example .env
php artisan key:generate
```

তারপর `.env` এডিট করে বসান:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://online.amarshopinbd.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=আপনার_db_নাম
DB_USERNAME=আপনার_db_user
DB_PASSWORD=আপনার_db_password

QUEUE_CONNECTION=database

# Reverse proxy: খালি রাখলে শুধু লোকাল/প্রাইভেট নেটওয়ার্কের proxy বিশ্বাস করা হয়।
# Hostinger-এ ভিজিটরের আসল IP না এলে (rate limit/IP block কাজ না করলে) নিচে proxy-র IP বা * দিন —
# শুধু যখন সার্ভারে সরাসরি ঢোকার পথ নেই, সবকিছু হোস্টের নিজস্ব proxy দিয়ে আসে।
TRUSTED_PROXIES=

# ছবি আপলোডের সর্বোচ্চ সাইজ (KB) — ছবি আমাদের নিজের সার্ভারে থাকে, বাইরের কোনো সার্ভিস লাগে না
MEDIA_MAX_KB=5120

ENABLE_GTM=true
```

> **নোট:** GTM Container ID এবং Steadfast courier API key — এগুলো `.env` এ বসাতে হবে না, admin panel এর ভেতর থেকেই (Site Setting / GTM Settings / Courier Settings পেজ) সেট করা যাবে, DB তে সেভ হয়।

## ৫. Database migrate

**পুরনো Next.js সাইট থেকে একই MySQL database reuse করছেন** ধরে নিয়ে — এই Laravel app-এর migration সেই একই ২২টা টেবিলের schema বানায়/মেলায় (column নাম হুবহু মিলিয়ে বানানো)। দুই পথ:

- **নতুন/ফাঁকা database হলে:** `php artisan migrate --seed` — টেবিল + sample data দুটোই তৈরি হবে।
- **পুরনো ডাটা-সহ database হলে:** `php artisan migrate` (শুধু migrate, `--seed` বাদ দিন যাতে ডুপ্লিকেট sample ডাটা না ঢোকে) — Laravel-এর migration idempotent-ভাবে লেখা, তাই আগে থেকে থাকা row-গুলো অক্ষুণ্ণ থাকবে।

```bash
php artisan migrate --force
```

(`--force` production-এ prompt এড়াতে লাগে।)

## ৬. ছবি: Storage link + permission

আপলোড করা সব ছবি সার্ভারের `storage/app/public/media`-তে থাকে এবং সাইটে `/storage/media/…` লিংকে দেখায়।

```bash
chmod -R 775 storage bootstrap/cache
php artisan storage:link
```

**`storage:link` ব্যর্থ হলে** (Hostinger shared-এ `symlink()` বন্ধ থাকলে `The [public/storage] link … symlink(): … disabled` ধরনের error), এটা চালান:

```bash
php artisan media:link
```

এটা আগে symlink চেষ্টা করে; না পারলে `public/storage` ফোল্ডারে আপলোড করা ছবির কপি বানায় — ওয়েব সার্ভার সরাসরি সেগুলো দেখায়, আর **নতুন আপলোডও নিজে থেকে কপি হয়ে যায়**। এটাও না হলে শেষ ভরসা: Laravel নিজেই `/storage/media/…` দেখাবে (ধীর, কিন্তু ছবি ভাঙবে না)।

> `public_html` যদি Laravel-এর `public/` ফোল্ডারের দিকে না থাকে (প্রজেক্টের `public/` সরাসরি `public_html`), তাহলে `config/media.php`-এর `public_path` ঠিক সেই ফোল্ডারের `storage` হতে হবে।

**পুরনো বাইরের ছবির লিংক** (আগে অন্য সার্ভিসে সেভ হওয়া) নিজের সার্ভারে আনতে:

```bash
php artisan media:localize-remote --dry-run   # আগে শুধু তালিকা দেখুন
php artisan media:localize-remote             # তারপর আসলটা (আগে ডাটাবেস ব্যাকআপ নিন)
```

## ৬ক. ডেমো ডাটা (ঐচ্ছিক — Clothing)

৫টা ক্যাটাগরি (পাঞ্জাবি, শার্ট, টি-শার্ট, প্যান্ট, বোরকা/আবায়া) × ৪টা প্রোডাক্ট, সাইজ S/M/L/XL-সহ, ছবিসহ। সব ডেমো সারি `is_demo` দিয়ে চিহ্নিত, ছবিগুলোর নাম `demo-*.png`। আসল ডাটায় হাত দেয় না; বারবার চালালেও ডুপ্লিকেট হয় না। `migrate --seed` এটা যোগ করে না — আলাদা করে চালাতে হয়:

```bash
php artisan migrate --force
php artisan db:seed --class=DemoClothingSeeder --force
```

পরে শুধু ডেমো ডাটা মুছতে (আগে `--dry-run` দিয়ে দেখে নিন):

```bash
php artisan demo:remove --dry-run
php artisan demo:remove
```

কোনো ডেমো প্রোডাক্ট কাস্টমার অর্ডার করে থাকলে সেটা মোছা হয় না (অর্ডারের ইতিহাস ঠিক রাখতে) — শুধু শপ থেকে লুকানো হয়।

## ৭. Cache warm করুন (production performance)

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

> পরে `.env` বদলালে অবশ্যই `php artisan config:clear` (বা আবার `config:cache`) চালান, নাহলে পুরনো config-ই থেকে যাবে।

## ৭ক. Queue ও Cron (SMS, কুরিয়ার)

কাস্টমার SMS ও কুরিয়ারে পাঠানো এখন queue-তে যায় (`QUEUE_CONNECTION=database`), তাই ধীর SMS/কুরিয়ার API চেকআউট আটকায় না।
Shared hosting-এ সবসময় চলা worker নেই, তাই **একটি cron job** দিয়ে প্রতি মিনিটে Laravel scheduler চালাতে হবে — সেটাই queue খালি করে।

hPanel → **Advanced → Cron Jobs** → নতুন Cron Job (প্রতি মিনিট: `* * * * *`):

```
* * * * * cd /home/<hostinger-user>/domains/<আপনার-ডোমেইন>/public_html && php artisan schedule:run >> /dev/null 2>&1
```

> `<hostinger-user>` ও পাথ hPanel-এ Cron Jobs পেজে বা File Manager-এর উপরে দেখা যায়। PHP-র পূর্ণ পাথ লাগলে (যেমন `/usr/bin/php` বা `/opt/alt/php83/usr/bin/php`) `php`-এর জায়গায় সেটা দিন।

যাচাই: একটা COD অর্ডার দিয়ে ১–২ মিনিট পর `orders`-এর SMS log (Admin → SMS) দেখুন। জমে থাকা jobs দেখতে: `php artisan queue:work --stop-when-empty` হাতে চালান; ব্যর্থ jobs: `php artisan queue:failed`।
Cron না চালালে SMS/কুরিয়ার job `jobs` টেবিলে জমে থাকবে, পাঠানো হবে না।

## ৮. Admin login

- URL: `https://yourdomain.com/admin`
- Default (seed থেকে): **username `admin`, password `admin123`**
- ⚠️ **লগইন করার সাথে সাথেই password পরিবর্তন করুন।**

## ৯. Live-test checklist

Deploy করার পর এই জিনিসগুলো একবার হাতে-কলমে যাচাই করুন:

- [ ] Homepage লোড হচ্ছে (hero banner, category, hot deals)
- [ ] `/shop`, `/product/{slug}` — প্রোডাক্ট দেখা যাচ্ছে
- [ ] Cart-এ যোগ করা → `/checkout` → order submit → `/order/{token}` কনফার্মেশন
- [ ] `/track` — **ফোন নাম্বার ছাড়া কখনো order দেখাবে না** (security টেস্ট)
- [ ] `/customer/login`, `/customer/register` কাজ করছে
- [ ] `/admin` লগইন কাজ করছে, dashboard এ সংখ্যা দেখাচ্ছে
- [ ] Admin থেকে Product/Banner/Brand/Blog/Site Setting/Media Library-তে image upload; আপলোডের পর সাইটে ছবি দেখা যাচ্ছে (`/storage/media/…`)
- [ ] GTM ID admin panel-এ বসিয়ে homepage-এর view-source এ script/noscript দেখা যাচ্ছে
- [ ] `/api/feed/facebook` খুললে XML ফিরে আসছে
- [ ] `/sitemap.xml` ও `/robots.txt` খুলছে; robots.txt-এ আপনার ডোমেইনের Sitemap লাইন আছে
- [ ] একটা COD অর্ডারের পর ১–২ মিনিটের মধ্যে SMS যাচ্ছে (Cron/queue কাজ করছে)
- [ ] `/customer/account` — লগইন করে অর্ডার হিস্ট্রি ও ঠিকানা সেভ কাজ করছে
- [ ] ভুল URL (`/xyz`) খুললে সাইটের ডিজাইনে ৪০৪ পেজ আসছে
- [ ] Response header-এ `X-Frame-Options`, `X-Content-Type-Options` আছে, `X-Powered-By` নেই
- [ ] Order-এ courier "Send to Steadfast" (আসল API key দিলে) কাজ করছে; Pathao/RedX (mock) consignment id বানাচ্ছে

## সমস্যা হলে

- **500 error / সাদা পেজ:** `storage/logs/laravel.log` দেখুন। প্রায়ই কারণ: `.env` এ ভুল DB credential, অথবা `APP_KEY` ফাঁকা (`php artisan key:generate` চালাননি)।
- **CSS/JS লোড হচ্ছে না:** `npm run build` চালিয়েছেন কিনা, আর Document Root ঠিক `public/` ফোল্ডারে সেট আছে কিনা দেখুন।
- **"Permission denied" storage error:** `chmod -R 775 storage bootstrap/cache` আবার চালান, আর `.env` ফাইলের owner ঠিক আছে কিনা দেখুন।

## ৮. অ্যাডমিন লিংক বদলানো ও ফিরিয়ে আনা

- অ্যাডমিন প্যানেলে **সেটিংস → অ্যাডমিন লিংক** থেকে `/admin`-এর বদলে অন্য পথ (যেমন `my-panel-2026`) রাখা যায়। শুধু ছোট হাতের ইংরেজি অক্ষর, সংখ্যা ও হাইফেন চলে। সেভের আগে নতুন লগইন লিংক দেখানো হয় — সেটা অবশ্যই লিখে রাখুন। বদলের পর পুরনো `/admin`-এ 404 আসে।
- পথটি `storage/app/admin-path.txt` ফাইলে থাকে (`git pull` এতে হাত দেয় না)। `route:cache` চালানো থাকলে সেভের সময় নিজে থেকে মুছে যায়; না মুছলে `php artisan route:clear` চালান।
- **লিংক ভুলে গেলে** সার্ভারে (SSH) চালান — `/admin` ফিরে আসবে:

```bash
php artisan admin:reset-path
```

(SSH না থাকলে File Manager থেকে `storage/app/admin-path.txt` ফাইলটি মুছে দিলেও একই কাজ হয়।)

## ৯. অ্যাডমিন পাসওয়ার্ড ও ব্যবহারকারী

- **নিজের পাসওয়ার্ড:** প্যানেলের উপরের ডানের ব্যবহারকারী-মেনু → Profile। পুরনো পাসওয়ার্ড, নতুন (ন্যূনতম ৮ অক্ষর), আবার নতুন।
- **ব্যবহারকারী:** সেটিংস → অ্যাডমিন ব্যবহারকারী (শুধু super admin)। নতুন যোগ, edit, পাসওয়ার্ড রিসেট (চাবি-আইকন), নিষ্ক্রিয়/সক্রিয়, মোছা। নিষ্ক্রিয় অ্যাডমিন লগইন করতে পারে না। ইউজারনেম অথবা (দেওয়া থাকলে) ইমেইল দিয়ে লগইন চলে।
- **সুরক্ষা:** শেষ সক্রিয় super admin-কে মোছা, নিষ্ক্রিয় করা বা রোল বদলানো যায় না; নিজেকে মোছা বা নিষ্ক্রিয় করা যায় না।
- **পাসওয়ার্ড ভুলে গেলে (সার্ভারে):** পাসওয়ার্ড দুবার জিজ্ঞেস করবে (লেখা দেখা যাবে না)। অ্যাকাউন্ট নিষ্ক্রিয় থাকলে সেটাও চালু হয়ে যায়।

```bash
php artisan admin:reset-password ইউজারনেম
```

(ইউজারনেম না দিলে তালিকা থেকে বাছতে বলবে।) এই আপডেটের জন্য `php artisan migrate --force` চালাতে হবে (অ্যাডমিন টেবিলে নাম, ইমেইল, সক্রিয় কলাম যোগ হয়)।

## ১০. ট্র্যাকিং ও স্বয়ংক্রিয় আইপি ব্লক

- **ট্র্যাকিং:** অ্যাডমিন → সেটিংস → জিটিএম / পিক্সেল। GTM আইডি `GTM-ABC1234` ফরম্যাটে না হলে সেভ হয় না। "অর্ডার দেওয়ার ইভেন্ট"-এর নাম (ডিফল্ট `OrderPlaced`) এখান থেকে বদলানো যায়। বট ও সার্চ ইঞ্জিনের ক্রলার থেকে কোনো ট্র্যাকিং যায় না।
- **অর্ডারের সাথে রাখা হয়:** Pixel-এর `_fbp`/`_fbc` কুকি, ব্রাউজার (user agent), যে পেজ থেকে অর্ডার, GA client id (আইপি আগে থেকেই `ip_address`-এ ছিল) — পরে সার্ভার থেকে Purchase পাঠানোর জন্য।
- **স্বয়ংক্রিয় আইপি ব্লক:** এক আইপি থেকে ৬০ সেকেন্ডে ১৫০টির বেশি রিকোয়েস্ট এলে ৩০ মিনিটের জন্য ব্লক (সীমা বদলানো যায়: সেটিংস → আইপি ব্লক → "স্বয়ংক্রিয় ব্লকের সীমা")। ব্লক হওয়া আইপি ওই তালিকায় "স্বয়ংক্রিয়" হিসেবে দেখায়। সাইন-ইন করা অ্যাডমিন এবং গত ৩০ দিনে প্যানেলে ঢোকা আইপি কখনো ব্লক হয় না; প্রাইভেট/রিজার্ভড আইপিও না।
- মেয়াদ শেষ হওয়া স্বয়ংক্রিয় ব্লক ঘণ্টায় একবার তালিকা থেকে মুছে যায় (Cron-এর `schedule:run` লাগে, ৭ক দেখুন)।
- এই আপডেটের জন্য `php artisan migrate --force` চালাতে হবে।

## ১১. সার্ভার থেকে Purchase (Meta Conversions API ও GA4)

- ব্রাউজার থেকে আর Purchase যায় না। অর্ডার **কনফার্মড** (অ্যাডমিন থেকে বদলানো যায়) বা তার পরের স্ট্যাটাসে পৌঁছালে সার্ভার Meta (Conversions API) ও GA4 (Measurement Protocol) দুটোতেই Purchase পাঠায় — প্রতি অর্ডারে একবার। ব্রাউজার থেকে শুধু অর্ডার দেওয়ার মুহূর্তে `OrderPlaced` যায়।
- **সেটআপ:** অ্যাডমিন → সেটিংস → জিটিএম / পিক্সেল। Meta Pixel ID + Conversions API access token, GA4 Measurement ID + API secret। দুটো secret এনক্রিপ্টেড থাকে (`APP_KEY` দিয়ে), সেভের পর শুধু শেষ ৪ অক্ষর দেখায়। **`APP_KEY` বদলালে secret দুটো আবার দিতে হবে।**
- **Test connection:** Meta-র জন্য Test Event Code লাগে (Events Manager → Test events); কোড ছাড়া পাঠায় না। GA4 টেস্ট শুধু যাচাই করে, কিছু রেকর্ড করে না।
- **Test Event Code ফাঁকা করতে ভুলবেন না:** সেভ করা থাকলে সার্ভারের সব আসল Purchase শুধু "Test events"-এ যায়।
- **কিউ ও Cron:** Purchase কিউতে যায় (`QUEUE_CONNECTION=database`), তাই Cron-এর `schedule:run` চালু থাকতে হবে (৭ক)। ব্যর্থ হলে ১, ৫ ও ১৫ মিনিট পর আবার চেষ্টা করে।
- **অর্ডারের পাতায়** Meta ও GA4 আলাদা দেখায় (গেছে / ব্যর্থ + কারণ), আর "Purchase আবার পাঠান" বাটন আছে।
- GTM না থাকলে GA4 Measurement ID দিলে সরাসরি gtag লোড হয়। GTM থাকলে লোড হয় না (দ্বিগুণ এড়াতে) — তখন GA4 ট্যাগ GTM-এ বসাতে হবে।
- এই আপডেটের জন্য `php artisan migrate --force` চালাতে হবে।
