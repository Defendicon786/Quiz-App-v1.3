<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <link rel="apple-touch-icon" sizes="76x76" href="./assets/img/apple-icon.png">
  <link rel="icon" type="image/png" href="./assets/img/favicon.png">
  <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
  <title>Narowal Public School And College - Student Registration</title>
  <meta content='width=device-width, initial-scale=1.0, shrink-to-fit=no' name='viewport' />
  <!--     Fonts and icons     -->
  <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700|Roboto+Slab:400,700|Material+Icons" />
  <!-- <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/latest/css/font-awesome.min.css"> --> <!-- Replaced by new Font Awesome -->
  <!-- New Links -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- CSS Files -->
  <link href="./assets/css/material-kit.css?v=2.0.4" rel="stylesheet" />
    <link href="./assets/css/modern.css" rel="stylesheet" />
    <link href="./assets/css/navbar.css" rel="stylesheet" />
  <style>
    .card-login {
      max-width: 650px;
      margin: 0 auto;
    }
  </style>
  <script>
  function validate() {
      var passwordA = document.forms["register"]["password"].value; // Renamed to avoid conflict with global password if any
      var passwordB = document.forms["register"]["repassword"].value; // Renamed
      if(passwordA==passwordB)
        return true;
      else{
        alert("Passwords do not match");
        return false;
      }     
  }
  </script>
<link id="dark-mode-style" rel="stylesheet" href="./assets/css/dark-mode.css" />
</head>

