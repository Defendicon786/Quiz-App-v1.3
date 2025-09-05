<?php
// Debug file to check paths and content of QuizExporter.php

echo "<h1>Debugging QuizExporter.php</h1>";

// Check file paths
$exporter_path = realpath("export/QuizExporter.php");
echo "<p>QuizExporter.php full path: " . $exporter_path . "</p>";

// Check if file exists
if (!file_exists($exporter_path)) {
    echo "<p style='color:red'>ERROR: File not found!</p>";
} else {
    echo "<p style='color:green'>File exists!</p>";
    
    // Check file modification time
    echo "<p>Last modified: " . date("Y-m-d H:i:s", filemtime($exporter_path)) . "</p>";
    
    // Check file size
    echo "<p>File size: " . filesize($exporter_path) . " bytes</p>";
    
    // Print the actual file content
    echo "<h2>File Content:</h2>";
    echo "<pre>";
    echo htmlspecialchars(file_get_contents($exporter_path));
    echo "</pre>";
    
    // Search for specific strings in the file
    $content = file_get_contents($exporter_path);
    
    echo "<h2>String Search Results:</h2>";
    echo "<p>Contains 'Narowal Public School and College': " . 
         (strpos($content, 'Narowal Public School and College') !== false ? 'YES' : 'NO') . "</p>";
    
    echo "<p>Contains 'QUIZ PORTAL': " . 
         (strpos($content, 'QUIZ PORTAL') !== false ? 'YES' : 'NO') . "</p>";
    
    echo "<p>Contains 'Quiz Portal': " . 
         (strpos($content, 'Quiz Portal') !== false ? 'YES' : 'NO') . "</p>";
}

// List all PHP files in the export directory
echo "<h2>All files in export directory:</h2>";
$files = glob("export/*");
echo "<ul>";
foreach ($files as $file) {
    echo "<li>" . $file . " (size: " . filesize($file) . " bytes, modified: " . 
         date("Y-m-d H:i:s", filemtime($file)) . ")</li>";
}
echo "</ul>";

?> 