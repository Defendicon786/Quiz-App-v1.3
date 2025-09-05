<?php
  session_start();
  if(!isset($_SESSION["instructorloggedin"]) || $_SESSION["instructorloggedin"] !== true){
      header("location: instructorlogin.php");
      exit;
  }
  
  include("database.php");
  
  // Process notification creation
  if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_notification'])) {
    $title = $_POST['title'];
    $message = $_POST['message'];
    $class_id = $_POST['class_id'];
    $section_id = !empty($_POST['section_id']) ? $_POST['section_id'] : NULL;
    $instructor_email = $_SESSION['email'];
    
    $sql = "INSERT INTO notifications (class_id, section_id, title, message, created_by) 
            VALUES (?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisss", $class_id, $section_id, $title, $message, $instructor_email);
    
    if ($stmt->execute()) {
      $success_message = "Notification created successfully!";
    } else {
      $error_message = "Error: " . $stmt->error;
    }
    
    $stmt->close();
  }
  
  // Delete notification
  if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_notification'])) {
    $notification_id = $_POST['notification_id'];
    
    $sql = "DELETE FROM notifications WHERE notification_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $notification_id);
    
    if ($stmt->execute()) {
      $success_message = "Notification deleted successfully!";
    } else {
      $error_message = "Error: " . $stmt->error;
    }
    
    $stmt->close();
  }
  
  // Toggle notification status
  if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['toggle_status'])) {
    $notification_id = $_POST['notification_id'];
    $status = $_POST['status'];
    $new_status = $status == 1 ? 0 : 1;
    
    $sql = "UPDATE notifications SET is_active = ? WHERE notification_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $new_status, $notification_id);
    
    if ($stmt->execute()) {
      $success_message = "Notification status updated successfully!";
    } else {
      $error_message = "Error: " . $stmt->error;
    }
    
    $stmt->close();
  }
  
  // Get classes for dropdown
  $class_query = "SELECT * FROM classes ORDER BY class_name";
  $class_result = $conn->query($class_query);
  
  // Get existing notifications
  $notification_query = "SELECT n.*, c.class_name, cs.section_name 
                        FROM notifications n
                        LEFT JOIN classes c ON n.class_id = c.class_id
                        LEFT JOIN class_sections cs ON n.section_id = cs.id
                        ORDER BY n.created_at DESC";
  $notification_result = $conn->query($notification_query);
  
  // Check for query execution error
  if (!$notification_result) {
    $error_message = "Error fetching notifications: " . $conn->error;
  }
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <link rel="apple-touch-icon" sizes="76x76" href="./assets/img/apple-icon.png">
  <link rel="icon" type="image/png" href="./assets/img/favicon.png">
  <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
  <title>Manage Notifications - Narowal Public School And College</title>
  <meta content='width=device-width, initial-scale=1.0, shrink-to-fit=no' name='viewport' />
  <!--     Fonts and icons     -->
  <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700|Roboto+Slab:400,700|Material+Icons" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- CSS Files -->
  <link href="./assets/css/material-kit.css?v=2.0.4" rel="stylesheet" />
  <link href="./assets/css/modern.css" rel="stylesheet" />
  <link href="./assets/css/navbar.css" rel="stylesheet" />
  <link href="./assets/css/portal.css" rel="stylesheet" />
  <link href="./assets/css/manage.css" rel="stylesheet" />
  <link href="./assets/css/sidebar.css" rel="stylesheet" />
  <link href="./assets/demo/demo.css" rel="stylesheet" />
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
              <div class="col-md-12 text-center">
                <p class="description">Send important notifications to students based on class and section</p>
              </div>
            </div>
        
            <?php if(isset($success_message)): ?>
              <div class="alert alert-success">
                <div class="container">
                  <div class="alert-icon">
                    <i class="material-icons">check</i>
                  </div>
                  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true"><i class="material-icons">clear</i></span>
                  </button>
                  <b>Success:</b> <?php echo $success_message; ?>
                </div>
              </div>
            <?php endif; ?>

            <?php if(isset($error_message)): ?>
              <div class="alert alert-danger">
                <div class="container">
                  <div class="alert-icon">
                    <i class="material-icons">error_outline</i>
                  </div>
                  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true"><i class="material-icons">clear</i></span>
                  </button>
                  <b>Error:</b> <?php echo $error_message; ?>
                </div>
              </div>
            <?php endif; ?>

            <div class="row">
              <div class="col-md-8 ml-auto mr-auto">
                <div class="card">
                  <div class="card-header card-header-primary">
                    <h4 class="card-title">Create New Notification</h4>
                  </div>
                  <div class="card-body">
                    <form method="post" action="">
                      <div class="row">
                        <div class="col-md-6">
                          <div class="form-group">
                            <label for="title" class="bmd-label-floating">Notification Title</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                          </div>
                        </div>
                        <div class="col-md-3">
                          <div class="form-group">
                            <label for="class_id" class="bmd-label-floating">Select Class</label>
                            <select class="form-control" id="class_id" name="class_id" required>
                              <option value="">Select Class</option>
                              <?php while($class = $class_result->fetch_assoc()): ?>
                                <option value="<?php echo $class['class_id']; ?>"><?php echo $class['class_name']; ?></option>
                              <?php endwhile; ?>
                            </select>
                          </div>
                        </div>
                        <div class="col-md-3">
                          <div class="form-group">
                            <label for="section_id" class="bmd-label-floating">Select Section (Optional)</label>
                            <select class="form-control" id="section_id" name="section_id">
                              <option value="">All Sections</option>
                            </select>
                          </div>
                        </div>
                      </div>
                      <div class="row">
                        <div class="col-md-12">
                          <div class="form-group">
                            <label for="message" class="bmd-label-floating">Notification Message</label>
                            <textarea class="form-control" id="message" name="message" rows="3" required></textarea>
                          </div>
                        </div>
                      </div>
                      <div class="row">
                        <div class="col-md-12 text-right">
                          <button type="submit" name="create_notification" class="btn btn-primary">Send Notification</button>
                        </div>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-12">
                <div class="card">
                  <div class="card-header card-header-primary">
                    <h4 class="card-title">Notification History</h4>
                  </div>
                  <div class="card-body">
                    <div class="table-responsive notification-table">
                      <table class="table">
                        <thead>
                          <tr>
                            <th>#</th>
                            <th>Title</th>
                            <th>Message</th>
                            <th>Class</th>
                            <th>Section</th>
                            <th>Created At</th>
                            <th>Status</th>
                            <th>Actions</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php if($notification_result && $notification_result->num_rows > 0): ?>
                            <?php while($notification = $notification_result->fetch_assoc()): ?>
                              <tr>
                                <td><?php echo $notification['notification_id']; ?></td>
                                <td><?php echo htmlspecialchars($notification['title']); ?></td>
                                <td class="notification-message">
                                  <?php echo htmlspecialchars(substr($notification['message'], 0, 50)) . (strlen($notification['message']) > 50 ? '...' : ''); ?>
                                  <div class="tooltip-content"><?php echo htmlspecialchars($notification['message']); ?></div>
                                </td>
                                <td><?php echo $notification['class_name']; ?></td>
                                <td><?php echo $notification['section_name'] ? $notification['section_name'] : 'All Sections'; ?></td>
                                <td><?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?></td>
                                <td>
                                  <form method="post" action="" style="display: inline;">
                                    <input type="hidden" name="notification_id" value="<?php echo $notification['notification_id']; ?>">
                                    <input type="hidden" name="status" value="<?php echo $notification['is_active']; ?>">
                                    <button type="submit" name="toggle_status" class="btn btn-link p-0 status-toggle">
                                      <?php if($notification['is_active'] == 1): ?>
                                        <span class="active-badge">Active</span>
                                      <?php else: ?>
                                        <span class="inactive-badge">Inactive</span>
                                      <?php endif; ?>
                                    </button>
                                  </form>
                                </td>
                                <td>
                                  <form method="post" action="" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this notification?');">
                                    <input type="hidden" name="notification_id" value="<?php echo $notification['notification_id']; ?>">
                                    <button type="submit" name="delete_notification" class="btn btn-danger btn-sm">
                                      <i class="material-icons">delete</i>
                                    </button>
                                  </form>
                                </td>
                              </tr>
                            <?php endwhile; ?>
                          <?php else: ?>
                            <tr>
                              <td colspan="8" class="text-center">No notifications found</td>
                            </tr>
                          <?php endif; ?>
                        </tbody>
                      </table>
                    </div>
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
  <script src="./assets/js/plugins/bootstrap-datetimepicker.js" type="text/javascript"></script>
  <script src="./assets/js/material-kit.js?v=2.0.4" type="text/javascript"></script>
  
  <script>
    $(document).ready(function() {
      // When class changes, fetch sections
      $('#class_id').change(function() {
        var classId = $(this).val();
        if (classId != '') {
          $.ajax({
            url: 'get_sections.php',
            type: 'post',
            data: {class_id: classId},
            dataType: 'json',
            success: function(response) {
              var len = response.length;
              $('#section_id').empty();
              $('#section_id').append('<option value="">All Sections</option>');
              for (var i = 0; i < len; i++) {
                $('#section_id').append('<option value="' + response[i].id + '">' + 
                  response[i].section_name + '</option>');
              }
            }
          });
        } else {
          $('#section_id').empty();
          $('#section_id').append('<option value="">All Sections</option>');
        }
      });
    });
  </script>
<script src="./assets/js/dark-mode.js"></script>
<script src="./assets/js/sidebar.js"></script>
</body>
</html>
