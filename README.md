# EDPay for WooCommerce

The official EDPay WooCommerce integration is an **early preview/beta**. It has not been established as production-ready. Install it on a test store, use Sandbox first, and validate the complete payment and webhook flow before considering a live deployment.

The current implementation creates a payment request from the store server, redirects the customer to a payment URL returned by EDPay, and updates the WooCommerce order after validating a webhook. It is a gateway-agnostic integration; payment routing and supported methods depend on EDPay and the merchant account.

## Requirements

- WordPress 6.0 or later
- WooCommerce 8.0 or later
- PHP 8.1 or later with the `curl`, `json`, and `hash` extensions
- An EDPay merchant account with Sandbox credentials and a webhook secret

The plugin declares WooCommerce compatibility through version 9.2 and declares HPOS compatibility in its code. These declarations are not a substitute for testing against your store's exact WordPress and WooCommerce versions.

## Install the preview

From the WordPress installation directory, clone the repository into the plugins directory:

```bash
git clone https://github.com/edutecka/edpay-woocommerce-plugin.git wp-content/plugins/edpay-woocommerce-plugin
```

Then open **WordPress Admin → Plugins** and activate **EDPay Multi-Gateway Checkout**. This repository currently provides a source checkout; do not assume that a packaged or signed release is available.

## Configure Sandbox

1. In WordPress Admin, open **WooCommerce → Settings → Payments → EDPay Multi-Gateway Checkout**.
2. Enable the payment method.
3. For testing, enter a `pk_test_...` public key with its matching `sk_test_...` private key and webhook secret (`whsec_...`). For production, use the matching `pk_live_...` and `sk_live_...` pair. The key prefixes determine the mode; both use https://api-pay.edutecka.com. The plugin checks that both API keys are present and from the same mode. Keep the private key and webhook secret on the server.
4. Save the settings and place test orders.

All requests use the single EDPay API host, `https://api-pay.edutecka.com`; the matching key prefixes determine test or production access.

## Current checkout behavior and limitations

For a WooCommerce order, the plugin creates one parent payment with `POST /v1/payments`, then starts hosted checkout with `POST /v1/payments/{id}/public-checkout`. Both writes use stable order-based idempotency keys. It builds the hosted URL from the returned opaque token at `https://pay.edutecka.com/pay?token=...&pk=...`.

The plugin stores the payment ID and reference on the WooCommerce order. A webhook is applied only when the reference, `type=payment.succeeded`, `status=succeeded`, amount, and currency match that order; the customer's return to the store is not proof of payment.

## Configure and test the webhook

In the EDPay dashboard, register this callback URL, replacing the hostname with your store's HTTPS hostname:

```text
https://store.example/?wc-api=edpay_webhook
```

Configure the webhook secret in the WooCommerce payment settings and subscribe to payment lifecycle events. The plugin requires `Payment-Signature`, checks HMAC-SHA256 over `timestamp + "." + raw request body`, and defaults to a 300-second timestamp window. Duplicate or mismatched payment events are rejected.

Before considering a live deployment, test successful, failed, and canceled payments, repeated webhook deliveries, invalid signatures, and missing or malformed webhook data in Sandbox. Confirm that duplicate deliveries do not cause unacceptable behavior for your store.

## Security

- Never expose `sk_test_...`, `sk_live_...`, or `whsec_...` in browser code, public repositories, logs, or support screenshots.
- Keep the API private key and webhook secret on the server.
- Use HTTPS for the store and webhook endpoint.
- Verify payment state from validated webhooks, not from a customer redirect or return URL.

## Documentation

- [WooCommerce integration guide](https://docs-pay.edutecka.com/plugins/woocommerce/)
- [Payments API reference](https://docs-pay.edutecka.com/api/payments/)
- [Webhook integration guide](https://docs-pay.edutecka.com/api/webhooks/)

## License

The plugin header declares GPL-3.0-or-later.

## Other official SDK repositories

- [JavaScript / TypeScript](https://github.com/edutecka/edpay-javascript-sdk)
- [Python](https://github.com/edutecka/edpay-python-sdk)
- [PHP](https://github.com/edutecka/edpay-php-sdk)
- [Java](https://github.com/edutecka/edpay-java-sdk)
- [.NET](https://github.com/edutecka/edpay-dot-net-sdk)

## Other official e-commerce plugin repositories

These repositories are official integration previews. Check each repository's status and test it in a sandbox before using it with a live store.

- [Shopify](https://github.com/edutecka/edpay-shopify-app)
- [Magento / Adobe Commerce](https://github.com/edutecka/edpay-magento2-module)
- [PrestaShop](https://github.com/edutecka/edpay-prestashop-module)
- [OpenCart](https://github.com/edutecka/edpay-opencart-extension)
- [WHMCS](https://github.com/edutecka/edpay-whmcs-gateway)
