<?php
// Debug script to help diagnose quiz issues
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
include "database.php";

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h1>Quiz System Diagnostic Tool</h1>";

// Check if the random_quiz_questions table exists
$table_sql = "SHOW TABLES LIKE 'random_quiz_questions'";
$table_result = $conn->query($table_sql);

echo "<h2>Table Status</h2>";
echo "<ul>";
echo "<li>random_quiz_questions table exists: " . ($table_result->num_rows > 0 ? "Yes" : "No") . "</li>";

// If the table doesn't exist, attempt to create it
if ($table_result->num_rows == 0) {
    $create_sql = "CREATE TABLE IF NOT EXISTS random_quiz_questions (
      quizid int(11) NOT NULL,
      qtype varchar(20) NOT NULL,
      qid int(11) NOT NULL,
      serialnumber int(11) NOT NULL,
      PRIMARY KEY (quizid, qtype, qid)
    )";
    
    if ($conn->query($create_sql)) {
        echo "<li style='color: green;'>Successfully created random_quiz_questions table!</li>";
        
        // Now add foreign key
        $sql_fk = "ALTER TABLE random_quiz_questions
                  ADD CONSTRAINT fk_random_quiz_quizid
                  FOREIGN KEY (quizid) 
                  REFERENCES quizconfig(quizid)
                  ON DELETE CASCADE ON UPDATE CASCADE";
        
        if ($conn->query($sql_fk)) {
            echo "<li style='color: green;'>Successfully added foreign key to random_quiz_questions table!</li>";
        } else {
            echo "<li style='color: red;'>Failed to add foreign key: " . $conn->error . "</li>";
        }
    } else {
        echo "<li style='color: red;'>Failed to create random_quiz_questions table: " . $conn->error . "</li>";
    }
}

// Check quiz records for integrity issues
$integrity_sql = "SELECT qc.quizid, qc.quiznumber, qc.quizname
                  FROM quizconfig qc
                  LEFT JOIN (
                      SELECT quizid, COUNT(*) as count 
                      FROM result 
                      GROUP BY quizid
                  ) r ON qc.quizid = r.quizid
                  WHERE r.quizid IS NULL
                  ORDER BY qc.quizid DESC";
$integrity_result = $conn->query($integrity_sql);

echo "<li>Quizzes with no result records: " . $integrity_result->num_rows . "</li>";
echo "</ul>";

// Show recent quizzes
$recent_sql = "SELECT qc.quizid, qc.quiznumber, qc.quizname, qc.is_random, 
              COUNT(DISTINCT r.rollnumber) as num_students
              FROM quizconfig qc
              LEFT JOIN response r ON qc.quizid = r.quizid
              GROUP BY qc.quizid
              ORDER BY qc.quizid DESC
              LIMIT 10";
$recent_result = $conn->query($recent_sql);

echo "<h2>Recent Quizzes</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Quiz ID</th><th>Quiz Number</th><th>Quiz Name</th><th>Is Random</th><th>Students</th><th>Random Questions</th></tr>";

while ($row = $recent_result->fetch_assoc()) {
    // Check if this quiz has random questions
    $random_sql = "SELECT COUNT(*) as count FROM random_quiz_questions WHERE quizid = " . $row['quizid'];
    $random_result = $conn->query($random_sql);
    $random_count = 0;
    if ($random_result) {
        $random_row = $random_result->fetch_assoc();
        $random_count = $random_row['count'];
    }
    
    echo "<tr>";
    echo "<td>" . $row['quizid'] . "</td>";
    echo "<td>" . $row['quiznumber'] . "</td>";
    echo "<td>" . $row['quizname'] . "</td>";
    echo "<td>" . ($row['is_random'] ? "Yes" : "No") . "</td>";
    echo "<td>" . $row['num_students'] . "</td>";
    echo "<td>" . $random_count . "</td>";
    echo "</tr>";
}
echo "</table>";

// Check for response entries with rollnumber=-1
$old_random_sql = "SELECT COUNT(*) as count FROM response WHERE rollnumber = -1";
$old_random_result = $conn->query($old_random_sql);
$old_random_count = 0;
if ($old_random_result) {
    $old_random_row = $old_random_result->fetch_assoc();
    $old_random_count = $old_random_row['count'];
}

echo "<h2>System Status</h2>";
echo "<ul>";
echo "<li>Response entries with rollnumber=-1: " . $old_random_count . "</li>";

// Check direct quizconfig to result discrepancies
$direct_sql = "SELECT qc.quizid, qc.quiznumber, qc.quizname 
               FROM quizconfig qc 
               WHERE qc.quizid NOT IN (SELECT quizid FROM result)
               ORDER BY qc.quizid DESC";
$direct_result = $conn->query($direct_sql);

echo "<li>Quizzes with no result entries (direct check): " . $direct_result->num_rows . "</li>";
echo "</ul>";

if ($direct_result->num_rows > 0) {
    echo "<h3>Quizzes with No Results</h3>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Quiz ID</th><th>Quiz Number</th><th>Quiz Name</th></tr>";
    
    while ($row = $direct_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['quizid'] . "</td>";
        echo "<td>" . $row['quiznumber'] . "</td>";
        echo "<td>" . $row['quizname'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Show database tables
$tables_sql = "SHOW TABLES";
$tables_result = $conn->query($tables_sql);

echo "<h2>Database Tables</h2>";
echo "<ul>";
while ($row = $tables_result->fetch_row()) {
    echo "<li>" . $row[0] . "</li>";
}
echo "</ul>";

// Display database version
$version_sql = "SELECT VERSION() as version";
$version_result = $conn->query($version_sql);
$version_row = $version_result->fetch_assoc();

echo "<h2>Database Information</h2>";
echo "<ul>";
echo "<li>MySQL Version: " . $version_row['version'] . "</li>";
echo "</ul>";

$conn->close();
echo "<a href='quizhome.php'>Go back to Quiz Home</a>";
?> 