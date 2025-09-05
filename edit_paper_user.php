<?php
session_start();
if (!isset($_SESSION['instructorloggedin']) || $_SESSION['instructorloggedin'] !== true) {
    header('Location: instructorlogin.php');
    exit;
}
include 'database.php';

$message = '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $headerText = trim($_POST['header'] ?? '');
    $activatedOn = $_POST['activated_on'] !== '' ? $_POST['activated_on'] : null;
    $expiresOn = $_POST['expires_on'] !== '' ? $_POST['expires_on'] : null;
    $logoPath = $_POST['existing_logo'] ?? null;

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

    if ($name && $email) {
        $query = 'UPDATE paper_users SET name=?, email=?, header=?, activated_on=?, expires_on=?, logo=?';
        $types = 'ssssss';
        $params = [$name, $email, $headerText, $activatedOn, $expiresOn, $logoPath];
        if ($password !== '') {
            $query .= ', password=?';
            $types .= 's';
            $params[] = $password;
        }
        $query .= ' WHERE id=?';
        $types .= 'i';
        $params[] = $id;

        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        if ($stmt->execute()) {
            $message = '<p class="text-success">User updated successfully!</p>';
        } else {
            $message = '<p class="text-danger">Error updating user.</p>';
        }
        $stmt->close();
    } else {
        $message = '<p class="text-danger">Please fill in all required fields.</p>';
    }
}

$user = null;
if ($id) {
    $stmt = $conn->prepare('SELECT id, name, email, logo, header, activated_on, expires_on FROM paper_users WHERE id=?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
    } else {
        $message = '<p class="text-danger">User not found.</p>';
    }
    $stmt->close();
} else {
    $message = '<p class="text-danger">No user specified.</p>';
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
    <title>Edit Paper User</title>
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
              <h2 class="text-center">Edit Paper Generator User</h2>
              <?php echo $message; ?>
              <?php if ($user) { ?>
              <div class="row">
                <div class="col-md-8 ml-auto mr-auto">
                  <div class="card">
                    <div class="card-header card-header-primary">
                      <h4 class="card-title">Edit User</h4>
                    </div>
                    <div class="card-body">
                      <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="id" value="<?php echo intval($user['id']); ?>">
                        <input type="hidden" name="existing_logo" value="<?php echo htmlspecialchars($user['logo']); ?>">
                        <div class="form-group">
                          <label class="bmd-label-floating">Name</label>
                          <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                        </div>
                        <div class="form-group">
                          <label class="bmd-label-floating">Email</label>
                          <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <div class="form-group">
                          <label class="bmd-label-floating">Password (leave blank to keep current)</label>
                          <input type="password" name="password" class="form-control">
                        </div>
                        <div class="form-group">
                          <label class="bmd-label-floating">Header Text</label>
                          <input type="text" name="header" class="form-control" value="<?php echo htmlspecialchars($user['header']); ?>">
                        </div>
                        <div class="form-group">
                          <label class="bmd-label-floating">Activation Date</label>
                          <input type="date" name="activated_on" class="form-control" value="<?php echo htmlspecialchars($user['activated_on']); ?>">
                        </div>
                        <div class="form-group">
                          <label class="bmd-label-floating">Freeze Date</label>
                          <input type="date" name="expires_on" class="form-control" value="<?php echo htmlspecialchars($user['expires_on']); ?>">
                          <div class="mt-3">
                            <label class="bmd-label-floating">Upload Logo</label>
                            <div class="input-group">
                              <input type="file" name="logo" id="logoInput" accept="image/*" style="display:none;">
                              <button type="button" id="uploadLogoButton" class="btn btn-secondary">Choose Logo</button>
                            </div>
                            <?php if (!empty($user['logo'])) { ?>
                            <img id="logoPreview" src="<?php echo htmlspecialchars($user['logo']); ?>" alt="Logo Preview" style="max-height:100px; margin-top:10px;" />
                            <?php } else { ?>
                            <img id="logoPreview" alt="Logo Preview" style="max-height:100px; display:none; margin-top:10px;" />
                            <?php } ?>
                          </div>
                        </div>
                        <button type="submit" class="btn btn-primary pull-right">Update User</button>
                        <a href="paper_manage.php" class="btn btn-secondary">Cancel</a>
                        <div class="clearfix"></div>
                      </form>
                    </div>
                  </div>
                </div>
              </div>
              <?php } ?>
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
        } else if (!preview.getAttribute('src')) {
            preview.style.display = 'none';
        }
    });
}
</script>
</body>
</html>

