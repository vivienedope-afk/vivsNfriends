<?php
require_once('../auth/session_check.php');
require_once('../config/database.php');
requireAdmin();

$conn = getDBConnection();

// Get application ID
$app_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($app_id <= 0) {
    echo '<p class="error">Invalid application ID.</p>';
    exit();
}

// Fetch application details
$query = "SELECT * FROM account_applications WHERE application_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $app_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo '<p class="error">Application not found.</p>';
    exit();
}

$app = $result->fetch_assoc();

// Get reviewer info if reviewed
$reviewer_name = 'N/A';
if ($app['reviewed_by']) {
    $reviewer_query = "SELECT first_name, last_name FROM users WHERE user_id = ?";
    $reviewer_stmt = $conn->prepare($reviewer_query);
    $reviewer_stmt->bind_param("i", $app['reviewed_by']);
    $reviewer_stmt->execute();
    $reviewer_result = $reviewer_stmt->get_result();
    if ($reviewer_result->num_rows > 0) {
        $reviewer = $reviewer_result->fetch_assoc();
        $reviewer_name = $reviewer['first_name'] . ' ' . $reviewer['last_name'];
    }
}
?>

<style>
  .app-detail-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 25px;
  }

  .app-detail-item {
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    border-left: 3px solid #d4a574;
  }

  .app-detail-label {
    font-size: 12px;
    color: #777;
    text-transform: uppercase;
    margin-bottom: 5px;
    font-weight: 600;
    letter-spacing: 0.5px;
  }

  .app-detail-value {
    font-size: 15px;
    color: #2c3e50;
    font-weight: 500;
  }

  .app-detail-item.full-width {
    grid-column: 1 / -1;
  }

  .id-proof-section {
    margin: 25px 0;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
    text-align: center;
  }

  .id-proof-section h3 {
    margin: 0 0 15px 0;
    color: #2c3e50;
    font-size: 16px;
  }

  .id-proof-image {
    max-width: 100%;
    max-height: 400px;
    border-radius: 8px;
    border: 2px solid #e0e0e0;
    cursor: pointer;
    transition: transform 0.3s;
  }

  .id-proof-image:hover {
    transform: scale(1.05);
  }

  .id-proof-link {
    display: inline-block;
    margin-top: 10px;
    padding: 10px 20px;
    background: #3498db;
    color: white;
    text-decoration: none;
    border-radius: 6px;
    font-size: 14px;
    transition: background 0.3s;
  }

  .id-proof-link:hover {
    background: #2980b9;
  }

  .action-buttons {
    display: flex;
    gap: 15px;
    margin-top: 25px;
    padding-top: 20px;
    border-top: 2px solid #e0e0e0;
  }

  .btn-approve, .btn-reject {
    flex: 1;
    padding: 14px 25px;
    border: none;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
  }

  .btn-approve {
    background: #27ae60;
    color: white;
  }

  .btn-approve:hover {
    background: #229954;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
  }

  .btn-reject {
    background: #e74c3c;
    color: white;
  }

  .btn-reject:hover {
    background: #c0392b;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
  }

  .btn-approve:disabled, .btn-reject:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }

  .rejection-form {
    display: none;
    margin-top: 20px;
    padding: 20px;
    background: #fff3cd;
    border-radius: 8px;
    border: 1px solid #ffeaa7;
  }

  .rejection-form.show {
    display: block;
  }

  .rejection-form label {
    display: block;
    font-weight: 600;
    margin-bottom: 10px;
    color: #856404;
  }

  .rejection-form textarea {
    width: 100%;
    padding: 12px;
    border: 2px solid #e0e0e0;
    border-radius: 6px;
    font-family: 'Roboto', sans-serif;
    font-size: 14px;
    min-height: 100px;
    resize: vertical;
  }

  .rejection-form-buttons {
    display: flex;
    gap: 10px;
    margin-top: 15px;
  }

  .btn-submit-rejection, .btn-cancel-rejection {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
  }

  .btn-submit-rejection {
    background: #e74c3c;
    color: white;
  }

  .btn-cancel-rejection {
    background: #95a5a6;
    color: white;
  }

  .status-display {
    display: inline-block;
    padding: 8px 16px;
    border-radius: 20px;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 13px;
  }

  .status-display.pending {
    background: #fff3cd;
    color: #856404;
  }

  .status-display.approved {
    background: #d4edda;
    color: #155724;
  }

  .status-display.rejected {
    background: #f8d7da;
    color: #721c24;
  }

  .reviewed-info {
    margin-top: 20px;
    padding: 15px;
    background: #e9ecef;
    border-radius: 8px;
  }

  .reviewed-info p {
    margin: 5px 0;
    font-size: 14px;
    color: #555;
  }

  @media (max-width: 600px) {
    .app-detail-grid {
      grid-template-columns: 1fr;
    }

    .action-buttons {
      flex-direction: column;
    }
  }
</style>

