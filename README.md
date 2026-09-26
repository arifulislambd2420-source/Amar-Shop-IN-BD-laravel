# আমারশপ — Laravel সংস্করণ (amarshop-laravel)

**আমারশপ** ই-কমার্স সাইটের নতুন সংস্করণ, **Laravel** দিয়ে নতুন করে বানানো হচ্ছে — উদ্দেশ্য: Hostinger shared hosting-এ Node.js ছাড়াই native চালানো।

## উৎস

- পুরনো সাইট (Next.js + TypeScript): `arifulislambd2420-source/Amar-Shop-IN-BD` — এটা লাইভ ও ব্যাকআপ হিসেবে অপরিবর্তিত।
- পূর্ণ feature/DB/API তালিকা ও পরিকল্পনা: [`MIGRATION_PLAN.md`](MIGRATION_PLAN.md)।

## Stack

- **Laravel 12** + **Blade + Livewire** (storefront)
- **Filament** (admin panel)
- **DB:** লোকালে **SQLite** (install লাগে না), production-এ **MySQL** (Hostinger)। Migration দুটোতেই চলে।
- **Node** শুধু লোকালে asset build (Vite); সার্ভারে লাগবে না।

## চালানো (লোকালে)

```bash
composer install
npm install
cp .env.example .env          # প্রথমবার
php artisan key:generate
php artisan migrate --seed     # SQLite ডিফল্ট
npm run dev                    # আলাদা টার্মিনাল
php artisan serve
```

সাইট: `http://localhost:8000` · Admin: `http://localhost:8000/admin`

## Progress (দেখো MIGRATION_PLAN.md)

- [x] Phase 1 — অডিট ও পরিকল্পনা
- [ ] Phase 2 — setup + migration + model
- [ ] Phase 3 — Auth (admin + customer)
- [ ] Phase 4 — Filament admin
- [x] Phase 5 — Storefront
- [x] Phase 6 — Integration
- [x] Phase 7 — Hostinger deploy guide ([DEPLOY.md](DEPLOY.md))
- [ ] Phase 8 — Cutover (ডোমেইন সরানো — Hostinger-এ deploy ও live-test সফল হওয়ার পর)
