<!-- Enhanced Edit Drug Modal -->
<div class="modal fade" id="editDrugModal" tabindex="-1" role="dialog" aria-labelledby="editDrugModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <form id="editDrugForm" method="POST" action="drug_crud/update.php" novalidate enctype="multipart/form-data">
        <div class="modal-header">
          <h5 class="modal-title">Edit Drug</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>

        <div class="modal-body row g-3">
          <input type="hidden" name="id" id="edit_id">

          <!-- Batch Number -->
          <div class="col-md-6">
            <label class="required-field">Batch Number</label>
            <input type="text" class="form-control" name="product_code" id="edit_product_code" required>
            <div class="invalid-feedback">Batch Number is required.</div>
          </div>

          <!-- Medicine Name -->
          <div class="col-md-6">
            <label class="required-field">Drug Name</label>
            <input type="text" class="form-control" name="medicine_name" id="edit_medicine_name" required>
            <div class="invalid-feedback">Drug Name is required.</div>
          </div>

          <!-- Category -->
          <div class="col-md-6">
            <label class="required-field">Category</label>
            <select name="category" id="edit_category" class="form-control" required>
              <option value="">--Select Category--</option>
              <?php
              $catRes = mysqli_query($con,"SELECT name FROM drug_categories ORDER BY name ASC");
              while($cat = mysqli_fetch_assoc($catRes)){
                  echo "<option value='".htmlspecialchars($cat['name'])."'>".htmlspecialchars($cat['name'])."</option>";
              }
              ?>
            </select>
            <div class="invalid-feedback">Category is required.</div>
          </div>

          <!-- Supplier Selection -->
          <div class="col-md-6">
            <label class="required-field">Supplier</label>
            <select name="supplier_id" id="edit_supplier_id" class="form-control" required>
              <option value="">--Select Supplier--</option>
              <?php
              $supRes = mysqli_query($con,"SELECT id, name FROM suppliers ORDER BY name ASC");
              while($sup = mysqli_fetch_assoc($supRes)){
                  echo "<option value='".$sup['id']."'>".htmlspecialchars($sup['name'])."</option>";
              }
              ?>
            </select>
            <div class="invalid-feedback">Supplier is required.</div>
          </div>

          <!-- Quantity Information -->
          <div class="col-md-4">
            <label class="required-field">Total Quantity</label>
            <input type="number" class="form-control" name="quantity" id="edit_quantity" min="1" required>
            <div class="invalid-feedback">Quantity must be greater than 0.</div>
          </div>

          <div class="col-md-4">
            <label class="required-field">Used Quantity</label>
            <input type="number" class="form-control" name="used_quantity" id="edit_used_quantity" min="0" required>
            <div class="invalid-feedback">Used Quantity must be between 0 and Total Quantity.</div>
          </div>

          <div class="col-md-4">
            <label>Remaining Quantity</label>
            <input type="text" class="form-control" id="edit_remain_quantity" readonly>
            <small class="form-text text-muted">Auto-calculated</small>
          </div>

          <!-- Expiry Date -->
          <div class="col-md-6">
            <label class="required-field">Expiry Date</label>
            <input type="date" class="form-control" name="expire_date" id="edit_expire_date" required>
            <div class="invalid-feedback">Expiry Date must be today or later.</div>
          </div>

          <!-- Company/Shalve -->
          <div class="col-md-6">
            <label>Shalve/Location</label>
            <input type="text" class="form-control" name="company" id="edit_company">
          </div>

          <!-- Pricing Information -->
          <div class="col-md-4">
            <label class="required-field">Cost Price (ETB)</label>
            <input type="number" step="0.01" class="form-control" name="actual_price" id="edit_actual_price" min="0.01" required>
            <div class="invalid-feedback">Cost Price must be greater than 0.</div>
          </div>

          <div class="col-md-4">
            <label class="required-field">Selling Price (ETB)</label>
            <input type="number" step="0.01" class="form-control" name="selling_price" id="edit_selling_price" min="0.01" required>
            <div class="invalid-feedback">Selling Price must be ≥ Cost Price.</div>
          </div>

          <!-- Profit Display -->
          <div class="col-md-2">
            <label>Profit (ETB)</label>
            <input type="text" id="edit_profit_birr" class="form-control" readonly>
          </div>
          <div class="col-md-2">
            <label>Profit %</label>
            <input type="text" id="edit_profit_percent" class="form-control" readonly>
          </div>

          <!-- Image Upload -->
          <div class="col-md-12">
            <label>Product Image</label>
            <input type="file" name="product_image" id="edit_product_image" class="form-control" accept="image/*">
            <small class="form-text text-muted">Leave empty to keep current image</small>
            <div id="edit_current_image" class="mt-2"></div>
          </div>
          <!-- Status -->
          <div class="col-md-12">
            <label class="required-field">Status</label>
            <select class="form-control" name="status" id="edit_status" required>
              <option value="Available">Available</option>
              <option value="Out of Stock">Out of Stock</option>
            </select>
            <div class="invalid-feedback">Please select a status.</div>
          </div>

          <!-- Audit Information -->
          <div class="col-md-12">
            <div class="card border-info mt-3">
              <div class="card-header bg-info text-white">
                <h6 class="mb-0">Stock Information</h6>
              </div>
              <div class="card-body">
                <div class="row">
                  <div class="col-md-4">
                    <strong>Low Stock Threshold:</strong> 
                    <span id="edit_low_stock_threshold" class="badge badge-warning">10 units</span>
                  </div>
                  <div class="col-md-4">
                    <strong>Expiry Warning:</strong> 
                    <span id="edit_expiry_warning" class="badge badge-success">30 days</span>
                  </div>
                  <div class="col-md-4">
                    <strong>Stock Status:</strong> 
                    <span id="edit_stock_status_badge" class="badge badge-success">Available</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Update Drug</button>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
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
  const form = byId('editDrugForm');
  
  // Form elements
  const elements = {
    id: byId('edit_id'),
    productCode: byId('edit_product_code'),
    medicineName: byId('edit_medicine_name'),
    category: byId('edit_category'),
    supplier: byId('edit_supplier_id'),
    manufacturer: byId('edit_manufacturer'),
    quantity: byId('edit_quantity'),
    usedQuantity: byId('edit_used_quantity'),
    remainQuantity: byId('edit_remain_quantity'),
    expireDate: byId('edit_expire_date'),
    company: byId('edit_company'),
    actualPrice: byId('edit_actual_price'),
    sellingPrice: byId('edit_selling_price'),
    profitBirr: byId('edit_profit_birr'),
    profitPercent: byId('edit_profit_percent'),
    productImage: byId('edit_product_image'),
    currentImage: byId('edit_current_image'),
    isControlled: byId('edit_is_controlled'),
    schedule: byId('edit_schedule'),
    status: byId('edit_status'),
    lowStockThreshold: byId('edit_low_stock_threshold'),
    expiryWarning: byId('edit_expiry_warning'),
    stockStatusBadge: byId('edit_stock_status_badge')
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

    // Update stock status
    updateStockStatus();
  }

  function updateStockStatus() {
    const remaining = parseIntSafe(elements.remainQuantity.value) || 0;
    const threshold = 10; // From system_settings
    
    let statusClass = 'badge-success';
    let statusText = 'Available';
    
    if (remaining === 0) {
      statusClass = 'badge-danger';
      statusText = 'Out of Stock';
    } else if (remaining <= threshold) {
      statusClass = 'badge-warning';
      statusText = 'Low Stock';
    }
    
    elements.stockStatusBadge.className = `badge ${statusClass}`;
    elements.stockStatusBadge.textContent = statusText;
  }

  // Controlled substance toggle
  function toggleControlledSection() {
    const controlledSection = document.querySelector('.card.border-warning');
    if (elements.isControlled.checked) {
      controlledSection.style.display = 'block';
      elements.schedule.required = true;
    } else {
      controlledSection.style.display = 'block'; // Keep visible but not required
      elements.schedule.required = false;
    }
  }

  // Field validation
  function validateField(el) {
    if (!el) return true;
    const id = el.id;
    clearInvalid(el);

    const validators = {
      'edit_product_code': () => !!el.value.trim(),
      'edit_medicine_name': () => !!el.value.trim(),
      'edit_category': () => !!el.value,
      'edit_supplier_id': () => !!el.value,
      'edit_manufacturer': () => !!el.value.trim(),
      'edit_quantity': () => {
        const v = parseIntSafe(el.value);
        return !isNaN(v) && v > 0;
      },
      'edit_used_quantity': () => {
        const used = parseIntSafe(el.value);
        const qty = parseIntSafe(elements.quantity.value);
        return !isNaN(used) && used >= 0 && (isNaN(qty) || used <= qty);
      },
      'edit_expire_date': () => {
        if (!el.value) return false;
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const selectedDate = new Date(el.value);
        return selectedDate >= today;
      },
      'edit_actual_price': () => {
        const v = parseFloatSafe(el.value);
        return !isNaN(v) && v > 0;
      },
      'edit_selling_price': () => {
        const sell = parseFloatSafe(el.value);
        const buy = parseFloatSafe(elements.actualPrice.value);
        return !isNaN(sell) && sell > 0 && (isNaN(buy) || sell >= buy);
      },
      'edit_schedule': () => {
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

  // Function to populate the form with drug data
  function populateEditForm(drugData) {
    elements.id.value = drugData.id;
    elements.productCode.value = drugData.product_code;
    elements.medicineName.value = drugData.medicine_name;
    elements.category.value = drugData.category;
    elements.supplier.value = drugData.supplier_id || '';
    elements.manufacturer.value = drugData.manufacturer;
    elements.quantity.value = drugData.quantity;
    elements.usedQuantity.value = drugData.used_quantity;
    elements.remainQuantity.value = drugData.remain_quantity;
    elements.expireDate.value = drugData.expire_date;
    elements.company.value = drugData.company || '';
    elements.actualPrice.value = drugData.actual_price;
    elements.sellingPrice.value = drugData.selling_price;
    elements.status.value = drugData.status;

    // Handle image display
    if (drugData.image_path) {
      elements.currentImage.innerHTML = `
        <div class="current-image-info">
          <strong>Current Image:</strong>
          <img src="../../${drugData.image_path}" alt="${drugData.medicine_name}" 
               style="max-width: 100px; max-height: 100px; display: block; margin-top: 5px;" 
               class="img-thumbnail">
          <small class="form-text text-muted">Upload new image to replace</small>
        </div>
      `;
    }

    // Handle controlled substance data
    if (drugData.schedule) {
      elements.isControlled.checked = true;
      elements.schedule.value = drugData.schedule;
    }

    // Recalculate all fields
    recalcFields();
    toggleControlledSection();
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
        elements.sellingPrice, elements.status
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
  const modalEl = document.getElementById('editDrugModal');
  if (modalEl) {
    modalEl.addEventListener('show.bs.modal', function(event) {
      const button = event.relatedTarget;
      
      // Extract drug data from data attributes
      const drugData = {
        id: button.getAttribute('data-id'),
        product_code: button.getAttribute('data-product-code'),
        medicine_name: button.getAttribute('data-medicine-name'),
        category: button.getAttribute('data-category'),
        supplier_id: button.getAttribute('data-supplier-id'),
        manufacturer: button.getAttribute('data-manufacturer'),
        quantity: button.getAttribute('data-quantity'),
        used_quantity: button.getAttribute('data-used-quantity'),
        remain_quantity: button.getAttribute('data-remain-quantity'),
        expire_date: button.getAttribute('data-expire-date'),
        company: button.getAttribute('data-company'),
        actual_price: button.getAttribute('data-actual-price'),
        selling_price: button.getAttribute('data-selling-price'),
        image_path: button.getAttribute('data-image-path'),
        schedule: button.getAttribute('data-schedule'),
        status: button.getAttribute('data-status')
      };
      
      // Populate the form
      populateEditForm(drugData);
      
      // Remove any validation errors
      form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
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

<style>
.required-field::after {
  content: " *";
  color: #dc3545;
}

.controlled-substance-section {
  border-left: 4px solid #ffc107;
}

.current-image-info {
  padding: 10px;
  background-color: #f8f9fa;
  border-radius: 4px;
  border: 1px solid #dee2e6;
}
</style>