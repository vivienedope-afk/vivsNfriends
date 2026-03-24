<?php
require_once('../auth/session_check.php');
require_once('../config/database.php');
requireAdmin();

$conn = getDBConnection();

$type = $_GET['type'] ?? 'monthly_collection';
$year = (int)($_GET['year'] ?? date('Y'));

function csv_out($filename, $headers, $rows) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit();
}

if ($type === 'monthly_collection') {
    $sql = "SELECT md.due_month,
              SUM(CASE WHEN md.status = 'paid' THEN md.amount ELSE 0 END) AS collected,
              SUM(CASE WHEN md.status IN ('unpaid', 'overdue') THEN md.amount ELSE 0 END) AS outstanding,
              SUM(CASE WHEN md.status = 'paid' THEN 1 ELSE 0 END) AS paid_count,
              COUNT(*) AS total_count
            FROM monthly_dues md
            WHERE md.due_year = ?
            GROUP BY md.due_month
            ORDER BY FIELD(md.due_month, 'January','February','March','April','May','June','July','August','September','October','November','December')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $year);
    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $rows[] = [
          $r['due_month'],
          number_format((float)$r['collected'], 2, '.', ''),
          number_format((float)$r['outstanding'], 2, '.', ''),
          (int)$r['paid_count'],
          (int)$r['total_count']
        ];
    }
    csv_out("monthly_collection_$year.csv", ['Month', 'Collected', 'Outstanding', 'Paid Records', 'Total Records'], $rows);
}

if ($type === 'facility_usage') {
    $sql = "SELECT fb.facility_name,
              COUNT(*) AS total_bookings,
              SUM(CASE WHEN fb.status = 'approved' THEN 1 ELSE 0 END) AS approved_count,
              SUM(CASE WHEN fb.status = 'rejected' THEN 1 ELSE 0 END) AS rejected_count,
              SUM(CASE WHEN fb.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_count,
              COALESCE(SUM(CASE WHEN fb.status = 'approved' THEN fb.booking_fee ELSE 0 END), 0) AS total_fees
            FROM facility_bookings fb
            WHERE YEAR(fb.booking_date) = ?
            GROUP BY fb.facility_name
            ORDER BY total_bookings DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $year);
    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $rows[] = [
          $r['facility_name'],
          (int)$r['total_bookings'],
          (int)$r['approved_count'],
          (int)$r['rejected_count'],
          (int)$r['cancelled_count'],
          number_format((float)$r['total_fees'], 2, '.', '')
        ];
    }
    csv_out("facility_usage_$year.csv", ['Facility', 'Total Bookings', 'Approved', 'Rejected', 'Cancelled', 'Total Fees'], $rows);
}

if ($type === 'outstanding') {
    $sql = "SELECT h.unit_number,
              CONCAT(u.first_name, ' ', u.last_name) AS owner_name,
              COUNT(md.dues_id) AS unpaid_count,
              COALESCE(SUM(md.amount), 0) AS outstanding_amount
            FROM monthly_dues md
            INNER JOIN households h ON md.household_id = h.household_id
            LEFT JOIN users u ON h.owner_id = u.user_id
            WHERE md.status IN ('unpaid', 'overdue')
            GROUP BY h.household_id, h.unit_number, owner_name
            ORDER BY outstanding_amount DESC";
    $res = $conn->query($sql);

    $rows = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = [
              $r['unit_number'],
              $r['owner_name'] ?? 'N/A',
              (int)$r['unpaid_count'],
              number_format((float)$r['outstanding_amount'], 2, '.', '')
            ];
        }
    }
    csv_out('outstanding_balances.csv', ['Unit', 'Resident', 'Unpaid Months', 'Outstanding Amount'], $rows);
}

header('Location: reports.php?error=invalid_export');
exit();
