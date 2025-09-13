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

    // Build query to avoid selecting duplicate questions already assigned
    $sql = "SELECT id FROM $table WHERE id NOT IN (
                SELECT qid FROM response WHERE quizid = ? AND rollnumber = ? AND attempt = ? AND qtype = ?
            )";

    // Ensure MCQ questions have all options present
    if ($type === 'a') {
        $sql .= " AND optiona IS NOT NULL AND optiona <> ''"
              . " AND optionb IS NOT NULL AND optionb <> ''"
              . " AND optionc IS NOT NULL AND optionc <> ''"
              . " AND optiond IS NOT NULL AND optiond <> ''";
    }

    $sql .= " ORDER BY RAND() LIMIT ?";

    $stmt = $conn1->prepare($sql);
    if (!$stmt) {
        return;
    }
    $stmt->bind_param("iiisi", $quizid, $rollno, $attempt, $type, $count);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $qid = (int)$row['id'];

            $insert = $conn1->prepare(
                "INSERT INTO response (quizid, rollnumber, attempt, qtype, qid, serialnumber, response) VALUES (?, ?, ?, ?, ?, ?, '')"
            );
            if ($insert) {
                $insert->bind_param(
                    "iiisii",
                    $quizid,
                    $rollno,
                    $attempt,
                    $type,
                    $qid,
                    $serialnumber
                );
                $insert->execute();
                $insert->close();
            }

            $serialnumber++;
        }
        $result->free();
    }
    $stmt->close();
}
?>
