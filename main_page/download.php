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

// Require the FPDF library
require('../fpdf/fpdf.php');

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

// Create PDF
class PDF extends FPDF {
    function Header() {
        // Logo
        $this->Image('../images/logo.png', 10, 6, 30);
        // Title
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, 'HDS DRUG STORE', 0, 1, 'C');
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 5, '123 Pharmacy Street, Addis Ababa, Ethiopia', 0, 1, 'C');
        $this->Cell(0, 5, 'Phone: +251 912 345 678 | Email: info@hdsdrugstore.com', 0, 1, 'C');
        // Line break
        $this->Ln(10);
    }
    
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
    
    function InvoiceTitle($invoice_number, $date) {
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 10, 'INVOICE RECEIPT', 0, 1, 'L');
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 5, 'Invoice #: ' . $invoice_number, 0, 1, 'L');
        $this->Cell(0, 5, 'Date: ' . date('F j, Y', strtotime($date)), 0, 1, 'L');
        $this->Ln(10);
    }
    
    function ItemsTable($header, $data) {
        // Column widths
        $w = array(15, 75, 25, 25, 25, 25);
        
        // Header
        $this->SetFont('Arial', 'B', 10);
        for($i = 0; $i < count($header); $i++) {
            $this->Cell($w[$i], 7, $header[$i], 1, 0, 'C');
        }
        $this->Ln();
        
        // Data
        $this->SetFont('Arial', '', 10);
        $total = 0;
        foreach($data as $row) {
            $this->Cell($w[0], 6, $row['id'], 'LR', 0, 'C');
            $this->Cell($w[1], 6, $row['medicine_name'], 'LR');
            $this->Cell($w[2], 6, number_format($row['quantity'], 0), 'LR', 0, 'R');
            $this->Cell($w[3], 6, number_format($row['price'], 2), 'LR', 0, 'R');
            $this->Cell($w[4], 6, number_format($row['discount'], 2), 'LR', 0, 'R');
            $this->Cell($w[5], 6, number_format($row['total'], 2), 'LR', 0, 'R');
            $this->Ln();
            $total += $row['total'];
        }
        
        // Closing line
        $this->Cell(array_sum($w), 0, '', 'T');
        $this->Ln(10);
        
        // Total
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 10, 'Total Amount: ' . number_format($total, 2) . ' Birr', 0, 1, 'R');
    }
}

// Create PDF instance
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();

// Invoice title
$pdf->InvoiceTitle($invoice_number, $invoice['Date']);

// Items table header
$header = array('#', 'Medicine Name', 'Qty', 'Price', 'Discount', 'Total');

// Get items data
$items_data = array();
while ($item = mysqli_fetch_assoc($items_result)) {
    $items_data[] = $item;
}

// Add items table
$pdf->ItemsTable($header, $items_data);

// Add footer note
$pdf->SetFont('Arial', 'I', 10);
$pdf->Cell(0, 10, 'Thank you for your purchase!', 0, 1, 'C');
$pdf->Cell(0, 5, 'For any inquiries, please contact our customer service', 0, 1, 'C');

// Create backup directory if it doesn't exist
$backup_dir = 'C:/invoices/';
if (!file_exists($backup_dir)) {
    mkdir($backup_dir, 0777, true);
}

// Save to file and output to browser
$filename = 'Invoice_' . $invoice_number . '.pdf';
$filepath = $backup_dir . $filename;

$pdf->Output($filepath, 'F'); // Save to file
$pdf->Output($filename, 'D'); // Download to user

exit();
?>