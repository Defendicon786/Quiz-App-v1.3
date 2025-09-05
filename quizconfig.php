<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
  session_start();
  if(!isset($_SESSION["instructorloggedin"]) || $_SESSION["instructorloggedin"] !== true){
      header("location: instructorlogin.php");
      exit;
  }

include "database.php";

// Get subjects for dropdown
$subjects = [];
$sql_subjects = "SELECT subject_id, subject_name FROM subjects ORDER BY subject_name ASC";
$result_subjects = $conn->query($sql_subjects);
if ($result_subjects && $result_subjects->num_rows > 0) {
    while ($row_subject = $result_subjects->fetch_assoc()) {
        $subjects[] = $row_subject;
    }
}

// Get classes for dropdown
$classes = [];
$sql_classes = "SELECT class_id, class_name FROM classes ORDER BY class_name ASC";
$result_classes = $conn->query($sql_classes);
if ($result_classes && $result_classes->num_rows > 0) {
    while ($row_class = $result_classes->fetch_assoc()) {
        $classes[] = $row_class;
    }
}

// We'll load sections dynamically based on class selection
$sections = [];

// Add JavaScript for dynamic chapter, section and topic loading
?>
<script>
function loadChapters() {
    var classId = document.getElementById('class_id').value;
    var subjectId = document.getElementById('subject_id').value;
    
    if(classId && subjectId) {
        fetch('get_chapters.php?class_id=' + classId + '&subject_id=' + subjectId)
            .then(response => response.json())
            .then(data => {
                var chapterSelect = document.getElementById('chapter_ids');
                chapterSelect.innerHTML = '<option value="">Select Chapters</option>';
                
                // Add "All Chapters" option at the top
                if (data.length > 0) {
                    var allOption = document.createElement('option');
                    allOption.value = 'all_chapters';
                    allOption.text = 'All Chapters';
                    chapterSelect.add(allOption, 1);
                }
                
                data.forEach(function(chapter) {
                    chapterSelect.innerHTML += '<option value="' + chapter.chapter_id + '">' + chapter.chapter_name + '</option>';
                });
                
                $(chapterSelect).select2();
                
                // Add event listener for All Chapters option
                $(chapterSelect).off('change.allchapters').on('change.allchapters', function(e) {
                    var values = $(this).val();
                    if (values && values.includes('all_chapters')) {
                        // Clear selection
                        $(this).val(null).trigger('change');
                        
                        // Get all chapter IDs except the placeholder and all_chapters
                        var allChapterIds = [];
                        for (var i = 0; i < chapterSelect.options.length; i++) {
                            var option = chapterSelect.options[i];
                            if (option.value && option.value !== 'all_chapters') {
                                allChapterIds.push(option.value);
                            }
                        }
                        
                        // Select all chapter options
                        $(this).val(allChapterIds).trigger('change');
                    }
                });
            })
            .catch(error => console.error('Error:', error));
    }
    
    // Also load sections for the selected class
    loadSections();
}

function loadSections() {
    var classId = document.getElementById('class_id').value;
    
    if(classId) {
        fetch('get_sections.php?class_id=' + classId)
            .then(response => response.json())
            .then(data => {
                var sectionSelect = document.getElementById('section_id');
                sectionSelect.innerHTML = '<option value="">Select Section (Optional)</option>';
                
                data.forEach(function(section) {
                    sectionSelect.innerHTML += '<option value="' + section.id + '">' + section.section_name + '</option>';
                });
                
                $(sectionSelect).select2();
            })
            .catch(error => console.error('Error:', error));
    }
}

function loadTopics(chapterIds) {
    var topicSelect = document.getElementById('topic_ids');
    if(!topicSelect) return;
    topicSelect.innerHTML = '<option value="">All Topics</option>';
    if(chapterIds && chapterIds.length > 0) {
        fetch('get_topics.php?chapter_ids=' + chapterIds.join(','))
            .then(response => response.json())
            .then(data => {
                data.forEach(function(topic) {
                    var option = document.createElement('option');
                    option.value = topic.topic_id;
                    option.text = topic.topic_name;
                    topicSelect.appendChild(option);
                });
                $(topicSelect).select2();
                $(topicSelect).val(null).trigger('change');
            })
            .catch(error => console.error('Error:', error));
    } else {
        $(topicSelect).select2();
        $(topicSelect).val(null).trigger('change');
    }
}
// Topic dropdown for question selection is loaded on the main page
</script>
<?php

// Get next quiz number
$query = "SELECT MAX(quiznumber) as max_quiz FROM quizconfig";
$result = $conn->query($query);
$row = $result->fetch_assoc();
$next = ($row['max_quiz'] !== null) ? $row['max_quiz'] + 1 : 1;

// Add this function after database connection
function getAvailableQuestionsCount($conn, $chapter_ids) {
    $counts = array(
        'mcq' => 0,
        'numerical' => 0,
        'dropdown' => 0,
        'fillblanks' => 0,
        'short' => 0,
        'essay' => 0
    );
    
    if (!empty($chapter_ids)) {
        $chapter_ids_str = implode(',', $chapter_ids);
        
        // Count MCQs
        $sql = "SELECT COUNT(*) as count FROM mcqdb WHERE chapter_id IN ($chapter_ids_str)";
        $result = $conn->query($sql);
        $counts['mcq'] = $result->fetch_assoc()['count'];
        
        // Count Numerical
        $sql = "SELECT COUNT(*) as count FROM numericaldb WHERE chapter_id IN ($chapter_ids_str)";
        $result = $conn->query($sql);
        $counts['numerical'] = $result->fetch_assoc()['count'];
        
        // Count Dropdown
        $sql = "SELECT COUNT(*) as count FROM dropdown WHERE chapter_id IN ($chapter_ids_str)";
        $result = $conn->query($sql);
        $counts['dropdown'] = $result->fetch_assoc()['count'];
        
        // Count Fill in Blanks
        $sql = "SELECT COUNT(*) as count FROM fillintheblanks WHERE chapter_id IN ($chapter_ids_str)";
        $result = $conn->query($sql);
        $counts['fillblanks'] = $result->fetch_assoc()['count'];
        
        // Count Short Answer
        $sql = "SELECT COUNT(*) as count FROM shortanswer where chapter_id IN ($chapter_ids_str)";
        $result = $conn->query($sql);
        $counts['short'] = $result->fetch_assoc()['count'];
        
        // Count Essay
        $sql = "SELECT COUNT(*) as count FROM essay WHERE chapter_id IN ($chapter_ids_str)";
        $result = $conn->query($sql);
        $counts['essay'] = $result->fetch_assoc()['count'];
    }
    
    return $counts;
}

?>
<script>
function updateAvailableQuestions() {
    var chapterIds = $('#chapter_ids').val();
    var topicIds = $('#topic_ids').val() || [];
    if (topicIds && topicIds.length > 0) {
        topicIds = topicIds.filter(function(id) { return id; });
        var totalTopics = $('#topic_ids option[value!=""]').length;
        if(topicIds.length === totalTopics) {
            topicIds = [];
        }
    }
    if(chapterIds && chapterIds.length > 0) {
        var url = 'get_question_counts.php?chapter_ids=' + chapterIds.join(',');
        if(topicIds && topicIds.length > 0) {
            url += '&topic_ids=' + topicIds.join(',');
        }
        fetch(url)
            .then(response => response.text())
            .then(text => {
                let data;
                try {
                    data = text.trim() === '' ? {} : JSON.parse(text);
                } catch (e) {
                    console.error('Failed to parse response:', text);
                    throw new Error('Invalid JSON response');
                }
                // Update max values for each question type
                $('#typea').attr('max', data.mcq);
                $('#typeb').attr('max', data.numerical);
                $('#typec').attr('max', data.dropdown);
                $('#typed').attr('max', data.fillblanks);
                $('#typee').attr('max', data.short);
                $('#typef').attr('max', data.essay);
                
                // Update helper text
                $('#mcq-available').text('Available: ' + data.mcq);
                $('#numerical-available').text('Available: ' + data.numerical);
                $('#dropdown-available').text('Available: ' + data.dropdown);
                $('#fillblanks-available').text('Available: ' + data.fillblanks);
                $('#short-available').text('Available: ' + data.short);
                $('#essay-available').text('Available: ' + data.essay);
            })
            .catch(error => console.error('Error:', error));
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Toggle visibility of Select Questions button based on random quiz checkbox
    $("#random_quiz_checkbox").on("change", function() {
        if($(this).is(":checked")) {
            $("#selectQuestionsBtn").prop("disabled", true);
        } else {
            let chapterIds = $("#chapter_ids").val();
            if(chapterIds && chapterIds.length > 0) {
                $("#selectQuestionsBtn").prop("disabled", false);
            }
        }
    });

    // Add onsubmit validation to the form
    $('form').on('submit', function(e) {
        if(!validateQuestionCounts()) {
            e.preventDefault();
        }
    });
});
</script>

