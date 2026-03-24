<?php
require_once('../auth/session_check.php');
require_once('../config/database.php');
requireAdmin();

$conn = getDBConnection();
$current_user = getCurrentUser();

$payment_id = (int)($_GET['id'] ?? 0);

$payment = null;
if ($payment_id > 0) {
    $sql = "SELECT p.*, md.due_month, md.due_year, md.amount AS due_amount, h.unit_number,
                   CONCAT(u.first_name, ' ', u.last_name) AS owner_name,
                   CONCAT(v.first_name, ' ', v.last_name) AS verified_by_name
            FROM payments p
            INNER JOIN monthly_dues md ON p.dues_id = md.dues_id
            INNER JOIN households h ON p.household_id = h.household_id
            LEFT JOIN users u ON h.owner_id = u.user_id
            LEFT JOIN users v ON p.verified_by = v.user_id
            WHERE p.payment_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $payment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $payment = $result->fetch_assoc();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payment Details - Maia Alta HOA</title>
  <link rel="stylesheet" href="../style.css">
  <link rel="stylesheet" href="../css/admin.css">
  <link rel="icon" type="image/png" href="../pics/Courtyard.png">
  <style>
    .detail-card { background:#fff; border-radius:10px; padding:20px; box-shadow:0 2px 6px rgba(0,0,0,.08); max-width:800px; }
    .detail-grid { display:grid; grid-template-columns: 1fr 1fr; gap:10px 20px; }
    .label { color:#666; font-size:12px; }
    .value { font-weight:600; color:#333; margin-top:2px; }
    .actions { margin-top:16px; }
    .btn { background:#c17f59; color:#fff; border:none; border-radius:6px; padding:10px 14px; text-decoration:none; display:inline-block; }
  </style>
</head>
<body>
  <main style="margin-left:250px; padding:30px;">
    <div class="page-header">
      <h1>Payment Details</h1>
      <p class="breadcrumb">Home > Payments & Dues > Payment Details</p>
    </div>

    <div class="detail-card">
      <?php if ($payment): ?>
        <div class="detail-grid">
          <div><div class="label">Payment ID</div><div class="value">#<?php echo (int)$payment['payment_id']; ?></div></div>
          <div><div class="label">Unit</div><div class="value"><?php echo htmlspecialchars($payment['unit_number']); ?></div></div>
          <div><div class="label">Resident</div><div class="value"><?php echo htmlspecialchars($payment['owner_name'] ?? 'N/A'); ?></div></div>
          <div><div class="label">Period</div><div class="value"><?php echo htmlspecialchars($payment['due_month'] . ' ' . $payment['due_year']); ?></div></div>
          <div><div class="label">Amount Due</div><div class="value">₱<?php echo number_format((float)$payment['due_amount'], 2); ?></div></div>
          <div><div class="label">Amount Paid</div><div class="value">₱<?php echo number_format((float)$payment['amount_paid'], 2); ?></div></div>
          <div><div class="label">Payment Method</div><div class="value"><?php echo strtoupper(htmlspecialchars($payment['payment_method'])); ?></div></div>
          <div><div class="label">Reference Number</div><div class="value"><?php echo htmlspecialchars($payment['reference_number'] ?? '-'); ?></div></div>
          <div><div class="label">Payment Date</div><div class="value"><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></div></div>
          <div><div class="label">Verified At</div><div class="value"><?php echo $payment['verified_at'] ? date('M d, Y g:i A', strtotime($payment['verified_at'])) : 'Pending'; ?></div></div>
          <div><div class="label">Verified By</div><div class="value"><?php echo htmlspecialchars($payment['verified_by_name'] ?? 'Not yet verified'); ?></div></div>
          <div><div class="label">Remarks</div><div class="value"><?php echo htmlspecialchars($payment['remarks'] ?? '-'); ?></div></div>
        </div>
      <?php else: ?>
        <p>Payment record not found.</p>
      <?php endif; ?>
      <div class="actions">
        <a href="payments.php" class="btn">Back to Payments</a>
      </div>
    </div>
  </main>
</body>
</html>
