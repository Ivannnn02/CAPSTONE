<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/enrollment_form_config.php';
require_once __DIR__ . '/enrollment_fields.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

smartenroll_require_role('finance');

function labelize(string $key): string
{
    return smartenroll_field_labelize($key, $GLOBALS['smartenroll_custom_field_map'] ?? []);
}

function normalizeDateValue(string $value): string
{
    return smartenroll_normalize_date_value($value);
}

function inputTypeFor(string $column): string
{
    return smartenroll_input_type_for($column, $GLOBALS['smartenroll_custom_field_map'] ?? []);
}

function ageFromDob(string $value): string
{
    return smartenroll_age_from_dob($value);
}

function studentEditSchoolYearFromCompletionDate(string $completionDateRaw): string
{
    $ts = $completionDateRaw !== '' ? strtotime($completionDateRaw) : false;
    if ($ts === false) {
        return '';
    }

    $month = (int)date('n', $ts);
    $year = (int)date('Y', $ts);
    $startYear = ($month >= 6) ? $year : ($year - 1);
    return $startYear . '-' . ($startYear + 1);
}

function studentEditSchoolYearFromDateRange(string $startDateRaw, string $endDateRaw): string
{
    $startDate = smartenroll_normalize_date_value($startDateRaw);
    $endDate = smartenroll_normalize_date_value($endDateRaw);

    if ($startDate !== '') {
        $startYear = (int)date('Y', strtotime($startDate));
        if ($endDate !== '') {
            $endYear = (int)date('Y', strtotime($endDate));
            if ($endYear < $startYear) {
                $endYear = $startYear + 1;
            }
            return $startYear . '-' . $endYear;
        }

        return $startYear . '-' . ($startYear + 1);
    }

    if ($endDate !== '') {
        $endYear = (int)date('Y', strtotime($endDate));
        return ($endYear - 1) . '-' . $endYear;
    }

    return '';
}

function studentEditNormalizeSchoolYear(string $schoolYearRaw): string
{
    $trimmed = trim($schoolYearRaw);
    if ($trimmed === '') {
        return '';
    }

    if (preg_match_all('/\d{4}/', $trimmed, $matches) < 1 || empty($matches[0])) {
        return '';
    }

    $years = array_values(array_map('intval', $matches[0]));
    $startYear = (int)$years[0];
    if ($startYear <= 0) {
        return '';
    }

    $endYear = isset($years[1]) ? (int)$years[1] : ($startYear + 1);
    if ($endYear < $startYear) {
        $endYear = $startYear + 1;
    }

    return $startYear . '-' . $endYear;
}

function studentEditResolveSchoolYear(string $schoolYearRaw, string $completionDateRaw = ''): string
{
    $normalized = studentEditNormalizeSchoolYear($schoolYearRaw);
    if ($normalized !== '') {
        return $normalized;
    }

    return studentEditSchoolYearFromCompletionDate($completionDateRaw);
}

function studentEditSchoolYearDatePickerDefaults(string $schoolYearRaw): array
{
    $normalized = studentEditNormalizeSchoolYear($schoolYearRaw);
    if ($normalized === '') {
        return ['start_date' => '', 'end_date' => ''];
    }

    [$startYearRaw, $endYearRaw] = array_pad(explode('-', $normalized, 2), 2, '');
    $startYear = (int)$startYearRaw;
    $endYear = (int)$endYearRaw;
    if ($startYear <= 0 || $endYear <= 0) {
        return ['start_date' => '', 'end_date' => ''];
    }

    return [
        'start_date' => sprintf('%04d-06-01', $startYear),
        'end_date' => sprintf('%04d-05-31', $endYear),
    ];
}

function studentEditSections(array $columns): array
{
    return smartenroll_build_sections($columns);
}

$gradeLevels = smartenroll_get_grade_levels();
$GLOBALS['smartenroll_custom_field_map'] = smartenroll_get_field_label_map();

