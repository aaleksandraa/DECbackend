# Chatbot Backend-First Setup (Facebook/Instagram)

Ovaj projekat sada podrzava direktan tok bez n8n posrednika:

- Meta webhook -> `POST /api/v1/chatbot/webhook`
- Backend obrada poruke (AI + booking logika)
- Backend salje odgovor preko Meta Graph API

## 1) Environment varijable

U `backend/.env` postavi:

- `CHATBOT_ENABLED=true`
- `OPENAI_API_KEY=...`
- `OPENAI_MODEL=...`
- `META_APP_ID=...`
- `META_APP_SECRET=...`
- `META_WEBHOOK_VERIFY_TOKEN=...`
- `META_GRAPH_VERSION=v18.0` (ili aktuelna verzija)
- `META_OAUTH_REDIRECT_URI=https://<tvoj-domain>/api/v1/admin/social-integrations/callback`
- `META_VERIFY_WEBHOOK_SIGNATURE=true`

`N8N_API_KEY` je ostavljen samo za legacy endpoint (`/api/v1/chatbot/message`).

## 2) Meta Webhook konfiguracija

U Meta app dashboard-u:

1. Webhook callback URL: `https://<tvoj-domain>/api/v1/chatbot/webhook`
2. Verify token: isto kao `META_WEBHOOK_VERIFY_TOKEN`
3. Pretplati app na potrebna polja za stranicu/IG poruke (`messages`, postbacks itd.)

Verifikacija ide kroz `GET /api/v1/chatbot/webhook`.

## 3) OAuth povezivanje salona

Salon owner iz dashboarda koristi:

- `GET /api/v1/admin/social-integrations/connect`
- Callback: `/api/v1/admin/social-integrations/callback`

Nakon povezivanja, integracija se oznacava kao `webhook_verified=false` dok ne stigne prvi validan signed webhook event.

## 4) Security napomena

Kad je `META_VERIFY_WEBHOOK_SIGNATURE=true`, backend zahtijeva `X-Hub-Signature-256` na webhook pozivu.
Zahtjevi bez potpisa se odbijaju sa `401`.

## 5) Legacy n8n endpoint

`POST /api/v1/chatbot/message` ostaje zbog backward compatibility, ali profesionalni preporuceni tok je direktni webhook kroz backend.

