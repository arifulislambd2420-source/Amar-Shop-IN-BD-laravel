# আমারশপ — Next.js → Laravel Migration Plan

**Status:** Phase 1 (audit & plan). The current Next.js app stays live and untouched until the Laravel rewrite is fully built and tested in a **separate folder/repo**.

## Target stack
- **Laravel** (latest stable) + **Blade + Livewire** (storefront)
- **Filament** (admin panel)
- **MySQL** — reuse the existing schema via Laravel migrations
- **Node** only locally for asset build (Vite); **not required on the server** (Hostinger shared PHP hosting runs it natively)

## Source app (what we are migrating FROM)
- Next.js 16 (App Router, TypeScript) + `mysql2` (raw SQL) + Tailwind + Zustand (cart/wishlist)
- Auth: admin JWT cookie (`jose`) + separate customer JWT cookie; bcrypt password hashing
- Deployed on Hostinger "Deploy Web App" (Node)

---

## 1. Database tables (22) — reuse schema, write Laravel migrations

> Money = `DECIMAL(10,2)`. Booleans stored as `TINYINT(1)`. All ids `INT AUTO_INCREMENT`.

### Catalog
| Table | Columns |
|---|---|
| `categories` | id, name, slug (unique), icon |
| `brands` | id, name, logo |
| `products` | id, name, slug (unique), description, price, sale_price, image, category_id (FK), brand_id (FK), stock, is_active, status (draft/published/hidden/outofstock/archived), sku (unique, nullable), cost_price, seo_title, meta_description, tags, created_at, updated_at, deleted_at (soft-delete) |
| `product_variants` | id, product_id (FK, cascade), label, price, stock |
| `product_images` | id, product_id (FK, cascade), url, alt, sort_order |
| `reviews` | id, product_id (FK, cascade), customer_name, rating (1–5 check), comment, approved, created_at |

### Orders
| Table | Columns |
|---|---|
| `orders` | id, order_token (unique), invoice_no (unique), customer_name, phone, email, district, thana, postcode, address, payment_method, payment_status, advance_amount, status, subtotal, shipping_fee, discount, total, notes, consignment_id, tracking_code, courier_status, created_at |
| `order_items` | id, order_id (FK, cascade), product_id (FK), product_name, unit_price, quantity, discount, line_total |

### Marketing / promotions
| Table | Columns |
|---|---|
| `coupons` | id, code (unique), discount_type (percent/fixed), discount_value, min_spend, uses, max_uses, valid_until, is_active, created_at |
| `flash_sales` | id, title, end_time, is_active, created_at |
| `flash_sale_items` | id, flash_sale_id (FK, cascade), product_id (FK, cascade), flash_price |
| `banners` | id, image, link, position (hero/side/promo), sort_order, active |
| `blogs` | id, title, slug (unique), cover, category, content, read_time, published_at |

### Users / auth
| Table | Columns |
|---|---|
| `admin_users` | id, username (unique), password_hash, role (default super_admin) |
| `users` (customers) | id, name, phone (unique), password_hash, created_at |
| `contact_messages` | id, name, phone, email, subject, message, created_at |

### Settings / system
| Table | Columns |
|---|---|
| `site_settings` | id, setting_key (unique), setting_value — holds site_logo, site_favicon, site_name, gtm_id, mail_* (SMTP), steadfast_* (courier), etc. |
| `fraud_api_configs` | id, type, api_url, api_key, active |
| `ip_blocks` | id, ip (unique), reason, created_at |
| `media_library` | id, file_name, file_path, mime_type, file_size, created_at |

### Live page editor (inline CMS)
| Table | Columns |
|---|---|
| `page_contents` | id, page_key, section_key, element_key, content_type, content_value, settings_json, is_published, version, updated_by, updated_at — unique (page_key, section_key, element_key) |
| `content_revisions` | id, content_id (FK, cascade), content_value, settings_json, version, changed_by, created_at |

**Laravel notes:** map each to an Eloquent model. Use Laravel's `password` (Hash::make = bcrypt) — existing bcrypt hashes are compatible, so admin/customer passwords carry over. `deleted_at` on products → use `SoftDeletes` trait. `site_settings` → a key/value settings model (or spatie/laravel-settings).

---

