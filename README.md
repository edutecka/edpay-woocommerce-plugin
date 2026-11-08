# Plugin Oficial EDPay para WooCommerce (WordPress)

[![Tested with WooCommerce](https://img.shields.io/badge/WooCommerce-8.0%2B-blue.svg)](https://woocommerce.com/)
[![WordPress Version](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org/)
[![License: GPLv3](https://img.shields.io/badge/License-GPLv3-green.svg)](LICENSE)

Integre a plataforma **EDPay** na sua loja virtual WordPress / WooCommerce para receber pagamentos através de canais locais e internacionais em Moçambique:
- Carteiras Móveis: **M-Pesa (Vodacom)** e **e-Mola (Movitel)** com prompt USSD interativo em tempo real
- Cartões de Débito / Crédito: **Visa** e **Mastercard** com autenticação 3D Secure 2
- Carteiras Digitais: **Apple Pay** e **Google Pay**
- **Referências Bancárias**: Millennium BIM / SimoRede

---

## Requisitos

- WordPress 6.0 ou superior
- WooCommerce 8.0 ou superior
- PHP 8.1 ou superior (com extensões `curl`, `json`, `hash`)
- Moeda da loja: **Metical (MZN)** ou conversão automática ativa
- Conta ativa na EDPay ([https://edpay.co.mz/register](https://edpay.co.mz/register))

---

## Instalação

### Opção 1: Instalação via WP-CLI (Recomendada para Servidores)
```bash
wp plugin install https://github.com/edutecka/edpay-woocommerce-plugin/archive/refs/heads/main.zip --activate
```

### Opção 2: Instalação Manual via Painel WordPress
1. Descarregue o ficheiro `.zip` da release mais recente.
2. No painel de administração do WordPress, aceda a **Plugins** &gt; **Adicionar Novo** &gt; **Enviar Plugin**.
3. Selecione o ficheiro descarregado e clique em **Instalar Agora**.
4. Clique em **Activar Plugin**.

---

## Configuração

1. Aceda a **WooCommerce** &gt; **Configurações** &gt; separador **Pagamentos**.
2. Localize **EDPay Multi-Gateway Checkout** e clique em **Gerir**.
3. Preencha os campos obrigatórios:
   - **Ambiente**: Selecione `Sandbox (Testes)` ou `Produção (Live)`.
   - **Chave Pública**: `pk_live_...` (ou `pk_test_...`).
   - **Chave Privada**: `sk_live_...` (ou `sk_test_...`).
   - **Segredo do Webhook**: `whsec_...` gerado no console EDPay.
   - **Mensagem USSD e-Mola**: Mensagem opcional de até 100 caracteres enviada no prompt do telemóvel Movitel.
4. Clique em **Guardar alterações**.

---

## Webhook Endpoint

Configure o seguinte URL no seu Dashboard EDPay (**Desenvolvedores &gt; Webhooks**):
```
https://seusite.co.mz/?wc-api=edpay_webhook
```
Eventos recomendados a subscrever:
- `payment.succeeded`
- `payment.failed`
- `payment.canceled`

---

## Condições Comerciais & Liquidação

- Tarifa unificada plana de **6,00%** em Meticais (MZN) sem custos fixos de adesão.
- Liquidação bancária direta para a conta do comerciante no prazo de **2 a 7 dias úteis**.

---

## Licença

GPLv3 ou posterior.
