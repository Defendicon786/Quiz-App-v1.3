<?php
session_start();
// Ensure instructor is logged in
if (!isset($_SESSION["instructorloggedin"]) || $_SESSION["instructorloggedin"] !== true) {
    header("location: instructorlogin.php");
    exit;
}

include "database.php"; // Database connection

$add_message = "";
$list_instructors_html = "";

// Handle Add Instructor form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_instructor'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    // WARNING: password should be hashed in production
    $password = $_POST['password']; // Plain text for now
    if (!empty($name) && !empty($email) && !empty($password)) {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // Check if email already exists
            $check_email_sql = sprintf("SELECT email FROM instructorinfo WHERE email = '%s'", $conn->real_escape_string($email));
            $check_email_result = $conn->query($check_email_sql);
            if ($check_email_result && $check_email_result->num_rows == 0) {
                // HASH PASSWORD - IMPORTANT: Replace with password_hash() in a real scenario
                // $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $hashed_password = $password; // Placeholder

                $insert_sql = sprintf("INSERT INTO instructorinfo (name, email, password) VALUES ('%s', '%s', '%s')",
                    $conn->real_escape_string($name),
                    $conn->real_escape_string($email),
                    $conn->real_escape_string($hashed_password) // Use hashed password here
                );

                if ($conn->query($insert_sql)) {
                    $add_message = "<p class='text-success'>Instructor added successfully!</p>";
                } else {
                    $add_message = "<p class='text-danger'>Error adding instructor: " . $conn->error . "</p>";
                }
            } else {
                $add_message = "<p class='text-danger'>Error: Email address already exists.</p>";
            }
        } else {
            $add_message = "<p class='text-danger'>Error: Invalid email format.</p>";
        }
    } else {
        $add_message = "<p class='text-danger'>Error: All fields are required.</p>";
    }
}

// Editing and deleting instructors have been disabled on this page

// Fetch and display current instructors
$fetch_instructors_sql = "SELECT name, email FROM instructorinfo ORDER BY name ASC";
$instructors_result = $conn->query($fetch_instructors_sql);

if ($instructors_result && $instructors_result->num_rows > 0) {
    $list_instructors_html .= "<h4 class='mt-5'>Current Instructors:</h4><ul class='list-group'>";
    while ($instructor_row = $instructors_result->fetch_assoc()) {
        $list_instructors_html .= sprintf(
            "<li class='list-group-item'>%s ( %s )</li>",
            htmlspecialchars($instructor_row['name']),
            htmlspecialchars($instructor_row['email'])
        );
    }
    $list_instructors_html .= "</ul>";
} else {
    $list_instructors_html = "<p class='mt-4'>No instructors found.</p>";
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <link rel="apple-touch-icon" sizes="76x76" href="./assets/img/apple-icon.png">
    <link rel="icon" type="image/png" href="./assets/img/favicon.png">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
    <title>Manage Instructors</title>
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
</head>
<body class="dark-mode">
<div class="layout">
  <?php include './includes/sidebar.php'; ?>
  <div class="main">
    <?php include './includes/header.php'; ?>
    <main class="content">
    <div class="wrapper">
        <div class="main main-raised" style="margin-top: 0;">
            <div class="container">
                <div class="section">
                    <div class="row">
                        <div class="col-md-8 ml-auto mr-auto">
                            <div class="card">
                                <div class="card-header card-header-primary">
                                    <h4 class="card-title">Add New Instructor</h4>
                                </div>
                                <div class="card-body">
                                    <?php echo $add_message; ?>
                                    <form method="POST" action="manage_instructors.php" id="instructorForm">
                                        <div class="form-group">
                                            <label for="name" class="bmd-label-floating">Full Name</label>
                                            <input type="text" class="form-control" id="name" name="name" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="email" class="bmd-label-floating">Email Address</label>
                                            <input type="email" class="form-control" id="email" name="email" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="password" class="bmd-label-floating">Password</label>
                                            <input type="password" class="form-control" id="password" name="password" required>
                                        </div>
                                        <button type="submit" name="add_instructor" class="btn btn-primary" id="submitBtn">Add Instructor</button>
                                    </form>
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-header card-header-primary">
                                    <h4 class="card-title">Current Instructors</h4>
                                </div>
                                <div class="card-body">
                                    <?php echo $list_instructors_html; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </main>
  </div>
</div>

    <!--   Core JS Files   -->
    <script src="./assets/js/core/jquery.min.js" type="text/javascript"></script>
    <script src="./assets/js/core/popper.min.js" type="text/javascript"></script>
    <script src="./assets/js/core/bootstrap-material-design.min.js" type="text/javascript"></script>
    <script src="./assets/js/plugins/moment.min.js"></script>
    <script src="./assets/js/material-kit.js?v=2.0.4" type="text/javascript"></script>
<script src="./assets/js/dark-mode.js"></script>
<script src="./assets/js/sidebar.js"></script>
    <!-- No additional inline scripts needed -->
</body>
</html>
