# Dabberhaly — Project Reference

**Last updated: 2026-11-21**

**Dabberhaly** (دبّرهالي) is an Egyptian e-commerce platform for practical household essentials.
Built with **Laravel 10**, **Inertia.js 2**, **Vue 3**, and **Vuetify 3**. Arabic is the default
language, English is supported; the storefront is RTL in Arabic.

## Current status (2026-11-21)

- **Store currency is EGP (جنيه)** everywhere store-facing. **Moyasar stays SAR deliberately**
  (no Egyptian gateway is available yet; card payments charge the EGP order total numerically
  as SAR — testing only).
- **Launch phase 1 is COD-only** — card payment is deferred. There is **no server yet**;
  readiness is judged on the business / user-experience side.
- **Egypt-only store**: 27 governorates, country code `EG`. Since 2026-11-21 the customer
  **never picks a country** — Egypt is automatic in web register, web checkout, API register
  and API checkout (user + guest); the customer only picks the governorate.
- Work is on the **`stage` branch**; the user pulls `stage` to test. Never push to `main`
  without approval.

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 10 (PHP 8.1+, PHP 8.3 locally) |
| Frontend | Vue 3 + Inertia.js 2 |
| UI Library | Vuetify 3 (`dabbarhaTheme`) |
| Styling | Custom CSS (Vanilla Latte theme) |
| Auth | Laravel session-based (web guard) + Sanctum (API, `auth:user` guard) |
| Payment | Moyasar gateway (SAR) + cash on delivery |
| Queue | Database queue (`queue:work`, Supervisor in production) |
| Search/Locale | Arabic/English (vue-i18n stub + `useLocale` + Laravel locale) |
| Routing (JS) | Ziggy |
| SMS / OTP | moraSms gateway |
| Debug | Telescope (require-dev, local only) |

---

## Brand Theme

Theme name: **dabbarhaTheme** (Vuetify). Brand identity: **vanilla latte (الفانيليا لاتيه)**.

| Token | Hex | Usage |
|-------|-----|-------|
| Mocha Brown (primary) | `#76543D` | Buttons, links, nav, prices |
| Gradient end | `#8d6b51` | Gradient end |
| Caramel (secondary) | `#C69763` | Accents, badges, hover states |
| Vanilla Cream (background) | `#F7F0E5` | Page backgrounds |
| Latte Beige | `#E6D3BB` | Soft beige surfaces |
| Pure White (surface) | `#FFFFFF` | Cards, forms |
| Primary Text | `#302F2B` | Headings, body |
| Secondary Text | `#77736B` | Descriptions, metadata |
| Border Beige | `#D7CDBD` | Card borders, dividers |

CSS variables are defined in `resources/css/app.css` and loaded globally via `resources/js/app.js`.

---

## Project Structure

```
app/
  Http/Controllers/
    Inertia/        ← Customer-facing page controllers
    Api/            ← Mobile/API controllers (prefix api/v1)
    Dashboard/      ← Admin panel controllers (prefix /admin)
    Webhooks/       ← Moyasar webhook
  Http/Requests/    ← FormRequests (Auth, Api, Checkout, Dashboard, Front, ...)
  Http/Resources/   ← API resources
  Http/Middleware/  ← admin, guest.header, is_user_verified, changeLanguage, ...
  Models/           ← Eloquent models (39)
  Repositories/     ← Cart, Order, Reports, SendNewsToUser, Notification, ...
  Services/         ← CheckoutServices, Cart, Discount, UserService, VerificationServices, ...
  Rules/            ← EgyptianMobileNumber
  Helper/           ← ApiResponse, ArabicSearch, TextTranslate, ...
  Enums/            ← OrderStatus
  Events/           ← UserOrder, OrderReturned, NewsletterRequested, ...
  Listeners/        ← SendAdminNotification, SendReturnNotification, SendNewsletterMail, ...
  Notifications/    ← OrderCreated/Returned/ContactMessage (database), Newsletter (mail)
resources/
  js/
    Pages/          ← Vue pages (Inertia)
    layouts/        ← AppLayout, AppHeader, AppFooter
    components/     ← Shared components, home_page/
    composables/    ← useLocale, useCartFly, useCompare, useSeo, useToast, ...
  css/app.css       ← Brand styles
  views/dashboard/  ← Admin Blade views
routes/
  web.php           ← Customer-facing routes
  api.php           ← API routes (api/v1)
  dashboard.php     ← Admin routes (/admin prefix)
rules/rules.php     ← Admin permission registry (group → abilities)
lang/               ← ar.json / en.json + ar|en/*.php (auth, flash, general, messages, validation)
docs/DEPLOYMENT.md  ← Deployment guide
deploy/supervisor/  ← Queue worker supervisor config
```

---

## Customer-Facing Pages & Routes (`routes/web.php`)

### Public (no auth required)

| Method | Route | Name | Controller → Page |
|--------|-------|------|-------------------|
| GET | `/` | `home` | HomeController → Home.vue |
| GET | `/sitemap.xml` | `sitemap` | SitemapController (invokable, cached 1 day) |
| GET | `/products` | `products` | ProductController → Products/Index.vue |
| GET | `/products/{slug}` | `productss.show` | ProductController → Products/Show.vue (note double-`s` name) |
| GET | `/categories` | `categories.index` | CategoryController → Categories/Index.vue |
| GET | `/categories/{slug}` | `categories.show` | CategoryController → Categories/Show.vue |
| GET | `/brands` | `brands.index` | BrandsController → Brands/Index.vue |
| GET | `/brands/{id}` | `brands.show` | BrandsController → Brands/Show.vue |
| GET | `/offers` | `offers` | OfferProductController → Offers/Index.vue |
| GET | `/compare` | `compare` | CompareController → Compare.vue (`?ids=1,2,3`) |
| GET | `/search` | `search` | SearchController (JSON top 8, or redirect to `/products?search=`) |
| GET | `/contact-us` | `contact.index` | ContactUsController → ContactUs/Index.vue |
| POST | `/contact-us` | `contact.store` | ContactUsController |
| GET | `/bulk-order` | `bulk.index` | BulkOrderController → BulkOrder/Index.vue |
| POST | `/bulk-order` | `bulk.store` | BulkOrderController |
| GET | `/representative-order` | `representative.index` | RepresentativeOrderController → RepresentativeOrder/Index.vue |
| POST | `/representative-order` | `representative.store` | RepresentativeOrderController |
| GET | `/faq` | `faq` | StaticPageController → StaticPages/Questions.vue |
| GET | `/shipping-policy` | `shipping.policy` | StaticPageController → Page id 2 |
| GET | `/terms-conditions` | `terms.conditions` | StaticPageController → Page id 1 |
| GET | `/privacy-policy` | `privacy.policy` | StaticPageController → Page id 3 |
| GET | `/exchanges-returns` | `exchanges.returns` | StaticPageController → Page id 5 |
| POST | `/subscribe` | `newsletter.subscribe` | NewsletterController (409 if already subscribed) |
| POST | `/locale/{lang}` | `locale.switch` | Closure — session locale (`ar`/`en` only) |
| GET | `/reviews/{productId}` | `reviews.index` | ReviewController (public, JSON) |
| GET | `/up` | `up` | Closure — JSON `{status: ok}`, no DB |

### Auth Pages

| Method | Route | Name | Notes |
|--------|-------|------|-------|
| GET | `/login` | `login` | UserAuthController → Auth/Login.vue |
| POST | `/login` | `auth.login` | 5-attempt rate limit; merges guest cart |
| GET | `/register` | `register` | UserAuthController → Auth/Register.vue (cities prop only; **no country select** — Egypt is fixed text) |
| POST | `/register` | `auth.register` | `throttle:5,1`; stores data in session `pending_registration`, sends 4-digit OTP |
| GET | `/verify-email` | `auth.verify.view` | Auth/VerifyEmail.vue |
| POST | `/verify` | `auth.verify` | `throttle:5,1`; creates user + main address (`country_id = Country::egyptId()`) |
| POST | `/verify-resend` | `auth.verify.resend` | `throttle:5,1` |
| GET | `/forgot-password` | `forgot` | Auth/ForgotPassword.vue |
| POST | `/forgot-password` | `auth.forgot` | Rate-limited 3/10 min; reset code created synchronously |
| GET | `/reset-password` | `reset` | Auth/ResetPassword.vue |
| POST | `/update-password` | `auth.update` | `throttle:5,1`; strong password required |
| POST | `/logout` | `logout` | `auth:web` |

### Cart (guest + authenticated, cookie-based — there is NO cart page, only the drawer)

| Method | Route | Name | Action |
|--------|-------|------|--------|
| POST | `/cart/add` | `cart.add` | Add product (422 if out of stock) |
| GET | `/cart` | `cart.index` | JSON `{items, count, total, free_shipping_threshold}` |
| PATCH | `/cart/{id}` | `cart.update` | Update quantity (≥ 1) |
| DELETE | `/cart/{id}` | `cart.destroy` | Remove item |

Cart identity: cookie `cart_id` (30 days, `Cart::getCookieId()`); logged-in users are scoped
by `user_id`, guests by the cookie. Login merges the guest cart into the user cart.

### Checkout & Payment (guest + authenticated)

| Method | Route | Name | Action |
|--------|-------|------|--------|
| GET | `/checkout` | `checkout` | Render checkout page (redirects home if cart empty) |
| POST | `/checkout` | `checkout.store` | Submit order (JSON, returns `redirect`) |
| POST | `/checkout/apply-discount` | `checkout.discount` | Apply discount code (auth only; guests get 401) |
| POST | `/checkout/remove-discount` | `checkout.remove-discount` | Remove applied discount |
| GET | `/checkout/success` | `checkout.success` | Guest COD success page (`?order_number=`, ownership-checked) |
| GET | `/payment/{order_number}` | `user.payment` | Moyasar payment form (ownership-checked) |
| GET | `/payment/{order_number}/callback` | `payment.callback` | Moyasar callback → Success / Failed page |

### Authenticated User Routes (`auth:web`)

| Method | Route | Name | Action |
|--------|-------|------|--------|
| GET | `/user-profile` | `user.profile` | Profile dashboard (tabs: account, wishlist, orders, returns, addresses) |
| GET | `/account` | `account` | Stub page linking to `/user-profile` and `/` |
| POST | `/wishlist/add/{product}` | `wishlist.add` | Add to wishlist |
| POST | `/wishlist/remove/{product}` | `wishlist.remove` | Remove from wishlist |
| POST | `/reviews` | `reviews.store` | Submit product review (one per user per product) |
| POST | `/user/addresses` | `user.addresses.store` | Add address (location taken from main address server-side) |
| PUT | `/user/addresses/{id}` | `user.addresses.update` | Edit address |
| DELETE | `/user/addresses/{id}` | `user.addresses.destroy` | Delete address (main address blocked) |
| POST | `/user/addresses/set-main/{id}` | `user.addresses.setMain` | Set main address (a duplicate route `POST /user_addresses/{address}/set_main` maps to the same action) |
| PUT | `/user_update_info/update` | `update_password` | Change password |
| PUT | `/user_normal_info/update` | `update_user_info` | Update profile info |
| GET | `/user_return_orders` | `user.return_products` | JSON list of returned orders |
| POST | `/user_return_orders/store` | `user.return_products.store` | Submit return request |
| POST | `/user_orders/cancel` | `user.orders.cancel` | Cancel order (JSON; route + controller live, but no web UI currently calls it) |

