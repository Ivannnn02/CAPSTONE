const studentSearch = document.getElementById('studentSearch');
if (studentSearch) {
  const searchForm = studentSearch.closest('.search-form');
  if (!searchForm) {
    studentSearch.addEventListener('input', () => {
      const query = studentSearch.value.toLowerCase();
      document.querySelectorAll('#payStudentList .student-pick-card').forEach((card) => {
        const haystack = (card.getAttribute('data-search') || '').toLowerCase();
        card.style.display = haystack.includes(query) ? '' : 'none';
      });
    });
  }
}

const paymentCatalog = document.getElementById('paymentCatalog');
const selectedPaymentTable = document.getElementById('selectedPaymentTable');
const selectedPaymentEmpty = document.getElementById('selectedPaymentEmpty');
const selectedPaymentRowTemplate = document.getElementById('selectedPaymentRowTemplate');
const paymentItemsJson = document.getElementById('paymentItemsJson');
const previewEmailItemsInput = document.getElementById('previewEmailItemsJson');
const paymentSubmitModeInput = document.getElementById('paymentSubmitMode');
const paymentForm = document.getElementById('paymentBuilderForm');
const paymentDateInput = document.getElementById('paymentDateInput');
const receiptNumberInput = document.getElementById('receiptNumberInput');
const saveInvoiceButton = document.getElementById('saveInvoiceButton');
const paymentPreview = document.getElementById('paymentPreview');
const totalPaidPreview = document.getElementById('totalPaidPreview');
const lessAmountPaidPreview = document.getElementById('lessAmountPaidPreview');
const balanceAfterPreview = document.getElementById('balanceAfterPreview');
const remainingBalanceDisplay = document.getElementById('remainingBalanceDisplay');
const selectedPlanTotalDisplay = document.getElementById('selectedPlanTotalDisplay');
const activePaymentPlanDisplay = document.getElementById('activePaymentPlanDisplay');
const receiptAddTrigger = document.getElementById('receiptAddTrigger');
const receiptAddRow = document.querySelector('.receipt-add-row');
const invoiceEmailTotal = document.getElementById('invoiceEmailTotal');
const invoiceEmailDueDateInput = document.getElementById('invoiceEmailDueDateInput');
const invoiceEmailMetaDueDate = document.getElementById('invoiceEmailMetaDueDate');
const invoiceEmailNumber = document.getElementById('invoiceEmailNumber');
const invoiceEmailBodyNumber = document.getElementById('invoiceEmailBodyNumber');
const invoiceEmailBodyAmount = document.getElementById('invoiceEmailBodyAmount');
const invoiceEmailBodyOutstanding = document.getElementById('invoiceEmailBodyOutstanding');
const invoiceEmailBodyDueDate = document.getElementById('invoiceEmailBodyDueDate');
const invoiceEmailItems = document.getElementById('invoiceEmailItems');
const invoiceEmailPrintTrigger = document.getElementById('invoiceEmailPrintTrigger');
const invoiceEmailSendTrigger = document.getElementById('invoiceEmailSendTrigger');
const selectedInvoicePrintTrigger = document.getElementById('selectedInvoicePrintTrigger');
const paymentPlanInput = document.getElementById('paymentPlanInput');
const paymentPlanButtons = Array.from(document.querySelectorAll('.payment-plan-btn[data-plan-option]'));
const gmailHistoryRows = () => Array.from(document.querySelectorAll('#gmail-send-history [data-history-fill]'));

function buildInvoiceEmailCatalogMarkup() {
  if (!paymentCatalog) {
    return '';
  }

  const renderInvoiceEmailCatalogRowMarkup = (option, displayLabel, defaultAmount, baseAmount, discountPercent, disabled) => {
    const normalizedOption = String(option || '').trim();
    const normalizedLabel = String(displayLabel || normalizedOption).trim() || normalizedOption;
    const normalizedDefaultAmount = formatInvoiceNumber(parseAmount(defaultAmount));
    const normalizedBaseAmount = formatInvoiceNumber(parseAmount(baseAmount || defaultAmount));
    const normalizedDiscountPercent = Number(parseAmount(discountPercent || '0')).toFixed(2);
    const isDisabled = disabled === true || disabled === 1 || disabled === '1';

    return '<div class="catalog-row invoice-email-catalog-row' + (isDisabled ? ' is-disabled' : '') + '"'
      + ' data-option="' + escapeHtml(normalizedOption) + '"'
      + ' data-display-label="' + escapeHtml(normalizedLabel) + '"'
      + ' data-default="' + escapeHtml(normalizedDefaultAmount) + '"'
      + ' data-base="' + escapeHtml(normalizedBaseAmount) + '"'
      + ' data-discount-percent="' + escapeHtml(normalizedDiscountPercent) + '"'
      + ' data-disabled="' + (isDisabled ? '1' : '0') + '">'
      + '<button type="button" class="catalog-add-btn" aria-label="Add ' + escapeHtml(normalizedOption) + '"' + (isDisabled ? ' disabled' : '') + '>'
      + '<i class="fa-solid ' + (isDisabled ? 'fa-check' : 'fa-plus') + '"></i>'
      + '</button>'
      + '<div class="receipt-catalog-copy">'
      + '<strong>' + escapeHtml(normalizedLabel) + '</strong>'
      + '<span>' + escapeHtml(normalizedDefaultAmount) + '</span>'
      + '</div>'
      + '</div>';
  };

  let monthlyCatalogItems = [];
  try {
    const planCatalogs = JSON.parse(paymentCatalog.dataset.planCatalogs || '{}') || {};
    monthlyCatalogItems = Array.isArray(planCatalogs.monthly?.catalog)
      ? planCatalogs.monthly.catalog.filter((item) => String(item.option || '').trim() === 'Monthly Payment')
      : [];
  } catch (error) {
    monthlyCatalogItems = [];
  }

  if (monthlyCatalogItems.length) {
    return '<div class="payment-catalog-card invoice-email-catalog-menu" id="invoiceEmailCatalog">'
      + monthlyCatalogItems.map((item) => renderInvoiceEmailCatalogRowMarkup(
        item.option || '',
        item.display_label || item.displayLabel || item.option || '',
        item.default_amount || item.defaultAmount || '0',
        item.base_amount || item.baseAmount || item.default_amount || item.defaultAmount || '0',
        item.discount_percent || item.discountPercent || '0',
        item.disabled
      )).join('')
      + '</div>';
  }

  const rows = Array.from(paymentCatalog.querySelectorAll('.catalog-row[data-option="Monthly Payment"]'));
  if (!rows.length) {
    return '';
  }

  return '<div class="payment-catalog-card invoice-email-catalog-menu" id="invoiceEmailCatalog">'
    + rows.map((row) => {
      const option = String(row.dataset.option || '');
      const dataLabel = String(row.dataset.displayLabel || '').trim();
      const strongLabel = String(row.querySelector('.receipt-catalog-copy strong')?.textContent || '').trim();
      return renderInvoiceEmailCatalogRowMarkup(
        option,
        dataLabel || strongLabel || option,
        row.dataset.default || '0',
        row.dataset.base || row.dataset.default || '0',
        row.dataset.discountPercent || '0',
        row.dataset.disabled === '1'
      );
    }).join('')
    + '</div>';
}

