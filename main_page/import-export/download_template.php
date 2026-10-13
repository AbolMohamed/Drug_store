<?php
session_start();
if (!isset($_SESSION['user_session'])) {
    header("location:../login.php");
    exit();
}

// Set headers for download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="drug_import_template.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// CSV headers with descriptions
$headers = [
    'Product Code*',           // Required - Unique identifier
    'Medicine Name*',          // Required
    'Category*',               // Required
    'Manufacturer*',           // Required
    'Quantity*',               // Required - Positive integer
    'Expiry Date*',            // Required - YYYY-MM-DD format
    'Shelf Location',          // Optional
    'Cost Price*',             // Required - Positive number
    'Selling Price*',          // Required - Positive number
    'Supplier ID',             // Optional - Must exist in suppliers table
    'Controlled Schedule'      // Optional - I, II, III, IV, or V
];

fputcsv($output, $headers);

// Add example rows
$examples = [
    [
        'MED001',
        'Paracetamol 500mg',
        'Analgesic',
        'Pharma Inc',
        '100',
        '2025-12-31',
        'Shelf A1',
        '5.00',
        '8.50',
        '1',
        ''
    ],
    [
        'MED002',
        'Amoxicillin 250mg',
        'Antibiotic',
        'MedCorp',
        '50',
        '2025-06-30',
        'Shelf B2',
        '12.00',
        '18.00',
        '2',
        ''
    ],
    [
        'CS001',
        'Morphine 10mg',
        'Analgesic',
        'Controlled Pharma',
        '25',
        '2025-09-15',
        'Locked Cabinet',
        '25.00',
        '40.00',
        '3',
        'II'
    ]
];

foreach ($examples as $example) {
    fputcsv($output, $example);
}

fclose($output);
exit;
?>