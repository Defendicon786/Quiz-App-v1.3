<?php
include 'database.php';

echo "Checking database tables:\n";

// Check if chapters table exists and has data
$result = $conn->query("SHOW TABLES LIKE 'chapters'");
if($result->num_rows > 0) {
    echo "chapters table exists\n";
    
    // Check chapters data
    $result = $conn->query("SELECT * FROM chapters LIMIT 5");
    echo "Number of chapters: " . $result->num_rows . "\n";
    while($row = $result->fetch_assoc()) {
        echo "Chapter ID: " . $row['chapter_id'] . ", Name: " . $row['chapter_name'] . "\n";
    }
} else {
    echo "chapters table does not exist\n";
}

// Check classes table
$result = $conn->query("SHOW TABLES LIKE 'classes'");
if($result->num_rows > 0) {
    echo "\nclasses table exists\n";
    
    // Check classes data
    $result = $conn->query("SELECT * FROM classes LIMIT 5");
    echo "Number of classes: " . $result->num_rows . "\n";
    while($row = $result->fetch_assoc()) {
        echo "Class ID: " . $row['class_id'] . ", Name: " . $row['class_name'] . "\n";
    }
} else {
    echo "classes table does not exist\n";
}

// Check subjects table
$result = $conn->query("SHOW TABLES LIKE 'subjects'");
if($result->num_rows > 0) {
    echo "\nsubjects table exists\n";
    
    // Check subjects data
    $result = $conn->query("SELECT * FROM subjects LIMIT 5");
    echo "Number of subjects: " . $result->num_rows . "\n";
    while($row = $result->fetch_assoc()) {
        echo "Subject ID: " . $row['subject_id'] . ", Name: " . $row['subject_name'] . "\n";
    }
} else {
    echo "subjects table does not exist\n";
}

$conn->close();
?> 