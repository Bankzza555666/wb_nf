<?php
require_once '../controller/config.php';

// Check if column exists first
$check = $conn->query("SHOW COLUMNS FROM ssh_products LIKE 'features'");
if ($check->num_rows == 0) {
    $sql = "ALTER TABLE ssh_products ADD COLUMN features TEXT DEFAULT NULL COMMENT 'JSON Features List'";
    if ($conn->query($sql) === TRUE) {
        echo "Column 'features' added successfully";
    } else {
        echo "Error adding column: " . $conn->error;
    }
} else {
    echo "Column 'features' already exists";
}
?>