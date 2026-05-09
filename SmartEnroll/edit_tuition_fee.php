<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/enrollment_form_config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

smartenroll_require_role('finance');

function smartenroll_parse_fee_value(mixed $value): float
{
    $raw = trim((string)$value);
    if ($raw === '') {
        return 0.0;
    }

    $normalized = str_replace(',', '', $raw);
    if (!is_numeric($normalized)) {
        throw new RuntimeException('Fee must be a valid number.');
    }

    $fee = round((float)$normalized, 2);
    if ($fee < 0) {
        throw new RuntimeException('Fee cannot be negative.');
    }

    return $fee;
}

function smartenroll_parse_discount_percent(mixed $value): float
{
    $raw = trim((string)$value);
    if ($raw === '') {
        return 0.0;
    }

    $normalized = str_replace(',', '', $raw);
    if (!is_numeric($normalized)) {
        throw new RuntimeException('Discount must be a valid number.');
    }

    $discount = round((float)$normalized, 2);
    if ($discount < 0 || $discount > 100) {
        throw new RuntimeException('Discount must be between 0 and 100 percent.');
    }

    return $discount;
}

function smartenroll_get_or_create_grade_breakdown_table(?mysqli $conn = null): void
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

function smartenroll_get_grade_breakdown_components(?mysqli $conn = null): array
{
    $ownsConnection = false;
    if ($conn === null) {
        $conn = new mysqli('127.0.0.1', 'root', '', 'smartenroll');
        $conn->set_charset('utf8mb4');
        $ownsConnection = true;
    }

    try {
        smartenroll_get_or_create_grade_breakdown_table($conn);
        
        $result = $conn->query("SELECT grade_key, components FROM `grade_breakdown_components`");
        $components = [];
        
        while ($row = $result->fetch_assoc()) {
            $gradeKey = (string)($row['grade_key'] ?? '');
            $componentsJson = (string)($row['components'] ?? '{}');
            $components[$gradeKey] = json_decode($componentsJson, true) ?? [];
        }

        return $components;
    } finally {
        if ($ownsConnection && $conn) {
            $conn->close();
        }
    }
}

