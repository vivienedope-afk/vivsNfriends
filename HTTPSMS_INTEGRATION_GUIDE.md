# httpSMS Integration Guide (PHP + Android)

This guide is tailored for your current project setup.
It uses httpSMS cloud API and your Android phone device.

## 1) What You Need

1. Android phone with SIM and signal.
2. httpSMS app installed on phone.
3. httpSMS account paired/login.
4. SMS permission enabled in phone app.
5. Device marked online in app/dashboard.

## 2) Install and Pair

1. Install latest httpSMS app:
- https://github.com/NdoleStudio/httpsms/releases/latest

2. Open app and login/pair device.
3. Allow SMS permission prompt.
4. Confirm device shows `online`.

## 3) Get Required Values

From httpSMS dashboard/app, collect:
1. API Key
2. Device ID
3. API URL (fixed):
- `https://api.httpsms.com/v1/messages/send`

## 4) Update .env (Exact Keys)

Open `.env` and fill:

```env
SMS_PROVIDER_MODE=adapter

SMS_DEFAULT_PROVIDER=httpsms
SMS_DEFAULT_GATEWAY_URL=https://api.httpsms.com/v1/messages/send
SMS_DEFAULT_GATEWAY_TOKEN=YOUR_HTTPSMS_API_KEY
SMS_DEFAULT_DEVICE_ID=YOUR_HTTPSMS_DEVICE_ID
SMS_DEFAULT_TIMEOUT=20

SMS_BILLING_PROVIDER=httpsms
SMS_BILLING_GATEWAY_URL=https://api.httpsms.com/v1/messages/send
SMS_BILLING_GATEWAY_TOKEN=YOUR_HTTPSMS_API_KEY
SMS_BILLING_DEVICE_ID=YOUR_HTTPSMS_DEVICE_ID
SMS_BILLING_TIMEOUT=20
```

Optional global fallback:

```env
SMS_PROVIDER=httpsms
SMS_DEVICE_ID=YOUR_HTTPSMS_DEVICE_ID
```

## 5) Payload Mapping (Already Handled in Code)

Your app now sends this format automatically for `httpsms` provider:

```json
{
  "content": "Hello from PHP",
  "phoneNumber": "+639XXXXXXXXX",
  "deviceId": "YOUR_DEVICE_ID"
}
```

Headers sent:
- `Content-Type: application/json`
- `x-api-key: YOUR_API_KEY`

No extra code needed in controller files.

## 6) Flows Already Wired In Your App

1. New due posted SMS
- Trigger: admin adds due in Payments

2. Payment received SMS
- Trigger: admin records payment

3. Booking status SMS
- Trigger: approve/reject/cancel booking

4. Application status SMS
- Trigger: approve/reject application

## 7) End-to-End Test Steps

1. Fill `.env` with real API key + device ID.
2. Ensure Android device is online in httpSMS.
3. Add monthly due in admin Payments.
4. Confirm resident gets `New Due Posted` SMS.
5. Record payment for same due.
6. Confirm resident gets `Payment Received` SMS.

## 8) Verify Logs in Database

```sql
SELECT log_id, user_id, subject, status, error_message, created_at
FROM notification_log
WHERE notification_type = 'sms'
ORDER BY log_id DESC
LIMIT 30;
```

Expected:
- `status = sent` for successful notifications.

If failed:
- check `error_message`
- check API key/device ID and device online status.

## 9) Common Errors

## Error: `httpSMS API key missing`
Fix:
- Set `SMS_DEFAULT_GATEWAY_TOKEN` and `SMS_BILLING_GATEWAY_TOKEN`.

## Error: `httpSMS device ID missing`
Fix:
- Set `SMS_DEFAULT_DEVICE_ID` and `SMS_BILLING_DEVICE_ID`.

## Error: `SMS failed` from provider
Fix:
1. Verify API key is valid.
2. Verify device ID is correct.
3. Verify Android app is online and has SMS permission.
4. Verify SIM has load/promo and signal.

## 10) Security Notes

1. Keep API key in `.env` only.
2. Do not commit `.env` to public repository.
3. Rotate API key if exposed.