$student = null;
$columns = [];
$error = '';
$showPopup = isset($_GET['saved']) && $_GET['saved'] === '1';
$showBalanceWarningPopup = false;
$schoolYearStartDateValue = trim((string)($_POST['school_year_start_date'] ?? ''));
$schoolYearEndDateValue = trim((string)($_POST['school_year_end_date'] ?? ''));
$schoolYearDisplayValue = trim((string)($_POST['school_year_display'] ?? $_POST['school_year'] ?? ''));

function resolve_remaining_balance(array $payment): float
{
    $storedBalance = round((float)($payment['balance_after'] ?? 0), 2);
    $tuitionFee = round((float)($payment['tuition_fee'] ?? 0), 2);
    $amountPaid = round((float)($payment['amount_paid'] ?? 0), 2);

    if ($storedBalance > 0 || $amountPaid >= $tuitionFee) {
        return max(0.0, $storedBalance);
    }

    return max(0.0, round($tuitionFee - $amountPaid, 2));
}

function studentEditTableExists(mysqli $conn, string $tableName): bool
{
    $escapedTableName = $conn->real_escape_string($tableName);
    $result = $conn->query("SHOW TABLES LIKE '{$escapedTableName}'");
    if (!$result) {
        return false;
    }

    $exists = $result->num_rows > 0;
    $result->close();
    return $exists;
}

function studentEditSyncTuitionPaymentSchoolYear(
    mysqli $conn,
    int $enrollmentId,
    string $studentId,
    string $gradeLevel,
    string $oldSchoolYear,
    string $newSchoolYear
): int {
    if ($oldSchoolYear === $newSchoolYear || !studentEditTableExists($conn, 'tuition_payments')) {
        return 0;
    }

    $updateStmt = $conn->prepare(
        "UPDATE tuition_payments
         SET school_year = ?
         WHERE (enrollment_id = ? OR student_id = ?)
           AND COALESCE(school_year, '') = ?
           AND (
               COALESCE(grade_level, '') = ?
               OR COALESCE(grade_level, '') = ''
           )"
    );
    $updateStmt->bind_param('sisss', $newSchoolYear, $enrollmentId, $studentId, $oldSchoolYear, $gradeLevel);
    $updateStmt->execute();
    $updatedRows = $updateStmt->affected_rows;
    $updateStmt->close();

    return max(0, $updatedRows);
}

