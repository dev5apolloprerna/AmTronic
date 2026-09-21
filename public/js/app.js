document.addEventListener('DOMContentLoaded', function () {
  initSidebarToggle();
  initConfirmDelete();
    initModals();
  initQuotationBuilder();
  initEmployeeForm();
});

/* ---------------- Employee form: login fields only for accounts that can log in ---------------- */
// Super Admins and employees whose designation is flagged "can log in" (e.g. Sales) need an
// email + password; everyone else is a record only. The server enforces this too - this just
// hides the password fields and toggles `required` so the form matches.
function initEmployeeForm() {
  var form = document.querySelector('form[data-employee-form]');
  if (!form) return;

  var role = form.querySelector('#role');
  var designation = form.querySelector('#designation_id');
  var email = form.querySelector('#email');
  var password = form.querySelector('#password');
  var confirmation = form.querySelector('#password_confirmation');
  var loginBlock = form.querySelector('[data-login-only]');
  var marks = form.querySelectorAll('[data-login-required-mark]');
  // Editing someone who already has a password: a blank password means "keep it".
  var passwordRequired = form.getAttribute('data-password-required') === '1';

  if (!role || !designation || !email || !password || !loginBlock) return;

  function canLogin() {
    if (role.value === 'super_admin') return true;
    var option = designation.options[designation.selectedIndex];
    return !!option && option.getAttribute('data-can-login') === '1';
  }

  function sync() {
    var on = canLogin();
    loginBlock.hidden = !on;
    email.required = on;
    password.required = on && passwordRequired;
    if (confirmation) confirmation.required = on && passwordRequired;
    marks.forEach(function (mark) { mark.hidden = !on; });
  }

  role.addEventListener('change', sync);
  designation.addEventListener('change', sync);
  sync();
}

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
  var adminChargesInput = document.getElementById('admin_charges');
  var materialHandlingChargesInput = document.getElementById('material_handling_charges');
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
    var itemSelect = row.querySelector('.js-item');
    var descriptionInput = row.querySelector('.js-description');
    var qtyInput = row.querySelector('.js-qty');
    var rateInput = row.querySelector('.js-rate');
    var removeBtn = row.querySelector('.js-remove');
    var amountEl = row.querySelector('.js-amount');

    // A description that is already there (edit mode) or that the user types is theirs:
    // picking a different item must not overwrite it.
    if (descriptionInput && descriptionInput.value.trim() !== '') {
      descriptionInput.dataset.userEdited = '1';
    }

    function recalcRow() {
      var qty = parseFloat(qtyInput.value) || 0;
      var rate = parseFloat(rateInput.value) || 0;
      amountEl.textContent = formatMoney(Math.round(qty * rate * 100) / 100);
      recalcTotals();
    }

    function fetchLastRate() {
      var customerId = customerSelect ? customerSelect.value : null;
      var item = itemSelect.value;
      if (!customerId || !item) return;

      var url = window.LAST_PRICE_URL + '?customer_id=' + encodeURIComponent(customerId) + '&item=' + encodeURIComponent(item);

      fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (data.found && data.rate !== null && !rateInput.dataset.userEdited) {
            rateInput.value = data.rate;
            var hint = row.querySelector('.js-last-price-hint');
            if (hint) {
              hint.textContent = 'Last rate: \u20b9' + formatMoney(data.rate);
              hint.style.display = 'block';
            }
            recalcRow();
          }
        })
        .catch(function () { /* silently ignore */ });
    }

    rateInput.addEventListener('input', function () {
      rateInput.dataset.userEdited = '1';
      recalcRow();
    });
    qtyInput.addEventListener('input', recalcRow);
    if (descriptionInput) {
      descriptionInput.addEventListener('input', function () {
        descriptionInput.dataset.userEdited = '1';
      });
    }
    itemSelect.addEventListener('change', function () {
      rateInput.dataset.userEdited = '';
      var hint = row.querySelector('.js-last-price-hint');
      if (hint) hint.style.display = 'none';

      // Pre-fill the description from the product / material, unless the user wrote their own.
      if (descriptionInput && !descriptionInput.dataset.userEdited) {
        var option = itemSelect.options[itemSelect.selectedIndex];
        descriptionInput.value = option ? (option.getAttribute('data-description') || '') : '';
      }

      fetchLastRate();
      recalcRow();
    });

    if (customerSelect) {
      customerSelect.addEventListener('change', function () {
        rateInput.dataset.userEdited = '';
        fetchLastRate();
      });
    }

    removeBtn.addEventListener('click', function () {
      if (container.querySelectorAll('.item-row').length <= 1) {
        alert('A quotation must have at least one item line.');
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
      var qty = parseFloat(row.querySelector('.js-qty').value) || 0;
      var rate = parseFloat(row.querySelector('.js-rate').value) || 0;
      subTotal += Math.round(qty * rate * 100) / 100;
    });

    var gstApplicable = isGstApplicable();
    var discount = discountInput ? (parseFloat(discountInput.value) || 0) : 0;
    var adminCharges = adminChargesInput ? (parseFloat(adminChargesInput.value) || 0) : 0;
    var materialHandlingCharges = materialHandlingChargesInput ? (parseFloat(materialHandlingChargesInput.value) || 0) : 0;

    // Discount cannot exceed the sub total - clamp it and show a hint.
    var discountExceeds = discount > subTotal;
    if (discountExceeds && discountInput) {
      discount = subTotal;
      discountInput.value = subTotal.toFixed(2);
    }
    if (discountErrorHint) discountErrorHint.style.display = discountExceeds ? 'block' : 'none';

    var taxableAmount = subTotal - discount + adminCharges + materialHandlingCharges;
    var gstAmount = gstApplicable ? taxableAmount * 0.18 : 0;

    var beforeRounding = taxableAmount + gstAmount;
    var roundedTotal = Math.round(beforeRounding);
    var roundOff = roundedTotal - beforeRounding;

    setText('summarySubTotal', formatMoney(subTotal));
    setText('summaryNetAmount', formatMoney(taxableAmount));
    setText('summaryGstAmount', formatMoney(gstAmount));
    setText('summaryRoundOff', (roundOff >= 0 ? '+' : '-') + '\u20b9' + formatMoney(Math.abs(roundOff)));
    setText('summaryTotal', formatMoney(roundedTotal));

    var gstRow = document.getElementById('summaryGstRow');
    if (gstRow) gstRow.style.display = gstApplicable ? 'inline' : 'none';

    var netAmountRow = document.getElementById('summaryNetAmountRow');
    if (netAmountRow) netAmountRow.style.display = discount > 0 || adminCharges > 0 || materialHandlingCharges > 0 ? 'flex' : 'none';
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
  if (adminChargesInput) adminChargesInput.addEventListener('input', recalcTotals);
  if (materialHandlingChargesInput) materialHandlingChargesInput.addEventListener('input', recalcTotals);

  // If creating fresh, start with one row.
  if (container.querySelectorAll('.item-row').length === 0) {
    addRow();
  }

  recalcTotals();
}
