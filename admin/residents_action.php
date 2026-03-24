<?php
require_once('../auth/session_check.php');
require_once('../config/database.php');
requireAdmin();

$conn = getDBConnection();
$current_user = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    // Add new resident
    $account_number = trim($_POST['account_number']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $contact_number = trim($_POST['contact_number']);
    $unit_number = trim($_POST['unit_number']);
    $lot_number = trim($_POST['lot_number']);
    $block_number = trim($_POST['block_number']);
    $resident_type = $_POST['resident_type'];
    $default_password = $_POST['default_password'];
    
    // Plain text password for now (will add hashing later)
    
    // Check if account number or email already exists
    $check_query = "SELECT user_id FROM users WHERE account_number = ? OR email = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ss", $account_number, $email);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        header('Location: residents.php?error=exists');
        exit();
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Insert user
        $user_query = "INSERT INTO users (account_number, password, email, first_name, last_name, contact_number, user_role, status, created_by) 
                       VALUES (?, ?, ?, ?, ?, ?, 'resident', 'active', ?)";
        $user_stmt = $conn->prepare($user_query);
        $user_stmt->bind_param("ssssssi", $account_number, $default_password, $email, $first_name, $last_name, $contact_number, $current_user['user_id']);
        $user_stmt->execute();
        $user_id = $conn->insert_id;
        
        // Insert household
        $household_query = "INSERT INTO households (unit_number, lot_number, block_number, owner_id, resident_type, move_in_date, status) 
                            VALUES (?, ?, ?, ?, ?, CURDATE(), 'occupied')";
        $household_stmt = $conn->prepare($household_query);
        $household_stmt->bind_param("sssis", $unit_number, $lot_number, $block_number, $user_id, $resident_type);
        $household_stmt->execute();
        $household_id = $conn->insert_id;
        
        // Link user to household
        $member_query = "INSERT INTO household_members (household_id, user_id, relationship, is_primary) 
                         VALUES (?, ?, 'owner', 1)";
        $member_stmt = $conn->prepare($member_query);
        $member_stmt->bind_param("ii", $household_id, $user_id);
        $member_stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        header('Location: residents.php?success=added');
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log($e->getMessage());
        header('Location: residents.php?error=failed');
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $household_id = (int)($_POST['household_id'] ?? 0);
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $status = trim($_POST['status'] ?? 'active');
    $unit_number = trim($_POST['unit_number'] ?? '');
    $lot_number = trim($_POST['lot_number'] ?? '');
    $block_number = trim($_POST['block_number'] ?? '');
    $resident_type = trim($_POST['resident_type'] ?? 'owner');
    $new_password = trim($_POST['new_password'] ?? '');

    if ($user_id <= 0 || $first_name === '' || $last_name === '' || $email === '' || $unit_number === '') {
        header('Location: residents.php?error=failed');
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header('Location: residents.php?error=failed');
        exit();
    }

    if (!in_array($status, ['active', 'inactive'], true)) {
        $status = 'active';
    }
    if (!in_array($resident_type, ['owner', 'tenant'], true)) {
        $resident_type = 'owner';
    }

    $email_check_query = "SELECT user_id FROM users WHERE email = ? AND user_id != ? LIMIT 1";
    $email_check_stmt = $conn->prepare($email_check_query);
    $email_check_stmt->bind_param("si", $email, $user_id);
    $email_check_stmt->execute();
    $email_check_result = $email_check_stmt->get_result();
    if ($email_check_result && $email_check_result->num_rows > 0) {
        header('Location: residents.php?error=exists');
        exit();
    }

    $conn->begin_transaction();
    try {
        if ($new_password !== '') {
            $update_user_query = "UPDATE users SET first_name = ?, last_name = ?, email = ?, contact_number = ?, status = ?, password = ? WHERE user_id = ? AND user_role = 'resident'";
            $update_user_stmt = $conn->prepare($update_user_query);
            $update_user_stmt->bind_param("ssssssi", $first_name, $last_name, $email, $contact_number, $status, $new_password, $user_id);
        } else {
            $update_user_query = "UPDATE users SET first_name = ?, last_name = ?, email = ?, contact_number = ?, status = ? WHERE user_id = ? AND user_role = 'resident'";
            $update_user_stmt = $conn->prepare($update_user_query);
            $update_user_stmt->bind_param("sssssi", $first_name, $last_name, $email, $contact_number, $status, $user_id);
        }
        $update_user_stmt->execute();

        if ($household_id > 0) {
            $unit_check_query = "SELECT household_id FROM households WHERE unit_number = ? AND household_id != ? LIMIT 1";
            $unit_check_stmt = $conn->prepare($unit_check_query);
            $unit_check_stmt->bind_param("si", $unit_number, $household_id);
            $unit_check_stmt->execute();
            $unit_check_result = $unit_check_stmt->get_result();
            if ($unit_check_result && $unit_check_result->num_rows > 0) {
                throw new Exception('Unit number already exists.');
            }

            $update_household_query = "UPDATE households SET unit_number = ?, lot_number = ?, block_number = ?, resident_type = ? WHERE household_id = ?";
            $update_household_stmt = $conn->prepare($update_household_query);
            $update_household_stmt->bind_param("ssssi", $unit_number, $lot_number, $block_number, $resident_type, $household_id);
            $update_household_stmt->execute();
        }

        $conn->commit();
        header('Location: residents.php?success=updated');
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        error_log($e->getMessage());
        header('Location: residents.php?error=failed');
        exit();
    }
}