<body class="login-page sidebar-collapse">
  <header class="header">
      <div class="header-content">
          <div class="school-name">
              Narowal Public School And College
              <span>Educating The Souls</span>
          </div>
      </div>
  </header>
  <nav class="navbar main-navbar fixed-top navbar-expand-lg" color-on-scroll="100" id="sectionsNav">
    <div class="container">
      <div class="navbar-translate">
        <a class="navbar-brand" href="studentlogin.php">
          Genesis Student's Portal </a>        
      </div>      
    </div>
  </nav>
  <div class="page-header header-filter" style="background-image: url('./assets/img/bg7.jpg'); background-size: cover; background-position: top center;">
    <div class="container">
      <div class="row" style="margin-top: 100px">
        <div class="col-lg-7 col-md-9 ml-auto mr-auto">
          <div class="card card-login">
            <form class="form" name="register" action="studentregister.php" method="post" onsubmit="return validate()">
              <div class="card-header card-header-primary text-center">
                <h4 class="card-title">Register</h4>
              </div>
              <p class="description text-center">If not already registered. Else directly login.</p>
              <div class="card-body">
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text">
                      <i class="material-icons">face</i>
                    </span>
                  </div>
                  <input type="text" class="form-control" name="name" placeholder="Name.." required>
                </div>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text">
                      <i class="material-icons">mail</i>
                    </span>
                  </div>
                  <input type="email" name="email" class="form-control" placeholder="Email.." required>
                </div>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text">
                      <i class="material-icons">format_list_numbered</i>
                    </span>
                  </div>
                  <input type="number" name="rollnumber" class="form-control" placeholder="Roll Number.." required>
                </div>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text">
                      <i class="material-icons">lock_outline</i>
                    </span>
                  </div>
                  <input type="password" name="password" class="form-control" placeholder="Password..">
                </div>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text">
                      <i class="material-icons">lock_outline</i>
                    </span>
                  </div>
                  <input type="password" name="repassword" class="form-control" placeholder="Retype Password..">
                </div>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text">
                      <i class="material-icons">category</i>
                    </span>
                  </div>
                  <?php
                  include "database.php";
                  // Get classes for dropdown
                  $classes = [];
                  $sql_classes = "SELECT class_id, class_name FROM classes ORDER BY class_name ASC";
                  $result_classes = $conn->query($sql_classes);
                  if ($result_classes && $result_classes->num_rows > 0) {
                      while ($row_class = $result_classes->fetch_assoc()) {
                          $classes[] = $row_class;
                      }
                  }
                  ?>
                  <select id="inputClass" name="department" class="form-control" onchange="loadSections()">
                    <option selected>Select Class</option>
                    <?php foreach ($classes as $class): ?>
                    <option value="<?php echo htmlspecialchars($class['class_name']); ?>" data-class-id="<?php echo $class['class_id']; ?>"><?php echo htmlspecialchars($class['class_name']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="input-group" >
                  <div class="input-group-prepend">
                    <span class="input-group-text">
                      <i class="material-icons">school</i>
                    </span>
                  </div>
                  <div class="form-check form-check-radio form-check-inline" style="margin: 0px;padding: 0px">
                    <label class="form-check-label">
                      <input class="form-check-input" type="radio"  id="inlineRadio1" name="program" value="btech" checked> B.Tech.
                      <span class="circle">
                          <span class="check"></span>
                      </span>
                    </label>
                  </div>
                  <div class="form-check form-check-radio form-check-inline" style="margin: 0px;padding: 0px">
                    <label class="form-check-label">
                      <input class="form-check-input" type="radio" id="inlineRadio2" name="program" value="mtech"> M.Tech.
                      <span class="circle">
                          <span class="check"></span>
                      </span>
                    </label>
                  </div>
                  <div class="form-check form-check-radio form-check-inline" style="margin: 0px;padding: 0px">
                    <label class="form-check-label">
                      <input class="form-check-input" type="radio" id="inlineRadio3"  name="program" value="msc"> M.Sc.
                      <span class="circle">
                          <span class="check"></span>
                      </span>
                    </label>
                  </div>
                  <div class="form-check form-check-radio form-check-inline" style="margin: 0px;padding: 0px">
                    <label class="form-check-label">
                      <input class="form-check-input" type="radio" id="inlineRadio3"  name="program" value="phd"> Phd.
                      <span class="circle">
                          <span class="check"></span>
                      </span>
                    </label>
                  </div>
                </div>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text">
                      <i class="material-icons">class</i>
                    </span>
                  </div>
                  <select id="sectionSelect" name="section" class="form-control" required>
                    <option value="">Select Section</option>
                  </select>
                </div>
                
                <script>
                function loadSections() {
                  var classSelect = document.getElementById('inputClass');
                  var selectedOption = classSelect.options[classSelect.selectedIndex];
                  var classId = selectedOption.getAttribute('data-class-id');
                  
                  if(classId) {
                    fetch('get_sections.php?class_id=' + classId)
                      .then(response => response.json())
                      .then(data => {
                        var sectionSelect = document.getElementById('sectionSelect');
                        sectionSelect.innerHTML = '<option value="">Select Section</option>';
                        
                        data.forEach(function(section) {
                          var option = document.createElement('option');
                          option.value = section.section_name;
                          option.textContent = section.section_name;
                          sectionSelect.appendChild(option);
                        });
                      })
                      .catch(error => console.error('Error:', error));
                  }
                }
                </script>
              </div>
              <div class="text-center">
                <a class="btn btn-primary btn-link" href="studentlogin.php" style="margin-left: 30px;margin-top: 30px;margin-right: 30px;margin-bottom: 15px;">Login</a>
                <button type="submit" style="margin-left: 30px;margin-top: 30px;margin-right: 30px;margin-bottom: 15px;" class="btn btn-primary btn-round">Register</button>
              </div>
            </form>
            <?php
              include "database.php";
              $name_val = isset($_POST["name"]) ? $_POST["name"] : null; // Added isset to avoid errors on initial load
              $email_val = isset($_POST["email"]) ? $_POST["email"] : null;
              $rollnumber_val = isset($_POST["rollnumber"]) ? $_POST["rollnumber"] : null;
              $password_val = isset($_POST["password"]) ? $_POST["password"] : null;
              $department_val = isset($_POST["department"]) ? $_POST["department"] : null;
              $program_val = isset($_POST["program"]) ? $_POST["program"] : null;
              $section_val = isset($_POST["section"]) ? $_POST["section"] : null; // Added section

              if ($name_val && $email_val && $rollnumber_val && $password_val && $department_val && $program_val && $section_val) { // Check if all POST variables are set, including section
                $sql = "insert into 
                studentinfo (name,email,rollnumber,password,department,program,section) values 
                ('".$conn->real_escape_string($name_val)."','".$conn->real_escape_string($email_val)."',".$conn->real_escape_string($rollnumber_val).",'".$conn->real_escape_string($password_val)."','".$conn->real_escape_string($department_val)."','".$conn->real_escape_string($program_val)."','".$conn->real_escape_string($section_val)."');";

                if ($conn->query($sql) === TRUE) {
                    echo '<p class="h6 text-center" style="color:green;">Registration Successful</p>';
                } else {
                    echo '<p class="h6 text-center" style="color:red;">Error: ' . $conn->error . '</p>'; // Show SQL error if any
                }
              }
              $conn->close();   
            ?>
          </div>
        </div>
      </div>
    </div>
  </div>  
    <footer class="footer footer-default">
        <div class="container">
            <div class="copyright" style="text-align: center; width: 100%;">
                A Project of StudyHT.com<br>
                Designed and Developed by Sir Hassan Tariq<br>
                &copy;
                <script>
                    document.write(new Date().getFullYear())
                </script>
            </div>
        </div>
    </footer>
  <script src="./assets/js/core/jquery.min.js" type="text/javascript"></script>
  <script src="./assets/js/core/popper.min.js" type="text/javascript"></script>
  <script src="./assets/js/core/bootstrap-material-design.min.js" type="text/javascript"></script>
  <script src="./assets/js/plugins/moment.min.js"></script>
  <script src="./assets/js/plugins/bootstrap-datetimepicker.js" type="text/javascript"></script>
  <script src="./assets/js/plugins/nouislider.min.js" type="text/javascript"></script>
  <script src="./assets/js/plugins/jquery.sharrre.js" type="text/javascript"></script>
  <script src="./assets/js/material-kit.js?v=2.0.4" type="text/javascript"></script>
<script src="./assets/js/dark-mode.js"></script>
</body>
</html>