<?php
session_start();
// Ensure instructor is logged in
if (!isset($_SESSION["instructorloggedin"]) || $_SESSION["instructorloggedin"] !== true) {
    header("location: instructorlogin.php");
    exit;
}

include "database.php";

$message = "";
$instructor_email = $_SESSION["email"];

// Fetch instructor details
$fetch_sql = sprintf(
    "SELECT name, email FROM instructorinfo WHERE email='%s'",
    $conn->real_escape_string($instructor_email)
);
$result = $conn->query($fetch_sql);

if ($result && $result->num_rows > 0) {
    $instructor = $result->fetch_assoc();
} else {
    $message = "<div class='alert alert-danger'>Error: Could not fetch instructor information.</div>";
    $instructor = array('name' => 'Not Found', 'email' => 'Not Found');
}

// For debugging
error_log("Session email: " . print_r($_SESSION, true));
error_log("Fetch SQL: " . $fetch_sql);
error_log("Instructor data: " . print_r($instructor, true));

// Handle password update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_password'])) {
    $current_password = trim($_POST['current_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    // Verify current password
    $verify_sql = sprintf(
        "SELECT password FROM instructorinfo WHERE email='%s'",
        $conn->real_escape_string($instructor_email)
    );
    $verify_result = $conn->query($verify_sql);
    
    if ($verify_result && $verify_result->num_rows > 0) {
        $stored_password = $verify_result->fetch_assoc()['password'];
        
        if ($current_password === $stored_password) {
            if ($new_password === $confirm_password) {
                if (strlen($new_password) >= 6) {
                    $update_sql = sprintf(
                        "UPDATE instructorinfo SET password='%s' WHERE email='%s'",
                        $conn->real_escape_string($new_password),
                        $conn->real_escape_string($instructor_email)
                    );
                    
                    if ($conn->query($update_sql)) {
                        $message = "<div class='alert alert-success'>Password updated successfully!</div>";
                    } else {
                        $message = "<div class='alert alert-danger'>Error updating password: " . $conn->error . "</div>";
                    }
                } else {
                    $message = "<div class='alert alert-danger'>New password must be at least 6 characters long.</div>";
                }
            } else {
                $message = "<div class='alert alert-danger'>New password and confirm password do not match.</div>";
            }
        } else {
            $message = "<div class='alert alert-danger'>Current password is incorrect.</div>";
        }
    } else {
        $message = "<div class='alert alert-danger'>Error: Could not verify current password.</div>";
    }
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
    <title>My Profile</title>
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
                            <div class="profile-card card">
                                <div class="card-header card-header-primary">
                                    <h4 class="card-title">Profile Information</h4>
                                </div>
                                <div class="card-body">
                                    <?php echo $message; ?>
                                    
                                    <!-- Profile Info -->
                                    <div class="row mb-4">
                                        <div class="col-md-12">
                                            <h6 class="text-primary">Name</h6>
                                            <p><?php echo htmlspecialchars($instructor['name']); ?></p>
                                            
                                            <h6 class="text-primary">Email</h6>
                                            <p><?php echo htmlspecialchars($instructor['email']); ?></p>
                                        </div>
                                    </div>
                                    
                                    <!-- Change Password Form -->
                                    <form method="POST" action="my_profile.php">
                                        <h4 class="text-primary mb-4">Change Password</h4>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="form-group">
                                                    <label class="bmd-label-floating">Current Password</label>
                                                    <input type="password" class="form-control" name="current_password" required>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="form-group">
                                                    <label class="bmd-label-floating">New Password</label>
                                                    <input type="password" class="form-control" name="new_password" required>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="form-group">
                                                    <label class="bmd-label-floating">Confirm New Password</label>
                                                    <input type="password" class="form-control" name="confirm_password" required>
                                                </div>
                                            </div>
                                        </div>
                                        <button type="submit" name="update_password" class="btn btn-primary">Update Password</button>
                                    </form>
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
</body>
</html>
