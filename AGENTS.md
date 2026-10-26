# shopwise-cart

Guest and customer carts. Lines are SKUs + quantities; names, availability and unit prices come from local read
models fed by Kafka, so they are display data only (totals and coupon validity come from pricing quotes, which the
BFF asks for). Created from `shopwise-service-template`: structure, checks and how to run PHP are described there
and still apply.

## Service facts
- **Owns**: `carts` (`customer_id` = JWT `sub`, null for a guest cart; unique, so one cart per customer), `cart_lines` (auto-increment id = order of first add, unique per cart+SKU). Read models: `catalog_products` (a row = the product is published), `catalog_variants`, `sku_prices`. Database `cart`.
- **Publishes**: nothing (P2).
- **Consumes** (consumer `read-models`, one deployment for all three topics):
  - `catalog.product-published` → product slug/name + its variants (active).
  - `catalog.variant-changed` → variant name + active flag; draft variants are stored and become sellable once the product is published.
  - `pricing.price-changed` → `amount`/`currency` per SKU, rendered as `unitAmount`/`currency` on lines.
- **HTTP** (BFF only) — contract `shopwise-contracts/openapi/cart.yaml`. `POST /api/carts` (guest, public); `GET /api/me/cart`, `POST /api/me/cart/merge` (`auth.jwt`); `GET /api/carts/{id}`, `PUT|DELETE /api/carts/{id}/items/{sku}`, `PUT|DELETE /api/carts/{id}/coupon` (`auth.jwt.optional`).
- **REST dependencies**: identity's JWKS (kit `Jwks`, `IDENTITY_JWKS_URL`) to verify customer tokens. Never calls catalog or pricing.
- **Local URL**: http://localhost:8105 (Tilt port-forward).

## Rules
- Access: a guest cart is reachable by id alone; a customer cart only with its owner's token. Everything else is 404, never 403 (`Cart::accessibleBy`). `auth.jwt.optional` (`app/Http/Middleware/OptionalJwt`) lets token-less requests through but still 401s a bad token, so the BFF refreshes instead of seeing a 404.
- For sale = active variant of a published product. Only for-sale SKUs can be set (else 422 `errors.sku`); existing lines stay and render `available: false`.
- Limits: 50 lines (422 `errors.items`), quantity 1..99. Merge adds quantities per SKU capped at 99, appends new SKUs while there is room (the rest are dropped), keeps the guest coupon only if the customer has none, deletes the guest cart, and is a no-op for an unknown or already merged guest id.
- Read-model handlers are last-writer-wins on the event time (`changed_at`), so redeliveries and cross-topic reordering cannot roll state back. Variants are keyed by SKU (catalog never reuses or changes one).
- Coupon codes are stored uppercased and not validated here.
- `GET /api/me/cart` answers 200 even when it created the cart.

## Data
- `db:seed` fills the read models with shopwise-catalog's demo products (same slugs, names, SKUs) and USD demo prices, with made-up ids and `changed_at` = 2000-01-01, so real events overwrite them. Running it again does nothing.
- Guest carts untouched for 30 days are deleted by `model:prune` (scheduled daily, `Cart::prunable`).
