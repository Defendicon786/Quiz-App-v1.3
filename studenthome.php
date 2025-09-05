<?php
  session_start();
  if(!isset($_SESSION["studentloggedin"]) || $_SESSION["studentloggedin"] !== true){
      header("location: studentlogin.php");
      exit;
  }

  include("database.php");


  // If no class_id in session, map from department
  if (!isset($_SESSION['class_id']) && isset($_SESSION['department'])) {
    $department_to_class = array(
      '1st Year' => 4,
      '2nd Year' => 6,
      '9th' => 1,
      '10th' => 2
    );

    if (array_key_exists($_SESSION['department'], $department_to_class)) {
      $_SESSION['class_id'] = $department_to_class[$_SESSION['department']];
    }
  }

  // Get notifications for this student's class and section
  if (isset($_SESSION['class_id'])) {
    $class_id = $_SESSION['class_id'];
    $section_id = isset($_SESSION['section_id']) ? $_SESSION['section_id'] : null;

    if ($section_id) {
      $sql = "SELECT * FROM notifications
              WHERE is_active = 1
              AND class_id = ?
              AND (section_id IS NULL OR section_id = ?)
              ORDER BY created_at DESC";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param("ii", $class_id, $section_id);
    } else {
      $sql = "SELECT * FROM notifications
              WHERE is_active = 1
              AND class_id = ?
              AND section_id IS NULL
              ORDER BY created_at DESC";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param("i", $class_id);
    }

    $stmt->execute();
    $notifications = $stmt->get_result();
    $stmt->close();
  } else {
    $notifications = null;
  }

  // Fetch upcoming quiz for this student
  $upcoming_quiz = null;
  if (isset($_SESSION['class_id'])) {
    $class_id = $_SESSION['class_id'];
    $section = isset($_SESSION['section']) ? $_SESSION['section'] : null;
    $rollnumber = $_SESSION['rollnumber'];

    $quiz_sql = "SELECT quizname, starttime, maxmarks
                 FROM quizconfig
                 WHERE endtime >= NOW()
                   AND class_id = ?
                   AND (section IS NULL OR LOWER(section) = LOWER(?))
                   AND (
                       SELECT COUNT(*) FROM quizrecord qr
                       WHERE qr.quizid = quizconfig.quizid
                         AND qr.rollnumber = ?
                   ) < attempts
                 ORDER BY starttime ASC
                 LIMIT 1";
    $stmt_quiz = $conn->prepare($quiz_sql);
    $section_param = $section ? $section : '';
    $stmt_quiz->bind_param('isi', $class_id, $section_param, $rollnumber);
    if ($stmt_quiz->execute()) {
      $result_quiz = $stmt_quiz->get_result();
      if ($result_quiz && $result_quiz->num_rows > 0) {
        $upcoming_quiz = $result_quiz->fetch_assoc();
      }
    }
    $stmt_quiz->close();
  }

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <link rel="apple-touch-icon" sizes="76x76" href="./assets/img/apple-icon.png">
  <link rel="icon" type="image/png" href="./assets/img/favicon.png">
  <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
  <title>Studyht Quiz Portal - Student Portal</title>
  <meta content='width=device-width, initial-scale=1.0, shrink-to-fit=no' name='viewport' />
  <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,700|Material+Icons" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="./assets/css/sidebar.css" rel="stylesheet" />
  <style>
    .dashboard-cards {
      display: flex;
      gap: 20px;
      flex-wrap: wrap;
      margin-top: 20px;
    }
    .dashboard-cards .card {
      background: #1e1e2f;
      border: none;
      border-radius: 8px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.1);
      flex: 1;
    }
    .dashboard-cards .card-body {
      padding: 20px;
      text-align: center;
    }
    .dashboard-cards .card i {
      font-size: 48px;
      color: #1a73e8;
    }
    .dashboard-cards .btn {
      display: inline-block;
      margin-top: 10px;
      padding: 10px 20px;
      background-color: #1a73e8;
      color: #fff;
      text-decoration: none;
      border-radius: 4px;
    }
    .dashboard-cards .btn:hover {
      background-color: #1557b0;
    }
    .upcoming-quiz {
      color: #1a73e8;
    }
    .footer-text {
      text-align: center;
      margin-top: 40px;
    }
  </style>
</head>
<body>
<div class="layout">
  <?php include './includes/sidebar.php'; // defines $quiz_link ?>
  <div class="main">
    <?php include './includes/header.php'; ?>
    <main class="content">
      <h1>Welcome to the Student Portal</h1>
      <?php if ($upcoming_quiz): ?>
        <p class="upcoming-quiz">Upcoming quiz: <strong><?php echo htmlspecialchars($upcoming_quiz['quizname']); ?></strong> on <?php echo htmlspecialchars($upcoming_quiz['starttime']); ?></p>
      <?php else: ?>
        <p class="upcoming-quiz">No upcoming quizzes.</p>
      <?php endif; ?>
      <div class="dashboard-cards">
        <div class="card">
          <div class="card-body">
            <i class="material-icons">assignment</i>
            <h4 class="card-title">Take Quiz</h4>
            <p class="card-text">Start your quiz now</p>
            <a href="<?php echo $quiz_link; ?>" class="btn">Start Quiz</a>
          </div>
        </div>
        <div class="card">
          <div class="card-body">
            <i class="material-icons">assessment</i>
            <h4 class="card-title">My Results</h4>
            <p class="card-text">View your quiz results and performance</p>
            <a href="my_results.php" class="btn">View Results</a>
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
<script src="./assets/js/sidebar.js"></script>
</body>
</html>
