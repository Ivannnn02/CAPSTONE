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
(function() {
  const dropdown = document.getElementById('gradeLevelDropdown');
  const container = document.getElementById('gradeHistoryContainer');
  
  if (!dropdown || !container) return;

  function formatMoney(amount) {
    return 'PHP ' + parseFloat(amount).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function formatDate(dateString) {
    const date = new Date(dateString + 'T00:00:00');
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return date.toLocaleDateString('en-PH', options);
  }

  dropdown.addEventListener('change', function() {
    const selectedGrade = this.value;
    
    if (!selectedGrade || !detailedPaymentHistory[selectedGrade]) {
      container.style.display = 'none';
      return;
    }

    // Find the grade level summary
    const gradeData = gradeLevelHistory.find(g => g.grade_key === selectedGrade);
    document.getElementById('balanceForGrade').textContent = formatMoney(remainingBalance);

    // Populate payment table
    const payments = detailedPaymentHistory[selectedGrade] || [];
    const tableBody = document.getElementById('paymentTableBody');
    tableBody.innerHTML = '';

    if (payments.length === 0) {
      const row = document.createElement('tr');
      row.innerHTML = '<td colspan="5" style="text-align: center; padding: 20px; color: #999;">No payments recorded for this grade level.</td>';
      tableBody.appendChild(row);
    } else {
      payments.forEach(payment => {
        const row = document.createElement('tr');
        row.innerHTML = `
          <td>${formatDate(payment.payment_date)}</td>
          <td><strong>${payment.receipt_no || '—'}</strong></td>
          <td class="amount-col">${formatMoney(payment.amount_paid)}</td>
          <td>${payment.school_year}</td>
          <td class="amount-col ${payment.balance_after > 0 ? 'balance-pending' : 'balance-settled'}">${formatMoney(payment.balance_after)}</td>
        `;
        tableBody.appendChild(row);
      });
    }

    container.style.display = 'block';
  });
})();
