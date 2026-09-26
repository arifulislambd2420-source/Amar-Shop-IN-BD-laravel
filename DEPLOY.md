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

QUEUE_CONNECTION=sync

# Cloudinary (image upload — Product/Banner/Site Setting logo+favicon)
CLOUDINARY_CLOUD_NAME=...
CLOUDINARY_API_KEY=...
CLOUDINARY_API_SECRET=...

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

## ৬. Storage link + permission

```bash
php artisan storage:link
chmod -R 775 storage bootstrap/cache
```

## ৭. Cache warm করুন (production performance)

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

> পরে `.env` বদলালে অবশ্যই `php artisan config:clear` (বা আবার `config:cache`) চালান, নাহলে পুরনো config-ই থেকে যাবে।

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
- [ ] Admin থেকে Product/Banner/Site Setting-এ image upload (Cloudinary env সঠিক থাকলে)
- [ ] GTM ID admin panel-এ বসিয়ে homepage-এর view-source এ script/noscript দেখা যাচ্ছে
- [ ] `/api/feed/facebook` খুললে XML ফিরে আসছে
- [ ] Order-এ courier "Send to Steadfast" (আসল API key দিলে) কাজ করছে; Pathao/RedX (mock) consignment id বানাচ্ছে

## সমস্যা হলে

- **500 error / সাদা পেজ:** `storage/logs/laravel.log` দেখুন। প্রায়ই কারণ: `.env` এ ভুল DB credential, অথবা `APP_KEY` ফাঁকা (`php artisan key:generate` চালাননি)।
- **CSS/JS লোড হচ্ছে না:** `npm run build` চালিয়েছেন কিনা, আর Document Root ঠিক `public/` ফোল্ডারে সেট আছে কিনা দেখুন।
- **"Permission denied" storage error:** `chmod -R 775 storage bootstrap/cache` আবার চালান, আর `.env` ফাইলের owner ঠিক আছে কিনা দেখুন।
