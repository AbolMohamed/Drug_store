<div class="modal fade" id="importDrugsModal" tabindex="-1" role="dialog" aria-labelledby="importDrugsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="importDrugsModalLabel">
                    <i class="fas fa-file-import"></i> Import Drugs from Excel/CSV
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true" class="text-white">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- Import Instructions -->
                <div class="alert alert-info">
                    <h6><i class="fas fa-info-circle"></i> Import Instructions</h6>
                    <ul class="mb-0">
                        <li>Supported formats: Excel (.xlsx, .xls) or CSV (.csv)</li>
                        <li>File should contain columns: Product Code, Medicine Name, Category, Manufacturer, Quantity, Expire Date, Cost Price, Selling Price</li>
                        <li>Expire Date format: YYYY-MM-DD (e.g., 2025-12-31)</li>
                        <li>Optional columns: Used Quantity, Company (Shelf), Supplier ID, Status</li>
                        <li><a href="import-export/download_template.php" class="alert-link">Download template file</a></li>
                    </ul>
                </div>

                <!-- File Upload Form -->
                <form id="importDrugsForm" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="importFile">Select File:</label>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" id="importFile" name="import_file" accept=".xlsx,.xls,.csv" required>
                            <label class="custom-file-label" for="importFile">Choose file...</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="importAction">Import Action:</label>
                        <select class="form-control" id="importAction" name="import_action">
                            <option value="add_new">Add New Products Only</option>
                            <option value="update_existing">Update Existing Products</option>
                            <option value="add_update">Add New and Update Existing</option>
                            <option value="replace_all">Replace All Inventory</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="importCategory">Default Category (if not specified):</label>
                        <select class="form-control" id="importCategory" name="default_category">
                            <option value="">-- Use from file --</option>
                            <?php foreach ($categories as $id => $name): ?>
                                <option value="<?= $name ?>"><?= $name ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="importManufacturer">Default Manufacturer (if not specified):</label>
                        <input type="text" class="form-control" id="importManufacturer" name="default_manufacturer" placeholder="Default manufacturer">
                    </div>

                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="skipDuplicates" name="skip_duplicates" checked>
                        <label class="form-check-label" for="skipDuplicates">Skip duplicate product codes</label>
                    </div>

                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="validateData" name="validate_data" checked>
                        <label class="form-check-label" for="validateData">Validate data before import</label>
                    </div>

                    <!-- Preview Section -->
                    <div id="importPreview" style="display: none;">
                        <hr>
                        <h6><i class="fas fa-eye"></i> Import Preview</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered" id="previewTable">
                                <thead>
                                    <tr>
                                        <th>Product Code</th>
                                        <th>Medicine Name</th>
                                        <th>Category</th>
                                        <th>Quantity</th>
                                        <th>Expiry</th>
                                        <th>Cost Price</th>
                                        <th>Sell Price</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Preview rows will be inserted here -->
                                </tbody>
                            </table>
                        </div>
                        <div class="alert alert-warning" id="previewStats">
                            <!-- Preview statistics will be inserted here -->
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-info" id="previewImport">
                    <i class="fas fa-eye"></i> Preview
                </button>
                <button type="button" class="btn btn-primary" id="executeImport" disabled>
                    <i class="fas fa-upload"></i> Execute Import
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Import Results Modal -->
<div class="modal fade" id="importResultsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="fas fa-check-circle"></i> Import Results
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true" class="text-white">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="importResultsContent">
                    <!-- Results will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="location.reload()">
                    <i class="fas fa-sync"></i> Refresh Page
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // File input label update
    $('#importFile').on('change', function() {
        var fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').html(fileName);
        $('#executeImport').prop('disabled', true);
        $('#importPreview').hide();
    });

    // Preview import
    $('#previewImport').click(function() {
        var formData = new FormData($('#importDrugsForm')[0]);
        
        if (!$('#importFile')[0].files[0]) {
            alert('Please select a file first');
            return;
        }

        $.ajax({
            url: 'import-export/preview_import.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                var result = JSON.parse(response);
                if (result.success) {
                    displayPreview(result.data);
                    $('#executeImport').prop('disabled', false);
                } else {
                    alert('Error: ' + result.message);
                }
            }
        });
    });

    // Execute import
    $('#executeImport').click(function() {
        if (confirm('Are you sure you want to execute the import? This action cannot be undone.')) {
            var formData = new FormData($('#importDrugsForm')[0]);
            
            $.ajax({
                url: 'import-export/execute_import.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    var result = JSON.parse(response);
                    displayImportResults(result);
                    $('#importDrugsModal').modal('hide');
                    $('#importResultsModal').modal('show');
                }
            });
        }
    });

    function displayPreview(data) {
        var tbody = $('#previewTable tbody');
        tbody.empty();
        
        data.products.forEach(function(product) {
            var row = `<tr class="${product.status === 'error' ? 'table-danger' : product.exists ? 'table-warning' : 'table-success'}">
                <td>${product.product_code}</td>
                <td>${product.medicine_name}</td>
                <td>${product.category}</td>
                <td>${product.quantity}</td>
                <td>${product.expire_date}</td>
                <td>${product.actual_price}</td>
                <td>${product.selling_price}</td>
                <td>
                    ${product.status === 'error' ? '<span class="badge badge-danger">Error</span>' : 
                      product.exists ? '<span class="badge badge-warning">Update</span>' : 
                      '<span class="badge badge-success">New</span>'}
                    ${product.error ? '<br><small class="text-danger">' + product.error + '</small>' : ''}
                </td>
            </tr>`;
            tbody.append(row);
        });

        $('#previewStats').html(`
            <strong>Preview Summary:</strong><br>
            Total Records: ${data.summary.total}<br>
            New Products: ${data.summary.new}<br>
            Updates: ${data.summary.updates}<br>
            Errors: ${data.summary.errors}<br>
            Skipped: ${data.summary.skipped}
        `);

        $('#importPreview').show();
    }

    function displayImportResults(result) {
        var content = '';
        
        if (result.success) {
            content = `
                <div class="alert alert-success">
                    <h6><i class="fas fa-check-circle"></i> Import Completed Successfully!</h6>
                    <p>Import completed at: ${new Date().toLocaleString()}</p>
                </div>
                <div class="row">
                    <div class="col-md-3">
                        <div class="card text-center bg-success text-white">
                            <div class="card-body">
                                <h3>${result.summary.added}</h3>
                                <p>Added</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center bg-warning text-dark">
                            <div class="card-body">
                                <h3>${result.summary.updated}</h3>
                                <p>Updated</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center bg-secondary text-white">
                            <div class="card-body">
                                <h3>${result.summary.skipped}</h3>
                                <p>Skipped</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center bg-danger text-white">
                            <div class="card-body">
                                <h3>${result.summary.errors}</h3>
                                <p>Errors</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            if (result.errors && result.errors.length > 0) {
                content += `
                    <div class="mt-4">
                        <h6>Errors:</h6>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                ${result.errors.map(error => `<li>${error}</li>`).join('')}
                            </ul>
                        </div>
                    </div>
                `;
            }
        } else {
            content = `
                <div class="alert alert-danger">
                    <h6><i class="fas fa-exclamation-triangle"></i> Import Failed!</h6>
                    <p>${result.message}</p>
                </div>
            `;
        }
        
        $('#importResultsContent').html(content);
    }
});
</script>