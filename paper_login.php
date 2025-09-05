<?php
session_start();

if (isset($_SESSION['paperloggedin']) && $_SESSION['paperloggedin'] === true) {
    header('Location: paper_home.php');
    exit;
}

$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    include 'database.php';
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    if ($email !== '' && $password !== '') {
        $stmt = $conn->prepare('SELECT id, name, password, logo, header, activated_on, expires_on, is_active FROM paper_users WHERE email = ? AND password = ?');
        if ($stmt) {
            $stmt->bind_param('ss', $email, $password);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($user = $result->fetch_assoc()) {
                $today = date('Y-m-d');
                if (!empty($user['expires_on']) && $today > $user['expires_on'] && $user['is_active']) {
                    $upd = $conn->prepare('UPDATE paper_users SET is_active = 0 WHERE id = ?');
                    $upd->bind_param('i', $user['id']);
                    $upd->execute();
                    $upd->close();
                    $user['is_active'] = 0;
                }
                if ((!empty($user['activated_on']) && $today < $user['activated_on']) || !$user['is_active']) {
                    $login_error = 'Account inactive. Please contact administrator.';
                } else {
                    $_SESSION['paperloggedin'] = true;
                    $_SESSION['paper_user_id'] = $user['id'];
                    $_SESSION['paper_logo'] = $user['logo'];
                    $_SESSION['paper_header'] = $user['header'];
                    $_SESSION['paper_user_name'] = $user['name'];
                    header('Location: paper_home.php');
                    exit;
                }
            } else {
                $login_error = 'Invalid credentials';
            }
            $stmt->close();
        }
        $conn->close();
    } else {
        $login_error = 'Please enter email and password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <link rel="apple-touch-icon" sizes="76x76" href="./assets/img/apple-icon.png">
    <link rel="icon" type="image/png" href="./assets/img/favicon.png">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
    <title>Paper Generator Login</title>
    <meta content='width=device-width, initial-scale=1.0, shrink-to-fit=no' name='viewport' />
    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700|Roboto+Slab:400,700|Material+Icons" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="./assets/css/material-kit.css?v=2.0.4" rel="stylesheet" />
    <link href="./assets/css/modern.css" rel="stylesheet" />
    <style>
        html, body { height: 100%; }
        body { display: flex; flex-direction: column; min-height: 100vh; margin: 0; }
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
        .card-login { max-width: 400px; margin: 0 auto; }
        .card .card-header-primary {
            background: linear-gradient(60deg, #ab47bc, #8e24aa);
            box-shadow: 0 5px 20px 0px rgba(0, 0, 0, 0.2),
                       0 13px 24px -11px rgba(156, 39, 176, 0.6);
            margin: -20px 20px 15px;
            border-radius: 3px;
            padding: 15px;
        }
        .card-header-primary .card-title { color: #fff; margin: 0; }
        .btn { width: 100%; }
    </style>
</head>
<body>
    <div class="page-header header-filter">
        <div class="container">
            <div class="row">
                <div class="col-md-4 col-sm-6 ml-auto mr-auto">
                    <div class="card card-login">
                        <form class="form" method="post">
                            <div class="card-header card-header-primary text-center">
                                <h4 class="card-title">Paper Generator Login</h4>
                            </div>
                            <p class="description text-center">Enter your credentials</p>
                            <div class="card-body">
                                <?php if ($login_error): ?>
                                    <div class="alert alert-danger text-center"><?php echo htmlspecialchars($login_error); ?></div>
                                <?php endif; ?>
                                <div class="form-group">
                                    <label class="bmd-label-floating">Email</label>
                                    <input type="email" name="email" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label class="bmd-label-floating">Password</label>
                                    <input type="password" name="password" class="form-control" required>
                                </div>
                            </div>
                            <div class="footer text-center">
                                <button type="submit" class="btn btn-primary btn-lg">Login</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
