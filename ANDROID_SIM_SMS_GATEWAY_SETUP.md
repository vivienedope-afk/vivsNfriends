# Android SIM SMS Gateway Setup (No Twilio, Detailed)

This is a full step-by-step guide to send SMS using an Android phone with regular SIM load.
No Twilio required.

## 1) Architecture (How It Works)

1. Your PHP app sends HTTP POST to an SMS gateway URL.
2. The Android gateway app receives the request.
3. The phone sends real SMS through your SIM.

Current app sender behavior:
- Mode: `SMS_PROVIDER_MODE=adapter`
- Sender file: `helpers/SmsService.php`
- Request body sent by app:

```json
{
  "to": "+639XXXXXXXXX",
  "message": "..."
}
```

## 2) Prerequisites

1. Android phone with active SIM.
2. Regular load or active unlimited SMS promo.
3. Android SMS gateway app that exposes HTTP endpoint.
4. Phone and PC on same network.
5. Phone app granted SMS permissions.

### If hotspot lang ang gamit mo

Yes, puwede hotspot lang.

1. Turn on mobile hotspot sa Android phone.
2. Connect your PC/laptop to that hotspot.
3. Start your SMS gateway app on the same phone.
4. Use the endpoint URL shown by the gateway app.
5. If no URL is shown, try phone hotspot gateway IP (usually `192.168.43.1`) + your app port/path.

Example only:

```env
SMS_DEFAULT_GATEWAY_URL=http://192.168.43.1:3000/message
SMS_BILLING_GATEWAY_URL=http://192.168.43.1:3000/message
```

Notes:
1. Some phones use different hotspot subnet/IP, so always prefer the URL shown by your gateway app.
2. Keep hotspot on and disable battery optimization for the gateway app.
3. If request times out, check app port/path and confirm app is listening on local network (not localhost-only).

## 3) Phone Setup (Step-by-Step)

## A. Install and configure gateway app
1. Install your preferred Android SMS gateway app.
2. Enable HTTP server/API inside the app.
3. Note the endpoint URL shown by app.
4. Note if app requires token/header key.

## B. Give permissions
1. Android Settings -> Apps -> Gateway App -> Permissions.
2. Allow `SMS`, `Phone`, and `Network` related permissions.
3. Disable battery optimization for gateway app.

## C. Keep gateway always online
1. Keep phone charging.
2. Disable aggressive battery saver.
3. Keep mobile signal stable.
4. Keep app running in foreground/service mode if required by app.

## D. Find phone LAN IP
1. Connect phone to same Wi-Fi as PC.
2. Open Wi-Fi details.
3. Copy phone IP (example: `192.168.1.55`).

## 4) .env Setup (Exact)

Open `.env` and set:

```env
SMS_PROVIDER_MODE=adapter

SMS_DEFAULT_GATEWAY_URL=http://192.168.1.55:3000/message
SMS_DEFAULT_GATEWAY_TOKEN=
SMS_DEFAULT_TIMEOUT=20

SMS_BILLING_GATEWAY_URL=http://192.168.1.55:3000/message
SMS_BILLING_GATEWAY_TOKEN=
SMS_BILLING_TIMEOUT=20
```

Important:
1. Do not use `127.0.0.1` if gateway is on phone.
2. Use phone LAN IP and correct port/path from app.
3. If gateway app requires token, set token values.
4. If no token needed, leave token blank.

## 5) Verify Gateway Reachability Before App Test

From your PC, test endpoint first.

PowerShell test:

```powershell
$body = @{ to = '+639XXXXXXXXX'; message = 'Gateway connectivity test' } | ConvertTo-Json
Invoke-RestMethod -Uri 'http://192.168.1.55:3000/message' -Method Post -ContentType 'application/json' -Body $body
```

If app requires token:

```powershell
$headers = @{ Authorization = 'Bearer your_token_here' }
$body = @{ to = '+639XXXXXXXXX'; message = 'Gateway connectivity test' } | ConvertTo-Json
Invoke-RestMethod -Uri 'http://192.168.1.55:3000/message' -Method Post -Headers $headers -ContentType 'application/json' -Body $body
```

Expected:
1. HTTP 2xx response from gateway.
2. SMS appears on destination phone.

## 6) App SMS Flows Already Wired

1. New due posted
- File: `admin/payments_action.php`
- Function: `sendNewDuePostedSms()`

2. Payment received
- File: `admin/payments_action.php`
- Function: `sendDuePaymentSuccessSms()`

3. Booking status updates
- File: `admin/bookings_action.php`
- Function: `notifyResidentBookingStatus()`

4. Application status updates
- File: `admin/process_application.php`
- Function: `notifyApplicantApplicationStatus()`

## 7) Live App Test Procedure

## Test A: New Due Posted SMS
1. Login admin.
2. Go to Payments & Dues.
3. Add new due for household linked to valid resident contact number.
4. Check destination phone for SMS.

## Test B: Payment Received SMS
1. Click `Record Payment` on the same due.
2. Submit payment form.
3. Check destination phone for payment confirmation SMS.

## 8) Verify Database Logs

Run:

```sql
SELECT log_id, user_id, subject, status, error_message, created_at
FROM notification_log
WHERE notification_type='sms'
ORDER BY log_id DESC
LIMIT 30;
```

Interpretation:
1. `status = sent` means app-side send succeeded.
2. `status = failed` means gateway rejected/unreachable.
3. Check `error_message` for root cause.

## 9) Common Issues and Fixes

## Issue: `SMS gateway config missing`
Cause:
1. URL empty in `.env`.

Fix:
1. Fill `SMS_DEFAULT_GATEWAY_URL` and `SMS_BILLING_GATEWAY_URL`.

## Issue: Timeout / connection refused
Cause:
1. Wrong phone IP/port/path.
2. Phone and PC not on same network.
3. Gateway app not running.

Fix:
1. Recheck app endpoint.
2. Recheck phone IP.
3. Keep gateway app active.

## Issue: Gateway returns success but SMS not delivered
Cause:
1. No SIM load/promo.
2. Signal weak.
3. SMS permission revoked on Android.

Fix:
1. Add load or activate SMS promo.
2. Improve signal.
3. Re-enable SMS permission.

## Issue: No recipient found for due/payment SMS
Cause:
1. `monthly_dues.household_id` not linked to resident/contact.

Fix:
1. Ensure household has linked resident and valid `contact_number`.

## 10) Operations Checklist

Daily:
1. Phone battery > 50% or charging.
2. Gateway app running.
3. SIM has load/promo.
4. Network connected.

Weekly:
1. Send one test SMS from app.
2. Review failed SMS logs in `notification_log`.
3. Verify resident contact numbers format.

Before go-live:
1. End-to-end test all 4 SMS flows.
2. Confirm message delivery on actual recipient devices.
3. Document gateway endpoint and fallback phone.

