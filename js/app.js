document.addEventListener('DOMContentLoaded', function () {
  initSidebarToggle();
  initConfirmDelete();
    initModals();
  initQuotationBuilder();
});

/* ---------------- Accessible modal dialogs ---------------- */
function initModals() {
  var activeModal = null;
  var opener = null;

  function openModal(modal, trigger) {
    if (!modal) return;
    activeModal = modal;
    opener = trigger || null;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');
    var firstInput = modal.querySelector('input:not([type="hidden"]), select, textarea');
    if (firstInput) firstInput.focus();
  }

  function closeModal() {
    if (!activeModal) return;
    activeModal.classList.remove('is-open');
    activeModal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('modal-open');
    if (opener) opener.focus();
    activeModal = null;
  }

  document.querySelectorAll('[data-modal-open]').forEach(function (trigger) {
    trigger.addEventListener('click', function () {
      openModal(document.getElementById(trigger.getAttribute('data-modal-open')), trigger);
    });
  });

  document.querySelectorAll('.modal').forEach(function (modal) {
    modal.querySelectorAll('[data-modal-close]').forEach(function (close) {
      close.addEventListener('click', closeModal);
    });
    if (modal.classList.contains('is-open')) openModal(modal, null);
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') closeModal();
  });
}


/* ---------------- Sidebar toggle (mobile) ---------------- */
function initSidebarToggle() {
  var toggleBtn = document.getElementById('sidebarToggle');
  var sidebar = document.querySelector('.sidebar');
  if (!toggleBtn || !sidebar) return;

  toggleBtn.addEventListener('click', function () {
    sidebar.classList.toggle('open');
  });
}

/* ---------------- Confirm delete on forms ---------------- */
function initConfirmDelete() {
  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      var msg = form.getAttribute('data-confirm') || 'Are you sure?';
      if (!confirm(msg)) {
        e.preventDefault();
      }
    });
  });
}

