<?php
session_start();
// Allow both instructor and student roles
if (!isset($_SESSION["instructorloggedin"]) && !isset($_SESSION["studentloggedin"])) {
    header("location: studentlogin.php");
    exit;
}

include "database.php";

$quiz_id = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0;

// Normalize student input (supports students[]= and comma-separated list)
$students = [];
if (isset($_GET['students'])) {
    if (is_array($_GET['students'])) {
        $students = array_map('intval', $_GET['students']);
    } else {
        $students = array_filter(array_map('intval', explode(',', $_GET['students'])));
    }
} elseif (isset($_GET['student'])) {
    $students = [intval($_GET['student'])];
}

if ($quiz_id === 0 && empty($students)) {
    echo "No quiz or students specified.";
    exit;
}

ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Quiz Results Export</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #f5f5f5; }
        .student-section { margin-top: 40px; }
        .print-btn { padding: 6px 12px; background-color: #4CAF50; color: #fff; border: none; cursor: pointer; }
        .header { display: flex; justify-content: space-between; align-items: center; }
    </style>
</head>
<body>
<div class="header">
    <h2>Quiz Results Export</h2>
    <button class="print-btn" onclick="window.print();">Print / Save PDF</button>
</div>
<?php
if ($quiz_id > 0) {
    // Quiz-specific export
    $quiz_sql = "SELECT qc.*, c.class_name, s.subject_name
                 FROM quizconfig qc
                 LEFT JOIN classes c ON qc.class_id = c.class_id
                 LEFT JOIN subjects s ON qc.subject_id = s.subject_id
                 WHERE qc.quiznumber = ?";
    $stmt = $conn->prepare($quiz_sql);
    $stmt->bind_param("i", $quiz_id);
    $stmt->execute();
    $quiz_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$quiz_info) {
        echo '<p>Quiz not found.</p>';
    } else {
        echo '<h3>Quiz #' . htmlspecialchars($quiz_info['quiznumber']) . ' - ' . htmlspecialchars($quiz_info['quizname']) . '</h3>';
        echo '<p>Class: ' . htmlspecialchars($quiz_info['class_name'] ?? 'N/A') . ' | Subject: ' . htmlspecialchars($quiz_info['subject_name'] ?? 'N/A') . '</p>';

        $results_sql = "SELECT
                            s.name as student_name,
                            c.class_name,
                            IFNULL(cs.section_name, s.section) as section_name,
                            s.rollnumber,
                            r.attempt,
                            r.mcqmarks,
                            r.numericalmarks,
                            r.dropdownmarks,
                            r.fillmarks,
                            r.shortmarks,
                            r.essaymarks,
                            r.mcqmarks + r.numericalmarks + r.dropdownmarks + r.fillmarks + r.shortmarks + r.essaymarks as total_marks
                         FROM result r
                         JOIN studentinfo s ON r.rollnumber = s.rollnumber
                         LEFT JOIN class_sections cs ON s.section_id = cs.id
                         LEFT JOIN classes c ON cs.class_id = c.class_id
                         WHERE r.quizid = ?";
        $types = "i";
        $params = [$quiz_info['quizid']];
        if (!empty($students)) {
            $placeholders = implode(',', array_fill(0, count($students), '?'));
            $results_sql .= " AND r.rollnumber IN (" . $placeholders . ")";
            $types .= str_repeat('i', count($students));
            $params = array_merge($params, $students);
        }
        $results_sql .= " ORDER BY r.attempt ASC, total_marks DESC";
        $stmt = $conn->prepare($results_sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $results = $stmt->get_result();

        if ($results->num_rows > 0) {
            echo '<table><thead><tr><th>Class</th><th>Section</th><th>Name</th><th>Roll No</th><th>Attempt</th><th>MCQ</th><th>Numerical</th><th>Dropdown</th><th>Fill</th><th>Short</th><th>Essay</th><th>Total</th></tr></thead><tbody>';
            while ($row = $results->fetch_assoc()) {
                echo '<tr>' .
                     '<td>' . htmlspecialchars($row['class_name'] ?? 'N/A') . '</td>' .
                     '<td>' . htmlspecialchars($row['section_name'] ?? 'N/A') . '</td>' .
                     '<td>' . htmlspecialchars($row['student_name']) . '</td>' .
                     '<td>' . htmlspecialchars($row['rollnumber']) . '</td>' .
                     '<td>' . htmlspecialchars($row['attempt']) . '</td>' .
                     '<td>' . $row['mcqmarks'] . '</td>' .
                     '<td>' . $row['numericalmarks'] . '</td>' .
                     '<td>' . $row['dropdownmarks'] . '</td>' .
                     '<td>' . $row['fillmarks'] . '</td>' .
                     '<td>' . $row['shortmarks'] . '</td>' .
                     '<td>' . $row['essaymarks'] . '</td>' .
                     '<td>' . $row['total_marks'] . '/' . $quiz_info['maxmarks'] . '</td>' .
                     '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>No results found.</p>';
        }
        $stmt->close();
    }
} else {
    // Student-specific export across all quizzes
    foreach ($students as $roll) {
        $student_sql = "SELECT name, department FROM studentinfo WHERE rollnumber = ?";
        $stmt = $conn->prepare($student_sql);
        $stmt->bind_param("i", $roll);
        $stmt->execute();
        $info = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        echo '<div class="student-section">';
        echo '<h3>Student: ' . htmlspecialchars($info['name'] ?? 'Unknown') . ' (' . htmlspecialchars($roll) . ')</h3>';
        if ($info && !empty($info['department'])) {
            echo '<p>Department: ' . htmlspecialchars($info['department']) . '</p>';
        }

        $sql = "SELECT
                    qc.quizname,
                    qc.quiznumber,
                    r.attempt,
                    r.mcqmarks + r.numericalmarks + r.dropdownmarks + r.fillmarks + r.shortmarks + r.essaymarks as total_marks,
                    qc.maxmarks,
                    qr.starttime,
                    qr.endtime,
                    TIMESTAMPDIFF(MINUTE, qr.starttime, qr.endtime) as time_taken
                 FROM result r
                 JOIN quizconfig qc ON r.quizid = qc.quizid
                 JOIN quizrecord qr ON r.quizid = qr.quizid AND r.rollnumber = qr.rollnumber AND r.attempt = qr.attempt
                 WHERE r.rollnumber = ?
                 ORDER BY qr.starttime DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $roll);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            echo '<table><thead><tr><th>Quiz Name</th><th>Quiz #</th><th>Attempt</th><th>Score</th><th>Time Taken</th><th>Date</th></tr></thead><tbody>';
            while ($row = $res->fetch_assoc()) {
                $score = $row['total_marks'] . '/' . $row['maxmarks'];
                $date = date('d M Y, h:i A', strtotime($row['starttime']));
                echo '<tr>' .
                     '<td>' . htmlspecialchars($row['quizname']) . '</td>' .
                     '<td>' . htmlspecialchars($row['quiznumber']) . '</td>' .
                     '<td>' . htmlspecialchars($row['attempt']) . '</td>' .
                     '<td>' . $score . '</td>' .
                     '<td>' . htmlspecialchars($row['time_taken']) . ' mins</td>' .
                     '<td>' . $date . '</td>' .
                     '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>No quiz attempts found.</p>';
        }
        $stmt->close();
        echo '</div>';
    }
}
$conn->close();
?>
</body>
</html>
<?php
echo ob_get_clean();
?>