## 2. Public / storefront pages (→ Blade + Livewire)
| Route | Purpose |
|---|---|
| `/` | Home: hero slider, category slider, hot deals, flash sale, category sections, brand strip, blog preview, promo banners |
| `/shop` | Product grid + category/brand/sort/pagination + search (`q`) |
| `/offers` | Discounted products |
| `/product/{slug}` | Product detail + variants + reviews + related; fires `view_item` |
| `/cart` | Cart page (client state) |
| `/checkout`, `/checkout/pay` | Checkout + payment step; fires `begin_checkout`/`purchase` |
| `/order/{token}` | Order confirmation (by secure token) |
| `/track` | Order tracking (phone REQUIRED — no IDOR) |
| `/brands` | Brand grid + search |
| `/blog`, `/blog/{slug}` | Blog list + detail |
| `/contact` | Contact form → contact_messages |
| `/about`, `/privacy`, `/terms`, `/returns`, `/delivery`, `/policy/{slug}` | Policy pages |
| `/customer/login`, `/customer/register` | Customer auth |

## 3. Admin pages (→ Filament resources/pages)
Dashboard (KPIs + date range + sales chart + top products + recent orders), Orders (list + edit + invoice print + courier dispatch), Products (list/filter/bulk + add/edit with variants/images/SEO + import/export + trash), Categories, Reviews (moderation), Banners, Blogs, Coupons, Flash-sales, Users (customers), Reports, IP Block, and Settings: General hub, Site Setting (logo/favicon), SMTP, Fraud API, GTM/Pixel, Courier.

## 4. API endpoints (→ Laravel routes/controllers; internal calls become Livewire actions)
- **Public:** products, product/{slug}, categories, brands, blogs, blogs/{slug}, search, reviews (GET+POST), contact, cart/apply-coupon, flash-sales/active, orders (create), orders/track, orders/payment, feed/facebook (Google Merchant/Meta XML)
- **Customer auth:** auth/register, auth/login, auth/logout, auth/otp/send, auth/otp/verify
- **Admin (guarded):** full CRUD for products (+variants/images/duplicate/trash/restore/bulk/export/import), orders (+courier: pathao/redx/steadfast), categories, banners, blogs, coupons, flash-sales, reviews, ip-block, settings (site/smtp/fraud-api/gtm/courier), content (live editor), media, upload, stats

## 5. Integrations to port
- **Image upload → Cloudinary** (`/api/admin/upload`) → Laravel: `cloudinary-labs/cloudinary-laravel` or Filament FileUpload with a Cloudinary disk.
- **GTM + Pixel dataLayer events** (view_item/add_to_cart/begin_checkout/purchase) → Blade JS helper + gtm_id from settings.
- **Product XML feed** (Google Merchant/Meta compatible) → Laravel route returning XML.
- **Courier**: Steadfast (live API), Pathao/RedX (currently mock) → Laravel services.
- **OTP/SMS** (`sms.ts`, auth/otp) → Laravel SMS gateway service.
- **Payment** (advance_amount, payment_status, /checkout/pay, orders/payment) → port the existing COD+advance flow.
- **Inline live editor** (page_contents/content_revisions) → Filament-managed content + a Blade `<x-editable>` component (lower priority; can ship after core).

## 6. Security invariants to preserve (do NOT regress)
- Order tracking ALWAYS requires phone (no order-id-only lookup).
- Order confirmation by random `order_token`, never sequential id.
- Stock check + decrement atomic (DB transaction + row lock) incl. variants.
- Every admin route/page behind real admin auth (Filament auth guard).
- `ADMIN_SESSION_SECRET`-style secret → Laravel `APP_KEY` (framework-managed).
- No `password_hash` ever returned to client.
- CSV import: SKU-only matching + formula-injection defense.

---

## 7. Phased execution (stop & review after each)
1. **✅ This file** — feature/DB/API audit → `MIGRATION_PLAN.md`.
2. Laravel setup + `.env` + migrations + Eloquent models (reuse MySQL schema).
3. Auth — admin (Filament) + customer (Livewire), bcrypt hashes carry over.
4. Filament admin: dashboard, orders, products+variants, categories, banners, blogs, coupons, flash-sales, site settings, users.
5. Storefront: home, shop, product, cart, checkout, blog, brands.
6. Integrations: Cloudinary upload, GTM/Pixel events, courier, product XML feed.
7. Hostinger deploy guide + live test.
8. Cutover: point domain to Laravel, keep Next.js as backup.

## 8. Env vars the Laravel app will need
`APP_KEY` (php artisan key:generate), `DB_*` (reuse existing MySQL), `CLOUDINARY_*`, mail (SMTP) + courier + SMS creds (or read from `site_settings`), `APP_URL`.

---

*Generated in Phase 1. Nothing in the Next.js app was modified.*
