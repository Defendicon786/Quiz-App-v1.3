<?php
include "database.php";

// First check if test quiz already exists
$sql = "SELECT quizid FROM quizconfig WHERE quizname = 'Test Quiz'";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    echo "Test quiz already exists!";
} else {
    // Add a test quiz that is currently active
    $sql = "INSERT INTO quizconfig (
        quiznumber,
        quizname,
        subject_id,
        class_id,
        chapter_ids,
        starttime,
        endtime,
        duration,
        maxmarks,
        typea,
        typeamarks,
        typeb,
        typebmarks,
        typec,
        typecmarks,
        typed,
        typedmarks,
        total_questions,
        is_random,
        attempts
    ) VALUES (
        1,
        'Test Quiz',
        1,
        1,
        '1,2',
        NOW(),
        DATE_ADD(NOW(), INTERVAL 1 DAY),
        60,
        100,
        2,
        20,
        2,
        20,
        2,
        20,
        2,
        20,
        8,
        1,
        3
    )";

    if($conn->query($sql)) {
        echo "Test quiz added successfully!<br>";
        
        // Add some test questions if they don't exist
        $tables = array(
            'mcqdb' => "INSERT INTO mcqdb (question, optiona, optionb, optionc, optiond, answer, chapter_id) 
                       VALUES ('What is 2+2?', '3', '4', '5', '6', 'B', 1)",
            'numericaldb' => "INSERT INTO numericaldb (question, answer, chapter_id) 
                            VALUES ('What is 5x5?', 25, 1)",
            'dropdown' => "INSERT INTO dropdown (question, options, answer, chapter_id) 
                         VALUES ('Select the largest number', '1,2,3,4', '4', 1)",
            'fillintheblanks' => "INSERT INTO fillintheblanks (question, answer, chapter_id) 
                                 VALUES ('The capital of France is _____.', 'Paris', 1)"
        );
        
        foreach ($tables as $table => $query) {
            // Check if questions exist
            $check = $conn->query("SELECT id FROM $table LIMIT 1");
            if ($check && $check->num_rows == 0) {
                if($conn->query($query)) {
                    echo "Added test question to $table<br>";
                } else {
                    echo "Error adding question to $table: " . $conn->error . "<br>";
                }
            }
        }
    } else {
        echo "Error adding test quiz: " . $conn->error;
    }
}
?> 