<div class="app-detail-grid">
  <div class="app-detail-item">
    <div class="app-detail-label">Application ID</div>
    <div class="app-detail-value">#<?php echo $app['application_id']; ?></div>
  </div>

  <div class="app-detail-item">
    <div class="app-detail-label">Status</div>
    <div class="app-detail-value">
      <span class="status-display <?php echo $app['status']; ?>">
        <?php echo ucfirst($app['status']); ?>
      </span>
    </div>
  </div>

  <div class="app-detail-item">
    <div class="app-detail-label">First Name</div>
    <div class="app-detail-value"><?php echo htmlspecialchars($app['first_name']); ?></div>
  </div>

  <div class="app-detail-item">
    <div class="app-detail-label">Last Name</div>
    <div class="app-detail-value"><?php echo htmlspecialchars($app['last_name']); ?></div>
  </div>

  <div class="app-detail-item">
    <div class="app-detail-label">Email Address</div>
    <div class="app-detail-value"><?php echo htmlspecialchars($app['email']); ?></div>
  </div>

  <div class="app-detail-item">
    <div class="app-detail-label">Contact Number</div>
    <div class="app-detail-value"><?php echo htmlspecialchars($app['contact_number']); ?></div>
  </div>

  <div class="app-detail-item">
    <div class="app-detail-label">Unit Number</div>
    <div class="app-detail-value"><?php echo htmlspecialchars($app['unit_number']); ?></div>
  </div>

  <div class="app-detail-item">
    <div class="app-detail-label">Resident Type</div>
    <div class="app-detail-value"><?php echo ucfirst($app['resident_type']); ?></div>
  </div>

  <?php if (!empty($app['lot_number'])): ?>
  <div class="app-detail-item">
    <div class="app-detail-label">Lot Number</div>
    <div class="app-detail-value"><?php echo htmlspecialchars($app['lot_number']); ?></div>
  </div>
  <?php endif; ?>

  <?php if (!empty($app['block_number'])): ?>
  <div class="app-detail-item">
    <div class="app-detail-label">Block Number</div>
    <div class="app-detail-value"><?php echo htmlspecialchars($app['block_number']); ?></div>
  </div>
  <?php endif; ?>

  <div class="app-detail-item">
    <div class="app-detail-label">Date Applied</div>
    <div class="app-detail-value"><?php echo date('F d, Y g:i A', strtotime($app['created_at'])); ?></div>
  </div>
</div>

<!-- Resident ID Proof -->
<div class="id-proof-section">
  <h3>Resident ID / Proof of Residency</h3>
  <?php if (!empty($app['id_proof_path']) && file_exists('../' . $app['id_proof_path'])): ?>
    <?php 
      $file_ext = strtolower(pathinfo($app['id_proof_path'], PATHINFO_EXTENSION));
      if (in_array($file_ext, ['jpg', 'jpeg', 'png'])):
    ?>
      <img src="../<?php echo htmlspecialchars($app['id_proof_path']); ?>" 
           alt="ID Proof" 
           class="id-proof-image"
           onclick="window.open('../<?php echo htmlspecialchars($app['id_proof_path']); ?>', '_blank')">
    <?php else: ?>
      <p>PDF Document</p>
      <a href="../<?php echo htmlspecialchars($app['id_proof_path']); ?>" 
         target="_blank" 
         class="id-proof-link">
        View PDF Document
      </a>
    <?php endif; ?>
  <?php else: ?>
    <p style="color: #e74c3c;">ID proof file not found.</p>
  <?php endif; ?>
</div>

<?php if ($app['status'] === 'rejected' && !empty($app['rejection_reason'])): ?>
  <div class="reviewed-info">
    <p><strong>Rejection Reason:</strong></p>
    <p><?php echo nl2br(htmlspecialchars($app['rejection_reason'])); ?></p>
  </div>
<?php endif; ?>

<?php if ($app['reviewed_at']): ?>
  <div class="reviewed-info">
    <p><strong>Reviewed By:</strong> <?php echo htmlspecialchars($reviewer_name); ?></p>
    <p><strong>Reviewed On:</strong> <?php echo date('F d, Y g:i A', strtotime($app['reviewed_at'])); ?></p>
  </div>
<?php endif; ?>

<?php if ($app['status'] === 'pending'): ?>
  <div class="action-buttons">
    <button class="btn-approve" onclick="approveApplication(<?php echo $app['application_id']; ?>)">
      Approve Application
    </button>
    <button class="btn-reject" onclick="showRejectionForm()">
      Reject Application
    </button>
  </div>

  <div id="rejectionForm" class="rejection-form">
    <label for="rejection_reason">Reason for Rejection:</label>
    <textarea id="rejection_reason" name="rejection_reason" 
              placeholder="Please provide a reason for rejection..."></textarea>
    <div class="rejection-form-buttons">
      <button class="btn-submit-rejection" onclick="rejectApplication(<?php echo $app['application_id']; ?>)">
        Confirm Rejection
      </button>
      <button class="btn-cancel-rejection" onclick="hideRejectionForm()">
        Cancel
      </button>
    </div>
  </div>

  <script>
    function approveApplication(appId) {
      if (confirm('Are you sure you want to APPROVE this application? This will create an account and send credentials to the applicant.')) {
        window.location.href = `process_application.php?action=approve&id=${appId}`;
      }
    }

    function showRejectionForm() {
      document.getElementById('rejectionForm').classList.add('show');
    }

    function hideRejectionForm() {
      document.getElementById('rejectionForm').classList.remove('show');
      document.getElementById('rejection_reason').value = '';
    }

    function rejectApplication(appId) {
      const reason = document.getElementById('rejection_reason').value.trim();
      
      if (reason === '') {
        alert('Please provide a reason for rejection.');
        return;
      }

      if (confirm('Are you sure you want to REJECT this application?')) {
        window.location.href = `process_application.php?action=reject&id=${appId}&reason=${encodeURIComponent(reason)}`;
      }
    }
  </script>
<?php endif; ?>

<?php $conn->close(); ?>
