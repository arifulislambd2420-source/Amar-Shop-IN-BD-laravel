# Phase 8 — Cutover Plan (Next.js → Laravel)

এই ধাপে যাওয়ার **শর্ত**: [DEPLOY.md](DEPLOY.md) অনুযায়ী Laravel app Hostinger-এ deploy হয়ে গেছে এবং তার **Live-test checklist**-এর সবকটা আইটেম আপনি নিজে হাতে-কলমে verify করেছেন (একটা আলাদা subdomain বা স্টেজিং URL-এ, main domain সুইচ করার আগে)।

## নীতি: zero-downtime, সহজে ফিরে আসা যায় এমন cutover

Next.js app টা কোথাও মুছে ফেলা হবে না — শুধু **কোন app-এ ট্রাফিক যাবে** সেটা বদলাবে। MySQL database একই থাকছে দুই app এর জন্য, তাই ডাটা হারানোর ঝুঁকি নেই।

## ধাপ ১ — স্টেজিং-এ পূর্ণ পরীক্ষা

Laravel app টা **আলাদা subdomain** এ (যেমন `laravel-test.amarshopinbd.com` বা `new.amarshopinbd.com`) দাঁড় করান, main domain না ছুঁয়ে:

- hPanel → Websites → এই subdomain-এর জন্য একটা নতুন website entry বানান, Document Root = Laravel repo-র `public/`
- একই MySQL database ব্যবহার করুন (production data-র সাথে টেস্ট)
- পুরো DEPLOY.md checklist ওখানে চালান

**অন্তত ২৪–৪৮ ঘণ্টা** এভাবে রেখে monitor করুন — বিশেষ করে:
- Order tracking এ ফোন ছাড়া কখনো ডাটা দেখাচ্ছে না
- Checkout করে আসল order MySQL এ ঠিকভাবে সেভ হচ্ছে (Next.js app যেটা পড়বে সেই একই টেবিলে)
- Admin login ও সব resource ঠিক আছে

## ধাপ ২ — Database সামঞ্জস্য নিশ্চিত করা

দুটো app **একই MySQL database** শেয়ার করছে বলে, cutover-এর আগমুহূর্ত পর্যন্ত Next.js app টাই লাইভ থাকবে এবং ডাটা লিখবে। এতে করে:

- Laravel migration যেন Next.js-এর বানানো schema-র সাথে **conflict না করে** — deploy করার সময় `php artisan migrate --force` চালান (সিড না করে, ইতিমধ্যে ডাটা আছে বলে), migration idempotent-ভাবে লেখা তাই নিরাপদ।
- Cutover-এর ঠিক আগে **database backup নিন** (hPanel → Databases → phpMyAdmin → Export, অথবা `mysqldump`)।

## ধাপ ৩ — DNS/Document Root সুইচ (আসল cutover মুহূর্ত)

**সবচেয়ে কম ঝুঁকিপূর্ণ পদ্ধতি: main domain-এর Document Root বদলানো** (DNS বদলানোর দরকার নেই, তাই সাথে সাথে effective, আর সাথে সাথে ফিরিয়েও আনা যায়):

1. hPanel → Websites → `online.amarshopinbd.com` → **Document Root পরিবর্তন করুন**: Next.js app-এর ফোল্ডার থেকে Laravel repo-র `public/` ফোল্ডারে।
2. Laravel-এর `.env` এ `APP_URL=https://online.amarshopinbd.com` বসান (subdomain টেস্টের সময় যা ছিল তা বদলে) এবং `php artisan config:cache` আবার চালান।
3. তৎক্ষণাৎ main domain খুলে homepage, admin login, checkout — মূল কয়েকটা জিনিস আবার যাচাই করুন।

**Rollback (কিছু ভুল হলে):** শুধু Document Root আবার Next.js app-এর ফোল্ডারে ফিরিয়ে দিন — সাথে সাথে পুরনো সাইট আবার লাইভ। এই কারণেই Next.js app মুছে ফেলা হয়নি ও ফেলা উচিত না।

## ধাপ ৪ — Next.js app ব্যাকআপ হিসেবে রাখা

- Next.js app-এর Node process (Hostinger "Deploy Web App") **বন্ধ করবেন না তৎক্ষণাৎ** — অন্তত ১ সপ্তাহ রেখে দিন যাতে দ্রুত rollback সম্ভব হয়।
- GitHub-এ পুরনো repo (`Amar-Shop-IN-BD`) অপরিবর্তিত থাকছে — এটাই permanent backup।
- হোস্টিং-এর ফাইল/ফোল্ডারও অন্তত কিছুদিন মুছবেন না, ট্রাফিক স্থিতিশীল হওয়া পর্যন্ত।

## ধাপ ৫ — Cutover-পরবর্তী মনিটরিং (প্রথম ৪৮ ঘণ্টা)

- `storage/logs/laravel.log` নিয়মিত দেখুন
- কয়েকটা আসল অর্ডার নিজে বসিয়ে সম্পূর্ণ flow (checkout → confirmation → admin-এ status change → courier dispatch) verify করুন
- GTM/Pixel event সত্যিই fire করছে কিনা GTM Preview মোডে দেখুন
- কোনো customer complaint এলে দ্রুত ধরার জন্য phone/email নজরে রাখুন

## যখন নিশ্চিত হবেন সব ঠিক আছে (~১–২ সপ্তাহ পর)

- Next.js app-এর Node process বন্ধ করতে পারেন (হোস্টিং resource বাঁচাতে) — কিন্তু GitHub repo, ও চাইলে একটা শেষ DB backup, স্থায়ীভাবে রেখে দিন।
- এই `CUTOVER.md`-এর চেকলিস্ট সম্পূর্ণ হলে README-এর Phase 8 বক্স টিক দিন।
