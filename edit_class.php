<?php
session_start();
include 'database.php';

if (!isset($_SESSION['email'])) {
    header("Location: instructorlogin.php");
    exit();
}

$instructor_email = $_SESSION['email'];
$class_id_to_edit = null;
$class_name = '';
$error_message = '';
$success_message = '';

// Check if class_id is provided for editing
if (isset($_GET['class_id'])) {
    $class_id_to_edit = $_GET['class_id'];
} elseif (isset($_POST['class_id'])) {
    $class_id_to_edit = $_POST['class_id'];
} else {
    $error_message = "No class ID provided for editing.";
    // Optional: redirect to manage_classes_subjects.php if no ID
    // header("Location: manage_classes_subjects.php");
    // exit();
}

// Fetch current class details if ID is available
if ($class_id_to_edit && empty($error_message)) {
    $stmt_fetch = $conn->prepare("SELECT class_name FROM classes WHERE class_id = ? AND instructor_email = ?");
    $stmt_fetch->bind_param("is", $class_id_to_edit, $instructor_email);
    $stmt_fetch->execute();
    $result_fetch = $stmt_fetch->get_result();
    if ($result_fetch->num_rows > 0) {
        $class = $result_fetch->fetch_assoc();
        $class_name = $class['class_name'];
    } else {
        $error_message = "Class not found or you do not have permission to edit it.";
        $class_id_to_edit = null; // Prevent further operations if class is not valid
    }
    $stmt_fetch->close();
}

// Handle form submission for updating the class
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_class']) && $class_id_to_edit) {
    $new_class_name = trim($_POST['class_name']);

    if (empty($new_class_name)) {
        $error_message = "Class name cannot be empty.";
    } elseif ($new_class_name === $class_name) {
        $error_message = "The new class name is the same as the current one. No changes made.";
    }else {
        // Check if the new class name already exists for this instructor (excluding the current class itself)
        $stmt_check = $conn->prepare("SELECT class_id FROM classes WHERE class_name = ? AND instructor_email = ? AND class_id != ?");
        $stmt_check->bind_param("ssi", $new_class_name, $instructor_email, $class_id_to_edit);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            $error_message = "Another class with this name already exists.";
        } else {
            $stmt_update = $conn->prepare("UPDATE classes SET class_name = ? WHERE class_id = ? AND instructor_email = ?");
            $stmt_update->bind_param("sis", $new_class_name, $class_id_to_edit, $instructor_email);
            if ($stmt_update->execute()) {
                $success_message = "Class '" . htmlspecialchars($new_class_name) . "' updated successfully! You will be redirected shortly.";
                $class_name = $new_class_name; // Update current class name to new one
                header("refresh:3;url=manage_classes_subjects.php");
            } else {
                $error_message = "Error updating class: " . $conn->error;
            }
            $stmt_update->close();
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
    <title>Edit Class</title>
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
        <h2>Edit Class</h2>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if ($class_id_to_edit && empty($success_message) && empty($error_message) || (isset($_POST['update_class']) && !empty($error_message)) ): // Show form if class is valid and no success message OR if there was a submission error ?>
        <form action="edit_class.php" method="POST">
            <input type="hidden" name="class_id" value="<?php echo htmlspecialchars($class_id_to_edit); ?>">
            <div class="form-group">
                <label for="class_name">Class Name:</label>
                <input type="text" class="form-control" id="class_name" name="class_name" value="<?php echo htmlspecialchars($class_name); ?>" required>
            </div>
            <button type="submit" name="update_class" class="btn btn-primary">Update Class</button>
            <a href="manage_classes_subjects.php" class="btn btn-secondary">Cancel</a>
        </form>
        <?php elseif (!$class_id_to_edit && empty($success_message)): // if class_id was invalid from the start and no success message (e.g. direct access with bad id) ?>
             <p>Please return to <a href="manage_classes_subjects.php">Manage Classes & Subjects</a> and select a valid class to edit.</p>
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