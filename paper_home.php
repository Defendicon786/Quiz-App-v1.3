<?php
session_start();
if (!isset($_SESSION['paperloggedin']) || $_SESSION['paperloggedin'] !== true) {
    header('Location: paper_login.php');
    exit;
}
include 'database.php';
$classes = $conn->query("SELECT class_id, class_name FROM classes ORDER BY class_name");
$conn->close();
$logo = $_SESSION['paper_logo'];
$header = $_SESSION['paper_header'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <link rel="apple-touch-icon" sizes="76x76" href="./assets/img/apple-icon.png">
    <link rel="icon" type="image/png" href="./assets/img/favicon.png">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
    <title>Generate Paper</title>
    <meta content='width=device-width, initial-scale=1.0, shrink-to-fit=no' name='viewport' />
    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,700|Material+Icons" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="./assets/css/material-kit.css?v=2.0.4" rel="stylesheet" />
    <link href="./assets/css/sidebar.css" rel="stylesheet" />
    <link id="dark-mode-style" rel="stylesheet" href="./assets/css/dark-mode.css" />
    <style>
        .footer-text { text-align: center; margin-top: 20px; padding: 20px 0; }
        body.dark-mode .main { background: transparent; }
        .card { background: #1e1e2f; color: #fff; }
        .card-header.card-header-primary { background: #1e1e2f; color: #fff; border-bottom: 1px solid #11111a; }
        .form-control { background-color: #424242; color: #fff; border-color: #666; }
        .form-control::placeholder { color: #bbb; }
        #question-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1050;
        }
        #question-modal .modal-content {
            background: #1e1e1e;
            color: #e0e0e0;
            max-height: 80vh;
            overflow-y: auto;
            padding: 20px;
            width: 90%;
            max-width: 800px;
        }
        #question-lists .type-block { margin-bottom: 15px; }
        #question-lists .type-block h5 { margin-top: 0; }
    </style>
</head>
<body class="dark-mode">
<div class="layout">
  <div class="main">
    <main class="content">
      <?php if ($logo) { echo '<div class="text-center mb-4"><img src="' . htmlspecialchars($logo) . '" height="80"></div>'; } ?>
      <h2 class="text-center mb-4"><?php echo htmlspecialchars($header); ?></h2>
      <div class="container">
        <div class="row justify-content-center">
          <div class="col-12 col-md-10 col-lg-8 col-xl-8">
            <div class="card">
              <form method="post" action="generate_paper.php">
                <div class="card-header card-header-primary text-center">
                  <h4 class="card-title">Generate Paper</h4>
                </div>
                <div class="card-body">
                        <div class="form-group">
                          <label class="bmd-label-floating">Paper Name</label>
                          <input type="text" name="paper_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                          <label class="bmd-label-floating">Select Class</label>
                          <select name="class_id" id="class_id" class="form-control" required>
                            <option value="">Select Class</option>
                            <?php while($row = $classes->fetch_assoc()) { echo '<option value="'.$row['class_id'].'">'.htmlspecialchars($row['class_name']).'</option>'; } ?>
                          </select>
                        </div>
                        <div class="form-group">
                          <label class="bmd-label-floating">Select Subject</label>
                          <select name="subject_id" id="subject_id" class="form-control" required>
                            <option value="">Select Subject</option>
                          </select>
                        </div>
                        <div class="form-group">
                          <label class="bmd-label-floating">Select Chapter</label>
                          <select name="chapter_id" id="chapter_id" class="form-control" required>
                            <option value="">Select Chapter</option>
                          </select>
                        </div>
                        <div class="form-group">
                          <label class="bmd-label-floating">Select Topic</label>
                          <select name="topic_id" id="topic_id" class="form-control">
                            <option value="">Select Topic</option>
                          </select>
                        </div>
                        <div class="form-group">
                          <label class="bmd-label-floating">MCQs</label>
                          <input type="number" name="mcq" value="0" min="0" class="form-control">
                          <small class="form-text text-muted">Available: <span id="mcq-count">0</span></small>
                        </div>
                        <div class="form-group">
                          <label class="bmd-label-floating">Short Questions</label>
                          <input type="number" name="short" value="0" min="0" class="form-control">
                          <small class="form-text text-muted">Available: <span id="short-count">0</span></small>
                        </div>
                        <div class="form-group">
                          <label class="bmd-label-floating">Long Questions</label>
                          <input type="number" name="essay" value="0" min="0" class="form-control">
                          <small class="form-text text-muted">Available: <span id="essay-count">0</span></small>
                        </div>
                        <div class="form-group">
                          <label class="bmd-label-floating">Fill in the Blanks</label>
                          <input type="number" name="fill" value="0" min="0" class="form-control">
                          <small class="form-text text-muted">Available: <span id="fill-count">0</span></small>
                        </div>
                        <div class="form-group">
                          <label class="bmd-label-floating">Numerical</label>
                          <input type="number" name="numerical" value="0" min="0" class="form-control">
                          <small class="form-text text-muted">Available: <span id="numerical-count">0</span></small>
                        </div>
                        <div class="form-group" id="manual-select-wrapper" style="display:none;">
                          <button type="button" id="manual-select" class="btn btn-secondary" style="color:#000;">Manual Selection</button>
                        </div>
                        <input type="hidden" name="selected_mcq" id="selected_mcq">
                        <input type="hidden" name="selected_short" id="selected_short">
                        <input type="hidden" name="selected_essay" id="selected_essay">
                        <input type="hidden" name="selected_fill" id="selected_fill">
                        <input type="hidden" name="selected_numerical" id="selected_numerical">
                        <div class="form-group">
                          <label class="bmd-label-floating">Date (optional)</label>
                          <input type="date" name="paper_date" class="form-control">
                        </div>
                        <div class="form-group">
                          <label>Selection Mode</label><br>
                          <div class="form-check form-check-inline">
                            <label class="form-check-label">
                              <input class="form-check-input" type="radio" name="mode" value="random" checked> Random
                              <span class="circle"><span class="check"></span></span>
                            </label>
                          </div>
                          <div class="form-check form-check-inline">
                            <label class="form-check-label">
                              <input class="form-check-input" type="radio" name="mode" value="manual"> Manual
                              <span class="circle"><span class="check"></span></span>
                            </label>
                          </div>
                        </div>
                      </div>
                      <div class="footer text-center">
                        <button type="submit" class="btn btn-primary btn-lg">Generate Paper</button>
                        <a href="paper_logout.php" class="btn btn-secondary btn-lg" style="color:#000;">Logout</a>
                      </div>
                    </form>
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
<div id="question-modal">
  <div class="modal-content">
    <h4>Select Questions</h4>
    <div id="question-lists"></div>
    <div class="text-right">
      <button type="button" id="save-selection" class="btn btn-primary btn-sm">Save</button>
      <button type="button" id="cancel-selection" class="btn btn-secondary btn-sm">Cancel</button>
    </div>
  </div>
</div>
<script src="./assets/js/core/jquery.min.js" type="text/javascript"></script>
<script src="./assets/js/core/popper.min.js" type="text/javascript"></script>
<script src="./assets/js/core/bootstrap-material-design.min.js" type="text/javascript"></script>
<script src="./assets/js/plugins/moment.min.js"></script>
<script src="./assets/js/material-kit.js?v=2.0.4" type="text/javascript"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const classSelect = document.getElementById('class_id');
    const subjectSelect = document.getElementById('subject_id');
    const chapterSelect = document.getElementById('chapter_id');
    const topicSelect = document.getElementById('topic_id');
    const manualBtn = document.getElementById('manual-select');
    const manualWrapper = document.getElementById('manual-select-wrapper');
    const questionModal = document.getElementById('question-modal');
    const questionLists = document.getElementById('question-lists');
    const saveSelection = document.getElementById('save-selection');
    const cancelSelection = document.getElementById('cancel-selection');
    const typeMap = [
        {key:'mcq', label:'MCQs', input:'mcq', hidden:'selected_mcq'},
        {key:'short', label:'Short Questions', input:'short', hidden:'selected_short'},
        {key:'essay', label:'Long Questions', input:'essay', hidden:'selected_essay'},
        {key:'fillblanks', label:'Fill in the Blanks', input:'fill', hidden:'selected_fill'},
        {key:'numerical', label:'Numerical', input:'numerical', hidden:'selected_numerical'}
    ];
    const counts = {
        mcq: document.getElementById('mcq-count'),
        short: document.getElementById('short-count'),
        essay: document.getElementById('essay-count'),
        fill: document.getElementById('fill-count'),
        numerical: document.getElementById('numerical-count')
    };

    classSelect.addEventListener('change', function() {
        const classId = this.value;
        subjectSelect.innerHTML = '<option value="">Select Subject</option>';
        chapterSelect.innerHTML = '<option value="">Select Chapter</option>';
        topicSelect.innerHTML = '<option value="">Select Topic</option>';
        if (!classId) return;
        fetch('get_subjects.php?class_id=' + classId)
            .then(r => r.json())
            .then(data => {
                data.forEach(s => {
                    subjectSelect.insertAdjacentHTML('beforeend', `<option value="${s.subject_id}">${s.subject_name}</option>`);
                });
            });
    });

    subjectSelect.addEventListener('change', function() {
        const classId = classSelect.value;
        const subjectId = this.value;
        chapterSelect.innerHTML = '<option value="">Select Chapter</option>';
        topicSelect.innerHTML = '<option value="">Select Topic</option>';
        if (!classId || !subjectId) return;
        fetch(`get_chapters.php?class_id=${classId}&subject_id=${subjectId}`)
            .then(r => r.json())
            .then(data => {
                if (Array.isArray(data)) {
                    data.forEach(c => {
                        chapterSelect.insertAdjacentHTML('beforeend', `<option value="${c.chapter_id}">${c.chapter_name}</option>`);
                    });
                }
            });
    });

    chapterSelect.addEventListener('change', function() {
        const chapterId = this.value;
        topicSelect.innerHTML = '<option value="">Select Topic</option>';
        if (!chapterId) return;
        fetch('get_topics.php?chapter_id=' + chapterId)
            .then(r => r.json())
            .then(data => {
                data.forEach(t => {
                    topicSelect.insertAdjacentHTML('beforeend', `<option value="${t.topic_id}">${t.topic_name}</option>`);
                });
            });
    });

    function updateCounts() {
        const chapterId = chapterSelect.value;
        const topicId = topicSelect.value;
        manualWrapper.style.display = topicId ? 'block' : 'none';
        if (!chapterId) return;
        let url = `get_question_counts.php?chapter_ids=${chapterId}`;
        if (topicId) url += `&topic_ids=${topicId}`;
        fetch(url)
            .then(r => r.json())
            .then(data => {
                counts.mcq.textContent = data.mcq || 0;
                counts.short.textContent = data.short || 0;
                counts.essay.textContent = data.essay || 0;
                counts.fill.textContent = data.fillblanks || 0;
                counts.numerical.textContent = data.numerical || 0;
            });
    }

    topicSelect.addEventListener('change', updateCounts);
    chapterSelect.addEventListener('change', updateCounts);

    manualBtn.addEventListener('click', function() {
        questionLists.innerHTML = '';
        const chapterId = chapterSelect.value;
        const topicId = topicSelect.value;
        typeMap.forEach(t => {
            const block = document.createElement('div');
            block.className = 'type-block';
            block.innerHTML = `<h5>${t.label}</h5><div class="questions">Loading...</div>`;
            questionLists.appendChild(block);
            const params = new URLSearchParams();
            params.append('type', t.key);
            params.append('chapter_ids', chapterId);
            if (topicId) params.append('topic_ids', topicId);
            fetch('get_questions.php', {method:'POST', body: params})
                .then(r => r.json())
                .then(data => {
                    const qDiv = block.querySelector('.questions');
                    qDiv.innerHTML = '';
                    if (Array.isArray(data) && data.length) {
                        const selected = document.getElementById(t.hidden).value.split(',');
                        data.forEach(q => {
                            const id = q.id;
                            const numeric = id.split('_')[1];
                            const checked = selected.includes(numeric) ? 'checked' : '';
                            qDiv.insertAdjacentHTML('beforeend', `<div><label><input type="checkbox" data-type="${t.key}" value="${id}" ${checked}> ${q.question}</label></div>`);
                        });
                    } else {
                        qDiv.textContent = 'No questions available.';
                    }
                });
        });
        questionModal.style.display = 'flex';
    });

    cancelSelection.addEventListener('click', function() {
        questionModal.style.display = 'none';
    });

    saveSelection.addEventListener('click', function() {
        typeMap.forEach(t => {
            const selected = Array.from(questionLists.querySelectorAll(`input[data-type="${t.key}"]:checked`)).map(cb => cb.value.split('_')[1]);
            document.getElementById(t.hidden).value = selected.join(',');
            const inputField = document.querySelector(`input[name="${t.input}"]`);
            if (inputField) inputField.value = selected.length;
        });
        questionModal.style.display = 'none';
    });
});
</script>
</body>
</html>
