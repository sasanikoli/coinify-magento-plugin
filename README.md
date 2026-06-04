# Coinify Payment Gateway for Magento 2

Accept cryptocurrency payments in your Magento 2 store via [Coinify](https://www.coinify.com).

## Requirements

- Magento 2.4.x
- PHP 7.4 or higher
- A Coinify merchant account (sandbox or production)

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

## License

MIT
