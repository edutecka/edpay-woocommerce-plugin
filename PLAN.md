# Roadmap and Architecture Plan - EDPay WooCommerce Plugin

## Objectives
1. Develop an EDPay gateway integration for WooCommerce stores with hosted and direct checkout support.
2. Handle webhooks with HMAC-SHA256 verification and idempotency to avoid duplicate order approvals.
3. Support WooCommerce High-Performance Order Storage (HPOS).

---

## Development Phases

### Phase 1: Gateway Core and Settings (Complete)
- [x] Register the `WC_Gateway_EDPay` class extending `WC_Payment_Gateway`.
- [x] Declare support for `products` and `refunds`.
- [x] Add a settings form for API keys, environment, webhook secret, and USSD message.
- [x] Support the Mozambican metical (`MZN`).

### Phase 2: Checkout Processing and Hosted Session (Complete)
- [x] Create a payment request through `POST /v1/payments`.
- [x] Redirect to the EDPay hosted payment page.
- [x] Configure success (`return_url`) and cancellation (`cancel_url`) return URLs.

### Phase 3: Webhook IPN Listener (Complete)
- [x] Handle requests at `?wc-api=edpay_webhook`.
- [x] Verify HMAC-SHA256 signatures with `hash_equals`.
- [x] Update the order state after receiving `payment.succeeded`.

### Phase 4: WooCommerce Blocks and Modern Checkout (Planned)
- [ ] Integrate with the native WooCommerce Checkout block (Gutenberg Blocks).
- [ ] Preview the USSD prompt in direct checkout.
