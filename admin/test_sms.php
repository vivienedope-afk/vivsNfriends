<?php
require_once('../config/database.php');
require_once('../helpers/SmsService.php');

$conn = getDBConnection();
$sms = getSmsService($conn);

// Test to Mary Smith's number
$result = $sms->sendSms('09498544041', 'TEST: Maia Alta HOA system is working!');

echo "<pre>";
print_r($result);
echo "</pre>";

$conn->close();
?>