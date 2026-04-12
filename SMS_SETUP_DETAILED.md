# Maia Alta HOA SMS Setup (Detailed)

This guide explains:
- what SMS notifications are currently used in this project,
- which gateway setup to use,
- and how to make SMS sending work end-to-end in your local XAMPP setup.

## 1) SMS Provider You Should Use

Your current code is built for an HTTP SMS gateway endpoint, not for Twilio SDK.

Implementation details:
- Sender class: `helpers/SmsService.php`
- Request format: JSON `{"to":"+63...","message":"..."}`
- Header required: `Authorization: <token>`
- Gateway config source: `.env`

Recommended provider pattern:
- Use any SMS gateway app/service that exposes a POST API endpoint with token-based auth.
- Your existing comments mention Traccar SMS Gateway style usage, so that is compatible with current code.

### So ano ang gagamitin mo ngayon?

Short answer:
- If you want fastest setup with current code: use an HTTP SMS gateway compatible with your current payload/header pattern.
- If you want enterprise-grade reliability: Twilio is a good choice, but usually needs either:
  - an adapter endpoint that converts your current request format to Twilio API format, or
  - direct Twilio integration changes in `SmsService.php`.

### Provider Options (Twilio vs Others)

#### Option A: Twilio
Best for:
- high reliability, delivery tools, global support, good dashboards.

Pros:
- very stable infrastructure
- strong logs and monitoring
- scalable for production

Cons for your current project:
- your current `SmsService` does not call Twilio API directly
- may require integration rewrite or middleware adapter
- typically higher cost than local PH-focused options

Use this when:
- you plan to scale beyond local HOA volume
- you can spend time on proper provider integration and testing

#### Option B: Philippine SMS provider with HTTP API (example: Semaphore)
Best for:
- PH-local delivery and simpler HTTP-based setup.

Pros:
- often easier to connect with your current HTTP pattern
- usually lower cost for PH traffic

Cons:
- provider-specific request formats vary

Use this when:
- target users are mostly PH mobile numbers
- you want simpler and cheaper rollout

#### Option C: Vonage / Infobip / AWS SNS
Best for:
- production cloud messaging with API ecosystem.

Pros:
- mature APIs, enterprise options

Cons:
- integration format mismatch may still require code changes
- pricing and setup complexity vary

Use this when:
- you need enterprise support contracts or multi-country routing

#### Option D: Local Android SMS Gateway app (HTTP endpoint)
Best for:
- very quick proof-of-concept and low-volume internal use.

Pros:
- works with your existing architecture
- minimal code change

Cons:
- depends on one device/network
- not ideal for high reliability production

Use this when:
- you are still in pilot/testing stage

### Recommended Path For Your Current Codebase

1. Immediate (no major rewrite):
- Use an HTTP gateway compatible with your existing `SmsService`.
- Configure `SMS_DEFAULT_*` and `SMS_BILLING_*` in `.env`.

2. Next step (production hardening):
- Choose Twilio or another managed provider.
- Add a provider adapter layer so your app still calls one internal `sendSms()` format.

3. Long-term:
- Add delivery status callbacks and retry queue.

## 2) All SMS Notifications Used By The System

### A. Booking Status SMS (Resident)
When admin approves/rejects/cancels a booking.

Triggered from:
- `admin/bookings_action.php`
- Calls: `notifyResidentBookingStatus()` in `config/NotificationHelper.php`

Message example:
- `MAIA ALTA HOA: Your booking for Basketball Court on April 15, 2026 has been APPROVED.`

Gateway type used:
- `general` (uses `SMS_DEFAULT_*` env keys)

### B. Application Status SMS (Applicant)
When application is approved/rejected.

Triggered from:
- `admin/process_application.php`
- Calls: `notifyApplicantApplicationStatus()` in `config/NotificationHelper.php`

Gateway type used:
- `general` (uses `SMS_DEFAULT_*` env keys)

### C. Monthly Due Reminder SMS
For due-date, 3-day reminder, and overdue notice.

Triggered from:
- `config/send_due_reminders.php`
- Calls: `sendDueReminder()` in `config/NotificationHelper.php`

Gateway type used:
- currently `general` in helper call (`sendSms(...)` without billing type)
- if you want this to use billing gateway, call `sendSms(..., 'billing')`

### D. Payment Success SMS (Newly Enabled)
When admin records a successful payment in Payments & Dues.

Triggered from:
- `admin/payments_action.php` (action: `record_payment`)
- Calls: `sendDuePaymentSuccessSms()` in `config/NotificationHelper.php`

Gateway type used:
- `billing` (uses `SMS_BILLING_*` env keys)

Message example:
- `MAIA ALTA HOA: Payment received. PHP 704.00 for April 2026 on Apr 12, 2026 via CASH. Thank you.`

## 3) Required .env Keys

Open your `.env` and set these values.

### Default SMS gateway (used by booking/application/most SMS)
```env
SMS_DEFAULT_GATEWAY_URL=https://your-sms-gateway.example/send
SMS_DEFAULT_GATEWAY_TOKEN=Bearer your_token_here
SMS_DEFAULT_TIMEOUT=20
```

