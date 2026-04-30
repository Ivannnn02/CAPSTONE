(function () {
  const saveBtn = document.querySelector('.tuition-save-btn');
  const overallInput = document.getElementById('overallBalance');
  const paidInput = document.getElementById('paidAmount');
  const balanceInput = document.getElementById('balanceAfterPayment');
  const historyBody = document.querySelector('.tuition-history tbody');

  if (!saveBtn || !overallInput || !paidInput || !balanceInput || !historyBody) return;

  const formatter = new Intl.NumberFormat('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });

  function parseAmount(value) {
    if (!value) return 0;
    const cleaned = value.replace(/[^0-9.\-]/g, '');
    const num = parseFloat(cleaned);
    return Number.isFinite(num) ? num : 0;
  }

  function formatPHP(value) {
    return `PHP ${formatter.format(value)}`;
  }

  function formatPaidInput() {
    const paid = parseAmount(paidInput.value);
    paidInput.value = formatPHP(paid);
  }

  function clearEmptyHistoryRow() {
    const firstRow = historyBody.querySelector('tr');
    if (!firstRow) return;
    const cells = firstRow.querySelectorAll('td');
    if (cells.length === 3 && cells[2].textContent.trim() === 'No payment history yet.') {
      historyBody.innerHTML = '';
    }
  }

  paidInput.addEventListener('blur', formatPaidInput);

  saveBtn.addEventListener('click', () => {
    const overall = parseAmount(overallInput.value);
    const paid = parseAmount(paidInput.value);
    const balance = Math.max(overall - paid, 0);

    paidInput.value = formatPHP(paid);
    balanceInput.value = formatPHP(balance);

    clearEmptyHistoryRow();

    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    let hour24 = today.getHours();
    const ampm = hour24 >= 12 ? 'PM' : 'AM';
    let hour12 = hour24 % 12;
    if (hour12 === 0) hour12 = 12;
    const hh = String(hour12).padStart(2, '0');
    const min = String(today.getMinutes()).padStart(2, '0');
    const sec = String(today.getSeconds()).padStart(2, '0');
    const dateStr = `${yyyy}-${mm}-${dd} | ${hh}:${min}:${sec} ${ampm}`;

    const row = document.createElement('tr');
    row.innerHTML = `
      <td>${dateStr}</td>
      <td>${formatPHP(overall)}</td>
      <td>${formatPHP(paid)}</td>
    `;
    historyBody.prepend(row);
  });
})();

// Grade level history dropdown handler
(function () {
  const dropdown = document.getElementById('gradeLevelDropdown');
  const container = document.getElementById('gradeHistoryContainer');
  const annualTotalEl = document.getElementById('annualTotal');
  const totalPaidEl = document.getElementById('totalPaidForGrade');
  const balanceEl = document.getElementById('balanceForGrade');
  const paymentTableBody = document.getElementById('paymentTableBody');
  const savedInvoiceTableBody = document.getElementById('savedInvoiceTableBody');
  const sentEmailTableBody = document.getElementById('sentEmailTableBody');

  if (
    !dropdown ||
    !container ||
    !annualTotalEl ||
    !totalPaidEl ||
    !balanceEl ||
    !paymentTableBody ||
    !savedInvoiceTableBody ||
    !sentEmailTableBody
  ) {
    return;
  }

  const formatter = new Intl.NumberFormat('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });

  function formatMoney(amount) {
    const numericAmount = Number(amount) || 0;
    return `PHP ${formatter.format(numericAmount)}`;
  }

  function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(`${dateString}T00:00:00`);
    if (Number.isNaN(date.getTime())) return dateString;
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return date.toLocaleDateString('en-PH', options);
  }

  function formatTimestamp(dateString) {
    if (!dateString) return 'N/A';
    const normalized = typeof dateString === 'string' ? dateString.replace(' ', 'T') : dateString;
    const date = new Date(normalized);
    if (Number.isNaN(date.getTime())) return dateString;
    const options = {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: 'numeric',
      minute: '2-digit',
    };
    return date.toLocaleString('en-PH', options);
  }

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function renderEmptyRow(tableBody, colspan, message) {
    const row = document.createElement('tr');
    row.innerHTML = `<td colspan="${colspan}" style="text-align: center; padding: 20px; color: #999;">${escapeHtml(message)}</td>`;
    tableBody.appendChild(row);
  }

  function renderPaymentHistory(gradeKey) {
    const payments = Array.isArray(detailedPaymentHistory[gradeKey]) ? detailedPaymentHistory[gradeKey] : [];
    paymentTableBody.innerHTML = '';

    if (payments.length === 0) {
      renderEmptyRow(paymentTableBody, 5, 'No payments recorded for this grade level.');
      return;
    }

    payments.forEach((payment) => {
      const row = document.createElement('tr');
      row.innerHTML = `
        <td>${escapeHtml(formatDate(payment.payment_date))}</td>
        <td><strong>${escapeHtml(payment.receipt_no || 'N/A')}</strong></td>
        <td class="amount-col">${escapeHtml(formatMoney(payment.amount_paid))}</td>
        <td>${escapeHtml(payment.school_year || 'N/A')}</td>
        <td class="amount-col ${Number(payment.balance_after) > 0 ? 'balance-pending' : 'balance-settled'}">${escapeHtml(formatMoney(payment.balance_after))}</td>
      `;
      paymentTableBody.appendChild(row);
    });
  }

  function renderSavedInvoices(gradeKey) {
    const invoices = Array.isArray(savedInvoiceHistoryByGrade[gradeKey]) ? savedInvoiceHistoryByGrade[gradeKey] : [];
    savedInvoiceTableBody.innerHTML = '';

    if (invoices.length === 0) {
      renderEmptyRow(savedInvoiceTableBody, 6, 'No saved invoices for this grade level.');
      return;
    }

    invoices.forEach((invoice) => {
      const row = document.createElement('tr');
      row.innerHTML = `
        <td>${escapeHtml(formatDate(invoice.payment_date))}</td>
        <td><strong>${escapeHtml(invoice.receipt_no || 'N/A')}</strong></td>
        <td>${escapeHtml(invoice.payment_items || 'N/A')}</td>
        <td class="amount-col">${escapeHtml(formatMoney(invoice.amount_paid))}</td>
        <td class="amount-col ${Number(invoice.balance_after) > 0 ? 'balance-pending' : 'balance-settled'}">${escapeHtml(formatMoney(invoice.balance_after))}</td>
        <td><span class="history-status ${Number(invoice.email_sent) === 1 ? 'sent' : 'pending'}">${Number(invoice.email_sent) === 1 ? 'Sent' : 'Not sent'}</span></td>
      `;
      savedInvoiceTableBody.appendChild(row);
    });
  }

  function renderSentEmails(gradeKey) {
    const emails = Array.isArray(sentEmailHistoryByGrade[gradeKey]) ? sentEmailHistoryByGrade[gradeKey] : [];
    sentEmailTableBody.innerHTML = '';

    if (emails.length === 0) {
      renderEmptyRow(sentEmailTableBody, 6, 'No sent email history for this grade level.');
      return;
    }

    emails.forEach((historyRow) => {
      const row = document.createElement('tr');
      row.innerHTML = `
        <td>${escapeHtml(formatTimestamp(historyRow.sent_at))}</td>
        <td>${escapeHtml(historyRow.type || 'N/A')}</td>
        <td><strong>${escapeHtml(historyRow.invoice_no || 'N/A')}</strong></td>
        <td>${escapeHtml(historyRow.payment_items || 'N/A')}</td>
        <td class="amount-col">${escapeHtml(formatMoney(historyRow.amount))}</td>
        <td>${escapeHtml(historyRow.email || 'N/A')}</td>
      `;
      sentEmailTableBody.appendChild(row);
    });
  }

  function renderGradeHistory(gradeKey) {
    const gradeData = Array.isArray(gradeLevelHistory)
      ? gradeLevelHistory.find((entry) => entry.grade_key === gradeKey)
      : null;

    if (!gradeData) {
      container.style.display = 'none';
      return;
    }

    annualTotalEl.textContent = formatMoney(gradeData.annual_total);
    totalPaidEl.textContent = formatMoney(gradeData.total_paid);
    balanceEl.textContent = formatMoney(gradeData.last_balance);
    renderPaymentHistory(gradeKey);
    renderSavedInvoices(gradeKey);
    renderSentEmails(gradeKey);
    container.style.display = 'block';
  }

  dropdown.addEventListener('change', function () {
    const selectedGrade = this.value;
    if (!selectedGrade) {
      container.style.display = 'none';
      return;
    }

    renderGradeHistory(selectedGrade);
  });

  if (currentGradeHistoryKey && Array.from(dropdown.options).some((option) => option.value === currentGradeHistoryKey)) {
    dropdown.value = currentGradeHistoryKey;
    renderGradeHistory(currentGradeHistoryKey);
  }
})();
