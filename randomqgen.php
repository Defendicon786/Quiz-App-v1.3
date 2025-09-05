<?php
  session_start();
  if(!isset($_SESSION["studentloggedin"]) || $_SESSION["studentloggedin"] !== true){
      header("location: studentlogin.php");
      exit;
  }
  $rollnumber = $_SESSION["rollnumber"];
?>
<?php
include "database.php";

// Get the latest quiz configuration
$query  = "SELECT * FROM quizconfig ORDER BY quiznumber DESC LIMIT 1";
$result = $conn->query($query);
$row    = $result->fetch_assoc();

$quizid    = (int)$row["quizid"];
$quiznumber = (int)$row["quiznumber"];
$typea      = (int)$row["typea"];
$typeb      = (int)$row["typeb"];
$typec      = (int)$row["typec"];
$typed      = (int)$row["typed"];
$typee      = (int)$row["typee"];
$typef      = (int)$row["typef"];

$attempt = 1; // Default attempt when using this script

// Clear any existing questions for this quiz attempt to avoid duplicates
$cleanup = $conn->prepare(
    "DELETE FROM response WHERE quizid = ? AND rollnumber = ? AND attempt = ?"
);
if ($cleanup) {
    $cleanup->bind_param("iii", $quizid, $rollnumber, $attempt);
    $cleanup->execute();
    $cleanup->close();
}

selectrand($conn, $typea, 'a', $rollnumber, $quizid, $attempt);
selectrand($conn, $typeb, 'b', $rollnumber, $quizid, $attempt);
selectrand($conn, $typec, 'c', $rollnumber, $quizid, $attempt);
selectrand($conn, $typed, 'd', $rollnumber, $quizid, $attempt);
selectrand($conn, $typee, 'e', $rollnumber, $quizid, $attempt);
selectrand($conn, $typef, 'f', $rollnumber, $quizid, $attempt);

$_SESSION["quizset"] = true;
header("location: quizpage.php");
exit;

function selectrand($conn1, $count, $type, $rollno, $quizid, $attempt) {
    static $serialnumber = 1;
    // Track selected questions to avoid duplicates across calls
    static $selected = [];

    if ($conn1->connect_error) {
        die("Connection failed: " . $conn1->connect_error);
    }

    switch ($type) {
        case 'a':
            $table = 'mcqdb';
            break;
        case 'b':
            $table = 'numericaldb';
            break;
        case 'c':
            $table = 'dropdown';
            break;
        case 'd':
            $table = 'fillintheblanks';
            break;
        case 'e':
            $table = 'shortanswer';
            break;
        case 'f':
            $table = 'essay';
            break;
        default:
            return;
    }

    $exclude = '';
    if (isset($selected[$table]) && count($selected[$table]) > 0) {
        $exclude_ids = implode(',', array_map('intval', $selected[$table]));
        $exclude = " WHERE id NOT IN ($exclude_ids)";
    }

    // Fetch unique questions, excluding any already selected
    $sql = "SELECT id FROM $table" . $exclude . " ORDER BY RAND() LIMIT " . intval($count);
    $result = $conn1->query($sql);

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $qid = (int)$row['id'];

            $stmt = $conn1->prepare(
                "INSERT INTO response (quizid, rollnumber, attempt, qtype, qid, serialnumber, response) VALUES (?, ?, ?, ?, ?, ?, '')"
            );
            if ($stmt) {
                $stmt->bind_param(
                    "iiisii",
                    $quizid,
                    $rollno,
                    $attempt,
                    $type,
                    $qid,
                    $serialnumber
                );
                $stmt->execute();
                $stmt->close();
            }

            // remember this question so it isn't selected again
            $selected[$table][] = $qid;
            $serialnumber++;
        }
        mysqli_free_result($result);
    }
}
?>
