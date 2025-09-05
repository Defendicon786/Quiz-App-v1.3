<?php
session_start();
include 'database.php';

if (!isset($_SESSION['email'])) {
    header("Location: instructorlogin.php");
    exit();
}

$instructor_email = $_SESSION['email'];
$class_name = '';
$error_message = '';
$success_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $class_name = trim($_POST['class_name']);

    if (empty($class_name)) {
        $error_message = "Class name cannot be empty.";
    } else {
        // Check if class with the same name already exists for this instructor
        $stmt_check = $conn->prepare("SELECT class_id FROM classes WHERE class_name = ? AND instructor_email = ?");
        $stmt_check->bind_param("ss", $class_name, $instructor_email);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            $error_message = "A class with this name already exists.";
        } else {
            $stmt_insert = $conn->prepare("INSERT INTO classes (class_name, instructor_email) VALUES (?, ?)");
            $stmt_insert->bind_param("ss", $class_name, $instructor_email);
            if ($stmt_insert->execute()) {
                $success_message = "Class '" . htmlspecialchars($class_name) . "' added successfully! You will be redirected shortly.";
                // Clear class name for the form if we were to stay on page
                // $class_name = ''; 
                header("refresh:3;url=manage_classes_subjects.php"); // Redirect after 3 seconds
            } else {
                $error_message = "Error adding class: " . $conn->error;
            }
            $stmt_insert->close();
        }
        $stmt_check->close();
    }
}
// $conn->close(); // Connection will be closed at the end of the script
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Class</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 20px; color: #333; }
        .container { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); max-width: 600px; margin: auto; }
        h2 { color: #5D4037; border-bottom: 2px solid #5D4037; padding-bottom: 10px; }
        .form-group { margin-bottom: 15px; }
        .btn-primary, .btn-secondary { margin-top: 10px; }
        .alert { margin-top: 15px; }
         .footer { text-align: center; margin-top: 30px; padding:15px; background-color: #e9ecef; border-radius: 8px; }
         .header-nav { margin-bottom: 20px; padding: 10px; background-color: #e9ecef; border-radius: 8px; text-align: right; }
         .header-nav a { margin: 0 10px; text-decoration: none; color: #007bff; }
         .header-nav a:hover { text-decoration: underline; }
    </style>
<link id="dark-mode-style" rel="stylesheet" href="./assets/css/dark-mode.css" />
</head>
<body class="landing-page sidebar-collapse">
    <div class="container">
        <div class="header-nav">
            <a href="instructorhome.php">Home</a>
            <a href="manage_classes_subjects.php">Manage Classes & Subjects</a>
            <a href="instructorlogout.php">Logout</a>
        </div>
        <h2>Add New Class</h2>

        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if (!$success_message): // Show form only if not showing success message ?> 
        <form action="add_class.php" method="POST">
            <div class="form-group">
                <label for="class_name">Class Name:</label>
                <input type="text" class="form-control" id="class_name" name="class_name" value="<?php echo htmlspecialchars($class_name); ?>" required>
            </div>
            <button type="submit" class="btn btn-primary">Add Class</button>
            <a href="manage_classes_subjects.php" class="btn btn-secondary">Cancel</a>
        </form>
        <?php endif; ?>
         <div class="footer">
            <p>&copy; <?php echo date("Y"); ?> Online Quiz Portal. All rights reserved.</p>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script src="./assets/js/dark-mode.js"></script>
</body>
</html> 