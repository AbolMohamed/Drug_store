<?php
$host = "localhost";
$user = "root";
$password = "";
$dbname = "pharmacy";
$con = mysqli_connect($host, $user, $password, $dbname);
if (!$con) {
    error_log('MySQL Connection Error: ' . mysqli_connect_error());
    // You can use die() for debugging, but do NOT use echo/print.
    die('Database connection failed.');
}
// No closing PHP tag and no whitespace after this line.
