<?php
ob_start(); // Start output buffering
session_start();

$error_message = '';
if (isset($_SESSION['error'])) {
    $error_message = htmlspecialchars($_SESSION['error']);
    unset($_SESSION['error']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <link rel="apple-touch-icon" sizes="76x76" href="./assets/img/apple-icon.png">
    <link rel="icon" type="image/png" href="./assets/img/favicon.png">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
    <title>Quiz Portal</title>
    <meta content='width=device-width, initial-scale=1.0, shrink-to-fit=no' name='viewport' />
    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700|Roboto+Slab:400,700|Material+Icons" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="./assets/css/material-kit.css?v=2.0.4" rel="stylesheet" />
    <link href="./assets/css/modern.css" rel="stylesheet" />
    <!-- Animation library for on-scroll animations -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" rel="stylesheet">
    <style>
        .page-header {
            height: 100vh;
            background: linear-gradient(45deg, rgba(0,0,0,0.7), rgba(72,72,176,0.7)), 
                        url('./assets/img/bg.jpg') center center;
            background-size: cover;
            margin: 0;
            padding: 0;
            border: 0;
            display: flex;
            align-items: center;
        }

        .header-filter::before {
            display: none;
        }

        .main-raised {
            margin: -60px 30px 0px;
            border-radius: 6px;
            box-shadow: 0 16px 24px 2px rgba(0, 0, 0, 0.14), 
                        0 6px 30px 5px rgba(0, 0, 0, 0.12), 
                        0 8px 10px -5px rgba(0, 0, 0, 0.2);
        }

        .main {
            background: #FFFFFF;
            position: relative;
            z-index: 3;
        }

        .profile-page .profile {
            text-align: center;
        }

        .profile-page .profile img {
            max-width: 160px;
            margin: 0 auto;
            transform: translate3d(0, -50%, 0);
        }

        .img-raised {
            box-shadow: 0 5px 15px -8px rgba(0, 0, 0, 0.24), 
                        0 8px 10px -5px rgba(0, 0, 0, 0.2);
        }

        .rounded-circle {
            border-radius: 50% !important;
        }

        .img-fluid {
            max-width: 100%;
            height: auto;
        }

        .title {
            margin-top: 30px;
            margin-bottom: 25px;
            min-height: 32px;
            font-weight: 700;
            font-family: "Roboto Slab", "Times New Roman", serif;
        }

        .description {
            margin: 1.071rem auto 0;
            max-width: 600px;
            color: #999;
        }

        .btn {
            padding: 12px 30px;
            margin: 5px;
            font-size: 12px;
            font-weight: 400;
            text-transform: uppercase;
            letter-spacing: 0;
            border: 0;
            border-radius: 3px;
            position: relative;
            transition: all 0.15s ease;
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

        .btn-lg {
            padding: 1.125rem 2.25rem;
            font-size: 0.875rem;
            line-height: 1.333333;
            border-radius: 0.2rem;
        }

        .buttons-container {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 30px;
        }

        .footer {
            padding: 30px 0;
            margin-top: 50px;
            background: #f8f9fa;
            border-top: 1px solid #eee;
        }
        
        .footer .copyright {
            color: #555;
            font-size: 14px;
            line-height: 1.8;
        }
        
        .footer .copyright strong {
            font-weight: 600;
            color: #333;
        }
        
        .footer .copyright .department {
            color: #1a73e8;
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .footer .copyright .designer {
            font-style: italic;
            margin: 5px 0;
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
        @media (max-width: 991px) {
            .main-raised {
                margin: -60px 15px 0px;
            }
        }

        @media (max-width: 768px) {
            .page-header {
                min-height: 100vh;
            }
            
            .main-raised {
                margin: -60px 10px 0px;
            }
            
            .title {
                font-size: 2.5em;
            }
            
            .description {
                font-size: 1em;
                padding: 0 15px;
            }
            
            .buttons-container {
                flex-direction: column;
                align-items: center;
                padding: 0 15px;
            }
            
            .btn {
                width: 100%;
                max-width: 300px;
            }
        }

        @media (max-width: 480px) {
            .title {
                font-size: 2em;
            }
            
            .description {
                font-size: 0.9em;
            }
            
            .profile-page .profile img {
                max-width: 120px;
            }
        }
    </style>
<link id="dark-mode-style" rel="stylesheet" href="./assets/css/dark-mode.css" />
</head>

<body class="landing-page sidebar-collapse">
    <?php
    if (!empty($error_message)) {
        echo '<div class="alert alert-danger" style="margin: 0; position: fixed; top: 0; left: 0; right: 0; z-index: 9999; text-align: center; padding: 15px;">
            ' . $error_message . '
        </div>';
    }
    ?>
    <div class="page-header header-filter" data-parallax="true">
        <div class="container">
            <div class="row">
                <div class="col-md-12">
                    <div class="brand text-center" data-aos="zoom-in">
                        <h1 class="title text-white">Online Quiz Portal</h1>
                        <h3 class="description text-white">Welcome to the Quiz Portal. Please select your role to continue.</h3>
                        <div class="buttons-container" data-aos="fade-up" data-aos-delay="300">
                            <a href="instructorlogin.php" class="btn btn-primary btn-lg">
                                <i class="material-icons">person_outline</i> Instructor Login
                            </a>
                            <a href="studentlogin.php" class="btn btn-primary btn-lg">
                                <i class="material-icons">school</i> Student Login
                            </a>
                            <a href="paper_login.php" class="btn btn-primary btn-lg">
                                <i class="material-icons">description</i> Paper Generator
                            </a>
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
    <!-- AOS animation library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    <script>
        AOS.init({
            once: true
        });
    </script>
<script src="./assets/js/dark-mode.js"></script>
</body>
</html>