function smartenroll_save_grade_breakdown_components(array $breakdownData, ?mysqli $conn = null): void
{
    $ownsConnection = false;
    if ($conn === null) {
        $conn = new mysqli('127.0.0.1', 'root', '', 'smartenroll');
        $conn->set_charset('utf8mb4');
        $ownsConnection = true;
    }

    try {
        smartenroll_get_or_create_grade_breakdown_table($conn);

        foreach ($breakdownData as $gradeKey => $components) {
            $componentsJson = json_encode($components, JSON_UNESCAPED_SLASHES);
            
            $stmt = $conn->prepare("
                INSERT INTO `grade_breakdown_components` (grade_key, components)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE
                    components = VALUES(components),
                    updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->bind_param('ss', $gradeKey, $componentsJson);
            $stmt->execute();
            $stmt->close();

            $annualComponents = is_array($components['annual'] ?? null) ? $components['annual'] : $components;
            $totalFee = array_sum(array_map(static fn($amount): float => (float)$amount, $annualComponents));
            $updateStmt = $conn->prepare("
                UPDATE `enrollment_grade_levels` 
                SET tuition_fee = ? 
                WHERE grade_key = ?
            ");
            $updateStmt->bind_param('ds', $totalFee, $gradeKey);
            $updateStmt->execute();
            $updateStmt->close();
        }
    } finally {
        if ($ownsConnection && $conn) {
            $conn->close();
        }
    }
}

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'save_tuition_breakdown') {
    try {
        $breakdownData = [];
        $discountSettings = [];
        $formComponents = $_POST['components'] ?? [];
        $formDiscounts = $_POST['discounts'] ?? [];

        if (is_array($formDiscounts)) {
            foreach ($formDiscounts as $gradeKey => $gradeDiscountData) {
                $gradeKey = trim((string)$gradeKey);
                if ($gradeKey === '' || !is_array($gradeDiscountData)) {
                    continue;
                }

                foreach (array_keys(smartenroll_payment_plan_defaults()) as $planKey) {
                    $discountSettings[$gradeKey][$planKey] = smartenroll_parse_discount_percent(
                        $gradeDiscountData[$planKey] ?? 0
                    );
                }
            }
        }

        foreach ($formComponents as $gradeKey => $gradeComponentData) {
            $gradeKey = trim((string)$gradeKey);
            if ($gradeKey === '') {
                continue;
            }

            if (!is_array($gradeComponentData)) {
                continue;
            }

            $gradePlanComponents = [];
            foreach (array_keys(smartenroll_payment_plan_defaults()) as $planKey) {
                $planComponentData = $gradeComponentData[$planKey] ?? [];
                if (!is_array($planComponentData) || !isset($planComponentData['name'], $planComponentData['amount'])) {
                    continue;
                }

                $planComponents = [];
                $componentNames = is_array($planComponentData['name']) ? $planComponentData['name'] : [];
                $componentAmounts = is_array($planComponentData['amount']) ? $planComponentData['amount'] : [];

                foreach ($componentNames as $componentIndex => $rawComponentName) {
                    $componentName = trim((string)$rawComponentName);
                    if ($componentName === '') {
                        continue;
                    }

                    $amount = smartenroll_parse_fee_value($componentAmounts[$componentIndex] ?? '');
                    if ($amount <= 0) {
                        continue;
                    }

                    $planComponents[$componentName] = $amount;
                }

                if (in_array($gradeKey, ['Toddler', 'Casa', 'Kindergarten'], true)) {
                    unset($planComponents['Books']);
                }

                if ($planComponents !== []) {
                    $gradePlanComponents[$planKey] = $planComponents;
                }
            }

            if ($gradePlanComponents !== []) {
                $breakdownData[$gradeKey] = $gradePlanComponents;
            }
        }

        smartenroll_save_grade_breakdown_components($breakdownData);
        smartenroll_save_payment_plan_settings($discountSettings);
        smartenroll_sync_tuition_payment_totals();
        header('Location: edit_tuition_fee.php?status=saved');
        exit;
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

if (($_GET['status'] ?? '') === 'saved') {
    $successMessage = 'Tuition fee breakdown was updated successfully.';
}

$gradeLevels = [];
$gradeBreakdowns = [];
$gradePaymentPlans = [];
$paymentPlanSettingsByGrade = [];

try {
    $gradeLevels = smartenroll_get_grade_levels();
    $gradePlanBreakdownMap = smartenroll_get_grade_plan_breakdown_map();
    $paymentPlanSettingsByGrade = smartenroll_get_payment_plan_settings();

    foreach ($gradeLevels as $grade) {
        $gradeKey = (string)$grade['grade_key'];
        $gradeBreakdowns[$gradeKey] = $gradePlanBreakdownMap[$gradeKey] ?? [];
        $gradePaymentPlans[$gradeKey] = smartenroll_resolve_grade_payment_plans($gradeKey);
    }
} catch (Throwable $e) {
    $gradeLevels = [];
    $gradeBreakdowns = [];
    $gradePaymentPlans = [];
    $paymentPlanSettingsByGrade = [];
    if ($errorMessage === '') {
        $errorMessage = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SMARTENROLL | Edit Tuition Fee</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/editable_enrollment_form.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="dashboard-page dashboard-white-page">

<main class="dashboard-main">
    <div class="dashboard-header student-header">
        <div class="student-header-left">
            <a href="dashboard.php" class="dashboard-link back-left">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div class="student-header-title">
                <h1>Edit Tuition Fee</h1>
                <p>Update the tuition fee amount for each grade level.</p>
            </div>
        </div>
    </div>

    <div class="settings-card">
        <?php if ($successMessage !== ''): ?>
            <div class="settings-alert success"><?php echo htmlspecialchars($successMessage); ?></div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="settings-alert error"><?php echo htmlspecialchars($errorMessage); ?></div>
        <?php endif; ?>

        <div class="settings-intro">
            <h2>Tuition Fees by Grade Level</h2>
            <p>Edit the annual fee breakdown and review the exact payment-plan rates used in tuition receipt details.</p>
        </div>

        <form method="post">
            <input type="hidden" name="form_action" value="save_tuition_breakdown">

            <?php foreach ($gradeLevels as $gradeRow): ?>
                <?php $gradeKey = (string)$gradeRow['grade_key']; ?>
                <?php $gradeLabel = (string)$gradeRow['grade_label']; ?>
                <?php $breakdown = $gradeBreakdowns[$gradeKey] ?? []; ?>
                <?php $planSummary = $gradePaymentPlans[$gradeKey] ?? []; ?>
                <?php $gradePlanDiscounts = smartenroll_normalize_payment_plan_discount_map($paymentPlanSettingsByGrade[$gradeKey] ?? []); ?>
                <?php $gradePlanDiscountsJson = htmlspecialchars((string)json_encode($gradePlanDiscounts, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?>

                <div
                    class="settings-subsection"
                    data-grade-key="<?php echo htmlspecialchars($gradeKey, ENT_QUOTES); ?>"
                    data-plan-discounts="<?php echo $gradePlanDiscountsJson; ?>"
                >
                    <h3 class="detail-section-title"><?php echo htmlspecialchars($gradeLabel); ?></h3>
                    
                    <input type="hidden" name="grade_key[]" value="<?php echo htmlspecialchars($gradeKey); ?>">

                    <div class="grade-plan-panel">
                        <div class="grade-plan-panel-head">
                            <div>
                                <span class="grade-plan-eyebrow">Payment Plan Rates</span>
                                <p>These are the exact rates currently used in tuition receipt details for this grade level.</p>
                            </div>
                            <div class="grade-discount-grid" aria-label="Payment plan discounts">
                                <?php foreach (smartenroll_payment_plan_defaults() as $discountPlanKey => $discountPlanMeta): ?>
                                    <label class="grade-discount-field">
                                        <span><?php echo htmlspecialchars((string)($discountPlanMeta['label'] ?? ucfirst($discountPlanKey))); ?></span>
                                        <input
                                            type="number"
                                            name="discounts[<?php echo htmlspecialchars($gradeKey, ENT_QUOTES); ?>][<?php echo htmlspecialchars($discountPlanKey, ENT_QUOTES); ?>]"
                                            value="<?php echo htmlspecialchars(number_format((float)($gradePlanDiscounts[$discountPlanKey] ?? 0), 2, '.', '')); ?>"
                                            min="0"
                                            max="100"
                                            step="0.01"
                                            inputmode="decimal"
                                            class="plan-discount-input"
                                            data-plan-discount-input="<?php echo htmlspecialchars($discountPlanKey, ENT_QUOTES); ?>"
                                        >
                                        <small>%</small>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="grade-plan-grid">
                            <?php foreach (smartenroll_payment_plan_defaults() as $planKey => $planMeta): ?>
                                <?php
                                    $plan = isset($planSummary[$planKey]) && is_array($planSummary[$planKey])
                                        ? $planSummary[$planKey]
                                        : [];
                                    $planRules = is_array($plan['item_rules'] ?? null) ? $plan['item_rules'] : [];
                                    if ($planRules === []) {
                                        $fallbackComponents = is_array($breakdown[$planKey] ?? null) ? $breakdown[$planKey] : [];
                                        foreach ($fallbackComponents as $fallbackLabel => $fallbackAmount) {
                                            $resolvedLabel = trim((string)$fallbackLabel);
                                            if ($resolvedLabel === '') {
                                                continue;
                                            }

                                            $resolvedAmount = max(0, round((float)$fallbackAmount, 2));
                                            $planRules[$resolvedLabel] = [
                                                'amount' => $resolvedAmount,
                                                'base_amount' => $resolvedAmount,
                                                'repeat_count' => 1,
                                                'discount_percent' => 0,
                                            ];
                                        }
                                    }
                                    if ($planRules === []) {
                                        $coreFallback = (string)($planMeta['core_option'] ?? 'Tuition Fee');
                                        $planRules[$coreFallback] = [
                                            'amount' => 0,
                                            'base_amount' => 0,
                                            'repeat_count' => 1,
                                            'discount_percent' => 0,
                                        ];
                                    }
                                    $paymentCountLabel = $planKey === 'monthly'
                                        ? '10 months'
                                        : ($planKey === 'semestral' ? '2 payments' : '1 payment');
                                    $coreOption = trim((string)($plan['core_option'] ?? $planMeta['core_option'] ?? 'Tuition Fee'));
                                    $planDiscountAmount = max(0, round((float)($plan['discount_amount'] ?? 0), 2));
                                    $planDiscountPercent = max(0, round((float)($plan['discount_percent'] ?? 0), 2));
                                    $planDiscountLabel = $planDiscountAmount > 0
                                        ? 'Discount: PHP ' . number_format($planDiscountAmount, 2)
                                            . ($planDiscountPercent > 0 ? ' (' . number_format($planDiscountPercent, 0) . '%)' : '')
                                        : 'No discount';
                                ?>
                                <div
                                    class="grade-plan-field<?php echo $planKey === 'annual' ? ' is-open' : ''; ?>"
                                    data-plan-key="<?php echo htmlspecialchars($planKey, ENT_QUOTES); ?>"
                                    role="button"
                                    tabindex="0"
                                    aria-expanded="<?php echo $planKey === 'annual' ? 'true' : 'false'; ?>"
                                >
                                    <span class="grade-plan-label-row">
                                        <strong><?php echo htmlspecialchars((string)($plan['label'] ?? $planMeta['label'] ?? ucfirst($planKey))); ?></strong>
                                        <small><?php echo htmlspecialchars($paymentCountLabel); ?></small>
                                    </span>
                                    <span class="grade-plan-total" data-plan-total>
                                        Total: <?php echo htmlspecialchars('PHP ' . number_format((float)($plan['standard_total'] ?? $plan['program_total'] ?? 0), 2)); ?>
                                    </span>
                                    <span class="grade-plan-discount-note" data-plan-discount>
                                        <?php echo htmlspecialchars($planDiscountLabel); ?>
                                    </span>
                                    <div class="grade-plan-rate-list" data-plan-rate-list>
                                        <?php foreach ($planRules as $itemLabel => $rule): ?>
                                            <?php
                                                $displayLabel = trim((string)$itemLabel);
                                                if ($planKey === 'monthly' && $displayLabel === $coreOption) {
                                                    $displayLabel = 'Monthly Tuition Fee';
                                                }
                                                $repeatCount = max(1, (int)($rule['repeat_count'] ?? 1));
                                                $rateAmount = round((float)($rule['amount'] ?? 0), 2);
                                                $rateNote = $planKey === 'monthly' && trim((string)$itemLabel) === $coreOption
                                                    ? 'per month'
                                                    : ($repeatCount > 1 ? 'x ' . $repeatCount : '');
                                            ?>
                                            <div class="grade-plan-rate-row grade-plan-component-row">
                                                <input
                                                    type="text"
                                                    name="components[<?php echo htmlspecialchars($gradeKey, ENT_QUOTES); ?>][<?php echo htmlspecialchars($planKey, ENT_QUOTES); ?>][name][]"
                                                    value="<?php echo htmlspecialchars((string)$itemLabel); ?>"
                                                    class="fee-component-name-input"
                                                    placeholder="Component name"
                                                >
                                                <input
                                                    type="number"
                                                    name="components[<?php echo htmlspecialchars($gradeKey, ENT_QUOTES); ?>][<?php echo htmlspecialchars($planKey, ENT_QUOTES); ?>][amount][]"
                                                    value="<?php echo htmlspecialchars(number_format((float)($rule['base_amount'] ?? $rateAmount), 2, '.', '')); ?>"
                                                    min="0"
                                                    step="0.01"
                                                    inputmode="decimal"
                                                    class="fee-component-input"
                                                    placeholder="0.00"
                                                >
                                                <button type="button" class="component-remove-btn" aria-label="Remove component">&times;</button>
                                                <?php if ($rateNote !== ''): ?>
                                                    <small><?php echo htmlspecialchars($rateNote); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                        <div class="grade-plan-add-row">
                                            <button
                                                type="button"
                                                class="grade-add-btn grade-plan-add-btn"
                                                data-grade-key="<?php echo htmlspecialchars($gradeKey, ENT_QUOTES); ?>"
                                                data-plan-key="<?php echo htmlspecialchars($planKey, ENT_QUOTES); ?>"
                                            >+ Add component</button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="grade-total-row">
                        <strong>Selected Plan Original Total:</strong>
                        <span class="total-amount">PHP 0.00</span>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="settings-actions">
                <span class="settings-help">Use numbers only. Example: `72740.00`.</span>
                <button type="submit" class="settings-save-btn">Save Tuition Fees</button>
            </div>
        </form>
    </div>
</main>
<script src="js/script.js"></script>
<script>
    const PAYMENT_PLAN_DEFAULTS = <?php echo json_encode(smartenroll_payment_plan_defaults(), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    const RESTRICTED_BOOK_GRADES = new Set(['Toddler', 'Casa', 'Kindergarten']);

    function parseMoneyValue(value) {
        return parseFloat(String(value || '').replace(/,/g, '')) || 0;
    }

    function roundMoney(value) {
        return Math.round((Number(value || 0) + Number.EPSILON) * 100) / 100;
    }

    function formatNumber(value) {
        return Number(value || 0).toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function formatPHP(value) {
        return 'PHP ' + formatNumber(value);
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function collectPlanComponents(planField) {
        const section = planField.closest('.settings-subsection');
        const gradeKey = String(section?.dataset.gradeKey || '');
        const componentMap = new Map();

        planField.querySelectorAll('.grade-plan-component-row').forEach((row) => {
            const label = String(row.querySelector('.fee-component-name-input')?.value || '').trim().replace(/\s+/g, ' ');
            const amount = roundMoney(parseMoneyValue(row.querySelector('.fee-component-input')?.value || '0'));
            if (!label || amount <= 0) {
                return;
            }
            if (RESTRICTED_BOOK_GRADES.has(gradeKey) && label.toLowerCase() === 'books') {
                return;
            }

            componentMap.set(label.toLowerCase(), { label, amount });
        });

        return Array.from(componentMap.values());
    }

    function resolveCoreComponent(components, coreOption) {
        if (!components.length) {
            return null;
        }

        const exactCore = components.find((component) => component.label.toLowerCase() === String(coreOption || '').toLowerCase());
        if (exactCore) {
            return exactCore;
        }

        if (coreOption === 'Monthly Payment') {
            const tuitionFee = components.find((component) => component.label.toLowerCase() === 'tuition fee');
            if (tuitionFee) {
                return tuitionFee;
            }
        }

        return components.reduce((largest, component) => {
            return component.amount > largest.amount ? component : largest;
        }, components[0]);
    }

    function buildPlanPreview(section, planField) {
        const planKey = planField.dataset.planKey || '';
        const planMeta = PAYMENT_PLAN_DEFAULTS?.[planKey] || {};
        const installmentCount = Math.max(parseInt(planMeta.installment_count || 1, 10), 1);
        const coreOption = String(planMeta.core_option || 'Tuition Fee');
        const components = collectPlanComponents(planField);
        const coreComponent = resolveCoreComponent(components, coreOption);
        if (!coreComponent) {
            return null;
        }

        let discounts = {};
        try {
            discounts = JSON.parse(section.dataset.planDiscounts || '{}') || {};
        } catch (error) {
            discounts = {};
        }

        const otherComponents = components.filter((component) => component !== coreComponent);
        const discountPercent = Math.max(parseMoneyValue(discounts[planKey] ?? planMeta.discount_percent ?? 0), 0);
        const coreBaseAmount = coreOption === 'Monthly Payment' && coreComponent.label !== 'Monthly Payment'
            ? roundMoney(coreComponent.amount / installmentCount)
            : coreComponent.amount;
        const coreAmount = roundMoney(coreBaseAmount * Math.max(0, 1 - (discountPercent / 100)));
        const itemRules = {};

        if (coreBaseAmount > 0) {
            itemRules[coreOption] = {
                amount: coreAmount,
                base_amount: coreBaseAmount,
                repeat_count: 1,
                discount_percent: discountPercent
            };
        }

        otherComponents.forEach((component) => {
            itemRules[component.label] = {
                amount: component.amount,
                base_amount: component.amount,
                repeat_count: 1,
                discount_percent: 0
            };
        });

        let standardTotal = 0;
        let programTotal = 0;
        Object.entries(itemRules).forEach(([itemLabel, rule]) => {
            const itemAmount = roundMoney(rule.amount || 0);
            const baseAmount = roundMoney(rule.base_amount || itemAmount);
            const repeatCount = Math.max(parseInt(rule.repeat_count || 1, 10), 1);

            if (itemLabel === coreOption && coreOption === 'Monthly Payment') {
                standardTotal += roundMoney(baseAmount * installmentCount);
                programTotal += roundMoney(itemAmount * installmentCount);
                return;
            }

            standardTotal += roundMoney(baseAmount * repeatCount);
            programTotal += roundMoney(itemAmount * repeatCount);
        });

        return {
            label: String(planMeta.label || planKey),
            core_option: coreOption,
            installment_count: installmentCount,
            program_total: roundMoney(programTotal),
            standard_total: roundMoney(standardTotal),
            discount_amount: Math.max(roundMoney(standardTotal - programTotal), 0),
            discount_percent: discountPercent,
            item_rules: itemRules
        };
    }

    function updateGradePlanPreview(section) {
        let activePlan = null;

        section.querySelectorAll('.grade-plan-field[data-plan-key]').forEach((field) => {
            const plan = buildPlanPreview(section, field);
            const total = field.querySelector('[data-plan-total]');
            const discount = field.querySelector('[data-plan-discount]');

            if (!plan) {
                if (total) {
                    total.textContent = 'Total: ' + formatPHP(0);
                }
                if (discount) {
                    discount.textContent = 'No discount';
                }
                return;
            }

            if (field.classList.contains('is-open')) {
                activePlan = plan;
            }
            if (total) {
                total.textContent = 'Total: ' + formatPHP(plan.standard_total);
            }
            if (discount) {
                discount.textContent = plan.discount_amount > 0
                    ? 'Discount: ' + formatPHP(plan.discount_amount)
                        + (plan.discount_percent > 0 ? ' (' + formatNumber(plan.discount_percent).replace('.00', '') + '%)' : '')
                    : 'No discount';
            }
        });

        const totalAmount = section.querySelector('.total-amount');
        if (totalAmount) {
            totalAmount.textContent = formatPHP(activePlan?.standard_total || 0);
        }
    }

    function updateGradeTotals(section) {
        updateGradePlanPreview(section);
    }

    function createComponentRow(gradeKey, planKey) {
        const row = document.createElement('div');
        row.className = 'grade-plan-rate-row grade-plan-component-row';

        const nameInput = document.createElement('input');
        nameInput.type = 'text';
        nameInput.name = `components[${gradeKey}][${planKey}][name][]`;
        nameInput.className = 'fee-component-name-input';
        nameInput.placeholder = 'Component name';

        const amountInput = document.createElement('input');
        amountInput.type = 'number';
        amountInput.name = `components[${gradeKey}][${planKey}][amount][]`;
        amountInput.className = 'fee-component-input';
        amountInput.min = '0';
        amountInput.step = '0.01';
        amountInput.inputMode = 'decimal';
        amountInput.placeholder = '0.00';

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'component-remove-btn';
        removeBtn.setAttribute('aria-label', 'Remove component');
        removeBtn.textContent = 'x';

        row.appendChild(nameInput);
        row.appendChild(amountInput);
        row.appendChild(removeBtn);

        return row;
    }

    document.addEventListener('click', function (event) {
        if (event.target.matches('.grade-plan-add-btn')) {
            event.preventDefault();
            event.stopPropagation();
            const gradeKey = event.target.dataset.gradeKey;
            const planKey = event.target.dataset.planKey;
            const subsection = event.target.closest('.settings-subsection');
            const rateList = event.target.closest('[data-plan-rate-list]');
            const addRow = event.target.closest('.grade-plan-add-row');
            const newRow = createComponentRow(gradeKey, planKey);
            rateList?.insertBefore(newRow, addRow || null);
            updateGradeTotals(subsection);
        }

        if (event.target.matches('.component-remove-btn')) {
            event.preventDefault();
            event.stopPropagation();
            const row = event.target.closest('.grade-plan-component-row');
            if (row) {
                const section = row.closest('.settings-subsection');
                row.remove();
                if (section) {
                    updateGradeTotals(section);
                }
            }
        }

        const planField = event.target.closest('.grade-plan-field[data-plan-key]');
        if (planField) {
            const section = planField.closest('.settings-subsection');
            const grid = planField.closest('.grade-plan-grid');
            grid?.querySelectorAll('.grade-plan-field[data-plan-key]').forEach((field) => {
                const isSelected = field === planField;
                field.classList.toggle('is-open', isSelected);
                field.setAttribute('aria-expanded', isSelected ? 'true' : 'false');
            });
            if (section) {
                updateGradeTotals(section);
            }
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }

        const eventTarget = event.target instanceof Element ? event.target : null;
        if (eventTarget?.matches('input, button, textarea, select')) {
            return;
        }

        const planField = eventTarget?.closest('.grade-plan-field[data-plan-key]');
        if (!planField) {
            return;
        }

        event.preventDefault();
        planField.click();
    });

    document.addEventListener('input', function (event) {
        if (event.target.matches('input[name*="[amount][]"], input[name*="[name][]"]')) {
            const section = event.target.closest('.settings-subsection');
            if (section) {
                updateGradeTotals(section);
            }
        }

        if (event.target.matches('.plan-discount-input')) {
            const section = event.target.closest('.settings-subsection');
            if (!section) {
                return;
            }

            const discounts = {};
            section.querySelectorAll('.plan-discount-input[data-plan-discount-input]').forEach((input) => {
                discounts[input.dataset.planDiscountInput || ''] = Math.min(100, Math.max(0, parseMoneyValue(input.value)));
            });
            section.dataset.planDiscounts = JSON.stringify(discounts);
            updateGradeTotals(section);
        }
    });

    document.querySelectorAll('.settings-subsection').forEach((section) => {
        updateGradeTotals(section);
    });
</script>
</body>
</html>