---

## Home Page Data & Sections

`HomeController::index()` returns four product lists with an identical mapped shape
(id, name, name_en, slug, price, discount_price, image_url, is_in_wishlist, quantity,
images[], parent{id,name,name_en}) via a shared `mapProducts()` + `baseProductsQuery()`:

| Prop | Source |
|------|--------|
| `products` | Latest 10 products |
| `offerProducts` | Latest 10 with `discount_price` set, > 0 and < `price` |
| `giftProducts` | Latest 10 in category slug `gifts` (empty list if the category is missing, never a 500) |
| `householdProducts` | Latest 10 in category slug `household-essentials` |
| `banners` | All `HeaderBanner` rows (`HeaderBannerResource`) |
| `homeDesigns` | `Design` rows `page_name=home` (`home_band_left/right`, `home_tv_banner`) |
| `topOffers` | `Design` rows `top_offer_1..5` |
| `newProductsBanner` | `Design` `new_products_banner` image (passed by the controller but **not rendered** in `Home.vue` — dead prop) |

`Home.vue` section order: UpperPanner (banner carousel) → TheFeatures → TopOffers →
latest products (`ProductsSwiper`, title `section_latest_products`, view-all `/products`) →
offers (`NewProducts`, title `section_offers`, view-all `/offers`) → QualityFeatuers →
gifts (`ProductsSwiper`, title `section_top_gifts`, view-all `/categories/gifts`) → band images →
household (`ProductsSwiper`, title `Household_supplies`, view-all `/categories/household-essentials`) →
TV banner image. `TopCategories` and `WhyShopWithUs` are imported but commented out.
There is no flash-deals section and no "top mobiles" section anymore.

---

## Layout & Shared Components

**`AppLayout.vue`**: renders `CartDrawer`, `AppHeader`, `QuickView`, `BackToTop`, `CompareBar`,
`ToastContainer`, then `<v-main>` with `AdvertisementMarquee` + page slot, then `AppFooter`.

- **`AppHeader.vue`**: logo, nav (Home / Products / Offers / Brands / Contact), categories
  dropdown (shared `categories` prop), `GlobalSearch` (desktop), language toggle, cart button
  with badge (opens drawer via mitt), account menu → `/user-profile` / `POST /logout`
  (or Login/Register links). No wishlist or compare link in the header.
- **`AppFooter.vue`**: links (shop pages, terms/privacy/shipping/exchanges/FAQ, bulk/rep
  orders), contact block from shared `siteContact` prop, newsletter form → `POST /subscribe`.
  App Store / Google Play badges are non-link `<div>`s.
- **`CartDrawer.vue`**: the only cart UI (slide-in drawer). Loads `GET /cart`, updates
  `PATCH /cart/{id}`, deletes `DELETE /cart/{id}`; free-shipping progress vs
  `free_shipping_threshold` (hidden when null/0); CTA is a link to `/checkout`.
- **`AdvertisementMarquee.vue`**: scrolling ad bar from shared `advertisements` prop
  (cached 1h per locale: `active_advertisements_{locale}`; cleared on every ad change).
- **`GlobalSearch.vue`**: debounced (280 ms, min 2 chars) live search → `GET /search`;
  Enter / "view all" → `/products?search=`.
- **`ProductCard.vue`**: product tile; add-to-cart, wishlist toggle (guests get a warning
  toast), compare toggle.
- **`SeoHead.vue`**: title, description, OG/Twitter, canonical (current URL stripped of
  query/hash; the `canonical_url` setting is deliberately not used). `JsonLd.vue` renders
  JSON-LD (Product, Organization/WebSite, priceCurrency EGP).
- **`QuickView.vue`**, **`CompareBar.vue`** (bottom bar from localStorage compare list,
  link `/compare?ids=…`), `BackToTop.vue`, `ToastContainer.vue`.

Shared Inertia props (`HandleInertiaRequests::share()`): `auth.user`, `locale`,
`advertisements`, `flash`, `seo`, `siteContact` (phone — `phone_number` preferred, else
`phone` — email, address, socials facebook/twitter/instagram/snap→snapchat/tiktok; rows/icons
hide when empty), plus `categories` shared from `AppServiceProvider`.


## Core Flows

### Guest Shopping Flow

1. Browse home page → product swipers, offers, banners
2. Products / Categories → filter by price, category, sort (12/page)
3. Product detail → images, features, reviews (AJAX), related products
4. Add to cart (cookie-based, no login needed)
5. Checkout → new-address form (+ email) — country is fixed Egypt
6. Select shipping (pickup free / fixed / weight / city-based, only enabled options shown)
7. Select payment method: only methods enabled in settings (COD and/or card)
8. Card → Moyasar payment page; COD → success page (`/checkout/success?order_number=`)

### Registered User Shopping Flow

1. Register → OTP via SMS → verify → login (guest cart merged on login)
2. Same browsing/cart flow as guest
3. At checkout → pick a saved address (default = main address) or add a new one
   (new address is also saved to the address book, title = localized "New Address")
4. Apply discount code (web: consumed at order creation; session `applied_discount_code_id`)
5. Same payment flow as guest; logged-in COD → profile orders tab + success flash

### Authentication Flow (web)

1. `POST /register` → validates (Egyptian mobile, unique phone/email, strong password,
   `city_id` required — **no `country_id`**) → stores hashed data in session
   `pending_registration` (no DB write) → sends 4-digit OTP → redirect to verify
2. `POST /verify` → 4-digit code, 10-min expiry, 5 wrong attempts invalidate the code →
   creates user + main address (`country_id = Country::egyptId()`), logs in, merges cart
3. `POST /login` → 5-attempt rate limit → session regenerated → home
4. Forgot password → email must exist → reset code (10 min, synchronous creation) →
   `POST /update-password` (strong password) → login
5. `POST /logout` → destroys session

### Checkout Flow (detail, web)

1. `GET /checkout`: redirect home if cart empty; props: `cartItems`, `total`, `totalBefore`,
   `cities` (all `status=used`), `shipping` (`ShippingTypesAndPrice::find(1)`),
   `discountPrice`, `userAddresses`, `summary` (**server-computed** via
   `CheckoutServices::checkoutSummary()`), `appliedDiscount`, `vatRate`,
   `freeShippingThreshold`, `paymentMethods` (`Setting::enabledPaymentMethods()`).
2. `POST /checkout/apply-discount` (auth only): validates code (active, remaining uses),
   applies to matching cart items, stores id in session (consumed at order creation).
3. `POST /checkout` (via `CheckoutServices`, in a transaction):
   - Validates address (existing saved address, or new address: first/last name,
     Egyptian mobile, address ≤ 255, `city_id` exists; **country defaults to Egypt**)
   - Validates `payment_method` against `Setting::allowedPaymentMethodValues()`
     (incl. legacy `cash` alias) — a disabled method is rejected server-side
   - Validates at most one shipping option; calculates shipping price
   - Soft-deleted products in the cart → clean 422 "المنتج غير متاح"
   - Creates `Order` + `OrderItem`s + `OrderAddress` (+ `UserAddress` if a new address
     was added); stock decremented with row locks; cart cleared
   - `CheckoutServices::checkPaymentMethod()`: card → redirect to `/payment/{order_number}`;
     COD + logged-in → profile orders tab; COD guest → `/checkout/success`
   - Errors use typed `CheckoutException` (only its user-facing message is shown;
     other exceptions return a generic message, no `$e->getMessage()` leak)

### Payment Flow (Moyasar)

1. `GET /payment/{order_number}` → renders Moyasar form with the publishable key
   (from `Setting`); amount = `Order::expectedPaymentAmountHalalas()`; **currency `SAR`**
2. User submits card via Moyasar.js (client-side, v2 form, visa/mastercard/mada)
3. Moyasar redirects to `GET /payment/{order_number}/callback?id={payment_id}`
4. Callback re-fetches the payment from Moyasar API — paid only if status `paid`
   **and** amount == expected halalas **and** currency `SAR` **and** the order number
   is in the description
5. Creates a `PaymentTransaction` record (deduped by Moyasar payment id)
6. Updates `Order.payment_status` to `paid` or `failed`
7. Renders `Payment/Success.vue` or `Payment/Failed.vue` (failed offers a retry link).
   Order-creation email goes to the **customer** (`OrderCreatedCustomerNotification`,
   `via()` = `['mail']`); admins get a **database** notification only.

---

## Customer Features

| Feature | Behavior |
|---------|----------|
| Wishlist | Web: auth only (`POST /wishlist/add|remove/{product}`); guests get a warning toast. API: user + guest (toggle endpoint, `productId` header). Profile wishlist tab reads `user.wishlist_products`. |
| Reviews | `POST /reviews` (auth): `rate` 1–5, `comment` 5–500 chars; one review per user per product (422 `product_already_reviewed`). `GET /reviews/{productId}`: JSON `{reviews (10/page), avg, count, ...}`. Product detail loads reviews via AJAX. Fake ratings were removed — only real ratings are shown. |
| Compare | Compare list lives in `localStorage` (`useCompare.js`, capped, oldest evicted); `CompareBar` links to `/compare?ids=…`; `Compare.vue` renders the table. |
| Returns (web) | `POST /user_return_orders/store` with `return_order_id` owned by the user; pending-only (`Order::canBeReturned()`), idempotent if already returned (200, no second alert), 422 if not returnable; sets `return_order=true`, status `Pending`, restores stock, fires `OrderReturned` (admin DB notification). Profile returns tab loads `GET /user_return_orders`. |
| Order cancel (web) | `POST /user_orders/cancel` (JSON, auth) → `OrderRepository::cancel()`; delivered/cancelled/returned cannot cancel (422). Route + controller live, but no web UI currently calls it. |
| Bulk orders | `GET/POST /bulk-order` — form `{name, phone (Egyptian mobile), company_name?, description}` → `BulkOrder` row + success flash. No city/country fields. |
| Representative orders | `GET/POST /representative-order` — form `{name, phone, description}` → `RepresentativesOrder` row + success flash. |
| Contact | `GET/POST /contact-us` — form `{full_name, contact_email, phone_number (Egyptian mobile), text}` → `ContactUs` row + admin database notification; contact info shown from settings. |
| FAQ | `GET /faq` — all `CommonQuestion` rows; client-side search + category tabs. |
| Static pages | Terms (id 1), shipping policy (id 2), privacy (id 3), exchanges & returns (id 5); localized title/content, purified HTML rendered with `v-html`. |
| Newsletter subscribe | `POST /subscribe` (web only — **no API endpoint**); 409 if already subscribed. Sending is queued (see Admin → Newsletter). |
| Search | Live search (debounced, min 2 chars) → `GET /search` JSON top 8; full results land on `/products?search=`. Arabic search normalization (see Localization). |
| Language switcher | `POST /locale/{lang}` (session, `ar`/`en` only); header toggle; full reload so `dir` updates. |
| Profile | `GET /user-profile` tabs (`?tab=`): account, wishlist, orders (10/page), returns, addresses. Address dialog has no country/city selects — location comes from the main address server-side. Main address cannot be deleted (button hidden in UI + blocked server-side). |

---

## Money & Pricing Rules

