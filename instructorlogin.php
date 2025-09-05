<?php
  session_start();
  $login_error_message = ''; // Initialize error message variable

  // Initialize attempt tracking
  if (!isset($_SESSION['instructor_attempts'])) {
      $_SESSION['instructor_attempts'] = 0;
  }

  // Check for lockout and reset attempts if lock has expired
  if (isset($_SESSION['instructor_lock_time'])) {
      if (time() - $_SESSION['instructor_lock_time'] < 300) {
          $remaining = 300 - (time() - $_SESSION['instructor_lock_time']);
          $login_error_message = '<div class="alert alert-danger">Too many failed attempts. Please try again after ' . ceil($remaining / 60) . ' minute(s).</div>';
      } else {
          unset($_SESSION['instructor_lock_time']);
          $_SESSION['instructor_attempts'] = 0;
      }
  }

  // Handle POST request for login
  if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($login_error_message)){
    include "database.php"; // Ensure $conn is available
    if (!$conn) {
        $login_error_message = '<div class="alert alert-danger">Database connection failed.</div>';
    } elseif (isset($_POST["email"]) && isset($_POST["password"])) {
        $email_unsafe = $_POST["email"];
        $password_unsafe = $_POST["password"];

        // Basic validation/sanitization
        $email = $conn->real_escape_string(trim($email_unsafe));
        $password = $conn->real_escape_string(trim($password_unsafe));

        if (!empty($email) && !empty($password)) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) { // Validate email format
                $sql = sprintf("SELECT email FROM instructorinfo WHERE email='%s' AND password='%s';", $email, $password);
                $result = $conn->query($sql);
                if($result && $result->num_rows > 0){
                    // Successful login, reset attempts
                    $_SESSION['instructor_attempts'] = 0;
                    unset($_SESSION['instructor_lock_time']);

                    $_SESSION["instructorloggedin"] = true;
                    $_SESSION["email"] = $email;
                    header("Location: instructorhome.php");
                    exit; // Crucial
                }
                else{
                    $_SESSION['instructor_attempts']++;
                    if ($_SESSION['instructor_attempts'] >= 5) {
                        $_SESSION['instructor_lock_time'] = time();
                        $login_error_message = '<div class="alert alert-danger">Too many failed attempts. Please try again after 5 minutes.</div>';
                    } else {
                        $remaining = 5 - $_SESSION['instructor_attempts'];
                        $login_error_message = '<div class="alert alert-danger">Invalid email or password! You have ' . $remaining . ' attempt(s) remaining.</div>';
                    }
                }
            } else {
                $login_error_message = '<div class="alert alert-danger">Invalid email format.</div>';
            }
        } else {
            $login_error_message = '<div class="alert alert-danger">Email and password cannot be empty.</div>';
        }
        if ($conn) {
            $conn->close();
        }
    } else {
        $login_error_message = '<div class="alert alert-danger">Please provide email and password.</div>';
    }
  }

  // Redirect if already logged in
  if(isset($_SESSION["instructorloggedin"]) && $_SESSION["instructorloggedin"] === true){
    header("Location: instructorhome.php");
    exit;
  }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <link rel="apple-touch-icon" sizes="76x76" href="./assets/img/apple-icon.png">
    <link rel="icon" type="image/png" href="./assets/img/favicon.png">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
    <title>Instructor Login</title>
    <meta content='width=device-width, initial-scale=1.0, shrink-to-fit=no' name='viewport' />
    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700|Roboto+Slab:400,700|Material+Icons" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="./assets/css/material-kit.css?v=2.0.4" rel="stylesheet" />
    <link href="./assets/css/modern.css" rel="stylesheet" />
    <style>
        .page-header {
            min-height: 100vh;
            background: linear-gradient(45deg, rgba(0,0,0,0.7), rgba(72,72,176,0.7)),
                        url('./assets/img/bg.jpg') center center;
            background-size: cover;
            margin: 0;
            padding: 0 0 100px;
            border: 0;
            display: flex;
            align-items: center;
        }

        .card {
            margin-bottom: 30px;
            border: 0;
            border-radius: 6px;
            color: #333333;
            background: #fff;
            width: 100%;
            box-shadow: 0 2px 2px 0 rgba(0,0,0,0.14), 
                       0 3px 1px -2px rgba(0,0,0,0.2), 
                       0 1px 5px 0 rgba(0,0,0,0.12);
        }

        .card-login {
            max-width: 400px;
            margin: 0 auto;
            padding: 20px;
        }

        .card .card-header-primary {
            background: linear-gradient(60deg, #ab47bc, #8e24aa);
            box-shadow: 0 5px 20px 0px rgba(0, 0, 0, 0.2), 
                       0 13px 24px -11px rgba(156, 39, 176, 0.6);
            margin: -20px 20px 15px;
            border-radius: 3px;
            padding: 15px;
            position: relative;
        }

        .card-header-primary .card-title {
            color: #fff;
            margin-top: 0;
            margin-bottom: 3px;
        }

        .description {
            color: #999;
        }

        .form-group {
            margin: 20px 0 0;
            padding-bottom: 10px;
            position: relative;
        }

        .form-control {
            height: 36px;
            padding: 8px 12px;
            border-radius: 4px;
        }

        .btn {
            padding: 12px 30px;
            font-size: 12px;
            font-weight: 400;
            text-transform: uppercase;
            letter-spacing: 0;
            border: 0;
            border-radius: 3px;
            margin: 20px 0;
            position: relative;
            transition: all 0.15s ease;
            width: 100%;
        }

        .btn.btn-primary {
            color: #fff;
            background-color: #9c27b0;
            border-color: #9c27b0;
            box-shadow: 0 2px 2px 0 rgba(156, 39, 176, 0.14), 
                       0 3px 1px -2px rgba(156, 39, 176, 0.2), 
                       0 1px 5px 0 rgba(156, 39, 176, 0.12);
        }

        .btn-primary:hover {
            background-color: #9124a3;
            border-color: #701c7e;
        }

        .back-to-home {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1000;
        }

        .back-to-home .btn {
            background-color: transparent;
            color: white;
            box-shadow: none;
            width: auto;
            padding: 8px 15px;
        }

        .back-to-home .btn:hover {
            background-color: rgba(255,255,255,0.1);
        }

        .footer {
            padding: 30px 0;
            margin: 50px auto 0;
            background: #ffffff;
            border-top: none;
            position: relative;
            width: 95%;
        }
        
        .footer .copyright {
            color: #333;
            font-size: 14px;
            line-height: 1.8;
        }
        
        .footer .copyright .department {
            color: #1a73e8;
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .footer .copyright .designer {
            font-style: italic;
            margin: 5px 0;
            color: white; /* keep designer text visible on this page */
        }
        
        .footer .copyright .year {
            background: #1a73e8;
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            display: inline-block;
            margin-top: 5px;
        }

        /* Responsive Styles */
        @media (max-width: 768px) {
            .card-login {
                margin: 20px;
            }
            
            .card .card-header-primary {
                margin: -20px 10px 15px;
            }
            
            .form-group {
                margin: 15px 0 0;
            }
            
            .btn {
                padding: 10px 20px;
            }
        }

        @media (max-width: 480px) {
            .card-login {
                margin: 10px;
                padding: 15px;
            }
            
            .card .card-header-primary {
                padding: 10px;
            }
            
            .form-group {
                margin: 10px 0 0;
            }
        }
    </style>
<link id="dark-mode-style" rel="stylesheet" href="./assets/css/dark-mode.css" />
</head>

<body class="login-page">
    <div class="back-to-home">
        <a href="index.php" class="btn">
            <i class="material-icons">arrow_back</i> Back to Home
        </a>
    </div>

    <div class="page-header">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 col-md-6 col-sm-8 ml-auto mr-auto">
                    <div class="card card-login">
                        <div class="card-header card-header-primary text-center">
                            <h4 class="card-title">Instructor Login</h4>
                            <div class="description">Enter your credentials to continue</div>
                        </div>
                        <div class="card-body">
                            <?php echo $login_error_message; ?>
                            <form method="POST" action="instructorlogin.php">
                                <div class="form-group">
                                    <label class="bmd-label-floating">Email</label>
                                    <input type="email" class="form-control" name="email" required>
                                </div>
                                <div class="form-group">
                                    <label class="bmd-label-floating">Password</label>
                                    <input type="password" class="form-control" name="password" required>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="material-icons">login</i> Login
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer footer-default">
        <div class="container">
            <div class="copyright text-center">
                <div class="department">A Project of StudyHT.com</div>
                <div class="designer">Designed and Developed by Sir Hassan Tariq</div>
                <div class="year">
                    &copy; <script>document.write(new Date().getFullYear())</script>
                </div>
            </div>
        </div>
    </footer>

    <!--   Core JS Files   -->
    <script src="./assets/js/core/jquery.min.js" type="text/javascript"></script>
    <script src="./assets/js/core/popper.min.js" type="text/javascript"></script>
    <script src="./assets/js/core/bootstrap-material-design.min.js" type="text/javascript"></script>
    <script src="./assets/js/plugins/moment.min.js"></script>
    <script src="./assets/js/material-kit.js?v=2.0.4" type="text/javascript"></script>
<script src="./assets/js/dark-mode.js"></script>
</body>
</html>