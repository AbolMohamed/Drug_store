<?php
// Start the session to access user data
if (!session_id()) session_start();

// Include your database connection file
include '../dbcon.php';

// Ensure a user is logged in before proceeding
if (!isset($_SESSION['user_id'])) {
    // Stop execution if the user is not authenticated
    die("Authentication required. Please log in.");
}

// Get the current user's ID
$user_id = $_SESSION['user_id'];

// Sanitize and retrieve the product ID and action from the URL
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

$message = ''; // To provide feedback to the user

// Proceed only if we have a valid product ID and a valid action ('pin' or 'unpin')
if ($product_id > 0 && in_array($action, ['pin', 'unpin'])) {
    
    if ($action === 'pin') {
        // Pin the product by inserting a record into the 'pinned_products' table.
        // We use 'INSERT IGNORE' to prevent an error if the item is already pinned.
        $stmt = $con->prepare("INSERT IGNORE INTO pinned_products (user_id, product_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $user_id, $product_id);
        if ($stmt->execute()) {
            $message = "Product pinned successfully!";
        } else {
            $message = "Error: Could not pin the product.";
        }
        $stmt->close();

    } elseif ($action === 'unpin') {
        // Unpin the product by deleting the record from the 'pinned_products' table.
        $stmt = $con->prepare("DELETE FROM pinned_products WHERE user_id = ? AND product_id = ?");
        $stmt->bind_param("ii", $user_id, $product_id);
        if ($stmt->execute()) {
            $message = "Product unpinned successfully!";
        } else {
            $message = "Error: Could not unpin the product.";
        }
        $stmt->close();
    }
} else {
    // Set an error message for invalid requests
    $message = "Invalid product or action specified.";
}

// Close the database connection
$con->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Updating Pin Status</title>
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; 
            text-align: center; 
            padding: 40px 20px; 
            background-color: #f8f9fa;
        }
        p {
            font-size: 1.1rem;
            color: #333;
        }
    </style>
</head>
<body>
    <p><?php echo htmlspecialchars($message); ?></p>
    <p>This window will close shortly.</p>

    <script>
        // This script runs inside the Facebox pop-up (iframe).
        // It sends a message back to the main selling page.
        if (window.parent) {
            window.parent.postMessage('pinUpdated', '*');
        }

        // Close the Facebox pop-up automatically after a brief delay.
        // This gives the user time to read the confirmation message.
        setTimeout(function() {
            if (window.parent && window.parent.jQuery && window.parent.jQuery.facebox) {
                window.parent.jQuery.facebox.close();
            }
        }, 1500); // 1.5-second delay
    </script>
</body>
</html>