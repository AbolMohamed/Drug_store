<!-- Enhanced Register Drug Modal -->
<div class="modal fade" id="registerDrugModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <form action="drug_crud/save_drug.php" method="post" id="drugForm" novalidate enctype="multipart/form-data">
        <div class="modal-header">
          <h5 class="modal-title">Register New Drug</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>

        <div class="modal-body row g-3">

          <!-- Product Code -->
          <div class="col-md-6">
            <label>Batch Number *</label>
            <input type="text" name="product_code" id="product_code" class="form-control" required>
            <div class="invalid-feedback">Batch Number is required.</div>
          </div>

          <!-- Medicine Name -->
          <div class="col-md-6">
            <label>Medicine Name *</label>
            <input type="text" name="medicine_name" id="medicine_name" class="form-control" required>
            <div class="invalid-feedback">Medicine Name is required.</div>
          </div>

          <!-- Category -->
          <div class="col-md-6">
            <label>Category *</label>
            <select name="category" id="category" class="form-control" required>
              <option value="">--Select Category--</option>
              <?php
              $catRes = mysqli_query($con,"SELECT id, name FROM drug_categories ORDER BY name ASC");
              while($cat = mysqli_fetch_assoc($catRes)){
                  echo "<option value='".htmlspecialchars($cat['name'])."'>".htmlspecialchars($cat['name'])."</option>";
              }
              ?>
            </select>
            <div class="invalid-feedback">Please select a Category.</div>
          </div>

          <!-- Supplier Selection -->
          <div class="col-md-6">
            <label>Supplier *</label>
            <select name="supplier_id" id="supplier_id" class="form-control" required>
              <option value="">--Select Supplier--</option>
              <?php
              $supRes = mysqli_query($con,"SELECT id, name FROM suppliers ORDER BY name ASC");
              while($sup = mysqli_fetch_assoc($supRes)){
                  echo "<option value='".$sup['id']."'>".htmlspecialchars($sup['name'])."</option>";
              }
              ?>
            </select>
            <div class="invalid-feedback">Please select a Supplier.</div>
          </div>

          <!-- Manufacturer -->
          <div class="col-md-6">
            <label>Manufacturer *</label>
            <input type="text" name="manufacturer" id="manufacturer" class="form-control" required>
            <div class="invalid-feedback">Manufacturer is required.</div>
          </div>

          <!-- Quantity Information -->
          <div class="col-md-4">
            <label>Total Quantity *</label>
            <input type="number" name="quantity" id="quantity" class="form-control" min="1" required>
            <div class="invalid-feedback">Quantity must be greater than 0.</div>
          </div>

          <div class="col-md-4">
            <label>Used Quantity</label>
            <input type="number" name="used_quantity" id="used_quantity" class="form-control" value="0" min="0" required>
            <div class="invalid-feedback">Used Quantity must be between 0 and Total Quantity.</div>
          </div>

          <div class="col-md-4">
            <label>Remaining Quantity</label>
            <input type="text" id="remain_quantity" class="form-control" readonly>
          </div>

          <!-- Expiry Date -->
          <div class="col-md-6">
            <label>Expiry Date *</label>
            <input type="date" name="expire_date" id="expire_date" class="form-control" required>
            <div class="invalid-feedback">Expiry Date must be today or later.</div>
          </div>

          <!-- Company/Shalve -->
          <div class="col-md-6">
            <label>Shalve/Location</label>
            <input type="text" name="company" id="company" class="form-control">
          </div>

          <!-- Pricing Information -->
          <div class="col-md-4">
            <label>Cost Price *</label>
            <input type="number" step="0.01" name="actual_price" id="actual_price" class="form-control" min="0.01" required>
            <div class="invalid-feedback">Cost Price must be greater than 0.</div>
          </div>

          <div class="col-md-4">
            <label>Selling Price *</label>
            <input type="number" step="0.01" name="selling_price" id="selling_price" class="form-control" min="0.01" required>
            <div class="invalid-feedback">Selling Price must be ≥ Cost Price.</div>
          </div>

          <!-- Profit Display -->
          <div class="col-md-2">
            <label>Profit (ETB)</label>
            <input type="text" id="profit_birr" class="form-control" readonly>
          </div>
          <div class="col-md-2">
            <label>Profit %</label>
            <input type="text" id="profit_percent" class="form-control" readonly>
          </div>

          <!-- Image Upload -->
          <div class="col-md-12">
            <label>Product Image</label>
            <input type="file" name="product_image" id="product_image" class="form-control" accept="image/*">
            <small class="form-text text-muted">Optional: Upload product image (JPG, PNG, GIF)</small>
          </div>

          <!-- Controlled Substance Information (Conditional) -->
          <div class="col-md-12 mt-3 controlled-substance-section" style="display: none;">
            <div class="card border-warning">
              <div class="card-header bg-warning text-dark">
                <h6 class="mb-0">Controlled Substance Information</h6>
              </div>
              <div class="card-body">
                <div class="row">
                  <div class="col-md-6">
                    <label>Schedule Class</label>
                    <select name="schedule" id="schedule" class="form-control">
                      <option value="">--Not Controlled--</option>
                      <option value="I">Schedule I</option>
                      <option value="II">Schedule II</option>
                      <option value="III">Schedule III</option>
                      <option value="IV">Schedule IV</option>
                      <option value="V">Schedule V</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label>Is Controlled Substance?</label>
                    <div class="form-check mt-2">
                      <input type="checkbox" name="is_controlled" id="is_controlled" class="form-check-input">
                      <label class="form-check-label" for="is_controlled">Yes, this is a controlled substance</label>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Save Drug</button>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Enhanced JavaScript -->
