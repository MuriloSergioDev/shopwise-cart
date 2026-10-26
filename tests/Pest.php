<?php

declare(strict_types=1);

use App\Models\CatalogProduct;
use App\Models\CatalogVariant;
use App\Models\SkuPrice;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Shopwise\Kit\Auth\KeySource;
use Shopwise\Kit\Messaging\Envelope;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

const CUSTOMER_ID = '01JBCDEFGHJKMNPQRSTVWXYZ99';

/**
 * Loads the contract example envelope for an event (shopwise-contracts/examples), optionally overriding data fields.
 *
 * @param  array<string, mixed>  $data
 * @param  array<string, mixed>  $envelope
 */
function contractExample(string $typeAndVersion, array $data = [], array $envelope = []): Envelope
{
    $json = file_get_contents(base_path("vendor/shopwise/contracts/examples/{$typeAndVersion}.json"));
    /** @var array<string, mixed> $payload */
    $payload = json_decode((string) $json, true, flags: JSON_THROW_ON_ERROR);
    /** @var array<string, mixed> $original */
    $original = $payload['data'];
    $payload['data'] = array_replace($original, $data);

    return Envelope::fromArray(array_replace($payload, $envelope));
}

/**
 * Authorization header with an identity-style access token for `$customerId`. The test key replaces identity's
 * JWKS, so no HTTP call is made.
 *
 * @return array{Authorization: string}
 */
function bearer(string $customerId = CUSTOMER_ID): array
{
    static $privateKey = null;
    static $publicKey = null;
    if ($privateKey === null) {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privateKey);
        $publicKey = openssl_pkey_get_details($key)['key'];
    }

    app()->instance(KeySource::class, new readonly class($publicKey) implements KeySource
    {
        public function __construct(private string $publicKey) {}

        public function keys(bool $refresh = false): array
        {
            return ['test' => new Key($this->publicKey, 'RS256')];
        }
    });

    $token = JWT::encode([
        'iss' => 'shopwise-identity',
        'sub' => $customerId,
        'email' => 'customer@shopwise.test',
        'roles' => ['customer'],
        'iat' => time(),
        'exp' => time() + 900,
    ], $privateKey, 'RS256', 'test');

    return ['Authorization' => "Bearer {$token}"];
}

/**
 * Puts a SKU in the read models: a published product with an active variant and, unless null, a USD price.
 */
function sellable(string $sku, ?int $unitAmount = 2900, bool $active = true, bool $published = true): CatalogVariant
{
    $productId = 'product-'.mb_strtolower($sku);
    if ($published) {
        CatalogProduct::query()->create(['id' => $productId, 'slug' => 'slug-'.mb_strtolower($sku), 'name' => "Product {$sku}", 'published_at' => now()]);
    }

    if ($unitAmount !== null) {
        SkuPrice::query()->create(['sku' => $sku, 'unit_amount' => $unitAmount, 'currency' => 'USD', 'changed_at' => now()]);
    }

    return CatalogVariant::query()->create([
        'id' => 'variant-'.mb_strtolower($sku),
        'product_id' => $productId,
        'sku' => $sku,
        'name' => "Variant {$sku}",
        'active' => $active,
        'changed_at' => now(),
    ]);
}