<script>
function validateQuestionCounts() {
    var chapterIds = $('#chapter_ids').val();
    if(!chapterIds || chapterIds.length === 0) {
        alert('Please select at least one chapter');
        return false;
    }

    // Get current values
    var typea = parseInt($('#typea').val()) || 0;
    var typeb = parseInt($('#typeb').val()) || 0;
    var typec = parseInt($('#typec').val()) || 0;
    var typed = parseInt($('#typed').val()) || 0;
    var typee = parseInt($('#typee').val()) || 0;
    var typef = parseInt($('#typef').val()) || 0;

    // Get max values
    var maxMcq = parseInt($('#typea').attr('max')) || 0;
    var maxNumerical = parseInt($('#typeb').attr('max')) || 0;
    var maxDropdown = parseInt($('#typec').attr('max')) || 0;
    var maxFill = parseInt($('#typed').attr('max')) || 0;
    var maxShort = parseInt($('#typee').attr('max')) || 0;
    var maxEssay = parseInt($('#typef').attr('max')) || 0;

    var errors = [];
    
    if(typea > maxMcq) errors.push('MCQ questions requested (' + typea + ') exceed available questions (' + maxMcq + ')');
    if(typeb > maxNumerical) errors.push('Numerical questions requested (' + typeb + ') exceed available questions (' + maxNumerical + ')');
    if(typec > maxDropdown) errors.push('Dropdown questions requested (' + typec + ') exceed available questions (' + maxDropdown + ')');
    if(typed > maxFill) errors.push('Fill in blanks questions requested (' + typed + ') exceed available questions (' + maxFill + ')');
    if(typee > maxShort) errors.push('Short answer questions requested (' + typee + ') exceed available questions (' + maxShort + ')');
    if(typef > maxEssay) errors.push('Essay questions requested (' + typef + ') exceed available questions (' + maxEssay + ')');

    if(errors.length > 0) {
        alert('Error:\n' + errors.join('\n'));
        return false;
    }

    return true;
}

// Add onsubmit validation to the form - handled in DOMContentLoaded listener above
</script>

<!-- Add JavaScript for loading questions and handling manual question selection -->
<script>
// Function to open question selector modal
// Flag to track if manual question selection modal is active
var manualSelectionActive = false;
// Store element that triggered the question selector
var questionSelectorTriggerElement = null;

function openQuestionSelector() {
    manualSelectionActive = true;
    questionSelectorTriggerElement = document.activeElement;
    var chapterIds = $('#chapter_ids').val();
    var topicIds = $('#topic_ids').val() || [];
    if(topicIds && topicIds.length > 0) {
        topicIds = topicIds.filter(function(id){ return id; });
        var totalTopics = $('#topic_ids option[value!=""]').length;
        if(topicIds.length === totalTopics) {
            topicIds = [];
        }
    }

    if(!chapterIds || chapterIds.length === 0) {
        alert('Please select chapters first to load questions');
        return;
    }


    
    // Show loading indicator
    $('.questions-list').html('<div class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading questions...</div>');
    
    // Reset previous selections and UI on modal open
    // Reset global selections
    window['mcqQuestions_selected'] = [];
    window['numericalQuestions_selected'] = [];
    window['dropdownQuestions_selected'] = [];
    window['fillblanksQuestions_selected'] = [];
    window['shortQuestions_selected'] = [];
    window['essayQuestions_selected'] = [];
    
    // Clear existing inputs from previous selections
    $('.selected-question-input').remove();
    
    // Reset count displays to reflect no selections
    $('#typea, #typeb, #typec, #typed, #typee, #typef').val(0);
    marks(); // Update total marks display
    
    // Load questions for each type sequentially instead of firing all
    // requests at once.  Some deployments throttle concurrent requests
    // which previously resulted in HTTP 503 responses for later requests.
    // By chaining the promises we give the backend breathing room and
    // avoid overloading it when the question selector is opened.
    const typesToLoad = [
        ['mcq', 'mcqQuestions'],
        ['numerical', 'numericalQuestions'],
        ['dropdown', 'dropdownQuestions'],
        ['fillblanks', 'fillblanksQuestions'],
        ['short', 'shortQuestions'],
        ['essay', 'essayQuestions']
    ];

    function loadSequentially(index) {
        if (index >= typesToLoad.length) return Promise.resolve();
        const [type, container] = typesToLoad[index];
        return loadQuestionsByType(type, container, chapterIds, topicIds)
            .finally(() => loadSequentially(index + 1));
    }

    loadSequentially(0);
    
    // Ensure modal is attached to body before showing
    $('#questionSelectorModal').appendTo('body').modal('show');
}

// Function to load questions by type
function loadQuestionsByType(type, containerId, chapterIds, topicIds) {
    if(!chapterIds || chapterIds.length === 0) return Promise.resolve();
    var url = 'get_chapter_questions.php?type=' + type + '&chapter_ids=' + chapterIds.join(',');
    if(topicIds && topicIds.length > 0) {
        url += '&topic_ids=' + topicIds.join(',');
    }

    return fetch(url, { credentials: 'same-origin' })
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.text();
        })
        .then(text => {
            let data;
            try {
                data = text.trim() === '' ? [] : JSON.parse(text);
            } catch (e) {
                console.error('Failed to parse response:', text);
                throw new Error('Invalid JSON response');
            }
            var container = $('#' + containerId + ' .questions-list');
            container.empty();
            
            if(data.error) {
                container.html('<div class="alert alert-danger">' + data.error + '</div>');
                return;
            }
            
            if(data.length === 0) {
                container.html('<div class="alert alert-info">No questions available for this chapter and question type</div>');
                return;
            }
            
            // Create checkboxes for each question with improved UI
            var html = '<div class="question-selection-container">';
            
            // Add select all option
            html += '<div class="select-all-container mb-3 p-2 bg-dark rounded">' +
                    '<div class="form-check">' +
                    '<label class="form-check-label">' +
                    '<input type="checkbox" class="form-check-input select-all-checkbox" id="select-all-' + type + '">' +
                    '<strong>Select All Questions</strong>' +
                    '</label>' +
                    '</div></div>';
            
            data.forEach(function(question, index) {
                // Format question display based on type
                var questionText = '';
                
                if(type === 'mcq') {
                    questionText = '<div class="question-text"><strong>Question:</strong> ' + question.question + 
                                  '<br><strong>A:</strong> ' + question.optiona + 
                                  '<br><strong>B:</strong> ' + question.optionb + 
                                  '<br><strong>C:</strong> ' + question.optionc + 
                                  '<br><strong>D:</strong> ' + question.optiond + 
                                  '<br><strong>Answer:</strong> ' + question.answer + '</div>';
                } else if(type === 'fillblanks') {
                    questionText = '<div class="question-text"><strong>Sentence:</strong> ' + question.sentence + 
                                  '<br><strong>Answer:</strong> ' + question.answer + '</div>';
                } else {
                    // Default format for numerical, short, essay
                    questionText = '<div class="question-text"><strong>Question:</strong> ' + (question.question || question.sentence) + 
                                  '<br><strong>Answer:</strong> ' + question.answer + '</div>';
                }
                
                html += '<div class="question-item card mb-2">' +
                        '<div class="card-body">' +
                        '<div class="form-check">' +
                        '<label class="form-check-label">' +
                        '<input type="checkbox" class="form-check-input question-checkbox" ' +
                        'id="' + type + '-question-' + index + '" ' +
                        'name="question[]" value="' + question.unique_id + '" ' +
                        'data-question-id="' + question.id + '" ' +
                        'data-question-type="' + type + '">' +
                        '<strong>Question #' + (index + 1) + '</strong>' +
                        '</label>' +
                        '</div>' +
                        questionText +
                        '</div></div>';
            });
            
            html += '</div>';
            container.html(html);
            
            // Add selection tracking
            $('#' + containerId).data('selected-questions', []);
            
            // Add event listeners to checkboxes
            setupCheckboxListeners(type, containerId);
        })
        .catch(error => {
            console.error('Error loading questions:', error);
            var container = $('#' + containerId + ' .questions-list');
            container.html('<div class="alert alert-danger">Error loading questions: ' + error.message + '</div>');
        });
}