### Billing SMS gateway (used by payment success SMS)
```env
SMS_BILLING_GATEWAY_URL=https://your-sms-gateway.example/send
SMS_BILLING_GATEWAY_TOKEN=Bearer your_token_here
SMS_BILLING_TIMEOUT=20
```

Notes:
- If your gateway expects raw token only, remove `Bearer ` prefix.
- `SmsService` sends `Authorization: <value>`, exactly as placed in `.env`.

## 4) Phone Number Format Rules

`SmsService` validates and normalizes numbers:
- `09XXXXXXXXX` becomes `+639XXXXXXXXX`
- E.164-like numbers are accepted (`+639...`)
- invalid phone format returns `Invalid phone number`

Best practice:
- Store resident/applicant mobile as `09XXXXXXXXX` or `+639XXXXXXXXX`.

## 5) Data Conditions Required Before SMS Can Send

SMS can still fail even with correct gateway if recipient lookup fails.

For resident-targeted notifications, ensure:
- resident has a valid `contact_number`
- resident record exists and is linked to household correctly
- for preference-aware flows, `notification_preferences.sms_notifications = 1`

Important for dues/payment SMS:
- `monthly_dues.household_id` must map to a resident via one of:
  - `household_members` (preferred)
  - `households.owner_id` fallback
- if household has no member and no owner, SMS cannot resolve recipient

## 6) Turn On SMS Preference For Test Users

Some flows check `sms_notifications` preference.

```sql
-- Enable SMS for one user
UPDATE notification_preferences
SET sms_notifications = 1
WHERE user_id = 123;

-- If row does not exist yet, create it
INSERT INTO notification_preferences (user_id, email_notifications, sms_notifications)
VALUES (123, 1, 1)
ON DUPLICATE KEY UPDATE sms_notifications = VALUES(sms_notifications);
```

## 7) Quick End-to-End Testing Steps

### Test 1: Booking status SMS
1. Create booking as resident.
2. Admin approves/rejects in Bookings page.
3. Check recipient phone and `notification_log` for `notification_type='sms'`.

### Test 2: Application status SMS
1. Submit application.
2. Approve/reject in Applications page.
3. Check applicant phone and log table.

### Test 3: Due reminder SMS
1. Ensure due record exists and is unpaid/overdue.
2. Run due reminder process (`config/send_due_reminders.php` if used by cron/manual).
3. Check log table and phone.

### Test 4: Payment success SMS
1. Go to Payments & Dues.
2. Record payment (`record_payment`).
3. SMS should send automatically after successful save.
4. Verify in `notification_log` and gateway/device.

## 8) SQL Checks You Can Run For Debugging

### A. Last SMS attempts
```sql
SELECT log_id, user_id, subject, status, error_message, created_at
FROM notification_log
WHERE notification_type = 'sms'
ORDER BY created_at DESC
LIMIT 30;
```

### B. Check dues to resident linkage
```sql
SELECT md.dues_id, md.household_id, h.owner_id,
       hm.user_id AS member_user_id,
       u.user_id AS owner_user_id,
       u.contact_number
FROM monthly_dues md
LEFT JOIN households h ON h.household_id = md.household_id
LEFT JOIN household_members hm ON hm.household_id = md.household_id
LEFT JOIN users u ON u.user_id = h.owner_id
ORDER BY md.dues_id DESC
LIMIT 20;
```

### C. Residents missing contact numbers
```sql
SELECT user_id, first_name, last_name, contact_number
FROM users
WHERE user_role = 'resident' AND (contact_number IS NULL OR contact_number = '');
```

## 9) Common Issues And Fixes

### Issue: `SMS gateway config missing`
Cause:
- Missing `SMS_DEFAULT_GATEWAY_URL/TOKEN` or `SMS_BILLING_GATEWAY_URL/TOKEN`.

Fix:
- Fill required env keys and restart Apache/PHP process if needed.

### Issue: `Invalid phone number`
Cause:
- Contact number is not `09XXXXXXXXX` or `+63...`.

Fix:
- Normalize numbers in DB.

### Issue: SMS not sent on dues payment even if action is successful
Cause:
- Dues household has no resident linkage (`household_members` empty and `owner_id` null), or no contact number.

Fix:
- Link household to resident and set valid contact number.

### Issue: SMS not sent for booking/app reminders
Cause:
- User preference `sms_notifications` is OFF in `notification_preferences`.

Fix:
- Turn SMS preference ON for that user.

## 10) Production Recommendations

- Use separate tokens/endpoints for `SMS_DEFAULT_*` and `SMS_BILLING_*`.
- Add gateway retry/backoff for transient failures.
- Add delivery receipt webhook support if your provider supports callbacks.
- Add admin dashboard widget for SMS health (last success/failure counts).

---
If you want, I can also create a second file with a copy-paste checklist format for your team (Setup Checklist + Go-Live Checklist + Daily Ops Checklist).
