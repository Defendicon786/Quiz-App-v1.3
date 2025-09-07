<?php
require_once 'includes/auth.php';
requirePermission();
include 'database.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['reactivate_id'])) {
        $id = (int)($_POST['reactivate_id']);
        $stmt = $conn->prepare('UPDATE paper_users SET is_active = 1 WHERE id = ?');
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            $message = '<p class="text-success">User reactivated successfully!</p>';
        }
        $stmt->close();
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $headerText = trim($_POST['header'] ?? '');
        $activatedOn = $_POST['activated_on'] !== '' ? $_POST['activated_on'] : null;
        $expiresOn = $_POST['expires_on'] !== '' ? $_POST['expires_on'] : null;
        $logoPath = null;

        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'assets/paper_logos/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            $filename = uniqid('logo_', true) . '.' . $ext;
            $logoPath = $uploadDir . $filename;
            move_uploaded_file($_FILES['logo']['tmp_name'], $logoPath);
        }

        if ($name && $email && $password) {
            $stmt = $conn->prepare('INSERT INTO paper_users (name, email, password, logo, header, activated_on, expires_on) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('sssssss', $name, $email, $password, $logoPath, $headerText, $activatedOn, $expiresOn);
            if ($stmt->execute()) {
                $message = '<p class="text-success">User added successfully!</p>';
            } else {
                $message = '<p class="text-danger">Error adding user.</p>';
            }
            $stmt->close();
        } else {
            $message = '<p class="text-danger">Please fill in all required fields.</p>';
        }
    }
}

$users = [];
$res = $conn->query('SELECT id, name, email, logo, activated_on, expires_on, is_active FROM paper_users ORDER BY name');
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $users[] = $row;
    }
    $res->free();
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
    <title>Manage Paper Users</title>
    <meta content='width=device-width, initial-scale=1.0, shrink-to-fit=no' name='viewport' />
    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,700|Material+Icons" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="./assets/css/material-kit.css?v=2.0.4" rel="stylesheet" />
    <link href="./assets/css/sidebar.css" rel="stylesheet" />
    <link href="./assets/css/modern.css" rel="stylesheet" />
    <link href="./assets/css/navbar.css" rel="stylesheet" />
    <link href="./assets/css/portal.css" rel="stylesheet" />
    <link href="./assets/css/manage.css" rel="stylesheet" />
    <link id="dark-mode-style" rel="stylesheet" href="./assets/css/dark-mode.css" />
</head>
<body class="dark-mode">
<div class="layout">
  <?php include './includes/sidebar.php'; ?>
  <div class="main">
    <?php include './includes/header.php'; ?>
    <main class="content">
      <div class="wrapper">
        <div class="main main-raised">
          <div class="container">
            <div class="section">
              <h2 class="text-center">Manage Paper Generator Users</h2>
              <?php echo $message; ?>
              <div class="row">
                <div class="col-md-8 ml-auto mr-auto">
                  <div class="card">
                    <div class="card-header card-header-primary">
                      <h4 class="card-title">Add Paper Generator User</h4>
                    </div>
                    <div class="card-body">
                      <form method="post" enctype="multipart/form-data">
                        <div class="form-group">
                          <label class="bmd-label-floating">Name</label>
                          <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="form-group">
                          <label class="bmd-label-floating">Email</label>
                          <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="form-group">
                          <label class="bmd-label-floating">Password</label>
                          <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="form-group">
                          <label class="bmd-label-floating">Header Text</label>
                          <input type="text" name="header" class="form-control">
                        </div>
                        <div class="form-group">
                          <label class="bmd-label-floating">Activation Date</label>
                          <input type="date" name="activated_on" class="form-control">
                        </div>
                        <div class="form-group">
                          <label class="bmd-label-floating">Freeze Date</label>
                          <input type="date" name="expires_on" class="form-control">
                          <div class="mt-3">
                            <label class="bmd-label-floating">Upload Logo</label>
                            <div class="input-group">
                              <input type="file" name="logo" id="logoInput" accept="image/*" style="display:none;">
                              <button type="button" id="uploadLogoButton" class="btn btn-secondary">Choose Logo</button>
                            </div>
                            <img id="logoPreview" alt="Logo Preview" style="max-height:100px; display:none; margin-top:10px;" />
                          </div>
                        </div>
                        <button type="submit" class="btn btn-primary pull-right">Add User</button>
                        <div class="clearfix"></div>
                      </form>
                    </div>
                  </div>
                </div>
              </div>
              <h4 class="mt-5">Current Paper Users</h4>
              <ul class="list-group">
                <?php foreach ($users as $row) {
                    echo '<li class="list-group-item d-flex justify-content-between align-items-center">';
                    echo '<div>';
                    if (!empty($row['logo'])) {
                        echo '<img src="'.htmlspecialchars($row['logo']).'" alt="Logo" height="40" style="margin-right:10px;">';
                    }
                    echo htmlspecialchars($row['name']).' ('.htmlspecialchars($row['email']).')';
                    if (!$row['is_active']) {
                        echo ' - Inactive';
                        echo '<form method="post" style="display:inline"><input type="hidden" name="reactivate_id" value="'.intval($row['id']).'"><button type="submit" class="btn btn-link btn-sm">Reactivate</button></form>';
                    } else {
                        echo ' - Active';
                    }
                    if (!empty($row['expires_on'])) {
                        echo ' - Freeze Date: '.htmlspecialchars($row['expires_on']);
                    }
                    echo '</div>';
                    echo '<a href="edit_paper_user.php?id='.intval($row['id']).'" class="btn btn-sm btn-primary">Edit</a>';
                    echo '</li>';
                } ?>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </main>
    <footer class="footer-text">
      <p>Narowal Public School and College</p>
      <p>Developed and Maintained by Sir Hassan Tariq</p>
    </footer>
  </div>
</div>
<script src="./assets/js/core/jquery.min.js" type="text/javascript"></script>
<script src="./assets/js/core/popper.min.js" type="text/javascript"></script>
<script src="./assets/js/core/bootstrap-material-design.min.js" type="text/javascript"></script>
<script src="./assets/js/plugins/moment.min.js"></script>
<script src="./assets/js/material-kit.js?v=2.0.4" type="text/javascript"></script>
<script src="./assets/js/dark-mode.js"></script>
<script src="./assets/js/sidebar.js"></script>
<script>
const uploadBtn = document.getElementById('uploadLogoButton');
const logoInput = document.getElementById('logoInput');
if (uploadBtn && logoInput) {
    uploadBtn.addEventListener('click', () => logoInput.click());
    logoInput.addEventListener('change', function () {
        const preview = document.getElementById('logoPreview');
        const [file] = this.files;
        if (file) {
            preview.src = URL.createObjectURL(file);
            preview.style.display = 'block';
        } else {
            preview.style.display = 'none';
        }
    });
}
</script>
</body>
</html>
