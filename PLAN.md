# Roadmap & Plano de Arquitetura - EDPay WooCommerce Plugin

## Objectivos
1. Disponibilizar gateway oficial certificado para lojas online WooCommerce com suporte a checkout alojado e checkout transparente.
2. Tratamento robusto de webhooks com verificação HMAC SHA-256 e idempotência para evitar duplicação de aprovação de encomendas.
3. Compatibilidade com High-Performance Order Storage (HPOS) do WooCommerce.

---

## Fases de Desenvolvimento

### Fase 1: Gateway Core & Definições (Concluído)
- [x] Registo da classe `WC_Gateway_EDPay` herdada de `WC_Payment_Gateway`.
- [x] Declaração de suporte a `products`, `refunds`, `woocommerce_blocks`.
- [x] Formulário de definições (Chaves API, ambiente, segredo de webhook, mensagem USSD).
- [x] Suporte à moeda Metical Moçambicano (`MZN`).

### Fase 2: Processamento de Checkout & Sessão Alojada (Concluído)
- [x] Criação de sessão de checkout através do endpoint `POST /v1/checkout/sessions`.
- [x] Redirecionamento seguro para a página de pagamento EDPay.
- [x] Handlers de retorno de sucesso (`return_url`) e cancelamento (`cancel_url`).

### Fase 3: Webhook IPN Listener (Concluído)
- [x] Handler no endpoint `?wc-api=edpay_webhook`.
- [x] Validação em tempo constante da assinatura HMAC SHA-256 (`hash_equals`).
- [x] Transição de estado de encomenda (`wc-processing` / `wc-completed` ao receber `payment.succeeded`).

### Fase 4: WooCommerce Blocks & Checkout Moderno (Em Planeamento)
- [ ] Integração com o bloco de Checkout nativo do WooCommerce (Gutenberg Blocks).
- [ ] Pré-visualização do prompt USSD no checkout transparente.
