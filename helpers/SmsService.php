<?php
/**
 * SMS Service for Maia Alta HOA System
 * Uses Traccar SMS Gateway App on Phone
 */

class SmsService {

    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /* =========================
       MAIN SMS SENDER
    ========================== */
    public function sendSms($to_phone, $message, $sms_type = 'general') {
        // Normalize Philippine phone numbers from local 0-prefix to +63 international format.
        $to_phone = trim($to_phone);
        if (preg_match('/^0(9\d{9})$/', $to_phone, $matches)) {
            $to_phone = '+63' . $matches[1];
        }

        if (!preg_match('/^\+?[1-9]\d{1,14}$/', $to_phone)) {
            return ['success' => false, 'message' => 'Invalid phone number'];
        }

        $providerMode = $this->getProviderMode();

        if ($providerMode === 'direct') {
            return $this->sendViaTwilio($to_phone, $message);
        }

        $payload = [
            'to' => $to_phone,
            'message' => $message,
        ];

        $config = $this->getGatewayConfig($sms_type);
        if (empty($config['url'])) {
            return ['success' => false, 'message' => 'SMS gateway config missing'];
        }

        $prepared = $this->prepareAdapterRequest($config, $to_phone, $message);
        if (!$prepared['success']) {
            return ['success' => false, 'message' => $prepared['message']];
        }

        $ch = curl_init($config['url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, (int)$config['timeout']);

        $sslVerify = $this->isSslVerifyEnabled();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $sslVerify);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $sslVerify ? 2 : 0);

        $headers = $prepared['headers'];

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($prepared['payload']));

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $statusCode < 200 || $statusCode >= 300) {
            error_log("SMS error: status={$statusCode} response={$response} error={$error}");
            return [
                'success' => false,
                'message' => 'SMS failed',
                'provider_state' => 'failed',
                'status' => $statusCode,
                'response' => $response,
                'error' => $error,
            ];
        }

        $providerState = 'sent';
        $providerMessage = '';
        $providerMessageId = '';
        $decoded = json_decode((string)$response, true);

        if (is_array($decoded)) {
            $providerMessage = trim((string)($decoded['message'] ?? ''));
            $apiStatus = strtolower(trim((string)($decoded['status'] ?? '')));
            $data = $decoded['data'] ?? null;

            if (is_array($data)) {
                $providerMessageId = trim((string)($data['id'] ?? ''));
                $state = strtolower(trim((string)($data['status'] ?? '')));
                if ($state !== '') {
                    $providerState = $state;
                } elseif ($apiStatus === 'success') {
                    $providerState = 'queued';
                }
            } elseif ($apiStatus === 'success') {
                $providerState = 'queued';
            }
        }

        return [
            'success' => true,
            'response' => $response,
            'status' => $statusCode,
            'provider_state' => $providerState,
            'provider_message' => $providerMessage,
            'provider_message_id' => $providerMessageId,
        ];
    }

    private function sendViaTwilio($to_phone, $message) {
        $sid = env('TWILIO_ACCOUNT_SID', '');
        $token = env('TWILIO_AUTH_TOKEN', '');
        $from = env('TWILIO_FROM_NUMBER', '');

        if ($sid === '' || $token === '' || $from === '') {
            return ['success' => false, 'message' => 'Twilio config missing'];
        }

        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($sid) . '/Messages.json';
        $postFields = http_build_query([
            'To' => $to_phone,
            'From' => $from,
            'Body' => $message,
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, (int)env('SMS_DEFAULT_TIMEOUT', 20));
        $sslVerify = $this->isSslVerifyEnabled();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $sslVerify);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $sslVerify ? 2 : 0);
        curl_setopt($ch, CURLOPT_USERPWD, $sid . ':' . $token);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $statusCode < 200 || $statusCode >= 300) {
            error_log("Twilio SMS error: status={$statusCode} response={$response} error={$error}");
            return [
                'success' => false,
                'message' => 'Twilio SMS failed',
                'provider_state' => 'failed',
                'status' => $statusCode,
                'response' => $response,
                'error' => $error,
            ];
        }

        $providerState = 'sent';
        $providerMessage = '';
        $providerMessageId = '';
        $decoded = json_decode((string)$response, true);
        if (is_array($decoded)) {
            $providerMessageId = trim((string)($decoded['sid'] ?? ''));
            $providerMessage = trim((string)($decoded['error_message'] ?? ''));
            $state = strtolower(trim((string)($decoded['status'] ?? '')));
            if ($state !== '') {
                $providerState = $state;
            }
        }

        return [
            'success' => true,
            'response' => $response,
            'status' => $statusCode,
            'provider_state' => $providerState,
            'provider_message' => $providerMessage,
            'provider_message_id' => $providerMessageId,
        ];
    }

    /* =========================
       MONTHLY BILLING SMS
    ========================== */
    public function sendMonthlyDueSms($user_id, $phone, $name, $amount, $due_date, $month, $year) {
        $message = "MAIA ALTA HOA: Monthly due reminder - ₱" . number_format($amount, 2) . " for $month $year. Due by $due_date. Please settle promptly.";

        $result = $this->sendSms($phone, $message, 'billing');

        // Log SMS
        $this->logSms($phone, $name, $message, $result['success'] ? 'sent' : 'failed', 'monthly_due', $user_id, $result['success'] ? null : ($result['error'] ?? 'SMS failed'));

        return $result;
    }

    /* =========================
       SMS LOGGING
    ========================== */
    private function logSms($phone, $name, $message, $status, $type, $user_id, $error) {
        $sql = "INSERT INTO sms_logs
                (user_id, phone_number, message, status, error_message, sent_at)
                VALUES (?, ?, ?, ?, ?, NOW())";

        try {
            $stmt = $this->db->prepare($sql);
            if (!$stmt) {
                error_log("SMS log prepare failed: " . $this->db->error);
                return null;
            }

            $stmt->bind_param(
                "issss",
                $user_id,
                $phone,
                $message,
                $status,
                $error
            );

            return $stmt->execute() ? $this->db->insert_id : null;
        } catch (mysqli_sql_exception $e) {
            error_log("SMS log error: " . $e->getMessage());
            return null;
        }
    }

    private function getGatewayConfig($sms_type = 'general') {
        require_once __DIR__ . '/../config/env.php';

        $prefix = strtolower((string)$sms_type) === 'billing' ? 'SMS_BILLING_' : 'SMS_DEFAULT_';

        $url = env($prefix . 'GATEWAY_URL', env('SMS_DEFAULT_GATEWAY_URL', ''));
        $token = env($prefix . 'GATEWAY_TOKEN', env('SMS_DEFAULT_GATEWAY_TOKEN', ''));
        $timeout = env($prefix . 'TIMEOUT', env('SMS_DEFAULT_TIMEOUT', 20));
        $provider = strtolower(trim((string)env($prefix . 'PROVIDER', env('SMS_PROVIDER', ''))));
        $deviceId = env($prefix . 'DEVICE_ID', env('SMS_DEVICE_ID', ''));
        $from = env($prefix . 'FROM', env('SMS_FROM', ''));

        return [
            'url' => $url,
            'token' => $token,
            'timeout' => is_numeric($timeout) ? (int)$timeout : 20,
            'provider' => $provider,
            'device_id' => trim((string)$deviceId),
            'from' => trim((string)$from)
        ];
    }

    private function prepareAdapterRequest($config, $to_phone, $message) {
        $url = strtolower((string)$config['url']);
        $provider = strtolower((string)($config['provider'] ?? ''));

        if ($provider === '' && strpos($url, 'api.httpsms.com') !== false) {
            $provider = 'httpsms';
        }

        if ($provider === 'httpsms') {
            if (empty($config['token'])) {
                return ['success' => false, 'message' => 'httpSMS API key missing'];
            }
            if (empty($config['from'])) {
                return ['success' => false, 'message' => 'httpSMS sender number missing'];
            }

            $toDigits = preg_replace('/\D+/', '', $to_phone);
            if ($toDigits === '') {
                return ['success' => false, 'message' => 'httpSMS recipient number invalid'];
            }

            return [
                'success' => true,
                'headers' => [
                    'Content-Type: application/json',
                    'x-api-key: ' . $config['token']
                ],
                'payload' => [
                    'from' => $config['from'],
                    'to' => $toDigits,
                    'content' => $message
                ]
            ];
        }

        // Generic adapter mode: send {to, message} and optional Authorization header.
        $headers = ['Content-Type: application/json'];
        if (!empty($config['token'])) {
            $headers[] = 'Authorization: ' . $config['token'];
        }

        return [
            'success' => true,
            'headers' => $headers,
            'payload' => [
                'to' => $to_phone,
                'message' => $message
            ]
        ];
    }

    private function getProviderMode() {
        require_once __DIR__ . '/../config/env.php';

        $mode = strtolower(trim((string)env('SMS_PROVIDER_MODE', 'adapter')));
        return in_array($mode, ['adapter', 'direct'], true) ? $mode : 'adapter';
    }

    private function isSslVerifyEnabled() {
        require_once __DIR__ . '/../config/env.php';

        $raw = strtolower(trim((string)env('SMS_SSL_VERIFY', '1')));
        return !in_array($raw, ['0', 'false', 'no', 'off'], true);
    }
}

/* Helper */
function getSmsService($conn) {
    return new SmsService($conn);
}
?>