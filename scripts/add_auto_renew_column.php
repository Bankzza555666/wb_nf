<?php
// scripts/add_auto_renew_column.php
require_once __DIR__ . '/../controller/config.php';

echo "Checking database schema...\n";

// Function to check and add column
function checkAndAddColumn($conn, $table, $column, $definition) {
    echo "Checking table '$table' for column '$column'...\n";
    $result = $conn->query("SHOW COLUMNS FROM $table LIKE '$column'");
    if ($result && $result->num_rows > 0) {
        echo "✅ Column '$column' already exists in '$table'.\n";
    } else {
        echo "⚠️ Column '$column' missing. Adding...\n";
        $sql = "ALTER TABLE $table ADD COLUMN $column $definition";
        if ($conn->query($sql)) {
            echo "✅ Successfully added column '$column' to '$table'.\n";
        } else {
            echo "❌ Error adding column: " . $conn->error . "\n";
        }
    }
}

// Add to user_rentals (for VPN)
checkAndAddColumn($conn, 'user_rentals', 'auto_renew', "TINYINT(1) NOT NULL DEFAULT 0 AFTER status");

// Add to ssh_rentals (just in case)
checkAndAddColumn($conn, 'ssh_rentals', 'auto_renew', "TINYINT(1) NOT NULL DEFAULT 0 AFTER status");

echo "Database update completed.\n";
?>
