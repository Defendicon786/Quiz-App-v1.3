<?php
// Script to handle patching and fixing of quiz data
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
include "database.php";

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h1>Quiz System Patch Tool</h1>";

// Function to check if a table exists
function tableExists($conn, $tableName) {
    $result = $conn->query("SHOW TABLES LIKE '$tableName'");
    return $result->num_rows > 0;
}

// Function to apply a set of SQL queries with error handling
function applySqlQueries($conn, $queries, $title) {
    echo "<h2>$title</h2>";
    echo "<ul>";
    
    foreach ($queries as $description => $sql) {
        echo "<li>$description... ";
        try {
            if ($conn->query($sql) === TRUE) {
                echo "<span style='color:green;'>Success</span>";
            } else {
                echo "<span style='color:red;'>Failed: " . $conn->error . "</span>";
            }
        } catch (Exception $e) {
            echo "<span style='color:red;'>Exception: " . $e->getMessage() . "</span>";
        }
        echo "</li>";
    }
    
    echo "</ul>";
}

// 1. Ensure the random_quiz_questions table exists
if (!tableExists($conn, 'random_quiz_questions')) {
    $create_table_sql = "CREATE TABLE IF NOT EXISTS random_quiz_questions (
        quizid int(11) NOT NULL,
        qtype varchar(20) NOT NULL,
        qid int(11) NOT NULL,
        serialnumber int(11) NOT NULL,
        PRIMARY KEY (quizid, qtype, qid)
    )";
    
    if ($conn->query($create_table_sql)) {
        echo "<div style='color:green;'>Successfully created random_quiz_questions table!</div>";
        
        // Add foreign key
        $sql_fk = "ALTER TABLE random_quiz_questions
                  ADD CONSTRAINT fk_random_quiz_quizid
                  FOREIGN KEY (quizid) 
                  REFERENCES quizconfig(quizid)
                  ON DELETE CASCADE ON UPDATE CASCADE";
        
        if ($conn->query($sql_fk)) {
            echo "<div style='color:green;'>Successfully added foreign key!</div>";
        } else {
            echo "<div style='color:red;'>Failed to add foreign key: " . $conn->error . "</div>";
        }
    } else {
        echo "<div style='color:red;'>Failed to create table: " . $conn->error . "</div>";
    }
}

// 2. Fix any quizzes that have rollnumber=-1 in response table and convert to random_quiz_questions
$check_invalid_sql = "SELECT COUNT(*) as count FROM response WHERE rollnumber = -1";
$invalid_result = $conn->query($check_invalid_sql);
$invalid_count = 0;

if ($invalid_result && $row = $invalid_result->fetch_assoc()) {
    $invalid_count = $row['count'];
}

if ($invalid_count > 0) {
    echo "<h2>Moving invalid response records to random_quiz_questions</h2>";
    
    // Move records
    $migrate_sql = "INSERT IGNORE INTO random_quiz_questions (quizid, qtype, qid, serialnumber)
                    SELECT quizid, qtype, qid, serialnumber 
                    FROM response 
                    WHERE rollnumber = -1";
    
    if ($conn->query($migrate_sql)) {
        echo "<div style='color:green;'>Successfully migrated " . $conn->affected_rows . " records to random_quiz_questions</div>";
        
        // Now delete the invalid records
        $delete_sql = "DELETE FROM response WHERE rollnumber = -1";
        if ($conn->query($delete_sql)) {
            echo "<div style='color:green;'>Successfully deleted " . $conn->affected_rows . " invalid records from response table</div>";
        } else {
            echo "<div style='color:red;'>Failed to delete invalid records: " . $conn->error . "</div>";
        }
    } else {
        echo "<div style='color:red;'>Failed to migrate records: " . $conn->error . "</div>";
    }
} else {
    echo "<div>No invalid response records found (this is good!)</div>";
}

// 3. Apply fixes to various files
echo "<h2>Fix Status</h2>";
echo "<div>The following fixes have been applied:</div>";
echo "<ul>";
echo "<li>install_random_questions_table.php - Ensures the random_quiz_questions table exists</li>";
echo "<li>quizconfig.php - Modified to store random questions in random_quiz_questions table</li>";
echo "<li>quizpage.php - Modified to fetch random questions from random_quiz_questions table</li>";
echo "<li>export.php - Updated to use random_quiz_questions table for PDF export</li>";
echo "<li>submit.php - Added checks to handle quizid inconsistencies</li>";
echo "</ul>";

// 4. Check for any quizzes that might have issues
$problem_sql = "SELECT qc.quizid, qc.quiznumber, qc.quizname, qc.is_random,
                (SELECT COUNT(*) FROM quizrecord qr WHERE qr.quizid = qc.quizid) as num_attempts,
                (SELECT COUNT(*) FROM result r WHERE r.quizid = qc.quizid) as num_results,
                (SELECT COUNT(*) FROM random_quiz_questions rqq WHERE rqq.quizid = qc.quizid) as num_random
                FROM quizconfig qc
                HAVING (qc.is_random = 1 AND num_random = 0) 
                   OR (num_attempts > 0 AND num_results = 0)
                ORDER BY qc.quizid DESC";
                
$problem_result = $conn->query($problem_sql);

if ($problem_result && $problem_result->num_rows > 0) {
    echo "<h2>Problematic Quizzes</h2>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Quiz ID</th><th>Quiz Number</th><th>Quiz Name</th><th>Is Random</th><th>Issues</th></tr>";
    
    while ($row = $problem_result->fetch_assoc()) {
        $issues = [];
        if ($row['is_random'] == 1 && $row['num_random'] == 0) {
            $issues[] = "Random quiz with no random questions";
        }
        if ($row['num_attempts'] > 0 && $row['num_results'] == 0) {
            $issues[] = "Has attempts but no results";
        }
        
        echo "<tr>";
        echo "<td>" . $row['quizid'] . "</td>";
        echo "<td>" . $row['quiznumber'] . "</td>";
        echo "<td>" . $row['quizname'] . "</td>";
        echo "<td>" . ($row['is_random'] ? "Yes" : "No") . "</td>";
        echo "<td>" . implode("<br>", $issues) . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<div style='color:green;'>No problematic quizzes found! System should be working correctly.</div>";
}

$conn->close();
echo "<br><br><a href='quizhome.php'>Go back to Quiz Home</a>";
?> 