# shopwise-cart

Shopwise cart service: guest and customer carts, guest cart merge on sign-in, coupon code storage. Names and
prices shown on lines come from read models fed by `catalog.*` and `pricing.price-changed` events.

Run it from `shopwise-platform`: `tilt up -- --only=identity,catalog,cart`, seed the read models once with
`kubectl exec deploy/cart-http -- php artisan db:seed --force`, then

```sh
cart=$(curl -s -XPOST localhost:8105/api/carts | jq -r .data.id)
curl -s -XPUT localhost:8105/api/carts/$cart/items/TEE-MER-BLA-M -H 'Content-Type: application/json' -d '{"quantity":2}'
```

Contract: `shopwise-contracts/openapi/cart.yaml`. Conventions: `AGENTS.md`.
