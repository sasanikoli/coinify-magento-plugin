# Coinify Payment Gateway for Magento 2

Accept cryptocurrency payments in your Magento 2 store via [Coinify](https://www.coinify.com/payment-solutions#merchant-form).

## Requirements

- **Magento 2.4.x** — not compatible with Magento 1.x or Magento 2.3.x and below
- **PHP 8.1 or higher** — required by Magento 2.4.x (PHP 7.4 is end-of-life and unsupported)
- **A Coinify merchant account** — sandbox or production; [sign up here](https://www.coinify.com/payment-solutions#merchant-form)

**For local development** (setting up Magento from scratch):

- **MySQL 8.0+** or MariaDB 10.6+
- **Elasticsearch 7.x** or **OpenSearch 1.x** — required by Magento's catalog search
- **Nginx or Apache**
- **Composer 2.x**
- **`repo.magento.com` credentials** — Magento's private Composer repository; generate a key pair at [commercedeveloper.adobe.com](https://commercedeveloper.adobe.com)

## Installation

```bash
composer require coinify/magento2-payment
php -d memory_limit=512M bin/magento module:enable Coinify_Payment
php -d memory_limit=512M bin/magento setup:upgrade
php -d memory_limit=512M bin/magento setup:di:compile
php -d memory_limit=512M bin/magento setup:static-content:deploy -f
php -d memory_limit=512M bin/magento cache:flush
```

> **Note:** The `-d memory_limit=512M` flag prevents `setup:di:compile` from failing on servers with a low default PHP memory limit. The `-f` flag on `setup:static-content:deploy` is required when Magento is running in production mode.

## Configuration

Navigate to **Stores → Configuration → Payment Methods → Coinify**.

| Field | Description |
|-------|-------------|
| Enabled | Enable/disable the payment method |
| Payment Method Title | Label shown to customers at checkout |
| Environment | `sandbox` for testing, `production` for live payments |
| API Key | Your Coinify API key |
| Webhook Secret | Shared secret for webhook signature validation — **required** |
| Auto-create Credit Memo on Refund | Automatically create an offline Credit Memo when Coinify confirms a refund |

> **Important:** The Webhook Secret must be set before accepting payments. Without it, all incoming webhooks will be rejected and order statuses will not update. A warning banner is displayed in the Magento admin until the secret is configured.

## Webhook Setup

1. Copy the **Webhook URL** shown in the Coinify configuration page (e.g. `https://your-store.com/coinify/webhook/notify`)
2. Paste it into your Coinify dashboard under **Webhook settings**
3. Copy the generated webhook secret from the Coinify dashboard back into the **Webhook Secret** field in Magento and save

## Sandbox Testing

1. Set Environment to `sandbox` in configuration
2. Use your Coinify sandbox API key from [app.sandbox.coinify.com](https://app.sandbox.coinify.com)
3. Set the webhook URL and secret in the sandbox dashboard under **Settings → Webhooks**
4. Place a test order and complete payment in the Coinify sandbox window
5. Order status will update to **Processing** and an invoice will be created automatically once the webhook is received

## Refunds

Initiate refunds from the order view in Magento admin under the **Coinify Payments** section. Coinify will email the customer to collect their wallet address. Once the crypto transfer completes, Coinify sends a `payment-intent.refund.completed` webhook which updates the refund status and (if enabled) automatically creates a Credit Memo.

## Development

To work on this module locally, you need a running Magento 2.4.x instance. The recommended approach is to install the module via a Composer path repository so edits are reflected immediately without copying files.

In your Magento root `composer.json`, add:

```json
"repositories": [
    {
        "type": "path",
        "url": "../coinify-magento-plugin",
        "options": {"symlink": true}
    }
]
```

Then install:

```bash
composer require coinify/magento2-payment
php -d memory_limit=512M bin/magento setup:upgrade
php -d memory_limit=512M bin/magento setup:di:compile
php -d memory_limit=512M bin/magento setup:static-content:deploy -f en_US
php -d memory_limit=512M bin/magento cache:flush
```

After any PHP file changes, re-run `setup:di:compile` and `cache:flush`. For template-only changes, `cache:flush` is sufficient.

## Testing

The plugin ships with a standalone unit test suite that runs without a Magento installation. Tests cover all security-critical paths and core business logic.

### Running tests locally

```bash
# First time only — install PHPUnit (uses a separate composer file so
# Magento packages are not required)
COMPOSER=composer-dev.json composer install

# Run the full suite
vendor-test/bin/phpunit

# Run with detailed output
vendor-test/bin/phpunit --testdox
```

### What is tested

| Test file | What it covers |
|---|---|
| `Test/Unit/Model/ConfigTest` | `decryptValue()` logic for empty / plaintext / Magento-encrypted values; all config getters |
| `Test/Unit/Model/Api/ClientTest` | Sandbox vs production URL selection; `X-API-KEY` header presence/absence; GET vs POST dispatch |
| `Test/Unit/Observer/CheckWebhookSecretTest` | Admin warning shown when secret is missing; not shown when set or plugin inactive; AJAX requests skipped |
| `Test/Unit/Controller/Webhook/NotifyTest` | HTTP 400 when `webhook_secret` not configured; HTTP 400 on invalid HMAC signature; HTTP 400 on intent/order ID mismatch (IDOR guard); HTTP 200 for a valid webhook |
| `Test/Unit/Controller/Checkout/RedirectTest` | URL allowlist accepts valid production and sandbox domains; rejects evil subdomain spoofing and plain HTTP; redirects to cart when no order in session |
| `Test/Unit/Model/Service/RefundProcessorTest` | Refund record saved with state `initiated`; correct refund ID extracted from `merchantRefunds`; no record saved when API returns no refunds |
| `Test/Unit/Model/Service/CreditMemoProcessorTest` | Full refund path (includes shipping amount); partial refund path (uses `adjustment_positive`); early return when `canCreditmemo()` is false |

### CI

Tests run automatically on every push and pull request to `main` via GitHub Actions ([`.github/workflows/ci.yml`](.github/workflows/ci.yml)), tested against PHP 8.2 and 8.3.

The CI workflow installs only PHPUnit (via `composer-dev.json`) — it does not require Magento or any Magento credentials.

## License

MIT