function buildInvoiceEmailAddControl() {
  return '<button type="button" class="invoice-email-add-trigger" aria-label="Add payment item" aria-haspopup="true" aria-expanded="false">'
    + '<i class="fa-solid fa-plus"></i>'
    + '<span>Add payment item</span>'
    + '</button>'
    + buildInvoiceEmailCatalogMarkup();
}

function renderInvoiceEmailEmptyState() {
  return '<div class="invoice-email-line-item is-empty">'
    + '<div class="invoice-email-empty-action">'
    + '<span>No billing item added yet</span>'
    + buildInvoiceEmailAddControl()
    + '</div>'
    + '<strong>0.00</strong>'
    + '</div>';
}

function renderInvoiceEmailAddRow() {
  return '<div class="invoice-email-line-item invoice-email-line-item-add">'
    + '<div class="invoice-email-empty-action">'
    + buildInvoiceEmailAddControl()
    + '</div>'
    + '<strong></strong>'
    + '</div>';
}

function renderInvoiceEmailPreviewRow(item, isRemovable = false) {
  const option = String(item.option || item.label || item.displayLabel || 'Payment Item');
  const label = String(item.label || item.displayLabel || option || 'Payment Item');
  const amount = parseAmount(item.amount);

  return '<div class="invoice-email-line-item' + (isRemovable ? ' is-removable' : '') + '">'
    + '<div class="invoice-email-line-copy">'
    + (isRemovable
      ? '<button type="button" class="invoice-email-remove-btn" data-option="' + escapeHtml(option) + '" aria-label="Remove ' + escapeHtml(label) + ' from top preview">'
        + '<i class="fa-solid fa-xmark"></i>'
        + '</button>'
      : '')
    + '<span>' + escapeHtml(label) + '</span>'
    + '</div>'
    + '<strong>' + escapeHtml(formatInvoiceNumber(amount)) + '</strong>'
    + '</div>';
}

function formatPHP(value) {
  return 'PHP ' + Number(value || 0).toLocaleString('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });
}

function formatInvoiceNumber(value) {
  return Number(value || 0).toLocaleString('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });
}

function parseAmount(value) {
  return parseFloat(String(value || '').replace(/[^0-9.\-]/g, '')) || 0;
}

function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function formatPreviewDate(value) {
  const raw = String(value || '').trim();
  if (!raw) {
    return 'N/A';
  }

  const parsedDate = new Date(raw + 'T00:00:00');
  if (Number.isNaN(parsedDate.getTime())) {
    return raw;
  }

  return parsedDate.toLocaleDateString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric'
  });
}

function showToast(message) {
  let container = document.getElementById('smartenrollToastContainer');
  if (!container) {
    container = document.createElement('div');
    container.id = 'smartenrollToastContainer';
    container.className = 'sr-toast-container';
    document.body.appendChild(container);
  }

  const toast = document.createElement('div');
  toast.className = 'sr-toast';
  toast.textContent = message;
  container.appendChild(toast);

  window.setTimeout(() => {
    toast.classList.add('is-hiding');
    window.setTimeout(() => {
      toast.remove();
      if (!container.hasChildNodes()) {
        container.remove();
      }
    }, 240);
  }, 2400);
}

const PRINT_TARGET_ATTRIBUTE = 'data-print-target';
const PRINT_TARGET_SELECTED_INVOICE = 'selected-invoice';
const PRINT_TARGET_EMAIL_PREVIEW = 'invoice-email';

function clearPrintTarget() {
  document.body?.removeAttribute(PRINT_TARGET_ATTRIBUTE);
}

function triggerSectionPrint(target) {
  if (!document.body) {
    return;
  }

  document.body.setAttribute(PRINT_TARGET_ATTRIBUTE, target);
  void document.body.offsetWidth;
  window.print();
}

selectedInvoicePrintTrigger?.addEventListener('click', () => {
  const previewShell = document.querySelector('#receipt-preview .receipt-preview');
  if (!previewShell) {
    window.alert('The saved invoice preview is not ready to print yet.');
    return;
  }

  triggerSectionPrint(PRINT_TARGET_SELECTED_INVOICE);
});

window.addEventListener('afterprint', clearPrintTarget);

if (typeof window.matchMedia === 'function') {
  const printMedia = window.matchMedia('print');
  const handlePrintMediaChange = (event) => {
    if (!event.matches) {
      clearPrintTarget();
    }
  };

  if (typeof printMedia.addEventListener === 'function') {
    printMedia.addEventListener('change', handlePrintMediaChange);
  } else if (typeof printMedia.addListener === 'function') {
    printMedia.addListener(handlePrintMediaChange);
  }
}