// Setup checkbox event listeners
function setupCheckboxListeners(type, containerId) {
    // Store questions globally for this tab to avoid jQuery data storage issues
    window[containerId + '_selected'] = window[containerId + '_selected'] || [];
    
    // Individual question checkboxes
    $('#' + containerId + ' .question-checkbox').off('change').on('change', function(e) {
        // Stop event propagation to prevent any parent handlers from firing
        e.stopPropagation();
        
        var selectedQuestions = window[containerId + '_selected'];
        
        var questionId = $(this).data('question-id');
        var questionType = $(this).data('question-type');
        var uniqueId = $(this).val();
        var isChecked = this.checked;
        
        console.log('Checkbox changed:', isChecked, uniqueId);
        
        // Use setTimeout to ensure the checkbox state is processed after the event
        setTimeout(function() {
            if(isChecked) {
                // Add to selected questions if not already in the array
                if(!selectedQuestions.some(q => q.uniqueId === uniqueId)) {
                    selectedQuestions.push({
                        id: questionId,
                        type: questionType,
                        uniqueId: uniqueId
                    });
                }
            } else {
                // Remove from selected questions
                selectedQuestions = selectedQuestions.filter(q => q.uniqueId !== uniqueId);
            }
            
            // Store updated selections globally
            window[containerId + '_selected'] = selectedQuestions;
            
            // Debug log to verify selections
            console.log('Current selections for ' + type + ':', selectedQuestions);
            
            // Update count display
            updateCountDisplay(type, selectedQuestions.length);
            
            // Update select all checkbox state
            updateSelectAllCheckbox(type);
        }, 50);
    });
    
    // Prevent label clicks from toggling checkbox (handle it manually)
    $('#' + containerId + ' .form-check-label').on('click', function(e) {
        // Only prevent default if the click was directly on the label, not on the checkbox
        if (e.target.type !== 'checkbox') {
            e.preventDefault();
            
            // Find the checkbox within this label and toggle it
            var checkbox = $(this).find('input[type="checkbox"]');
            checkbox.prop('checked', !checkbox.prop('checked')).trigger('change');
        }
    });
    
    // Select all checkbox
    $('#select-all-' + type).off('change').on('change', function(e) {
        // Stop event propagation
        e.stopPropagation();
        
        var checkboxes = $('#' + containerId + ' .question-checkbox');
        var selectedQuestions = [];
        var isChecked = this.checked;
        
        // Use setTimeout to ensure the checkbox state is processed after the event
        setTimeout(function() {
            checkboxes.prop('checked', isChecked);
            
            if(isChecked) {
                // Add all questions to selected
                checkboxes.each(function() {
                    selectedQuestions.push({
                        id: $(this).data('question-id'),
                        type: $(this).data('question-type'),
                        uniqueId: $(this).val()
                    });
                });
            }
            
            // Store updated selections globally
            window[containerId + '_selected'] = selectedQuestions;
            
            // Debug log to verify selections
            console.log('Select all ' + type + ' changed:', selectedQuestions);
            
            // Update count display
            updateCountDisplay(type, selectedQuestions.length);
        }, 50);
    });
}

// Update select all checkbox state based on individual selections
function updateSelectAllCheckbox(type) {
    var container = $('#' + getContainerIdByType(type));
    var allCheckboxes = container.find('.question-checkbox');
    var checkedCheckboxes = container.find('.question-checkbox:checked');
    
    var selectAllCheckbox = $('#select-all-' + type);
    selectAllCheckbox.prop('checked', allCheckboxes.length > 0 && allCheckboxes.length === checkedCheckboxes.length);
}

// Get container id by question type
function getContainerIdByType(type) {
    switch(type) {
        case 'mcq': return 'mcqQuestions';
        case 'numerical': return 'numericalQuestions';
        case 'dropdown': return 'dropdownQuestions';
        case 'fillblanks': return 'fillblanksQuestions';
        case 'short': return 'shortQuestions';
        case 'essay': return 'essayQuestions';
        default: return '';
    }
}

// Update the count display in the tab
function updateCountDisplay(type, count) {
    var tabId;
    switch(type) {
        case 'mcq': tabId = '#typea'; break;
        case 'numerical': tabId = '#typeb'; break;
        case 'dropdown': tabId = '#typec'; break;
        case 'fillblanks': tabId = '#typed'; break;
        case 'short': tabId = '#typee'; break;
        case 'essay': tabId = '#typef'; break;
    }
    
    if(manualSelectionActive) {
        // Update the count in the input field only during manual selection
        $(tabId).val(count);
        // Trigger the marks calculation
        marks();
    }
}

// Function to save selected questions
function saveSelectedQuestions() {
    // Get selected questions from each tab using global storage
    var selectedQuestions = {
        mcq: window['mcqQuestions_selected'] || [],
        numerical: window['numericalQuestions_selected'] || [],
        dropdown: window['dropdownQuestions_selected'] || [],
        fillblanks: window['fillblanksQuestions_selected'] || [],
        short: window['shortQuestions_selected'] || [],
        essay: window['essayQuestions_selected'] || []
    };
    
    console.log('All selected questions:', selectedQuestions);
    
    // Create hidden inputs to store selected questions
    var hiddenInputs = '';
    
    // First remove any existing hidden inputs
    $('.selected-question-input').remove();
    
    // Create hidden inputs for MCQ questions
    var mcqIds = selectedQuestions.mcq.map(q => q.uniqueId);
    if(mcqIds.length > 0) {
        hiddenInputs += '<input type="hidden" name="selected_mcq" class="selected-question-input" value="' + mcqIds.join(',') + '">';
        // Update the displayed count
        $('#typea').val(mcqIds.length);
    } else {
        $('#typea').val(0);
    }
    
    // Numerical questions
    var numericalIds = selectedQuestions.numerical.map(q => q.uniqueId);
    if(numericalIds.length > 0) {
        hiddenInputs += '<input type="hidden" name="selected_numerical" class="selected-question-input" value="' + numericalIds.join(',') + '">';
        $('#typeb').val(numericalIds.length);
    } else {
        $('#typeb').val(0);
    }
    
    // Dropdown questions
    var dropdownIds = selectedQuestions.dropdown.map(q => q.uniqueId);
    if(dropdownIds.length > 0) {
        hiddenInputs += '<input type="hidden" name="selected_dropdown" class="selected-question-input" value="' + dropdownIds.join(',') + '">';
        $('#typec').val(dropdownIds.length);
    } else {
        $('#typec').val(0);
    }
    
    // Fill in blanks questions
    var fillblanksIds = selectedQuestions.fillblanks.map(q => q.uniqueId);
    if(fillblanksIds.length > 0) {
        hiddenInputs += '<input type="hidden" name="selected_fillblanks" class="selected-question-input" value="' + fillblanksIds.join(',') + '">';
        $('#typed').val(fillblanksIds.length);
    } else {
        $('#typed').val(0);
    }
    
    // Short answer questions
    var shortIds = selectedQuestions.short.map(q => q.uniqueId);
    if(shortIds.length > 0) {
        hiddenInputs += '<input type="hidden" name="selected_short" class="selected-question-input" value="' + shortIds.join(',') + '">';
        $('#typee').val(shortIds.length);
    } else {
        $('#typee').val(0);
    }
    
    // Essay questions
    var essayIds = selectedQuestions.essay.map(q => q.uniqueId);
    if(essayIds.length > 0) {
        hiddenInputs += '<input type="hidden" name="selected_essay" class="selected-question-input" value="' + essayIds.join(',') + '">';
        $('#typef').val(essayIds.length);
    } else {
        $('#typef').val(0);
    }
    
    // Calculate total questions count
    var totalQuestions = mcqIds.length + numericalIds.length + dropdownIds.length + 
                         fillblanksIds.length + shortIds.length + essayIds.length;
    
    // Add hidden input for total questions count
    hiddenInputs += '<input type="hidden" name="total_questions" class="selected-question-input" value="' + totalQuestions + '">';
    
    // Add a flag to indicate manual selection
    hiddenInputs += '<input type="hidden" name="is_manual_selection" class="selected-question-input" value="1">';

    // Store selected topics from the dropdown
    var topicIds = $('#topic_ids').val() || [];
    if(topicIds.length > 0) {
        topicIds = topicIds.filter(function(id){ return id; });
    }
    if(topicIds && topicIds.length > 0) {
        hiddenInputs += '<input type="hidden" name="topic_ids" class="selected-question-input" value="' + topicIds.join(',') + '">';
    }
    
    // Append hidden inputs to the form
    $('form[name="quizconfig"]').append(hiddenInputs);
    
    // Recalculate total marks
    marks();
    
    // Show selection summary
    console.log('Total selected questions:', totalQuestions);
    
    // Close the modal
    $('#questionSelectorModal').modal('hide');
    
    // Show confirmation message
    if(totalQuestions > 0) {
        alert('You have selected ' + totalQuestions + ' questions');
    } else {
        alert('You have not selected any questions');
    }
    
    // Force re-validation of question counts
    validateQuestionCounts();
    manualSelectionActive = false;
}

