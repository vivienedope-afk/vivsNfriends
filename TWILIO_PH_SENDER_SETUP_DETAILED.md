# Twilio PH Sender Setup (Detailed)

This guide is for your current error:
- Twilio error `21612`
- "Message cannot be sent with current To/From combination"

Your app is already reaching Twilio API.
The remaining issue is sender-route setup in Twilio.

## 1) Goal

Make SMS from your app work to Philippine numbers (`+63...`) using Twilio.

## 2) Current App Requirements

Your app currently uses direct Twilio mode in `.env`:

```env
SMS_PROVIDER_MODE=direct
TWILIO_ACCOUNT_SID=...
TWILIO_AUTH_TOKEN=...
TWILIO_FROM_NUMBER=+16624904652
```

Code path:
- sender: `helpers/SmsService.php`
- triggers:
  - due posted: `admin/payments_action.php` -> `sendNewDuePostedSms()`
  - payment success: `admin/payments_action.php` -> `sendDuePaymentSuccessSms()`

## 3) Twilio Console Setup (Exact)

## A. Confirm account mode
1. Open Twilio Console.
2. Check if account is `Trial`.
3. If trial:
- recipient number must be verified
- some destination/sender combinations still fail

## B. Verify destination number (Trial accounts)
1. Go to Twilio Console -> Phone Numbers -> Verified Caller IDs.
2. Add target PH number in E.164 format: `+639XXXXXXXXX`.
3. Complete OTP verification.

Important:
- The number in Twilio must match your DB `users.contact_number` after normalization.

## C. Enable PH destination
1. Go to Messaging -> Settings -> Geo Permissions.
2. Enable `Philippines (+63)`.
3. Save changes.

## D. Create Messaging Service (recommended)
1. Go to Messaging -> Services -> Create Messaging Service.
2. Name: `MaiaAltaSMS`.
3. Use case: `Notifications`.
4. Add sender(s):
- Add Twilio number(s) that can send SMS
- Prefer sender known to support PH routes

## E. Sender Pool / Smart Encoding settings
Inside Messaging Service settings:
1. Enable smart encoding (optional).
2. Check sender pool contains at least one SMS-capable sender.
3. Ensure sender is not restricted for PH destination.

## F. Upgrade account if needed
If still trial and failing:
1. Upgrade Twilio account.
2. Re-check number capability and destination permissions.
3. Retry test.

## 4) Validate Sender Capability

For your current sender `+16624904652`, verify:
1. It is SMS-capable (not voice-only).
2. It supports sending to Philippines from your account.
3. It is not blocked by trial route restrictions.

If `21612` persists, that sender is not valid for PH route in your account context.
Use another sender or Messaging Service route.

## 5) App-Side Config Update Options

## Option 1 (current implementation): direct from fixed number
Use this if your Twilio number is confirmed PH-capable.

`.env`:
```env
SMS_PROVIDER_MODE=direct
TWILIO_ACCOUNT_SID=AC...
TWILIO_AUTH_TOKEN=...
TWILIO_FROM_NUMBER=+1XXXXXXXXXX
SMS_SSL_VERIFY=0
```

Note:
- Set `SMS_SSL_VERIFY=1` in production once CA certs are configured.

## Option 2 (recommended for stable routing): use Messaging Service SID
If you want sender pooling/routing from Twilio Messaging Service,
update code to send with `MessagingServiceSid` instead of `From`.

You can prepare env now:
```env
TWILIO_MESSAGING_SERVICE_SID=MGXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX
```

Then update sender code to use:
- `MessagingServiceSid=<SID>`
- remove fixed `From` for that request

If you want, I can patch this for you in one step.

## 6) Database Checks Before Testing

Ensure target resident has correct number:

```sql
SELECT user_id, first_name, last_name, contact_number
FROM users
WHERE user_role='resident' AND status='active';
```

Expected format in app:
- `09XXXXXXXXX` or `+639XXXXXXXXX`

## 7) End-to-End Test Sequence (Your App)

1. Add new dues in Payments page for linked household.
- should trigger: `New Due Posted` SMS

2. Click Record Payment for same due.
- should trigger: `Payment Received` SMS

3. Check logs:

```sql
SELECT log_id, user_id, subject, status, error_message, created_at
FROM notification_log
WHERE notification_type='sms'
ORDER BY log_id DESC
LIMIT 20;
```

Success indicators:
- `status = sent`
- `error_message = NULL`

## 8) Error-to-Fix Map

### `21608` unverified number
Fix:
- verify destination number in Twilio (trial)

### `21612` invalid To/From combination
Fix:
- use PH-compatible sender
- use Messaging Service route
- upgrade account if trial restrictions apply

### SSL certificate error in local XAMPP
Fix:
- temporary local: `SMS_SSL_VERIFY=0`
- production: install CA bundle and set `SMS_SSL_VERIFY=1`

## 9) Production Checklist

1. Twilio account upgraded
2. PH geo permission enabled
3. Sender route validated to PH
4. Messaging Service configured
5. `SMS_SSL_VERIFY=1`
6. credentials rotated and kept in server-only `.env`

---
If you want, next I can patch your app to support `TWILIO_MESSAGING_SERVICE_SID` so you can stop relying on one fixed `TWILIO_FROM_NUMBER`.
