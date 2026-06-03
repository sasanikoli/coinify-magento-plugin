# Coinify Payment Gateway for Magento 2

Accept cryptocurrency payments in your Magento 2 store via [Coinify](https://www.coinify.com).

## Requirements

- Magento 2.4.x
- PHP 7.4 or higher
- A Coinify merchant account (sandbox or production)

## Installation

```bash
composer require coinify/magento2-payment
bin/magento module:enable Coinify_Payment
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

## Configuration

Navigate to **Stores → Configuration → Sales → Payment Methods → Coinify**.

| Field | Description |
|-------|-------------|
| Enabled | Enable/disable the payment method |
| Payment Method Title | Label shown to customers at checkout |
| Environment | `sandbox` for testing, `production` for live payments |
| API Key | Your Coinify API key |
| Webhook Secret | Shared secret for webhook signature validation |
| Auto-create Credit Memo on Refund | Automatically create an offline Credit Memo when Coinify confirms a refund |

## Webhook Setup

Set your webhook URL in the Coinify dashboard to:

```
https://your-store.com/coinify/webhook/notify
```

The URL is also displayed in the Magento admin under **Stores → Configuration → Sales → Payment Methods → Coinify → Webhook URL**.

## Sandbox Testing

1. Set Environment to `sandbox` in configuration
2. Use your Coinify sandbox API key from [app.sandbox.coinify.com](https://app.sandbox.coinify.com)
3. Webhooks from the sandbox dashboard can be replayed for testing

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
php -d memory_limit=512M bin/magento cache:flush
```

After any PHP file changes, re-run `setup:di:compile` and `cache:flush`. For template-only changes, `cache:flush` is sufficient.

## License

MIT
