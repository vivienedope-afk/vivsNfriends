# Twilio SMS Setup Guide For Maia Alta HOA

This document is a Twilio-only guide for your project.

It includes:
- Twilio account setup
- required credentials
- how to connect Twilio to your current SMS code
- testing and troubleshooting

## 1. Important Context About Your Current Code

Your current SMS sender is in helpers/SmsService.php and sends:
- POST JSON body: to, message
- Authorization header: Authorization: <token>

Twilio native API format is different.

Because of that, you have 2 integration choices:

1. Recommended quick path: keep your existing app code and use a Twilio adapter endpoint.
2. Full direct path: rewrite SmsService.php to call Twilio REST API directly.

## 2. Twilio Account Setup

1. Create account at Twilio.
2. Verify your own phone number in Twilio console for trial mode.
3. Buy or activate a Twilio phone number with SMS capability.
4. Copy these credentials from console:
- Account SID
- Auth Token
- Twilio From Number

## 3. Credentials You Need To Store

Twilio core values:
- TWILIO_ACCOUNT_SID
- TWILIO_AUTH_TOKEN
- TWILIO_FROM_NUMBER

For your app using current env pattern, add these too:
- SMS_DEFAULT_GATEWAY_URL
- SMS_DEFAULT_GATEWAY_TOKEN
- SMS_BILLING_GATEWAY_URL
- SMS_BILLING_GATEWAY_TOKEN

Copy-ready block (matches your current project env keys):

```env
SMS_PROVIDER_MODE=adapter

TWILIO_ACCOUNT_SID=
TWILIO_AUTH_TOKEN=
TWILIO_FROM_NUMBER=
TWILIO_ADAPTER_TOKEN=

SMS_DEFAULT_GATEWAY_URL=
SMS_DEFAULT_GATEWAY_TOKEN=
SMS_DEFAULT_TIMEOUT=20

SMS_BILLING_GATEWAY_URL=
SMS_BILLING_GATEWAY_TOKEN=
SMS_BILLING_TIMEOUT=20
```

## 4. Recommended Integration For Your Existing App

### Option A: Twilio Adapter Endpoint (No Major App Rewrite)

Create a small adapter service endpoint that receives your app payload:
- body fields: to, message
- header: Authorization

Then adapter sends to Twilio API.

Your app keeps calling SmsService exactly as-is.

Set in .env:

```env
SMS_DEFAULT_GATEWAY_URL=https://your-adapter-domain/send-sms
SMS_DEFAULT_GATEWAY_TOKEN=Bearer your_internal_adapter_token
SMS_DEFAULT_TIMEOUT=20

SMS_BILLING_GATEWAY_URL=https://your-adapter-domain/send-sms
SMS_BILLING_GATEWAY_TOKEN=Bearer your_internal_adapter_token
SMS_BILLING_TIMEOUT=20
```

Why this is best now:
- no major changes across notification flows
- easier rollback
- you can swap providers later without touching app logic

## 5. Direct Twilio Integration Option

If you prefer direct Twilio integration inside app:

1. Install Twilio PHP SDK with Composer.
2. Update helpers/SmsService.php to call Twilio API.
3. Keep your current methods and return format so other files continue working.

Suggested direct env keys:

```env
TWILIO_ACCOUNT_SID=ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TWILIO_AUTH_TOKEN=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TWILIO_FROM_NUMBER=+1xxxxxxxxxx
```

If you do direct integration, you can either:
- keep current SMS_DEFAULT and SMS_BILLING keys as feature toggles, or
- replace them with Twilio keys only.

## 6. Twilio Trial Limitations You Should Expect

In trial mode:
- you can send only to verified destination numbers
- message may include Twilio trial prefix

If messages fail in trial, check if recipient is verified in Twilio console.

## 7. End-To-End Testing Steps

### Test A: Booking Status SMS
1. Create a booking as resident.
2. Approve or reject booking in admin Bookings.
3. Confirm SMS received.
4. Check notification_log table for sms status.

### Test B: Application Status SMS
1. Submit application.
2. Approve or reject application.
3. Confirm SMS to applicant.

### Test C: Dues Reminder SMS
1. Ensure dues record is due or overdue.
2. Trigger reminder process.
3. Confirm SMS received.

### Test D: Payment Success SMS
1. Record payment in Payments and Dues.
2. Confirm SMS payment receipt message.

## 8. SQL Query For SMS Debug

```sql
SELECT log_id, user_id, subject, status, error_message, created_at
FROM notification_log
WHERE notification_type = 'sms'
ORDER BY created_at DESC
LIMIT 50;
```

## 9. Common Twilio Errors And Fixes

### Error: Authentication failed
Cause:
- wrong SID or auth token
Fix:
- re-copy values from Twilio console

### Error: Unverified destination in trial
Cause:
- number not verified
Fix:
- verify destination number in Twilio trial settings

### Error: Invalid From number
Cause:
- using number not provisioned for SMS
Fix:
- use Twilio number with SMS capability

### Error: 429 or rate limits
Cause:
- too many requests
Fix:
- add queueing and retry backoff

### Error: Message not delivered in PH
Cause:
- sender route restrictions or carrier filtering
Fix:
- use Twilio-supported sender strategy for PH and check delivery reports

## 10. Security Notes

1. Never commit Twilio credentials to git.
2. Keep .env in server only.
3. Use separate credentials for staging and production.
4. Rotate Auth Token if exposed.

## 11. Recommended Production Path

1. Start with adapter pattern to avoid breaking existing code.
2. Validate all 4 SMS flows in this app.
3. Add monitoring dashboard from notification_log.
4. Add retry queue for failed sends.
5. Move to direct Twilio SDK only when stable and tested.

## 12. Quick Decision

If your goal is to go live fast with minimum code risk:
- Use Twilio via adapter endpoint first.

If your goal is long-term clean architecture with Twilio-native features:
- Implement direct Twilio integration in SmsService.php.