- **Totals**: `total = subtotal + shipping + tax` in all three checkout paths (web, API user,
  API guest). Subtotal uses the charged unit price: offer `discount_price` when set, else
  `price`; coupon `discounted_price` only when a discount is actually active.
- **VAT**: from `settings.value_added_tax` (0–100, default 0), applied uniformly on the
  discounted items subtotal, stored in `orders.tax_amount`, shown as a line in the summary
  and the order email.
- **Free shipping**: `settings.free_shipping_threshold` (default 500), editable at
  `/admin/front_settings` (labelled in جنيه). **0 or null = DISABLED** — the drawer hides
  its free-shipping block and no order gets free shipping. The waiver uses the
  pre-coupon subtotal and applies only when `shippingPrice > 0` (pickup stays 0).
  **Undecided by the user**: the marketing copy still promises free shipping over **200**
  in 4 places (features bar, the active ad, the shipping policy page, the FAQ) while the
  setting is 500. The final launch number (200 vs 500 vs none) has NOT been picked.
- **Shipping prices** live in `shipping_types_and_price` (row id 1), edited at
  `/admin/shipping_types` (not in general settings): pickup from store (free, on), fixed
  price `normal_shipping_price` (seeder value 20, placeholder), weight-based `weight_price`
  per kg (seeder value 5, placeholder), city-based (off; each city has its own
  `shipping_price`). Missing row → clean error, not a 500.
- **Payment toggles**: `settings.payment_cod_enabled` + `payment_card_enabled`
  (both default true; at least one must be enabled). Web checkout shows only enabled
  methods (Inertia prop `paymentMethods`, default = first enabled); all three checkout
  paths reject a disabled method server-side (`Setting::enabledPaymentMethods()` /
  `allowedPaymentMethodValues()`, incl. legacy `cash` alias).
- **Discount codes**: `discount_type` is `percentage` or `price` (fixed amount).
  Percentage = per unit off the offer price; fixed = once per order, spread across
  eligible lines, each line capped at its own total. Validity = `status='active'` AND
  `number_of_used > 0`. **Codes have no expiry dates.** Global code (no linked products)
  discounts everything; otherwise only linked products.
  - Web: code id stored in session at apply time, **consumed at order creation**
    (creates `AppliedDiscountCode` row + decrements, with `lockForUpdate`).
  - API (user + guest): **consumed at apply time** (row created + `number_of_used`
    decremented when applied; empty cart → nothing consumed). Remove-discount endpoints
    reset `discounted_price` and delete the row but do **not** restore `number_of_used`.
  - `AppliedDiscountCode` (`applied_discount_codes` table): one row = "this code is
    applied to the cart identified by `cookie_id`". Orders record `discount_code_id`.
  - Web remove: `POST /checkout/remove-discount`. API remove:
    `POST /api/v1/remove-discount` (user), `POST /api/v1/guest-remove-discount` (guest).
- **Moyasar / currency**: Moyasar form currency is hardcoded `SAR`; the webhook and both
  callbacks verify `currency === 'SAR'`; `payment_transactions.currency` defaults to
  `SAR`. Card payments therefore charge the EGP order total numerically as SAR
  (accepted for testing; card payment is deferred for launch).
  `Order::expectedPaymentAmountHalalas()` = `(int) round(total_price * 100)`.

---

## Order Lifecycle

- **Status enum** (`app/Enums/OrderStatus.php`): `pending, confirmed, processing, shipped,
  delivered, cancelled` (string-backed, stored as MySQL ENUM on `orders.status`).
  `canTransitionTo()`: forward-only; delivered/cancelled are terminal; cancel allowed
  from any non-terminal state; backwards moves forbidden.
- **Order numbers**: auto-generated by the `creating` hook (`{year}000001`, incrementing
  per year, incl. soft-deleted rows; `createWithUniqueNumber()` retries on duplicates;
  unique index on `orders.number`).
- **Delivered ⇒ paid, COD-only**: transitioning to `delivered` sets
  `payment_status='paid'` only when `payment_method` is COD (`cash_on_delivery`/`cash`);
  card orders are never auto-paid on delivery.
- **Cancel**: customer endpoints (web + API user + API guest) via the cancellable-states
  transition; cancel restores stock (each `orderItem` quantity incremented back);
  cancel-after-return is refused; delivered/cancelled cannot cancel.
- **Returns**: pending-only (`Order::canBeReturned()` = `!return_order && status->canReturn()`).
  On return: `return_order=true`, status back to `Pending`, stock restored, admin
  database notification (`OrderReturned`); `payment_status` untouched (refunds are manual
  via the Moyasar dashboard). Repeat returns are idempotent (200, no second alert).
- **Admin order delete** also restores stock (unless already cancelled); orders use
  soft deletes.
