<?php
include "database.php";

// Add options column to fillintheblanks table if it doesn't exist
$sql = "SHOW COLUMNS FROM fillintheblanks LIKE 'options'";
$result = $conn->query($sql);

if ($result->num_rows == 0) {
    $sql = "ALTER TABLE fillintheblanks ADD COLUMN options TEXT AFTER question";
    if ($conn->query($sql) === TRUE) {
        echo "Options column added successfully to fillintheblanks table";
    } else {
        echo "Error adding options column: " . $conn->error;
    }
} else {
    echo "Options column already exists in fillintheblanks table";
}

$conn->close();
?> 