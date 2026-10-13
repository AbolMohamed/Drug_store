<div class="modal fade" id="bulkUpdateModal" tabindex="-1" role="dialog" aria-labelledby="bulkUpdateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="bulkUpdateModalLabel">
                    <i class="fas fa-edit"></i> Bulk Update Products
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> This will update all selected products with the specified values.
                </div>

                <form id="bulkUpdateForm">
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="bulkUpdateField">Field to Update:</label>
                            <select class="form-control" id="bulkUpdateField" name="field" required>
                                <option value="">Select Field...</option>
                                <option value="category">Category</option>
                                <option value="manufacturer">Manufacturer</option>
                                <option value="status">Status</option>
                                <option value="selling_price">Selling Price</option>
                                <option value="actual_price">Cost Price</option>
                                <option value="company">Shelf Location</option>
                                <option value="supplier_id">Supplier</option>
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="bulkUpdateValue">New Value:</label>
                            <div id="bulkUpdateValueContainer">
                                <input type="text" class="form-control" id="bulkUpdateValue" name="value" placeholder="Enter new value..." required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="bulkUpdateCondition">Apply Condition (Optional):</label>
                        <select class="form-control" id="bulkUpdateCondition" name="condition">
                            <option value="all">All Selected Items</option>
                            <option value="low_stock">Only Low Stock Items (≤ <?= $low_stock_threshold ?>)</option>
                            <option value="expired">Only Expired Items</option>
                            <option value="expiring_soon">Only Expiring Soon (≤ <?= $expiry_warning_days ?> days)</option>
                            <option value="out_of_stock">Only Out of Stock Items</option>
                            <option value="available">Only Available Items</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="bulkUpdateNotes">Update Notes (for audit):</label>
                        <textarea class="form-control" id="bulkUpdateNotes" name="notes" rows="2" placeholder="Reason for this bulk update..."></textarea>
                    </div>

                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> 
                        <strong>Warning:</strong> This action will affect <span id="selectedCount">0</span> products and cannot be undone easily.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="previewBulkUpdate">
                    <i class="fas fa-eye"></i> Preview Changes
                </button>
                <button type="button" class="btn btn-primary" id="executeBulkUpdate" disabled>
                    <i class="fas fa-play"></i> Execute Update
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewBulkUpdateModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="fas fa-eye"></i> Preview Bulk Update
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true" class="text-white">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="previewContent">
                    <!-- Preview content will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Back to Edit</button>
                <button type="button" class="btn btn-primary" id="confirmBulkUpdate">
                    <i class="fas fa-check"></i> Confirm and Execute
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Dynamic value field based on selected field
    $('#bulkUpdateField').change(function() {
        var field = $(this).val();
        var container = $('#bulkUpdateValueContainer');
        
        switch(field) {
            case 'category':
                container.html('<select class="form-control" id="bulkUpdateValue" name="value" required><?php foreach($categories as $id => $name): ?><option value="<?= $name ?>"><?= $name ?></option><?php endforeach; ?></select>');
                break;
            case 'status':
                container.html('<select class="form-control" id="bulkUpdateValue" name="value" required><option value="Available">Available</option><option value="Out of Stock">Out of Stock</option></select>');
                break;
            case 'supplier_id':
                container.html('<select class="form-control" id="bulkUpdateValue" name="value" required><?php foreach($suppliers as $id => $name): ?><option value="<?= $id ?>"><?= $name ?></option><?php endforeach; ?></select>');
                break;
            case 'selling_price':
            case 'actual_price':
                container.html('<div class="input-group"><div class="input-group-prepend"><span class="input-group-text"><?= $currency_symbol ?></span></div><input type="number" step="0.01" min="0" class="form-control" id="bulkUpdateValue" name="value" placeholder="0.00" required></div>');
                break;
            default:
                container.html('<input type="text" class="form-control" id="bulkUpdateValue" name="value" placeholder="Enter new value..." required>');
        }
    });

    // Update selected count
    function updateSelectedCount() {
        var selectedCount = $('.row-select:checked').length;
        $('#selectedCount').text(selectedCount);
        $('#executeBulkUpdate').prop('disabled', selectedCount === 0);
    }

    // Update count when modal is shown
    $('#bulkUpdateModal').on('show.bs.modal', function() {
        updateSelectedCount();
    });

    // Preview bulk update
    $('#previewBulkUpdate').click(function() {
        var selectedIds = [];
        $('.row-select:checked').each(function() {
            selectedIds.push($(this).val());
        });

        if (selectedIds.length === 0) {
            alert('Please select at least one product');
            return;
        }

        var formData = $('#bulkUpdateForm').serialize() + '&ids=' + selectedIds.join(',');

        $.ajax({
            url: 'drug_crud/preview_bulk_update.php',
            type: 'POST',
            data: formData,
            success: function(response) {
                $('#previewContent').html(response);
                $('#previewBulkUpdateModal').modal('show');
            }
        });
    });

    // Confirm and execute bulk update
    $('#confirmBulkUpdate').click(function() {
        var selectedIds = [];
        $('.row-select:checked').each(function() {
            selectedIds.push($(this).val());
        });

        var formData = $('#bulkUpdateForm').serialize() + '&ids=' + selectedIds.join(',');

        $.ajax({
            url: 'drug_crud/execute_bulk_update.php',
            type: 'POST',
            data: formData,
            success: function(response) {
                var result = JSON.parse(response);
                if (result.success) {
                    alert('Successfully updated ' + result.updated + ' products');
                    $('#previewBulkUpdateModal').modal('hide');
                    $('#bulkUpdateModal').modal('hide');
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            }
        });
    });
});
</script>