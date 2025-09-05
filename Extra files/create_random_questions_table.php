<?php
// Create a new table for storing preselected random questions
include "database.php";

$sql = "CREATE TABLE IF NOT EXISTS random_quiz_questions (
  quizid int(11) NOT NULL,
  qtype varchar(20) NOT NULL,
  qid int(11) NOT NULL,
  serialnumber int(11) NOT NULL,
  PRIMARY KEY (quizid, qtype, qid),
  FOREIGN KEY (quizid) REFERENCES quizconfig(quizid) ON DELETE CASCADE ON UPDATE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "Table random_quiz_questions created successfully";
} else {
    echo "Error creating table: " . $conn->error;
}

$conn->close();
?> 