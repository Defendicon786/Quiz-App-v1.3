<?php
session_start();
// Ensure instructor is logged in
if (!isset($_SESSION["instructorloggedin"]) || $_SESSION["instructorloggedin"] !== true) {
    header("location: instructorlogin.php");
    exit;
}

include "database.php"; // Database connection

$message = "";

    // Handle Add/Edit/Delete Student
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_student'])) {
        $rollnumber = trim($_POST['rollnumber']);
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $class_id = trim($_POST['class_id']);
        $section = trim($_POST['section']);

        if (!empty($rollnumber) && !empty($name) && !empty($email) && !empty($password) && !empty($class_id)) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                // Check if roll number already exists
                $check_roll_sql = sprintf("SELECT rollnumber FROM studentinfo WHERE rollnumber = %d", 
                    intval($rollnumber));
                $check_roll_result = $conn->query($check_roll_sql);
                
                if ($check_roll_result && $check_roll_result->num_rows == 0) {
                    // Get class name for storing in department
                    $class_sql = sprintf("SELECT class_name FROM classes WHERE class_id = %d", intval($class_id));
                    $class_result = $conn->query($class_sql);
                    $class_name = '';
                    if ($class_result && $class_result->num_rows > 0) {
                        $class_row = $class_result->fetch_assoc();
                        $class_name = $class_row['class_name'];
                    }
                    
                    // Get section_id from section_name
                    $section_id = null;
                    if (!empty($section)) {
                        $section_sql = sprintf(
                            "SELECT id FROM class_sections WHERE class_id = %d AND section_name = '%s'", 
                            intval($class_id),
                            $conn->real_escape_string($section)
                        );
                        $section_result = $conn->query($section_sql);
                        if ($section_result && $section_result->num_rows > 0) {
                            $section_row = $section_result->fetch_assoc();
                            $section_id = $section_row['id'];
                        } else {
                            // If section doesn't exist, create it
                            $insert_section_sql = sprintf(
                                "INSERT INTO class_sections (class_id, section_name) VALUES (%d, '%s')",
                                intval($class_id),
                                $conn->real_escape_string($section)
                            );
                            if ($conn->query($insert_section_sql)) {
                                $section_id = $conn->insert_id;
                            }
                        }
                    }
                    
                    $insert_sql = sprintf(
                        "INSERT INTO studentinfo (rollnumber, name, email, password, department, program, section, section_id) VALUES (%d, '%s', '%s', '%s', '%s', '%s', '%s', %s)",
                        intval($rollnumber),
                        $conn->real_escape_string($name),
                        $conn->real_escape_string($email),
                        $conn->real_escape_string($password),
                        $conn->real_escape_string($class_name), // Store class name in department field
                        $conn->real_escape_string(''), // Empty program field
                        $conn->real_escape_string($section),
                        $section_id ? $section_id : "NULL"
                    );

                    if ($conn->query($insert_sql)) {
                        $message = "<div class='alert alert-success'>Student added successfully!</div>";
                    } else {
                        $message = "<div class='alert alert-danger'>Error adding student: " . $conn->error . "</div>";
                    }
                } else {
                    $message = "<div class='alert alert-danger'>Error: Roll number already exists.</div>";
                }
            } else {
                $message = "<div class='alert alert-danger'>Error: Invalid email format.</div>";
            }
        } else {
            $message = "<div class='alert alert-danger'>Error: All fields are required.</div>";
        }
    }
    
    // Handle Edit Student
    else if (isset($_POST['edit_student'])) {
        $rollnumber = trim($_POST['rollnumber']);
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $class_id = trim($_POST['class_id']);
        $section = trim($_POST['section']);
        
        // Get class name for storing in department
        $class_sql = sprintf("SELECT class_name FROM classes WHERE class_id = %d", intval($class_id));
        $class_result = $conn->query($class_sql);
        $class_name = '';
        if ($class_result && $class_result->num_rows > 0) {
            $class_row = $class_result->fetch_assoc();
            $class_name = $class_row['class_name'];
        }
        
        // Get section_id from section_name or create new section
        $section_id = null;
        if (!empty($section)) {
            $section_sql = sprintf(
                "SELECT id FROM class_sections WHERE class_id = %d AND section_name = '%s'", 
                intval($class_id),
                $conn->real_escape_string($section)
            );
            $section_result = $conn->query($section_sql);
            if ($section_result && $section_result->num_rows > 0) {
                $section_row = $section_result->fetch_assoc();
                $section_id = $section_row['id'];
            } else {
                // If section doesn't exist, create it
                $insert_section_sql = sprintf(
                    "INSERT INTO class_sections (class_id, section_name) VALUES (%d, '%s')",
                    intval($class_id),
                    $conn->real_escape_string($section)
                );
                if ($conn->query($insert_section_sql)) {
                    $section_id = $conn->insert_id;
                }
            }
        }
        
        $update_sql = sprintf(
            "UPDATE studentinfo SET name='%s', email='%s', department='%s', program='%s', section='%s', section_id=%s WHERE rollnumber=%d",
            $conn->real_escape_string($name),
            $conn->real_escape_string($email),
            $conn->real_escape_string($class_name), // Store class name in department field
            $conn->real_escape_string(''), // Empty program field
            $conn->real_escape_string($section),
            $section_id ? $section_id : "NULL",
            intval($rollnumber)
        );
        
        if ($conn->query($update_sql)) {
            $message = "<div class='alert alert-success'>Student updated successfully!</div>";
        } else {
            $message = "<div class='alert alert-danger'>Error updating student: " . $conn->error . "</div>";
        }
    }
    
    // Handle Delete Student
    else if (isset($_POST['delete_student'])) {
        $rollnumber = trim($_POST['rollnumber']);
        
        $delete_sql = sprintf(
            "DELETE FROM studentinfo WHERE rollnumber=%d",
            intval($rollnumber)
        );
        
        if ($conn->query($delete_sql)) {
            $message = "<div class='alert alert-success'>Student deleted successfully!</div>";
        } else {
            $message = "<div class='alert alert-danger'>Error deleting student: " . $conn->error . "</div>";
        }
    }
}