if (paymentCatalog && selectedPaymentTable && selectedPaymentRowTemplate) {
  let fullTuition = parseAmount(selectedPaymentTable.dataset.fullTuition || '0');
  let displayPlanTotal = parseAmount(selectedPaymentTable.dataset.standardTotal || selectedPaymentTable.dataset.fullTuition || '0');
  const paidTotalBeforePayment = parseAmount(selectedPaymentTable.dataset.paidTotal || '0');
  let remainingBeforePayment = parseAmount(selectedPaymentTable.dataset.remaining || String(fullTuition));
  let displayRemainingBeforePayment = parseAmount(selectedPaymentTable.dataset.standardRemaining || String(displayPlanTotal));
  const paymentPlanLocked = selectedPaymentTable.dataset.planLocked === '1';
  let activePaymentPlanKey = String(selectedPaymentTable.dataset.activePlanKey || paymentPlanInput?.value || 'annual').trim() || 'annual';
  let paymentPlanCatalogs = {};
  try {
    paymentPlanCatalogs = JSON.parse(selectedPaymentTable.dataset.planCatalogs || paymentCatalog.dataset.planCatalogs || '{}') || {};
  } catch (error) {
    paymentPlanCatalogs = {};
  }
  const discountPlanOptions = new Set(paymentPlanButtons.map((button) => String(button.dataset.planOption || '').trim()).filter(Boolean));
  const previewEmailItemsState = [];
  let previewLoadedFromHistory = false;
  const getRows = () => Array.from(selectedPaymentTable.querySelectorAll('.selected-payment-row[data-option]'));
  const getCatalogRows = () => Array.from(paymentCatalog.querySelectorAll('.catalog-row[data-option]'));
  const getCatalogRowByOption = (option) => getCatalogRows().find((row) => row.dataset.option === option) || null;
  const isDiscountPlanOption = (option) => discountPlanOptions.has(String(option || '').trim());
  const getBuilderTotal = () => getRows().reduce((sum, row) => sum + Math.max(getRowAmount(row), 0), 0);
  const getBuilderCreditTotal = () => getRows().reduce((sum, row) => sum + Math.max(getRowCreditAmount(row), 0), 0);
  const hasPreviewEmailItems = () => previewEmailItemsState.length > 0;
  const getPreviewEmailItemsTotal = () => previewEmailItemsState.reduce((sum, item) => sum + Math.max(parseAmount(item.amount), 0), 0);
  const getActivePlanConfig = (planKey = activePaymentPlanKey) => {
    const normalizedKey = String(planKey || '').trim();
    return paymentPlanCatalogs[normalizedKey]
      || paymentPlanCatalogs.annual
      || Object.values(paymentPlanCatalogs)[0]
      || null;
  };
  const getActivePlanDiscountPercent = (option = '') => {
    const activePlanConfig = getActivePlanConfig();
    if (!activePlanConfig) {
      return 0;
    }

    const normalizedOption = String(option || '').trim();
    const coreOption = String(activePlanConfig.core_option || '').trim();
    if (!normalizedOption || normalizedOption !== coreOption) {
      return 0;
    }

    return Math.max(parseAmount(activePlanConfig.discount_percent || '0'), 0);
  };
  const syncPlanSummary = () => {
    const activePlanConfig = getActivePlanConfig();
    if (selectedPlanTotalDisplay) {
      selectedPlanTotalDisplay.textContent = formatPHP(displayPlanTotal);
    }
    if (activePaymentPlanDisplay) {
      activePaymentPlanDisplay.textContent = String(activePlanConfig?.label || selectedPaymentTable.dataset.activePlanLabel || 'Annual Payment');
    }
  };
  const getPreviewPayloadItems = () => {
    return previewEmailItemsState.map((item) => {
      const payloadItem = {
        option: item.option || '',
        label: item.label || item.displayLabel || item.option || 'Payment Item',
        amount: Number(parseAmount(item.amount).toFixed(2))
      };

      const baseAmount = parseAmount(item.baseAmount || item.base_amount || item.amount);
      const discountPercent = parseAmount(item.discountPercent || item.discount_percent || '0');
      if (baseAmount > parseAmount(item.amount) + 0.005) {
        payloadItem.base_amount = Number(baseAmount.toFixed(2));
      }
      if (discountPercent > 0) {
        payloadItem.discount_percent = Number(discountPercent.toFixed(2));
      }

      return payloadItem;
    });
  };
  const getPreviewEmailDisplayItems = () => {
    return previewEmailItemsState.map((item) => ({
      option: item.option || '',
      label: item.label || item.displayLabel || item.option || 'Payment Item',
      amount: parseAmount(item.amount),
      base_amount: parseAmount(item.baseAmount || item.base_amount || item.amount),
      discount_percent: parseAmount(item.discountPercent || item.discount_percent || '0')
    }));
  };
  const syncPreviewEmailItemsInput = () => {
    if (!previewEmailItemsInput) {
      return;
    }

    const previewItems = getPreviewPayloadItems();
    previewEmailItemsInput.value = previewItems.length ? JSON.stringify(previewItems) : '';
  };
  const getInvoiceEmailCatalog = () => document.getElementById('invoiceEmailCatalog');
  const setInvoiceEmailCatalogOpen = (isOpen) => {
    const catalog = getInvoiceEmailCatalog();
    const trigger = invoiceEmailItems?.querySelector('.invoice-email-add-trigger');
    if (catalog) {
      catalog.classList.toggle('is-open', isOpen);
    }
    if (trigger) {
      trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }
  };
  const syncPaymentDateInputs = (source = 'builder') => {
    if (source === 'preview') {
      if (paymentDateInput && invoiceEmailDueDateInput) {
        paymentDateInput.value = invoiceEmailDueDateInput.value || '';
      }
      return invoiceEmailDueDateInput?.value || paymentDateInput?.value || '';
    }

    if (paymentDateInput && invoiceEmailDueDateInput) {
      invoiceEmailDueDateInput.value = paymentDateInput.value || '';
    }

    return paymentDateInput?.value || invoiceEmailDueDateInput?.value || '';
  };

  const syncEmailPreview = (total, dateSource = 'builder') => {
    const displayItems = getPreviewEmailDisplayItems();
    const dueDateText = formatPreviewDate(syncPaymentDateInputs(dateSource));
    const invoiceNumberText = String(receiptNumberInput?.value || 'N/A').trim() || 'N/A';
    const previewTotal = hasPreviewEmailItems() ? getPreviewEmailItemsTotal() : 0;
    const isCustomPreview = hasPreviewEmailItems();
    const formattedTotal = formatPHP(previewTotal);

    if (invoiceEmailTotal) {
      invoiceEmailTotal.textContent = formatInvoiceNumber(previewTotal);
    }
    if (invoiceEmailMetaDueDate) {
      invoiceEmailMetaDueDate.textContent = 'Due ' + dueDateText;
    }
    if (invoiceEmailNumber) {
      invoiceEmailNumber.textContent = invoiceNumberText;
    }
    if (invoiceEmailBodyNumber) {
      invoiceEmailBodyNumber.textContent = invoiceNumberText;
    }
    if (invoiceEmailBodyAmount) {
      invoiceEmailBodyAmount.textContent = formattedTotal;
    }
    if (invoiceEmailBodyOutstanding) {
      invoiceEmailBodyOutstanding.textContent = formattedTotal;
    }
    if (invoiceEmailBodyDueDate) {
      invoiceEmailBodyDueDate.textContent = dueDateText;
    }
    if (invoiceEmailItems) {
      if (!displayItems.length) {
        invoiceEmailItems.innerHTML = renderInvoiceEmailEmptyState();
        syncPreviewEmailItemsInput();
        return;
      }

      const itemRows = displayItems.map((item) => renderInvoiceEmailPreviewRow(item, isCustomPreview));

      if (isCustomPreview) {
        itemRows.push(renderInvoiceEmailAddRow());
      }

      invoiceEmailItems.innerHTML = itemRows.join('');
    }

    syncPreviewEmailItemsInput();
  };
  const renderCatalogRowMarkup = (item) => {
    const option = String(item.option || '').trim();
    const displayLabel = String(item.display_label || item.displayLabel || option).trim() || option;
    const defaultAmount = formatInvoiceNumber(parseAmount(item.default_amount || item.defaultAmount || '0'));
    const baseAmount = formatInvoiceNumber(parseAmount(item.base_amount || item.baseAmount || item.default_amount || item.defaultAmount || '0'));
    const discountPercent = Number(parseAmount(item.discount_percent || item.discountPercent || '0')).toFixed(2);
    const disabled = item.disabled === true || item.disabled === 1 || item.disabled === '1';

    return '<div class="catalog-row receipt-catalog-row' + (disabled ? ' is-disabled' : '') + '"'
      + ' data-option="' + escapeHtml(option) + '"'
      + ' data-display-label="' + escapeHtml(displayLabel) + '"'
      + ' data-default="' + escapeHtml(defaultAmount) + '"'
      + ' data-base="' + escapeHtml(baseAmount) + '"'
      + ' data-discount-percent="' + escapeHtml(discountPercent) + '"'
      + ' data-disabled="' + (disabled ? '1' : '0') + '">'
      + '<button type="button" class="catalog-add-btn" aria-label="Add ' + escapeHtml(option) + '"' + (disabled ? ' disabled' : '') + '>'
      + '<i class="fa-solid ' + (disabled ? 'fa-check' : 'fa-plus') + '"></i>'
      + '</button>'
      + '<div class="receipt-catalog-copy">'
      + '<strong>' + escapeHtml(displayLabel) + '</strong>'
      + '<span>' + escapeHtml(defaultAmount) + '</span>'
      + '</div>'
      + '</div>';
  };
  const renderCatalogRows = (catalogItems) => {
    paymentCatalog.innerHTML = Array.isArray(catalogItems)
      ? catalogItems.map((item) => renderCatalogRowMarkup(item)).join('')
      : '';
    const hasEnabledCatalogItem = Array.isArray(catalogItems)
      && catalogItems.some((item) => !(item.disabled === true || item.disabled === 1 || item.disabled === '1'));
    if (receiptAddTrigger) {
      receiptAddTrigger.disabled = !hasEnabledCatalogItem;
    }
    bindCatalogRows(paymentCatalog);
  };
  const syncPlanButtons = () => {
    paymentPlanButtons.forEach((button) => {
      const buttonPlanKey = String(button.dataset.planOption || '').trim();
      const isActive = buttonPlanKey === activePaymentPlanKey;
      button.classList.toggle('is-active', isActive);
      button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
      if (paymentPlanLocked && !isActive) {
        button.disabled = true;
      }
    });
  };
  const setCatalogOpen = (isOpen) => {
    paymentCatalog.classList.toggle('is-open', isOpen);
    if (receiptAddTrigger) {
      receiptAddTrigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }
    if (isOpen) {
      setInvoiceEmailCatalogOpen(false);
    }
  };

  const formatDiscountAmount = (value) => formatInvoiceNumber(Math.max(parseAmount(value), 0));

  const getRowAmount = (row) => {
    return parseAmount(row.dataset.amount || '0');
  };
  const getRowBaseAmount = (row) => {
    return parseAmount(row.dataset.baseAmount || row.dataset.amount || '0');
  };
  const getRowBaseFactor = (row) => {
    const storedFactor = parseAmount(row.dataset.baseFactor || '0');
    return storedFactor > 0 ? storedFactor : 1;
  };
  const getRowDiscountPercent = (row) => {
    return parseAmount(row.dataset.discountPercent || '0');
  };
  const getRowDiscountAmount = (row) => {
    return Math.max(getRowBaseAmount(row) - getRowAmount(row), 0);
  };
  const getRowCreditAmount = (row) => {
    return getRowAmount(row);
  };
  const getTuitionUnitPriceInputValue = (row) => {
    return getRowBaseAmount(row);
  };
  const getRowAmountFromUnitPrice = (row, unitPrice) => {
    const baseFactor = getRowBaseFactor(row);
    if (baseFactor <= 1) {
      return Math.max(parseAmount(unitPrice), 0);
    }

    return Math.max(parseAmount(unitPrice) / baseFactor, 0);
  };
  const hasDiscountPlanRow = () => getRows().some((row) => isDiscountPlanOption(row.dataset.option || ''));

  const getUnitPriceDisplay = (row) => row.querySelector('.selected-unit-price-display');
  const getTuitionManualWrap = (row) => row.querySelector('.tuition-manual-wrap');
  const getTuitionManualInput = (row) => row.querySelector('.tuition-manual-input');
  const getDiscountCell = (row) => row.querySelector('.selected-row-discount');

  const setRowPricing = (row, amount, options = {}) => {
    const {
      baseAmount = amount,
      discountPercent = 0,
      skipInputSync = false
    } = options;
    const normalizedAmount = Math.max(parseAmount(amount), 0);
    const normalizedBaseAmount = Math.max(parseAmount(baseAmount), normalizedAmount);
    const normalizedDiscountPercent = Math.max(parseAmount(discountPercent), 0);
    const normalizedBaseFactor = normalizedAmount > 0
      ? (normalizedBaseAmount / normalizedAmount)
      : getRowBaseFactor(row);
    row.dataset.amount = normalizedAmount.toFixed(2);
    row.dataset.baseAmount = normalizedBaseAmount.toFixed(2);
    row.dataset.baseFactor = normalizedBaseFactor.toFixed(6);
    row.dataset.discountPercent = normalizedDiscountPercent.toFixed(2);
    const suggested = getUnitPriceDisplay(row);
    const discountCell = getDiscountCell(row);
    const lineAmount = row.querySelector('.selected-row-amount');
    const manualInput = getTuitionManualInput(row);
    if (suggested) {
      suggested.textContent = formatInvoiceNumber(normalizedBaseAmount);
    }
    if (discountCell) {
      discountCell.textContent = formatDiscountAmount(normalizedBaseAmount - normalizedAmount);
    }
    if (lineAmount) {
      lineAmount.textContent = formatInvoiceNumber(normalizedAmount);
    }
    if (manualInput && !skipInputSync && document.activeElement !== manualInput) {
      manualInput.value = formatInvoiceNumber(getTuitionUnitPriceInputValue(row));
    }
  };

  const setRowAmount = (row, amount, options = {}) => {
    const existingDiscountPercent = getRowDiscountPercent(row);
    const existingBaseFactor = getRowBaseFactor(row);
    setRowPricing(row, amount, {
      ...options,
      baseAmount: options.baseAmount ?? (Math.max(parseAmount(amount), 0) * existingBaseFactor),
      discountPercent: options.discountPercent ?? existingDiscountPercent
    });
  };

  const setRowStatus = (row, message) => {
    const status = row.querySelector('.selected-row-status');
    if (status) {
      status.textContent = message;
    }
  };

  const getMaxTuitionAllowed = (tuitionRow) => {
    const otherRowsTotal = getRows().reduce((sum, row) => {
      if (row === tuitionRow) {
        return sum;
      }
      return sum + Math.max(getRowAmount(row), 0);
    }, 0);

    return Math.max(remainingBeforePayment - otherRowsTotal, 0);
  };

  const enableTuitionManualInput = (row) => {
    const manualWrap = getTuitionManualWrap(row);
    const manualInput = getTuitionManualInput(row);
    const display = getUnitPriceDisplay(row);
    if (!manualWrap || !manualInput || !display) {
      return;
    }

    manualWrap.classList.remove('is-hidden');
    display.classList.add('is-hidden');
    manualInput.value = formatInvoiceNumber(getTuitionUnitPriceInputValue(row));

    manualInput.addEventListener('focus', () => {
      manualInput.select();
    });

    manualInput.addEventListener('input', () => {
      const maxAllowed = getMaxTuitionAllowed(row);
      const baseFactor = getRowBaseFactor(row);
      const typedUnitPrice = parseAmount(manualInput.value);
      const normalizedUnitPrice = Math.min(Math.max(typedUnitPrice, 0), maxAllowed * baseFactor);
      const normalizedAmount = getRowAmountFromUnitPrice(row, normalizedUnitPrice);
      setRowPricing(row, normalizedAmount, {
        baseAmount: normalizedUnitPrice,
        discountPercent: getRowDiscountPercent(row),
        skipInputSync: true
      });
      syncTotals();
    });

    manualInput.addEventListener('blur', () => {
      const maxAllowed = getMaxTuitionAllowed(row);
      const baseFactor = getRowBaseFactor(row);
      const normalizedUnitPrice = Math.min(Math.max(getTuitionUnitPriceInputValue(row), 0), maxAllowed * baseFactor);
      const normalizedAmount = getRowAmountFromUnitPrice(row, normalizedUnitPrice);
      setRowPricing(row, normalizedAmount, {
        baseAmount: normalizedUnitPrice,
        discountPercent: getRowDiscountPercent(row)
      });
    });
  };

  const syncEmptyState = () => {
    const hasRows = getRows().length > 0;
    if (selectedPaymentEmpty) {
      selectedPaymentEmpty.style.display = hasRows ? 'none' : 'table-row';
    }
  };

  const syncTotals = () => {
    getRows().forEach((row) => {
      if (row.dataset.option !== 'Tuition Fee') {
        return;
      }

      const maxAllowed = getMaxTuitionAllowed(row);
      const currentAmount = getRowAmount(row);
      if (currentAmount > maxAllowed) {
        setRowAmount(row, maxAllowed);
      }
      setRowStatus(
        row,
        maxAllowed > 0
          ? (getRowBaseFactor(row) > 1.000001
            ? 'Discount is applied automatically as you edit the unit price'
            : 'Enter any amount up to the remaining balance')
          : 'No remaining balance left'
      );
    });

    const total = getBuilderTotal();
    const creditedTotal = getBuilderCreditTotal();
    const remaining = Math.max(remainingBeforePayment - creditedTotal, 0);
    if (paymentPreview) {
      paymentPreview.textContent = formatInvoiceNumber(total);
    }
    if (totalPaidPreview) {
      totalPaidPreview.textContent = formatInvoiceNumber(total);
    }
    if (lessAmountPaidPreview) {
      lessAmountPaidPreview.textContent = formatInvoiceNumber(total);
    }
    if (remainingBalanceDisplay) {
      remainingBalanceDisplay.textContent = formatPHP(remaining);
    }
    if (balanceAfterPreview) {
      balanceAfterPreview.textContent = formatInvoiceNumber(remaining);
    }

    syncEmailPreview(total);
  };

  const clearBuilderRows = () => {
    getRows().forEach((row) => row.remove());
    previewEmailItemsState.splice(0, previewEmailItemsState.length);
    previewLoadedFromHistory = false;
    setActiveHistoryRow(null);
    syncEmptyState();
  };

  const setActivePaymentPlan = (nextPlanKey, options = {}) => {
    const {
      clearRows = true,
      showMessage = false
    } = options;
    const nextConfig = getActivePlanConfig(nextPlanKey);
    if (!nextConfig) {
      return;
    }

    const normalizedNextKey = String(nextConfig.key || nextPlanKey || '').trim() || activePaymentPlanKey;
    if (paymentPlanLocked && normalizedNextKey !== activePaymentPlanKey) {
      showToast('This student already has saved payments under a locked payment plan.');
      return;
    }

    const shouldClearRows = clearRows && (getRows().length > 0 || hasPreviewEmailItems());
    if (shouldClearRows) {
      clearBuilderRows();
      if (showMessage) {
        showToast('Payment rows were cleared to switch rates.');
      }
    }

    activePaymentPlanKey = normalizedNextKey;
    fullTuition = parseAmount(nextConfig.program_total || '0');
    displayPlanTotal = parseAmount(nextConfig.standard_total || nextConfig.program_total || fullTuition);
    remainingBeforePayment = Math.max(parseAmount(nextConfig.remaining_balance || (fullTuition - paidTotalBeforePayment)), 0);
    displayRemainingBeforePayment = Math.max(parseAmount(nextConfig.standard_remaining_balance || (displayPlanTotal - paidTotalBeforePayment)), 0);
    selectedPaymentTable.dataset.activePlanKey = activePaymentPlanKey;
    selectedPaymentTable.dataset.activePlanLabel = String(nextConfig.label || '');
    selectedPaymentTable.dataset.fullTuition = fullTuition.toFixed(2);
    selectedPaymentTable.dataset.standardTotal = displayPlanTotal.toFixed(2);
    selectedPaymentTable.dataset.remaining = remainingBeforePayment.toFixed(2);
    selectedPaymentTable.dataset.standardRemaining = displayRemainingBeforePayment.toFixed(2);

    if (paymentPlanInput) {
      paymentPlanInput.value = activePaymentPlanKey;
    }

    renderCatalogRows(nextConfig.catalog || []);
    syncPlanButtons();
    syncPlanSummary();
    syncEmptyState();
    syncTotals();
  };

  const setActiveHistoryRow = (activeRow) => {
    gmailHistoryRows().forEach((row) => {
      row.classList.toggle('active-history-row', row === activeRow);
    });
  };

  const getHistoryItemLabel = (item, option) => {
    const explicitLabel = String(item.display_label || item.label || '').trim();
    if (explicitLabel) {
      return explicitLabel;
    }

    const catalogRow = getCatalogRowByOption(option);
    const catalogLabel = String(catalogRow?.dataset.displayLabel || '').trim();
    return catalogLabel || option;
  };

  const addSelectedRow = (option, defaultAmount, displayLabel, config = {}) => {
    const {
      baseAmount = defaultAmount,
      discountPercent = getActivePlanDiscountPercent(option),
      replaceExisting = false,
      statusMessage = '',
      isDiscountPlan = isDiscountPlanOption(option)
    } = config;
    const existingRow = getRows().find((row) => row.dataset.option === option);
    if (existingRow) {
      return existingRow;
    }

    if (isDiscountPlan) {
      if (getRows().length > 0 && replaceExisting) {
        clearBuilderRows();
      } else if (getRows().length > 0) {
        showToast('Clear the current invoice rows first before using a discount payment plan.');
        return null;
      }
    } else if (hasDiscountPlanRow()) {
      showToast('Clear the selected discount payment plan before adding other billing items.');
      return null;
    }

    const hasTuitionFee = getRows().some((row) => row.dataset.option === 'Tuition Fee');
    const hasMonthlyPayment = getRows().some((row) => row.dataset.option === 'Monthly Payment');
    if ((option === 'Tuition Fee' && hasMonthlyPayment) || (option === 'Monthly Payment' && hasTuitionFee)) {
      showToast('Choose either Tuition Fee or Monthly Payment only, not both.');
      return null;
    }

    const fragment = selectedPaymentRowTemplate.content.cloneNode(true);
    const row = fragment.querySelector('.selected-payment-row');
    const name = row.querySelector('.selected-item-name');
    const removeBtn = row.querySelector('.remove-selected-btn');

    row.dataset.option = option;
    const effectiveLabel = displayLabel || option;
    row.dataset.displayLabel = effectiveLabel;
    row.dataset.label = effectiveLabel;
    name.textContent = effectiveLabel;
    if (isDiscountPlan) {
      setRowAmount(row, defaultAmount, {
        baseAmount,
        discountPercent
      });
      setRowStatus(row, statusMessage || `${formatDiscountAmount(baseAmount - defaultAmount)} discount applied automatically`);
    } else if (option === 'Tuition Fee') {
      const maxAllowed = getMaxTuitionAllowed(row);
      if (maxAllowed <= 0) {
        showToast('No remaining balance available for Tuition Fee.');
        return null;
      }

      const initialAmount = Math.min(defaultAmount, maxAllowed);
      const baseFactor = defaultAmount > 0 ? (baseAmount / defaultAmount) : 1;
      setRowAmount(row, initialAmount, {
        baseAmount: initialAmount * baseFactor,
        discountPercent
      });
      setRowStatus(row, Math.max(baseAmount - defaultAmount, 0) > 0
        ? 'Discount is applied automatically as you edit the unit price'
        : 'Enter any amount up to the remaining balance');
      enableTuitionManualInput(row);
    } else {
      setRowAmount(row, defaultAmount, { baseAmount, discountPercent });
      setRowStatus(
        row,
        Math.max(baseAmount - defaultAmount, 0) > 0
          ? `${formatDiscountAmount(baseAmount - defaultAmount)} discount applied automatically`
          : 'Fixed brochure amount'
      );
    }

    removeBtn.addEventListener('click', () => {
      row.remove();
      syncEmptyState();
      syncTotals();
    });

    if (receiptAddRow) {
      selectedPaymentTable.insertBefore(fragment, receiptAddRow);
    } else if (selectedPaymentEmpty) {
      selectedPaymentTable.insertBefore(fragment, selectedPaymentEmpty);
    } else {
      selectedPaymentTable.appendChild(fragment);
    }
    setCatalogOpen(false);
    setInvoiceEmailCatalogOpen(false);
    syncEmptyState();
    syncTotals();
    return row;
  };

  const loadHistoryIntoEmailPreview = (payload, activeRow) => {
    if (!payload || typeof payload !== 'object') {
      return;
    }

    const fallbackAmount = Math.max(parseAmount(payload.amount_paid), 0);
    const historyItems = Array.isArray(payload.items)
      ? payload.items.filter((item) => item && typeof item === 'object')
      : [];
    const normalizedItems = historyItems.length
      ? historyItems
      : (fallbackAmount > 0 ? [{
        option: 'Tuition Fee',
        display_label: String(getCatalogRowByOption('Tuition Fee')?.dataset.displayLabel || 'Tuition Fee'),
        amount: fallbackAmount
      }] : []);

    previewEmailItemsState.splice(0, previewEmailItemsState.length);
    previewLoadedFromHistory = true;

    const paymentDate = String(payload.payment_date || '').trim();
    if (paymentDateInput && paymentDate) {
      paymentDateInput.value = paymentDate;
    }
    if (invoiceEmailDueDateInput && paymentDate) {
      invoiceEmailDueDateInput.value = paymentDate;
    }

    const receiptNo = String(payload.receipt_no || '').trim();
    if (receiptNumberInput && receiptNo) {
      receiptNumberInput.value = receiptNo;
    }

    normalizedItems.forEach((item) => {
      const option = String(item.option || '').trim();
      const amount = Math.max(parseAmount(item.amount), 0);
      if (!option || amount <= 0) {
        return;
      }

      const displayLabel = getHistoryItemLabel(item, option);
      const previewItem = {
        option,
        label: displayLabel,
        displayLabel,
        amount: amount.toFixed(2)
      };

      const baseAmount = Math.max(parseAmount(item.base_amount), 0);
      const discountPercent = Math.max(parseAmount(item.discount_percent), 0);
      if (discountPercent > 0 && baseAmount > 0) {
        previewItem.baseAmount = baseAmount.toFixed(2);
        previewItem.discountPercent = discountPercent.toFixed(2);
      }

      previewEmailItemsState.push(previewItem);
    });

    syncEmailPreview(getBuilderTotal(), 'preview');
    setActiveHistoryRow(activeRow);
    document.getElementById('receipt-email-preview')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    showToast('Sent email preview loaded.');
  };

  const addPreviewEmailItem = (option, defaultAmount, displayLabel, config = {}) => {
    const {
      baseAmount = defaultAmount,
      discountPercent = 0
    } = config;
    previewLoadedFromHistory = false;
    setActiveHistoryRow(null);

    const existingPreviewItem = previewEmailItemsState.find((item) => item.option === option);
    if (existingPreviewItem) {
      showToast('This payment item is already added in the top preview.');
      return;
    }

    const hasPreviewTuitionFee = previewEmailItemsState.some((item) => item.option === 'Tuition Fee');
    const hasPreviewMonthlyPayment = previewEmailItemsState.some((item) => item.option === 'Monthly Payment');
    if ((option === 'Tuition Fee' && hasPreviewMonthlyPayment) || (option === 'Monthly Payment' && hasPreviewTuitionFee)) {
      showToast('Choose either Tuition Fee or Monthly Payment only, not both.');
      return;
    }

    let amount = Math.max(defaultAmount, 0);
    let resolvedBaseAmount = Math.max(baseAmount, amount);
    if (option === 'Tuition Fee') {
      amount = Math.min(amount, remainingBeforePayment);
      if (defaultAmount > 0 && baseAmount > 0 && amount < defaultAmount) {
        resolvedBaseAmount = Number((amount * (baseAmount / defaultAmount)).toFixed(2));
      }
    }

    if (amount <= 0) {
      showToast('No valid amount is available for this payment item.');
      return;
    }

    const previewItem = {
      option,
      label: displayLabel || option,
      displayLabel: displayLabel || option,
      amount: amount.toFixed(2)
    };
    if (resolvedBaseAmount > amount + 0.005) {
      previewItem.baseAmount = resolvedBaseAmount.toFixed(2);
    }
    if (discountPercent > 0 && resolvedBaseAmount > 0) {
      previewItem.discountPercent = Number(discountPercent).toFixed(2);
    }

    previewEmailItemsState.push(previewItem);

    setInvoiceEmailCatalogOpen(false);
    syncEmailPreview(getBuilderTotal());
  };

  const removePreviewEmailItem = (option) => {
    previewLoadedFromHistory = false;
    setActiveHistoryRow(null);

    const targetOption = String(option || '').trim();
    if (!targetOption) {
      return;
    }

    const previewIndex = previewEmailItemsState.findIndex((item) => item.option === targetOption);
    if (previewIndex === -1) {
      return;
    }

    previewEmailItemsState.splice(previewIndex, 1);
    setInvoiceEmailCatalogOpen(false);
    syncEmailPreview(getBuilderTotal());
  };

  receiptAddTrigger?.addEventListener('click', (event) => {
    event.stopPropagation();
    if (receiptAddTrigger.disabled) {
      return;
    }

    setCatalogOpen(!paymentCatalog.classList.contains('is-open'));
  });

  const bindCatalogRows = (catalogRoot) => {
    catalogRoot.querySelectorAll('.catalog-row[data-option]').forEach((catalogRow) => {
      const button = catalogRow.querySelector('.catalog-add-btn');
      const handleCatalogAdd = () => {
        if (catalogRow.dataset.disabled === '1') {
          return;
        }

        const option = catalogRow.dataset.option || '';
        const displayLabel = catalogRow.dataset.displayLabel || option;
        const defaultAmount = parseAmount(catalogRow.dataset.default || '0');
        const baseAmount = parseAmount(catalogRow.dataset.base || catalogRow.dataset.default || '0');
        const discountPercent = parseAmount(catalogRow.dataset.discountPercent || '0');
        if (!option) {
          return;
        }
        addSelectedRow(option, defaultAmount, displayLabel, { baseAmount, discountPercent });
      };

      button?.addEventListener('click', (event) => {
        event.stopPropagation();
        handleCatalogAdd();
      });

      catalogRow.addEventListener('click', handleCatalogAdd);
    });
  };

  bindCatalogRows(paymentCatalog);

  paymentPlanButtons.forEach((button) => {
    button.addEventListener('click', () => {
      if (button.disabled) {
        return;
      }

      const nextPlanKey = String(button.dataset.planOption || '').trim();
      if (!nextPlanKey) {
        showToast('This payment plan is not available right now.');
        return;
      }

      setCatalogOpen(false);
      setInvoiceEmailCatalogOpen(false);
      setActivePaymentPlan(nextPlanKey, {
        clearRows: true,
        showMessage: true
      });
    });
  });

  setActivePaymentPlan(activePaymentPlanKey, { clearRows: false });

  gmailHistoryRows().forEach((historyRow) => {
    historyRow.addEventListener('click', (event) => {
      const target = event.target instanceof Element ? event.target : null;
      if (!target) {
        return;
      }

      if (target.closest('.table-send-form, .table-send-btn, button, input, select, textarea')) {
        return;
      }

      const link = target.closest('a.history-link');
      const previewTargetLink = link || historyRow.querySelector('a.history-link');
      const previewHref = String(previewTargetLink?.getAttribute('href') || '');
      const isPreviewEmailHistory = previewHref.includes('#receipt-email-preview');
      if (!isPreviewEmailHistory) {
        return;
      }

      if (link && (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey)) {
        return;
      }

      const rawPayload = historyRow.dataset.historyFill || '';
      if (!rawPayload) {
        return;
      }

      if (link) {
        event.preventDefault();
      }

      let payload = null;
      try {
        payload = JSON.parse(rawPayload);
      } catch (error) {
        payload = null;
      }

      if (!payload) {
        showToast('This sent email preview could not be loaded.');
        return;
      }

      loadHistoryIntoEmailPreview(payload, historyRow);
    });
  });

  document.addEventListener('click', (event) => {
    const target = event.target;

    if (paymentCatalog.classList.contains('is-open')) {
      if (!paymentCatalog.contains(target) && !receiptAddTrigger?.contains(target)) {
        setCatalogOpen(false);
      }
    }

    const previewCatalog = getInvoiceEmailCatalog();
    const previewTrigger = invoiceEmailItems?.querySelector('.invoice-email-add-trigger');
    if (previewCatalog?.classList.contains('is-open')) {
      if (!previewCatalog.contains(target) && !previewTrigger?.contains(target)) {
        setInvoiceEmailCatalogOpen(false);
      }
    }
  });

  invoiceEmailItems?.addEventListener('click', (event) => {
    const removeButton = event.target.closest('.invoice-email-remove-btn');
    if (removeButton) {
      event.preventDefault();
      removePreviewEmailItem(removeButton.dataset.option || '');
      return;
    }

    const addButton = event.target.closest('.invoice-email-add-trigger');
    if (!addButton) {
      return;
    }

    event.preventDefault();
    const previewCatalog = getInvoiceEmailCatalog();
    if (!previewCatalog) {
      showToast('No payment item is available to add right now.');
      return;
    }

    setCatalogOpen(false);
    setInvoiceEmailCatalogOpen(!previewCatalog.classList.contains('is-open'));
  });

  invoiceEmailItems?.addEventListener('click', (event) => {
    const catalogRow = event.target.closest('.invoice-email-catalog-row[data-option]');
    if (!catalogRow) {
      return;
    }

    if (catalogRow.dataset.disabled === '1') {
      event.preventDefault();
      return;
    }

    const option = catalogRow.dataset.option || '';
    const displayLabel = catalogRow.dataset.displayLabel || option;
    const defaultAmount = parseAmount(catalogRow.dataset.default || '0');
    const baseAmount = parseAmount(catalogRow.dataset.base || catalogRow.dataset.default || '0');
    const discountPercent = parseAmount(catalogRow.dataset.discountPercent || '0');
    if (!option) {
      return;
    }

    event.preventDefault();
    addPreviewEmailItem(option, defaultAmount, displayLabel, { baseAmount, discountPercent });
  });

  invoiceEmailItems?.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
      return;
    }

    setInvoiceEmailCatalogOpen(false);
  });

  saveInvoiceButton?.addEventListener('click', () => {
    if (paymentSubmitModeInput) {
      paymentSubmitModeInput.value = 'save';
    }
  });

  invoiceEmailSendTrigger?.addEventListener('click', () => {
    const previewItems = getPreviewPayloadItems();
    if (!previewItems.length) {
      window.alert('Please add at least one payment item in the top preview before sending.');
      return;
    }

    if (paymentSubmitModeInput) {
      paymentSubmitModeInput.value = 'preview_send';
    }
    syncPreviewEmailItemsInput();
    paymentForm?.requestSubmit();
  });

  invoiceEmailPrintTrigger?.addEventListener('click', () => {
    const previewShell = document.querySelector('.invoice-email-preview-panel .invoice-email-shell');
    if (!previewShell) {
      window.alert('The invoice preview is not ready to print yet.');
      return;
    }

    setInvoiceEmailCatalogOpen(false);
    triggerSectionPrint(PRINT_TARGET_EMAIL_PREVIEW);
  });

  const initialPreviewCatalog = getInvoiceEmailCatalog();
  if (initialPreviewCatalog) {
    setInvoiceEmailCatalogOpen(false);
  }

  paymentForm?.addEventListener('submit', (event) => {
    const builderRows = getRows().map((row) => {
      const option = row.dataset.option || '';
      const amount = parseAmount(row.dataset.amount || '0');
      const payloadRow = {
        option,
        amount
      };

      const baseAmount = getRowBaseAmount(row);
      const discountPercent = getRowDiscountPercent(row);
      if (baseAmount > amount + 0.005) {
        payloadRow.base_amount = Number(baseAmount.toFixed(2));
      }
      if (discountPercent > 0) {
        payloadRow.discount_percent = Number(discountPercent.toFixed(2));
      }
      if (baseAmount > amount + 0.005 || discountPercent > 0) {
        payloadRow.label = row.dataset.label || row.dataset.displayLabel || option;
      }

      return payloadRow;
    }).filter((row) => row.option && row.amount > 0);
    const previewRows = getPreviewPayloadItems();
    const submitMode = paymentSubmitModeInput?.value === 'preview_send' ? 'preview_send' : 'save';
    const activeRows = submitMode === 'preview_send' ? previewRows : builderRows;

    if (!activeRows.length) {
      event.preventDefault();
      if (paymentSubmitModeInput) {
        paymentSubmitModeInput.value = 'save';
      }
      window.alert(submitMode === 'preview_send'
        ? 'Please add at least one payment item in the top preview before sending.'
        : 'Please add at least one payment row.');
      return;
    }

    const hasTuitionFee = activeRows.some((row) => row.option === 'Tuition Fee');
    const hasMonthlyPayment = activeRows.some((row) => row.option === 'Monthly Payment');
    if (hasTuitionFee && hasMonthlyPayment) {
      event.preventDefault();
      if (paymentSubmitModeInput) {
        paymentSubmitModeInput.value = 'save';
      }
      showToast('Choose either Tuition Fee or Monthly Payment only, not both.');
      return;
    }

    const selectedDiscountPlanRows = activeRows.filter((row) => isDiscountPlanOption(row.option || ''));
    if (selectedDiscountPlanRows.length > 1 || (selectedDiscountPlanRows.length === 1 && activeRows.length > 1)) {
      event.preventDefault();
      if (paymentSubmitModeInput) {
        paymentSubmitModeInput.value = 'save';
      }
      showToast('Discount payment plans must be used on their own.');
      return;
    }

    const totalCreditedAmount = activeRows.reduce((sum, row) => {
      return sum + parseAmount(row.amount);
    }, 0);
    if (totalCreditedAmount > remainingBeforePayment) {
      event.preventDefault();
      if (paymentSubmitModeInput) {
        paymentSubmitModeInput.value = 'save';
      }
      window.alert('The entered amount exceeds the remaining balance of ' + formatPHP(remainingBeforePayment) + '.');
      return;
    }

    if (paymentItemsJson) {
      paymentItemsJson.value = JSON.stringify(builderRows);
    }
    syncPreviewEmailItemsInput();
  });

  paymentDateInput?.addEventListener('input', () => {
    previewLoadedFromHistory = false;
    setActiveHistoryRow(null);
    syncEmailPreview(getBuilderTotal(), 'builder');
  });

  invoiceEmailDueDateInput?.addEventListener('input', () => {
    previewLoadedFromHistory = false;
    setActiveHistoryRow(null);
    syncEmailPreview(getBuilderTotal(), 'preview');
  });

  receiptNumberInput?.addEventListener('input', () => {
    previewLoadedFromHistory = false;
    setActiveHistoryRow(null);
    syncEmailPreview(getBuilderTotal());
  });

  setCatalogOpen(false);
  syncTotals();
  syncEmptyState();
}
