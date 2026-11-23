# EduCore SMS — Email, SMS/WhatsApp & Pakistani Payment Gateway Setup (R6)

## 1. Email Deliverability (SPF / DKIM / DMARC)
- Set `MAIL_MAILER=smtp` (or SES/Postmark/Resend) in production, `MAIL_FROM_ADDRESS=noreply@yourdomain.pk`.
- Publish DNS SPF (`v=spf1 include:... ~all`), DKIM selector keys, and DMARC (`v=DMARC1; p=quarantine;`).

## 2. SMS & WhatsApp Adapters (`MessagingGatewayService`)
- Default sandbox driver logs messages and persists delivery receipts + cost estimates in `notification_deliveries` (with RLS).
- Respects quiet hours (`21:00–07:00` local time) for fee dunning/reminders (`skipped_quiet_hours`).
- Plug in vendor HTTP credentials (Jazz/Zong/Telenor/WhatsApp Cloud API) via env when owner selects vendor (Decision D5).

## 3. Payment Webhooks (`POST /webhooks/payments/{provider}`)
- Supported providers: `1bill`, `jazzcash`, `easypaisa`.
- Requires `X-Gateway-Signature` HMAC-SHA256 header signed with `PAYMENT_WEBHOOK_SECRET`.
- Strictly idempotent on `(provider, provider_reference)` in `payment_gateway_transactions` — duplicate/replay webhooks return `200 already_processed` without double-posting GL.
