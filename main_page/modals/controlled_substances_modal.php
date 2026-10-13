<?php
// Get all controlled substances
$controlled_sql = "SELECT cs.*, s.medicine_name, s.product_code, s.remain_quantity, s.expire_date 
                  FROM controlled_substances cs 
                  JOIN stock s ON cs.product_id = s.id 
                  ORDER BY cs.schedule, s.medicine_name";
$controlled_result = mysqli_query($con, $controlled_sql);
$controlled_substances = mysqli_fetch_all($controlled_result, MYSQLI_ASSOC);
?>

<div class="modal fade" id="controlledSubstancesModal" tabindex="-1" role="dialog" aria-labelledby="controlledSubstancesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="controlledSubstancesModalLabel">
                    <i class="fas fa-exclamation-triangle"></i> Controlled Substances Management
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true" class="text-white">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <?php if (!$can_manage_controlled): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-lock"></i> You don't have permission to manage controlled substances.
                    </div>
                <?php else: ?>
                    <!-- Add Controlled Substance Form -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-plus-circle"></i> Add Controlled Substance</h6>
                        </div>
                        <div class="card-body">
                            <form id="addControlledSubstanceForm">
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="csProduct">Product:</label>
                                        <select class="form-control" id="csProduct" name="product_id" required>
                                            <option value="">Select Product...</option>
                                            <?php
                                            $products_sql = "SELECT id, medicine_name, product_code FROM stock WHERE id NOT IN (SELECT product_id FROM controlled_substances) ORDER BY medicine_name";
                                            $products_result = mysqli_query($con, $products_sql);
                                            while ($product = mysqli_fetch_assoc($products_result)) {
                                                echo "<option value='{$product['id']}'>{$product['medicine_name']} ({$product['product_code']})</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="csSchedule">Schedule:</label>
                                        <select class="form-control" id="csSchedule" name="schedule" required>
                                            <option value="I">Schedule I</option>
                                            <option value="II">Schedule II</option>
                                            <option value="III">Schedule III</option>
                                            <option value="IV">Schedule IV</option>
                                            <option value="V">Schedule V</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-4">
                                        <label for="csDisposalDate">Disposal Date:</label>
                                        <input type="date" class="form-control" id="csDisposalDate" name="disposal_date">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="csDisposalMethod">Disposal Method:</label>
                                        <input type="text" class="form-control" id="csDisposalMethod" name="disposal_method" placeholder="e.g., Incineration, Return to supplier">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="csDisposalWitness">Disposal Witness:</label>
                                        <input type="text" class="form-control" id="csDisposalWitness" name="disposal_witness" placeholder="Witness name">
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-save"></i> Add Controlled Substance
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Controlled Substances List -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-list"></i> Controlled Substances List</h6>
                        </div>
                        <div class="card-body">
                            <?php if (count($controlled_substances) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead class="thead-dark">
                                            <tr>
                                                <th>Schedule</th>
                                                <th>Product Name</th>
                                                <th>Batch Code</th>
                                                <th>Stock</th>
                                                <th>Expiry</th>
                                                <th>Disposal Date</th>
                                                <th>Disposal Method</th>
                                                <th>Witness</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($controlled_substances as $cs): ?>
                                                <tr class="<?= strtotime($cs['expire_date']) < time() ? 'table-danger' : '' ?>">
                                                    <td>
                                                        <span class="badge badge-<?= 
                                                            $cs['schedule'] === 'I' ? 'danger' : 
                                                            ($cs['schedule'] === 'II' ? 'warning' : 
                                                            ($cs['schedule'] === 'III' ? 'info' : 
                                                            ($cs['schedule'] === 'IV' ? 'primary' : 'secondary')))
                                                        ?>">
                                                            Schedule <?= $cs['schedule'] ?>
                                                        </span>
                                                    </td>
                                                    <td><?= htmlspecialchars($cs['medicine_name']) ?></td>
                                                    <td><?= htmlspecialchars($cs['product_code']) ?></td>
                                                    <td><?= $cs['remain_quantity'] ?></td>
                                                    <td><?= date('M j, Y', strtotime($cs['expire_date'])) ?></td>
                                                    <td><?= $cs['disposal_date'] ? date('M j, Y', strtotime($cs['disposal_date'])) : 'Not set' ?></td>
                                                    <td><?= $cs['disposal_method'] ? htmlspecialchars($cs['disposal_method']) : 'Not set' ?></td>
                                                    <td><?= $cs['disposal_witness'] ? htmlspecialchars($cs['disposal_witness']) : 'Not set' ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-info edit-cs-btn" data-id="<?= $cs['id'] ?>" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-danger remove-cs-btn" data-id="<?= $cs['id'] ?>" title="Remove">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-exclamation-triangle fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No controlled substances found.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <?php if ($can_manage_controlled): ?>
                    <button type="button" class="btn btn-info" id="generateCSReport">
                        <i class="fas fa-file-pdf"></i> Generate Report
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Controlled Substance Detail Modal -->
<div class="modal fade" id="controlledSubstanceDetailModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="fas fa-info-circle"></i> Controlled Substance Details
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true" class="text-white">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="csDetailContent">
                <!-- Details will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Add controlled substance
    $('#addControlledSubstanceForm').submit(function(e) {
        e.preventDefault();
        
        $.ajax({
            url: 'drug_crud/add_controlled_substance.php',
            type: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                var result = JSON.parse(response);
                if (result.success) {
                    alert('Controlled substance added successfully');
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            }
        });
    });

    // Edit controlled substance
    $('.edit-cs-btn').click(function() {
        var csId = $(this).data('id');
        
        $.ajax({
            url: 'drug_crud/get_controlled_substance.php',
            type: 'POST',
            data: { cs_id: csId },
            success: function(response) {
                var csData = JSON.parse(response);
                if (csData.exists) {
                    // Populate edit form (you might want to create a separate edit modal)
                    $('#csDetailContent').html(`
                        <div class="form-group">
                            <label><strong>Product:</strong></label>
                            <p>${csData.product_name} (${csData.product_code})</p>
                        </div>
                        <div class="form-group">
                            <label><strong>Schedule:</strong></label>
                            <p>Schedule ${csData.schedule}</p>
                        </div>
                        <div class="form-group">
                            <label><strong>Disposal Date:</strong></label>
                            <p>${csData.disposal_date || 'Not set'}</p>
                        </div>
                        <div class="form-group">
                            <label><strong>Disposal Method:</strong></label>
                            <p>${csData.disposal_method || 'Not set'}</p>
                        </div>
                        <div class="form-group">
                            <label><strong>Disposal Witness:</strong></label>
                            <p>${csData.disposal_witness || 'Not set'}</p>
                        </div>
                    `);
                    $('#controlledSubstanceDetailModal').modal('show');
                }
            }
        });
    });

    // Remove controlled substance
    $('.remove-cs-btn').click(function() {
        var csId = $(this).data('id');
        
        if (confirm('Are you sure you want to remove this controlled substance designation?')) {
            $.ajax({
                url: 'drug_crud/remove_controlled_substance.php',
                type: 'POST',
                data: { cs_id: csId },
                success: function(response) {
                    var result = JSON.parse(response);
                    if (result.success) {
                        alert('Controlled substance designation removed');
                        location.reload();
                    } else {
                        alert('Error: ' + result.message);
                    }
                }
            });
        }
    });

    // Generate controlled substances report
    $('#generateCSReport').click(function() {
        window.open('import-export/generate_cs_report.php', '_blank');
    });
});
</script>