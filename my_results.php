<?php
session_start();
include "database.php";

// Ensure student is logged in
if (!isset($_SESSION["studentloggedin"]) || $_SESSION["studentloggedin"] !== true) {
    header("location: studentlogin.php");
    exit;
}

$rollnumber = $_SESSION["rollnumber"];

// Fetch student's quiz results
$sql = "SELECT
            qc.quizid,
            qc.quizname,
            qc.quiznumber,
            c.class_name,
            s.subject_name,
            r.attempt,
            r.mcqmarks + r.numericalmarks + r.dropdownmarks + r.fillmarks + r.shortmarks + r.essaymarks AS total_marks,
            qc.maxmarks,
            qr.starttime,
            qr.endtime,
            TIMESTAMPDIFF(MINUTE, qr.starttime, qr.endtime) AS time_taken
        FROM result r
        JOIN quizconfig qc ON r.quizid = qc.quizid
        JOIN quizrecord qr ON r.quizid = qr.quizid AND r.rollnumber = qr.rollnumber AND r.attempt = qr.attempt
        LEFT JOIN classes c ON qc.class_id = c.class_id
        LEFT JOIN subjects s ON qc.subject_id = s.subject_id
        WHERE r.rollnumber = ?
        ORDER BY qr.starttime DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $rollnumber);
$stmt->execute();
$results = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <link rel="apple-touch-icon" sizes="76x76" href="./assets/img/apple-icon.png">
    <link rel="icon" type="image/png" href="./assets/img/favicon.png">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
    <title>My Quiz Results</title>
    <meta content='width=device-width, initial-scale=1.0, shrink-to-fit=no' name='viewport' />
    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700|Roboto+Slab:400,700|Material+Icons" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="./assets/css/material-kit.css?v=2.0.4" rel="stylesheet" />
    <link href="./assets/css/modern.css" rel="stylesheet" />
    <link href="./assets/css/navbar.css" rel="stylesheet" />
    <link href="./assets/css/portal.css" rel="stylesheet" />
    <link href="./assets/css/manage.css" rel="stylesheet" />
    <link href="./assets/css/sidebar.css" rel="stylesheet" />
    <link id="dark-mode-style" rel="stylesheet" href="./assets/css/dark-mode.css" />
    <style>
        .result-row { transition: all 0.3s ease; }
        .result-row:hover { background-color: #f8f9fa; transform: translateY(-2px); box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .score-badge { font-size: 1em; padding: 5px 10px; }
        .footer-text { text-align: center; margin-top: 40px; }
    </style>
</head>
<body class="dark-mode">
<div class="layout">
  <?php include './includes/sidebar.php'; ?>
  <div class="main">
    <?php include './includes/header.php'; ?>
    <main class="content">
      <div class="wrapper">
        <div class="main main-raised" style="margin-top:0;">
          <div class="container">
            <div class="section" style="padding-top:0;">
              <div class="card">
                <div class="card-header card-header-primary d-flex justify-content-between align-items-center">
                  <div>
                    <h4 class="card-title mb-0">My Quiz Results</h4>
                    <p class="card-category">View all your quiz attempts and scores</p>
                  </div>
                  <a href="results_export.php?students=<?php echo $rollnumber; ?>" class="btn btn-success btn-sm" target="_blank">
                    <i class="material-icons">picture_as_pdf</i> Download All Results
                  </a>
                </div>
                <div class="card-body">
                  <?php if ($results->num_rows > 0): ?>
                    <div class="table-responsive">
                      <table class="table">
                        <thead class="text-primary">
                          <tr>
                            <th>Quiz Name</th>
                            <th>Class</th>
                            <th>Subject</th>
                            <th>Attempt</th>
                            <th>Score</th>
                            <th>Time Taken</th>
                            <th>Date</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php while ($row = $results->fetch_assoc()):
                                $percentage = ($row['total_marks'] / $row['maxmarks']) * 100;
                                $badge_class = 'badge-';
                                if ($percentage >= 80) $badge_class .= 'success';
                                else if ($percentage >= 60) $badge_class .= 'info';
                                else if ($percentage >= 40) $badge_class .= 'warning';
                                else $badge_class .= 'danger';
                          ?>
                          <tr class="result-row">
                            <td>
                              <strong><?php echo htmlspecialchars($row['quizname']); ?></strong><br>
                              <small class="text-muted">Quiz #<?php echo htmlspecialchars($row['quiznumber']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($row['class_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['subject_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['attempt']); ?></td>
                            <td>
                              <span class="badge <?php echo $badge_class; ?> score-badge">
                                <?php echo $row['total_marks']; ?>/<?php echo $row['maxmarks']; ?> (<?php echo round($percentage, 1); ?>%)
                              </span>
                            </td>
                            <td><?php echo htmlspecialchars($row['time_taken']); ?> mins</td>
                            <td>
                              <?php $start_date = new DateTime($row['starttime']); echo $start_date->format('d M Y, h:i A'); ?>
                            </td>
                          </tr>
                          <?php endwhile; ?>
                        </tbody>
                      </table>
                    </div>
                  <?php else: ?>
                    <div class="alert alert-info">
                      <i class="material-icons">info</i> You haven't attempted any quizzes yet.
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="footer-text">
        <p>Narowal Public School and College</p>
        <p>Developed and Maintained by Sir Hassan Tariq</p>
      </div>
    </main>
  </div>
</div>
<!--   Core JS Files   -->
<script src="./assets/js/core/jquery.min.js" type="text/javascript"></script>
<script src="./assets/js/core/popper.min.js" type="text/javascript"></script>
<script src="./assets/js/core/bootstrap-material-design.min.js" type="text/javascript"></script>
<script src="./assets/js/plugins/moment.min.js"></script>
<script src="./assets/js/plugins/bootstrap-datetimepicker.js" type="text/javascript"></script>
<script src="./assets/js/plugins/nouislider.min.js" type="text/javascript"></script>
<script src="./assets/js/material-kit.js?v=2.0.4" type="text/javascript"></script>
<script src="./assets/js/dark-mode.js"></script>
<script src="./assets/js/sidebar.js"></script>
</body>
</html>