// Fetch all classes for dropdown
$classes = [];
$classes_sql = "SELECT class_id, class_name FROM classes ORDER BY class_name ASC";
$classes_result = $conn->query($classes_sql);
if ($classes_result && $classes_result->num_rows > 0) {
    while ($class_row = $classes_result->fetch_assoc()) {
        $classes[] = $class_row;
    }
}

// We'll load sections dynamically based on class selection

// Fetch all students
$students_html = "";

// Initialize pagination variables
$recordsPerPage = 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $recordsPerPage;

// Filter variables
$filter_roll = isset($_GET['filter_roll']) ? trim($_GET['filter_roll']) : '';
$filter_name = isset($_GET['filter_name']) ? trim($_GET['filter_name']) : '';
$filter_class = isset($_GET['filter_class']) ? intval($_GET['filter_class']) : '';
$filter_section = isset($_GET['filter_section']) ? trim($_GET['filter_section']) : '';

// Build SQL query with filters
$where_conditions = [];
$params = [];

if (!empty($filter_roll)) {
    $where_conditions[] = "s.rollnumber LIKE ?";
    $params[] = "%$filter_roll%";
}

if (!empty($filter_name)) {
    $where_conditions[] = "s.name LIKE ?";
    $params[] = "%$filter_name%";
}

if (!empty($filter_class)) {
    $where_conditions[] = "c.class_id = ?";
    $params[] = $filter_class;
}

if (!empty($filter_section)) {
    $where_conditions[] = "s.section LIKE ?";
    $params[] = "%$filter_section%";
}

// Construct WHERE clause
$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
}

// Count total records for pagination
$count_sql = "SELECT COUNT(*) as total FROM studentinfo s LEFT JOIN classes c ON s.department = c.class_name $where_clause";
$stmt = $conn->prepare($count_sql);

