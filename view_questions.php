<?php
  session_start();
  if(!isset($_SESSION["instructorloggedin"]) || $_SESSION["instructorloggedin"] !== true){
      header("location: instructorlogin.php");
      exit;
  }
  include "database.php"; // For database connection
  
  // Initialize filter variables
  $filter_class_id = isset($_GET['filter_class']) ? intval($_GET['filter_class']) : 0;
  $filter_subject_id = isset($_GET['filter_subject']) ? intval($_GET['filter_subject']) : 0;
  $filter_chapter_id = isset($_GET['filter_chapter']) ? intval($_GET['filter_chapter']) : 0;
  $filter_topic_id = isset($_GET['filter_topic']) ? intval($_GET['filter_topic']) : 0;

  // Pagination variables
  $records_per_page = 10;
  $current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
  $offset = ($current_page - 1) * $records_per_page;

  // Fetch classes for filter
  $classes_query = "SELECT class_id, class_name FROM classes ORDER BY class_name";
  $classes_result = $conn->query($classes_query);
  $classes = [];
  while ($row = $classes_result->fetch_assoc()) {
    $classes[] = $row;
  }

  // Fetch subjects for filter
  $subjects_query = "SELECT subject_id, subject_name FROM subjects ORDER BY subject_name";
  $subjects_result = $conn->query($subjects_query);
  $subjects = [];
  while ($row = $subjects_result->fetch_assoc()) {
    $subjects[] = $row;
  }

  // Fetch chapters if class and subject are selected
  $chapters = [];
  if ($filter_class_id && $filter_subject_id) {
    $chapters_query = "SELECT chapter_id, chapter_name FROM chapters
                      WHERE class_id = ? AND subject_id = ?
                      ORDER BY chapter_name";
    $stmt = $conn->prepare($chapters_query);
    $stmt->bind_param("ii", $filter_class_id, $filter_subject_id);
    $stmt->execute();
    $chapters_result = $stmt->get_result();
    while ($row = $chapters_result->fetch_assoc()) {
      $chapters[] = $row;
    }
    $stmt->close();
  }

  // Fetch topics if a chapter is selected
  $topics = [];
  if ($filter_chapter_id) {
    $topics_query = "SELECT topic_id, topic_name FROM topics
                     WHERE chapter_id = ?
                     ORDER BY topic_name";
    $stmt = $conn->prepare($topics_query);
    $stmt->bind_param("i", $filter_chapter_id);
    $stmt->execute();
    $topics_result = $stmt->get_result();
    while ($row = $topics_result->fetch_assoc()) {
      $topics[] = $row;
    }
    $stmt->close();
  }

  // Handle delete action
  if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['q_type']) && isset($_GET['id'])) {
    $q_type = $_GET['q_type'];
    $id = intval($_GET['id']);
    
    // Map question type to table name
    $delete_table_map = [
      'mcq' => 'mcqdb',
      'numerical' => 'numericaldb',
      'dropdown' => 'dropdown',
      'fillintheblanks' => 'fillintheblanks',
      'shortanswer' => 'shortanswer',
      'essay' => 'essay'
    ];
    
    if (array_key_exists($q_type, $delete_table_map)) {
      $table_name = $delete_table_map[$q_type];
      $delete_sql = "DELETE FROM " . $conn->real_escape_string($table_name) . " WHERE id = ?";
      
      if ($stmt = $conn->prepare($delete_sql)) {
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
          $stmt->close();
          header("Location: view_questions.php?q_type=" . urlencode($q_type) . "&deleted=1");
          exit;
        } else {
          $stmt->close();
          header("Location: view_questions.php?q_type=" . urlencode($q_type) . "&error=delete_failed");
          exit;
        }
      }
    }
  }

  // Show success/error messages
  $message = "";
  if (isset($_GET['deleted']) && $_GET['deleted'] == '1') {
    $message = '<div class="alert alert-success text-center">Question deleted successfully!</div>';
  } elseif (isset($_GET['error']) && $_GET['error'] == 'delete_failed') {
    $message = '<div class="alert alert-danger text-center">Failed to delete question. Please try again.</div>';
  }
  
  $selected_q_type = isset($_GET['q_type']) ? $_GET['q_type'] : 'mcq'; // Default to MCQ
  $questions_html = ""; // To store HTML for questions table
  $total_records = 0; // For pagination

  // Define table names for different question types
  $table_map = [
    'mcq' => 'mcqdb',
    'numerical' => 'numericaldb',
    'dropdown' => 'dropdown',
    'fillintheblanks' => 'fillintheblanks',
    'shortanswer' => 'shortanswer',
    'essay' => 'essay'
  ];

  if (array_key_exists($selected_q_type, $table_map)) {
    $table_name = $table_map[$selected_q_type];
    
    // First, count total records for pagination
    $count_sql = "SELECT COUNT(*) as total
                  FROM " . $conn->real_escape_string($table_name) . " q
                  LEFT JOIN chapters c ON q.chapter_id = c.chapter_id
                  LEFT JOIN topics t ON q.topic_id = t.topic_id
                  LEFT JOIN classes cl ON c.class_id = cl.class_id
                  LEFT JOIN subjects s ON c.subject_id = s.subject_id
                  WHERE 1=1";
    
    $params_count = [];
    $param_types_count = "";
    
    if ($filter_class_id) {
      $count_sql .= " AND cl.class_id = ?";
      $params_count[] = $filter_class_id;
      $param_types_count .= "i";
    }
    if ($filter_subject_id) {
      $count_sql .= " AND c.subject_id = ?";
      $params_count[] = $filter_subject_id;
      $param_types_count .= "i";
    }
    if ($filter_chapter_id) {
      $count_sql .= " AND c.chapter_id = ?";
      $params_count[] = $filter_chapter_id;
      $param_types_count .= "i";
    }
    if ($filter_topic_id) {
      $count_sql .= " AND t.topic_id = ?";
      $params_count[] = $filter_topic_id;
      $param_types_count .= "i";
    }
    
    if (!empty($params_count)) {
      $stmt_count = $conn->prepare($count_sql);
      $stmt_count->bind_param($param_types_count, ...$params_count);
      $stmt_count->execute();
      $result_count = $stmt_count->get_result();
      $row_count = $result_count->fetch_assoc();
      $total_records = $row_count['total'];
      $stmt_count->close();
    } else {
      $result_count = $conn->query($count_sql);
      $row_count = $result_count->fetch_assoc();
      $total_records = $row_count['total'];
    }
    
    // Calculate total pages for pagination
    $total_pages = ceil($total_records / $records_per_page);
    
    // Fetch questions with pagination
    $sql_fetch_questions = "SELECT q.*, c.chapter_name, t.topic_name, cl.class_name, s.subject_name
                           FROM " . $conn->real_escape_string($table_name) . " q
                           LEFT JOIN chapters c ON q.chapter_id = c.chapter_id
                           LEFT JOIN topics t ON q.topic_id = t.topic_id
                           LEFT JOIN classes cl ON c.class_id = cl.class_id
                           LEFT JOIN subjects s ON c.subject_id = s.subject_id
                           WHERE 1=1";
    
    $params = [];
    $param_types = "";
    
    if ($filter_class_id) {
      $sql_fetch_questions .= " AND cl.class_id = ?";
      $params[] = $filter_class_id;
      $param_types .= "i";
    }
    if ($filter_subject_id) {
      $sql_fetch_questions .= " AND c.subject_id = ?";
      $params[] = $filter_subject_id;
      $param_types .= "i";
    }
    if ($filter_chapter_id) {
      $sql_fetch_questions .= " AND c.chapter_id = ?";
      $params[] = $filter_chapter_id;
      $param_types .= "i";
    }
    if ($filter_topic_id) {
      $sql_fetch_questions .= " AND t.topic_id = ?";
      $params[] = $filter_topic_id;
      $param_types .= "i";
    }
    
    $sql_fetch_questions .= " ORDER BY q.id DESC LIMIT ?, ?";

    if (!empty($params)) {
      $stmt = $conn->prepare($sql_fetch_questions);
      $params[] = $offset;
      $params[] = $records_per_page;
      $param_types .= "ii";
      $stmt->bind_param($param_types, ...$params);
      $stmt->execute();
      $result_questions = $stmt->get_result();
    } else {
      // For prepared statement with only LIMIT parameters
      $stmt = $conn->prepare($sql_fetch_questions);
      $stmt->bind_param("ii", $offset, $records_per_page);
      $stmt->execute();
      $result_questions = $stmt->get_result();
    }

    if ($result_questions && $result_questions->num_rows > 0) {
        $questions_html .= "<table class='table table-striped table-hover'>";
        // Header row - varies by question type
        if ($selected_q_type == 'mcq') {
            $questions_html .= "<thead><tr><th>S.No</th><th>Question</th><th>Option A</th><th>Option B</th><th>Option C</th><th>Option D</th><th>Correct Answer</th><th>Class</th><th>Chapter</th><th>Topic</th><th>Actions</th></tr></thead>";
        } elseif ($selected_q_type == 'numerical') {
            $questions_html .= "<thead><tr><th>S.No</th><th>Question</th><th>Correct Answer</th><th>Class</th><th>Chapter</th><th>Topic</th><th>Actions</th></tr></thead>";
        } else {
            $questions_html .= "<thead><tr><th>S.No</th><th>Question</th>";
            if ($selected_q_type == 'dropdown') {
                $questions_html .= "<th>Options</th><th>Correct Option</th>";
            }
            $questions_html .= "<th>Class</th><th>Chapter</th><th>Topic</th><th>Actions</th></tr></thead>";
        }
        
        $questions_html .= "<tbody>";
        $serial_number = $offset + 1; // Start serial number from current page offset
        while ($row = $result_questions->fetch_assoc()) {
            $questions_html .= "<tr>";
            $questions_html .= "<td>" . $serial_number . "</td>"; // Serial number instead of ID
            $questions_html .= "<td>" . htmlspecialchars($row['question']) . "</td>";

            if ($selected_q_type == 'mcq') {
                $questions_html .= "<td>" . htmlspecialchars($row['optiona']) . "</td>";
                $questions_html .= "<td>" . htmlspecialchars($row['optionb']) . "</td>";
                $questions_html .= "<td>" . htmlspecialchars($row['optionc']) . "</td>";
                $questions_html .= "<td>" . htmlspecialchars($row['optiond']) . "</td>";
                $questions_html .= "<td>" . htmlspecialchars($row['answer']) . "</td>";
            } elseif ($selected_q_type == 'numerical') {
                $questions_html .= "<td>" . htmlspecialchars($row['answer']) . "</td>";
            } elseif ($selected_q_type == 'dropdown') {
                $questions_html .= "<td>" . htmlspecialchars($row['options']) . "</td>";
                $questions_html .= "<td>" . htmlspecialchars($row['answer']) . "</td>";
            }

            // Add class, chapter and topic columns for all question types
            $questions_html .= "<td>" . htmlspecialchars($row['class_name'] ?? 'N/A') . "</td>";
            $questions_html .= "<td>" . htmlspecialchars($row['chapter_name'] ?? 'N/A') . "</td>";
            $questions_html .= "<td>" . htmlspecialchars($row['topic_name'] ?? 'N/A') . "</td>";
            
            $questions_html .= "<td>";
            $questions_html .= "<a href='questionfeed.php?action=edit&q_type=" . htmlspecialchars($selected_q_type) . "&id=" . htmlspecialchars($row['id']) . "' class='btn btn-info btn-sm' style='margin-right: 5px;'>Edit</a>";
            $questions_html .= "<a href='view_questions.php?action=delete&q_type=" . htmlspecialchars($selected_q_type) . "&id=" . htmlspecialchars($row['id']) . "' class='btn btn-danger btn-sm' onclick=\"return confirm('Are you sure you want to delete this question ID: " . htmlspecialchars($row['id']) . "?');\">Delete</a>";
            $questions_html .= "</td>";
            $questions_html .= "</tr>";
            $serial_number++; // Increment serial number
        }
        $questions_html .= "</tbody></table>";
        
        // Add pagination if there are multiple pages
        if ($total_pages > 1) {
            $questions_html .= '<nav aria-label="Questions pagination"><ul class="pagination justify-content-center">';
            
            // Previous page link
            if ($current_page > 1) {
                $prev_page_url = http_build_query(array_merge($_GET, ['page' => $current_page - 1]));
                $questions_html .= '<li class="page-item"><a class="page-link" href="?' . $prev_page_url . '"><i class="material-icons">chevron_left</i></a></li>';
            } else {
                $questions_html .= '<li class="page-item disabled"><a class="page-link" href="#"><i class="material-icons">chevron_left</i></a></li>';
            }
            
            // Page numbers
            $start_page = max(1, $current_page - 2);
            $end_page = min($total_pages, $current_page + 2);
            
            if ($start_page > 1) {
                $first_page_url = http_build_query(array_merge($_GET, ['page' => 1]));
                $questions_html .= '<li class="page-item"><a class="page-link" href="?' . $first_page_url . '">1</a></li>';
                if ($start_page > 2) {
                    $questions_html .= '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                }
            }
            
            for ($i = $start_page; $i <= $end_page; $i++) {
                $page_url = http_build_query(array_merge($_GET, ['page' => $i]));
                if ($i == $current_page) {
                    $questions_html .= '<li class="page-item active"><a class="page-link" href="#">' . $i . '</a></li>';
                } else {
                    $questions_html .= '<li class="page-item"><a class="page-link" href="?' . $page_url . '">' . $i . '</a></li>';
                }
            }
            
            if ($end_page < $total_pages) {
                if ($end_page < $total_pages - 1) {
                    $questions_html .= '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                }
                $last_page_url = http_build_query(array_merge($_GET, ['page' => $total_pages]));
                $questions_html .= '<li class="page-item"><a class="page-link" href="?' . $last_page_url . '">' . $total_pages . '</a></li>';
            }
            
            // Next page link
            if ($current_page < $total_pages) {
                $next_page_url = http_build_query(array_merge($_GET, ['page' => $current_page + 1]));
                $questions_html .= '<li class="page-item"><a class="page-link" href="?' . $next_page_url . '"><i class="material-icons">chevron_right</i></a></li>';
            } else {
                $questions_html .= '<li class="page-item disabled"><a class="page-link" href="#"><i class="material-icons">chevron_right</i></a></li>';
            }
            
            $questions_html .= '</ul></nav>';
            
            // Show records info
            $questions_html .= '<div class="text-center text-muted mb-4">';
            $questions_html .= 'Showing ' . ($offset + 1) . ' to ' . min($offset + $records_per_page, $total_records) . ' of ' . $total_records . ' records';
            $questions_html .= '</div>';
        }
    } else {
        $questions_html = "<p class='text-center'>No questions found for this type. Table: " . htmlspecialchars($table_name) . "</p>";
    }
  } else {
      $questions_html = "<p class='text-center text-danger'>Invalid question type selected: " . htmlspecialchars($selected_q_type) . "</p>";
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
  <title>View Questions</title>
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
          <div class="row">
            <div class="col-md-12">
              <div class="card">
                <div class="card-header card-header-primary">
                  <h4 class="card-title">View Questions</h4>
                  <p class="card-category">Manage your question bank</p>
                </div>
                <div class="card-body">
                  <?php echo $message; ?>
                  
                  <div class="question-type-nav text-center">
                    <a href="?q_type=mcq" class="btn <?php echo $selected_q_type === 'mcq' ? 'btn-primary' : 'btn-outline-primary'; ?>">MCQ</a>
                    <a href="?q_type=numerical" class="btn <?php echo $selected_q_type === 'numerical' ? 'btn-primary' : 'btn-outline-primary'; ?>">Numerical</a>
                    <a href="?q_type=dropdown" class="btn <?php echo $selected_q_type === 'dropdown' ? 'btn-primary' : 'btn-outline-primary'; ?>">Dropdown</a>
                    <a href="?q_type=fillintheblanks" class="btn <?php echo $selected_q_type === 'fillintheblanks' ? 'btn-primary' : 'btn-outline-primary'; ?>">Fill in Blanks</a>
                    <a href="?q_type=shortanswer" class="btn <?php echo $selected_q_type === 'shortanswer' ? 'btn-primary' : 'btn-outline-primary'; ?>">Short Answer</a>
                    <a href="?q_type=essay" class="btn <?php echo $selected_q_type === 'essay' ? 'btn-primary' : 'btn-outline-primary'; ?>">Essay</a>
                  </div>

                  <div class="filter-section card-body">
                    <form method="GET" class="row align-items-end" id="filterForm">
                      <input type="hidden" name="q_type" value="<?php echo htmlspecialchars($selected_q_type); ?>">
                      
                      <div class="col-md-2">
                        <div class="form-group">
                          <label for="filter_class">Class</label>
                          <select class="form-control" id="filter_class" name="filter_class" onchange="loadChapters()">
                            <option value="0">All Classes</option>
                            <?php foreach ($classes as $class): ?>
                              <option value="<?php echo $class['class_id']; ?>" 
                                <?php echo $filter_class_id == $class['class_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['class_name']); ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                      </div>

                      <div class="col-md-2">
                        <div class="form-group">
                          <label for="filter_subject">Subject</label>
                          <select class="form-control" id="filter_subject" name="filter_subject" onchange="loadChapters()">
                            <option value="0">All Subjects</option>
                            <?php foreach ($subjects as $subject): ?>
                              <option value="<?php echo $subject['subject_id']; ?>"
                                <?php echo $filter_subject_id == $subject['subject_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subject['subject_name']); ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                      </div>

                      <div class="col-md-2">
                        <div class="form-group">
                          <label for="filter_chapter">Chapter</label>
                          <select class="form-control" id="filter_chapter" name="filter_chapter" onchange="loadTopics()">
                            <option value="0">All Chapters</option>
                            <?php foreach ($chapters as $chapter): ?>
                              <option value="<?php echo $chapter['chapter_id']; ?>"
                                <?php echo $filter_chapter_id == $chapter['chapter_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($chapter['chapter_name']); ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                      </div>

                      <div class="col-md-2">
                        <div class="form-group">
                          <label for="filter_topic">Topic</label>
                          <select class="form-control" id="filter_topic" name="filter_topic">
                            <option value="0">All Topics</option>
                            <?php foreach ($topics as $topic): ?>
                              <option value="<?php echo $topic['topic_id']; ?>"
                                <?php echo $filter_topic_id == $topic['topic_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($topic['topic_name']); ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                      </div>

                      <div class="col-md-2">
                        <div class="form-group">
                          <button type="submit" class="btn btn-primary">
                            <i class="material-icons">filter_list</i> Apply Filters
                          </button>
                          <a href="?q_type=<?php echo htmlspecialchars($selected_q_type); ?>" class="btn btn-outline-secondary">
                            <i class="material-icons">clear</i> Clear
                          </a>
                        </div>
                      </div>
                    </form>
                  </div>

                  <div class="table-responsive-wrapper">
                    <?php
                      // Modify the table HTML to include responsive classes
                      $questions_html = str_replace('<table class=\'table', '<table class=\'table table-responsive', $questions_html);
                      $questions_html = str_replace('class=\'btn', 'class=\'btn btn-sm', $questions_html);
                      
                      // Wrap action buttons in a div
                      $questions_html = preg_replace(
                        '/<td>(<a.*?<\/a>.*?<\/a>)<\/td>/',
                        '<td><div class="action-buttons">$1</div></td>',
                        $questions_html
                      );
                      
                      echo $questions_html;
                    ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div> <!-- End Wrapper -->

  <!--   Core JS Files   -->
  <script src="./assets/js/core/jquery.min.js" type="text/javascript"></script>
  <script src="./assets/js/core/popper.min.js" type="text/javascript"></script>
  <script src="./assets/js/core/bootstrap-material-design.min.js" type="text/javascript"></script>
  <script src="./assets/js/plugins/moment.min.js"></script>
  <script src="./assets/js/plugins/bootstrap-datetimepicker.js" type="text/javascript"></script>
  <script src="./assets/js/plugins/nouislider.min.js" type="text/javascript"></script>
  <script src="./assets/js/material-kit.js?v=2.0.4" type="text/javascript"></script>
  
  <script type="text/javascript">
  function loadChapters() {
      var classId = $('#filter_class').val();
      var subjectId = $('#filter_subject').val();
      var chapterSelect = $('#filter_chapter');
      var topicSelect = $('#filter_topic');
      
      console.log('loadChapters called - Class ID:', classId, 'Subject ID:', subjectId);
      
      // Clear existing options except the first one
      chapterSelect.find('option:not(:first)').remove();
      topicSelect.find('option:not(:first)').remove();
      
      // Only load chapters if both class and subject are selected
      if (classId > 0 && subjectId > 0) {
        console.log('Fetching chapters for Class ID:', classId, 'Subject ID:', subjectId);
        
        // Show loading indicator
        chapterSelect.prop('disabled', true);
        chapterSelect.append($('<option>', {
          text: 'Loading chapters...',
          disabled: true,
          selected: true
        }));
        
        $.ajax({
          url: 'get_chapters.php',
          type: 'GET',
          data: {
            class_id: classId,
            subject_id: subjectId
          },
          dataType: 'json',
          beforeSend: function() {
            console.log('Sending AJAX request to get_chapters.php');
          },
          success: function(response) {
            console.log('AJAX response received:', response);
            
            // Clear the loading indicator
            chapterSelect.find('option:not(:first)').remove();
            chapterSelect.prop('disabled', false);
            
            // Check if we received an error
            if (response.error) {
              console.error('Server returned error:', response.error);
              chapterSelect.append($('<option>', {
                text: 'Error loading chapters: ' + response.error,
                disabled: true
              }));
              return;
            }
            
            // Check if response is empty array or not an array
            if (!Array.isArray(response) || response.length === 0) {
              console.log('No chapters found or invalid response');
              chapterSelect.append($('<option>', {
                text: 'No chapters found',
                disabled: true
              }));
              return;
            }
            
            // Add chapters to dropdown
            $.each(response, function(i, chapter) {
              chapterSelect.append($('<option>', {
                value: chapter.chapter_id,
                text: chapter.chapter_name
              }));
            });
            console.log('Loaded ' + response.length + ' chapters');
            // After loading chapters, load topics for selected chapter
            loadTopics();
          },
          error: function(xhr, status, error) {
            console.error('AJAX Error:', status, error);
            console.error('Response Text:', xhr.responseText);
            console.error('Status Code:', xhr.status);
            
            // Clear loading and enable select
            chapterSelect.find('option:not(:first)').remove();
            chapterSelect.prop('disabled', false);
            
            // Add error option
            chapterSelect.append($('<option>', {
              text: 'Error loading chapters. Check console.',
              disabled: true
            }));
          }
        });
      } else {
        console.log('Both class and subject must be selected to load chapters');
      }
    }

    function loadTopics() {
      var chapterId = $('#filter_chapter').val();
      var topicSelect = $('#filter_topic');

      topicSelect.find('option:not(:first)').remove();

      if (chapterId > 0) {
        topicSelect.prop('disabled', true);
        topicSelect.append($('<option>', {
          text: 'Loading topics...',
          disabled: true,
          selected: true
        }));

        $.ajax({
          url: 'get_topics.php',
          type: 'GET',
          data: { chapter_id: chapterId },
          dataType: 'json',
          success: function(response) {
            topicSelect.find('option:not(:first)').remove();
            topicSelect.prop('disabled', false);

            if (!Array.isArray(response) || response.length === 0) {
              topicSelect.append($('<option>', { text: 'No topics found', disabled: true }));
              return;
            }

            $.each(response, function(i, topic) {
              topicSelect.append($('<option>', {
                value: topic.topic_id,
                text: topic.topic_name
              }));
            });
          },
          error: function() {
            topicSelect.find('option:not(:first)').remove();
            topicSelect.prop('disabled', false);
            topicSelect.append($('<option>', { text: 'Error loading topics', disabled: true }));
          }
        });
      }
    }

    // Call loadChapters once when the page loads to populate based on initial selection
    $(document).ready(function() {
      console.log('Document ready - checking if we should load chapters');

      // Check if initial values are set
      if ($('#filter_class').val() > 0 && $('#filter_subject').val() > 0) {
        console.log('Initial values set for class and subject, loading chapters');
        loadChapters();
      }
      if ($('#filter_chapter').val() > 0) {
        loadTopics();
      }
    });
  </script>
    </main>
  </div>
</div>
<script src="./assets/js/sidebar.js"></script>
<script src="./assets/js/dark-mode.js"></script>
</body>
</html>
