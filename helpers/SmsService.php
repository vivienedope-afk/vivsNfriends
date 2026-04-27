<?php
/**
 * SMS Service for Maia Alta HOA System
 * Uses Traccar SMS Gateway App on Phone
 */

class SmsService {

    // Use the LOCAL SERVICE token from your phone app, NOT the push notification API key
    private $sms_gateway_url = 'http://172.20.10.144:8082/';  // Root endpoint, NOT /api/send
    private $sms_gateway_token = '22322bb4-181a-403c-9d91-34bf116d7fee';  // Local Service Token
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /* =========================
       MAIN SMS SENDER
    ========================== */
    public function sendSms($to_phone, $message) {
        // Normalize Philippine phone numbers from local 0-prefix to +63 international format.
        $to_phone = trim($to_phone);
        if (preg_match('/^0(9\d{9})$/', $to_phone, $matches)) {
            $to_phone = '+63' . $matches[1];
        }

        if (!preg_match('/^\+?[1-9]\d{1,14}$/', $to_phone)) {
            return ['success' => false, 'message' => 'Invalid phone number'];
        }

        $payload = [
            'to' => $to_phone,
            'message' => $message,
        ];
        //loko kayo HAHAHAHAHA tangina damit main branch ginamit nyo mga loko HAHAHAHAHAHAHAHAHAHAHHAHAHAHAHAHAHAHAHAHHAHAHAHAHAHAHAHAHAHHAHAHAHA

        $ch = curl_init($this->sms_gateway_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: ' . $this->sms_gateway_token  // Use the Local Service Token
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $statusCode < 200 || $statusCode >= 300) {
            error_log("SMS error: status={$statusCode} response={$response} error={$error}");
            return [
                'success' => false,
                'message' => 'SMS failed',
                'status' => $statusCode,
                'response' => $response,
                'error' => $error,
            ];
        }

        return [
            'success' => true,
            'response' => $response,
            'status' => $statusCode,
        ];
    }

    /* =========================
       MONTHLY BILLING SMS
    ========================== */
    public function sendMonthlyDueSms($user_id, $phone, $name, $amount, $due_date, $month, $year) {
        $message = "MAIA ALTA HOA: Monthly due reminder - ₱" . number_format($amount, 2) . " for $month $year. Due by $due_date. Please settle promptly.";

        $result = $this->sendSms($phone, $message);

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
}

/* Helper */
function getSmsService($conn) {
    return new SmsService($conn);
}
?>