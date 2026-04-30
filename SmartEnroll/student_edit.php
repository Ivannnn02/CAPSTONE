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

    // Get outstanding balance for this student's CURRENT grade level
    $outstandingBalance = 0;
    $studentId = trim((string)($student['student_id'] ?? ''));
    $enrollmentId = (int)($student['id'] ?? 0);
    $currentGradeLevel = trim((string)($student['grade_level'] ?? ''));

    if ($studentId !== '' || $enrollmentId > 0) {
        // Calculate balance as: tuition_fee - SUM(amount_paid) for all payments in current grade level
        $balanceStmt = $conn->prepare(
            "SELECT 
                COALESCE(SUM(amount_paid), 0) AS total_paid,
                COALESCE(MAX(tuition_fee), 0) AS tuition_fee
             FROM tuition_payments
             WHERE (student_id = ? OR enrollment_id = ?) AND grade_level = ?"
        );
        $balanceStmt->bind_param('sis', $studentId, $enrollmentId, $currentGradeLevel);
        $balanceStmt->execute();
        $balanceResult = $balanceStmt->get_result()->fetch_assoc();
        $balanceStmt->close();

        if (!empty($balanceResult)) {
            $totalPaid = round((float)($balanceResult['total_paid'] ?? 0), 2);
            $tuitionFee = round((float)($balanceResult['tuition_fee'] ?? 0), 2);
            $outstandingBalance = max(0, round($tuitionFee - $totalPaid, 2));
        } else {
            // For students with no payments for current grade level, set balance to tuition fee
            $gradeKey = trim((string)($student['grade_level'] ?? ''));
            $tuitionFeeMap = [];
            foreach ($gradeLevels as $gradeLevel) {
                $tuitionFeeMap[(string)($gradeLevel['grade_key'] ?? '')] = round((float)($gradeLevel['tuition_fee'] ?? 0), 2);
            }
            if ($gradeKey !== '' && isset($tuitionFeeMap[$gradeKey])) {
                $outstandingBalance = $tuitionFeeMap[$gradeKey];
            }
        }
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

        // If grade level is changing, check if student is fully paid
        if ($gradeIsChanging) {
            if ($outstandingBalance > 0) {
                throw new RuntimeException('Cannot change grade level. This student has an outstanding balance of PHP ' . number_format($outstandingBalance, 2) . '. Please settle the balance before changing the grade level.');
            }
        }

        if (array_key_exists('completion_date', $data)) {
            $completionDateRaw = $data['completion_date'];
            $ts = $completionDateRaw !== '' ? strtotime($completionDateRaw) : false;
            if ($ts !== false) {
                $month = (int)date('n', $ts);
                $year = (int)date('Y', $ts);
                $startYear = ($month >= 6) ? $year : ($year - 1);
                $data['school_year'] = $startYear . '-' . ($startYear + 1);
            } else {
                $data['school_year'] = '';
            }
        }

        if (array_key_exists('dob', $data)) {
            $data['age'] = ageFromDob($data['dob']);
        }

        if (($data['medication'] ?? '') !== 'yes') {
            $data['medication_details'] = '';
        }

        if (!empty($data)) {
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

            // Handle grade level change: create new transaction for the new grade level
            $newGradeLevel = trim((string)($data['grade_level'] ?? ''));
            $currentGradeLevel = trim((string)($student['grade_level'] ?? ''));
            if ($newGradeLevel !== '' && $newGradeLevel !== $currentGradeLevel) {
                // Get new annual program total (tuition fee) for the new grade level
                $newTuitionFee = 0;
                foreach ($gradeLevels as $gradeLevel) {
                    if ((string)$gradeLevel['grade_key'] === $newGradeLevel) {
                        $newTuitionFee = round((float)($gradeLevel['tuition_fee'] ?? 0), 2);
                        break;
                    }
                }

                if ($newTuitionFee > 0) {
                    // Get school year from enrollment record
                    $currentSchoolYear = trim((string)($student['school_year'] ?? ''));

                    // Generate a new receipt number for the grade level change
                    $receiptNo = 'GLC-' . date('Ymd') . '-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT);

                    // Create new payment transaction for the new grade level
                    $insertStmt = $conn->prepare(
                        "INSERT INTO tuition_payments (
                            enrollment_id, student_id, email, school_year, grade_level,
                            payment_date, amount_paid, tuition_fee, balance_after,
                            receipt_no, payment_note, payment_status
                        ) VALUES (?, ?, ?, ?, ?, CURDATE(), 0, ?, ?, ?, ?, 'ready')"
                    );

                    $studentEmail = trim((string)($student['email'] ?? ''));
                    $paymentNote = "Grade level changed from {$currentGradeLevel} to {$newGradeLevel}";

                    $insertStmt->bind_param(
                        'issssddss',
                        $enrollmentId,           // enrollment_id
                        $studentId,             // student_id
                        $studentEmail,          // email
                        $currentSchoolYear,     // school_year
                        $newGradeLevel,         // grade_level
                        $newTuitionFee,         // tuition_fee
                        $newTuitionFee,         // balance_after (full amount owed)
                        $receiptNo,             // receipt_no
                        $paymentNote            // payment_note
                    );
                    $insertStmt->execute();
                    $insertStmt->close();
                }
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

<script src="js/student_edit.js"></script>
</body>
</html>
