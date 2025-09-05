<?php
session_start();
include "database.php";

$message = "";
if (!$conn) {
    $message = "<div class='alert alert-danger'>Database connection failed.</div>";
}

// Initialize attempt tracking
if (!isset($_SESSION['student_attempts'])) {
    $_SESSION['student_attempts'] = 0;
}

// Check for lockout and reset attempts if lock has expired
if (isset($_SESSION['student_lock_time'])) {
    if (time() - $_SESSION['student_lock_time'] < 300) {
        $remaining = 300 - (time() - $_SESSION['student_lock_time']);
        $message = "<div class='alert alert-danger'>Too many failed attempts. Please try again after " . ceil($remaining / 60) . " minute(s).</div>";
    } else {
        unset($_SESSION['student_lock_time']);
        $_SESSION['student_attempts'] = 0;
    }
}

if ($conn && $_SERVER["REQUEST_METHOD"] == "POST" && empty($message)) {
    $rollnumber = trim($_POST["rollnumber"]);
    $password = trim($_POST["password"]);

    $sql = sprintf("SELECT * FROM studentinfo WHERE rollnumber=%d AND password='%s'",
        intval($rollnumber),
        $conn->real_escape_string($password)
    );

    $result = $conn->query($sql);

    if ($result->num_rows == 1) {
        // Successful login, reset attempts
        $_SESSION['student_attempts'] = 0;
        unset($_SESSION['student_lock_time']);

        $student_data = $result->fetch_assoc();
        $_SESSION["studentloggedin"] = true;
        $_SESSION["rollnumber"] = $rollnumber;
        $_SESSION["name"] = $student_data['name'];
        $_SESSION["email"] = $student_data['email'];
        $_SESSION["department"] = $student_data['department'];
        $_SESSION["program"] = $student_data['program'];
        $_SESSION["section"] = $student_data['section'];

        // Get class and section IDs to store in session
        $class_query = $conn->prepare("SELECT class_id FROM classes WHERE class_name = ?");
        $class_query->bind_param("s", $student_data['department']);
        $class_query->execute();
        $class_result = $class_query->get_result();

        if ($class_result->num_rows == 1) {
            $class_data = $class_result->fetch_assoc();
            $_SESSION["class_id"] = $class_data['class_id'];

            // Get section ID if section exists
            if (!empty($student_data['section'])) {
                $section_query = $conn->prepare("SELECT id FROM class_sections WHERE class_id = ? AND section_name = ?");
                $section_query->bind_param("is", $class_data['class_id'], $student_data['section']);
                $section_query->execute();
                $section_result = $section_query->get_result();

                if ($section_result->num_rows == 1) {
                    $section_data = $section_result->fetch_assoc();
                    $_SESSION["section_id"] = $section_data['id'];
                }
                $section_query->close();
            }
        }
        $class_query->close();

        header("location: studenthome.php");
    } else {
        // Failed login attempt
        $_SESSION['student_attempts']++;
        if ($_SESSION['student_attempts'] >= 5) {
            $_SESSION['student_lock_time'] = time();
            $message = "<div class='alert alert-danger'>Too many failed attempts. Please try again after 5 minutes.</div>";
        } else {
            $remaining = 5 - $_SESSION['student_attempts'];
            $message = "<div class='alert alert-danger'>Invalid roll number or password! You have {$remaining} attempt(s) remaining.</div>";
        }
    }
}
if ($conn) {
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <link rel="apple-touch-icon" sizes="76x76" href="./assets/img/apple-icon.png">
    <link rel="icon" type="image/png" href="./assets/img/favicon.png">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
    <title>Student Login</title>
    <meta content='width=device-width, initial-scale=1.0, shrink-to-fit=no' name='viewport' />
    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700|Roboto+Slab:400,700|Material+Icons" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="./assets/css/material-kit.css?v=2.0.4" rel="stylesheet" />
    <link href="./assets/css/modern.css" rel="stylesheet" />
    <style>
        html, body {
            height: 100%;
        }

        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            margin: 0;
        }
        .page-header {
            background: linear-gradient(45deg, rgba(0,0,0,0.7), rgba(72,72,176,0.7)),
                        url('./assets/img/bg.jpg') center center;
            background-size: cover;
            margin: 0;
            padding: 0;
            border: 0;
            display: flex;
            align-items: center;
            flex: 1 0 auto;
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
            background: #ffffff;
            border-top: none;
            width: 100%;
            margin-top: auto;
        }

        /* Override Material Kit's absolute positioned footer on login pages */
        .login-page .footer {
            position: relative;
            background: #ffffff;
            color: #555;
            z-index: auto;
        }
        
        .footer .copyright {
            color: #555;
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
            color: black;
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
            .page-header {
                padding-bottom: 120px;
            }
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
            .page-header {
                padding-bottom: 150px;
            }
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
                            <h4 class="card-title">Student Login</h4>
                            <div class="description">Enter your credentials to continue</div>
                        </div>
                        <div class="card-body">
                            <?php echo $message; ?>
                            <form method="POST" action="studentlogin.php">
                                <div class="form-group">
                                    <label class="bmd-label-floating">Roll Number</label>
                                    <input type="number" class="form-control" name="rollnumber" required>
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
