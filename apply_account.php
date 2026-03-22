<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Apply for Account - Maia Alta HOA</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="css/apply_account.css">
  <link rel="icon" type="image/png" href="pics/Courtyard.png">
</head>
<body>
  <div class="apply-container">
    <div class="apply-card">
      <div class="apply-header">
        <img src="pics/Courtyard.png" alt="Courtyard Logo" class="apply-logo">
        <h1>Apply for Account</h1>
        <p>Maia Alta Homes HOA Portal</p>
      </div>

      <div class="info-box">
        <p><strong>Who can apply:</strong> Current residents of Maia Alta Homes (owners and tenants)</p>
        <p><strong>Requirements:</strong> Valid resident ID or proof of residency</p>
        <p><strong>Processing time:</strong> 1-3 business days</p>
      </div>

      <?php if(isset($_GET['success'])): ?>
        <div class="alert alert-success">
          Application submitted successfully! You will receive a notification once your application is reviewed.
        </div>
      <?php endif; ?>

      <?php if(isset($_GET['error'])): ?>
        <div class="alert alert-error">
          <?php 
            if($_GET['error'] == 'exists') {
              echo 'An application with this email or contact number already exists.';
            } elseif($_GET['error'] == 'file_error') {
              echo 'Error uploading ID proof. Please try again.';
            } elseif($_GET['error'] == 'invalid_file') {
              echo 'Invalid file type. Please upload an image (JPG, PNG) or PDF.';
            } elseif($_GET['error'] == 'file_too_large') {
              echo 'File size too large. Maximum 5MB allowed.';
            } elseif($_GET['error'] == 'required') {
              echo 'Please fill in all required fields.';
            } else {
              echo 'An error occurred. Please try again.';
            }
          ?>
        </div>
      <?php endif; ?>

      <form action="process_application.php" method="POST" enctype="multipart/form-data" class="apply-form" id="applicationForm">
        <div class="form-section">
          <h3>Personal Information</h3>
          
          <div class="form-row">
            <div class="form-group">
              <label for="first_name">First Name <span class="required">*</span></label>
              <input type="text" id="first_name" name="first_name" required maxlength="50">
            </div>

            <div class="form-group">
              <label for="last_name">Last Name <span class="required">*</span></label>
              <input type="text" id="last_name" name="last_name" required maxlength="50">
            </div>
          </div>

          <div class="form-group">
            <label for="email">Email Address <span class="required">*</span></label>
            <input type="email" id="email" name="email" required maxlength="100">
            <span class="input-hint">Will be used for account notifications</span>
          </div>

          <div class="form-group">
            <label for="contact_number">Contact Number <span class="required">*</span></label>
            <input type="tel" id="contact_number" name="contact_number" 
                   placeholder="09XXXXXXXXX" required pattern="09[0-9]{9}" maxlength="11">
            <span class="input-hint">Format: 09XXXXXXXXX</span>
          </div>
        </div>

        <div class="form-section">
          <h3>Residence Information</h3>
          
          <div class="form-row">
            <div class="form-group">
              <label for="unit_number">Unit Number <span class="required">*</span></label>
              <input type="text" id="unit_number" name="unit_number" required maxlength="20">
            </div>

            <div class="form-group">
              <label for="resident_type">Resident Type <span class="required">*</span></label>
              <select id="resident_type" name="resident_type" required>
                <option value="">-- Select --</option>
                <option value="owner">Owner</option>
                <option value="tenant">Tenant</option>
              </select>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="lot_number">Lot Number</label>
              <input type="text" id="lot_number" name="lot_number" maxlength="20">
            </div>

            <div class="form-group">
              <label for="block_number">Block Number</label>
              <input type="text" id="block_number" name="block_number" maxlength="20">
            </div>
          </div>
        </div>

        <div class="form-section">
          <h3>Proof of Residency</h3>
          
          <div class="form-group">
            <label for="id_proof">Upload Resident ID / Proof of Residency <span class="required">*</span></label>
            <input type="file" id="id_proof" name="id_proof" accept="image/*,.pdf" required>
            <span class="input-hint">Accepted formats: JPG, PNG, PDF (Max 5MB)</span>
            <div id="file-preview" class="file-preview"></div>
          </div>
        </div>

        <div class="form-section terms-section">
          <label class="checkbox-label">
            <input type="checkbox" id="terms" name="terms" required>
            <span>I confirm that the information provided is accurate and I agree to the terms and conditions of Maia Alta Homes HOA <span class="required">*</span></span>
          </label>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn-submit" id="submitBtn">Submit Application</button>
          <a href="login.php" class="btn-cancel">Back to Login</a>
        </div>
      </form>
    </div>
  </div>

  <script>
    // File preview
    document.getElementById('id_proof').addEventListener('change', function(e) {
      const file = e.target.files[0];
      const preview = document.getElementById('file-preview');
      
      if (file) {
        const fileSize = (file.size / 1024 / 1024).toFixed(2); // MB
        const fileName = file.name;
        const fileType = file.type;
        
        if (fileSize > 5) {
          alert('File size exceeds 5MB limit. Please choose a smaller file.');
          this.value = '';
          preview.innerHTML = '';
          return;
        }
        
        preview.innerHTML = `
          <div class="file-info">
            <strong>Selected file:</strong> ${fileName} (${fileSize} MB)
          </div>
        `;
        
        // Show image preview if it's an image
        if (fileType.startsWith('image/')) {
          const reader = new FileReader();
          reader.onload = function(e) {
            preview.innerHTML += `
              <div class="image-preview">
                <img src="${e.target.result}" alt="ID Preview">
              </div>
            `;
          };
          reader.readAsDataURL(file);
        }
      } else {
        preview.innerHTML = '';
      }
    });

    // Form validation
    document.getElementById('applicationForm').addEventListener('submit', function(e) {
      const contactNumber = document.getElementById('contact_number').value;
      const terms = document.getElementById('terms').checked;
      
      if (!contactNumber.match(/^09[0-9]{9}$/)) {
        e.preventDefault();
        alert('Please enter a valid Philippine mobile number (09XXXXXXXXX)');
        return false;
      }
      
      if (!terms) {
        e.preventDefault();
        alert('Please agree to the terms and conditions');
        return false;
      }
      
      // Disable submit button to prevent double submission
      document.getElementById('submitBtn').disabled = true;
      document.getElementById('submitBtn').textContent = 'Submitting...';
    });
  </script>
</body>
</html>
