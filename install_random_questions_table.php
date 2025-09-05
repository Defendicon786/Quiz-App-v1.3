<?php
// Install script for random_quiz_questions table
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
include "database.php";

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create table SQL
$sql = "CREATE TABLE IF NOT EXISTS random_quiz_questions (
  quizid int(11) NOT NULL,
  qtype varchar(20) NOT NULL,
  qid int(11) NOT NULL,
  serialnumber int(11) NOT NULL,
  PRIMARY KEY (quizid, qtype, qid)
)";

echo "<h2>Creating random_quiz_questions table</h2>";
echo "SQL: " . htmlspecialchars($sql) . "<br><br>";

// Execute query
if ($conn->query($sql) === TRUE) {
    echo "<div style='color:green; padding:10px; border:1px solid green;'>Table random_quiz_questions created successfully</div>";
    
    // Now add foreign key
    $sql_fk = "ALTER TABLE random_quiz_questions
              ADD CONSTRAINT fk_random_quiz_quizid
              FOREIGN KEY (quizid) 
              REFERENCES quizconfig(quizid)
              ON DELETE CASCADE ON UPDATE CASCADE";
    
    echo "<h3>Adding Foreign Key</h3>";
    echo "SQL: " . htmlspecialchars($sql_fk) . "<br><br>";
    
    if ($conn->query($sql_fk) === TRUE) {
        echo "<div style='color:green; padding:10px; border:1px solid green;'>Foreign key added successfully</div>";
    } else {
        echo "<div style='color:red; padding:10px; border:1px solid red;'>Error adding foreign key: " . $conn->error . "</div>";
        
        // Debug info for foreign key error
        echo "<h3>Debug Information for Foreign Key Error</h3>";
        $tables_sql = "SHOW TABLES";
        $result = $conn->query($tables_sql);
        
        if ($result) {
            echo "<h4>Tables in database:</h4>";
            echo "<ul>";
            while ($row = $result->fetch_row()) {
                echo "<li>" . htmlspecialchars($row[0]) . "</li>";
            }
            echo "</ul>";
        }
        
        // Check quizconfig table
        $check_quizconfig = "SHOW CREATE TABLE quizconfig";
        $result = $conn->query($check_quizconfig);
        
        if ($result && $row = $result->fetch_row()) {
            echo "<h4>quizconfig table structure:</h4>";
            echo "<pre>" . htmlspecialchars($row[1]) . "</pre>";
        }
    }
} else {
    echo "<div style='color:red; padding:10px; border:1px solid red;'>Error creating table: " . $conn->error . "</div>";
    
    // Debug info for create table error
    echo "<h3>Debug Information for Create Table Error</h3>";
    $tables_sql = "SHOW TABLES";
    $result = $conn->query($tables_sql);
    
    if ($result) {
        echo "<h4>Tables in database:</h4>";
        echo "<ul>";
        while ($row = $result->fetch_row()) {
            echo "<li>" . htmlspecialchars($row[0]) . "</li>";
        }
        echo "</ul>";
    }
    
    // Check if we have permission to create tables
    $check_privileges = "SHOW GRANTS FOR CURRENT_USER()";
    $result = $conn->query($check_privileges);
    
    if ($result) {
        echo "<h4>User privileges:</h4>";
        echo "<ul>";
        while ($row = $result->fetch_row()) {
            echo "<li>" . htmlspecialchars($row[0]) . "</li>";
        }
        echo "</ul>";
    }
}

$conn->close();
echo "<a href='quizhome.php'>Go back to Quiz Home</a>";
?> 