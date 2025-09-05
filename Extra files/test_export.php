<?php
// Test file to directly use QuizExporter

// Define constant to prevent direct access to QuizExporter.php
define('QUIZ_PORTAL', true);

// Include necessary files
include "database.php"; // Include database connection
include "export/QuizExporter.php"; // Include exporter class

// Create dummy quiz data structure
$quiz = [
    'quizname' => 'Test Quiz',
    'quiznumber' => '999',
    'class_name' => 'Test Class',
    'subject_name' => 'Test Subject',
    'section' => 'A',
    'duration' => 30,
    'maxmarks' => 50,
    'mcq' => 5,
    'numerical' => 0,
    'dropdown' => 0,
    'fill' => 0,
    'short' => 0,
    'essay' => 0
];

// Create dummy questions 
$questions = [
    [
        'questiontext' => 'Sample Question 1',
        'options' => json_encode(['Option A', 'Option B', 'Option C', 'Option D']),
        'answer' => 'A',
        'questiontype' => 'mcq',
        'marks' => 5
    ]
];

// Call exporter directly
$html = QuizExporter::exportToPDF($quiz, $questions);

// Output the generated HTML
echo $html;
?> 