// Toggle status (activate/deactivate)
if (isset($_GET['action']) && $_GET['action'] == 'toggle_status') {
    $user_id = intval($_GET['user_id']);
    $current_status = $_GET['status'];
    $new_status = $current_status === 'active' ? 'inactive' : 'active';
    
    $update_query = "UPDATE users SET status = ? WHERE user_id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("si", $new_status, $user_id);
    
    if ($update_stmt->execute()) {
        header('Location: residents.php?success=updated');
    } else {
        header('Location: residents.php?error=failed');
    }
    exit();
}

if (isset($_GET['action']) && $_GET['action'] == 'archive') {
    $user_id = (int)($_GET['user_id'] ?? 0);
    if ($user_id <= 0) {
        header('Location: residents.php?error=failed');
        exit();
    }

    $conn->begin_transaction();
    try {
        $archive_user_query = "UPDATE users SET status = 'inactive' WHERE user_id = ? AND user_role = 'resident'";
        $archive_user_stmt = $conn->prepare($archive_user_query);
        $archive_user_stmt->bind_param("i", $user_id);
        $archive_user_stmt->execute();

        $archive_household_query = "UPDATE households h
                                    INNER JOIN household_members hm ON hm.household_id = h.household_id
                                    SET h.status = 'vacant'
                                    WHERE hm.user_id = ? AND hm.is_primary = 1";
        $archive_household_stmt = $conn->prepare($archive_household_query);
        $archive_household_stmt->bind_param("i", $user_id);
        $archive_household_stmt->execute();

        $conn->commit();
        header('Location: residents.php?success=archived');
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        error_log($e->getMessage());
        header('Location: residents.php?error=failed');
        exit();
    }
}

if (isset($_GET['action']) && $_GET['action'] == 'delete') {
    $user_id = (int)($_GET['user_id'] ?? 0);
    if ($user_id <= 0) {
        header('Location: residents.php?error=failed');
        exit();
    }

    $conn->begin_transaction();
    try {
        $households_query = "SELECT household_id FROM household_members WHERE user_id = ?";
        $households_stmt = $conn->prepare($households_query);
        $households_stmt->bind_param("i", $user_id);
        $households_stmt->execute();
        $households_result = $households_stmt->get_result();

        $household_ids = [];
        if ($households_result) {
            while ($h = $households_result->fetch_assoc()) {
                $household_ids[] = (int)$h['household_id'];
            }
        }

        foreach ($household_ids as $household_id) {
            $dues_check_query = "SELECT COUNT(*) AS total FROM monthly_dues WHERE household_id = ?";
            $dues_check_stmt = $conn->prepare($dues_check_query);
            $dues_check_stmt->bind_param("i", $household_id);
            $dues_check_stmt->execute();
            $dues_total = (int)($dues_check_stmt->get_result()->fetch_assoc()['total'] ?? 0);

            $bookings_check_query = "SELECT COUNT(*) AS total FROM facility_bookings WHERE household_id = ?";
            $bookings_check_stmt = $conn->prepare($bookings_check_query);
            $bookings_check_stmt->bind_param("i", $household_id);
            $bookings_check_stmt->execute();
            $bookings_total = (int)($bookings_check_stmt->get_result()->fetch_assoc()['total'] ?? 0);

            if ($dues_total > 0 || $bookings_total > 0) {
                throw new Exception('Resident has existing financial/booking records. Archive instead of delete.');
            }

            $delete_member_query = "DELETE FROM household_members WHERE household_id = ? AND user_id = ?";
            $delete_member_stmt = $conn->prepare($delete_member_query);
            $delete_member_stmt->bind_param("ii", $household_id, $user_id);
            $delete_member_stmt->execute();

            $remaining_query = "SELECT COUNT(*) AS total FROM household_members WHERE household_id = ?";
            $remaining_stmt = $conn->prepare($remaining_query);
            $remaining_stmt->bind_param("i", $household_id);
            $remaining_stmt->execute();
            $remaining_total = (int)($remaining_stmt->get_result()->fetch_assoc()['total'] ?? 0);

            if ($remaining_total === 0) {
                $delete_household_query = "DELETE FROM households WHERE household_id = ?";
                $delete_household_stmt = $conn->prepare($delete_household_query);
                $delete_household_stmt->bind_param("i", $household_id);
                $delete_household_stmt->execute();
            }
        }

        $delete_prefs_query = "DELETE FROM notification_preferences WHERE user_id = ?";
        $delete_prefs_stmt = $conn->prepare($delete_prefs_query);
        $delete_prefs_stmt->bind_param("i", $user_id);
        $delete_prefs_stmt->execute();

        $delete_user_query = "DELETE FROM users WHERE user_id = ? AND user_role = 'resident'";
        $delete_user_stmt = $conn->prepare($delete_user_query);
        $delete_user_stmt->bind_param("i", $user_id);
        $delete_user_stmt->execute();

        $conn->commit();
        header('Location: residents.php?success=deleted');
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        error_log($e->getMessage());
        header('Location: residents.php?error=has_records');
        exit();
    }
}

$conn->close();
header('Location: residents.php');
exit();
?>
