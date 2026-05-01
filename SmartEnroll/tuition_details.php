<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/enrollment_form_config.php';
smartenroll_auth_start_session();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$currentUser = smartenroll_require_role('finance');

$student = null;
$error = '';

function format_name(array $row): string
{
    $m = trim((string)($row['learner_mname'] ?? ''));
    $mi = $m !== '' ? strtoupper(mb_substr($m, 0, 1)) . '.' : '';
    $full = trim(
        ($row['learner_lname'] ?? '') . ', ' .
        ($row['learner_fname'] ?? '') . ' ' . $mi
    );
    return trim(preg_replace('/\s+/', ' ', $full), ' ,');
}

function format_money(?float $amount): string
{
    return 'PHP ' . number_format((float)$amount, 2);
}

function build_grade_history_key(?string $gradeLevel, ?string $schoolYear): string
{
    $grade = trim((string)$gradeLevel);
    $schoolYearValue = trim((string)$schoolYear);

    return ($grade !== '' ? $grade : 'Unknown') . '|' . ($schoolYearValue !== '' ? $schoolYearValue : 'N/A');
}

function decode_saved_payment_items(?string $rawJson, float $amountPaid): array
{
    $decoded = json_decode((string)$rawJson, true);
    if (!is_array($decoded) || $decoded === []) {
        return [[
            'option' => 'Tuition Fee',
            'label' => 'Tuition Fee',
            'amount' => round($amountPaid, 2),
        ]];
    }

    $items = [];
    foreach ($decoded as $row) {
        if (!is_array($row)) {
            continue;
        }

        $label = trim((string)($row['label'] ?? $row['option'] ?? ''));
        $option = trim((string)($row['option'] ?? ($label !== '' ? $label : 'Other')));
        $amount = round((float)($row['amount'] ?? 0), 2);
        if ($label === '' || $amount <= 0) {
            continue;
        }

        $items[] = [
            'option' => $option,
            'label' => $label,
            'amount' => $amount,
        ];
    }

    if ($items === []) {
        $items[] = [
            'option' => 'Tuition Fee',
            'label' => 'Tuition Fee',
            'amount' => round($amountPaid, 2),
        ];
    }

    return $items;
}

function summarize_payment_items(array $items, string $emptyLabel = 'N/A'): string
{
    $labels = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $label = trim((string)($item['label'] ?? $item['option'] ?? ''));
        if ($label !== '') {
            $labels[] = $label;
        }
    }

    $labels = array_values(array_unique($labels));
    return $labels !== [] ? implode(', ', $labels) : $emptyLabel;
}

