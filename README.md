# আমারশপ — Laravel সংস্করণ (amarshop-laravel)

**আমারশপ** ই-কমার্স সাইটের নতুন সংস্করণ, **Laravel** দিয়ে নতুন করে বানানো — উদ্দেশ্য: Hostinger shared hosting-এ Node.js ছাড়াই native চালানো।

## উৎস

- পুরনো সাইট (Next.js + TypeScript): `arifulislambd2420-source/Amar-Shop-IN-BD` — লাইভ ও ব্যাকআপ হিসেবে অপরিবর্তিত।
- পূর্ণ feature/DB/API তালিকা ও পরিকল্পনা: [`MIGRATION_PLAN.md`](MIGRATION_PLAN.md)।

## Stack

- **Laravel 13** (PHP ^8.3) + **Blade + Livewire** (storefront)
- **Filament 5** (admin panel)
- **Tailwind CSS 4 + Vite 8** (লোকালে বিল্ড; বিল্ড করা `public/build` গিটে থাকে, সার্ভারে Node লাগে না)
- **DB:** লোকালে **SQLite**, production-এ **MySQL** (Hostinger)। Migration দুটোতেই চলে।
- Queue: `database` driver + একটি cron job (দেখুন [DEPLOY.md](DEPLOY.md) §৭ক)

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

> PHP-তে **`intl`** এক্সটেনশন চালু থাকতে হবে (Filament-এর টেবিল তারিখ/সংখ্যা ফরম্যাটে লাগে)।

## টেস্ট

```bash
php artisan test
```

টেস্ট মেমরি-SQLite-এ চলে (`RefreshDatabase`) — আপনার আসল ডাটাবেসে হাত দেয় না। চেকআউট, ল্যান্ডিং অর্ডার, ডেলিভারি চার্জ, ডুপ্লিকেট অর্ডার, অর্ডার ট্র্যাকিং, SEO/sitemap, ট্র্যাকিং ইভেন্ট, কাস্টমার অ্যাকাউন্ট, অ্যাডমিন রোল কভার করা আছে।

## প্রধান ফিচার

- **স্টোর:** শপ, প্রোডাক্ট (গ্যালারি, রিভিউ, রিলেটেড), কার্ট, চেকআউট (COD + bKash), অর্ডার ট্র্যাকিং, কাস্টমার অ্যাকাউন্ট (অর্ডার হিস্ট্রি, সেভ ঠিকানা), ব্লগ, ব্র্যান্ড, ইনফো পেজ।
- **ল্যান্ডিং পেজ:** ব্লক-বিল্ডার (green / purple / cream) + পুরনো template-1/2/3; প্যাকেজ, সাইজ/কালার, রিপোর্ট (ভিজিট/অর্ডার/কনভার্শন)।
- **অ্যাডমিন (`/admin`):** ড্যাশবোর্ড (চার্ট, কম স্টক সতর্কতা), অর্ডার, **Incomplete Orders** (কল করে অর্ডারে রূপান্তর), কুপন, ফ্ল্যাশ সেল, মিডিয়া লাইব্রেরি, Site Setting (নাম, রং, কনটাক্ট, SEO, ডেলিভারি চার্জ, অর্ডার সুরক্ষা, স্টক সীমা), Info Pages (rich text), GTM/Meta Pixel, SMS, bKash, কুরিয়ার, ফ্রড/IP ব্লক।
- **অ্যাডমিন রোল:** সুপার অ্যাডমিন (সব) · ম্যানেজার (সেটিং ও অ্যাডমিন ইউজার ছাড়া সব) · অর্ডার স্টাফ (শুধু অর্ডার)। Settings → Admin Users থেকে বেছে দেওয়া যায়।
- **নিরাপত্তা:** ফর্মে rate limit, security header, একই ফোন থেকে পরপর অর্ডার আটকানো (Site Setting-এ সময়), নিরাপদ trusted-proxy সেটিং (`TRUSTED_PROXIES`)।
- **SEO ও ট্র্যাকিং:** canonical, OG, Product/Organization JSON-LD, ডায়নামিক `sitemap.xml` ও `robots.txt`; GTM + Meta Pixel (PageView, ViewContent, AddToCart, InitiateCheckout, Purchase — `event_id`সহ)।

## Progress (দেখো MIGRATION_PLAN.md)

- [x] Phase 1 — অডিট ও পরিকল্পনা
- [x] Phase 2 — setup + migration + model
- [x] Phase 3 — Auth (admin + customer)
- [x] Phase 4 — Filament admin
- [x] Phase 5 — Storefront
- [x] Phase 6 — Integration
- [x] Phase 7 — Hostinger deploy guide ([DEPLOY.md](DEPLOY.md))
- [ ] Phase 8 — Cutover ([CUTOVER.md](CUTOVER.md) প্ল্যান তৈরি; বাস্তবায়ন বাকি — লাগবে আপনার GitHub push + Hostinger deploy + live-test)