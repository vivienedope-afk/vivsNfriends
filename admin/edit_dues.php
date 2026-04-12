<?php
require_once('../auth/session_check.php');
require_once('../config/database.php');
requireAdmin();

$conn = getDBConnection();
$current_user = getCurrentUser();

function ensureMonthlyDuesMetaColumns($conn) {
  $conn->query("ALTER TABLE monthly_dues ADD COLUMN IF NOT EXISTS title VARCHAR(150) NULL AFTER due_year");
  $conn->query("ALTER TABLE monthly_dues ADD COLUMN IF NOT EXISTS description TEXT NULL AFTER title");
}

ensureMonthlyDuesMetaColumns($conn);

$dues_id = (int)($_GET['id'] ?? 0);
$dues = null;

if ($dues_id > 0) {
    $sql = "SELECT md.*, h.unit_number, CONCAT(u.first_name, ' ', u.last_name) AS owner_name
            FROM monthly_dues md
            INNER JOIN households h ON md.household_id = h.household_id
            LEFT JOIN users u ON h.owner_id = u.user_id
            WHERE md.dues_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $dues_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $dues = $result->fetch_assoc();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Dues - Maia Alta HOA</title>
  <link rel="stylesheet" href="../style.css">
  <link rel="stylesheet" href="../css/admin.css">
  <link rel="icon" type="image/png" href="../pics/Courtyard.png">
  <style>
    .form-card { background:#fff; border-radius:10px; padding:20px; box-shadow:0 2px 6px rgba(0,0,0,.08); max-width:720px; }
    .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .form-grid .full { grid-column:1 / -1; }
    label { font-size:12px; color:#666; display:block; margin-bottom:6px; }
    input, select { width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; }
    .btn { border:none; border-radius:6px; padding:10px 14px; cursor:pointer; font-weight:600; }
    .btn-primary { background:#c17f59; color:#fff; }
    .btn-secondary { background:#6c757d; color:#fff; text-decoration:none; display:inline-block; }
    .actions { margin-top:16px; display:flex; gap:10px; }
  </style>
</head>
<body>
  <main style="margin-left:250px; padding:30px;">
    <div class="page-header">
      <h1>Edit Monthly Dues</h1>
      <p class="breadcrumb">Home > Payments & Dues > Edit Dues</p>
    </div>

    <div class="form-card">
      <?php if ($dues): ?>
        <form method="POST" action="payments_action.php">
          <input type="hidden" name="action" value="edit_dues">
          <input type="hidden" name="dues_id" value="<?php echo (int)$dues['dues_id']; ?>">

          <div class="form-grid">
            <div class="full">
              <label>Resident</label>
              <input type="text" value="<?php echo htmlspecialchars($dues['unit_number'] . ' - ' . ($dues['owner_name'] ?? 'N/A')); ?>" readonly>
            </div>
            <div>
              <label>Title</label>
              <input type="text" name="title" maxlength="150" value="<?php echo htmlspecialchars($dues['title'] ?? 'Monthly HOA Dues'); ?>" required>
            </div>
            <div class="full">
              <label>Description</label>
              <input type="text" name="description" value="<?php echo htmlspecialchars($dues['description'] ?? ''); ?>" placeholder="Optional description for this due.">
            </div>
            <div>
              <label>Due Month</label>
              <select name="due_month" required>
                <?php foreach (['January','February','March','April','May','June','July','August','September','October','November','December'] as $month): ?>
                  <option value="<?php echo $month; ?>" <?php echo $dues['due_month'] === $month ? 'selected' : ''; ?>><?php echo $month; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label>Due Year</label>
              <input type="number" name="due_year" value="<?php echo (int)$dues['due_year']; ?>" min="2020" max="2100" required>
            </div>
            <div>
              <label>Amount</label>
              <input type="number" name="amount" step="0.01" min="0" value="<?php echo htmlspecialchars($dues['amount']); ?>" required>
            </div>
            <div>
              <label>Due Date</label>
              <input type="date" name="due_date" value="<?php echo htmlspecialchars($dues['due_date']); ?>" required>
            </div>
            <div>
              <label>Status</label>
              <select name="status" required>
                <option value="unpaid" <?php echo $dues['status'] === 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                <option value="paid" <?php echo $dues['status'] === 'paid' ? 'selected' : ''; ?>>Paid</option>
                <option value="overdue" <?php echo $dues['status'] === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
              </select>
            </div>
          </div>

          <div class="actions">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="payments.php" class="btn btn-secondary">Cancel</a>
          </div>
        </form>
      <?php else: ?>
        <p>Dues record not found.</p>
        <div class="actions"><a href="payments.php" class="btn btn-secondary">Back to Payments</a></div>
      <?php endif; ?>
    </div>
  </main>
</body>
</html>
