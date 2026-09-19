from pathlib import Path

vault = Path(r'D:\JSTACK CLIENTES\PROYECTO TRIBIO COMIENZO\tribio_brain')
note = '''---
type: architecture
domain: backend
tags: [architecture, payments, flow, checkout, multi-tenant]
---

# Flow Checkout Flow

Flow is an independent, per-store gateway alongside [[Mercado-Pago-Checkout-Flow]] and [[PayPal-Checkout-Flow]]. It uses the shared checkout drawer, including Maetek's minimal-light template and future templates using that drawer. [[Culqi-Subscription-Billing]] remains separate SaaS billing.

## Contract and source

Source: `tribio_final/es-openApiFlow.yaml`, `/payment/create`, `/payment/getStatus`, `PayResponse`, `PaymentStatus`. Official references: https://developers.flow.cl/api and https://web.flow.cl/es-pe/ayuda/ (reviewed 2026-09-19).

Parameters are sorted alphabetically, concatenated as key + value without separators, then signed with HMAC-SHA256. `s` contains the signature. POST creation uses form encoding; status uses GET. Sandbox: https://sandbox.flow.cl/api. Production: https://www.flow.cl/api. `paymentMethod=9` delegates selection to the methods contracted by the merchant. Yape requires activation with Flow; Tribio does not promise that every merchant has Yape.

## Store configuration and shared UI

[[Store-Model]] adds `flow_enabled` (default false), `flow_mode` (sandbox by default), `flow_currency` (PEN by default), `flow_api_key` and `flow_secret_key`. Keys use Laravel encrypted casts and are hidden from model JSON. The dashboard never renders stored keys; blank fields preserve them. Disable via the checkbox. When switching environment, replace both keys. Back up APP_KEY: encrypted credentials depend on it.

[[Store-Settings-Web-UI]] embeds `components/checkout/flow-settings.blade.php`; checkout embeds `components/checkout/flow-option.blade.php`. Styling lives in `resources/css/flow.css`, imported by app.css, and inherits --pay-* tokens. Selection has keyboard focus, conditional sandbox notice, currency availability, loading and inline error states. The action is "Continuar a Flow". Flow-only stores select Flow by default. No gateway-specific code is needed in new storefront templates beyond the existing shared drawer.

Only card/mixed checkout modes offer Flow. The order currency must exactly match the merchant's configured Flow currency (PEN/USD/CLP/MXN choices, subject to the merchant contract). No silent conversion is performed. A mismatched currency is shown as unavailable and rejected server-side before stock changes.

## Lifecycle and verification

1. Shared checkout posts `payment_method: flow` with the existing cart/customer payload. [[Order-Model]] snapshots server-calculated prices, promotions and shipping (see [[Shipping-Promotions]]).
2. Flow checkout runs in a database transaction. Failed initialization/validation rolls back order, items, stock and counters. Order emails are queued after commit so rolled-back orders do not generate notifications (see [[Order-Email-Notifications]]).
3. `App\\Services\\FlowService::createPayment` creates the remote order, storing `flow_order_id` and unique, hidden `flow_token`; returns `payment_url` from the provider's HTTPS allowlisted URL plus encoded token. The order remains pending. Creation is not retried automatically because a timeout may hide a remote creation.
4. Flow calls confirmation with a token. `FlowService::reconcile` first locates the order by store, method and token, then queries Flow using that store's credentials. It verifies commerceOrder, flowOrder, currency, amount (minor units), and status.
5. Status 1 remains pending, 2 marks paid, 3/4 marks failed. A row lock serializes updates; repeated or late notifications cannot downgrade paid/refunded orders. Fulfillment status advances from pending to confirmed only, preserving later fulfillment states.
6. Browser return uses the same server verification. `payments/flow-result.blade.php` shows paid, pending, failed or temporary unavailability and offers a manual status refresh for pending orders. Browser input never marks an order paid. Responses have no-store and no-referrer headers. No customer address/email is exposed on the result page.

Callbacks use the canonical APP_URL host, not the custom storefront host: [[Multi-Tenancy]] redirects /api on custom domains, which could lose POST bodies. APP_URL must be public HTTPS in deployment. The return page links to the correct store URL.

## Endpoints

| Method / URI | Input / headers | Responses |
| --- | --- | --- |
| POST /tienda/{slug}/checkout (or custom-domain /checkout) | Existing checkout JSON + payment_method=flow; Content-Type application/json, Accept application/json; storefront CSRF token | 200 success=true, order_number, redirect_url, payment_url, whatsapp_url=null; 422 unavailable/currency/validation; 502 initialization failure, success=false and user-safe error |
| POST /api/flow/{store}/confirmation | Public provider callback; form token required string max 255; Content-Type application/x-www-form-urlencoded, Accept application/json | 200 received=true; 404 unknown/store-mismatched token; 422 validation; 503 verification/provider failure, received=false, allowing retry |
| GET or POST /api/flow/{store}/return | Public browser return, token string max 255 (POST form preferred) | 200 HTML reflecting verified persisted state, including temporary provider failure; 404 unknown token; 422 JSON validation when Accept application/json |

New endpoints serve provider/storefront traffic. The mobile [[Store-Settings-API]] write contract is unchanged. Store JSON adds non-secret Flow flags/mode/currency; keys are excluded. [[Orders-API]] may include flow_order_id but never flow_token.

## Files and rollout

- Migration `2026_09_19_180000_add_flow_payment_fields.php`.
- `App\\Services\\FlowService`, `App\\Http\\Controllers\\FlowPaymentController`, `App\\Http\\Requests\\FlowCallbackRequest`.
- Shared drawer, Flow option/settings/result views and CSS.
- `tests/Feature/FlowPaymentTest.php` covers signed creation, tenant isolation, amount/currency/reference tampering, callback idempotence, return states, checkout routing, rollback, unavailable settings and shared UI.

Deploy migration and frontend build, configure the store's sandbox credentials, verify public HTTPS callbacks, complete a real sandbox payment before selecting production. Automated tests use fake Flow HTTP responses, not real charges. Remote production migration and merchant activation were not performed during implementation. Existing inventory behavior still reserves stock for successfully initialized pending/failed payments; reservation expiry/release and refund workflows are not added by this integration.

## Related
[[Store-Model]] · [[Order-Model]] · [[Orders-API]] · [[Store-Settings-Web-UI]] · [[Multi-Tenancy]] · [[Mercado-Pago-Checkout-Flow]] · [[PayPal-Checkout-Flow]]
'''
(vault / '01-Backend/Architecture/Flow-Checkout-Flow.md').write_text(note, encoding='utf-8')
index = vault / '00-Index.md'
text = index.read_text(encoding='utf-8')
if '[[Flow-Checkout-Flow]]' not in text:
    text = text.replace('- [[PayPal-Checkout-Flow]]', '- [[Flow-Checkout-Flow]] — independent per-store Flow checkout, hosted methods including merchant-enabled Yape, verified callbacks\n- [[PayPal-Checkout-Flow]]')
    index.write_text(text, encoding='utf-8')
