<?php
/**
 * Plugin Name: EDPay Multi-Gateway Checkout para WooCommerce
 * Plugin URI: https://edpay.co.mz
 * Description: Aceite pagamentos por M-Pesa, e-Mola, Cartões Visa/Mastercard (3DS), Apple Pay, Google Pay e Referências Bancárias na sua loja WooCommerce.
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

// Declarar compatibilidade com High-Performance Order Storage (HPOS)
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

// Registar o Gateway de Pagamento
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
        private string $emolaCustomMessage;

        public function __construct()
        {
            $this->id                 = 'edpay';
            $this->icon               = apply_filters('woocommerce_edpay_icon', '');
            $this->has_fields         = false;
            $this->method_title       = __('EDPay Multi-Gateway Checkout', 'edpay-woocommerce');
            $this->method_description = __('Receba pagamentos instantâneos através de M-Pesa, e-Mola, Cartões Visa/Mastercard, Apple Pay e Referências Bancárias.', 'edpay-woocommerce');

            $this->supports = ['products', 'refunds'];

            $this->init_form_fields();
            $this->init_settings();

            $this->title               = $this->get_option('title', 'EDPay (M-Pesa, e-Mola, Cartão, Carteiras Digitais)');
            $this->description         = $this->get_option('description', 'Pague com segurança através de Carteira Móvel, Cartão Visa/Mastercard ou Referência Bancária.');
            $this->environment         = $this->get_option('environment', 'sandbox');
            $this->publicKey           = $this->get_option('public_key');
            $this->privateKey          = $this->get_option('private_key');
            $this->webhookSecret       = $this->get_option('webhook_secret');
            $this->emolaCustomMessage  = $this->get_option('emola_custom_message', 'Pagamento de Encomenda');

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
            add_action('woocommerce_api_edpay_webhook', [$this, 'handle_webhook']);
        }

        public function init_form_fields()
        {
            $this->form_fields = [
                'enabled' => [
                    'title'   => __('Ativar/Desativar', 'edpay-woocommerce'),
                    'type'    => 'checkbox',
                    'label'   => __('Ativar pagamentos via EDPay', 'edpay-woocommerce'),
                    'default' => 'yes',
                ],
                'title' => [
                    'title'       => __('Título no Checkout', 'edpay-woocommerce'),
                    'type'        => 'text',
                    'description' => __('Nome visível para o cliente na página de pagamento.', 'edpay-woocommerce'),
                    'default'     => 'EDPay · Pagamento Seguro (M-Pesa, e-Mola, Cartão, Bancos)',
                    'desc_tip'    => true,
                ],
                'description' => [
                    'title'       => __('Descrição', 'edpay-woocommerce'),
                    'type'        => 'textarea',
                    'description' => __('Descrição apresentada por baixo do título no checkout.', 'edpay-woocommerce'),
                    'default'     => 'Pague de forma rápida e segura com Carteira Móvel (M-Pesa, e-Mola), Cartão Visa / Mastercard ou Referência Bancária.',
                ],
                'environment' => [
                    'title'       => __('Ambiente de Execução', 'edpay-woocommerce'),
                    'type'        => 'select',
                    'description' => __('Selecione Sandbox para efetuar testes antes de entrar em produção.', 'edpay-woocommerce'),
                    'default'     => 'sandbox',
                    'options'     => [
                        'sandbox'    => __('Sandbox (Testes)', 'edpay-woocommerce'),
                        'production' => __('Produção (Live)', 'edpay-woocommerce'),
                    ],
                ],
                'public_key' => [
                    'title'       => __('Chave Pública (Public Key)', 'edpay-woocommerce'),
                    'type'        => 'text',
                    'description' => __('Formato: pk_test_... ou pk_live_...', 'edpay-woocommerce'),
                    'default'     => '',
                ],
                'private_key' => [
                    'title'       => __('Chave Privada (Private Key)', 'edpay-woocommerce'),
                    'type'        => 'password',
                    'description' => __('Formato: sk_test_... ou sk_live_...', 'edpay-woocommerce'),
                    'default'     => '',
                ],
                'webhook_secret' => [
                    'title'       => __('Segredo do Webhook (Webhook Secret)', 'edpay-woocommerce'),
                    'type'        => 'password',
                    'description' => __('Formato: whsec_... Obtenha no Dashboard EDPay para verificação HMAC SHA-256.', 'edpay-woocommerce'),
                    'default'     => '',
                ],
                'emola_custom_message' => [
                    'title'       => __('Mensagem USSD e-Mola', 'edpay-woocommerce'),
                    'type'        => 'text',
                    'description' => __('Texto opcional (máx. 100 caracteres) enviado no prompt USSD Movitel.', 'edpay-woocommerce'),
                    'default'     => 'Pagamento Loja Online',
                ],
            ];
        }

        private function get_base_url(): string
        {
            return $this->environment === 'production'
                ? 'https://api.edpay.co.mz'
                : 'https://sandbox-api.edpay.co.mz';
        }

        public function process_payment($order_id)
        {
            $order = wc_get_order($order_id);
            if (!$order) {
                wc_add_notice(__('Encomenda não encontrada.', 'edpay-woocommerce'), 'error');
                return ['result' => 'fail'];
            }

            $amount_minor = (int) round($order->get_total() * 100);
            $currency     = $order->get_currency();

            $success_url = $this->get_return_url($order);
            $cancel_url  = $order->get_cancel_order_url();

            $payload = [
                'amount_minor'   => $amount_minor,
                'currency'       => $currency,
                'reference'      => 'WC-' . $order->get_id(),
                'return_url'     => $success_url,
                'cancel_url'     => $cancel_url,
                'customer'       => [
                    'name'  => $order->get_formatted_billing_full_name(),
                    'email' => $order->get_billing_email(),
                    'phone' => $order->get_billing_phone(),
                ],
                'metadata'       => [
                    'order_id'    => $order->get_id(),
                    'platform'    => 'woocommerce',
                ],
            ];

            $response = wp_remote_post($this->get_base_url() . '/v1/payments', [
                'timeout' => 30,
                'headers' => [
                    'X-EDPay-Public-Key'  => $this->publicKey,
                    'X-EDPay-Private-Key' => $this->privateKey,
                    'Content-Type'        => 'application/json',
                    'Accept'              => 'application/json',
                ],
                'body' => wp_json_encode($payload),
            ]);

            if (is_wp_error($response)) {
                wc_add_notice(__('Erro de comunicação com o servidor EDPay: ', 'edpay-woocommerce') . $response->get_error_message(), 'error');
                return ['result' => 'fail'];
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $checkout_url = $body['payment_url'] ?? $body['checkout_url'] ?? null;

            if ($status_code < 200 || $status_code >= 300 || empty($checkout_url)) {
                $msg = $body['message'] ?? __('Não foi possível iniciar o pagamento na EDPay.', 'edpay-woocommerce');
                wc_add_notice($msg, 'error');
                return ['result' => 'fail'];
            }

            // Marcar encomenda como a aguardar pagamento
            $order->update_status('on-hold', __('Aguardando pagamento na EDPay.', 'edpay-woocommerce'));
            $order->update_meta_data('_edpay_payment_id', $body['id'] ?? $body['payment_id'] ?? '');
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
            $signature   = $_SERVER['HTTP_X_EDPAY_SIGNATURE'] ?? '';

            if (empty($raw_payload) || empty($signature) || empty($this->webhookSecret)) {
                status_header(400);
                wp_send_json_error(['message' => 'Missing signature, payload or secret']);
            }

            $computed = hash_hmac('sha256', $raw_payload, $this->webhookSecret);
            if (!hash_equals($computed, strtolower($signature))) {
                status_header(401);
                wp_send_json_error(['message' => 'Invalid HMAC signature']);
            }

            $event = json_decode($raw_payload, true);
            if (empty($event['event']) || empty($event['data'])) {
                status_header(400);
                wp_send_json_error(['message' => 'Invalid event payload structure']);
            }

            $event_type = $event['event'];
            $data       = $event['data'];
            $order_id   = $data['metadata']['order_id'] ?? null;

            if ($order_id) {
                $order = wc_get_order($order_id);
                if ($order) {
                    if ($event_type === 'payment.succeeded') {
                        $order->payment_complete($data['id'] ?? '');
                        $order->add_order_note(sprintf(__('Pagamento EDPay confirmado com sucesso. ID Transação: %s. Método: %s', 'edpay-woocommerce'), $data['id'] ?? '', $data['method'] ?? 'multi-gateway'));
                    } elseif ($event_type === 'payment.failed') {
                        $order->update_status('failed', sprintf(__('Pagamento EDPay falhou: %s', 'edpay-woocommerce'), $data['failure_reason'] ?? 'Recusado pelo gateway'));
                    } elseif ($event_type === 'payment.canceled') {
                        $order->update_status('cancelled', __('Pagamento cancelado na EDPay.', 'edpay-woocommerce'));
                    }
                }
            }

            status_header(200);
            wp_send_json_success(['received' => true]);
        }
    }
}
