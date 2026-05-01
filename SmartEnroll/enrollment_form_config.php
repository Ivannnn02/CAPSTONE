<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function smartenroll_grade_level_defaults(): array
{
    return [
        ['grade_key' => 'Toddler', 'grade_label' => 'Toddler', 'tuition_fee' => 69340.00, 'sort_order' => 10],
        ['grade_key' => 'Casa', 'grade_label' => 'Casa', 'tuition_fee' => 75732.00, 'sort_order' => 20],
        ['grade_key' => 'Kindergarten', 'grade_label' => 'Kindergarten', 'tuition_fee' => 77612.00, 'sort_order' => 30],
        ['grade_key' => 'Brave', 'grade_label' => 'Brave SpEd', 'tuition_fee' => 85226.00, 'sort_order' => 40],
        ['grade_key' => 'Grade 1', 'grade_label' => 'Grade 1', 'tuition_fee' => 78740.00, 'sort_order' => 50],
        ['grade_key' => 'Grade 2', 'grade_label' => 'Grade 2', 'tuition_fee' => 78740.00, 'sort_order' => 60],
        ['grade_key' => 'Grade 3', 'grade_label' => 'Grade 3', 'tuition_fee' => 80240.00, 'sort_order' => 70],
    ];
}

function smartenroll_payment_plan_defaults(): array
{
    return [
        'annual' => [
            'label' => 'Annual Payment',
            'core_option' => 'Tuition Fee',
            'installment_count' => 1,
            'discount_percent' => 20.0,
        ],
        'semestral' => [
            'label' => 'Semestral Payment',
            'core_option' => 'Tuition Fee',
            'installment_count' => 2,
            'discount_percent' => 15.0,
        ],
        'monthly' => [
            'label' => 'Monthly Payment',
            'core_option' => 'Monthly Payment',
            'installment_count' => 10,
            'discount_percent' => 10.0,
        ],
    ];
}

function smartenroll_normalize_payment_plan_key(string $planKey): string
{
    $normalized = strtolower(trim($planKey));

    return match ($normalized) {
        'annual', 'annual payment', 'annual payment plan' => 'annual',
        'semestral', 'semestral payment', 'semestral payment plan', 'semester', 'semester payment' => 'semestral',
        'monthly', 'monthly payment', 'monthly payment plan' => 'monthly',
        default => 'annual',
    };
}

function smartenroll_grade_payment_plan_templates(): array
{
    return [
        'Brave' => [
            'annual' => [
                'program_total' => 85226.00,
                'items' => [
                    'Tuition Fee' => 79226.00,
                    'Registration Fee & Miscellaneous' => 6000.00,
                ],
            ],
            'semestral' => [
                'program_total' => 87952.50,
                'items' => [
                    'Tuition Fee' => 81952.50,
                    'Registration Fee & Miscellaneous' => [
                        'amount' => 3000.00,
                        'repeat_count' => 2,
                    ],
                ],
            ],
            'monthly' => [
                'program_total' => 83900.00,
                'items' => [
                    'Monthly Payment' => 7790.00,
                    'Registration Fee & Miscellaneous' => 6000.00,
                ],
            ],
        ],
        'Casa' => [
            'annual' => [
                'program_total' => 75732.00,
                'items' => [
                    'Tuition Fee' => 69732.00,
                    'Registration Fee & Miscellaneous' => 6000.00,
                ],
            ],
            'semestral' => [
                'program_total' => 78105.00,
                'items' => [
                    'Tuition Fee' => 72105.00,
                    'Registration Fee & Miscellaneous' => [
                        'amount' => 3000.00,
                        'repeat_count' => 2,
                    ],
                ],
            ],
            'monthly' => [
                'program_total' => 73800.00,
                'items' => [
                    'Monthly Payment' => 6780.00,
                    'Registration Fee & Miscellaneous' => 6000.00,
                ],
            ],
        ],
        'Toddler' => [
            'annual' => [
                'program_total' => 69340.00,
                'items' => [
                    'Tuition Fee' => 63340.00,
                    'Registration Fee & Miscellaneous' => 6000.00,
                ],
            ],
            'semestral' => [
                'program_total' => 71475.00,
                'items' => [
                    'Tuition Fee' => 65475.00,
                    'Registration Fee & Miscellaneous' => [
                        'amount' => 3000.00,
                        'repeat_count' => 2,
                    ],
                ],
            ],
            'monthly' => [
                'program_total' => 67000.00,
                'items' => [
                    'Monthly Payment' => 6100.00,
                    'Registration Fee & Miscellaneous' => 6000.00,
                ],
            ],
        ],
        'Kindergarten' => [
            'annual' => [
                'program_total' => 77612.00,
                'items' => [
                    'Tuition Fee' => 71612.00,
                    'Registration Fee & Miscellaneous' => 6000.00,
                ],
            ],
            'semestral' => [
                'program_total' => 80055.00,
                'items' => [
                    'Tuition Fee' => 74055.00,
                    'Registration Fee & Miscellaneous' => [
                        'amount' => 3000.00,
                        'repeat_count' => 2,
                    ],
                ],
            ],
            'monthly' => [
                'program_total' => 75800.00,
                'items' => [
                    'Monthly Payment' => 6980.00,
                    'Registration Fee & Miscellaneous' => 6000.00,
                ],
            ],
        ],
        'Grade 1' => [
            'annual' => [
                'program_total' => 78740.00,
                'items' => [
                    'Tuition Fee' => 72740.00,
                    'Registration Fee & Miscellaneous' => 2500.00,
                    'Books' => 3500.00,
                ],
            ],
            'semestral' => [
                'program_total' => 81225.00,
                'items' => [
                    'Tuition Fee' => 75225.00,
                    'Registration Fee & Miscellaneous' => 2500.00,
                    'Books' => 3500.00,
                ],
            ],
            'monthly' => [
                'program_total' => 77000.00,
                'items' => [
                    'Monthly Payment' => 7100.00,
                    'Registration Fee & Miscellaneous' => 2500.00,
                    'Books' => 3500.00,
                ],
            ],
        ],
        'Grade 2' => [
            'annual' => [
                'program_total' => 78740.00,
                'items' => [
                    'Tuition Fee' => 72740.00,
                    'Registration Fee & Miscellaneous' => 2000.00,
                    'Books' => 4000.00,
                ],
            ],
            'semestral' => [
                'program_total' => 81225.00,
                'items' => [
                    'Tuition Fee' => 75225.00,
                    'Registration Fee & Miscellaneous' => 2000.00,
                    'Books' => 4000.00,
                ],
            ],
            'monthly' => [
                'program_total' => 77000.00,
                'items' => [
                    'Monthly Payment' => 7100.00,
                    'Registration Fee & Miscellaneous' => 2000.00,
                    'Books' => 4000.00,
                ],
            ],
        ],
        'Grade 3' => [
            'annual' => [
                'program_total' => 80240.00,
                'items' => [
                    'Tuition Fee' => 74240.00,
                    'Registration Fee & Miscellaneous' => 1000.00,
                    'Books' => 5000.00,
                ],
            ],
            'semestral' => [
                'program_total' => 81225.00,
                'items' => [
                    'Tuition Fee' => 75225.00,
                    'Registration Fee & Miscellaneous' => 1000.00,
                    'Books' => 5000.00,
                ],
            ],
            'monthly' => [
                'program_total' => 77000.00,
                'items' => [
                    'Monthly Payment' => 7100.00,
                    'Registration Fee & Miscellaneous' => 1000.00,
                    'Books' => 5000.00,
                ],
            ],
        ],
    ];
}

