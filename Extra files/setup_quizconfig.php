<?php
include 'database.php';

// Add missing columns to quizconfig table
$sql = "ALTER TABLE quizconfig 
        ADD COLUMN IF NOT EXISTS total_questions INT DEFAULT 10,
        ADD COLUMN IF NOT EXISTS is_random TINYINT(1) DEFAULT 0,
        ADD COLUMN IF NOT EXISTS chapter_ids TEXT DEFAULT NULL";

if ($conn->query($sql) === TRUE) {
    echo "Table quizconfig updated successfully";
} else {
    echo "Error updating table: " . $conn->error;
}

$conn->close();
?> 