<!-- Delete Drug Modal -->
<div class="modal fade" id="deleteDrugModal" tabindex="-1" role="dialog" aria-labelledby="deleteDrugModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form id="deleteDrugForm" method="POST" action="drug_crud/delete.php">
        <div class="modal-header">
          <h5 class="modal-title">Delete Drug</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="delete_id">
          <p>Are you sure you want to delete this drug?</p>
          <p><strong id="delete_drug_name"></strong></p>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-danger">Delete</button>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    include('../../dbcon.php');
    $id = intval($_POST['id']);

    // Check if this drug is used in order_items
    $check = $con->prepare("SELECT COUNT(*) FROM order_items WHERE product_id=?");
    $check->bind_param("i", $id);
    $check->execute();
    $check->bind_result($count);
    $check->fetch();
    $check->close();

    if ($count > 0) {
        // Cannot delete, drug is linked to orders
        header("Location: ../view.php?msg=linked");
        exit;
    }

    // Safe to delete
    $stmt = $con->prepare("DELETE FROM stock WHERE id=?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        header("Location: ../view.php?msg=deleted");
        exit;
    } else {
        header("Location: ../view.php?msg=error");
        exit;
    }
}
?>