/* ---------------- Quotation Item Builder ---------------- */
function initQuotationBuilder() {
  var container = document.getElementById('itemsContainer');
  if (!container) return;

  var addBtn = document.getElementById('addItemBtn');
  var template = document.getElementById('itemRowTemplate');
  var customerSelect = document.getElementById('customer_id');
  var gstYesCheckbox = document.getElementById('gst_yes');
  var gstNoCheckbox = document.getElementById('gst_no');
  var gstHiddenInput = document.getElementById('gst_applicable_hidden');
  var discountInput = document.getElementById('discount_amount');
  var discountErrorHint = document.getElementById('discountErrorHint');
  var rowIndex = parseInt(container.getAttribute('data-next-index'), 10) || 0;

  function isGstApplicable() {
    return !!(gstHiddenInput && gstHiddenInput.value === '1');
  }

  function setGstApplicable(applicable) {
    if (gstYesCheckbox) gstYesCheckbox.checked = applicable;
    if (gstNoCheckbox) gstNoCheckbox.checked = !applicable;
    if (gstHiddenInput) gstHiddenInput.value = applicable ? '1' : '0';
  }

  function bindRow(row) {
    var productSelect = row.querySelector('.js-product');
    var sizeInput = row.querySelector('.js-size');
    var rollsInput = row.querySelector('.js-rolls');
    var priceInput = row.querySelector('.js-price');
    var removeBtn = row.querySelector('.js-remove');
    var amountEl = row.querySelector('.js-amount');

    function recalcRow() {
    var size = parseFloat(sizeInput.value) || 0;
    var rolls = parseInt(rollsInput.value, 10) || 0;
    var price = parseFloat(priceInput.value) || 0;
    var totalMtr = size * rolls;
    var amount = rolls * price;   // was: totalMtr * price
    amountEl.textContent = formatMoney(amount);
    recalcTotals();
  }

    function fetchLastPrice() {
      var customerId = customerSelect ? customerSelect.value : null;
      var productId = productSelect.value;
      if (!customerId || !productId) return;

      var url = window.LAST_PRICE_URL + '?customer_id=' + encodeURIComponent(customerId) + '&product_id=' + encodeURIComponent(productId);

      fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (data.found && data.price_per_mtr !== null && !priceInput.dataset.userEdited) {
            priceInput.value = data.price_per_mtr;
            var hint = row.querySelector('.js-last-price-hint');
            if (hint) {
              hint.textContent = 'Last rate: ' + formatMoney(data.price_per_mtr) + '/Mtr';
              hint.style.display = 'block';
            }
            recalcRow();
          }
        })
        .catch(function () { /* silently ignore */ });
    }

    priceInput.addEventListener('input', function () {
      priceInput.dataset.userEdited = '1';
      recalcRow();
    });
    sizeInput.addEventListener('input', recalcRow);
    rollsInput.addEventListener('input', recalcRow);
    productSelect.addEventListener('change', function () {
      priceInput.dataset.userEdited = '';
      fetchLastPrice();
      recalcRow();
    });

    if (customerSelect) {
      customerSelect.addEventListener('change', function () {
        priceInput.dataset.userEdited = '';
        fetchLastPrice();
      });
    }

    removeBtn.addEventListener('click', function () {
      if (container.querySelectorAll('.item-row').length <= 1) {
        alert('A quotation must have at least one product line.');
        return;
      }
      row.remove();
      recalcTotals();
    });

    recalcRow();
  }

  function addRow() {
    var html = template.innerHTML.replace(/__INDEX__/g, rowIndex);
    var wrapper = document.createElement('div');
    wrapper.innerHTML = html.trim();
    var row = wrapper.firstElementChild;
    container.appendChild(row);
    bindRow(row);
    rowIndex++;
  }

  function recalcTotals() {
    var subTotal = 0;
    container.querySelectorAll('.item-row').forEach(function (row) {
      var rolls = parseInt(row.querySelector('.js-rolls').value, 10) || 0;
      var price = parseFloat(row.querySelector('.js-price').value) || 0;
      subTotal += rolls * price;   // was: size * rolls * price
  });

    var gstApplicable = isGstApplicable();
    var discount = discountInput ? (parseFloat(discountInput.value) || 0) : 0;

    // Discount cannot exceed the sub total - clamp it and show a hint.
    var discountExceeds = discount > subTotal;
    if (discountExceeds && discountInput) {
      discount = subTotal;
      discountInput.value = subTotal.toFixed(2);
    }
    if (discountErrorHint) discountErrorHint.style.display = discountExceeds ? 'block' : 'none';

    var discountedSubTotal = subTotal - discount;
    var gstAmount = gstApplicable ? discountedSubTotal * 0.18 : 0;

    var beforeRounding = discountedSubTotal + gstAmount;
    var roundedTotal = Math.round(beforeRounding);
    var roundOff = roundedTotal - beforeRounding;

    setText('summarySubTotal', formatMoney(subTotal));
    setText('summaryNetAmount', formatMoney(discountedSubTotal));
    setText('summaryGstAmount', formatMoney(gstAmount));
    setText('summaryRoundOff', (roundOff >= 0 ? '+' : '-') + '\u20b9' + formatMoney(Math.abs(roundOff)));
    setText('summaryTotal', formatMoney(roundedTotal));

    var gstRow = document.getElementById('summaryGstRow');
    if (gstRow) gstRow.style.display = gstApplicable ? 'inline' : 'none';

    var netAmountRow = document.getElementById('summaryNetAmountRow');
    if (netAmountRow) netAmountRow.style.display = discount > 0 ? 'flex' : 'none';
  }

  function setText(id, text) {
    var el = document.getElementById(id);
    if (el) el.textContent = text;
  }

  function formatMoney(num) {
    return Number(num || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  // Bind existing rows (edit mode) and wire up add button / gst toggle.
  container.querySelectorAll('.item-row').forEach(bindRow);
  addBtn.addEventListener('click', addRow);

  if (gstYesCheckbox) {
    gstYesCheckbox.addEventListener('change', function () {
      setGstApplicable(gstYesCheckbox.checked);
      recalcTotals();
    });
  }
  if (gstNoCheckbox) {
    gstNoCheckbox.addEventListener('change', function () {
      setGstApplicable(!gstNoCheckbox.checked);
      recalcTotals();
    });
  }
  if (discountInput) discountInput.addEventListener('input', recalcTotals);

  // If creating fresh, start with one row.
  if (container.querySelectorAll('.item-row').length === 0) {
    addRow();
  }

  recalcTotals();
}
