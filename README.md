# আমারশপ — Laravel সংস্করণ (amarshop-laravel)

এটা **আমারশপ** ই-কমার্স সাইটের নতুন সংস্করণ, **Laravel** দিয়ে নতুন করে বানানো হচ্ছে।

## এটা কী

আগের সাইটটা ছিল **Next.js + TypeScript** দিয়ে বানানো (রিপো: `arifulislambd2420-source/Amar-Shop-IN-BD`)। সেটা এখনো লাইভ ও ব্যাকআপ হিসেবে অপরিবর্তিত আছে। এই নতুন রিপোতে একই সাইট **PHP/Laravel** দিয়ে আবার বানানো হচ্ছে, যাতে Hostinger-এর সাধারণ (shared) হোস্টিং-এ কোনো Node.js ঝামেলা ছাড়াই native ভাবে চলে।

## Stack

- **Laravel** (latest stable) — মূল framework
- **Blade + Livewire** — storefront (দোকানের সামনের অংশ)
- **Filament** — admin panel
- **MySQL** — ডাটাবেজ (আগের সাইটের একই schema reuse করা হচ্ছে)
- **Node** শুধু লোকালে CSS/JS build (Vite) এর জন্য; **সার্ভারে Node লাগবে না**

## কোথা থেকে এসেছে

- উৎস রিপো (পুরনো Next.js): `arifulislambd2420-source/Amar-Shop-IN-BD`
- পুরো feature/DB/API তালিকা ও রূপান্তর পরিকল্পনা: [`MIGRATION_PLAN.md`](MIGRATION_PLAN.md)

## কীভাবে চালাবে (লোকালে)

> প্রথমবার লাগবে: **PHP 8.2+**, **Composer**, এবং একটা **MySQL** ডাটাবেজ।

```bash
# ১. dependency ইনস্টল
composer install
npm install

# ২. এনভায়রনমেন্ট ফাইল তৈরি ও app key
cp .env.example .env
php artisan key:generate

# ৩. .env এ DB তথ্য বসাও (DB_DATABASE, DB_USERNAME, DB_PASSWORD)

# ৪. টেবিল তৈরি + স্যাম্পল ডাটা
php artisan migrate --seed

# ৫. asset build + সার্ভার চালু
npm run dev        # আলাদা টার্মিনালে
php artisan serve
```

তারপর ব্রাউজারে `http://localhost:8000` খোলো। Admin panel: `http://localhost:8000/admin`।

## অবস্থা (Progress)

রূপান্তর ধাপে ধাপে হচ্ছে (দেখো `MIGRATION_PLAN.md`):

- [x] Phase 1 — feature/DB/API অডিট ও পরিকল্পনা
- [ ] Phase 2 — Laravel setup + migration + model
- [ ] Phase 3 — Auth (admin + customer)
- [ ] Phase 4 — Filament admin
- [ ] Phase 5 — Storefront
- [ ] Phase 6 — Integration (Cloudinary, GTM/Pixel, courier, XML feed)
- [ ] Phase 7 — Hostinger deploy
- [ ] Phase 8 — Cutover (ডোমেইন সরানো)