function studentEditSyncAuditLogSchoolYear(
    mysqli $conn,
    string $studentId,
    string $gradeLevel,
    string $oldSchoolYear,
    string $newSchoolYear
): int {
    if ($studentId === '' || $oldSchoolYear === $newSchoolYear || !studentEditTableExists($conn, 'audit_logs')) {
        return 0;
    }

    $studentPattern = '%"student_id":"' . $studentId . '"%';
    $selectStmt = $conn->prepare(
        "SELECT id, details_json
         FROM audit_logs
         WHERE action IN ('tuition_payment_saved', 'tuition_invoice_preview_emailed', 'tuition_receipt_emailed')
           AND details_json LIKE ?"
    );
    $selectStmt->bind_param('s', $studentPattern);
    $selectStmt->execute();
    $result = $selectStmt->get_result();

    $rowsToUpdate = [];
    while ($row = $result->fetch_assoc()) {
        $details = json_decode((string)($row['details_json'] ?? ''), true);
        if (!is_array($details)) {
            continue;
        }

        $detailStudentId = trim((string)($details['student_id'] ?? ''));
        $detailSchoolYear = trim((string)($details['school_year'] ?? ''));
        $detailGradeLevel = trim((string)($details['grade_level'] ?? ''));

        if ($detailStudentId !== $studentId || $detailSchoolYear !== $oldSchoolYear) {
            continue;
        }

        if ($gradeLevel !== '' && $detailGradeLevel !== '' && $detailGradeLevel !== $gradeLevel) {
            continue;
        }

        $details['school_year'] = $newSchoolYear;
        $rowsToUpdate[] = [
            'id' => (int)($row['id'] ?? 0),
            'details_json' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }
    $selectStmt->close();

    if ($rowsToUpdate === []) {
        return 0;
    }

    $updateStmt = $conn->prepare("UPDATE audit_logs SET details_json = ? WHERE id = ?");
    $updatedCount = 0;
    foreach ($rowsToUpdate as $row) {
        $detailsJson = (string)($row['details_json'] ?? '');
        $rowId = (int)($row['id'] ?? 0);
        if ($detailsJson === '' || $rowId <= 0) {
            continue;
        }

        $updateStmt->bind_param('si', $detailsJson, $rowId);
        $updateStmt->execute();
        if ($updateStmt->affected_rows >= 0) {
            $updatedCount++;
        }
    }
    $updateStmt->close();

    return $updatedCount;
}

function studentEditEnsureBatchAssignmentsTable(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS batch_assignments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            enrollment_id INT NOT NULL,
            student_id VARCHAR(50) NOT NULL,
            school_year VARCHAR(20) NOT NULL,
            grade_level VARCHAR(50) NOT NULL,
            batch_name VARCHAR(50) NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_student_sy_grade (student_id, school_year, grade_level),
            UNIQUE KEY uniq_enrollment_sy_grade (enrollment_id, school_year, grade_level)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $colCheck = $conn->query("SHOW COLUMNS FROM batch_assignments LIKE 'enrollment_id'");
    if ($colCheck && $colCheck->num_rows === 0) {
        $conn->query("ALTER TABLE batch_assignments ADD COLUMN enrollment_id INT NOT NULL DEFAULT 0 AFTER id");
    }
    if ($colCheck) {
        $colCheck->close();
    }

    $oldIndexCheck = $conn->query("SHOW INDEX FROM batch_assignments WHERE Key_name = 'uniq_enrollment'");
    if ($oldIndexCheck && $oldIndexCheck->num_rows > 0) {
        $conn->query("ALTER TABLE batch_assignments DROP INDEX uniq_enrollment");
    }
    if ($oldIndexCheck) {
        $oldIndexCheck->close();
    }

    $comboIndexCheck = $conn->query("SHOW INDEX FROM batch_assignments WHERE Key_name = 'uniq_enrollment_sy_grade'");
    if ($comboIndexCheck && $comboIndexCheck->num_rows === 0) {
        $conn->query("ALTER TABLE batch_assignments ADD UNIQUE KEY uniq_enrollment_sy_grade (enrollment_id, school_year, grade_level)");
    }
    if ($comboIndexCheck) {
        $comboIndexCheck->close();
    }
}

function studentEditPreserveBatchAssignmentRecord(
    mysqli $conn,
    int $enrollmentId,
    string $studentId,
    string $schoolYear,
    string $gradeLevel
): void {
    if ($enrollmentId <= 0 || $schoolYear === '' || $gradeLevel === '') {
        return;
    }

    studentEditEnsureBatchAssignmentsTable($conn);

    $insertStmt = $conn->prepare(
        "INSERT INTO batch_assignments (enrollment_id, student_id, school_year, grade_level, batch_name)
         VALUES (?, ?, ?, ?, '')
         ON DUPLICATE KEY UPDATE
            student_id = VALUES(student_id)"
    );
    $insertStmt->bind_param('isss', $enrollmentId, $studentId, $schoolYear, $gradeLevel);
    $insertStmt->execute();
    $insertStmt->close();
}

function studentEditSyncBatchHistory(
    mysqli $conn,
    int $enrollmentId,
    string $studentId,
    string $oldSchoolYear,
    string $oldGradeLevel,
    string $newSchoolYear,
    string $newGradeLevel
): void {
    studentEditPreserveBatchAssignmentRecord($conn, $enrollmentId, $studentId, $oldSchoolYear, $oldGradeLevel);

    if ($oldSchoolYear === $newSchoolYear && $oldGradeLevel === $newGradeLevel) {
        return;
    }

    studentEditPreserveBatchAssignmentRecord($conn, $enrollmentId, $studentId, $newSchoolYear, $newGradeLevel);
}

try {
    $conn = new mysqli('127.0.0.1', 'root', '', 'smartenroll');
    $conn->set_charset('utf8mb4');
    smartenroll_ensure_student_status_column($conn);

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        throw new RuntimeException('Invalid student ID.');
    }

    $colRes = $conn->query("SHOW COLUMNS FROM `enrollments`");
    while ($row = $colRes->fetch_assoc()) {
        $columns[] = $row['Field'];
    }

    $skip = ['id', 'created_at'];
    $readOnly = ['student_id', 'school_year', 'created_at'];

    $stmt = $conn->prepare("SELECT * FROM `enrollments` WHERE `id` = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();

    if (!$student) {
        throw new RuntimeException('Student record not found.');
    }

    $completionDateContext = trim((string)($_POST['completion_date'] ?? ($student['completion_date'] ?? '')));
    $storedSchoolYearValue = trim((string)($student['school_year'] ?? ''));
    $resolvedSchoolYearValue = studentEditResolveSchoolYear($storedSchoolYearValue, $completionDateContext);
    if ($schoolYearDisplayValue === '') {
        $computedPostedSchoolYear = studentEditSchoolYearFromDateRange($schoolYearStartDateValue, $schoolYearEndDateValue);
        if ($computedPostedSchoolYear !== '') {
            $schoolYearDisplayValue = $computedPostedSchoolYear;
        }
    }

    if ($schoolYearDisplayValue === '') {
        $schoolYearDisplayValue = $resolvedSchoolYearValue;
    }

    if ($schoolYearStartDateValue === '' || $schoolYearEndDateValue === '') {
        $defaultDateRange = studentEditSchoolYearDatePickerDefaults($schoolYearDisplayValue !== '' ? $schoolYearDisplayValue : $resolvedSchoolYearValue);
        if ($schoolYearStartDateValue === '') {
            $schoolYearStartDateValue = $defaultDateRange['start_date'];
        }
        if ($schoolYearEndDateValue === '') {
            $schoolYearEndDateValue = $defaultDateRange['end_date'];
        }
    }

    $computedSchoolYearDisplay = studentEditSchoolYearFromDateRange($schoolYearStartDateValue, $schoolYearEndDateValue);
    if ($computedSchoolYearDisplay !== '') {
        $schoolYearDisplayValue = $computedSchoolYearDisplay;
    }

    // Get outstanding balance for this student's CURRENT grade level
    $outstandingBalance = 0;
    $studentId = trim((string)($student['student_id'] ?? ''));
    $enrollmentId = (int)($student['id'] ?? 0);
    $currentGradeLevel = trim((string)($student['grade_level'] ?? ''));
    $currentSchoolYear = trim((string)($student['school_year'] ?? ''));
    $configuredCurrentTuitionFee = $currentGradeLevel !== ''
        ? round((float)(smartenroll_resolve_grade_tuition_fee($currentGradeLevel, $conn) ?? 0), 2)
        : 0.0;

    if ($studentId !== '' || $enrollmentId > 0) {
        // Calculate balance for the student's current grade level and school year only.
        $balanceStmt = $conn->prepare(
            "SELECT 
                payment_items,
                amount_paid,
                tuition_fee
             FROM tuition_payments
             WHERE (student_id = ? OR enrollment_id = ?)
               AND COALESCE(grade_level, '') = ?
               AND COALESCE(school_year, '') = ?"
        );
        $balanceStmt->bind_param('siss', $studentId, $enrollmentId, $currentGradeLevel, $currentSchoolYear);
        $balanceStmt->execute();
        $balanceResult = $balanceStmt->get_result();
        $balanceStmt->close();

        $totalPaid = 0.0;
        $storedTuitionFee = 0.0;
        while ($paymentRow = $balanceResult->fetch_assoc()) {
            $totalPaid += smartenroll_payment_items_credit_total_from_json(
                (string)($paymentRow['payment_items'] ?? ''),
                (float)($paymentRow['amount_paid'] ?? 0)
            );
            $storedTuitionFee = max($storedTuitionFee, round((float)($paymentRow['tuition_fee'] ?? 0), 2));
        }
        $totalPaid = round($totalPaid, 2);
        $tuitionFee = $configuredCurrentTuitionFee > 0 ? $configuredCurrentTuitionFee : $storedTuitionFee;
        $outstandingBalance = max(0, round($tuitionFee - $totalPaid, 2));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = [];
        foreach ($columns as $col) {
            if (in_array($col, $skip, true)) {
                continue;
            }
            if (isset($_POST[$col])) {
                $data[$col] = trim((string)$_POST[$col]);
            }
        }

        // Check if grade level is being changed
        $newGradeLevel = trim((string)($data['grade_level'] ?? ''));
        $currentGradeLevel = trim((string)($student['grade_level'] ?? ''));
        $gradeIsChanging = $newGradeLevel !== '' && $newGradeLevel !== $currentGradeLevel;
        $currentSchoolYearValue = trim((string)($student['school_year'] ?? ''));

        // If grade level is changing, check if student is fully paid
        if ($gradeIsChanging) {
            if ($outstandingBalance > 0) {
                throw new RuntimeException('Cannot change grade level. The student must be fully paid in the current grade level first. Outstanding balance: PHP ' . number_format($outstandingBalance, 2) . '.');
            }
        }

        $completionDateRaw = trim((string)($data['completion_date'] ?? ''));
        $schoolYearStartDate = trim((string)($_POST['school_year_start_date'] ?? ''));
        $schoolYearEndDate = trim((string)($_POST['school_year_end_date'] ?? ''));
        $schoolYearDisplayRaw = trim((string)($_POST['school_year_display'] ?? ''));
        $schoolYearHiddenRaw = trim((string)($data['school_year'] ?? ''));
        $schoolYearRaw = studentEditNormalizeSchoolYear($schoolYearDisplayRaw);

        if ($schoolYearRaw === '') {
            $schoolYearRaw = studentEditSchoolYearFromDateRange($schoolYearStartDate, $schoolYearEndDate);
        }

        if ($schoolYearRaw === '') {
            $schoolYearRaw = $schoolYearHiddenRaw;
        }

        $data['school_year'] = studentEditResolveSchoolYear($schoolYearRaw, $completionDateRaw);
        $newSchoolYearValue = trim((string)($data['school_year'] ?? ''));
        $schoolYearChanged = $newSchoolYearValue !== $currentSchoolYearValue;

        if (array_key_exists('dob', $data)) {
            $data['age'] = ageFromDob($data['dob']);
        }

        if (($data['medication'] ?? '') !== 'yes') {
            $data['medication_details'] = '';
        }

        if (!empty($data)) {
            $conn->begin_transaction();
            try {
                $set = [];
                $types = '';
                $values = [];
                foreach ($data as $col => $val) {
                    $set[] = "`$col` = ?";
                    $types .= 's';
                    $values[] = $val;
                }
                $types .= 'i';
                $values[] = $id;

                $sql = "UPDATE `enrollments` SET " . implode(', ', $set) . " WHERE `id` = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param($types, ...$values);
                $stmt->execute();
                $stmt->close();

                $studentIdValue = trim((string)($student['student_id'] ?? ''));
                $effectiveGradeLevel = $newGradeLevel !== '' ? $newGradeLevel : $currentGradeLevel;

                if ($schoolYearChanged && !$gradeIsChanging) {
                    $updatedPaymentRows = studentEditSyncTuitionPaymentSchoolYear(
                        $conn,
                        $enrollmentId,
                        $studentIdValue,
                        $currentGradeLevel,
                        $currentSchoolYearValue,
                        $newSchoolYearValue
                    );

                    if ($updatedPaymentRows > 0) {
                        smartenroll_sync_tuition_payment_totals($conn);
                    }

                    studentEditSyncAuditLogSchoolYear(
                        $conn,
                        $studentIdValue,
                        $currentGradeLevel,
                        $currentSchoolYearValue,
                        $newSchoolYearValue
                    );
                }

                if ($schoolYearChanged || $gradeIsChanging) {
                    studentEditSyncBatchHistory(
                        $conn,
                        $enrollmentId,
                        $studentIdValue,
                        $currentSchoolYearValue,
                        $currentGradeLevel,
                        $newSchoolYearValue,
                        $effectiveGradeLevel
                    );
                }

                $conn->commit();
            } catch (Throwable $syncError) {
                $conn->rollback();
                throw $syncError;
            }

            header('Location: student_edit.php?id=' . $id . '&saved=1');
            exit;
        }
    }

    if (!$student) {
        throw new RuntimeException('Student record not found.');
    }

    $sectionMap = studentEditSections($columns);
} catch (Throwable $e) {
    $error = $e->getMessage();
    $showBalanceWarningPopup = strpos($error, 'Cannot change grade level') === 0;
    $skip = ['id', 'created_at'];
    $readOnly = ['student_id', 'school_year', 'created_at'];
    $sectionMap = $student && !empty($columns) ? studentEditSections($columns) : [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SMARTENROLL | Edit Student</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/student_edit.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="dashboard-page dashboard-white-page">

<main class="dashboard-main">
    <div class="dashboard-header student-header">
        <div class="student-header-left">
            <a href="student_list.php" class="dashboard-link back-left">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div class="student-header-title">
                <h1>Edit Student</h1>
                <p>Update all saved enrollment form details for this student.</p>
            </div>
        </div>
    </div>

    <div class="student-edit-card">
        <?php if ($error): ?>
            <div class="student-error">
                <strong><?php echo strpos($error, 'Cannot change grade level') === 0 ? 'Cannot Change Grade Level' : 'Unable to load student.'; ?></strong>
                <p><?php echo htmlspecialchars($error); ?></p>
                <?php if (strpos($error, 'Cannot change grade level') === 0): ?>
                    <p style="margin-top: 12px; font-size: 14px; color: #666;">
                        Please navigate to <a href="tuition_receipt_details.php?student_id=<?php echo urlencode((string)($student['student_id'] ?? '')); ?>" style="color: #3b82f6; text-decoration: none;">Payment Record</a> 
                        to settle the remaining balance, then you can change the grade level.
                    </p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php if ($showPopup): ?>
                <div id="successPopup" class="popup-overlay">
                    <div class="popup-box">
                        <div class="popup-icon success-icon" id="successIcon">
                            <img src="assets/logo.png" id="successLogo" alt="Logo">
                            <i class="fas fa-check" id="successCheck"></i>
                        </div>

                        <h2>Changes Saved!</h2>
                        <p>The student record was updated successfully.</p>
                        <button class="popup-btn" id="closeSuccess">OK</button>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($outstandingBalance > 0): ?>
                <div id="balanceWarningPopup" class="popup-overlay" style="display: <?php echo $showBalanceWarningPopup ? 'flex' : 'none'; ?>;">
                    <div class="popup-box">
                        <div class="popup-icon warning-icon" id="warningIcon">
                            <img src="assets/logo.png" id="warningLogo" alt="Logo">
                            <i class="fas fa-exclamation-triangle" id="warningTriangle"></i>
                        </div>

                        <h2>Outstanding Balance</h2>
                        <p>This student has a remaining balance of <strong>PHP <?php echo number_format($outstandingBalance, 2); ?></strong> on their tuition account.</p>
                        <p style="margin-top: 16px; font-size: 14px; color: #666;">To change the grade level and update the tuition rate, you must first settle this balance.</p>
                        
                        <div style="display: flex; gap: 12px; margin-top: 24px;">
                            <button type="button" class="popup-btn" id="cancelGradeChange" style="background: #d0d5dd; color: #344054; flex: 1;">Cancel</button>
                            <a href="tuition_receipt_details.php?student_id=<?php echo urlencode((string)$student['student_id']); ?>" class="popup-btn" style="background: #3b82f6; color: white; text-decoration: none; flex: 1; display: flex; align-items: center; justify-content: center;">Go to Payment</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <form method="post" id="studentEditForm">
                <input type="hidden" name="grade_change_attempted" id="gradeChangeAttempted" value="0">
                <input type="hidden" id="remainingBalance" value="<?php echo htmlspecialchars((string)$outstandingBalance, ENT_QUOTES, 'UTF-8'); ?>">
                <?php foreach ($sectionMap as $sectionTitle => $fields): ?>
                    <div class="detail-section">
                        <h3 class="detail-section-title"><?php echo htmlspecialchars($sectionTitle); ?></h3>
                        <div class="student-edit-grid">
                                <?php foreach ($fields as $col): ?>
                                <?php if (in_array($col, $skip, true)) { continue; } ?>
                                <label class="edit-item">
                                    <span class="detail-label"><?php echo htmlspecialchars(labelize($col)); ?></span>
                                    <?php $val = (string)($student[$col] ?? ''); ?>
                                    <?php $customField = $GLOBALS['smartenroll_custom_field_map'][$col] ?? null; ?>
                                    <?php if ($customField !== null && inputTypeFor($col) === 'select'): ?>
                                        <select name="<?php echo htmlspecialchars($col); ?>">
                                            <option value="">Select</option>
                                            <?php foreach (smartenroll_custom_field_options($customField) as $option): ?>
                                                <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $val === $option ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($option); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif ($customField !== null && inputTypeFor($col) === 'textarea'): ?>
                                        <textarea
                                            name="<?php echo htmlspecialchars($col); ?>"
                                            rows="4"
                                        ><?php echo htmlspecialchars($val); ?></textarea>
                                    <?php elseif ($col === 'learner_ext'): ?>
                                        <select name="learner_ext">
                                            <?php
                                                $extOptions = ['' => 'None', 'Jr' => 'Jr.', 'Sr' => 'Sr.', 'II' => 'II', 'III' => 'III'];
                                                foreach ($extOptions as $optVal => $optLabel):
                                                    $selected = ($val === $optVal) ? 'selected' : '';
                                            ?>
                                                <option value="<?php echo htmlspecialchars($optVal); ?>" <?php echo $selected; ?>>
                                                    <?php echo htmlspecialchars($optLabel); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif ($col === 'sex'): ?>
                                        <select name="sex">
                                            <?php
                                                $sexOptions = ['' => 'Select', 'Male' => 'Male', 'Female' => 'Female'];
                                                foreach ($sexOptions as $optVal => $optLabel):
                                                    $selected = ($val === $optVal) ? 'selected' : '';
                                            ?>
                                                <option value="<?php echo htmlspecialchars($optVal); ?>" <?php echo $selected; ?>>
                                                    <?php echo htmlspecialchars($optLabel); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif ($col === 'student_status'): ?>
                                        <select name="student_status">
                                            <option value="">Select Status</option>
                                            <?php foreach (smartenroll_student_status_options() as $optVal): ?>
                                                <option value="<?php echo htmlspecialchars($optVal); ?>" <?php echo $val === $optVal ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($optVal); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif ($col === 'guardian_type'): ?>
                                        <select name="guardian_type">
                                            <?php
                                                $gOptions = ['' => 'Select', 'other' => 'Other', 'mother' => 'Mother', 'father' => 'Father'];
                                                foreach ($gOptions as $optVal => $optLabel):
                                                    $selected = ($val === $optVal) ? 'selected' : '';
                                            ?>
                                                <option value="<?php echo htmlspecialchars($optVal); ?>" <?php echo $selected; ?>>
                                                    <?php echo htmlspecialchars($optLabel); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif ($col === 'grade_level'): ?>
                                        <select name="grade_level">
                                            <?php foreach ($gradeLevels as $gradeLevel): ?>
                                                <?php
                                                    $optVal = (string)$gradeLevel['grade_key'];
                                                    $optLabel = (string)$gradeLevel['grade_label'];
                                                    $selected = ($val === $optVal) ? 'selected' : '';
                                                ?>
                                                <option value="<?php echo htmlspecialchars($optVal); ?>" <?php echo $selected; ?>>
                                                    <?php echo htmlspecialchars($optLabel); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif ($col === 'school_year'): ?>
                                        <div class="school-year-builder">
                                            <input
                                                type="text"
                                                name="school_year_display"
                                                id="schoolYearInput"
                                                value="<?php echo htmlspecialchars($schoolYearDisplayValue); ?>"
                                                placeholder="2025-2026"
                                                pattern="\d{4}-\d{4}"
                                            >
                                            <input
                                                type="hidden"
                                                name="school_year"
                                                id="schoolYearHiddenInput"
                                                value="<?php echo htmlspecialchars($schoolYearDisplayValue); ?>"
                                            >
                                            <div class="school-year-config-grid">
                                                <div class="school-year-config-item">
                                                    <span class="school-year-config-label">Start Month</span>
                                                    <input
                                                        type="date"
                                                        name="school_year_start_date"
                                                        id="schoolYearStartDate"
                                                        value="<?php echo htmlspecialchars(normalizeDateValue($schoolYearStartDateValue)); ?>"
                                                    >
                                                </div>
                                                <div class="school-year-config-item">
                                                    <span class="school-year-config-label">End Month</span>
                                                    <input
                                                        type="date"
                                                        name="school_year_end_date"
                                                        id="schoolYearEndDate"
                                                        value="<?php echo htmlspecialchars(normalizeDateValue($schoolYearEndDateValue)); ?>"
                                                    >
                                                </div>
                                            </div>
                                            <small class="school-year-help">You can type the school year directly or use the start and end dates below.</small>
                                        </div>
                                    <?php elseif (in_array($col, ['guardian_lname', 'guardian_fname', 'guardian_mname', 'guardian_occ', 'guardian_contact'], true)): ?>
                                        <input
                                            type="<?php echo htmlspecialchars(inputTypeFor($col)); ?>"
                                            name="<?php echo htmlspecialchars($col); ?>"
                                            value="<?php echo htmlspecialchars($val); ?>"
                                            data-guardian-field="<?php echo htmlspecialchars($col); ?>"
                                        >
                                    <?php elseif ($col === 'medication'): ?>
                                        <select name="medication">
                                            <?php
                                                $mOptions = ['' => 'Select', 'yes' => 'Yes', 'no' => 'No'];
                                                foreach ($mOptions as $optVal => $optLabel):
                                                    $selected = ($val === $optVal) ? 'selected' : '';
                                            ?>
                                                <option value="<?php echo htmlspecialchars($optVal); ?>" <?php echo $selected; ?>>
                                                    <?php echo htmlspecialchars($optLabel); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif (in_array($col, ['dob', 'completion_date'], true)): ?>
                                        <input
                                            type="date"
                                            name="<?php echo htmlspecialchars($col); ?>"
                                            value="<?php echo htmlspecialchars(normalizeDateValue($val)); ?>"
                                        >
                                    <?php elseif ($col === 'age'): ?>
                                        <input
                                            type="number"
                                            name="age"
                                            value="<?php echo htmlspecialchars($val); ?>"
                                            readonly
                                        >
                                    <?php elseif (in_array($col, ['special_needs', 'medication_details'], true)): ?>
                                        <textarea
                                            name="<?php echo htmlspecialchars($col); ?>"
                                            rows="4"
                                        ><?php echo htmlspecialchars($val); ?></textarea>
                                    <?php else: ?>
                                        <input
                                            type="<?php echo htmlspecialchars(inputTypeFor($col)); ?>"
                                            name="<?php echo htmlspecialchars($col); ?>"
                                            value="<?php echo htmlspecialchars($val); ?>"
                                            <?php echo in_array($col, $readOnly, true) ? 'readonly' : ''; ?>
                                        >
                                    <?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="edit-actions">
                    <button type="submit" class="edit-save">Save Changes</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</main>

<?php $studentEditJsVersion = @filemtime(__DIR__ . '/js/student_edit.js') ?: time(); ?>
<script src="js/student_edit.js?v=<?php echo urlencode((string)$studentEditJsVersion); ?>"></script>
</body>
</html>
