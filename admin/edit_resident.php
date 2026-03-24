<?php
require_once('../auth/session_check.php');
require_once('../config/database.php');
requireAdmin();

$conn = getDBConnection();
$current_user = getCurrentUser();

$user_id = (int)($_GET['user_id'] ?? 0);
$resident = null;

if ($user_id > 0) {
    $query = "SELECT u.user_id, u.account_number, u.first_name, u.last_name, u.email, u.contact_number, u.status,
                     h.household_id, h.unit_number, h.lot_number, h.block_number, h.resident_type
              FROM users u
              LEFT JOIN household_members hm ON u.user_id = hm.user_id AND hm.is_primary = 1
              LEFT JOIN households h ON hm.household_id = h.household_id
              WHERE u.user_id = ? AND u.user_role = 'resident'
              LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $resident = $result->fetch_assoc();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Resident - Maia Alta HOA</title>
  <link rel="stylesheet" href="../style.css">
  <link rel="stylesheet" href="../css/admin.css">
  <link rel="icon" type="image/png" href="../pics/Courtyard.png">
  <style>
    .form-card { background:#fff; border-radius:12px; padding:24px; box-shadow:0 2px 8px rgba(0,0,0,.08); max-width:900px; }
    .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    .full { grid-column:1 / -1; }
    label { display:block; font-size:13px; color:#555; margin-bottom:6px; }
    input, select { width:100%; padding:10px; border:1px solid #ddd; border-radius:7px; }
    .btns { margin-top:16px; display:flex; gap:10px; }
    .btn { border:none; border-radius:7px; padding:10px 14px; font-weight:600; cursor:pointer; }
    .btn-primary { background:#c17f59; color:#fff; }
    .btn-secondary { background:#6c757d; color:#fff; text-decoration:none; display:inline-flex; align-items:center; }
  </style>
</head>
<body>
  <main style="margin-left:250px; padding:30px;">
    <div class="page-header">
      <h1>Edit Resident</h1>
      <p class="breadcrumb">Home > Residents > Edit Resident</p>
    </div>

    <div class="form-card">
      <?php if ($resident): ?>
        <form method="POST" action="residents_action.php">
          <input type="hidden" name="action" value="edit">
          <input type="hidden" name="user_id" value="<?php echo (int)$resident['user_id']; ?>">
          <input type="hidden" name="household_id" value="<?php echo (int)($resident['household_id'] ?? 0); ?>">

          <div class="form-grid">
            <div>
              <label>Account Number</label>
              <input type="text" value="<?php echo htmlspecialchars($resident['account_number']); ?>" readonly>
            </div>
            <div>
              <label>Status</label>
              <select name="status" required>
                <option value="active" <?php echo $resident['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $resident['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
              </select>
            </div>
            <div>
              <label>First Name</label>
              <input type="text" name="first_name" value="<?php echo htmlspecialchars($resident['first_name']); ?>" required>
            </div>
            <div>
              <label>Last Name</label>
              <input type="text" name="last_name" value="<?php echo htmlspecialchars($resident['last_name']); ?>" required>
            </div>
            <div>
              <label>Email</label>
              <input type="email" name="email" value="<?php echo htmlspecialchars($resident['email']); ?>" required>
            </div>
            <div>
              <label>Contact Number</label>
              <input type="text" name="contact_number" value="<?php echo htmlspecialchars($resident['contact_number'] ?? ''); ?>">
            </div>
            <div>
              <label>Unit Number</label>
              <input type="text" name="unit_number" value="<?php echo htmlspecialchars($resident['unit_number'] ?? ''); ?>" required>
            </div>
            <div>
              <label>Resident Type</label>
              <select name="resident_type" required>
                <option value="owner" <?php echo ($resident['resident_type'] ?? '') === 'owner' ? 'selected' : ''; ?>>Owner</option>
                <option value="tenant" <?php echo ($resident['resident_type'] ?? '') === 'tenant' ? 'selected' : ''; ?>>Tenant</option>
              </select>
            </div>
            <div>
              <label>Lot Number</label>
              <input type="text" name="lot_number" value="<?php echo htmlspecialchars($resident['lot_number'] ?? ''); ?>">
            </div>
            <div>
              <label>Block Number</label>
              <input type="text" name="block_number" value="<?php echo htmlspecialchars($resident['block_number'] ?? ''); ?>">
            </div>
            <div class="full">
              <label>New Password (Optional)</label>
              <input type="text" name="new_password" placeholder="Leave blank to keep current password">
            </div>
          </div>

          <div class="btns">
            <button class="btn btn-primary" type="submit">Save Changes</button>
            <a class="btn btn-secondary" href="residents.php">Cancel</a>
          </div>
        </form>
      <?php else: ?>
        <p>Resident not found.</p>
        <a class="btn btn-secondary" href="residents.php">Back to Residents</a>
      <?php endif; ?>
    </div>
  </main>
</body>
</html>