for relative, summary in {
    '01-Backend/Models/Store-Model.md': 'Flow fields: flow_enabled, encrypted/JSON-hidden flow_api_key and flow_secret_key, flow_mode (sandbox/live), flow_currency. Blank dashboard key fields preserve credentials.',
    '01-Backend/Models/Order-Model.md': 'Flow references: indexed flow_order_id and unique JSON-hidden flow_token. Payment updates are tenant scoped, verified with the provider and serialized by row lock.',
    '01-Backend/Architecture/Store-Settings-Web-UI.md': 'Independent Flow section supports activation, sandbox/live mode, merchant currency and encrypted credentials without displaying stored keys.',
    '01-Backend/Architecture/Order-Email-Notifications.md': 'Order emails now queue after database commit; Flow initialization rollback does not send notifications for discarded orders.',
    '03-API-Contracts/Store-Settings-API.md': 'Flow adds read-only store configuration metadata (flow_enabled, flow_mode, flow_currency) to serialized stores. Credential keys are hidden; mobile update validation is unchanged.',
    '03-API-Contracts/Orders-API.md': 'Serialized orders may include flow_order_id. flow_token is hidden. Provider confirmation/return contracts are documented in the Flow note.',
}.items():
    p = vault / relative
    if '## Flow integration (2026-09-19)' not in p.read_text(encoding='utf-8'):
        with p.open('a', encoding='utf-8') as f:
            f.write('\n\n## Flow integration (2026-09-19)\n\n' + summary + ' See [[Flow-Checkout-Flow]].\n')
with (vault / 'log.md').open('a', encoding='utf-8') as f:
    f.write('\n\n## [2026-09-19] integration | Add Flow as a shared per-store payment gateway\n\nImplemented [[Flow-Checkout-Flow]] from es-openApiFlow.yaml: encrypted merchant keys, independent dashboard setup, shared checkout option and styles, signed hosted payment creation, canonical-host callbacks, server-verified amount/currency/order identity, idempotent payment updates, pending/paid/failed return screens, transactional checkout rollback and after-commit order emails. Updated [[Store-Model]], [[Order-Model]], [[Store-Settings-Web-UI]], [[Store-Settings-API]], [[Orders-API]], [[Order-Email-Notifications]] and index. Automated Flow tests and dashboard regression tests passed; frontend build and Blade compilation passed. Real merchant sandbox charge and remote deployment migration remain required. Yape availability depends on activation with Flow.\n')
print('Flow note, index, six related notes and append-only log updated.')