function smartenroll_grade_breakdown_templates(): array
{
    $templates = [];

    foreach (smartenroll_grade_payment_plan_templates() as $gradeKey => $plans) {
        $annualPlan = $plans['annual']['items'] ?? [];
        if (!is_array($annualPlan) || $annualPlan === []) {
            continue;
        }

        $templates[$gradeKey] = $annualPlan;
    }

    return $templates;
}

function smartenroll_filter_grade_breakdown_components(array $components, string $gradeKey): array
{
    $gradeKey = trim($gradeKey);
    $restricted = ['Toddler', 'Casa', 'Kindergarten'];

    if (in_array($gradeKey, $restricted, true)) {
        unset($components['Books']);
    }

    return $components;
}

function smartenroll_grade_level_connection(?mysqli $conn, bool &$ownsConnection): mysqli
{
    if ($conn instanceof mysqli) {
        $ownsConnection = false;
        return $conn;
    }

    $ownsConnection = true;
    return smartenroll_auth_db();
}

function smartenroll_ensure_grade_levels_table(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS enrollment_grade_levels (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            grade_key VARCHAR(150) NOT NULL,
            grade_label VARCHAR(150) NOT NULL,
            tuition_fee DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_grade_key (grade_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

function smartenroll_seed_grade_levels(mysqli $conn): void
{
    $countResult = $conn->query("SELECT COUNT(*) AS total FROM enrollment_grade_levels");
    $count = (int)($countResult->fetch_assoc()['total'] ?? 0);
    if ($count > 0) {
        return;
    }

    $stmt = $conn->prepare(
        "INSERT INTO enrollment_grade_levels (grade_key, grade_label, tuition_fee, sort_order, is_active)
         VALUES (?, ?, ?, ?, 1)"
    );

    foreach (smartenroll_grade_level_defaults() as $row) {
        $gradeKey = (string)$row['grade_key'];
        $gradeLabel = (string)$row['grade_label'];
        $tuitionFee = (float)$row['tuition_fee'];
        $sortOrder = (int)$row['sort_order'];
        $stmt->bind_param('ssdi', $gradeKey, $gradeLabel, $tuitionFee, $sortOrder);
        $stmt->execute();
    }

    $stmt->close();

    smartenroll_restore_toddler_grade_level($conn);
}

function smartenroll_restore_toddler_grade_level(mysqli $conn): void
{
    $toddlerRow = null;
    foreach (smartenroll_grade_level_defaults() as $row) {
        if (((string)($row['grade_key'] ?? '')) === 'Toddler') {
            $toddlerRow = $row;
            break;
        }
    }

    if ($toddlerRow === null) {
        return;
    }

    $stmt = $conn->prepare(
        "INSERT INTO enrollment_grade_levels (grade_key, grade_label, tuition_fee, sort_order, is_active)
         VALUES (?, ?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE
            grade_label = VALUES(grade_label),
            tuition_fee = VALUES(tuition_fee),
            sort_order = VALUES(sort_order),
            is_active = 1"
    );

    $gradeKey = (string)$toddlerRow['grade_key'];
    $gradeLabel = (string)$toddlerRow['grade_label'];
    $tuitionFee = (float)$toddlerRow['tuition_fee'];
    $sortOrder = (int)$toddlerRow['sort_order'];
    $stmt->bind_param('ssdi', $gradeKey, $gradeLabel, $tuitionFee, $sortOrder);
    $stmt->execute();
    $stmt->close();
}

function smartenroll_get_grade_levels(?mysqli $conn = null): array
{
    $ownsConnection = false;
    $db = smartenroll_grade_level_connection($conn, $ownsConnection);

    try {
        smartenroll_ensure_grade_levels_table($db);
        smartenroll_seed_grade_levels($db);
        smartenroll_restore_toddler_grade_level($db);

        $rows = [];
        $result = $db->query(
            "SELECT id, grade_key, grade_label, tuition_fee, sort_order, is_active
             FROM enrollment_grade_levels
             WHERE is_active = 1
             ORDER BY sort_order ASC, id ASC"
        );

        while ($row = $result->fetch_assoc()) {
            $row['id'] = (int)($row['id'] ?? 0);
            $row['tuition_fee'] = round((float)($row['tuition_fee'] ?? 0), 2);
            $row['sort_order'] = (int)($row['sort_order'] ?? 0);
            $row['is_active'] = (int)($row['is_active'] ?? 0);
            $rows[] = $row;
        }

        return $rows;
    } finally {
        if ($ownsConnection) {
            $db->close();
        }
    }
}

function smartenroll_save_grade_levels(array $rows, ?mysqli $conn = null): void
{
    $ownsConnection = false;
    $db = smartenroll_grade_level_connection($conn, $ownsConnection);

    try {
        smartenroll_ensure_grade_levels_table($db);

        $cleanRows = [];
        $seenKeys = [];
        $seenLabels = [];
        $sortOrder = 10;

        foreach ($rows as $row) {
            $gradeKey = trim((string)($row['grade_key'] ?? ''));
            $gradeLabel = trim((string)($row['grade_label'] ?? ''));
            $tuitionFee = round((float)($row['tuition_fee'] ?? 0), 2);

            if ($gradeKey === '' && $gradeLabel === '') {
                continue;
            }

            // If one field is blank, reuse the other so admins can add or rename grades faster.
            if ($gradeKey === '' && $gradeLabel !== '') {
                $gradeKey = $gradeLabel;
            }

            if ($gradeLabel === '' && $gradeKey !== '') {
                $gradeLabel = $gradeKey;
            }

            if (isset($seenKeys[strtolower($gradeKey)])) {
                throw new RuntimeException('Duplicate grade values are not allowed: ' . $gradeKey);
            }
            $seenKeys[strtolower($gradeKey)] = true;

            if (isset($seenLabels[strtolower($gradeLabel)])) {
                throw new RuntimeException('This grade level already exists: ' . $gradeLabel);
            }
            $seenLabels[strtolower($gradeLabel)] = true;

            $cleanRows[] = [
                'grade_key' => $gradeKey,
                'grade_label' => $gradeLabel,
                'tuition_fee' => $tuitionFee,
                'sort_order' => $sortOrder,
            ];
            $sortOrder += 10;
        }

        if (empty($cleanRows)) {
            throw new RuntimeException('Add at least one grade level before saving.');
        }

        $db->begin_transaction();
        $db->query("DELETE FROM enrollment_grade_levels");

        $stmt = $db->prepare(
            "INSERT INTO enrollment_grade_levels (grade_key, grade_label, tuition_fee, sort_order, is_active)
             VALUES (?, ?, ?, ?, 1)"
        );

        foreach ($cleanRows as $row) {
            $gradeKey = $row['grade_key'];
            $gradeLabel = $row['grade_label'];
            $tuitionFee = $row['tuition_fee'];
            $rowSortOrder = $row['sort_order'];
            $stmt->bind_param('ssdi', $gradeKey, $gradeLabel, $tuitionFee, $rowSortOrder);
            $stmt->execute();
        }

        $stmt->close();
        $db->commit();
    } catch (Throwable $e) {
        if ($db->errno === 0) {
            try {
                $db->rollback();
            } catch (Throwable $ignore) {
            }
        } else {
            try {
                $db->rollback();
            } catch (Throwable $ignore) {
            }
        }
        throw $e;
    } finally {
        if ($ownsConnection) {
            $db->close();
        }
    }
}

function smartenroll_get_grade_tuition_map(?mysqli $conn = null): array
{
    $map = [];
    foreach (smartenroll_get_grade_levels($conn) as $row) {
        $map[(string)$row['grade_key']] = (float)$row['tuition_fee'];
    }

    return $map;
}

function smartenroll_normalize_grade_value(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/', ' ', $value);
    return strtolower((string)$value);
}

function smartenroll_get_grade_level_lookup(?mysqli $conn = null): array
{
    $lookup = [
        'by_key' => [],
        'by_label' => [],
    ];

    foreach (smartenroll_get_grade_levels($conn) as $row) {
        $normalizedKey = smartenroll_normalize_grade_value((string)($row['grade_key'] ?? ''));
        if ($normalizedKey !== '') {
            $lookup['by_key'][$normalizedKey] = $row;
        }

        $normalizedLabel = smartenroll_normalize_grade_value((string)($row['grade_label'] ?? ''));
        if ($normalizedLabel !== '') {
            $lookup['by_label'][$normalizedLabel] = $row;
        }
    }

    return $lookup;
}

function smartenroll_find_grade_level(string $gradeValue, ?mysqli $conn = null, ?array $lookup = null): ?array
{
    $normalizedValue = smartenroll_normalize_grade_value($gradeValue);
    if ($normalizedValue === '') {
        return null;
    }

    $resolvedLookup = $lookup ?? smartenroll_get_grade_level_lookup($conn);

    return $resolvedLookup['by_key'][$normalizedValue]
        ?? $resolvedLookup['by_label'][$normalizedValue]
        ?? null;
}

function smartenroll_default_payment_plan_discount_map(): array
{
    $defaults = [];

    foreach (smartenroll_payment_plan_defaults() as $planKey => $planMeta) {
        $defaults[$planKey] = max(0, min(100, round((float)($planMeta['discount_percent'] ?? 0), 2)));
    }

    return $defaults;
}

function smartenroll_normalize_payment_plan_discount_map(array $settings): array
{
    $defaults = smartenroll_default_payment_plan_discount_map();
    $normalized = [];

    foreach ($defaults as $planKey => $defaultPercent) {
        $normalized[$planKey] = max(
            0,
            min(100, round((float)($settings[$planKey] ?? $defaultPercent), 2))
        );
    }

    return $normalized;
}

function smartenroll_ensure_payment_plan_settings_table(?mysqli $conn = null): void
{
    $ownsConnection = false;
    $db = smartenroll_grade_level_connection($conn, $ownsConnection);

    try {
        $db->query(
            "CREATE TABLE IF NOT EXISTS grade_payment_plan_settings (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                grade_key VARCHAR(150) NOT NULL,
                annual_discount_percent DECIMAL(5,2) NOT NULL DEFAULT 20.00,
                semestral_discount_percent DECIMAL(5,2) NOT NULL DEFAULT 15.00,
                monthly_discount_percent DECIMAL(5,2) NOT NULL DEFAULT 10.00,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_grade_payment_plan_settings_grade_key (grade_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
    } finally {
        if ($ownsConnection) {
            $db->close();
        }
    }
}

function smartenroll_get_payment_plan_settings(?string $gradeKey = null, ?mysqli $conn = null): array
{
    $ownsConnection = false;
    $db = smartenroll_grade_level_connection($conn, $ownsConnection);
    $defaults = smartenroll_default_payment_plan_discount_map();

    try {
        smartenroll_ensure_payment_plan_settings_table($db);

        if ($gradeKey !== null) {
            $normalizedGradeKey = trim($gradeKey);
            if ($normalizedGradeKey === '') {
                return [];
            }

            $stmt = $db->prepare(
                "SELECT annual_discount_percent, semestral_discount_percent, monthly_discount_percent
                 FROM grade_payment_plan_settings
                 WHERE grade_key = ?
                 LIMIT 1"
            );
            $stmt->bind_param('s', $normalizedGradeKey);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!is_array($row)) {
                return [];
            }

            return smartenroll_normalize_payment_plan_discount_map([
                'annual' => $row['annual_discount_percent'] ?? $defaults['annual'],
                'semestral' => $row['semestral_discount_percent'] ?? $defaults['semestral'],
                'monthly' => $row['monthly_discount_percent'] ?? $defaults['monthly'],
            ]);
        }

        $settingsByGrade = [];
        $result = $db->query(
            "SELECT grade_key, annual_discount_percent, semestral_discount_percent, monthly_discount_percent
             FROM grade_payment_plan_settings"
        );

        while ($row = $result->fetch_assoc()) {
            $resolvedGradeKey = trim((string)($row['grade_key'] ?? ''));
            if ($resolvedGradeKey === '') {
                continue;
            }

            $settingsByGrade[$resolvedGradeKey] = smartenroll_normalize_payment_plan_discount_map([
                'annual' => $row['annual_discount_percent'] ?? $defaults['annual'],
                'semestral' => $row['semestral_discount_percent'] ?? $defaults['semestral'],
                'monthly' => $row['monthly_discount_percent'] ?? $defaults['monthly'],
            ]);
        }

        return $settingsByGrade;
    } finally {
        if ($ownsConnection) {
            $db->close();
        }
    }
}

function smartenroll_save_payment_plan_settings(array $settingsByGrade, ?mysqli $conn = null): void
{
    $ownsConnection = false;
    $db = smartenroll_grade_level_connection($conn, $ownsConnection);

    try {
        smartenroll_ensure_payment_plan_settings_table($db);

        $stmt = $db->prepare(
            "INSERT INTO grade_payment_plan_settings (
                grade_key, annual_discount_percent, semestral_discount_percent, monthly_discount_percent
             ) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                annual_discount_percent = VALUES(annual_discount_percent),
                semestral_discount_percent = VALUES(semestral_discount_percent),
                monthly_discount_percent = VALUES(monthly_discount_percent),
                updated_at = CURRENT_TIMESTAMP"
        );

        foreach ($settingsByGrade as $gradeKey => $settings) {
            $resolvedGradeKey = trim((string)$gradeKey);
            if ($resolvedGradeKey === '') {
                continue;
            }

            $normalizedSettings = smartenroll_normalize_payment_plan_discount_map(
                is_array($settings) ? $settings : []
            );
            $annualPercent = $normalizedSettings['annual'];
            $semestralPercent = $normalizedSettings['semestral'];
            $monthlyPercent = $normalizedSettings['monthly'];

            $stmt->bind_param('sddd', $resolvedGradeKey, $annualPercent, $semestralPercent, $monthlyPercent);
            $stmt->execute();
        }

        $stmt->close();
    } finally {
        if ($ownsConnection) {
            $db->close();
        }
    }
}

function smartenroll_resolve_core_breakdown_component(array $breakdown): array
{
    $resolvedBreakdown = [];
    foreach ($breakdown as $label => $amount) {
        $resolvedLabel = trim((string)$label);
        $resolvedAmount = round((float)$amount, 2);
        if ($resolvedLabel === '' || $resolvedAmount <= 0) {
            continue;
        }

        $resolvedBreakdown[$resolvedLabel] = $resolvedAmount;
    }

    if ($resolvedBreakdown === []) {
        return ['label' => 'Tuition Fee', 'amount' => 0.0];
    }

    if (isset($resolvedBreakdown['Tuition Fee']) && $resolvedBreakdown['Tuition Fee'] > 0) {
        return ['label' => 'Tuition Fee', 'amount' => $resolvedBreakdown['Tuition Fee']];
    }

    $largestLabel = array_key_first($resolvedBreakdown);
    $largestAmount = (float)$resolvedBreakdown[$largestLabel];
    foreach ($resolvedBreakdown as $label => $amount) {
        if ($amount > $largestAmount) {
            $largestLabel = $label;
            $largestAmount = (float)$amount;
        }
    }

    return ['label' => $largestLabel, 'amount' => $largestAmount];
}

function smartenroll_build_dynamic_grade_payment_plans(string $gradeKey, array $breakdown, array $discountSettings = []): array
{
    $normalizedBreakdown = [];
    foreach (smartenroll_filter_grade_breakdown_components($breakdown, $gradeKey) as $label => $amount) {
        $resolvedLabel = trim((string)$label);
        $resolvedAmount = round((float)$amount, 2);
        if ($resolvedLabel === '' || $resolvedAmount <= 0) {
            continue;
        }

        $normalizedBreakdown[$resolvedLabel] = $resolvedAmount;
    }

    if ($normalizedBreakdown === []) {
        return [];
    }

    $coreComponent = smartenroll_resolve_core_breakdown_component($normalizedBreakdown);
    $coreSourceLabel = trim((string)($coreComponent['label'] ?? 'Tuition Fee'));
    $coreSourceAmount = round((float)($coreComponent['amount'] ?? 0), 2);
    if ($coreSourceAmount <= 0) {
        return [];
    }

    $otherComponents = $normalizedBreakdown;
    unset($otherComponents[$coreSourceLabel]);

    $resolvedDiscounts = smartenroll_normalize_payment_plan_discount_map($discountSettings);
    $resolvedPlans = [];

    foreach (smartenroll_payment_plan_defaults() as $planKey => $planMeta) {
        $installmentCount = max(1, (int)($planMeta['installment_count'] ?? 1));
        $coreOption = (string)($planMeta['core_option'] ?? 'Tuition Fee');
        $coreDiscountPercent = $resolvedDiscounts[$planKey] ?? 0.0;
        $coreBaseAmount = $coreOption === 'Monthly Payment'
            ? round($coreSourceAmount / $installmentCount, 2)
            : $coreSourceAmount;
        $coreDiscountFactor = max(0, 1 - ($coreDiscountPercent / 100));
        $coreAmount = round($coreBaseAmount * $coreDiscountFactor, 2);

        $items = [];
        $itemRules = [];

        if ($coreBaseAmount > 0) {
            $items[$coreOption] = $coreAmount;
            $itemRules[$coreOption] = [
                'amount' => $coreAmount,
                'base_amount' => $coreBaseAmount,
                'repeat_count' => 1,
                'discount_percent' => $coreDiscountPercent,
            ];
        }

        foreach ($otherComponents as $label => $amount) {
            $resolvedLabel = trim((string)$label);
            $resolvedAmount = round((float)$amount, 2);
            if ($resolvedLabel === '' || $resolvedAmount <= 0) {
                continue;
            }

            $items[$resolvedLabel] = $resolvedAmount;
            $itemRules[$resolvedLabel] = [
                'amount' => $resolvedAmount,
                'repeat_count' => 1,
                'discount_percent' => 0.0,
            ];
        }

        if ($items === []) {
            continue;
        }

        $standardTotal = 0.0;
        $programTotal = 0.0;
        foreach ($itemRules as $itemLabel => $rule) {
            $itemAmount = round((float)($rule['amount'] ?? 0), 2);
            $baseAmount = round((float)($rule['base_amount'] ?? $itemAmount), 2);
            $repeatCount = max(1, (int)($rule['repeat_count'] ?? 1));

            if ($itemLabel === $coreOption && $coreOption === 'Monthly Payment') {
                $standardTotal += round($baseAmount * $installmentCount, 2);
                $programTotal += round($itemAmount * $installmentCount, 2);
                continue;
            }

            $standardTotal += round($baseAmount * $repeatCount, 2);
            $programTotal += round($itemAmount * $repeatCount, 2);
        }

        $standardTotal = round($standardTotal, 2);
        $programTotal = round($programTotal, 2);
        if ($standardTotal <= 0) {
            continue;
        }

        $resolvedPlans[$planKey] = [
            'key' => $planKey,
            'label' => (string)($planMeta['label'] ?? $planKey),
            'core_option' => $coreOption,
            'installment_count' => $installmentCount,
            'program_total' => $programTotal,
            'standard_total' => $standardTotal,
            'discount_amount' => max(0, round($standardTotal - $programTotal, 2)),
            'discount_percent' => $coreDiscountPercent,
            'items' => $items,
            'item_rules' => $itemRules,
        ];
    }

    return $resolvedPlans;
}

function smartenroll_normalize_breakdown_components(array $components, string $gradeKey): array
{
    $normalized = [];
    foreach (smartenroll_filter_grade_breakdown_components($components, $gradeKey) as $label => $amount) {
        $resolvedLabel = trim((string)$label);
        $resolvedAmount = round((float)$amount, 2);
        if ($resolvedLabel === '' || $resolvedAmount <= 0) {
            continue;
        }

        $normalized[$resolvedLabel] = $resolvedAmount;
    }

    return $normalized;
}

function smartenroll_normalize_plan_breakdown_components(array $components, string $gradeKey): array
{
    $planKeys = array_keys(smartenroll_payment_plan_defaults());
    $hasPlanKeys = false;
    foreach ($planKeys as $planKey) {
        if (isset($components[$planKey]) && is_array($components[$planKey])) {
            $hasPlanKeys = true;
            break;
        }
    }

    if (!$hasPlanKeys) {
        $annualComponents = smartenroll_normalize_breakdown_components($components, $gradeKey);
        return $annualComponents !== [] ? ['annual' => $annualComponents] : [];
    }

    $normalized = [];
    foreach ($planKeys as $planKey) {
        $planComponents = isset($components[$planKey]) && is_array($components[$planKey])
            ? smartenroll_normalize_breakdown_components($components[$planKey], $gradeKey)
            : [];
        if ($planComponents !== []) {
            $normalized[$planKey] = $planComponents;
        }
    }

    return $normalized;
}

function smartenroll_get_saved_plan_breakdown_components(?string $gradeKey = null, ?mysqli $conn = null): array
{
    $savedComponents = smartenroll_get_saved_breakdown_components($gradeKey, $conn);

    if ($gradeKey !== null) {
        return smartenroll_normalize_plan_breakdown_components($savedComponents, $gradeKey);
    }

    $normalized = [];
    foreach ($savedComponents as $savedGradeKey => $components) {
        if (!is_array($components)) {
            continue;
        }

        $planComponents = smartenroll_normalize_plan_breakdown_components($components, (string)$savedGradeKey);
        if ($planComponents !== []) {
            $normalized[(string)$savedGradeKey] = $planComponents;
        }
    }

    return $normalized;
}

function smartenroll_build_payment_plan_from_components(string $planKey, array $planMeta, array $components, float $discountPercent): array
{
    $installmentCount = max(1, (int)($planMeta['installment_count'] ?? 1));
    $coreOption = (string)($planMeta['core_option'] ?? 'Tuition Fee');
    $coreLabel = array_key_exists($coreOption, $components) ? $coreOption : '';

    if ($coreLabel === '' && $coreOption === 'Monthly Payment' && array_key_exists('Tuition Fee', $components)) {
        $coreLabel = 'Tuition Fee';
    }

    if ($coreLabel === '') {
        $coreComponent = smartenroll_resolve_core_breakdown_component($components);
        $coreLabel = trim((string)($coreComponent['label'] ?? ''));
    }

    $coreSourceAmount = round((float)($components[$coreLabel] ?? 0), 2);
    if ($coreSourceAmount <= 0) {
        return [];
    }

    $coreBaseAmount = $coreOption === 'Monthly Payment' && $coreLabel !== 'Monthly Payment'
        ? round($coreSourceAmount / $installmentCount, 2)
        : $coreSourceAmount;
    $discountPercent = max(0, round($discountPercent, 2));
    $discountFactor = max(0, 1 - ($discountPercent / 100));
    $coreAmount = round($coreBaseAmount * $discountFactor, 2);

    $items = [];
    $itemRules = [];
    $items[$coreOption] = $coreAmount;
    $itemRules[$coreOption] = [
        'amount' => $coreAmount,
        'base_amount' => $coreBaseAmount,
        'repeat_count' => 1,
        'discount_percent' => $discountPercent,
    ];

    foreach ($components as $label => $amount) {
        if ((string)$label === $coreLabel) {
            continue;
        }

        $resolvedLabel = trim((string)$label);
        $resolvedAmount = round((float)$amount, 2);
        if ($resolvedLabel === '' || $resolvedAmount <= 0) {
            continue;
        }

        $items[$resolvedLabel] = $resolvedAmount;
        $itemRules[$resolvedLabel] = [
            'amount' => $resolvedAmount,
            'base_amount' => $resolvedAmount,
            'repeat_count' => 1,
            'discount_percent' => 0.0,
        ];
    }

    $standardTotal = 0.0;
    $programTotal = 0.0;
    foreach ($itemRules as $itemLabel => $rule) {
        $itemAmount = round((float)($rule['amount'] ?? 0), 2);
        $baseAmount = round((float)($rule['base_amount'] ?? $itemAmount), 2);
        $repeatCount = max(1, (int)($rule['repeat_count'] ?? 1));

        if ($itemLabel === $coreOption && $coreOption === 'Monthly Payment') {
            $standardTotal += round($baseAmount * $installmentCount, 2);
            $programTotal += round($itemAmount * $installmentCount, 2);
            continue;
        }

        $standardTotal += round($baseAmount * $repeatCount, 2);
        $programTotal += round($itemAmount * $repeatCount, 2);
    }

    $standardTotal = round($standardTotal, 2);
    $programTotal = round($programTotal, 2);
    if ($standardTotal <= 0) {
        return [];
    }

    return [
        'key' => $planKey,
        'label' => (string)($planMeta['label'] ?? $planKey),
        'core_option' => $coreOption,
        'installment_count' => $installmentCount,
        'program_total' => $programTotal,
        'standard_total' => $standardTotal,
        'discount_amount' => max(0, round($standardTotal - $programTotal, 2)),
        'discount_percent' => $discountPercent,
        'items' => $items,
        'item_rules' => $itemRules,
    ];
}

function smartenroll_resolve_grade_payment_plans(string $gradeValue, ?mysqli $conn = null, ?array $lookup = null): array
{
    $row = smartenroll_find_grade_level($gradeValue, $conn, $lookup);
    $gradeKey = trim((string)($row['grade_key'] ?? $gradeValue));
    $discountSettings = smartenroll_default_payment_plan_discount_map();
    try {
        $savedDiscountSettings = smartenroll_get_payment_plan_settings($gradeKey, $conn);
        if ($savedDiscountSettings !== []) {
            $discountSettings = $savedDiscountSettings;
        }
    } catch (Throwable $e) {
        $discountSettings = smartenroll_default_payment_plan_discount_map();
    }

    $savedPlanBreakdowns = [];
    try {
        $savedPlanBreakdowns = smartenroll_get_saved_plan_breakdown_components($gradeKey, $conn);
    } catch (Throwable $e) {
        $savedPlanBreakdowns = [];
    }

    $templates = smartenroll_grade_payment_plan_templates();
    $gradePlans = $templates[$gradeKey] ?? [];
    $resolved = [];

    foreach (smartenroll_payment_plan_defaults() as $planKey => $planMeta) {
        $planDiscountPercent = max(0, round((float)($discountSettings[$planKey] ?? $planMeta['discount_percent'] ?? 0), 2));
        if (isset($savedPlanBreakdowns[$planKey]) && is_array($savedPlanBreakdowns[$planKey]) && $savedPlanBreakdowns[$planKey] !== []) {
            $savedPlan = smartenroll_build_payment_plan_from_components(
                $planKey,
                $planMeta,
                $savedPlanBreakdowns[$planKey],
                $planDiscountPercent
            );
            if ($savedPlan !== []) {
                $resolved[$planKey] = $savedPlan;
                continue;
            }
        }

        $template = $gradePlans[$planKey] ?? null;
        if (!is_array($template)) {
            continue;
        }

        $coreOption = (string)($planMeta['core_option'] ?? 'Tuition Fee');
        $installmentCount = max(1, (int)($planMeta['installment_count'] ?? 1));
        $items = [];
        $itemRules = [];
        foreach ((array)($template['items'] ?? []) as $label => $amount) {
            $itemLabel = trim((string)$label);
            $rawItemAmount = 0.0;
            $repeatCount = 1;
            $discountPercent = 0.0;
            $baseAmount = 0.0;
            if (is_array($amount)) {
                $rawItemAmount = round((float)($amount['amount'] ?? 0), 2);
                $repeatCount = max(1, (int)($amount['repeat_count'] ?? 1));
                $discountPercent = max(0, round((float)($amount['discount_percent'] ?? 0), 2));
                $baseAmount = round((float)($amount['base_amount'] ?? 0), 2);
            } else {
                $rawItemAmount = round((float)$amount, 2);
            }
            if ($itemLabel === '' || $rawItemAmount <= 0) {
                continue;
            }
            if ($itemLabel === $coreOption && $discountPercent <= 0 && $planDiscountPercent > 0) {
                $discountPercent = $planDiscountPercent;
            }
            if ($baseAmount <= 0) {
                $baseAmount = $rawItemAmount;
            }

            $discountFactor = max(0, 1 - ($discountPercent / 100));
            $itemAmount = $discountPercent > 0
                ? round($baseAmount * $discountFactor, 2)
                : $rawItemAmount;
            $items[$itemLabel] = $itemAmount;
            $itemRules[$itemLabel] = [
                'amount' => $itemAmount,
                'base_amount' => $baseAmount,
                'repeat_count' => $repeatCount,
                'discount_percent' => $discountPercent,
            ];
        }

        if ($items === []) {
            continue;
        }

        $standardTotal = 0.0;
        $programTotal = 0.0;
        foreach ($itemRules as $itemLabel => $rule) {
            $itemAmount = round((float)($rule['amount'] ?? 0), 2);
            $baseAmount = round((float)($rule['base_amount'] ?? $itemAmount), 2);
            $repeatCount = max(1, (int)($rule['repeat_count'] ?? 1));

            if ($itemLabel === $coreOption && $coreOption === 'Monthly Payment') {
                $standardTotal += round($baseAmount * $installmentCount, 2);
                $programTotal += round($itemAmount * $installmentCount, 2);
                continue;
            }

            $standardTotal += round($baseAmount * $repeatCount, 2);
            $programTotal += round($itemAmount * $repeatCount, 2);
        }

        $standardTotal = round($standardTotal, 2);
        $programTotal = round($programTotal, 2);
        if ($standardTotal <= 0) {
            continue;
        }

        $resolved[$planKey] = [
            'key' => $planKey,
            'label' => (string)($planMeta['label'] ?? $planKey),
            'core_option' => $coreOption,
            'installment_count' => $installmentCount,
            'program_total' => $programTotal,
            'standard_total' => $standardTotal,
            'discount_amount' => max(0, round($standardTotal - $programTotal, 2)),
            'discount_percent' => $planDiscountPercent,
            'items' => $items,
            'item_rules' => $itemRules,
        ];
    }

    if ($resolved !== []) {
        return $resolved;
    }

    $fallbackTotal = round((float)($row['tuition_fee'] ?? 0), 2);
    if ($fallbackTotal <= 0) {
        return [];
    }

    return [
        'annual' => [
            'key' => 'annual',
            'label' => 'Annual Payment',
            'core_option' => 'Tuition Fee',
            'installment_count' => 1,
            'program_total' => $fallbackTotal,
            'standard_total' => $fallbackTotal,
            'discount_amount' => 0.0,
            'discount_percent' => 0.0,
            'items' => [
                'Tuition Fee' => $fallbackTotal,
            ],
            'item_rules' => [
                'Tuition Fee' => [
                    'amount' => $fallbackTotal,
                    'repeat_count' => 1,
                ],
            ],
        ],
    ];
}

function smartenroll_resolve_grade_payment_plan(string $gradeValue, string $planKey = 'annual', ?mysqli $conn = null, ?array $lookup = null): array
{
    $plans = smartenroll_resolve_grade_payment_plans($gradeValue, $conn, $lookup);
    if ($plans === []) {
        return [];
    }

    $normalizedPlanKey = smartenroll_normalize_payment_plan_key($planKey);
    return $plans[$normalizedPlanKey] ?? $plans['annual'] ?? reset($plans);
}

function smartenroll_resolve_grade_payment_plan_total(string $gradeValue, string $planKey = 'annual', ?mysqli $conn = null, ?array $lookup = null): ?float
{
    $plan = smartenroll_resolve_grade_payment_plan($gradeValue, $planKey, $conn, $lookup);
    if ($plan !== []) {
        return round((float)($plan['program_total'] ?? 0), 2);
    }

    $row = smartenroll_find_grade_level($gradeValue, $conn, $lookup);
    return $row !== null ? round((float)($row['tuition_fee'] ?? 0), 2) : null;
}

function smartenroll_resolve_grade_tuition_fee(string $gradeValue, ?mysqli $conn = null, ?array $lookup = null): ?float
{
    return smartenroll_resolve_grade_payment_plan_total($gradeValue, 'annual', $conn, $lookup);
}

function smartenroll_ensure_breakdown_components_table(?mysqli $conn = null): void
{
    $ownsConnection = false;
    if ($conn === null) {
        $conn = new mysqli('127.0.0.1', 'root', '', 'smartenroll');
        $conn->set_charset('utf8mb4');
        $ownsConnection = true;
    }

    try {
        $conn->query("
            CREATE TABLE IF NOT EXISTS `grade_breakdown_components` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `grade_key` VARCHAR(255) UNIQUE NOT NULL,
                `components` JSON NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } finally {
        if ($ownsConnection && $conn) {
            $conn->close();
        }
    }
}

function smartenroll_get_saved_breakdown_components(?string $gradeKey = null, ?mysqli $conn = null): array
{
    $ownsConnection = false;
    if ($conn === null) {
        $conn = new mysqli('127.0.0.1', 'root', '', 'smartenroll');
        $conn->set_charset('utf8mb4');
        $ownsConnection = true;
    }

    try {
        smartenroll_ensure_breakdown_components_table($conn);

        if ($gradeKey !== null) {
            $gradeKey = trim($gradeKey);
            $stmt = $conn->prepare("SELECT components FROM `grade_breakdown_components` WHERE grade_key = ? LIMIT 1");
            $stmt->bind_param('s', $gradeKey);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();

            if ($row !== null) {
                $components = json_decode((string)($row['components'] ?? '{}'), true);
                $components = is_array($components) ? $components : [];
                return smartenroll_normalize_plan_breakdown_components($components, $gradeKey) !== []
                    ? $components
                    : smartenroll_filter_grade_breakdown_components($components, $gradeKey);
            }
            return [];
        }

        // Get all components
        $result = $conn->query("SELECT grade_key, components FROM `grade_breakdown_components`");
        $allComponents = [];
        while ($row = $result->fetch_assoc()) {
            $key = (string)($row['grade_key'] ?? '');
            $components = json_decode((string)($row['components'] ?? '{}'), true);
            $components = is_array($components) ? $components : [];
            $allComponents[$key] = smartenroll_normalize_plan_breakdown_components($components, $key) !== []
                ? $components
                : smartenroll_filter_grade_breakdown_components($components, $key);
        }
        return $allComponents;
    } finally {
        if ($ownsConnection && $conn) {
            $conn->close();
        }
    }
}

function smartenroll_get_grade_breakdown_map(?mysqli $conn = null): array
{
    $tuitionMap = smartenroll_get_grade_tuition_map($conn);
    $templates = smartenroll_grade_breakdown_templates();
    $savedPlanComponents = smartenroll_get_saved_plan_breakdown_components(null, $conn);
    $result = [];

    foreach ($tuitionMap as $gradeKey => $tuitionFee) {
        if (isset($savedPlanComponents[$gradeKey]['annual']) && !empty($savedPlanComponents[$gradeKey]['annual'])) {
            $result[$gradeKey] = smartenroll_filter_grade_breakdown_components($savedPlanComponents[$gradeKey]['annual'], $gradeKey);
        } else {
            $template = $templates[$gradeKey] ?? ['Tuition Fee' => $tuitionFee];
            $result[$gradeKey] = smartenroll_filter_grade_breakdown_components($template, $gradeKey);
        }
    }

    return $result;
}

function smartenroll_template_plan_components(array $templateItems, string $gradeKey): array
{
    $components = [];
    foreach ($templateItems as $label => $amount) {
        $componentLabel = trim((string)$label);
        if ($componentLabel === '') {
            continue;
        }

        if (is_array($amount)) {
            $componentAmount = round((float)($amount['base_amount'] ?? $amount['amount'] ?? 0), 2);
        } else {
            $componentAmount = round((float)$amount, 2);
        }

        if ($componentAmount > 0) {
            $components[$componentLabel] = $componentAmount;
        }
    }

    return smartenroll_filter_grade_breakdown_components($components, $gradeKey);
}

function smartenroll_get_grade_plan_breakdown_map(?mysqli $conn = null): array
{
    $tuitionMap = smartenroll_get_grade_tuition_map($conn);
    $templates = smartenroll_grade_payment_plan_templates();
    $savedPlanComponents = smartenroll_get_saved_plan_breakdown_components(null, $conn);
    $result = [];

    foreach ($tuitionMap as $gradeKey => $tuitionFee) {
        $gradePlanMap = [];
        foreach (smartenroll_payment_plan_defaults() as $planKey => $planMeta) {
            if (isset($savedPlanComponents[$gradeKey][$planKey]) && $savedPlanComponents[$gradeKey][$planKey] !== []) {
                $gradePlanMap[$planKey] = $savedPlanComponents[$gradeKey][$planKey];
                continue;
            }

            $templateItems = $templates[$gradeKey][$planKey]['items'] ?? null;
            if (is_array($templateItems) && $templateItems !== []) {
                $gradePlanMap[$planKey] = smartenroll_template_plan_components($templateItems, (string)$gradeKey);
                continue;
            }

            $coreOption = (string)($planMeta['core_option'] ?? 'Tuition Fee');
            $fallbackAmount = $coreOption === 'Monthly Payment'
                ? round((float)$tuitionFee / max(1, (int)($planMeta['installment_count'] ?? 1)), 2)
                : round((float)$tuitionFee, 2);
            $gradePlanMap[$planKey] = [$coreOption => $fallbackAmount];
        }

        $result[(string)$gradeKey] = $gradePlanMap;
    }

    return $result;
}

function smartenroll_resolve_grade_breakdown(string $gradeValue, ?mysqli $conn = null, ?array $lookup = null): array
{
    $row = smartenroll_find_grade_level($gradeValue, $conn, $lookup);
    if ($row === null) {
        return [];
    }

    $gradeKey = (string)($row['grade_key'] ?? '');
    $tuitionFee = round((float)($row['tuition_fee'] ?? 0), 2);
    
    $savedPlanComponents = smartenroll_get_saved_plan_breakdown_components($gradeKey, $conn);
    if (!empty($savedPlanComponents['annual'])) {
        return $savedPlanComponents['annual'];
    }

    $template = smartenroll_grade_breakdown_templates()[$gradeKey] ?? ['Tuition Fee' => $tuitionFee];
    return smartenroll_filter_grade_breakdown_components($template, $gradeKey);
}

function smartenroll_payment_item_discount_percent(array $item): float
{
    return max(0, round((float)($item['discount_percent'] ?? 0), 2));
}

function smartenroll_payment_item_base_amount(array $item): float
{
    $baseAmount = round((float)($item['base_amount'] ?? 0), 2);
    if ($baseAmount > 0) {
        return $baseAmount;
    }

    return round((float)($item['amount'] ?? 0), 2);
}

function smartenroll_payment_item_discount_amount(array $item): float
{
    $amount = round((float)($item['amount'] ?? 0), 2);
    $baseAmount = smartenroll_payment_item_base_amount($item);
    return max(0, round($baseAmount - $amount, 2));
}

function smartenroll_payment_item_credit_amount(array $item, ?float $fallbackAmount = null): float
{
    $amount = round((float)($item['amount'] ?? ($fallbackAmount ?? 0)), 2);
    $baseAmount = round((float)($item['base_amount'] ?? 0), 2);
    $discountPercent = smartenroll_payment_item_discount_percent($item);
    $option = trim((string)($item['option'] ?? $item['label'] ?? ''));
    $isLegacyDiscountPlan = in_array($option, ['Annual Payment Plan', 'Semestral Payment Plan', 'Monthly Payment Plan'], true);

    if ($isLegacyDiscountPlan && $discountPercent > 0 && $baseAmount > 0) {
        return $baseAmount;
    }

    return $amount;
}

function smartenroll_payment_items_credit_total(array $items, float $fallbackAmount = 0.0): float
{
    if ($items === []) {
        return round(max(0, $fallbackAmount), 2);
    }

    $total = 0.0;
    $hasValidItem = false;

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $creditAmount = smartenroll_payment_item_credit_amount($item);
        if ($creditAmount <= 0) {
            continue;
        }

        $total += $creditAmount;
        $hasValidItem = true;
    }

    return round($hasValidItem ? $total : max(0, $fallbackAmount), 2);
}

function smartenroll_payment_items_credit_total_from_json(?string $rawJson, float $fallbackAmount = 0.0): float
{
    $decoded = json_decode((string)$rawJson, true);
    if (!is_array($decoded)) {
        return round(max(0, $fallbackAmount), 2);
    }

    return smartenroll_payment_items_credit_total($decoded, $fallbackAmount);
}

function smartenroll_sync_tuition_payment_totals(?mysqli $conn = null): int
{
    $ownsConnection = false;
    $db = smartenroll_grade_level_connection($conn, $ownsConnection);

    try {
        $tableCheck = $db->query("SHOW TABLES LIKE 'tuition_payments'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
            if ($tableCheck) {
                $tableCheck->close();
            }
            return 0;
        }
        $tableCheck->close();

        $paymentPlanCheck = $db->query("SHOW COLUMNS FROM tuition_payments LIKE 'payment_plan'");
        if ($paymentPlanCheck && $paymentPlanCheck->num_rows === 0) {
            $db->query("ALTER TABLE tuition_payments ADD COLUMN payment_plan VARCHAR(32) DEFAULT '' AFTER grade_level");
        }
        if ($paymentPlanCheck) {
            $paymentPlanCheck->close();
        }

        $rows = [];
        $result = $db->query(
            "SELECT
                tp.id,
                tp.enrollment_id,
                tp.student_id,
                COALESCE(tp.school_year, '') AS school_year,
                COALESCE(tp.grade_level, '') AS stored_grade_level,
                COALESCE(tp.payment_plan, '') AS payment_plan,
                COALESCE(e.grade_level, '') AS current_grade_level,
                tp.tuition_fee,
                tp.payment_items,
                tp.amount_paid,
                tp.balance_after,
                tp.payment_date
             FROM tuition_payments tp
             LEFT JOIN enrollments e ON e.id = tp.enrollment_id
             ORDER BY tp.enrollment_id ASC, tp.student_id ASC, school_year ASC, COALESCE(tp.grade_level, e.grade_level, '') ASC, tp.payment_date ASC, tp.id ASC"
        );

        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->close();

        if ($rows === []) {
            return 0;
        }

        $lookup = smartenroll_get_grade_level_lookup($db);
        $runningByGroup = [];
        $updated = 0;
        $updateStmt = $db->prepare(
            "UPDATE tuition_payments
             SET grade_level = ?, tuition_fee = ?, balance_after = ?
             WHERE id = ?"
        );

        foreach ($rows as $row) {
            $enrollmentId = (int)($row['enrollment_id'] ?? 0);
            $studentId = trim((string)($row['student_id'] ?? ''));
            $schoolYear = trim((string)($row['school_year'] ?? ''));
            $storedGradeLevel = trim((string)($row['stored_grade_level'] ?? ''));
            $storedPaymentPlan = trim((string)($row['payment_plan'] ?? ''));
            $currentGradeLevel = trim((string)($row['current_grade_level'] ?? ''));
            $resolvedGradeLevel = $storedGradeLevel !== '' ? $storedGradeLevel : $currentGradeLevel;
            $paymentPlanKey = $storedPaymentPlan !== ''
                ? smartenroll_normalize_payment_plan_key($storedPaymentPlan)
                : 'annual';
            $groupKey = ($enrollmentId > 0 ? 'enrollment:' . $enrollmentId : 'student:' . $studentId)
                . '|'
                . $schoolYear
                . '|'
                . $resolvedGradeLevel
                . '|'
                . $paymentPlanKey;

            if (!array_key_exists($groupKey, $runningByGroup)) {
                $runningByGroup[$groupKey] = 0.0;
            }

            $storedTuitionFee = round((float)($row['tuition_fee'] ?? 0), 2);
            if ($storedPaymentPlan !== '') {
                $resolvedPlanTotal = smartenroll_resolve_grade_payment_plan_total($resolvedGradeLevel, $paymentPlanKey, $db, $lookup);
                $tuitionFee = $storedTuitionFee > 0 ? $storedTuitionFee : round((float)($resolvedPlanTotal ?? 0), 2);
            } else {
                $resolvedTuitionFee = smartenroll_resolve_grade_tuition_fee($resolvedGradeLevel, $db, $lookup);
                $tuitionFee = $resolvedTuitionFee ?? $storedTuitionFee;
            }

            $runningByGroup[$groupKey] += smartenroll_payment_items_credit_total_from_json(
                (string)($row['payment_items'] ?? ''),
                (float)($row['amount_paid'] ?? 0)
            );
            $computedBalance = max(0, round($tuitionFee - $runningByGroup[$groupKey], 2));
            $storedBalance = round((float)($row['balance_after'] ?? 0), 2);

            if (
                abs($tuitionFee - $storedTuitionFee) >= 0.01 ||
                abs($computedBalance - $storedBalance) >= 0.01 ||
                ($storedGradeLevel === '' && $resolvedGradeLevel !== '')
            ) {
                $rowId = (int)($row['id'] ?? 0);
                $updateStmt->bind_param('sddi', $resolvedGradeLevel, $tuitionFee, $computedBalance, $rowId);
                $updateStmt->execute();
                $updated++;
            }
        }

        $updateStmt->close();
        return $updated;
    } finally {
        if ($ownsConnection) {
            $db->close();
        }
    }
}