if (!empty($params)) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$totalRecords = $row['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Fetch students with pagination and filters
$fetch_students_sql = "SELECT s.*, c.class_id FROM studentinfo s 
                       LEFT JOIN classes c ON s.department = c.class_name
                       $where_clause
                       ORDER BY s.rollnumber ASC
                       LIMIT ? OFFSET ?";

$stmt = $conn->prepare($fetch_students_sql);

if (!empty($params)) {
    $params[] = $recordsPerPage;
    $params[] = $offset;
    $types = str_repeat('s', count($params) - 2) . 'ii';
    $stmt->bind_param($types, ...$params);
} else {
    $stmt->bind_param('ii', $recordsPerPage, $offset);
}

$stmt->execute();
$students_result = $stmt->get_result();

if ($students_result && $students_result->num_rows > 0) {
    while ($student = $students_result->fetch_assoc()) {
        $students_html .= sprintf(
            '<tr>
                <td>%s</td>
                <td>%s</td>
                <td>%s</td>
                <td>%s</td>
                <td>%s</td>
                <td>
                    <button type="button" class="btn btn-info btn-sm" onclick="editStudent(%d, \'%s\', \'%s\', \'%s\', \'%s\')">
                        <i class="material-icons">edit</i>
                    </button>
                    <button type="button" class="btn btn-danger btn-sm" onclick="deleteStudent(%d)">
                        <i class="material-icons">delete</i>
                    </button>
                </td>
            </tr>',
            htmlspecialchars($student['rollnumber']),
            htmlspecialchars($student['name']),
            htmlspecialchars($student['email']),
            htmlspecialchars($student['department']), // Display class name (stored in department field)
            htmlspecialchars($student['section'] ?? ''),
            $student['rollnumber'],
            addslashes($student['name']),
            addslashes($student['email']),
            addslashes($student['class_id'] ?? ''), // Pass class_id for editing
            addslashes($student['section'] ?? ''),
            $student['rollnumber']
        );
    }
} else {
    $students_html = '<tr><td colspan="6" class="text-center">No students found matching your criteria.</td></tr>';
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
    <title>Manage Students</title>
    <meta content='width=device-width, initial-scale=1.0, shrink-to-fit=no' name='viewport' />
    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700|Roboto+Slab:400,700|Material+Icons" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="./assets/css/material-kit.css?v=2.0.4" rel="stylesheet" />
    <link href="./assets/css/modern.css" rel="stylesheet" />
    <link href="./assets/css/navbar.css" rel="stylesheet" />
    <link href="./assets/css/portal.css" rel="stylesheet" />
    <link href="./assets/css/manage.css" rel="stylesheet" />
    <link href="./assets/css/sidebar.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
    <style>

        /* Additional Styles */
        .main-raised {
            margin-top: 0 !important;
        }
        .card { 
            margin-bottom: 30px;
        }
        .table td, .table th {
            vertical-align: middle;
        }
        .btn-sm {
            padding: 0.40625rem 1.25rem;
        }
        .btn-sm i {
            font-size: 18px;
        }

        .dark-input {
            background-color: #1e1e2f;
            color: #fff;
            border: 1px solid #27293d;
        }

        .dark-input::placeholder {
            color: #bbb;
        }

        /* Select2 custom styling */
        .select2-container--default .select2-selection--single {
            height: 36px;
            border: 1px solid #27293d;
            border-radius: 0;
            background-color: #1e1e2f;
            padding-top: 2px;
            color: #fff;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #fff;
            line-height: 32px;
            padding-left: 12px;
        }

        .select2-container--default.select2-container--focus .select2-selection--single,
        .select2-container--default.select2-container--open .select2-selection--single {
            border-color: #9c27b0;
            box-shadow: 0 1px 0 0 #9c27b0;
        }

        .select2-dropdown {
            border: 1px solid #27293d;
            border-radius: 0;
            background-color: #1e1e2f;
            color: #fff;
        }

        .select2-results__option {
            padding: 8px 12px;
            color: #fff;
        }

        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: #9c27b0;
            color: #fff;
        }
        
        /* Pagination Styles */
        .pagination-container {
            margin-top: 30px;
        }
        
        .pagination {
            display: inline-flex;
            border-radius: 3px;
            margin: 10px 0;
        }
        
        .pagination .page-item {
            margin: 0 2px;
        }
        
        .pagination .page-item.active .page-link {
            background-color: #9c27b0;
            border-color: #9c27b0;
            color: white;
            box-shadow: 0 4px 20px 0px rgba(0, 0, 0, 0.14), 0 7px 10px -5px rgba(156, 39, 176, 0.4);
        }
        
        .pagination .page-link {
            border: 0;
            border-radius: 30px !important;
            transition: all .3s;
            padding: 0 11px;
            margin: 0 3px;
            min-width: 30px;
            height: 30px;
            line-height: 30px;
            color: #999999;
            font-weight: 400;
            font-size: 12px;
            text-transform: uppercase;
            background: transparent;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .pagination .page-link:hover {
            background-color: #eee;
            border-color: #ddd;
            color: #333;
        }
        
        .pagination .page-link .material-icons {
            font-size: 20px;
        }
        
        .text-muted {
            font-size: 13px;
            margin-top: 10px;
        }
    </style>
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
                    <?php echo $message; ?>
                    
                    <!-- Add Student Form -->
                    <div class="row">
                        <div class="col-md-8 ml-auto mr-auto">
                            <div class="card">
                                <div class="card-header card-header-primary">
                                    <h4 class="card-title">Add New Student</h4>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="manage_students.php" id="studentForm">
                                        <input type="hidden" name="action" id="formAction" value="add">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="bmd-label-floating">Roll Number</label>
                                                    <input type="number" class="form-control" name="rollnumber" id="rollnumber" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="bmd-label-floating">Full Name</label>
                                                    <input type="text" class="form-control" name="name" id="name" required>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="bmd-label-floating">Email</label>
                                                    <input type="email" class="form-control dark-input" name="email" id="email" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="bmd-label-floating">Password</label>
                                                    <input type="password" class="form-control dark-input" name="password" id="password">
                                                    <small class="form-text text-muted">Leave empty to keep existing password when editing</small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="bmd-label-floating">Class</label>
                                                    <select class="form-control dark-input" name="class_id" id="class_id">
                                                        <option value="">Select Class</option>
                                                        <?php foreach ($classes as $class) : ?>
                                                            <option value="<?php echo htmlspecialchars($class['class_id']); ?>"><?php echo htmlspecialchars($class['class_name']); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="bmd-label-floating">Section</label>
                                                    <select class="form-control dark-input" name="section" id="section">
                                                        <option value="">First select a class</option>
                                                    </select>
                                                    <div id="section-loading-indicator" style="display:none; color:blue; font-size:12px; margin-top:5px;">
                                                        <i class="material-icons" style="font-size:14px;vertical-align:middle;">sync</i> Loading sections...
                                                    </div>
                                                    <div id="section-error-indicator" style="display:none; color:red; font-size:12px; margin-top:5px;">
                                                        <i class="material-icons" style="font-size:14px;vertical-align:middle;">error</i> Error loading sections
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <button type="submit" name="add_student" class="btn btn-primary pull-right">Add Student</button>
                                        <div class="clearfix"></div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Section Add -->
                    <div class="row mb-4">
                        <div class="col-md-8 ml-auto mr-auto">
                            <div class="card">
                                <div class="card-header card-header-info">
                                    <h4 class="card-title">Add Class Section</h4>
                                    <p class="card-category">Quickly add a new section to a class</p>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-5">
                                            <select class="form-control" id="quick_class_id">
                                                <option value="">Select Class</option>
                                                <?php foreach ($classes as $class) : ?>
                                                    <option value="<?php echo htmlspecialchars($class['class_id']); ?>"><?php echo htmlspecialchars($class['class_name']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-5">
                                            <input type="text" class="form-control" id="quick_section_name" placeholder="Enter Section Name (e.g., A, B, Gold)">
                                        </div>
                                        <div class="col-md-2">
                                            <button type="button" class="btn btn-info btn-block" id="add_section_btn">Add</button>
                                        </div>
                                    </div>
                                    <div class="row mt-2">
                                        <div class="col-md-12">
                                            <div id="quick_add_message"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                
                <!-- Students List -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header card-header-primary">
                                    <h4 class="card-title">Students List</h4>
                                </div>
                                <div class="card-body">
                                    <!-- Filter Form -->
                                    <form method="GET" action="manage_students.php" class="mb-4">
                                        <div class="row">
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label class="bmd-label-floating">Roll Number</label>
                                                    <input type="text" class="form-control" name="filter_roll" value="<?php echo htmlspecialchars($filter_roll); ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label class="bmd-label-floating">Name</label>
                                                    <input type="text" class="form-control" name="filter_name" value="<?php echo htmlspecialchars($filter_name); ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label class="bmd-label-floating">Class</label>
                                                    <select class="form-control dark-input" name="filter_class">
                                                        <option value="">All Classes</option>
                                                        <?php foreach ($classes as $class) : ?>
                                                            <option value="<?php echo htmlspecialchars($class['class_id']); ?>" <?php echo $filter_class == $class['class_id'] ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($class['class_name']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label class="bmd-label-floating">Section</label>
                                                    <input type="text" class="form-control" name="filter_section" value="<?php echo htmlspecialchars($filter_section); ?>">
                                                </div>
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="material-icons">search</i> Filter
                                        </button>
                                        <a href="manage_students.php" class="btn btn-default btn-sm">
                                            <i class="material-icons">clear</i> Reset
                                        </a>
                                    </form>
                                    
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead class="text-primary">
                                                <tr>
                                                    <th>Roll Number</th>
                                                    <th>Name</th>
                                                    <th>Email</th>
                                                    <th>Class</th>
                                                    <th>Section</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php echo $students_html; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <!-- Pagination -->
                                    <?php if ($totalPages > 1): ?>
                                    <div class="pagination-container text-center">
                                        <ul class="pagination">
                                            <?php if ($page > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=1<?php 
                                                        echo !empty($filter_roll) ? '&filter_roll='.urlencode($filter_roll) : ''; 
                                                        echo !empty($filter_name) ? '&filter_name='.urlencode($filter_name) : '';
                                                        echo !empty($filter_class) ? '&filter_class='.$filter_class : '';
                                                        echo !empty($filter_section) ? '&filter_section='.urlencode($filter_section) : '';
                                                    ?>">
                                                        <i class="material-icons">first_page</i>
                                                    </a>
                                                </li>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?php echo $page - 1; ?><?php 
                                                        echo !empty($filter_roll) ? '&filter_roll='.urlencode($filter_roll) : ''; 
                                                        echo !empty($filter_name) ? '&filter_name='.urlencode($filter_name) : '';
                                                        echo !empty($filter_class) ? '&filter_class='.$filter_class : '';
                                                        echo !empty($filter_section) ? '&filter_section='.urlencode($filter_section) : '';
                                                    ?>">
                                                        <i class="material-icons">chevron_left</i>
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                            
                                            <?php
                                            // Show page numbers with a limit of 5 pages
                                            $startPage = max(1, $page - 2);
                                            $endPage = min($totalPages, $startPage + 4);
                                            if ($endPage - $startPage < 4) {
                                                $startPage = max(1, $endPage - 4);
                                            }
                                            
                                            for ($i = $startPage; $i <= $endPage; $i++): 
                                            ?>
                                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                    <a class="page-link" href="?page=<?php echo $i; ?><?php 
                                                        echo !empty($filter_roll) ? '&filter_roll='.urlencode($filter_roll) : ''; 
                                                        echo !empty($filter_name) ? '&filter_name='.urlencode($filter_name) : '';
                                                        echo !empty($filter_class) ? '&filter_class='.$filter_class : '';
                                                        echo !empty($filter_section) ? '&filter_section='.urlencode($filter_section) : '';
                                                    ?>"><?php echo $i; ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            
                                            <?php if ($page < $totalPages): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?php echo $page + 1; ?><?php 
                                                        echo !empty($filter_roll) ? '&filter_roll='.urlencode($filter_roll) : ''; 
                                                        echo !empty($filter_name) ? '&filter_name='.urlencode($filter_name) : '';
                                                        echo !empty($filter_class) ? '&filter_class='.$filter_class : '';
                                                        echo !empty($filter_section) ? '&filter_section='.urlencode($filter_section) : '';
                                                    ?>">
                                                        <i class="material-icons">chevron_right</i>
                                                    </a>
                                                </li>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?php echo $totalPages; ?><?php 
                                                        echo !empty($filter_roll) ? '&filter_roll='.urlencode($filter_roll) : ''; 
                                                        echo !empty($filter_name) ? '&filter_name='.urlencode($filter_name) : '';
                                                        echo !empty($filter_class) ? '&filter_class='.$filter_class : '';
                                                        echo !empty($filter_section) ? '&filter_section='.urlencode($filter_section) : '';
                                                    ?>">
                                                        <i class="material-icons">last_page</i>
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                        <div class="text-muted">
                                            Showing <?php echo min(($page - 1) * $recordsPerPage + 1, $totalRecords); ?> to <?php echo min($page * $recordsPerPage, $totalRecords); ?> of <?php echo $totalRecords; ?> students
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Confirm Delete</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete this student?</p>
                    </div>
                    <div class="modal-footer">
                        <form method="POST" action="manage_students.php">
                            <input type="hidden" name="rollnumber" id="deleteRollNumber">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                            <button type="submit" name="delete_student" class="btn btn-danger">Delete</button>
                        </form>
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
    
    <!-- Debug Test for get_sections.php -->
    <script>
        // Immediately test get_sections.php on page load
        $(function() {
            console.log("Testing get_sections.php connection...");
            // Test with class_id 1 (first class)
            fetch('get_sections.php?class_id=1')
                .then(response => {
                    console.log("Test Response Status:", response.status);
                    return response.text();
                })
                .then(text => {
                    console.log("Raw response from get_sections.php:", text);
                    try {
                        const data = JSON.parse(text);
                        console.log("Parsed data:", data);
                    } catch (e) {
                        console.error("Error parsing response:", e);
                    }
                })
                .catch(error => {
                    console.error("Error testing get_sections.php:", error);
                });
        });
    </script>
    
    <script>
        // Function to edit student
        function editStudent(rollnumber, name, email, class_id, section) {
            console.log("Edit student called with:", {rollnumber, name, email, class_id, section});
            
            // Set basic form fields
            document.getElementById('rollnumber').value = rollnumber;
            document.getElementById('rollnumber').readOnly = true;
            document.getElementById('name').value = name;
            document.getElementById('email').value = email;
            
            // Set class dropdown value using Select2
            if (class_id) {
                $('#class_id').val(class_id).trigger('change');
                console.log("Set class_id to:", class_id);
                
                // Keep the section value for later use
                window.pendingSection = section;
                
                // Listen once for the loadSections completion
                const originalFunc = window.loadSections;
                window.loadSections = function() {
                    console.log("Intercepted loadSections call");
                    originalFunc();
                    
                    // Restore original function
                    window.loadSections = originalFunc;
                    
                    // Set section value after a delay to ensure sections are loaded
                    setTimeout(() => {
                        // By now sections should be loaded
                        if (window.pendingSection) {
                            console.log("Setting pending section to:", window.pendingSection);
                            
                            // Try to find the section in the dropdown first
                            let sectionFound = false;
                            $('#section option').each(function() {
                                if ($(this).val() === window.pendingSection) {
                                    sectionFound = true;
                                    return false; // break the loop
                                }
                            });
                            
                            // If not found and we have a value, create it
                            if (!sectionFound && window.pendingSection) {
                                console.log("Creating new section option:", window.pendingSection);
                                const newOption = new Option(window.pendingSection, window.pendingSection, true, true);
                                $('#section').append(newOption);
                            }
                            
                            // Set the value
                            $('#section').val(window.pendingSection).trigger('change');
                            
                            // Reset the pending section
                            window.pendingSection = null;
                        }
                    }, 1000);
                };
            } else {
                // If no class ID, just reset section
                $('#section').val('').trigger('change');
            }
            
            // Update form action
            document.getElementById('password').required = false;
            document.getElementById('formAction').value = 'edit';
            
            // Change form button
            const submitBtn = document.querySelector('button[type="submit"]');
            submitBtn.innerHTML = 'Update Student';
            submitBtn.name = 'edit_student';
            
            // Scroll to form
            document.querySelector('.card').scrollIntoView({ behavior: 'smooth' });
        }

        // Function to delete student
        function deleteStudent(rollnumber) {
            document.getElementById('deleteRollNumber').value = rollnumber;
            $('#deleteModal').modal('show');
        }

        // Reset form when adding new student
        document.querySelector('.card-header').addEventListener('click', function() {
            document.getElementById('studentForm').reset();
            document.getElementById('rollnumber').readOnly = false;
            document.getElementById('password').required = true;
            document.getElementById('formAction').value = 'add';
            
            const submitBtn = document.querySelector('button[type="submit"]');
            submitBtn.innerHTML = 'Add Student';
            submitBtn.name = 'add_student';
        });

                // Function to load sections based on selected class
        function loadSections() {
            var classId = $('#class_id').val();
            console.log("Loading sections for class ID:", classId);
            
            // Get DOM elements
            var sectionSelect = document.getElementById('section');
            var loadingIndicator = document.getElementById('section-loading-indicator');
            var errorIndicator = document.getElementById('section-error-indicator');
            
            // Reset indicators
            loadingIndicator.style.display = 'none';
            errorIndicator.style.display = 'none';
            
            if(classId) {
                // Show loading state
                loadingIndicator.style.display = 'block';
                sectionSelect.disabled = true;
                
                // Show debugging information in console
                console.log("Fetching from:", 'get_sections.php?class_id=' + classId);
                
                // First show a loading option
                sectionSelect.innerHTML = '<option value="">Loading sections...</option>';
                
                // Add cache buster to prevent caching issues
                var url = 'get_sections.php?class_id=' + classId + '&_=' + new Date().getTime();
                
                fetch(url)
                    .then(response => {
                        console.log("Response status:", response.status);
                        if (!response.ok) {
                            throw new Error("HTTP error " + response.status);
                        }
                        return response.text().then(text => {
                            try {
                                // Try to parse as JSON
                                if (text.trim() === '') {
                                    console.warn("Empty response from server");
                                    return [];
                                }
                                return JSON.parse(text);
                            } catch (e) {
                                // If parsing fails, log the raw text
                                console.error("Failed to parse response:", text);
                                throw new Error("Invalid JSON response: " + text);
                            }
                        });
                    })
                    .then(data => {
                        // Hide loading
                        loadingIndicator.style.display = 'none';
                        sectionSelect.disabled = false;
                        
                        console.log("Sections data received:", data);
                        
                        // Reset select
                        sectionSelect.innerHTML = '<option value="">Select Section</option>';
                        
                        // Add new options from API
                        if (Array.isArray(data) && data.length > 0) {
                            data.forEach(function(section) {
                                console.log("Adding section:", section);
                                var option = document.createElement('option');
                                option.value = section.section_name;
                                option.textContent = section.section_name;
                                option.setAttribute('data-section-id', section.id);
                                sectionSelect.appendChild(option);
                            });
                            
                            // Show success message
                            console.log("Successfully loaded", data.length, "sections");
                        } else {
                            console.log("No sections found for this class");
                            // If no sections, add a create new option
                            sectionSelect.innerHTML = '<option value="">No sections found - you can create one</option>';
                        }
                        
                        // Initialize/refresh Select2
                        $(sectionSelect).select2({
                            placeholder: "Select Section",
                            allowClear: true,
                            width: '100%',
                            tags: true
                        });
                    })
                    .catch(error => {
                        // Hide loading, show error
                        loadingIndicator.style.display = 'none';
                        errorIndicator.style.display = 'block';
                        sectionSelect.disabled = false;
                        
                        console.error("Error loading sections:", error);
                        sectionSelect.innerHTML = '<option value="">Error loading sections</option>';
                        
                        // Initialize Select2 anyway
                        $(sectionSelect).select2({
                            placeholder: "Error loading sections",
                            allowClear: true,
                            width: '100%',
                            tags: true
                        });
                    });
            } else {
                // Reset select if no class is selected
                sectionSelect.innerHTML = '<option value="">First select a class</option>';
                sectionSelect.disabled = true;
                
                $(sectionSelect).select2({
                    placeholder: "First select a class",
                    allowClear: true,
                    width: '100%',
                    tags: true
                });
            }
        }                // Initialize Select2 on dropdowns
        $(document).ready(function() {
            console.log("Document ready, initializing dropdowns");
            
            // Initialize the class dropdown with Select2
            $('#class_id').select2({
                placeholder: "Select Class",
                allowClear: true,
                width: '100%'
            });
            
            // Initialize the section dropdown with Select2
            $('#section').select2({
                placeholder: "Select Section",
                allowClear: true,
                width: '100%',
                tags: true // This allows creating new options if they don't exist
            });
            
            // Initialize the filter class dropdown with Select2
            $('select[name="filter_class"]').select2({
                placeholder: "All Classes",
                allowClear: true,
                width: '100%'
            });
            
            // Add event listener for class changes to load sections
            $('#class_id').on('change', function() {
                console.log("Class dropdown changed to:", $(this).val());
                loadSections();
            });
            
            // Trigger loadSections if class is already selected (for edit mode)
            var classId = $('#class_id').val();
            if(classId) {
                console.log("Class already selected in form, loading sections for:", classId);
                loadSections();
            } else {
                // Disable section dropdown if no class is selected
                $('#section').prop('disabled', true);
            }
            
            // Fix for Select2 in modals/dynamically loaded content
            $(document).on('select2:open', () => {
                document.querySelector('.select2-search__field').focus();
            });
            
            // Add diagnostic click handler to section dropdown
            $('#section').on('click', function() {
                if (!$('#class_id').val()) {
                    alert('Please select a class first to load available sections');
                }
            });
            
            // Initialize Select2 for quick section add
            $('#quick_class_id').select2({
                placeholder: "Select Class",
                allowClear: true,
                width: '100%'
            });
            
            // Handle quick section add
            $('#add_section_btn').on('click', function() {
                const classId = $('#quick_class_id').val();
                const sectionName = $('#quick_section_name').val().trim();
                const messageDiv = $('#quick_add_message');
                
                // Validate inputs
                if (!classId) {
                    messageDiv.html('<div class="alert alert-warning">Please select a class.</div>');
                    return;
                }
                
                if (!sectionName) {
                    messageDiv.html('<div class="alert alert-warning">Please enter a section name.</div>');
                    return;
                }
                
                // Show loading state
                messageDiv.html('<div class="alert alert-info">Adding section...</div>');
                $('#add_section_btn').prop('disabled', true);
                
                // Create a FormData object and append the data
                const formData = new FormData();
                formData.append('class_id', classId);
                formData.append('section_name', sectionName);
                formData.append('action', 'quick_add_section');
                
                // Send the request
                fetch('quick_add_section.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        messageDiv.html('<div class="alert alert-success">' + data.message + '</div>');
                        $('#quick_section_name').val(''); // Clear the input
                        
                        // If the same class is selected in the main form, refresh its sections
                        if ($('#class_id').val() === classId) {
                            loadSections();
                        }
                    } else {
                        messageDiv.html('<div class="alert alert-danger">' + data.message + '</div>');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    messageDiv.html('<div class="alert alert-danger">An error occurred. Please try again.</div>');
                })
                .finally(() => {
                    $('#add_section_btn').prop('disabled', false);
                });
            });
        });
    </script>
<script src="./assets/js/dark-mode.js"></script>
<script src="./assets/js/sidebar.js"></script>
</body>
</html>