</script>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <link rel="apple-touch-icon" sizes="76x76" href="./assets/img/apple-icon.png">
  <link rel="icon" type="image/png" href="./assets/img/favicon.png">
  <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
  <title>Quiz Configuration</title>
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
  <link id="dark-mode-style" rel="stylesheet" href="./assets/css/dark-mode.css" />
  <style>
    /* Dark dropdowns for class/subject/section/chapter/topic selection */
    #class_id,
    #subject_id,
    #section_id,
    #chapter_ids,
    #topic_ids {
      background-color: #11111a;
      color: #fff;
    }
    #class_id option,
    #subject_id option,
    #section_id option,
    #chapter_ids option,
    #topic_ids option {
      background-color: #11111a;
      color: #fff;
    }
    .select2-container--default .select2-selection--single,
    .select2-container--default .select2-selection--multiple {
      background-color: #11111a;
      color: #fff;
      border: 1px solid #11111a;
    }
    .select2-dropdown,
    .select2-search__field,
    .select2-results__option {
      background-color: #11111a;
      color: #fff;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered,
    .select2-container--default .select2-selection--single .select2-selection__placeholder,
    .select2-container--default .select2-selection--multiple .select2-selection__rendered {
      color: #fff;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice {
      background-color: #11111a;
      border: 1px solid #11111a;
      color: #fff;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
      color: #fff;
    }

    /* Dark theme for manual question selection modal */
    #questionSelectorModal .modal-content {
      background-color: #1e1e2f;
      color: #fff;
      border: 1px solid #11111a;
    }
    #questionSelectorModal .modal-header,
    #questionSelectorModal .modal-footer {
      border-color: #11111a;
    }
    #questionSelectorModal .modal-title {
      color: #fff;
    }
    #questionSelectorModal .close {
      color: #fff;
      opacity: 1;
    }
    #questionSelectorModal .nav-tabs {
      border-bottom: 1px solid #11111a;
    }
    #questionSelectorModal .nav-tabs .nav-link {
      background-color: #11111a;
      color: #fff;
      border: 1px solid #1e1e2f;
      margin-right: 2px;
    }
    #questionSelectorModal .nav-tabs .nav-link.active {
      background-color: #1e1e2f;
      border-color: #1e1e2f;
    }

    /* Ensure checkboxes in manual selection modal are visible */
    #questionSelectorModal .form-check-input {
      position: relative;
      margin: 0 0.5rem 0 0;
      width: 1rem;
      height: 1rem;
      opacity: 1;
      pointer-events: auto;
      z-index: 1;
      overflow: visible;
      -webkit-appearance: checkbox;
      appearance: checkbox;
    }
    #questionSelectorModal .question-checkbox {
      accent-color: #0d6efd;
      background-color: #fff;
      border: 2px solid #0d6efd;
    }
    #questionSelectorModal .select-all-checkbox {
      accent-color: #ffc107;
      background-color: #fff;
      border: 2px solid #ffc107;
    }
    #questionSelectorModal .select-all-container {
      background-color: #343a40;
      color: #fff;
      position: relative;
      z-index: 2;
    }
    #questionSelectorModal .form-check-label {
      display: flex;
      align-items: center;
      color: #fff;
    }
  </style>
  <script>
    function marks() {
        var xa = document.getElementById("typea").value;
        var ya = document.getElementById("typeamarks").value;
        var ta = +xa*+ya;  
        document.getElementById("totala").innerHTML = ta;
        var xb = document.getElementById("typeb").value;
        var yb = document.getElementById("typebmarks").value; 
        var tb = +xb*+yb;     
        document.getElementById("totalb").innerHTML = tb;
        var xc = document.getElementById("typec").value;
        var yc = document.getElementById("typecmarks").value; 
        var tc = +xc*+yc;     
        document.getElementById("totalc").innerHTML = tc;
        var xd = document.getElementById("typed").value;
        var yd = document.getElementById("typedmarks").value; 
        var td = +xd*+yd;
        document.getElementById("totald").innerHTML = td;
        var xe = document.getElementById("typee").value;
        var ye = document.getElementById("typeemarks").value;
        var te = +xe*+ye;
        document.getElementById("totale").innerHTML = te;
        var xf = document.getElementById("typef").value;
        var yf = document.getElementById("typefmarks").value;
        var tf = +xf*+yf;     
        document.getElementById("totalf").innerHTML = tf;
        document.getElementById("total").innerHTML = ta+tb+tc+td+te+tf;
    }
  </script>
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
          <div class="row justify-content-center">
            <div class="col-lg-10 col-md-12">
              <div class="card">
                <form class="form" name="quizconfig" action="quizconfig.php" method="post">
                  <div class="card-header card-header-primary text-center">
                    <h4 class="card-title">Set Quiz</h4>
                    <p class="description">Set the pattern of the next quiz</p>
                  </div>

                  <div class="card-body">
                <!-- Quiz Basic Info -->
                <div class="row form-row-mobile">
                  <div class="col-md-6 col-12 mb-3">
                <div class="row">
                      <div class="col-md-4 col-5">
                        <p class="h5 mobile-text-center">Quiz Number</p>
                  </div>
                      <div class="col-md-8 col-7">
                    <input type="number" min="0" name="quiznumber" id="quiznumber" class="form-control text-center" value="<?php echo $next; ?>" required>
                  </div>
                    </div>
                  </div>
                  <div class="col-md-3 col-6 mb-3">
                    <div class="row">
                      <div class="col-6">
                        <p class="h5 mobile-text-center">Duration</p>
                      </div>
                      <div class="col-6">
                    <input type="number" min="0" name="duration" class="form-control text-center" value="10" required>
                  </div>
                    </div>
                  </div>
                  <div class="col-md-3 col-6">
                    <div class="row">
                      <div class="col-6">
                        <p class="h5 mobile-text-center">Attempts</p>
                      </div>
                      <div class="col-6">
                    <input type="number" min="1" name="attempts" id="attempts" class="form-control text-center" value="1" required>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Quiz Name -->
                <div class="row form-row-mobile">
                  <div class="col-md-3 col-12 mb-2">
                    <p class="h5 mobile-text-center">Quiz Name</p>
                  </div>
                  <div class="col-md-9 col-12">
                    <input type="text" name="quizname" id="quizname" class="form-control" placeholder="E.g., Mid Term Exam, Chapter 1 Quiz" required>
                  </div>
                </div>

                <!-- Class Selection -->
                <div class="row form-row-mobile">
                  <div class="col-md-3 col-12 mb-2">
                    <p class="h5 mobile-text-center">Class</p>
                  </div>
                  <div class="col-md-9 col-12">
                    <select name="class_id" id="class_id" class="form-control mobile-full-width" onchange="loadChapters()">
                        <option value="">Select Class</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo htmlspecialchars($class['class_id']); ?>">
                                <?php echo htmlspecialchars($class['class_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted">First select class and subject to enable chapter selection</small>
                  </div>
                </div>

                <!-- Subject Selection -->
                <div class="row form-row-mobile">
                  <div class="col-md-3 col-12 mb-2">
                    <p class="h5 mobile-text-center">Subject</p>
                  </div>
                  <div class="col-md-9 col-12">
                    <select class="form-control" id="subject_id" name="subject_id" onchange="loadChapters()" required>
                        <option value="">Select Subject</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?php echo htmlspecialchars($subject['subject_id']); ?>">
                                <?php echo htmlspecialchars($subject['subject_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                  </div>
                </div>

                <!-- Section Selection -->
                <div class="row form-row-mobile">
                  <div class="col-md-3 col-12 mb-2">
                    <p class="h5 mobile-text-center">Section</p>
                  </div>
                  <div class="col-md-9 col-12">
                    <select class="form-control" id="section_id" name="section_id">
                      <option value="">Select Section (Optional)</option>
                      <!-- Sections will be loaded dynamically based on class selection -->
                    </select>
                  </div>
                </div>

                <!-- Chapters Selection -->
                <div class="row form-row-mobile">
                  <div class="col-md-3 col-12 mb-2">
                    <p class="h5 mobile-text-center">Chapters</p>
                  </div>
                  <div class="col-md-9 col-12">
                    <select name="chapter_ids[]" id="chapter_ids" class="form-control mobile-full-width" multiple>
                      <option value="">Select Chapters</option>
                    </select>
                  </div>
                </div>

                <!-- Topics Selection -->
                <div class="row form-row-mobile">
                  <div class="col-md-3 col-12 mb-2">
                    <p class="h5 mobile-text-center">Topics</p>
                  </div>
                  <div class="col-md-9 col-12">
                    <select name="topic_ids[]" id="topic_ids" class="form-control mobile-full-width" multiple>
                      <option value="">All Topics</option>
                    </select>
                  </div>
                </div>
                <!-- Manual or Random Selection -->
                <div class="row form-row-mobile">
                  <div class="col-md-3 col-12 mb-2"></div>
                  <div class="col-md-9 col-12">
                    <div class="mt-2 d-flex align-items-center">
                      <button type="button" id="selectQuestionsBtn" class="btn btn-info btn-sm" onclick="openQuestionSelector()" disabled>
                        <i class="material-icons">list</i> Select Questions
                      </button>
                      <div class="form-check ml-3">
                        <label class="form-check-label">
                          <input class="form-check-input position-static" type="checkbox" name="random_quiz" value="1" id="random_quiz_checkbox" disabled>
                          Randomly select questions
                          <span class="form-check-sign">
                            <span class="check"></span>
                          </span>
                        </label>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Question Selection Modal -->
                <div class="modal fade" id="questionSelectorModal" tabindex="-1" role="dialog">
                  <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title">Select Questions</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                          <span aria-hidden="true">&times;</span>
                        </button>
                      </div>
                      <div class="modal-body">
                        <div class="question-tabs">
                          <ul class="nav nav-tabs" role="tablist">
                            <li class="nav-item">
                              <a class="nav-link active" data-toggle="tab" href="#mcqQuestions" role="tab">MCQ</a>
                            </li>
                            <li class="nav-item">
                              <a class="nav-link" data-toggle="tab" href="#numericalQuestions" role="tab">Numerical</a>
                            </li>
                            <li class="nav-item">
                              <a class="nav-link" data-toggle="tab" href="#dropdownQuestions" role="tab">Dropdown</a>
                            </li>
                            <li class="nav-item">
                              <a class="nav-link" data-toggle="tab" href="#fillblanksQuestions" role="tab">Fill Blanks</a>
                            </li>
                            <li class="nav-item">
                              <a class="nav-link" data-toggle="tab" href="#shortQuestions" role="tab">Short Answer</a>
                            </li>
                            <li class="nav-item">
                              <a class="nav-link" data-toggle="tab" href="#essayQuestions" role="tab">Essay</a>
                            </li>
                          </ul>
                          <div class="tab-content">
                            <div class="tab-pane active" id="mcqQuestions" role="tabpanel">
                              <div class="questions-list"></div>
                            </div>
                            <div class="tab-pane" id="numericalQuestions" role="tabpanel">
                              <div class="questions-list"></div>
                            </div>
                            <div class="tab-pane" id="dropdownQuestions" role="tabpanel">
                              <div class="questions-list"></div>
                            </div>
                            <div class="tab-pane" id="fillblanksQuestions" role="tabpanel">
                              <div class="questions-list"></div>
                            </div>
                            <div class="tab-pane" id="shortQuestions" role="tabpanel">
                              <div class="questions-list"></div>
                            </div>
                            <div class="tab-pane" id="essayQuestions" role="tabpanel">
                              <div class="questions-list"></div>
                            </div>
                          </div>
                        </div>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" onclick="saveSelectedQuestions()">Save Selection</button>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Quiz Start Time -->
                <div class="row form-row-mobile mt-4">
                  <div class="col-md-3 col-12 mb-2">
                    <p class="h5 mobile-text-center">Start Date and Time</p>
                  </div>
                  <div class="col-md-9 col-12">
                    <input type="text" class="form-control datetimepicker" name="starttime" value="<?php echo date('d/m/Y h:i A'); ?>"/>
                  </div>
                </div>

                <!-- Quiz End Time -->
                <div class="row form-row-mobile mt-3">
                  <div class="col-md-3 col-12 mb-2">
                    <p class="h5 mobile-text-center">End Date and Time</p>
                  </div>
                  <div class="col-md-9 col-12">
                    <input type="text" class="form-control datetimepicker" name="endtime" value="<?php echo date('d/m/Y h:i A', strtotime('+1 day')); ?>"/>
                    <small class="form-text text-muted">Quiz will no longer be available after this time. Students who start the quiz before this time will still get the full duration.</small>
                  </div>
                </div>

                <!-- Question Types Table Header -->
                <div class="row mt-4 d-none d-md-flex">
                  <div class="col-md-3">
                    <p class="h5">Question Type</p>
                  </div>
                  <div class="col-md-3 text-center">
                    <p class="h5">Number of Questions</p>
                  </div>
                  <div class="col-md-3 text-center">
                    <p class="h5">Marks per Question</p>
                  </div>
                  <div class="col-md-3 text-center">
                    <p class="h5">Total Marks</p>
                  </div>
                </div>

                <!-- MCQ Type -->
                <div class="row quiz-type-row">
                  <div class="col-md-3 col-12">
                    <p class="h6 mobile-text-center">MCQ</p>
                  </div>
                  <div class="col-md-3 col-4">
                    <input type="number" min="0" step="1" class="form-control text-center" name="typea" id="typea" value="0" oninput="marks()">
                  </div>
                  <div class="col-md-3 col-4">
                    <input type="number" min="0" step="1" class="form-control text-center" name="typeamarks" id="typeamarks" value="0" oninput="marks()">
                  </div>
                  <div class="col-md-3 col-4">
                    <p class="h5 text-center" style="font-style: italic;" id="totala">0</p>
                  </div>
                  <div class="col"><span id="mcq-available" class="text-info"></span></div>
                </div>

                <!-- Numerical Type -->
                <div class="row quiz-type-row">
                  <div class="col-md-3 col-12">
                    <p class="h6 mobile-text-center">Numerical type</p>
                  </div>
                  <div class="col-md-3 col-4">
                    <input type="number" min="0" step="1" class="form-control text-center" name="typeb" id="typeb" value="0" oninput="marks()">
                  </div>
                  <div class="col-md-3 col-4">
                    <input type="number" min="0" step="1" class="form-control text-center" name="typebmarks" id="typebmarks" value="0" oninput="marks()">
                  </div>
                  <div class="col-md-3 col-4">
                    <p class="h5 text-center" style="font-style: italic;" id="totalb">0</p>
                  </div>
                  <div class="col"><span id="numerical-available" class="text-info"></span></div>
                </div>

                <!-- Dropdown Type -->
                <div class="row quiz-type-row">
                  <div class="col-md-3 col-12">
                    <p class="h6 mobile-text-center">Drop down</p>
                  </div>
                  <div class="col-md-3 col-4">
                    <input type="number" min="0" step="1" class="form-control text-center" name="typec" id="typec" value="0" oninput="marks()">
                  </div>
                  <div class="col-md-3 col-4">
                    <input type="number" min="0" step="1" class="form-control text-center" name="typecmarks" id="typecmarks" value="0" oninput="marks()">
                  </div>
                  <div class="col-md-3 col-4">
                    <p class="h5 text-center" style="font-style: italic;" id="totalc">0</p>
                  </div>
                  <div class="col"><span id="dropdown-available" class="text-info"></span></div>
                </div>

                <!-- Fill in the blank Type -->
                <div class="row quiz-type-row">
                  <div class="col-md-3 col-12">
                    <p class="h6 mobile-text-center">Fill in the blank</p>
                  </div>
                  <div class="col-md-3 col-4">
                    <input type="number" min="0" step="1" class="form-control text-center" name="typed" id="typed" value="0" oninput="marks()">
                  </div>
                  <div class="col-md-3 col-4">
                    <input type="number" min="0" step="1" class="form-control text-center" name="typedmarks" id="typedmarks" value="0" oninput="marks()">
                  </div>
                  <div class="col-md-3 col-4">
                    <p class="h5 text-center" style="font-style: italic;" id="totald">0</p>
                  </div>
                  <div class="col"><span id="fillblanks-available" class="text-info"></span></div>
                </div>

                <!-- Short answer Type -->
                <div class="row quiz-type-row">
                  <div class="col-md-3 col-12">
                    <p class="h6 mobile-text-center">Short answer type</p>
                  </div>
                  <div class="col-md-3 col-4">
                    <input type="number" min="0" step="1" class="form-control text-center" name="typee" id="typee" value="0" oninput="marks()">
                  </div>
                  <div class="col-md-3 col-4">
                    <input type="number" min="0" step="1" class="form-control text-center" name="typeemarks" id="typeemarks" value="0" oninput="marks()">
                  </div>
                  <div class="col-md-3 col-4">
                    <p class="h5 text-center" style="font-style: italic;" id="totale">0</p>
                  </div>
                  <div class="col"><span id="short-available" class="text-info"></span></div>
                </div>

                <!-- Essay Type -->
                <div class="row quiz-type-row">
                  <div class="col-md-3 col-12">
                    <p class="h6 mobile-text-center">Essay type</p>
                  </div>
                  <div class="col-md-3 col-4">
                    <input type="number" min="0" step="1" class="form-control text-center" name="typef" id="typef" value="0" oninput="marks()">
                  </div>
                  <div class="col-md-3 col-4">
                    <input type="number" min="0" step="1" class="form-control text-center" name="typefmarks" id="typefmarks" value="0" oninput="marks()">
                  </div>
                  <div class="col-md-3 col-4">
                    <p class="h5 text-center" style="font-style: italic;" id="totalf">0</p>
                  </div>
                  <div class="col"><span id="essay-available" class="text-info"></span></div>
                </div>

                <!-- Total Marks -->
                <div class="row quiz-type-row mt-3">
                  <div class="col-md-3 col-6">
                    <p class="h5 mobile-text-center">Total Quiz Marks</p>
                  </div>
                  <div class="col-md-6 d-none d-md-block"></div>
                  <div class="col-md-3 col-6">
                    <p class="h2 text-center" id="total">0</p>
                  </div>
                </div>

                <!-- Random Questions Option -->
                <div class="row mt-3" style="display:none;">
                  <div class="col-12">
                    <div class="form-check">
                      <label class="form-check-label">
                        <input class="form-check-input position-static" type="checkbox" name="random_quiz_hidden" value="1">
                        Randomly select questions from the question bank
                        <span class="form-check-sign">
                          <span class="check"></span>
                        </span>
                      </label>
                    </div>
                  </div>
                </div>
              </div>


              <div class="text-center pb-4">
                <button type="submit" class="btn btn-primary btn-round">Set Quiz</button>
              </div>
            </form>

            <?php if ($_SERVER["REQUEST_METHOD"] == "POST"): ?>
              <div class="card-body pt-0">
            <?php              
              $quiznumber = $_POST["quiznumber"];
              $quizname = isset($_POST["quizname"]) && !empty(trim($_POST["quizname"])) ? 
                          $conn->real_escape_string(trim($_POST["quizname"])) : "Quiz #" . $conn->real_escape_string($quiznumber);

              // Validate chapter selection
              $chapter_ids = NULL;
              if (isset($_POST["chapter_ids"])) {
                  $chapter_input = $_POST["chapter_ids"];
                  if (!is_array($chapter_input)) {
                      $chapter_input = [$chapter_input];
                  }
                  // Remove 'all_chapters' from the array if present
                  $chapter_input = array_filter($chapter_input, function($value) {
                      return $value !== 'all_chapters';
                  });
                  if (!empty($chapter_input)) {
                      $chapter_ids = implode(',', array_map('intval', $chapter_input));
                  }
              }

              $topic_ids = NULL;
              if (isset($_POST['topic_ids'])) {
                  $topic_input = $_POST['topic_ids'];
                  if (!is_array($topic_input)) {
                      $topic_input = [$topic_input];
                  }
                  $topic_ids = implode(',', array_map('intval', $topic_input));
              }
              
              // Check if chapters are selected
              if (empty($chapter_ids)) {
                  echo '<div class="message error">Error: Please select at least one chapter for the quiz.</div>';
              } else {
                  $typeamarks = isset($_POST["typeamarks"]) ? $_POST["typeamarks"] : 0;
                  $typea = isset($_POST["typea"]) ? $_POST["typea"] : 0;
                  $typebmarks = isset($_POST["typebmarks"]) ? $_POST["typebmarks"] : 0;
                  $typeb = isset($_POST["typeb"]) ? $_POST["typeb"] : 0;
                  $typecmarks = isset($_POST["typecmarks"]) ? $_POST["typecmarks"] : 0;
                  $typec = isset($_POST["typec"]) ? $_POST["typec"] : 0;
                  $typedmarks = isset($_POST["typedmarks"]) ? $_POST["typedmarks"] : 0;
                  $typed = isset($_POST["typed"]) ? $_POST["typed"] : 0;
                  $typeemarks = isset($_POST["typeemarks"]) ? $_POST["typeemarks"] : 0;
                  $typee = isset($_POST["typee"]) ? $_POST["typee"] : 0;
                  $typefmarks = isset($_POST["typefmarks"]) ? $_POST["typefmarks"] : 0;
                  $typef = isset($_POST["typef"]) ? $_POST["typef"] : 0;

                  // Determine if random selection was requested
                  $is_random = isset($_POST["random_quiz"]) ? 1 : 0;

                  // If random quiz is selected, only add a default question when no counts were provided
                  if ($is_random) {
                      $total_selected = $typea + $typeb + $typec + $typed + $typee + $typef;
                      if ($total_selected == 0) {
                          $available = getAvailableQuestionsCount($conn, explode(',', $chapter_ids));
                          if ($available['mcq'] > 0) {
                              $typea = 1;
                          } elseif ($available['numerical'] > 0) {
                              $typeb = 1;
                          } elseif ($available['dropdown'] > 0) {
                              $typec = 1;
                          } elseif ($available['fillblanks'] > 0) {
                              $typed = 1;
                          } elseif ($available['short'] > 0) {
                              $typee = 1;
                          } elseif ($available['essay'] > 0) {
                              $typef = 1;
                          }
                      }
                  }
                  $maxmarks = $typeamarks*$typea+$typebmarks*$typeb+$typecmarks*$typec+$typedmarks*$typed+$typeemarks*$typee+$typefmarks*$typef;
                  
                  $duration = isset($_POST["duration"]) ? $_POST["duration"] : 0;
                              
                  // Get total_questions from form or calculate from question types
                  $total_questions = isset($_POST["total_questions"]) ? intval($_POST["total_questions"]) : 
                                  ($typea + $typeb + $typec + $typed + $typee + $typef);
                
                  // Convert starttime to SQL format
                  $starttime_input = isset($_POST["starttime"]) ? $_POST["starttime"] : date('d/m/Y h:i A');
                  $starttime_sql = null;
                  if ($starttime_input) {
                      $datetime_obj = DateTime::createFromFormat('d/m/Y h:i A', $starttime_input);
                      if ($datetime_obj) {
                          // Set timezone for the datetime object
                          $datetime_obj->setTimezone(new DateTimeZone($_SESSION['timezone']));
                          $starttime_sql = $datetime_obj->format('Y-m-d H:i:s');
                      } else {
                          $starttime_sql = date('Y-m-d H:i:s');
                      }
                  } else {
                      $starttime_sql = date('Y-m-d H:i:s');
                  }

                  $endtime_sql = null;
                  // Check if endtime is provided by the user
                  if (isset($_POST["endtime"]) && !empty($_POST["endtime"])) {
                      $endtime_input = $_POST["endtime"];
                      $endtime_obj = DateTime::createFromFormat('d/m/Y h:i A', $endtime_input);
                      if ($endtime_obj) {
                          // Set timezone for the datetime object
                          $endtime_obj->setTimezone(new DateTimeZone($_SESSION['timezone']));
                          $endtime_sql = $endtime_obj->format('Y-m-d H:i:s');
                      } else {
                          // Fallback to calculating endtime based on duration
                          if ($starttime_sql && isset($_POST["duration"]) && is_numeric($_POST["duration"])) {
                              try {
                                  $start_datetime_obj = new DateTime($starttime_sql, new DateTimeZone($_SESSION['timezone']));
                                  $duration_minutes = intval($_POST["duration"]);
                                  $start_datetime_obj->add(new DateInterval('PT' . $duration_minutes . 'M'));
                                  $endtime_sql = $start_datetime_obj->format('Y-m-d H:i:s');
                              } catch (Exception $e) {
                                  error_log("Error calculating endtime: " . $e->getMessage());
                                  $endtime_sql = date('Y-m-d H:i:s', strtotime($starttime_sql . ' +1 day'));
                              }
                          } else {
                              $endtime_sql = date('Y-m-d H:i:s', strtotime($starttime_sql . ' +1 day'));
                          }
                      }
                  } else {
                      // Old behavior: calculate endtime based on duration
                      if ($starttime_sql && isset($_POST["duration"]) && is_numeric($_POST["duration"])) {
                          try {
                              $start_datetime_obj = new DateTime($starttime_sql, new DateTimeZone($_SESSION['timezone']));
                              $duration_minutes = intval($_POST["duration"]);
                              $start_datetime_obj->add(new DateInterval('PT' . $duration_minutes . 'M'));
                              $endtime_sql = $start_datetime_obj->format('Y-m-d H:i:s');
                          } catch (Exception $e) {
                              error_log("Error calculating endtime: " . $e->getMessage());
                              $endtime_sql = date('Y-m-d H:i:s', strtotime($starttime_sql . ' +1 day'));
                          }
                      } else {
                          $endtime_sql = date('Y-m-d H:i:s', strtotime($starttime_sql . ' +1 day'));
                      }
                  }

                  $attempts = isset($_POST["attempts"]) && is_numeric($_POST["attempts"]) && intval($_POST["attempts"]) > 0 ? intval($_POST["attempts"]) : 1;
                  $subject_id = isset($_POST["subject_id"]) && !empty($_POST["subject_id"]) ? intval($_POST["subject_id"]) : NULL;   
                  $class_id = isset($_POST["class_id"]) && !empty($_POST["class_id"]) ? intval($_POST["class_id"]) : NULL; 
                  
                  // Get section from optional dropdown or direct input
                  $section_id = isset($_POST["section_id"]) && !empty($_POST["section_id"]) ? intval($_POST["section_id"]) : NULL;
                  $section = NULL;
                  
                  // First check if a direct section name was provided in the text field
                  if (!empty($_POST['section'])) {
                      $section = strtoupper(trim($conn->real_escape_string($_POST['section'])));
                  } 
                  // If no direct name but section_id is provided, get the section name from the ID
                  else if ($section_id) {
                      $section_query = "SELECT section_name FROM class_sections WHERE id = ?";
                      $section_stmt = $conn->prepare($section_query);
                      $section_stmt->bind_param("i", $section_id);
                      $section_stmt->execute();
                      $section_result = $section_stmt->get_result();
                      if ($section_row = $section_result->fetch_assoc()) {
                          $section = strtoupper(trim($section_row['section_name']));
                      }
                  }
                  
                  $sql = "INSERT INTO quizconfig (
                    quiznumber, quizname, subject_id, class_id, chapter_ids, topic_ids,
                    starttime, endtime, duration, maxmarks, 
                    mcq, numerical, dropdown, fill, short, essay,
                    mcqmarks, numericalmarks, dropdownmarks, fillmarks, shortmarks, essaymarks,
                    typea, typeamarks, typeb, typebmarks, 
                    typec, typecmarks, typed, typedmarks, 
                    typee, typeemarks, typef, typefmarks,
                    total_questions, is_random, attempts, section
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?
                )";

                  if (!empty($quiznumber)) {
                      $stmt = $conn->prepare($sql);
                      
                      // Get selected questions
                      $selected_questions = array();
                      if(isset($_POST['selected_mcq'])) $selected_questions[] = $_POST['selected_mcq'];
                      if(isset($_POST['selected_numerical'])) $selected_questions[] = $_POST['selected_numerical'];
                      if(isset($_POST['selected_dropdown'])) $selected_questions[] = $_POST['selected_dropdown'];
                      if(isset($_POST['selected_fillblanks'])) $selected_questions[] = $_POST['selected_fillblanks'];
                      if(isset($_POST['selected_short'])) $selected_questions[] = $_POST['selected_short'];
                      if(isset($_POST['selected_essay'])) $selected_questions[] = $_POST['selected_essay'];
                      
                      $selected_question_ids = implode(',', array_filter($selected_questions));
                      
                      // Check if this is a manual selection but no questions were selected
                      $is_manual_selection = isset($_POST["is_manual_selection"]) ? intval($_POST["is_manual_selection"]) : 0;
                      if($is_manual_selection && empty($selected_question_ids)) {
                          echo '<div class="message error">Error: You selected manual question selection but did not choose any questions. Please select questions or use random selection.</div>';
                          exit;
                      }
                      
                      // Update typea-typef values if manual selection is being used
                      if($is_manual_selection && !empty($selected_question_ids)) {
                          // Count how many of each type were selected
                          $typea = isset($_POST['selected_mcq']) ? substr_count($_POST['selected_mcq'], ',') + 1 : 0;
                          $typeb = isset($_POST['selected_numerical']) ? substr_count($_POST['selected_numerical'], ',') + 1 : 0;
                          $typec = isset($_POST['selected_dropdown']) ? substr_count($_POST['selected_dropdown'], ',') + 1 : 0;
                          $typed = isset($_POST['selected_fillblanks']) ? substr_count($_POST['selected_fillblanks'], ',') + 1 : 0;
                          $typee = isset($_POST['selected_short']) ? substr_count($_POST['selected_short'], ',') + 1 : 0;
                          $typef = isset($_POST['selected_essay']) ? substr_count($_POST['selected_essay'], ',') + 1 : 0;
                          
                          // Recalculate total questions
                          $total_questions = $typea + $typeb + $typec + $typed + $typee + $typef;
                          
                          // Recalculate max marks
                          $maxmarks = $typeamarks*$typea + $typebmarks*$typeb + $typecmarks*$typec + 
                                      $typedmarks*$typed + $typeemarks*$typee + $typefmarks*$typef;
                      }
                      
                      // Set values for the additional required fields
                      $mcq = $typea;  // Use typea value for mcq
                      $mcqmarks = $typeamarks;
                      $numerical = $typeb;  // Use typeb value for numerical
                      $numericalmarks = $typebmarks;
                      $dropdown = $typec;  // Use typec value for dropdown
                      $dropdownmarks = $typecmarks;
                      $fill = $typed;  // Use typed value for fill
                      $fillmarks = $typedmarks;
                      $short = $typee;  // Use typee value for short
                      $shortmarks = $typeemarks;
                      $essay = $typef;  // Use typef value for essay
                      $essaymarks = $typefmarks;
                      
                      // Debugging bind_param
                      $debug_types_string = "isiissssiiiiiiiiiiiiiiiiiiiiiiiiiiiiis";
                      $debug_variables_array = [
                          $quiznumber, $quizname, $subject_id, $class_id, $chapter_ids, $topic_ids,
                          $starttime_sql, $endtime_sql, $duration, $maxmarks,
                          $mcq, $numerical, $dropdown, $fill, $short, $essay,
                          $mcqmarks, $numericalmarks, $dropdownmarks, $fillmarks, $shortmarks, $essaymarks,
                          $typea, $typeamarks, $typeb, $typebmarks,
                          $typec, $typecmarks, $typed, $typedmarks,
                          $typee, $typeemarks, $typef, $typefmarks,
                          $total_questions, $is_random, $attempts, $section
                      ];
                      error_log("quizconfig.php bind_param check: Types string = '" . $debug_types_string . "', Length = " . strlen($debug_types_string) . ", Variables count = " . count($debug_variables_array));

                      // Using the $debug_types_string variable directly in bind_param
                      $stmt->bind_param($debug_types_string, 
                          $quiznumber, 
                          $quizname,
                          $subject_id,
                          $class_id,
                          $chapter_ids,
                          $topic_ids,
                          $starttime_sql,
                          $endtime_sql,
                          $duration,
                          $maxmarks,
                          $mcq,
                          $numerical,
                          $dropdown,
                          $fill,
                          $short,
                          $essay,
                          $mcqmarks,
                          $numericalmarks,
                          $dropdownmarks,
                          $fillmarks,
                          $shortmarks,
                          $essaymarks,
                          $typea,
                          $typeamarks,
                          $typeb,
                          $typebmarks,
                          $typec,
                          $typecmarks,
                          $typed,
                          $typedmarks,
                          $typee,
                          $typeemarks,
                          $typef,
                          $typefmarks,
                          $total_questions,
                          $is_random,
                          $attempts,
                          $section
                      );

                      if ($stmt->execute()) {
                          $quizid = $conn->insert_id;
                          // Clean up any existing random question entries for this quiz
                          $cleanup_sql = "DELETE FROM random_quiz_questions WHERE quizid = ?";
                          $cleanup_stmt = $conn->prepare($cleanup_sql);
                          if ($cleanup_stmt) {
                              $cleanup_stmt->bind_param("i", $quizid);
                              $cleanup_stmt->execute();
                              $cleanup_stmt->close();
                          }
                          
                          // Save selected questions in response table if not random
                          if (!$is_random && !empty($selected_question_ids)) {
                              $questions = explode(',', $selected_question_ids);
                              $serial = 1;
                              
                              // Update the total_questions count in the quizconfig table
                              $total_questions = count($questions);
                              $update_sql = "UPDATE quizconfig SET total_questions = ? WHERE quizid = ?";
                              $update_stmt = $conn->prepare($update_sql);
                              if ($update_stmt) {
                                  $update_stmt->bind_param("ii", $total_questions, $quizid);
                                  $update_stmt->execute();
                                  $update_stmt->close();
                                  error_log("Updated quizconfig total_questions to $total_questions for quizid=$quizid");
                              } else {
                                  error_log("Failed to prepare update statement for total_questions: " . $conn->error);
                              }
                              
                              // Log all selected question IDs for debugging
                              error_log("Selected question IDs for quiz $quizid: " . print_r($selected_question_ids, true));
                              
                              foreach ($questions as $qid) {
                                  // Determine question type based on prefix or table
                                  $qtype = '';
                                  if (strpos($qid, 'mcq_') === 0) $qtype = 'a';
                                  else if (strpos($qid, 'numerical_') === 0) $qtype = 'b';
                                  else if (strpos($qid, 'dropdown_') === 0) $qtype = 'c';
                                  else if (strpos($qid, 'fillblanks_') === 0) $qtype = 'd';
                                  else if (strpos($qid, 'short_') === 0) $qtype = 'e';
                                  else if (strpos($qid, 'essay_') === 0) $qtype = 'f';
                                  
                                  if (empty($qtype)) {
                                      error_log("Unknown question type for ID: " . $qid);
                                      continue;
                                  }
                                  
                                  // Extract numeric ID after the prefix
                                  $numeric_id = preg_replace('/^[a-z]+_/', '', $qid);
                                  $numeric_id = intval($numeric_id);
                                  
                                  if ($qtype && $numeric_id > 0) {
                                      // Use NULL for rollnumber and attempt=0 to indicate these are manually selected quiz questions, not student responses
                                      $sql_question = "INSERT INTO random_quiz_questions (quizid, qtype, qid, serialnumber) VALUES (?, ?, ?, ?)";
                                      $stmt_question = $conn->prepare($sql_question);
                                      
                                      if ($stmt_question) {
                                          $stmt_question->bind_param("isii", $quizid, $qtype, $numeric_id, $serial);
                                          $success = $stmt_question->execute();
                                          if (!$success) {
                                              error_log("Failed to insert question record: " . $stmt_question->error);
                                          } else {
                                              // Log successful insertion
                                              error_log("Inserted question: quizid=$quizid, qtype=$qtype, qid=$numeric_id, serial=$serial");
                                              // Increment serial only on successful insertion
                                              $serial++;
                                          }
                                          $stmt_question->close();
                                      } else {
                                          error_log("Failed to prepare statement for question insert: " . $conn->error);
                                      }
                                  } else {
                                      error_log("Invalid question data: qtype=$qtype, qid=$numeric_id");
                                  }
                              }
                              
                              // Additional debugging
                              if ($serial <= 1) {
                                  error_log("Warning: No questions were added to the quiz (quizid=$quizid). Selected question data: " . print_r($selected_question_ids, true));
                              } else {
                                  error_log("Successfully added " . ($serial - 1) . " questions to quiz (quizid=$quizid)");
                              }
                          } else if ($is_random && !empty($chapter_ids)) {
                              // For random quiz, preselect random questions from chapters and store them
                              $preselect_chapters = explode(',', $chapter_ids);
                              $chapter_ids_str = implode(',', $preselect_chapters);
                              $topic_ids_str = '';
                              if (!empty($topic_ids)) {
                                  $preselect_topics = explode(',', $topic_ids);
                                  $topic_ids_str = implode(',', $preselect_topics);
                              }
                              
                              // Function to get random questions of a specific type
                              function getRandomQuestions($conn, $type, $count, $chapter_ids_str, $topic_ids_str = '') {
                                  $questions = array();
                                  if ($count <= 0) {
                                      return $questions;
                                  }

                                  $table = '';
                                  switch($type) {
                                      case 'a': $table = 'mcqdb'; break;
                                      case 'b': $table = 'numericaldb'; break;
                                      case 'c': $table = 'dropdown'; break;
                                      case 'd': $table = 'fillintheblanks'; break;
                                      case 'e': $table = 'shortanswer'; break;
                                      case 'f': $table = 'essay'; break;
                                      default: return array();
                                  }

                                  // When topics are provided, try to distribute questions equally
                                  if (!empty($topic_ids_str)) {
                                      $topic_ids = array_filter(array_map('intval', explode(',', $topic_ids_str)));
                                      $num_topics = count($topic_ids);

                                      if ($num_topics > 0) {
                                          $base = intdiv($count, $num_topics);
                                          $remainder = $count % $num_topics;
                                          $selected = array();

                                          foreach ($topic_ids as $idx => $tid) {
                                              $limit = $base + ($remainder > 0 ? 1 : 0);
                                              if ($remainder > 0) { $remainder--; }
                                              if ($limit <= 0) { continue; }

                                              $sql = "SELECT id FROM $table WHERE chapter_id IN ($chapter_ids_str) AND topic_id = $tid ORDER BY RAND() LIMIT $limit";
                                              $result = $conn->query($sql);
                                              if ($result && $result->num_rows > 0) {
                                                  while ($row = $result->fetch_assoc()) {
                                                      $questions[] = array('type' => $type, 'id' => $row['id']);
                                                      $selected[] = $row['id'];
                                                  }
                                              }
                                          }

                                          // Fill any remaining slots from all topics excluding already selected IDs
                                          $remaining = $count - count($questions);
                                          if ($remaining > 0) {
                                              $exclude = '';
                                              if (!empty($selected)) {
                                                  $exclude = ' AND id NOT IN (' . implode(',', $selected) . ')';
                                              }
                                              $sql = "SELECT id FROM $table WHERE chapter_id IN ($chapter_ids_str) AND topic_id IN ($topic_ids_str)$exclude ORDER BY RAND() LIMIT $remaining";
                                              $result = $conn->query($sql);
                                              if ($result && $result->num_rows > 0) {
                                                  while ($row = $result->fetch_assoc()) {
                                                      $questions[] = array('type' => $type, 'id' => $row['id']);
                                                  }
                                              }
                                          }

                                          return $questions;
                                      }
                                  }

                                  // Fallback: random selection without topic distribution
                                  $sql = "SELECT id FROM $table WHERE chapter_id IN ($chapter_ids_str)";
                                  $sql .= " ORDER BY RAND() LIMIT $count";
                                  $result = $conn->query($sql);
                                  if ($result && $result->num_rows > 0) {
                                      while ($row = $result->fetch_assoc()) {
                                          $questions[] = array('type' => $type, 'id' => $row['id']);
                                      }
                                  }
                                  return $questions;
                              }
                              
                              // Get random questions for each type
                              $random_questions = array();
                              
                              if ($typea > 0) {
                                  $random_questions = array_merge($random_questions, getRandomQuestions($conn, 'a', $typea, $chapter_ids_str, $topic_ids_str));
                              }
                              if ($typeb > 0) {
                                  $random_questions = array_merge($random_questions, getRandomQuestions($conn, 'b', $typeb, $chapter_ids_str, $topic_ids_str));
                              }
                              if ($typec > 0) {
                                  $random_questions = array_merge($random_questions, getRandomQuestions($conn, 'c', $typec, $chapter_ids_str, $topic_ids_str));
                              }
                              if ($typed > 0) {
                                  $random_questions = array_merge($random_questions, getRandomQuestions($conn, 'd', $typed, $chapter_ids_str, $topic_ids_str));
                              }
                              if ($typee > 0) {
                                  $random_questions = array_merge($random_questions, getRandomQuestions($conn, 'e', $typee, $chapter_ids_str, $topic_ids_str));
                              }
                              if ($typef > 0) {
                                  $random_questions = array_merge($random_questions, getRandomQuestions($conn, 'f', $typef, $chapter_ids_str, $topic_ids_str));
                              }
                              
                              // Store randomly selected questions in the dedicated random_quiz_questions table
                              $serial = 1;
                              foreach ($random_questions as $q) {
                                  $sql_question = "INSERT INTO random_quiz_questions (quizid, qtype, qid, serialnumber) VALUES (?, ?, ?, ?)";
                                  $stmt_question = $conn->prepare($sql_question);
                                  
                                  // Fix: Add error logging to check if prepare failed
                                  if (!$stmt_question) {
                                      error_log("SQL prepare error: " . $conn->error);
                                      continue;
                                  }
                                  
                                  $stmt_question->bind_param("isii", $quizid, $q['type'], $q['id'], $serial);
                                  
                                  // Fix: Add error logging for execution failures
                                  if (!$stmt_question->execute()) {
                                      error_log("SQL execute error: " . $stmt_question->error);
                                  }
                                  $serial++;
                              }
                          }
                          
                          echo '<div class="message success">Quiz Set Successfully</div>';
                          echo '<script>document.getElementById("quiznumber").value = ' . ($quiznumber + 1) . ';</script>';
                      } else {
                          echo '<div class="message error">Error setting quiz: ' . htmlspecialchars($stmt->error) . '</div>';
                      }
                      $stmt->close();
                  } else {
                      echo '<div class="message error">Error: Quiz Number cannot be empty.</div>';
                  }
              }
            ?>
              </div>
            <?php endif; ?>
          </div> <!-- card -->
        </div> <!-- col -->
      </div> <!-- row -->
    </div> <!-- section -->
  </div> <!-- container -->
</div> <!-- main -->
</div> <!-- wrapper -->
    </main>
  </div>
</div>

  <!-- Core JS Files -->
  <script src="./assets/js/core/jquery.min.js" type="text/javascript"></script>
  <script src="./assets/js/core/popper.min.js" type="text/javascript"></script>
  <script src="./assets/js/core/bootstrap-material-design.min.js" type="text/javascript"></script>
  <script src="./assets/js/plugins/moment.min.js"></script>
  <script src="./assets/js/plugins/bootstrap-datetimepicker.js"></script>
  <script src="./assets/js/material-kit.js?v=2.0.4" type="text/javascript"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
  <script>    
  $(document).ready(function() {
      // Existing Select2 initialization
      $('#subject_id, #class_id, #chapter_ids, #section_id, #topic_ids').select2({
        width: '100%',
        minimumResultsForSearch: 10
      });

      // Load topics if chapters are preselected
      var initialChapters = $('#chapter_ids').val();
      if(initialChapters && initialChapters.length > 0) {
        loadTopics(initialChapters);
      }
      
      // Initialize datetimepicker
      $('.datetimepicker').datetimepicker({
        format: 'DD/MM/YYYY hh:mm A',
        icons: {
          time: "fa fa-clock-o",
          date: "fa fa-calendar",
          up: "fa fa-chevron-up",
          down: "fa fa-chevron-down",
          previous: 'fa fa-chevron-left',
          next: 'fa fa-chevron-right',
          today: 'fa fa-screenshot',
          clear: 'fa fa-trash',
          close: 'fa fa-remove'
        }
      });

        $("#chapter_ids").on("change", function() {
          var selectedChapters = $(this).val();
          $("#selectQuestionsBtn").prop("disabled", true);
          $("#random_quiz_checkbox").prop("disabled", true);
          loadTopics(selectedChapters);
          updateAvailableQuestions();
        });

      // Update available question counts when topic filter changes
        $(document).on('change', '#topic_ids', function() {
          var chapterIds = $("#chapter_ids").val();
          if(chapterIds && chapterIds.length > 0) {
            $("#random_quiz_checkbox").prop("disabled", false);
            if(!$("#random_quiz_checkbox").is(":checked")) {
              $("#selectQuestionsBtn").prop("disabled", false);
            } else {
              $("#selectQuestionsBtn").prop("disabled", true);
            }
          } else {
            $("#selectQuestionsBtn").prop("disabled", true);
            $("#random_quiz_checkbox").prop("disabled", true);
          }
          updateAvailableQuestions();
        });

      // Handle modal focus and state when it closes
      $('#questionSelectorModal')
        .on('hide.bs.modal', function() {
          if (questionSelectorTriggerElement) {
            $(questionSelectorTriggerElement).focus();
            questionSelectorTriggerElement = null;
          }
        })
        .on('hidden.bs.modal', function() {
          manualSelectionActive = false;
        });
    });
  </script>
<script src="./assets/js/dark-mode.js"></script>
<script src="./assets/js/sidebar.js"></script>
</body>
</html>
