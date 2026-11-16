<?php
/**
 * Plugin Name: EDPay Multi-Gateway Checkout for WooCommerce
 * Plugin URI: https://edpay.co.mz
 * Description: Accept payments through the methods available from EDPay in your WooCommerce store.
 * Version: 1.0.0
 * Author: EDPay Engineering
 * Author URI: https://edpay.co.mz
 * Text Domain: edpay-woocommerce
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * WC requires at least: 8.0
 * WC tested up to: 9.2
 * License: GPLv3 or later
 */

if (!defined('ABSPATH')) {
    exit;
}

// Declare compatibility with High-Performance Order Storage (HPOS).
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

// Register the payment gateway.
add_filter('woocommerce_payment_gateways', function ($gateways) {
    $gateways[] = 'WC_Gateway_EDPay';
    return $gateways;
});

add_action('plugins_loaded', 'init_edpay_gateway_class');

function init_edpay_gateway_class()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    class WC_Gateway_EDPay extends WC_Payment_Gateway
    {
        private string $environment;
        private string $publicKey;
        private string $privateKey;
        private string $webhookSecret;

        public function __construct()
        {
            $this->id                 = 'edpay';
            $this->icon               = apply_filters('woocommerce_edpay_icon', '');
            $this->has_fields         = false;
            $this->method_title       = __('EDPay Multi-Gateway Checkout', 'edpay-woocommerce');
            $this->method_description = __('Accept payments through the methods available from EDPay.', 'edpay-woocommerce');

            $this->supports = ['products', 'refunds'];

            $this->init_form_fields();
            $this->init_settings();

            $this->title               = $this->get_option('title', 'EDPay');
            $this->description         = $this->get_option('description', 'Choose from the payment methods available through EDPay.');
            $this->environment         = $this->get_option('environment', 'sandbox');
            $this->publicKey           = trim((string) $this->get_option('public_key', ''));
            $this->privateKey          = trim((string) $this->get_option('private_key', ''));
            $this->webhookSecret       = $this->get_option('webhook_secret');

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
            add_action('woocommerce_api_edpay_webhook', [$this, 'handle_webhook']);
        }

        public function init_form_fields()
        {
            $this->form_fields = [
                'enabled' => [
                    'title'   => __('Enable/Disable', 'edpay-woocommerce'),
                    'type'    => 'checkbox',
                    'label'   => __('Enable EDPay payments', 'edpay-woocommerce'),
                    'default' => 'yes',
                ],
                'title' => [
                    'title'       => __('Checkout title', 'edpay-woocommerce'),
                    'type'        => 'text',
                    'description' => __('Name shown to the customer on the payment page.', 'edpay-woocommerce'),
                    'default'     => 'EDPay',
                    'desc_tip'    => true,
                ],
                'description' => [
                    'title'       => __('Description', 'edpay-woocommerce'),
                    'type'        => 'textarea',
                    'description' => __('Description shown below the title at checkout.', 'edpay-woocommerce'),
                    'default'     => 'Choose from the payment methods available through EDPay.',
                ],
                'environment' => [
                    'title'       => __('Environment', 'edpay-woocommerce'),
                    'type'        => 'select',
                    'description' => __('Select Sandbox to test before using the Production environment.', 'edpay-woocommerce'),
                    'default'     => 'sandbox',
                    'options'     => [
                        'sandbox'    => __('Sandbox (Testing)', 'edpay-woocommerce'),
                        'production' => __('Production', 'edpay-woocommerce'),
                    ],
                ],
                'public_key' => [
                    'title'       => __('Public Key', 'edpay-woocommerce'),
                    'type'        => 'text',
                    'description' => __('Use pk_test_... with sk_test_... or pk_live_... with sk_live_...; both keys are required.', 'edpay-woocommerce'),
                    'default'     => '',
                ],
                'private_key' => [
                    'title'       => __('Private Key', 'edpay-woocommerce'),
                    'type'        => 'password',
                    'description' => __('Format: sk_test_... or sk_live_...', 'edpay-woocommerce'),
                    'default'     => '',
                ],
                'webhook_secret' => [
                    'title'       => __('Webhook Secret', 'edpay-woocommerce'),
                    'type'        => 'password',
                    'description' => __('Format: whsec_... Find this in the EDPay dashboard. Required to verify the Payment-Signature header.', 'edpay-woocommerce'),
                    'default'     => '',
                ],
                'webhook_tolerance_seconds' => [
                    'title'       => __('Webhook Timestamp Tolerance (seconds)', 'edpay-woocommerce'),
                    'type'        => 'number',
                    'description' => __('Maximum accepted timestamp age or clock skew for Payment-Signature. Defaults to 300 seconds.', 'edpay-woocommerce'),
                    'default'     => '300',
                    'custom_attributes' => [
                        'min'  => '0',
                        'step' => '1',
                    ],
                ],
            ];
        }

        private function get_base_url(): string
        {
            return $this->environment === 'production'
                ? 'https://api.edpay.co.mz'
                : 'https://sandbox-api.edpay.co.mz';
        }

        private function has_valid_partner_key_pair(): bool
        {
            $publicMode = preg_match('/^pk_(test|live)_[a-fA-F0-9]{24}$/D', $this->publicKey, $publicMatch) ? $publicMatch[1] : null;
            $privateMode = preg_match('/^sk_(test|live)_[a-fA-F0-9]{64}$/D', $this->privateKey, $privateMatch) ? $privateMatch[1] : null;
            return $publicMode !== null && $publicMode === $privateMode;
        }

        public function process_payment($order_id)
        {
            $order = wc_get_order($order_id);
            if (!$order) {
                wc_add_notice(__('Order not found.', 'edpay-woocommerce'), 'error');
                return ['result' => 'fail'];
            }

            if (!$this->has_valid_partner_key_pair()) {
                wc_add_notice(__('Configure a valid EDPay public key and private key from the same environment.', 'edpay-woocommerce'), 'error');
                return ['result' => 'fail'];
            }

            $amount_minor = (int) round($order->get_total() * 100);
            $currency     = $order->get_currency();

            $headers = [
                'X-EDPay-Public-Key' => $this->publicKey,
                'X-EDPay-Private-Key' => $this->privateKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ];
            $key_prefix = 'woocommerce-order-' . $order->get_id();
            $link_response = wp_remote_post($this->get_base_url() . '/v1/payment-links', [
                'timeout' => 30,
                'headers' => $headers + ['Idempotency-Key' => $key_prefix . '-link-create'],
                'body' => wp_json_encode(['amount_minor' => $amount_minor, 'currency' => $currency]),
            ]);
            if (is_wp_error($link_response)) {
                wc_add_notice(__('Unable to communicate with the EDPay server: ', 'edpay-woocommerce') . $link_response->get_error_message(), 'error');
                return ['result' => 'fail'];
            }
            $link_code = wp_remote_retrieve_response_code($link_response);
            $link_body = json_decode(wp_remote_retrieve_body($link_response), true);
            $link_id = $link_body['data']['id'] ?? '';
            if ($link_code < 200 || $link_code >= 300 || !is_string($link_id) || $link_id === '') {
                wc_add_notice(__('Unable to create an EDPay payment link.', 'edpay-woocommerce'), 'error');
                return ['result' => 'fail'];
            }
            $session_response = wp_remote_post($this->get_base_url() . '/v1/checkout/sessions', [
                'timeout' => 30,
                'headers' => $headers + ['Idempotency-Key' => $key_prefix . '-checkout-session'],
                'body' => wp_json_encode(['payment_link_id' => $link_id]),
            ]);
            if (is_wp_error($session_response)) {
                wc_add_notice(__('Unable to communicate with the EDPay server: ', 'edpay-woocommerce') . $session_response->get_error_message(), 'error');
                return ['result' => 'fail'];
            }
            $session_code = wp_remote_retrieve_response_code($session_response);
            $session_body = json_decode(wp_remote_retrieve_body($session_response), true);
            $token = $session_body['data']['token'] ?? '';
            if ($session_code < 200 || $session_code >= 300 || !is_string($token) || $token === '') {
                wc_add_notice(__('Unable to create an EDPay checkout session.', 'edpay-woocommerce'), 'error');
                return ['result' => 'fail'];
            }
            $checkout_url = 'https://pay.edutecka.com/pay?' . http_build_query([
                'token' => $token, 'pk' => $this->publicKey,
            ], '', '&', PHP_QUERY_RFC3986);

            // Put the order on hold while payment is pending.
            $order->update_status('on-hold', __('Awaiting payment through EDPay.', 'edpay-woocommerce'));
            $order->update_meta_data('_edpay_payment_link_id', $link_id);
            $order->save();

            WC()->cart->empty_cart();

            return [
                'result'   => 'success',
                'redirect' => $checkout_url,
            ];
        }

        public function handle_webhook()
        {
            $raw_payload = file_get_contents('php://input');
            $signatureHeader = $_SERVER['HTTP_PAYMENT_SIGNATURE'] ?? '';
            $toleranceConfig = $this->get_option('webhook_tolerance_seconds', '300');
            $toleranceSeconds = filter_var($toleranceConfig, FILTER_VALIDATE_INT);
            $toleranceSeconds = $toleranceSeconds !== false && $toleranceSeconds >= 0 ? $toleranceSeconds : 300;

            if ($raw_payload === '' || $signatureHeader === '' || trim($this->webhookSecret) === '') {
                status_header(401);
                wp_send_json_error(['message' => 'Webhook signature or secret is missing']);
            }

            $signatureFields = [];
            foreach (explode(',', $signatureHeader) as $field) {
                $parts = explode('=', trim($field), 2);
                if (count($parts) === 2 && in_array($parts[0], ['t', 'v1'], true)) {
                    if (array_key_exists($parts[0], $signatureFields)) {
                        $signatureFields[$parts[0]] = null;
                        break;
                    }
                    $signatureFields[$parts[0]] = trim($parts[1]);
                }
            }
            $timestamp = $signatureFields['t'] ?? '';
            $signature = $signatureFields['v1'] ?? '';
            $signedAt = preg_match('/^\d+$/', $timestamp) ? filter_var($timestamp, FILTER_VALIDATE_INT) : false;
            $validHeader = $signedAt !== false && preg_match('/^[a-f0-9]{64}$/i', $signature);
            $validTimestamp = $validHeader && abs(time() - $signedAt) <= $toleranceSeconds;
            $computed = $validTimestamp ? hash_hmac('sha256', $timestamp . '.' . $raw_payload, $this->webhookSecret) : '';
            if (!$validTimestamp || !hash_equals($computed, strtolower($signature))) {
                status_header(401);
                wp_send_json_error(['message' => 'Invalid webhook signature']);
            }

            $event = json_decode($raw_payload, true);
            if ((empty($event['type']) && empty($event['event'])) || empty($event['data'])) {
                status_header(400);
                wp_send_json_error(['message' => 'Invalid event payload structure']);
            }

            $event_type = $event['type'] ?? $event['event'] ?? '';
            $data       = $event['data'];
            $payment_link_id = $data['payment_link_id'] ?? '';

            if (is_string($payment_link_id) && $payment_link_id !== '') {
                $orders = wc_get_orders([
                    'limit' => 1,
                    'meta_key' => '_edpay_payment_link_id',
                    'meta_value' => $payment_link_id,
                    'return' => 'objects',
                ]);
                $order = $orders[0] ?? null;
                if (!$order || $event_type !== 'payment.succeeded' || ($data['status'] ?? '') !== 'succeeded'
                    || !isset($data['amount_minor'])
                    || (int) $data['amount_minor'] !== (int) round((float) $order->get_total() * 100)
                    || strtoupper((string) ($data['currency'] ?? '')) !== strtoupper((string) $order->get_currency())
                    || empty($data['id'])) {
                    status_header(409);
                    wp_send_json_error(['message' => 'Payment link, status, amount, or currency mismatch']);
                }
                if ($order) {
                    if ($order->is_paid() || $order->get_meta('_edpay_payment_id')) {
                        status_header(409);
                        wp_send_json_error(['message' => 'Payment link already processed']);
                    }
                    if ($event_type === 'payment.succeeded') {
                        $order->add_meta_data('_edpay_payment_id', (string) $data['id'], true);
                        $order->save_meta_data();
                        $order->payment_complete($data['id'] ?? '');
                        $order->add_order_note(sprintf(__('Payment confirmed by EDPay. Transaction ID: %s. Payment method: %s', 'edpay-woocommerce'), $data['id'] ?? '', $data['method'] ?? 'Not provided'));
                    } elseif ($event_type === 'payment.failed') {
                        $order->update_status('failed', sprintf(__('EDPay payment failed: %s', 'edpay-woocommerce'), $data['failure_reason'] ?? 'Declined by the payment provider'));
                    } elseif ($event_type === 'payment.cancelled' || $event_type === 'payment.canceled') {
                        $order->update_status('cancelled', __('Payment canceled through EDPay.', 'edpay-woocommerce'));
                    }
                }
            }

            status_header(200);
            wp_send_json_success(['received' => true]);
        }
    }
}