- **Order addresses**: `order_addresses` rows are created at checkout (saved address with
  fallback to the customer's main address for missing city/country, or a new address).
  When a logged-in user adds a new address at checkout it is also saved to their address
  book. The checkout address card shows no city field.


## API (Mobile / External) — base `/api/v1`

- **Prefix** `api/v1` (`RouteServiceProvider`); global `api` middleware = `ThrottleRequests:api`
  (60/min per user-or-IP) + `SubstituteBindings`. No session, no CSRF.
- Almost everything is inside `Route::middleware(['changeLanguage'])`; the 3 exceptions
  outside it are `GET /payment-page/{order_number}`, `GET /payment-page/{number}/payment/callback`,
  `POST /webhooks/moyasar`.
- **Locale**: from the first 2 chars of the `Accept-Language` header (`ar`/`en`, else `ar`).
- **Response envelope**: `ApiResponse::sendResponse($status_code, $msg, $data)` →
  JSON `{status_code, message, data}` with HTTP status = `$status_code`. Exceptions noted
  per area (plain `response()->json(...)`, raw strings, Blade views, redirects).
- **Empty/not-found convention**: many catalogue endpoints return HTTP 200 with `data: []`
  when nothing is found, not 404.

### Guards / identity

| Guard / mechanism | Definition |
|---|---|
| `auth:user` | Sanctum guard (`driver: sanctum, provider: users`). Token via `createToken('-AuthToken')`; **expiration 365 days** (`config/sanctum.php`). Sent as `Authorization: Bearer <token>`. |
| `is_user_verified` | `CheckUserVerification` middleware. If the user has a verification row with `is_verified=false` → 403 `{message, verification_required: true, user_id}`; sends a fresh SMS only if no live (unexpired) code exists (API OTP lifetime **60 s**; reset-password code 10 min). Applies to the whole authenticated group (profile, cart, checkout, discount, orders) — **not** to `GET /user` or `POST /logout`. |
| `guest.header` | `GuestHeader` middleware: requires a `guest-id` **header**, else 401 `{status_code:401, message:'Guest ID header is required.'}`. |
| Guest identity | The `guest-id` header carries the Guest **`cookie_id`** (a UUID minted by `POST /create-guest`), **not** the numeric id. Every guest endpoint resolves the Guest row by `cookie_id`; the numeric id is only the internal FK. |

### Auth + guest creation

| Method | Path | Middleware | Controller / action |
|--------|------|-----------|---------------------|
| GET | `/user` | `auth:user` (no `is_user_verified`) | Closure — returns `$request->user()` raw (not the envelope) |
| POST | `/register` | public | `Api\UserAuthController@register` |
| POST | `/verify-code` | public | `Api\UserAuthController@verifyCode` |
| POST | `/resend-verify-code` | public | `Api\UserAuthController@resendVerifyCode` |
| POST | `/login` | public | `Api\UserAuthController@login` |
| POST | `/forget-password` | public | `Api\UserAuthController@forgetPassword` |
| POST | `/reset-password` | public | `Api\UserAuthController@resetPassword` |
| POST | `/logout` | `auth:user` | `Api\UserAuthController@logout` |
| POST | `/create-guest` | public, `throttle:10,1` | `Api\GuestController@createGuest` |

- **Register** (`Api/RegisterRequest`): `first_name*`, `family_name*`, `email*` (unique),
  `password*` (confirmed), `phone_number*` (EgyptianMobileNumber, unique), `address*` (≤255),
  `city_id*` (exists). **No `country_id`** — the main address country is derived from the
  city, falling back to Egypt (`City::find(city_id)?->country_id ?? Country::egyptId()`).
  Throttle 5/IP/min (429). Success: `{verification_required:true, user_id}` (SMS OTP, 60 s).
  No token yet.
- **Verify-code**: `user_id*`, `code*`. Throttle 10/user/min; expired → 403; wrong → 403,
  counted (`MAX_OTP_ATTEMPTS = 5`); ≥ 5 wrong → code invalidated. Success: marks verified +
  returns Sanctum token `data:{token}` (200).
- **Login** (`Api/LoginRequest`): `phone_number*` (EgyptianMobileNumber + `exists:users`),
  `password*`. Throttle 5/phone+IP/min. Unverified API-registered account → 403
  `{verification_required:true, user_id}` (accounts with no verification row, e.g.
  web-registered, are not blocked). Success 200 `{token}`; failure 401.
- **Forget-password**: `phone_number*` (EgyptianMobileNumber). Throttle 5/phone+IP/min.
  Unknown-but-well-formed number → uniform 200 (no enumeration, no SMS). Known → reset OTP.
- **Reset-password**: `user_id*`, `code_verify*`, `password*` (min 6, confirmed).
  Throttle 10/user/min. Success 200.
- **Resend-verify-code**: `user_id*`. Throttle 5/user/min. Quirk: unknown user returns the
  plain string `'user id not found'` (not JSON).
- **Logout**: deletes `currentAccessToken()`; 200. **Create-guest**: no body; returns
  200 `{id: <numeric Guest.id>, cookie_id: <uuid>}` — the app must store `cookie_id` and
  send it as the `guest-id` header thereafter.

### Profile + addresses (`auth:user` + `is_user_verified`)

| Method | Path | Controller / action |
|--------|------|---------------------|
| GET | `/user-info` | `PersonalInfoController@getUserInfo` → `UserInfoResource` (`{id, profile_image, first_name, family_name, phone_number, email, country:{id, name, country_code}}` — country from the user's first address) |
| POST | `/change-personal-info` | `PersonalInfoController@changePersonalInfo` (partial update; Egyptian mobile, unique ignoring self) |
| POST | `/change-password` | `PersonalInfoController@changePassword` (`current_password*` via `current_password:user`, new password min 8, confirmed) |
| POST | `/change-profile-image` | `PersonalInfoController@changeProfileImage` (jpeg/png/jpg/gif/webp, max 2 MB; old image deleted) |
| GET | `/user-addresses` | `UserAddressesController@getAllAddresses` |
| GET | `/user-country-cities` | `UserAddressesController@getUserCities` (cities `status='used'` in the country of the user's first address) |
| POST | `/create-address` | `UserAddressesController@createAddress` |
| POST | `/update-address/{id}` | `UserAddressesController@updateAddress` |
| DELETE | `/delete-address/{id}` | `UserAddressesController@deleteAddress` (main address refused) |
| POST | `/main-address/{id}` | `UserAddressesController@setMainAddress` |

Address rules (`UserAddressRequest`): `address_title*`, `address*` (≤255), `first_name*`,
`last_name` nullable, `phone_number*` (EgyptianMobileNumber), `city_id*`. **No `country_id`
in the rules** — create/update derive it as
`$request->country_id ?? City::find(city_id)?->country_id ?? $user->addresses->first()?->country_id`.
Update/delete/set-main are scoped to the caller's own addresses.

### Cart — user vs guest

User routes: `auth:user` + `is_user_verified`. Guest routes: `guest.header`.

| Method | Path | Controller / action |
|--------|------|---------------------|
| GET | `/cart` | `Api\CartController@index` |
| POST | `/cart/add` | `Api\CartController@store` (`Api/CartRequest`) |
| POST | `/cart/update/{id}` | `Api\CartController@update` |
| POST | `/cart/delete/{id}` | `Api\CartController@destroy` |
| GET | `/guest-cart` | `Api\guest\GuestCartController@guestIndex` |
| POST | `/guest-cart/add` | `Api\guest\GuestCartController@guestStore` |
| POST | `/guest-cart/update/{id}` | `Api\guest\GuestCartController@guestUpdate` |
| POST | `/guest-cart/delete/{id}` | `Api\guest\GuestCartController@guestDestroy` |

- **Add (both)**: `{product_id* (exists:products), quantity* (int, min:1)}`. Already in the
  active cart (`status=0`) → quantity incremented; exceeding stock → 400. User add returns
  the `ApiResponse` envelope; guest add returns plain JSON 201 on create / 200 on increment.
- **List**: `{total_count, total_price, total_quantity, free_shipping_threshold, items: CartResource[]}`
  — user additionally gets `discounted_price` (= totalBeforeDiscount − totalPrice).
  `CartResource`: `{id, name, image, category, price, quantity, weight, discount_price, product_status}`.
  Totals use the offer price; coupon `discounted_price` only when a discount is actually
  active (stale leftovers ignored). A soft-deleted product in the cart → 422 `المنتج غير متاح`.
- **Update**: `{quantity* (int, min:1)}`. Guest update caps at product stock (400 if exceeded);
  **user update has no stock cap** (verified asymmetry in code).
- **Delete**: soft-delete by setting `status=1` (row not removed), scoped by `user_id` / `cookie_id`.

### Checkout — user vs guest

| Method | Path | Middleware | Controller |
|--------|------|-----------|------------|
| POST | `/checkout` | `auth:user` + `is_user_verified` | `Api\CheckoutController@usercheckout` |
| POST | `/guest-checkout` | `guest.header` | `Api\guest\CheckoutController@guestCheckout` |
| GET | `/shipping-info` | public | `Api\CheckoutController@shippingInfo` → `ShippingResource` for row 1 (`{pickup-from-store, shipping-based-on-weight, shipping-weight-price, fixed-shipping, fixed-shipping-price, shipping-based-on-city}`); missing row → 422 |

**User checkout** (`Api/UserCheckoutRequest`): `payment_method*` (`Rule::in(Setting::allowedPaymentMethodValues())`);
`user_address` required unless a new address is being added; `terms` nullable boolean;
new address: `first_name*`, `last_name*`, `phone_number*` (EgyptianMobileNumber), `city_id*`,
`address*` (≤255); `country_id` nullable/exists. **Egypt default:** if no `user_address` and
no `country_id` supplied, the controller merges
`country_id = City::find(city_id)?->country_id ?? Country::egyptId()`.
Flow: at most one shipping option → shipping price (row 1; missing → `CheckoutException` → 400)
→ empty cart → 200 message → transaction: `join_news` subscribes the email, `createOrder`
(totals = discounted subtotal + shipping + VAT; free shipping waives the fee when the
pre-coupon subtotal ≥ threshold, 0/null = disabled), `createOrderItems` (stock with
`lockForUpdate`, quantity decremented), `isAddingNewAddress`, product → `archived` at
quantity ≤ 0. Response via `checkPaymentMethod()`: card → **201** `{redirect: <web user.payment URL>}`;
COD → **201** `{redirect: <home URL>, message: 'تم إتمام الطلب بنجاح'}`. Note: the redirect
target is the **web** payment page, not the API `/payment-page/...` route.

**Guest checkout** (`Api\guest\CheckoutController`, inline validation): same rules, except
**`terms` is `required|boolean`** (user: nullable). `country_id` nullable; Egypt default
applies the same way. Guest row is `Guest::firstOrCreate(['cookie_id' => guest-id])`.
Response is plain JSON (not the envelope): COD → 201 `{message:'Order created successfully'}`;
card → 201 `{message, payment_url: <web user.payment URL>}`. Failure → 500.

### Payment methods + discount codes

| Method | Path | Middleware | Controller / action |
|--------|------|-----------|---------------------|
| POST | `/check-discount` | `auth:user` + `is_user_verified` | `Api\CheckDiscountController` (`__invoke`) |
| POST | `/remove-discount` | `auth:user` + `is_user_verified` | `Api\CheckDiscountController@removeDiscount` |
| POST | `/guest-check-discount` | `guest.header` | `Api\guest\GuestCheckDiscountController` (`__invoke`) |
| POST | `/guest-remove-discount` | `guest.header` | `Api\guest\GuestCheckDiscountController@removeDiscount` |

- **Apply (both)**: `{discount_code* (exists:discount_codes,code)}`. Code must be
  `status='active'` and `number_of_used > 0`; already-applied → 400; empty cart → 200
  "cart empty" and nothing consumed. On success the code is applied to cart rows
  (`discounted_price` per item), an `AppliedDiscountCode` row is created keyed by
  `Cart::getCookieIdApi()`, and `number_of_used` is **decremented at apply time**.
  User success: `ApiResponse` 200 `{discount_code, discount_price}`; guest: plain JSON
  200 `{message, discount_code, cart}`.
- **Remove (both)**: no body. Resets `discounted_price=null` and deletes the
  `AppliedDiscountCode` row(s). User returns `ApiResponse` 200 `{new_total, summary}`
  (`summary` = `checkoutSummary`: `subtotal_before_discount, subtotal, discount, tax_amount, vat_rate, free_shipping_threshold`);
  guest returns `ApiResponse` 200 `{new_total}` only.

### Orders — user vs guest

User routes: `auth:user` + `is_user_verified`. Guest routes: `guest.header`. Both use
`OrderResource`: `{id, number, shipping-price, total_price, subtotal, total_before_discount, discount_amount, tax_amount, products:[{product: ProductsResource, quantity}], user:{id, name, email, address}, status, order-status}`.

| Method | Path | Controller / action |
|--------|------|---------------------|
| GET | `/user_orders` | `Api\UserOrdersController@mainOrders` |
| GET | `/user_show_order/{number}` | `Api\UserOrdersController@showOrder` |
| POST | `/user-orders/delete` | `Api\UserOrdersController@destroy` |
| POST | `/user-orders/cancel` | `Api\UserOrdersController@cancel` |
| GET | `/user-return-orders` | `Api\UserOrdersController@returns` |
| POST | `/user-return-orders/store` | `Api\UserOrdersController@store` |
| GET | `/guest-orders` | `Api\guest\GuestOrdersController@mainOrders` |
| GET | `/guest-order/{number}` | `Api\guest\GuestOrdersController@showOrder` |
| POST | `/guest-order/delete` | `Api\guest\GuestOrdersController@destroy` |
| POST | `/guest-order/cancel` | `Api\guest\GuestOrdersController@cancel` |
| GET | `/guest-return-orders` | `Api\guest\GuestOrdersController@returns` |
| POST | `/guest-return-orders/store` | `Api\guest\GuestOrdersController@store` |

- **List**: `return_order=false`, `latest()`, **paginate 6**. User returns the envelope;
  guest returns plain JSON `{orders: [...]}` (no Guest row → `{orders: []}`, 200).
- **Detail `/{number}`**: by order **number** (not id). Not found → 404 `الطلب غير موجود`.
- **Delete item** (`{product_id*, order_number*}`): only a **pending, unpaid, non-return**
  order can be edited (422 otherwise); decrements the item quantity by 1 (deletes the row
  if it was 1), restores 1 unit to stock, recalculates totals, deletes the order if no
  items remain.
- **Cancel** (`{order_number*}`): `OrderRepository::cancel()` — returns 422 if
  `return_order` or the status cannot transition to `Cancelled`; success 200.
- **Returns list**: `return_order=true`, paginate 10. User: empty → 200 `لا يوجد مرتجعات`;
  guest: empty → **404** (verified asymmetry).
- **Return store** (`{return_order_id*}` — the order's **numeric id**): ownership-scoped;
  already returned → 200 quietly (idempotent, no second admin alert); only pending
  orders can be returned (422 otherwise). Sets `return_order=true`, status `Pending`,
  restores stock, fires `OrderReturned` (admin DB notification); `payment_status` untouched.

### Favourites

| Method | Path | Middleware | Controller / action |
|--------|------|-----------|---------------------|
| GET | `/all-user-fav-products` | `auth:user` | `GetAllFavProductsController@userFavProducts` |
| POST | `/user-fav-product-add-or-delete` | `auth:user` | `AddToFavProductsController@userAddFavProducts` |
| GET | `/all-guest-fav-products` | public (no middleware) | `GetAllFavProductsController@guestFavProducts` |
| POST | `/guest-fav-product-add-or-delete` | `guest.header` | `AddToFavProductsController@guestAddFavProducts` |
| GET | `/check-if-auth` | `auth:user` | `AddToFavProductsController@checkIfAuth` |

Toggle add/remove is **one endpoint each** (no separate remove). The product id is passed in
the **`productId` request header**, not the body. User: `wishlistProducts()->toggle($productId)`;
unknown product → 400. Guest: resolves Guest by `cookie_id` → 401 if none; unknown product → 200.
Lists paginate 10 (`ViewProductsResource`). `check-if-auth` returns the boolean as the
**message** field with `data: []`.

### Catalogue / products — all public GET

| Method | Path | Controller / action | Behaviour |
|--------|------|---------------------|-----------|
| GET | `/products/{category_id}` | `AllProductsController` | Products of that category, paginate 10 |
| GET | `/product-details/{id}` | `ProductDetailsController@getProductDetails` | `ProductDetailsResource` (`{id, name, category, choices, price_before_discount, price_after_discount, currency, description, image, more_images[≤3], is_favourite}`); missing → 200 "Product Doesn't Exists", `[]` |
| GET | `/product-features/{id}` | `ProductDetailsController@productFeatures` | Product features collection |
| GET | `/related-products/{id}` | `ProductDetailsController@relatedProducts` | Same-category products, take 10 |
| GET | `/search?product_name=` | `SearchProductController@search` | Arabic-normalized REGEXP search over `name`/`name_en`; no term → all products, latest, paginate 10 |
| GET | `/filter` | `FilterProductsController@filterProducts` | `rate_number`, `category_id`, `company_id`, `price_min`, `price_max`, `color_id`; only `status='active'`; paginate 10 |
| GET | `/get-top-products` | `TopSellingProductsController@topProducts` | By Σ `order_items.quantity`, limit 20 |
| GET | `/get-all-top-products` | `TopSellingProductsController@allTopProducts` | Same ranking, limit 50, paginate 10 |
| GET | `/get-latest-products` | `LatestProductController@latestProducts` | Latest 5 |
| GET | `/get-all-latest-products` | `LatestProductController@allLatestProducts` | Latest, paginate 10 |
| GET | `/discounts` | `DiscountsController@discounts` | Products with `discount_price != null`, take 20 |
| GET | `/all-discounts` | `DiscountsController@allDiscounts` | Same, paginate 10 |
| GET | `/get-main-categories` | `MainCategoriesController@mainCategories` | Top-level (`parent_id=null`) categories `has('products')` |
| GET | `/get-all-categories` | `MainCategoriesController@allMainCategories` | All categories `has('products')` |
| GET | `/get-sub-categories/{mainCateory}` | `MainCategoriesController@subMainCategories` | Children of the given category (note the route-parameter typo `mainCateory`) |
| GET | `/get-header-banners` | `AllBannersController@headerBanners` | All `HeaderBanner` rows |
| GET | `/get-all-banners` | `AllBannersController@allBanners` | `Design` rows excluding `offer_page_1`, `offer_page_2` |
| GET | `/colors` | `ColorsController` | Colours `has('products')` |
| GET | `/brands` | `BrandsController` | Companies `has('products')` |

List endpoints return `ViewProductsResource`
(`{id, name, category:{id,name}, price_before_discount, price_after_discount, currency, image, product_status, is_favourite}`).
Paginated lists: when `total > perPage`, `data = {records: [...], 'pagination links': {...}}`;
otherwise `data` is the bare resource collection. Several detail endpoints return 200 with a
"you must determind if you guest or user" message when **both** `user-id` and `guest-id`
headers are present; `is_favourite` is resolved from those headers.

### Settings / currencies / static / contact

| Method | Path | Middleware | Controller / action |
|--------|------|-----------|---------------------|
| GET | `/settings` | public | `Api\SettingsController@settings` → `SettingResource` (`{id, website_name, subscription_title, address, email, facebook, twitter, instagram, snap, tiktok, tax_number, value_added_tax, image, logo}` — first row only; **no** `free_shipping_threshold`, payment toggles, or Moyasar keys) |
| GET | `/default-currency` | public | `Api\CurrenciesController@defaultCurrency` (row `default_currency=true`) |
| GET | `/all-currencies` | public | `Api\CurrenciesController@allCurrencies` (rows `status='used'` — EGP only after seeding) |
| GET | `/static-pages` | public | `Api\StaticPagesController@static_pages` (Pages id 3, 2, 1 in that order, localized) |
| GET | `/common-questions` | public | `Api\StaticPagesController@common_questions` (all FAQ rows, localized) |
| POST | `/contact-us` | public | `Api\ContactUsController@sendMessage` (`full_name*` min 10, `email*`, `phone_number*` Egyptian mobile, `message*` min 10; notifies all admins) |

### Bulk / representative orders — public POST

| Method | Path | Controller / action |
|--------|------|---------------------|
| POST | `/store-bulk-order` | `Api\BulkOrderController@store` (`Front/bullkorder/BulkRequest`: `name*`, `phone*` Egyptian mobile, `company_name?`, `description*`) |
| POST | `/store-represintative-order` | `Api\RepresentativeOrderController@storeorder` (inline: `name*`, `phone*` Egyptian mobile, `description*`; note the route typo `represintative`) |

### Payment page, callback, Moyasar webhook (outside `changeLanguage`)

| Method | Path | Controller / action |
|--------|------|---------------------|
| GET | `/payment-page/{order_number}` (name `api.user.payment`) | `Api\PaymentController@index` — no auth middleware; ownership checked in code (user token must match `order.user_id`, or `guest-id` header must match `order.cookie_id` / `order.guest.cookie_id`); renders **Blade view** `front.user-payment` with `{order, publishable_key, amountHalalas}`. **Not** the page checkout redirects to. |
| GET | `/payment-page/{number}/payment/callback` (name `api.payment.callback`) | `Api\PaymentController@callback` — `?id=<moyasar payment id>`; idempotent (already-paid → success); verifies status `paid` + exact halalas amount + `currency='SAR'` + order number in description; records one `PaymentTransaction` per Moyasar payment id; verified-paid → `paid` (200 JSON / redirect home); failed or failed-verification → `failed` (422 JSON / redirect) |
| POST | `/webhooks/moyasar` (name `webhooks.moyasar`) | `Webhooks\MoyasarWebhookController@handle` — accepts the payment object directly or wrapped in `data`; **never trusts the payload**: re-fetches the payment from Moyasar with the secret key (lookup failure → 502); order number extracted from the verified payment's `description` via `/(\d{6,})/`; already-paid → 200 "Order already paid." (idempotent); same verification as the callback; `PaymentTransaction` deduped by `moyasar_payment_id`. **The user has NOT registered this URL in the Moyasar dashboard yet** — Moyasar never calls it until then. No signature verification of the incoming request itself was observed. |

### Endpoints that do NOT exist in the API (verified)

- **Newsletter subscribe**: no API endpoint — only web `POST /subscribe`.
- **Cities list**: `Api\CitiesController::allCities()` exists (Egypt-only via `Country code='EG'`)
  and is imported in `routes/api.php`, but **no route references it**; the only city endpoint
  is the authenticated `GET /user-country-cities`.
- No API route for: product reviews/comments, a checkout-summary/totals preview, order
  tracking beyond `showOrder`, password change for guests, or a dedicated "remove favourite"
  endpoint (toggle only). No `PUT`/`PATCH` anywhere in the API; updates/deletes use `POST`
  except `DELETE /delete-address/{id}`.


## Admin Dashboard

Prefix: `/admin/` — route group in `routes/dashboard.php` with `admin` middleware
(`App\Http\Middleware\Admin`: redirects to `admin.login` unless `Auth::guard('admin')` is
authenticated; it does **not** check any ability — authorization is per-controller via Gates).
194 admin routes. Admin login: `GET admin/login` → `admin.login`, `POST /admin/login` →
`admin.login.store`; logout `POST /admin/logout`. The admin is entirely **Blade**
(`resources/views/dashboard/index.blade.php` layout) — Inertia/Vue is storefront-only.
Admin home: `GET /admin` → `dashboard.index` (Gate `dashboard.view`): product/order/user/
message/admin/return counts, total + today revenue (paid, non-returned), this/last month
orders, orders-by-status (last 30d), 7-day sales chart, latest 5 orders, low stock
(qty ≤ 5, > 0, top 5), out-of-stock count, top 5 selling products, pending-order count.

### Sidebar sections (`resources/views/components/dashboard/side-bar.blade.php`)

Only **one** `@can` in the whole sidebar: the "إدارة المدراء" (admin management) group is
wrapped in `@can('admin.view')`. Every other link renders unconditionally (route-level
Gates still enforce on click). Sidebar badges count the logged-in admin's **unread**
notifications by type (contact messages, order-created, order-returned).

| Group | Links (route names) |
|-------|---------------------|
| — | لوحة التحكم → `dashboard.index` |
| الكتالوج (Catalogue) | Products: `products.index`, `products.create`, `out_of_stock`, `products.trash`; Categories & options: `main_categories.index`, `main_choices.index`, `colors.index`, `product_availability.index`; Brands: `companies.index`, `companies.create` |
| المبيعات (Sales) | Orders: `orders.index` (badge), `return_orders.index` (badge), `bulk_orders.index`, `representatives_orders.index`; Payments & discounts: `payments.index`, `discount_code.index`; Shipping: `shipping.index`, `countries.index`, `cities.index` |
| العملاء (Customers) | `clients.index`; Contact & newsletter (badge): `contact_us.index`, `user_news.create` |
| المحتوى (Content) | Design: `banners.index`, `designs.index`, `advertisements.index`, `store_featuers.index`; Pages: `pages.index`, `common_questions.index` |
| النظام (System) | `reports.index`, `front_settings`, `currencies.index`; Admin management (`@can('admin.view')`): `admins.index`, `rules.index` |

Not in the sidebar although routes exist: header text (`header_text.*`), legacy header
banner (`header_banner.*`), notifications (`notifications.index` — via the topbar bell),
profile/profile-settings (topbar user dropdown only).

### Routes by section (`routes/dashboard.php`)

| Section | Routes |
|---------|--------|
| Home | `GET /` → `dashboard.index` |
| Notifications | `GET /notifications` → `notifications.index`; `GET /notifications/read` → `notifications.read` |
| Profile | `GET /profile` → `profile.index`; `PUT /profile/{id}/update`; `GET /profile/edit` → `profile.settings.index`; `PUT /profile/update` |
| Contact | `GET /contact_us_view` → `contact_us.index`; `GET /contact_us_view/{id}/show` → `contact_us.watch` |
| Newsletter | `GET /send_news` → `user_news.create`; `POST /send_news_mail` → `user_news.send` |
| Settings | `GET /front_settings` → `front_settings`; `PUT /front_settings/{id}/update` → `settings.update` |
| Colors / Designs / Banners | Full 7-action resources: `colors`, `designs`, `banners` (`NewHeaderBannerController`) |
| Products | `Route::resource('/products')` + `GET /products/trash`, `PUT /products/{id}/restore`, `DELETE /products/{id}/force-delete`, `GET /products/out_of_stock`, `GET /fetch-choices`, `POST /product_images/delete` |
| Categories / Choices / Companies / Features | Resources: `main_categories`, `main_choices`, `companies`, `store_featuers` (except `show`; note `featuers` spelling) |
| Admins / Clients / Rules | `Route::resource('/admins')` + `PUT /admins/{id}/update_password`; `Route::resource('/clients')` (except create/store/show) + `PUT /clients/{id}/update_pass`; `Route::resource('/rules')` |
| Header text/banner | `Route::resource('/header_text')`; `GET /header_banner`, `POST /header_banner/delete`, `POST /header_banner/store` (route name `header_banner.sotreAndUpdate` — typo) |
| Advertisements | `Route::resource('advertisements')` (except `show`) + `POST advertisements/{advertisement}/toggle` |
| Pages / FAQ | `Route::resource('pages')`; `Route::resource('dashboard/common_questions')` → paths `admin/dashboard/common_questions*` |
| Bulk / Rep orders | Resources: `bulk_orders`, `representatives_orders` |
| Payments | `GET /payments` → `payments.index`; `GET /payments/{id}` → `payments.show` (read-only) |
| Countries / Cities | `Route::resource('/countries')` + `GET /countries/{countryId}/cities` (JSON); `Route::resource('/cities')` |
| Currencies | `Route::resource('/currencies')` + `GET /currencies/default_currency`, `PUT /currencies/change_default_currency` |
| Orders / Returns | `GET /orders`, `GET /orders/{id}`, `PUT /orders/{id}/update`, `DELETE /orders/{id}/delete`; `GET /return_orders`, `GET /return_orders/{id}`, `DELETE /return_orders/{id}/delete` (no update route for returns) |
| Availability / Discounts | Resources: `product_availability`, `discount_code` (except `show`) + `GET /api/search-products` |
| Shipping | `GET /shipping_types`, `GET /shipping_types/edit/{id}`, `PUT /shipping_types/{id}/update`, `GET /get-cities` |
| Reports | `GET /reports` → `reports.index` (single route; tabs/export are query params) |

### Permission system

- **Registry**: `rules/rules.php` — **33 groups**, each mapping ability → Arabic label
  (dashboard, products, categories, companies, designs, admins, clients, groups, orders,
  return_orders, news, settings, shipping, discount_code, product_availability, currencies,
  countries, colors, reports, payments, bulk_orders, representatives_orders, store_features,
  pages, contact_us, header_banner, banners, header_text, advertisements, choices, cities,
  common_questions, notifications). Key abilities: `dashboard.view`; products
  `product.view/edit/create/delete/trash.view/restore/delete.forever`; orders
  `order.view/edit/delete`; returns `return_order.view/delete`; **news: `news.view`
  (view the page), `news.send` (send the newsletter)**; settings `settings.edit`; reports
  only **`report.create`** (viewing `/admin/reports` requires the `report.create` ability);
  payments `payment.view`; notifications `notification.view`; groups
  `admin.group.view/create/edit/delete`; admins `admin.view/edit/create/delete/change_password`.
- **Gates**: `AuthServiceProvider` flattens the registry into Gates; `Gate::before` returns
  `true` for `super_admin` (bypass every Gate). `Admin::hasAbility($ability)`: **deny wins**
  — false if any of the admin's groups has a deny row for that ability, otherwise true
  only if an allow row exists. (Note: the UI can only create `allow` rows, so deny rows
  can only exist via seed/manual DB insert.)
- **Admin self-protection (escalation blocked)**: a non-super-admin gets 403 touching a
  `super_admin` account or their own record (`AdminsController::ensureTargetIsNotProtected`,
  called from edit/update/destroy/ChangePassword); `store`/`update` use validated data only,
  so `super_admin` cannot be mass-assigned (also not in `Admin::$fillable`); a non-super-admin
  gets 403 editing a rule group assigned to themselves (`RulesController::ensureGroupIsNotOwn`).
- **Enforcement**: nearly every dashboard action starts with `Gate::authorize('<ability>')`.
  Exceptions with no Gate (still behind the `admin` login middleware): `ProfileController`,
  `ProfileSettingsController`, `ProductsController@outOfStock` / `@fetchChoices`,
  `ShippingController@edit`, `DiscountCodeController@searchProducts`, and some empty `show()`
  methods.

### Reports — `GET /admin/reports` (`reports.index`, Gate `report.create`)

ONE page (single route; tabs/export are query params). Period filter: `today` / `7d` /
`30d` (default) / `custom` (max **92 days**), resolved in `Africa/Cairo`.
**Overview section above the tabs**: 4 KPI cards (revenue, orders, average order value,
new customers) + daily revenue/orders chart (Chart.js 4.4.0, reused from the dashboard home)
+ orders by status + COD-vs-card and paid-vs-unpaid splits.
**5 table tabs** (all paginated 15/page with distinct page names): (1) best sellers by
revenue + qty with category; (2) low stock (adjustable threshold, default 5) + zero-stock
with last sale date; (3) top customers by spend with orders + last order; (4) returns with
quantities + return rate; (5) coupons with times used, discount given, revenue.
**CSV export** on all 5 tables (`?export=<tab>`): same queries, streamed, UTF-8 BOM,
**`;` delimiter** (Arabic Windows/Excel list-separator reason), capped at **5000 rows**.
**Revenue definition**: `SUM(total_price)` over `payment_status=paid` AND
`status != cancelled` AND `return_order=false` within the period on `created_at`
(delivered COD counts as paid; `total_price` includes shipping+VAT); orders-by-status
counts ALL orders; coupon usage counts the paid set. Caveat: per-product revenue uses item
prices (pre-coupon), so best-sellers revenue can slightly overstate when a coupon was used.
Performance: SQL aggregates only; KPIs+trend cached 10 min keyed by period; migration
`2026_11_21_000001` adds composite index `orders_reports_idx` on
`orders(payment_status, status, created_at)`.

### Newsletter send page — `GET /admin/send_news` (`user_news.create`, Gate `news.view`)

- `create()`: reads `?search=` (trimmed), gets a **paginated (50/page)** subscriber list
  filtered by email LIKE when searching, plus the overall `$totalSubscribers` (the stats
  card shows the true total even while searching). View has the search form (GET, clear
  button when searching), the subscribers table with pagination links
  (`withQueryString()`, Bootstrap 5), row numbers via `firstItem()`, and a select-all
  checkbox (count = current page count).
- `POST /admin/send_news_mail` (`user_news.send`, Gate **`news.send`**): validated
  (`title` ≤ 255, `body`, `users` array min 1, each id exists in `send_news_to_users`);
  loads subscribers `whereIn(id)->orderBy(id)->chunk(200)` and fires **one
  `NewsletterRequested` event per subscriber** — nothing is sent synchronously. Success
  flash (exact text): `تم استلام طلب إرسال النشرة وسيتم إرسالها للمشتركين` ("request
  received", not "sent").
- Queued sending: listener `SendNewsletterMail` (`ShouldQueue`, tries 5, backoff
  60/300/900/1800s, timeout 120s) calls `$subscriber->notify(new NewsletterNotification(...))`
  (`via()` = `['mail']`, view `emails.newsletter`). Listeners are auto-discovered
  (`EventServiceProvider::$listen` is empty, `shouldDiscoverEvents() = true`).
  Staff admins who send newsletters need the `news.send` ability assigned (super admins
  bypass).

### Admin notifications (database-only, by design)

- `OrderCreatedAdminNotification`, `OrderReturnedAdminNotification`,
  `ContactMessageAdminNotification`: `via()` returns **`['database']`** only. (Each has a
  `toMail()` method that is dead code — no admin email is ever sent.)
- Payloads: order-created — `title: يوجد طلب جديد`, `body: تمت اضافة الطلب #<number>…`,
  `url: orders.show`; return — `title: تم إرجاع طلب`, `url: return_orders.show`;
  contact — `title: يوجد رسالة جديده`, `url: contact_us.watch`.
- Senders: order-created via `SendAdminNotification` listener (`ShouldQueueAfterCommit`,
  tries 5) on the `UserOrder` event, to super-admins if any else all admins; return via
  `SendReturnNotification` on `OrderReturned`; contact messages notify admins directly
  from the contact controllers.
- Bell dropdown (topbar): latest N notifications + unread count badge; each item links
  through `notifications.read?notification_id=…&url=…`. Page `GET /admin/notifications`
  (Gate `notification.view`, paginated).
- `GET /admin/notifications/read` marks a notification read **on a GET** (it doubles as
  the click-through link) and redirects only to the URL's path+query (open-redirect guard;
  a URL with no path falls back to `back()`).

### Settings (`/admin/front_settings`, Gate `settings.edit`)

Single settings row, **5 tabs** (active tab remembered in localStorage):
المتجر (general: `website_name[_en]`, `subscription_title`, `email`, `phone_number`,
`address`, `tax_number`, `value_added_tax` 0–100, `free_shipping_threshold` in جنيه —
0 = disabled, `google_play`, `apple_store`), التواصل الاجتماعي (social links),
الشعار والصور (logo/image), **الدفع والرسائل** (Moyasar `publishable_key`/`secret_key` +
**payment toggles** + SMS `sms_api_key`/`sms_user_name`/`sms_sender`), SEO
(`seo_meta_*`, OG/Twitter, `google_analytics_id`, `google_tag_manager_id`, `robots_index`,
`canonical_url`). Payment toggles: checkboxes `payment_cod_enabled` (الدفع عند الاستلام)
and `payment_card_enabled` (الدفع بالبطاقة Moyasar), hidden `0` inputs; `SettingRequest`
requires **at least one** enabled. Socials have a `url` validation rule — a previously
saved non-URL handle blocks saving settings until corrected.

### Admin CRUD sections (brief)

| Section | Notes |
|---------|-------|
| Products | Name ar+en, `price` ≥ 0, `discount_price` < price, image jpeg/png/jpg/webp ≤ 5 MB, `status` active/archived, category required, company nullable, quantity, weight ≤ 100000, availability, choices/colors arrays with `exists`. Soft-delete trash/restore/force-delete (force-delete removes image + gallery files); out-of-stock list. |
| Categories | 3 seeded (gifts / household-essentials / accessories); `show()` empty. |
| Orders | Status-only update via enum + `canTransitionTo`; delete; delivered→paid COD-only. No create/edit of order contents. `show()` redirects a `return_order=true` order to `return_orders.show`. |
| Return orders | List/show/delete only; returns are `Order` rows with `return_order=true`. No status update. |
| Payments | Read-only Moyasar transaction log + detail; filter by status, search by Moyasar id / order number; 20/page; stats total_paid/paid_count/failed_count/today_paid. |
| Clients | Customers (User model): list/edit/password/delete; no admin-side create (customers self-register). |
| Admins | Admin accounts + group assignment (`rules` ids must exist); password change per admin; super-admin protections. |
| Rules (groups) | Named groups of abilities; create/edit/delete; own-group edit blocked for non-super-admins. |
| Designs | Page designs (`Design`: title, page_name, image, image_en, description). Home slots: `home_band_left/right`, `home_tv_banner`, `top_offer_1..5`, `new_products_banner`. Fallback to bundled assets when no image. |
| Banners | `NewHeaderBannerController` — header banner carousel (`HeaderBanner`: image ar/en + link). Distinct from the legacy single header banner (`HeaderBanerController`) and from designs. |
| Advertisements / marquee | Moving ad bar items, ar+en titles, `is_active` toggle; locale-keyed 1h cache, cleared on every mutation. An ad with empty `title_en` falls back to Arabic in English by design. |
| Discount codes | Code, `price` (amount), `discount_type` percentage/`price`, `status` active/inactive, `number_of_used` integer ≥ 0, optional product links (form product search, top 10). Percentage capped at 100. No `show` route. |
| Shipping types | Single settings row (id 1): pickup toggle, fixed price, weight price per kg, city-based on/off + per-city prices; index shows only `status='used'` countries/cities. |
| Countries / Cities | Country CRUD (Egypt-only by seeder, code `EG`), city CRUD; `GET /countries/{countryId}/cities` JSON for dependent selects. |
| Currencies | Currency CRUD + default-currency picker; storefront uses the `default_currency=true` row (EGP after seeding). |
| Pages | Static pages (terms, shipping, privacy, about, returns) with ar+en fields. |
| FAQ (`common_questions`) | Questions/answers ar+en; route path is `admin/dashboard/common_questions*`. |
| Features (`store_featuers`) | Store feature blocks (homepage quality/features); resource except `show`. |
| Colors | Color CRUD. |
| Contact messages | Read-only list + show; no delete; sidebar badge from unread notifications. |
| Bulk / Representative orders | Full CRUD; store/update validate name, Egyptian mobile, company (bulk), description. |
| Header text | Header texts CRUD (not in the sidebar). |


## Key Models & Relationships (`app/Models/`, 39 files)

Only `Order` and `Product` use SoftDeletes.

| Model | Notes |
|-------|-------|
| `Admin` | Extends `User`, guard `admin`; `super_admin` boolean (not fillable); `rules()` belongsToMany `Rule` via `admin_rule`; `hasAbility($ability)` (deny beats allow) |
| `Advertisement` | `title, title_en, is_active` |
| `AppliedDiscountCode` | `applied_discount_codes`, no timestamps; `cookie_id, discount_id` — one row = code applied to a cart keyed by cookie |
| `BulkOrder` | `name, phone, company_name, description` |
| `Cart` | ULID ids; cookie `cart_id` (30 days); `cookie_id, status, product_id, user_id, quantity, weight, discounted_price`; global scope restricts queries to current user (`user_id`) or guest (`cookie_id`); `getCookieId()` / `getCookieIdApi()` (API uses `guest-id` header) |
| `Choice` | Self-referencing options (`name, parent_id`); links to categories and products |
| `City` | `name_ar, name_en, code, status, country_id, shipping_price`; locale `name` accessor |
| `Color` | `color_code, name`; products via `color_product` |
| `Comment` | Product reviews: `comment, user_id, rate, product_id` |
| `CommonQuestion` | FAQ: `title, description, title_en, description_en`; `localizedTitle()/localizedDescription()` |
| `Company` | Brands: `name, name_en, image, product_id` |
| `ContactUs` | `name, phone_number, message, email` |
| `Country` | `name_ar, name_en, code, status, phone_code`; `cities()`; `Country::egyptId(): ?int` — Egypt by code `EG`, fallback to sole country row, else null; never hardcodes an id |
| `Currency` | `name, name_ar, status, code, symbol, price_in_default_currency, default_currency` |
| `Design` | Home banners: `title, page_name, image, image_en, description`; `getCurrentImageLangAttribute()` |
| `DiscountCode` | `name, code, price, status` (enum active/inactive), `discount_type`, `number_of_used`; products via `discount_code_product`. `price` = amount OR % depending on `discount_type` |
| `Guest` | `cookie_id, first_name, email, family_name, phone_number, address`; orders, wishlist, returns |
| `HeaderBanner` | `header_image, header_image_en, image_link`; `getCurrentImageLangAttribute()` |
| `HeaderText` | `title, description` |
| `MainCategory` | `parent_id, name, name_en, description, description_en, image, slug`; self-referencing category/children, products, choices |
| `Order` | **SoftDeletes**; `status` cast to `OrderStatus` enum; fillable: `user_id, guest_id, discount_code_id, cookie_id, payment_method, status, payment_status, return_order, note, totalBeforeDiscount, total_price, shipping_price, tax_amount` (+ `number`, set by hook). Relations: user (default name زائر), guest, discountCode, transactions, latestTransaction, products (via order_items), addresses, orderItems, billing/shippingAddress, choices |
| `OrderAddress` | `order_id, type, first_name, last_name, email, phone_number, country_id, city_id, address` |
| `OrderItem` | Extends Pivot, `order_items`; `order_id, product_id, product_name, price, quantity` |
| `Page` | `title, content, title_en, content_en`; `localizedTitle()/localizedContent()` |
| `PaymentTransaction` | `order_id, moyasar_payment_id, status, amount` (float, "in SAR, not halalas"), `currency, payment_method, card_brand, card_last_four, description, raw_response, ip_address`; statuses paid/failed/initiated/authorized |
| `Product` | **SoftDeletes**; fillable: `name, name_en, description, description_en, image, price, discount_price, status, category_id, parent_id, company_id, quantity, is_special, product_availability_id, weight, slug`. `creating` hook keeps an explicitly set slug (uniquified via `SlugHelper::unique`), else slugifies `name`. Relations: orders/orderItems, cartItems, choices, discountCodes, comments, company, colors, images, features, availability, wishlistUsers; `scopeSearchText()` uses `ArabicSearch` REGEXP; `parent()` = category |
| `ProductAvailability` | `name, name_en` |
| `ProductFeature` | `feature_name, feature_name_en, feature_description, product_id` |
| `ProductImage` | `image, product_id`; `image_url` appends |
| `RepresentativesOrder` | `name, phone, description` |
| `ReturnProduct` | `return_products`: `user_id, guest_id, product_id` |
| `Rule` | Admin permission groups: `name`; `abilities()` hasMany `RuleAbility`; `createWithAbilies()/updateWithAbilies()` in transactions |
| `RuleAbility` | `rule_id, ability, type` (`allow`/deny) |
| `SendNewsToUser` | `send_news_to_users`, Notifiable; `subscription_email`; `routeNotificationForMail()` returns it |
| `Setting` | Single row (id 1). Encrypted casts: `secret_key, sms_api_key, publishable_key`. `payment_cod_enabled/payment_card_enabled` boolean. `enabledPaymentMethods()` (never empty — falls back to COD), `allowedPaymentMethodValues()` (+ legacy `cash` alias) |
| `ShippingType` | **DEAD** — table `shipping_types` was dropped; zero references outside the model file. `ShippingTypesAndPrice` is the live one |
| `ShippingTypesAndPrice` | `shipping_types_and_price`: `add_pickup_from_store, add_wight_price, add_normal_price, add_price_based_on_city, weight_price, normal_shipping_price` |
| `StoreFatuer` | Table `store_featuers` (typo in name): `image, title, title_en, description` |
| `User` | Sanctum `HasApiTokens`; `password` cast `hashed`; `first_name, email, password, family_name, phone_number, address, image`; wishlist, addresses, orders, comments |
| `UserAddress` | `address_title, first_name, family_name, phone_number, user_id, address, city_id, country_id, main_address`; `getAddressTitleAttribute()` translates system titles (`عنوان جديد` ⇄ `New Address`, `العنوان الأساسي` ⇄ `Main Address`) on read; user-typed titles untouched |
| `User_verfication` | Table `users_verificationCodes`: `user_id, code, verification_code_expires_at, compare_code, is_reset_password, is_verified` |

**Notable model facts**: `users` has NO `city_id`/`country_id` columns — registration stores
them on the customer's **main address**. `Product::$parent` is `belongsTo(MainCategory::class, 'category_id')`;
`products.category_id` is nullable; resources use null-safe access. `Order::$casts['status']`
is the `OrderStatus` enum; `return_order` is NOT cast (int 0/1).

---

## Migrations & Seeders

### Notable migrations

- `2024_01_01_000012` — `orders` (+ `order_items`, `order_addresses`, `order_choices`,
  `return_products`); `order_status_id` FK + legacy `order_statuses` table (still created,
  but the live status is the enum on `orders.status`)
- `2024_01_01_000015` — `settings` (`value_added_tax` decimal default 0; Moyasar/SMS keys)
- `2024_01_01_000010` — `discount_codes` (`code` unique, `price`, `status`, `discount_type`
  default `fixed`, `number_of_used`) + `cookie_discount_ids` (renamed later)
- `2026_11_18_000001` — `carts.cookie_id` widened to VARCHAR(255)
- `2026_11_18_000002` — `settings.free_shipping_threshold` (nullable; backfills nulls to 500)
- `2026_11_18_000003` — English fields on pages + drop of dead tables/columns
- `2026_11_19_000001` — English fields on FAQ/products/categories
- `2026_11_19_000002` — `orders.tax_amount` decimal(10,2) nullable
- `2026_11_19_000003` — index on `products.status`
- `2026_11_19_000004` — `Setting` secrets widened to TEXT + legacy plaintext encrypted in place
- `2026_11_19_000005` — renames `cookie_discount_ids` → `applied_discount_codes` (data kept)
- `2026_11_19_000006` — `settings.payment_cod_enabled` + `payment_card_enabled` (both default true)
- `2026_11_21_000001` — composite index `orders_reports_idx` on
  `orders(payment_status, status, created_at)` (reports)
- `2026_11_18_000001` (enum) — `orders.status` → MySQL ENUM
  (`pending,confirmed,processing,shipped,delivered,cancelled`) NOT NULL DEFAULT `pending`
- `2026_04_01_190146` — `discount_codes.status` boolean → ENUM(`active`,`inactive`)
- `2026_04_05_171613` — `payment_transactions` (`amount` decimal "in SAR (not halalas)",
  `currency` default `SAR`, `moyasar_payment_id` indexed)
- `2026_11_10/11` — soft deletes on `products` / `orders`; `2026_11_11_000003` — unique
  index on `orders.number`; `2026_11_11_000009` — drops the `shipping_types` table
  (hence the dead `ShippingType` model)

### Seeders (`database/seeders/`)

`DatabaseSeeder` core (all envs): `AdminSeeder, SettingsSeeder, CurrencySeeder,
CountryCitySeeder, ShippingTypesAndPriceSeeder, StaticPagesSeeder, StoreFeaturesSeeder`.
Demo/local-only: `CompanySeeder, AdvertisementSeeder, ProductSeeder, DesignSeeder, UserSeeder`.
`TopOffersSeeder` is intentionally NOT called (duplicates `DesignSeeder` rows). No seeder
for `Rule`/`RuleAbility` — a non-super-admin starts with no abilities.

| Seeder | What it seeds |
|--------|---------------|
| `AdminSeeder` | `admin@dabberhaly.com` (env `ADMIN_EMAIL`), password env `ADMIN_PASSWORD` else **`12345678`**; `super_admin=true` set directly (not fillable); the existing row is NOT reset on re-seed unless a password is configured. (Note: the `.env.example` comment claiming a random password is generated and printed once is stale — the code defaults to `12345678`.) |
| `SettingsSeeder` | Row id 1: `website_name` دبّرهالي / `Dabberhaly`, contact fields null, `value_added_tax=0`, `free_shipping_threshold=500`; Moyasar/SMS keys copied from env — no hardcoded keys |
| `CurrencySeeder` | Deletes SAR/USD/EUR/GBP/AED, then EGP only: `Egyptian Pound` / جنيه مصري, symbol جنيه, rate 1, `default_currency=true`, `status=used` |
| `CountryCitySeeder` | Egypt only: code `EG`, `phone_code +20`, `status=used`; **27 governorates** with per-city `shipping_price` (Cairo/Giza 20, Alexandria/Dakahlia/Beheira/Gharbia/Menofia/Sharkia 30, Qalubia 25, Fayoum/Ismailia/Suez/Beni Suef/Port Said/Damietta/Kafr El Sheikh 35, Minya/Assiut/Sohag 40, Luxor/Qena/Red Sea 45, New Valley/Aswan/South Sinai/Matrouh/North Sinai 50). Deletes all non-EG countries |
| `ShippingTypesAndPriceSeeder` | Row **id 1**: pickup on, weight on, fixed on, city-based off, `weight_price=5.00`, `normal_shipping_price=20.00` |
| `StaticPagesSeeder` | Pages id 1–5: Terms, Shipping policy (all Egypt governorates; Cairo/Giza 1–2 days, others 2–4; free shipping over 200 جنيه), Privacy, About, Returns (14 days, original condition + invoice). 10 FAQ rows (tracking, payment methods incl. COD + cards + bank transfer, delivery times, cancel within 2h, return within 14 days, free shipping over 200 جنيه, authenticity, support Sat–Thu 9–6, no minimum order) |
| `AdvertisementSeeder` | 3 active ads: free shipping over 200 جنيه / 10% off first order / shop now |
| `ProductSeeder` | 3 categories (gifts هدايا, household-essentials مستلزمات منزلية, accessories اكسسوارات — deletes old electronics/clothing/home-kitchen + their demo products) + **16 demo products** (gifts 6, household 6, accessories 4; 9 discounted, 2 out of stock: طقم أواني طبخ + علبة عطور هدايا). Matches by `name_en`, slug = English slug; re-seeding does not duplicate. **Demo products have no photos** (image null → "No photo" placeholder). Also seeds availabilities (متاح/غير متاح), 4 colours, Default Company |
| `CompanySeeder` | 8 demo brands: Samsung, Apple, Sony, LG, Nike, Adidas, IKEA, Philips |
| `DesignSeeder` | Home banners by `page_name=home` (main banner, offers, featured, `home_band_left/right`, `home_tv_banner`, `top_offer_1–4`, …) |
| `UserSeeder` | Local only: test user `user@user.com` / `01112345678` / password `12345678` |
| `StoreFeaturesSeeder` | 5 features: Fast Shipping, Secure Payment, Free Returns (14 days), 24/7 Support, Authentic Products |

---

## Security & Validation

- **Egyptian mobile** — shared rule `App\Rules\EgyptianMobileNumber`:
  `/^(?:\+20|20|0)1[0125][0-9]{8}$/` (spaces/dashes stripped first). Accepts 010/011/012/015
  + 8 digits and `+20`/`20` forms; rejects 013/014/016/019, wrong lengths, letters,
  landline 02. Applied in 22 places (web + API register, API login, web profile, web/API/
  guest checkout, API addresses, web/API contact, API personal info, bulk/rep web/API/admin,
  API forget-password). Client-side mirror of the regex exists in `Register.vue`. The value
  is stored as typed (no normalization) — API login is exact-match on the stored string.
  Deliberately left on the old broader rule: `SettingRequest` `phone_number` (the store's
  own contact number — could be a landline or hotline).
- **OTP via moraSms** — `moraSms::sendSms` POSTs to `https://www.mora-sa.com/api/v1/sendsms`
  with credentials from the `Setting` row; codes are 4-digit `mt_rand(1000,9999)` and are
  **never returned in any API response**. API verify code expires in **60 seconds**; web
  password-reset code in **10 minutes**. `MAX_OTP_ATTEMPTS = 5` — on the 5th wrong attempt
  the code row is deleted so it can never be guessed later. `CheckUserVerification` does
  not regenerate/send an SMS when an unexpired code exists.
- **Throttling** — Web: register/verify/verify-resend/update-password each `throttle:5,1`;
  login 5 attempts (email+IP). API: register 5/IP/min, OTP verify 10/user/min, login
  5/phone+IP/min, forget-password 5/phone+IP/min, OTP reset 10/user/min, OTP resend
  5/user/min (RateLimiter, 60s decay, cleared on success). Admin login 5/email+IP.
  `POST /api/v1/create-guest` `throttle:10,1`. API forget-password/register return a
  uniform response whether the phone exists or not (no enumeration).
- **Sanctum** — tokens expire after **365 days** (`config/sanctum.php`, 525600 minutes).
- **Secrets** — `Setting` `secret_key` / `sms_api_key` / `publishable_key` are `encrypted`
  casts (migration encrypted old values, columns widened to TEXT).
- **Image uploads** — admin image rules allow-list mimes (`jpeg,png,jpg,gif,webp`, size
  caps 2–5 MB) and do not include `svg`. Note: no single global SVG ban was found in
  code — the restriction lives in the individual FormRequests; at least one repository
  upload helper does no mime check of its own.
- **Admin escalation** — fully blocked (see Admin → Permission system): non-super-admins
  cannot touch super-admin accounts or their own record, cannot edit their own group,
  and `super_admin` cannot be mass-assigned.
- **Other** — session cookie `Secure` defaults on in production; CORS from
  `CORS_ALLOWED_ORIGINS` (default APP_URL); `CheckUserVerification` no longer resends
  SMS when an unexpired code exists; checkout errors use typed `CheckoutException`
  (no internal message leak); payment/success pages are ownership-checked (else 404);
  order numbers are sequential, so a bare number lookup is not enough.

---

## Localization

- Languages: **Arabic** (default) and **English** (`config/app.php`: `locale=ar`,
  `fallback_locale=en`). Switched via `POST /locale/{lang}` (session).
- UI strings in `lang/ar.json` + `lang/en.json` (in sync; en.json has zero Arabic values);
  flash/validation messages in `lang/ar|en/{auth,flash,general,messages,validation}.php`.
  Currency key is `currency_egp` (ar: جنيه, en: EGP); `currency_sar` is gone.
- `useLocale` composable exposes `t()`, `locale`, `dir`, `pick(item, 'name')`.
  `app.blade.php` sets `<html lang dir>` from the locale; auth pages use `:dir` from `useLocale`.
- **Bilingual content pattern**: nullable `_en` columns + model `$fillable` +
  `localized*()` helpers (en + non-empty → English, else Arabic) + English fields in
  admin forms. Applied to: `pages`, `common_questions`, `products` (`description_en`),
  `main_categories` (`description_en`), `advertisements` (`title_en`), `designs`
  (`image_en`), `header_banners` (`header_image_en`), `cities`/`countries` (`name_en`),
  `store_featuers` (`title_en`), `product_features` (`feature_name_en`).
  An ad with empty `title_en` falls back to Arabic in English by design.
- **Arabic search normalization** (`App\Helper\ArabicSearch::toRegExpPattern()`): query-side
  only — converts the query to a REGEXP pattern where variant letters become character
  classes (`[اأإآٱ]`, `[ةه]`, `[ىي]`), applied via `Product::scopeSearchText()` on API
  search, web live search, and the `/products` page. Limitation: stored text with tashkeel
  between letters still won't match.
- `translateWithHTMLTags` (`app/Helper/TextTranslate.php`): returns the original unchanged
  when the target is `ar`; for `en` with empty English content the API falls back to live
  Google Translate calls (untested branch — external calls, can hit HTTP 429).
- **English content is still NULL** for pages/FAQ/products/categories — fine for an
  Arabic-only launch; English visitors see Arabic until the admin fills it.

---

## Deployment & Ops

- **`docs/DEPLOYMENT.md`**: Laravel 10 + Inertia 2 + Vue 3 + Vuetify 3; PHP 8.3
  (cli, common, mysql, mbstring, xml, curl, zip, bcmath, intl, gd), MySQL 8.0,
  Composer 2, Node 20, Supervisor + cron, HTTPS. Steps: `cp .env.example .env`,
  `key:generate`, `composer install --no-dev --optimize-autoloader`,
  `npm install && npm run build`, `migrate --force`, **`db:seed --force` BEFORE
  `config:cache`** (seeders read `env()`), `storage:link`,
  `config:cache && route:cache && view:cache`. Deploy checklist pulls branch **`stage`**,
  never unreviewed `main`.
- **Queue worker required** (emails/newsletters/reset codes queued; without it password
  reset is dead — code created synchronously, sending queued).
  `deploy/supervisor/dabberhaly-queue.conf`: `php artisan queue:work database --sleep=3
  --tries=3 --timeout=120`, `user=www-data`, `numprocs=1`, `autostart/autorestart=true`,
  log `storage/logs/queue-worker.log`. `--timeout=120` must stay below
  `config/queue.php` database `retry_after=180`.
- **Scheduler** (`app/Console/Kernel.php`): `telescope:prune` daily (only if
  `vendor/laravel/telescope` exists); `queue:prune-failed --hours=72` daily;
  `app:prune-expired-data` daily (deletes expired `users_verificationCodes` rows and
  carts untouched ≥ 30 days).
- **Telescope**: package in `require-dev`; provider registered only when
  `is_dir(vendor/laravel/telescope)` (so `composer install --no-dev` boots).
  `config/telescope.php`: enabled by default in local, off elsewhere; when disabled the
  routes are not registered at all (404, not 403). `viewTelescope` gate allow-list is
  empty (nobody can open `/telescope` in non-local). `.env.example` sets
  `TELESCOPE_ENABLED=false` (production template).
- **`.env.example`** (production-safe): `APP_ENV=production`, `APP_DEBUG=false`,
  `APP_URL=https://example.com`, `TELESCOPE_ENABLED=false`, `LOG_LEVEL=error`,
  `DB_DATABASE=dabberhaly`, `QUEUE_CONNECTION=database`, `SESSION_DRIVER=file`,
  `SESSION_LIFETIME=120`, `SESSION_SECURE_COOKIE=true`,
  `CORS_ALLOWED_ORIGINS=https://dabberhaly.com`, SMTP placeholders
  (`MAIL_FROM_ADDRESS=noreply@example.com`). **No Moyasar/SMS variables** (keys live in
  the `settings` table, managed at `/admin/front_settings`).
- **Moyasar webhook to register**: `POST {APP_URL}/api/v1/webhooks/moyasar` — not done yet.
- **Backups**: no package yet — manual mysqldump + storage tar cron, daily 02:00/02:30,
  keep 14 days. Health: `GET /up` → 200. Logs are daily, 14 days.
- **Local dev**: `APP_URL=http://127.0.0.1:8000`, `APP_ENV=local`, MySQL db `dabberhaly`
  (+ `ecommerce_testing` for PHPUnit), `QUEUE_CONNECTION=sync`, mail via mailpit.
  `public/build` is gitignored — build locally after pulling (`npm run build`).

---

## Known Limitations / Deferred

- **hreflang** is missing (deliberately deferred).
- `GET /admin/notifications/read` is a GET that mutates state (marks read) — intentional
  click-through design.
- The `canonical_url` setting is inert (canonical comes from the current URL).
- API discount codes are consumed at apply time and have no expiry dates (design left
  for later); removing an API discount does not restore `number_of_used`.
- Customers are **not notified** when their order status changes.
- Footer App Store / Google Play badges are non-link `<div>`s.
- Web change-password has **no current-password check** (no Vue form exists — needs UI
  + user decision).
- Header-banner / product-gallery link-only rows crash on a missing image (logic bug,
  not validation); `image_link` is validated as `string`, not `url` (relative paths exist).
- `Inertia\GuestOrdersController` is **dead code** (no routes point to it; its methods
  return Blade views `front.guest_orders.*` that do not exist). Guest order/return/
  wishlist on the web side has no UI; the live guest flows are the API controller and
  the checkout success / payment pages.
- `POST /user_orders/cancel` (web) has no UI caller; duplicate set-main-address routes
  exist (`/user/addresses/set-main/{id}` and `/user_addresses/{address}/set_main`).
- `newProductsBanner` is passed to `Home.vue` but never rendered; `TopCategories` and
  `WhyShopWithUs` are commented out on the home page.
- `ShippingType` model is dead (table dropped); legacy `order_statuses` table +
  `orders.order_status_id` still exist from the base migration (live status is the enum).
- Live route-name typos (do not "fix" silently — they are referenced): product detail
  `productss.show`, API `store-represintative-order`, API param `{mainCateory}`,
  admin `header_banner.sotreAndUpdate`, `productsController` lowercase in dashboard routes.
- Static-pages FAQ still advertises card/bank-transfer payment and "cancel within 2 hours"
  — copy may be stale vs. the cash-first launch decision.
- Newsletter sending is untested end-to-end (local queue is `sync`); Moyasar keys are
  empty in the local `.env`.
- The user still needs to: pick the final free-shipping number (200 vs 500 vs none) and
  align the 4 copy spots; uncheck "الدفع بالبطاقة" in `/admin/front_settings` for the
  COD-only launch; fill real contact/social values; add real products/categories and set
  real shipping prices (seeder values 20 flat / 5 per kg are placeholders); register the
  Moyasar webhook URL; pick a mail provider + a real `MAIL_FROM_ADDRESS`; provision the
  server (queue worker, scheduler cron, backup cron, `storage:link`) when one exists.