try {
    $conn = new mysqli('127.0.0.1', 'root', '', 'smartenroll');
    $conn->set_charset('utf8mb4');

    $studentId = trim((string)($_GET['student_id'] ?? $_POST['student_id'] ?? ''));
    if ($studentId === '') {
        throw new RuntimeException('Missing student ID.');
    }

    $stmt = $conn->prepare("SELECT id, student_id, learner_lname, learner_fname, learner_mname, grade_level, school_year, completion_date, email FROM enrollments WHERE student_id = ? LIMIT 1");
    $stmt->bind_param('s', $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();

    if (!$student) {
        throw new RuntimeException('Student not found.');
    }

} catch (Throwable $e) {
    $error = $e->getMessage();
}

$studentName = $student ? format_name($student) : '';
$schoolYear = $student['school_year'] ?? '';
$completionDate = $student['completion_date'] ?? '';
$gradeLevel = $student['grade_level'] ?? '';

$totalTuition = isset($conn) && $conn instanceof mysqli
    ? (smartenroll_resolve_grade_tuition_fee($gradeLevel, $conn) ?? 0.0)
    : (smartenroll_resolve_grade_tuition_fee($gradeLevel) ?? 0.0);
$amountPaid = 0.0;
$creditedAmountPaid = 0.0;

if ($student && isset($conn) && $conn instanceof mysqli) {
    $selectedEnrollmentId = (int)($student['id'] ?? 0);
    $selectedSchoolYear = trim((string)($student['school_year'] ?? ''));

    $tableCheck = $conn->query("SHOW TABLES LIKE 'tuition_payments'");
    $hasPaymentsTable = $tableCheck && $tableCheck->num_rows > 0;
    if ($tableCheck) {
        $tableCheck->close();
    }

    if ($hasPaymentsTable && $selectedEnrollmentId > 0) {
        $selectedStudentId = trim((string)($student['student_id'] ?? ''));
        $currentGradeLevel = trim((string)($student['grade_level'] ?? ''));
        $paymentStmt = $conn->prepare(
            "SELECT payment_items, amount_paid, tuition_fee
             FROM tuition_payments
             WHERE (enrollment_id = ? OR student_id = ?)
               AND COALESCE(grade_level, '') = ?
               AND COALESCE(school_year, '') = ?
               AND amount_paid > 0
             ORDER BY payment_date DESC, id DESC"
        );
        $paymentStmt->bind_param('iiss', $selectedEnrollmentId, $selectedStudentId, $currentGradeLevel, $selectedSchoolYear);
        $paymentStmt->execute();
        $paymentResult = $paymentStmt->get_result();
        $paymentStmt->close();

        $resolvedProgramTotal = 0.0;
        while ($paymentRow = $paymentResult->fetch_assoc()) {
            if ($resolvedProgramTotal <= 0) {
                $resolvedProgramTotal = round((float)($paymentRow['tuition_fee'] ?? 0), 2);
            }
            $amountPaid += (float)($paymentRow['amount_paid'] ?? 0);
            $creditedAmountPaid += smartenroll_payment_items_credit_total_from_json(
                (string)($paymentRow['payment_items'] ?? ''),
                (float)($paymentRow['amount_paid'] ?? 0)
            );
        }
        if ($resolvedProgramTotal > 0) {
            $totalTuition = $resolvedProgramTotal;
        }
        $amountPaid = round($amountPaid, 2);
        $creditedAmountPaid = round($creditedAmountPaid, 2);
    }
}

$remainingBalance = max(0, round($totalTuition - $creditedAmountPaid, 2));

// Get grade-level history, saved invoices, and sent email history per grade.
$gradeLevelHistory = [];
$detailedPaymentHistory = [];
$savedInvoiceHistoryByGrade = [];
$sentEmailHistoryByGrade = [];
$currentGradeHistoryKey = '';
if ($student && isset($conn) && $conn instanceof mysqli) {
    $selectedEnrollmentId = (int)($student['id'] ?? 0);
    $selectedStudentId = trim((string)($student['student_id'] ?? ''));
    $selectedCurrentGradeLevel = trim((string)($student['grade_level'] ?? ''));
    $selectedCurrentSchoolYear = trim((string)($student['school_year'] ?? ''));
    $gradeLevelHistoryMap = [];

    $ensureGradeHistoryEntry = static function (string $gradeLevelValue, string $schoolYearValue, float $annualTotal = 0.0) use (&$gradeLevelHistoryMap, $conn): string {
        $gradeLevelLabel = trim($gradeLevelValue) !== '' ? trim($gradeLevelValue) : 'Unknown';
        $schoolYearLabel = trim($schoolYearValue) !== '' ? trim($schoolYearValue) : 'N/A';
        $gradeKeyValue = build_grade_history_key($gradeLevelValue, $schoolYearValue);
        $resolvedAnnualTotal = round($annualTotal, 2);

        if ($resolvedAnnualTotal <= 0 && $gradeLevelLabel !== 'Unknown') {
            $resolvedAnnualTotal = round((float)(smartenroll_resolve_grade_tuition_fee($gradeLevelLabel, $conn) ?? 0.0), 2);
        }

        if (!isset($gradeLevelHistoryMap[$gradeKeyValue])) {
            $gradeLevelHistoryMap[$gradeKeyValue] = [
                'grade_key' => $gradeKeyValue,
                'grade_level' => $gradeLevelLabel,
                'school_year' => $schoolYearLabel,
                'payment_count' => 0,
                'total_paid' => 0.0,
                'annual_total' => max(0, $resolvedAnnualTotal),
                'last_balance' => max(0, $resolvedAnnualTotal),
                '_latest_balance_recorded' => false,
            ];
        } elseif ($annualTotal > 0) {
            $gradeLevelHistoryMap[$gradeKeyValue]['annual_total'] = $resolvedAnnualTotal;
            if (empty($gradeLevelHistoryMap[$gradeKeyValue]['_latest_balance_recorded'])) {
                $gradeLevelHistoryMap[$gradeKeyValue]['last_balance'] = max(0, $resolvedAnnualTotal);
            }
        } elseif ($resolvedAnnualTotal > (float)($gradeLevelHistoryMap[$gradeKeyValue]['annual_total'] ?? 0)) {
            $gradeLevelHistoryMap[$gradeKeyValue]['annual_total'] = $resolvedAnnualTotal;
            if (empty($gradeLevelHistoryMap[$gradeKeyValue]['_latest_balance_recorded'])) {
                $gradeLevelHistoryMap[$gradeKeyValue]['last_balance'] = max(0, $resolvedAnnualTotal);
            }
        }

        return $gradeKeyValue;
    };

    if ($selectedCurrentGradeLevel !== '' || $selectedCurrentSchoolYear !== '') {
        $currentAnnualTotal = round((float)(smartenroll_resolve_grade_tuition_fee($selectedCurrentGradeLevel, $conn) ?? 0.0), 2);
        $currentGradeHistoryKey = $ensureGradeHistoryEntry($selectedCurrentGradeLevel, $selectedCurrentSchoolYear, $currentAnnualTotal);
    }

    $tableCheck = $conn->query("SHOW TABLES LIKE 'tuition_payments'");
    $hasPaymentsTable = $tableCheck && $tableCheck->num_rows > 0;
    if ($tableCheck) {
        $tableCheck->close();
    }

    if ($hasPaymentsTable) {
        $detailStmt = $conn->prepare(
            "SELECT
                id,
                COALESCE(grade_level, '') AS grade_level,
                COALESCE(school_year, '') AS school_year,
                payment_date,
                amount_paid,
                tuition_fee,
                balance_after,
                receipt_no,
                payment_items,
                email_sent,
                created_at
             FROM tuition_payments
             WHERE (enrollment_id = ? OR student_id = ?)
             ORDER BY COALESCE(school_year, '') DESC, COALESCE(grade_level, '') ASC, payment_date DESC, id DESC"
        );
        $detailStmt->bind_param('is', $selectedEnrollmentId, $selectedStudentId);
        $detailStmt->execute();
        $detailResult = $detailStmt->get_result();

        while ($row = $detailResult->fetch_assoc()) {
            $rowGradeLevel = trim((string)($row['grade_level'] ?? ''));
            $rowSchoolYear = trim((string)($row['school_year'] ?? ''));
            $rowTuitionFee = round((float)($row['tuition_fee'] ?? 0), 2);
            $gradeKey = $ensureGradeHistoryEntry($rowGradeLevel, $rowSchoolYear, $rowTuitionFee);
            $amountPaidValue = round((float)($row['amount_paid'] ?? 0), 2);

            if ($amountPaidValue <= 0) {
                continue;
            }

            $gradeLevelHistoryMap[$gradeKey]['payment_count'] = (int)($gradeLevelHistoryMap[$gradeKey]['payment_count'] ?? 0) + 1;
            $gradeLevelHistoryMap[$gradeKey]['total_paid'] = round((float)($gradeLevelHistoryMap[$gradeKey]['total_paid'] ?? 0) + $amountPaidValue, 2);

            if (empty($gradeLevelHistoryMap[$gradeKey]['_latest_balance_recorded'])) {
                $gradeLevelHistoryMap[$gradeKey]['last_balance'] = max(0, round((float)($row['balance_after'] ?? 0), 2));
                $gradeLevelHistoryMap[$gradeKey]['_latest_balance_recorded'] = true;
            }

            $paymentItems = decode_saved_payment_items((string)($row['payment_items'] ?? ''), $amountPaidValue);
            $paymentItemsLabel = summarize_payment_items($paymentItems);

            $detailedPaymentHistory[$gradeKey][] = [
                'id' => (int)($row['id'] ?? 0),
                'payment_date' => (string)($row['payment_date'] ?? ''),
                'amount_paid' => $amountPaidValue,
                'tuition_fee' => $rowTuitionFee,
                'balance_after' => round((float)($row['balance_after'] ?? 0), 2),
                'receipt_no' => (string)($row['receipt_no'] ?? ''),
                'school_year' => $rowSchoolYear !== '' ? $rowSchoolYear : 'N/A',
            ];

            $savedInvoiceHistoryByGrade[$gradeKey][] = [
                'id' => (int)($row['id'] ?? 0),
                'payment_date' => (string)($row['payment_date'] ?? ''),
                'receipt_no' => (string)($row['receipt_no'] ?? ''),
                'payment_items' => $paymentItemsLabel,
                'amount_paid' => $amountPaidValue,
                'balance_after' => round((float)($row['balance_after'] ?? 0), 2),
                'email_sent' => (int)($row['email_sent'] ?? 0),
            ];
        }
        $detailStmt->close();
    }

    $tableCheck = $conn->query("SHOW TABLES LIKE 'audit_logs'");
    $hasAuditLogsTable = $tableCheck && $tableCheck->num_rows > 0;
    if ($tableCheck) {
        $tableCheck->close();
    }

    if ($hasAuditLogsTable) {
        $previewAction = 'tuition_invoice_preview_emailed';
        $previewStudentPattern = '%"student_id":"' . $selectedStudentId . '"%';
        $previewStmt = $conn->prepare(
            "SELECT entity_id, details_json, created_at
             FROM audit_logs
             WHERE action = ?
               AND details_json LIKE ?
             ORDER BY created_at DESC, id DESC"
        );
        $previewStmt->bind_param('ss', $previewAction, $previewStudentPattern);
        $previewStmt->execute();
        $previewResult = $previewStmt->get_result();

        while ($previewRow = $previewResult->fetch_assoc()) {
            $previewDetails = json_decode((string)($previewRow['details_json'] ?? ''), true);
            if (!is_array($previewDetails)) {
                continue;
            }

            $previewGradeLevel = trim((string)($previewDetails['grade_level'] ?? ''));
            $previewSchoolYear = trim((string)($previewDetails['school_year'] ?? ''));
            if ($previewGradeLevel === '' && $previewSchoolYear === '') {
                if (count($gradeLevelHistoryMap) !== 1) {
                    continue;
                }

                $onlyGradeEntry = reset($gradeLevelHistoryMap);
                $previewGradeLevel = trim((string)($onlyGradeEntry['grade_level'] ?? ''));
                $previewSchoolYear = trim((string)($onlyGradeEntry['school_year'] ?? ''));
            }

            $previewKey = $ensureGradeHistoryEntry($previewGradeLevel, $previewSchoolYear, 0.0);
            $previewItems = is_array($previewDetails['items'] ?? null) ? $previewDetails['items'] : [];
            $sentEmailHistoryByGrade[$previewKey][] = [
                'sent_at' => (string)($previewRow['created_at'] ?? ''),
                'type' => 'Preview Email',
                'invoice_no' => trim((string)($previewRow['entity_id'] ?? '')) !== '' ? (string)$previewRow['entity_id'] : 'N/A',
                'payment_items' => summarize_payment_items($previewItems, 'No billing item added yet'),
                'amount' => round((float)($previewDetails['amount'] ?? 0), 2),
                'email' => trim((string)($previewDetails['email'] ?? '')) !== '' ? (string)$previewDetails['email'] : 'N/A',
            ];
        }
        $previewStmt->close();

        if ($hasPaymentsTable) {
            $invoiceAction = 'tuition_receipt_emailed';
            $invoiceStmt = $conn->prepare(
                "SELECT
                    al.details_json,
                    al.created_at,
                    tp.receipt_no,
                    tp.amount_paid,
                    tp.payment_items,
                    COALESCE(tp.grade_level, '') AS grade_level,
                    COALESCE(tp.school_year, '') AS school_year
                 FROM audit_logs al
                 INNER JOIN tuition_payments tp
                    ON tp.id = CAST(al.entity_id AS UNSIGNED)
                 WHERE al.action = ?
                   AND (tp.enrollment_id = ? OR tp.student_id = ?)
                 ORDER BY al.created_at DESC, al.id DESC"
            );
            $invoiceStmt->bind_param('sis', $invoiceAction, $selectedEnrollmentId, $selectedStudentId);
            $invoiceStmt->execute();
            $invoiceResult = $invoiceStmt->get_result();

            while ($invoiceRow = $invoiceResult->fetch_assoc()) {
                $invoiceGradeLevel = trim((string)($invoiceRow['grade_level'] ?? ''));
                $invoiceSchoolYear = trim((string)($invoiceRow['school_year'] ?? ''));
                $invoiceKey = $ensureGradeHistoryEntry($invoiceGradeLevel, $invoiceSchoolYear, 0.0);
                $invoiceDetails = json_decode((string)($invoiceRow['details_json'] ?? ''), true);
                if (!is_array($invoiceDetails)) {
                    $invoiceDetails = [];
                }

                $invoiceItems = decode_saved_payment_items((string)($invoiceRow['payment_items'] ?? ''), (float)($invoiceRow['amount_paid'] ?? 0));
                $sentEmailHistoryByGrade[$invoiceKey][] = [
                    'sent_at' => (string)($invoiceRow['created_at'] ?? ''),
                    'type' => 'Invoice Email',
                    'invoice_no' => trim((string)($invoiceRow['receipt_no'] ?? '')) !== '' ? (string)$invoiceRow['receipt_no'] : 'N/A',
                    'payment_items' => summarize_payment_items($invoiceItems),
                    'amount' => round((float)($invoiceRow['amount_paid'] ?? 0), 2),
                    'email' => trim((string)($invoiceDetails['email'] ?? '')) !== '' ? (string)$invoiceDetails['email'] : 'N/A',
                ];
            }
            $invoiceStmt->close();
        }
    }

    foreach ($gradeLevelHistoryMap as &$historyEntry) {
        $historyEntry['annual_total'] = round((float)($historyEntry['annual_total'] ?? 0), 2);
        $historyEntry['total_paid'] = round((float)($historyEntry['total_paid'] ?? 0), 2);
        if (empty($historyEntry['_latest_balance_recorded'])) {
            $historyEntry['last_balance'] = max(0, round((float)$historyEntry['annual_total'] - (float)$historyEntry['total_paid'], 2));
        } else {
            $historyEntry['last_balance'] = max(0, round((float)($historyEntry['last_balance'] ?? 0), 2));
        }
        unset($historyEntry['_latest_balance_recorded']);
    }
    unset($historyEntry);

    $gradeLevelHistory = array_values($gradeLevelHistoryMap);
    usort($gradeLevelHistory, static function (array $left, array $right): int {
        $leftSchoolYear = (string)($left['school_year'] ?? '');
        $rightSchoolYear = (string)($right['school_year'] ?? '');
        if ($leftSchoolYear !== $rightSchoolYear) {
            return strcmp($rightSchoolYear, $leftSchoolYear);
        }

        return strcmp((string)($left['grade_level'] ?? ''), (string)($right['grade_level'] ?? ''));
    });
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SMARTENROLL | Tuition Details</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/tuition_details.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="dashboard-page dashboard-white-page">

<main class="dashboard-main">
    <div class="dashboard-header tuition-header tuition-header-bar">
        <div class="student-header-left">
            <a href="track_tuition.php" class="dashboard-link back-left"><i class="fa-solid fa-arrow-left"></i></a>
            <div class="student-header-title">
                <h1>Tuition Details</h1>
                <p>Payment profile for the selected student.</p>
            </div>
        </div>
    </div>

    <section class="tuition-section">
        <div class="tuition-card tuition-form-card">
            <?php if ($error): ?>
                <div class="student-error"><strong>Unable to load student.</strong> <?php echo htmlspecialchars($error); ?></div>
            <?php else: ?>
                <div class="tuition-overview">
                    <div class="tuition-overview-copy">
                        <span class="tuition-overview-label">Student Billing Profile</span>
                        <h2><?php echo htmlspecialchars($studentName); ?></h2>
                    </div>
                    <div class="tuition-overview-meta">
                        <div class="tuition-meta-chip">
                            <span>Grade Level</span>
                            <strong><?php echo htmlspecialchars($student['grade_level'] ?? '—'); ?></strong>
                        </div>
                        <div class="tuition-meta-chip">
                            <span>School Year</span>
                            <strong><?php echo htmlspecialchars($schoolYear ?: '—'); ?></strong>
                        </div>
                    </div>
                </div>

                <div class="tuition-summary-grid">
                    <div class="tuition-summary-card">
                        <span>Total Tuition</span>
                        <strong><?php echo htmlspecialchars(format_money($totalTuition)); ?></strong>
                    </div>
                    <div class="tuition-summary-card">
                        <span>Amount Paid</span>
                        <strong><?php echo htmlspecialchars(format_money($amountPaid)); ?></strong>
                    </div>
                    <div class="tuition-summary-card accent">
                        <span>Remaining Balance</span>
                        <strong><?php echo htmlspecialchars(format_money($remainingBalance)); ?></strong>
                    </div>
                </div>

                <div class="tuition-form-section">
                    <div class="tuition-section-head">
                        <h3>Payment Information</h3>
                    </div>

                    <div class="tuition-form-grid">
                        <div class="tuition-field">
                            <label>Customer/ID No.</label>
                            <input type="text" value="<?php echo htmlspecialchars($student['student_id'] ?? '—'); ?>">
                        </div>
                        <div class="tuition-field">
                            <label>Student Name</label>
                            <input type="text" value="<?php echo htmlspecialchars($studentName); ?>">
                        </div>
                        <div class="tuition-field">
                            <label>School Year</label>
                            <input type="text" value="<?php echo htmlspecialchars($schoolYear ?: '—'); ?>">
                        </div>
                        <div class="tuition-field">
                            <label>Campus/Branch</label>
                            <input type="text" value="Adreo Montessori Incorporated">
                        </div>
                        <div class="tuition-field tuition-field-wide">
                            <label>Teller/Cashier Name</label>
                            <input type="text" value="adreomontessori@gmail.com">
                        </div>
                    </div>
                </div>

                <hr class="tuition-divider">

                <div class="tuition-form-section">
                    <div class="tuition-section-head">
                        <h3>Balance Summary</h3>
                    </div>

                    <div class="tuition-form-grid tuition-balance-grid">
                        <div class="tuition-field">
                            <label>Total Tuition</label>
                            <input type="text" value="<?php echo htmlspecialchars(format_money($totalTuition)); ?>" readonly>
                        </div>
                        <div class="tuition-field">
                            <label>Amount Paid</label>
                            <input type="text" value="<?php echo htmlspecialchars(format_money($amountPaid)); ?>" readonly>
                        </div>
                        <div class="tuition-field">
                            <label>Remaining Balance</label>
                            <input type="text" value="<?php echo htmlspecialchars(format_money($remainingBalance)); ?>" readonly>
                        </div>
                    </div>
                </div>

                <hr class="tuition-divider">

                <?php if (!empty($gradeLevelHistory)): ?>
                <div class="tuition-form-section">
                    <div class="tuition-section-head">
                        <h3>Grade Level History</h3>
                    </div>

                    <div class="grade-level-selector">
                        <label for="gradeLevelDropdown">Select Grade Level:</label>
                        <select id="gradeLevelDropdown" class="grade-dropdown">
                            <option value="">-- Select a grade level --</option>
                            <?php foreach ($gradeLevelHistory as $history): ?>
                            <option value="<?php echo htmlspecialchars($history['grade_key']); ?>">
                                <?php echo htmlspecialchars($history['grade_level'] . ' (' . $history['school_year'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="gradeHistoryContainer" style="display: none;">
                        <div class="grade-summary-cards">
                            <div class="grade-summary-card">
                                <span>Program Total</span>
                                <strong id="annualTotal">PHP 0.00</strong>
                            </div>
                            <div class="grade-summary-card">
                                <span>Total Paid</span>
                                <strong id="totalPaidForGrade">PHP 0.00</strong>
                            </div>
                            <div class="grade-summary-card accent">
                                <span>Remaining Balance</span>
                                <strong id="balanceForGrade">PHP 0.00</strong>
                            </div>
                        </div>

                        <div class="payment-details-table">
                            <table class="payment-table payment-history-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Receipt No.</th>
                                        <th>Amount Paid</th>
                                        <th>School Year</th>
                                        <th>Balance After</th>
                                    </tr>
                                </thead>
                                <tbody id="paymentTableBody">
                                </tbody>
                            </table>
                        </div>

                        <div class="history-subsection">
                            <div class="tuition-section-head history-panel-head">
                                <h4>Saved Invoices</h4>
                            </div>

                            <div class="payment-details-table">
                                <table class="payment-table saved-invoice-table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Invoice</th>
                                            <th>Payment Items</th>
                                            <th>Total Breakdown</th>
                                            <th>Remaining Balance</th>
                                            <th>Email Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="savedInvoiceTableBody">
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="history-subsection">
                            <div class="tuition-section-head history-panel-head">
                                <h4>Sent Email History</h4>
                                <p>Preview and invoice emails already sent for the selected grade level.</p>
                            </div>

                            <div class="payment-details-table">
                                <table class="payment-table sent-email-table">
                                    <thead>
                                        <tr>
                                            <th>Sent</th>
                                            <th>Type</th>
                                            <th>Invoice</th>
                                            <th>Payment Items</th>
                                            <th>Amount</th>
                                            <th>Recipient</th>
                                        </tr>
                                    </thead>
                                    <tbody id="sentEmailTableBody">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="tuition-divider">
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </section>
</main>

<script>
    // Grade level payment history data
    const detailedPaymentHistory = <?php echo json_encode($detailedPaymentHistory, JSON_UNESCAPED_SLASHES); ?>;
    const gradeLevelHistory = <?php echo json_encode($gradeLevelHistory, JSON_UNESCAPED_SLASHES); ?>;
    const savedInvoiceHistoryByGrade = <?php echo json_encode($savedInvoiceHistoryByGrade, JSON_UNESCAPED_SLASHES); ?>;
    const sentEmailHistoryByGrade = <?php echo json_encode($sentEmailHistoryByGrade, JSON_UNESCAPED_SLASHES); ?>;
    const currentGradeHistoryKey = <?php echo json_encode($currentGradeHistoryKey, JSON_UNESCAPED_SLASHES); ?>;
</script>

<script src="js/tuition_details.js"></script></body>
</html>





