<script>
(function(){
  // Helper functions
  const byId = id => document.getElementById(id);
  const form = byId('drugForm');
  
  // Form elements
  const elements = {
    productCode: byId('product_code'),
    medicineName: byId('medicine_name'),
    category: byId('category'),
    supplier: byId('supplier_id'),
    manufacturer: byId('manufacturer'),
    quantity: byId('quantity'),
    usedQuantity: byId('used_quantity'),
    remainQuantity: byId('remain_quantity'),
    expireDate: byId('expire_date'),
    company: byId('company'),
    actualPrice: byId('actual_price'),
    sellingPrice: byId('selling_price'),
    profitBirr: byId('profit_birr'),
    profitPercent: byId('profit_percent'),
    isControlled: byId('is_controlled'),
    controlledSection: document.querySelector('.controlled-substance-section'),
    schedule: byId('schedule')
  };

  // Utility functions
  function clearInvalid(el) {
    if (!el) return;
    el.classList.remove('is-invalid');
  }

  function setInvalid(el) {
    if (!el) return;
    el.classList.add('is-invalid');
  }

  function parseIntSafe(v) {
    const n = parseInt(String(v||'').trim(), 10);
    return isNaN(n) ? NaN : n;
  }

  function parseFloatSafe(v) {
    const n = parseFloat(String(v||'').trim());
    return isNaN(n) ? NaN : n;
  }

  // Calculate remaining quantity and profit
  function recalcFields() {
    // Quantity calculations
    let qty = parseIntSafe(elements.quantity.value) || 0;
    let used = parseIntSafe(elements.usedQuantity.value) || 0;
    
    if (used > qty) {
      elements.usedQuantity.value = qty;
      used = qty;
    }
    
    elements.remainQuantity.value = qty - used;

    // Profit calculations
    let actual = parseFloatSafe(elements.actualPrice.value) || 0;
    let sell = parseFloatSafe(elements.sellingPrice.value) || 0;
    
    const profit = sell - actual;
    elements.profitBirr.value = profit.toFixed(2);
    elements.profitPercent.value = (actual > 0) ? ((profit / actual) * 100).toFixed(2) : '0.00';

    // Update status based on remaining quantity
    updateStatusDisplay();
  }

  function updateStatusDisplay() {
    const remaining = parseIntSafe(elements.remainQuantity.value) || 0;
    // You can add visual status indicator here if needed
  }

  // Controlled substance toggle
  function toggleControlledSection() {
    if (elements.isControlled.checked) {
      elements.controlledSection.style.display = 'block';
      elements.schedule.required = true;
    } else {
      elements.controlledSection.style.display = 'none';
      elements.schedule.required = false;
      elements.schedule.value = '';
    }
  }

  // Field validation
  function validateField(el) {
    if (!el) return true;
    const id = el.id;
    clearInvalid(el);

    const validators = {
      'product_code': () => !!el.value.trim(),
      'medicine_name': () => !!el.value.trim(),
      'category': () => !!el.value,
      'supplier_id': () => !!el.value,
      'manufacturer': () => !!el.value.trim(),
      'quantity': () => {
        const v = parseIntSafe(el.value);
        return !isNaN(v) && v > 0;
      },
      'used_quantity': () => {
        const used = parseIntSafe(el.value);
        const qty = parseIntSafe(elements.quantity.value);
        return !isNaN(used) && used >= 0 && (isNaN(qty) || used <= qty);
      },
      'expire_date': () => {
        if (!el.value) return false;
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const selectedDate = new Date(el.value);
        return selectedDate >= today;
      },
      'actual_price': () => {
        const v = parseFloatSafe(el.value);
        return !isNaN(v) && v > 0;
      },
      'selling_price': () => {
        const sell = parseFloatSafe(el.value);
        const buy = parseFloatSafe(elements.actualPrice.value);
        return !isNaN(sell) && sell > 0 && (isNaN(buy) || sell >= buy);
      },
      'schedule': () => {
        if (!elements.isControlled.checked) return true;
        return !!el.value;
      }
    };

    if (validators[id] && !validators[id]()) {
      setInvalid(el);
      return false;
    }

    return true;
  }

  // Event listeners
  function initializeEventListeners() {
    const inputs = form.querySelectorAll('input, select, textarea');
    
    inputs.forEach(input => {
      input.addEventListener('input', () => {
        recalcFields();
        validateField(input);
      });
      
      input.addEventListener('change', () => {
        recalcFields();
        validateField(input);
      });
    });

    // Controlled substance toggle
    elements.isControlled.addEventListener('change', toggleControlledSection);

    // Form submission
    form.addEventListener('submit', function(e) {
      recalcFields();
      
      // Clear previous validation
      inputs.forEach(input => input.classList.remove('is-invalid'));

      // Validate all fields
      const fieldsToValidate = [
        elements.productCode, elements.medicineName, elements.category,
        elements.supplier, elements.manufacturer, elements.quantity,
        elements.usedQuantity, elements.expireDate, elements.actualPrice,
        elements.sellingPrice
      ];

      if (elements.isControlled.checked) {
        fieldsToValidate.push(elements.schedule);
      }

      let firstInvalid = null;
      fieldsToValidate.forEach(field => {
        if (!validateField(field) && !firstInvalid) {
          firstInvalid = field;
        }
      });

      if (firstInvalid) {
        e.preventDefault();
        firstInvalid.focus();
        if (firstInvalid.select) firstInvalid.select();
        
        // Scroll to first invalid field
        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    });
  }

  // Modal show event
  const modalEl = document.getElementById('registerDrugModal');
  if (modalEl) {
    modalEl.addEventListener('show.bs.modal', function() {
      form.reset();
      form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
      elements.controlledSection.style.display = 'none';
      elements.schedule.required = false;
      recalcFields();
    });
  }

  // Initialize
  document.addEventListener('DOMContentLoaded', function() {
    initializeEventListeners();
    recalcFields();
    toggleControlledSection();
  });

})();
</script>