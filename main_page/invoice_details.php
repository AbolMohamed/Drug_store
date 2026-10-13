<?php
session_start();
require '../dbcon.php';

// Check authentication
if (!isset($_SESSION['user_session'])) {
    header("Location: ../login.php");
    exit();
}

// Get invoice number from URL
$invoice_number = isset($_GET['invoice_number']) ? intval($_GET['invoice_number']) : 0;

if ($invoice_number <= 0) {
    die("Invalid invoice number");
}

// Get invoice data
$invoice_sql = "SELECT * FROM sales WHERE invoice_number = '$invoice_number'";
$invoice_result = mysqli_query($con, $invoice_sql);
$invoice = mysqli_fetch_assoc($invoice_result);

if (!$invoice) {
    die("Invoice not found");
}

// Get invoice items
$items_sql = "SELECT * FROM sales_items WHERE invoice_number = '$invoice_number'";
$items_result = mysqli_query($con, $items_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>HDS Drug Store - Invoice Details</title>
    <link rel="icon" type="image/png" sizes="16x16" href="../images/favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- CSS Links -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .invoice-container {
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin: 20px auto;
            max-width: 800px;
        }
        .invoice-header {
            border-bottom: 1px solid #eee;
            margin-bottom: 20px;
            padding-bottom: 20px;
        }
        .invoice-logo {
            max-height: 80px;
        }
        .invoice-title {
            font-size: 24px;
            font-weight: bold;
        }
        .invoice-table th {
            background-color: #6794bb;
            color: white;
        }
        .total-row {
            font-weight: bold;
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <div class="invoice-header">
            <div class="row">
                <div class="col-md-6">
                    <img src="../images/logo.png" alt="HDS Drug Store" class="invoice-logo">
                    <h2 class="invoice-title">HDS DRUG STORE</h2>
                    <p>123 Pharmacy Street<br>Addis Ababa, Ethiopia<br>
                    Phone: +251 912 345 678<br>Email: info@hdsdrugstore.com</p>
                </div>
                <div class="col-md-6 text-right">
                    <h3>INVOICE #<?= $invoice_number ?></h3>
                    <p><strong>Date:</strong> <?= date('F j, Y', strtotime($invoice['Date'])) ?></p>
                    <p><strong>Customer:</strong> <?= htmlspecialchars($invoice['customer_name'] ?? 'Walk-in Customer') ?></p>
                </div>
            </div>
        </div>
        
        <table class="table table-bordered invoice-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Medicine Name</th>
                    <th>Quantity</th>
                    <th>Price</th>
                    <th>Discount</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $counter = 1;
                $grand_total = 0;
                while ($item = mysqli_fetch_assoc($items_result)): 
                    $grand_total += $item['total'];
                ?>
                <tr>
                    <td><?= $counter++ ?></td>
                    <td><?= htmlspecialchars($item['medicine_name']) ?></td>
                    <td><?= $item['quantity'] ?></td>
                    <td><?= number_format($item['price'], 2) ?> Birr</td>
                    <td><?= number_format($item['discount'], 2) ?> Birr</td>
                    <td><?= number_format($item['total'], 2) ?> Birr</td>
                </tr>
                <?php endwhile; ?>
                <tr class="total-row">
                    <td colspan="5" class="text-right"><strong>Grand Total:</strong></td>
                    <td><?= number_format($grand_total, 2) ?> Birr</td>
                </tr>
            </tbody>
        </table>
        
        <div class="row mt-4">
            <div class="col-md-6">
                <h5>Payment Information</h5>
                <p><strong>Payment Method:</strong> <?= htmlspecialchars($invoice['payment_method'] ?? 'Cash') ?></p>
                <p><strong>Amount Paid:</strong> <?= number_format($invoice['total_amount'], 2) ?> Birr</p>
            </div>
            <div class="col-md-6 text-right">
                <a href="download.php?invoice_number=<?= $invoice_number ?>" class="btn btn-success">
                    <i class="fas fa-download"></i> Download PDF
                </a>
                <button onclick="window.print()" class="btn btn-primary">
                    <i class="fas fa-print"></i> Print Invoice
                </button>
            </div>
        </div>
        
        <div class="mt-4 text-center">
            <p>Thank you for your purchase!</p>
            <p class="text-muted">For any inquiries, please contact our customer service</p>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>