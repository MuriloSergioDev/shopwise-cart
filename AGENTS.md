# shopwise-cart

Guest and customer carts. Lines are SKUs + quantities; names, availability and unit prices come from local read
models fed by Kafka, so they are display data only (totals and coupon validity come from pricing quotes, which the
BFF asks for). Created from `shopwise-service-template`: structure, checks and how to run PHP are described there
and still apply.

## Service facts
- **Owns**: `carts` (`customer_id` = JWT `sub`, null for a guest cart; `version`; `checked_out_at` + `checkout_id` + `order_id` once checkout closed it; `locked_by_checkout_id` while a checkout holds it; a partial unique index allows one *open* cart per customer), `cart_lines` (auto-increment id = order of first add, unique per cart+SKU). Read models: `catalog_products` (a row = the product is published), `catalog_variants`, `sku_prices`. Database `cart`.
- **Publishes** (key = cartId): `cart.cart-checked-out` when `POST /internal/carts/{id}/checked-out` closes a cart, with the lines checkout sent (`schemas/events/cart/cart-checked-out.v1.json`).
- **Consumes** (consumer `read-models`, one deployment for all three topics):
  - `catalog.product-published` → product slug/name + its variants (active).
  - `catalog.variant-changed` → variant name + active flag; draft variants are stored and become sellable once the product is published.
  - `pricing.price-changed` → `amount`/`currency` per SKU, rendered as `unitAmount`/`currency` on lines.
- **HTTP** (BFF only) — contract `shopwise-contracts/openapi/cart.yaml`. `POST /api/carts` (guest, public); `GET /api/me/cart`, `POST /api/me/cart/merge` (`auth.jwt`); `GET /api/carts/{id}`, `PUT|DELETE /api/carts/{id}/items/{sku}`, `PUT|DELETE /api/carts/{id}/coupon` (`auth.jwt.optional`); `POST /api/carts/{id}/snapshot {checkoutId}` (`auth.jwt`, checkout's step 1: snapshot + lock); `GET /api/carts/{id}/snapshot` (deprecated, no lock).
  Internal (`routes/internal.php`, prefix `/internal`, no JWT, cluster-only per ADR-0004): `POST /internal/carts/{id}/checked-out` (checkout's step 7), `POST /internal/carts/{id}/unlock {checkoutId}` (failed checkout).
- **REST dependencies**: identity's JWKS (kit `Jwks`, `IDENTITY_JWKS_URL`) to verify customer tokens. Never calls catalog or pricing.
- **Local URL**: http://localhost:8105 (Tilt port-forward).

## Rules
- Access: a guest cart is reachable by id alone; a customer cart only with its owner's token. Everything else is 404, never 403 (`Cart::accessibleBy`). `auth.jwt.optional` (`app/Http/Middleware/OptionalJwt`) lets token-less requests through but still 401s a bad token, so the BFF refreshes instead of seeing a 404.
- For sale = active variant of a published product. Only for-sale SKUs can be set (else 422 `errors.sku`); existing lines stay and render `available: false`.
- Limits: 50 lines (422 `errors.items`), quantity 1..99. Merge adds quantities per SKU capped at 99, appends new SKUs while there is room (the rest are not added and come back in `meta.droppedSkus`), keeps the guest coupon only if the customer has none, deletes the guest cart, and is a no-op for an unknown or already merged guest id.
- Read-model handlers are last-writer-wins on the event time (`changed_at`), so redeliveries and cross-topic reordering cannot roll state back. Variants are keyed by SKU (catalog never reuses or changes one).
- Coupon codes are stored uppercased and not validated here.
- Every change goes through `Cart::recordChange()`, which bumps `version` in the database (and `updated_at`). Edits lock the open cart (`Cart::open()`), so they serialise with closing it.
- Snapshot: only the caller's open customer cart; guest, closed or someone else's cart is 404, no token 401.
- Checkout lock (`App\Actions\TakeCartSnapshot`): POST snapshot locks the cart for `checkoutId` in the same transaction (an empty cart too); the same checkoutId re-locks, another is 409. Locking does not bump `version`. While locked, item/coupon edits and a merge into the cart are 409 `{message, code: "cart_locked", checkoutId}` (`App\Exceptions\CartLocked`, via `Cart::ensureUnlocked()`); a refused merge keeps the guest cart. Reads show `lockedByCheckoutId`.
- Unlock (`App\Actions\UnlockCart`) clears only the caller's own lock and is idempotent; returns `{data: {cartId, lockedByCheckoutId}}`. Unknown or closed cart 404.
- Closing (`App\Actions\CheckOutCart`) is idempotent per `checkoutId`: a repeat is 200 with the same body `{data: {cartId, checkoutId, orderId, closedAt}}` and no second event; a cart closed or locked by a different checkout is 409; unknown or guest cart is 404. Closing clears the lock. A closed cart is 404 everywhere (`accessibleBy` excludes it), its lines are kept, and `GET /api/me/cart` opens a new cart.
- `GET /api/me/cart` answers 201 when it created the cart, 200 otherwise.

## Data
- `db:seed` fills the read models with shopwise-catalog's demo products (same slugs, names, SKUs) and USD demo prices, with made-up ids and `changed_at` = 2000-01-01, so real events overwrite them. Running it again does nothing.
- Guest carts untouched for 30 days are deleted by `model:prune` (scheduled daily, `Cart::prunable`).
