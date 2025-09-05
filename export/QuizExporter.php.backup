<?php
/**
 * QuizExporter Class
 * Handles exporting quizzes to various formats (HTML/Print)
 */

// Check if accessed directly
if (!defined('QUIZ_PORTAL')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

class QuizExporter {
    
    /**
     * Export quiz data to printable HTML (for PDF printing)
     * 
     * @param array $quiz Quiz data
     * @param array $questions Quiz questions data
     * @return string HTML content
     */
    public static function exportToPDF($quiz, $questions) {
        // Build HTML content for printing
        $html = self::generatePrintableHTML($quiz, $questions, true);
        return $html;
    }
    
    /**
     * Export quiz data to Word-compatible HTML
     * 
     * @param array $quiz Quiz data
     * @param array $questions Quiz questions data
     * @return string HTML content
     */
    public static function exportToWord($quiz, $questions) {
        // Build HTML content for Word
        $html = self::generatePrintableHTML($quiz, $questions, false);
        return $html;
    }
    
    /**
     * Generate printable HTML for both PDF and Word exports
     * 
     * @param array $quiz Quiz data
     * @param array $questions Quiz questions data
     * @param bool $isPDF Whether this is for PDF (true) or Word (false)
     * @return string HTML content
     */
    private static function generatePrintableHTML($quiz, $questions, $isPDF = true) {
        $title = htmlspecialchars($quiz['quizname']) . ' - Quiz #' . htmlspecialchars($quiz['quiznumber']);
        $totalMarks = $quiz['maxmarks'] ?? 0;
        $duration = $quiz['duration'] ?? 0;
        
        $html = '<!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>Created by Sir Hassan Tariq - ' . $title . '</title>
                    <style>
                        body { 
                            font-family: Arial, sans-serif; 
                            margin: 5px;
                            line-height: 1.2;
                            color: #333;
                            background-color: #fff;
                            font-size: 10pt;
                        }
                        .exam-header {
                            text-align: center;
                            margin-bottom: 5px;
                            border-bottom: 1px solid #333;
                            padding-bottom: 5px;
                        }
                        .exam-logo {
                            font-size: 16pt;
                            font-weight: bold;
                            margin-bottom: 2px;
                            letter-spacing: 0px;
                            color: #222;
                        }
                        .exam-title {
                            font-size: 14pt;
                            font-weight: bold;
                            margin-bottom: 2px;
                            text-transform: uppercase;
                        }
                        .exam-subtitle {
                            font-size: 11pt;
                            margin-bottom: 2px;
                        }
                        .exam-info-box {
                            display: flex;
                            justify-content: space-between;
                            margin: 5px 0;
                            border: 1px solid #ddd;
                            padding: 3px;
                            background-color: #f9f9f9;
                            font-size: 9pt;
                        }
                        .exam-info-section {
                            flex: 1;
                            padding: 0 3px;
                        }
                        .exam-info-title {
                            font-weight: bold;
                            margin-bottom: 2px;
                        }
                        .instructions {
                            margin: 5px 0;
                            padding: 4px;
                            border: 1px solid #ddd;
                            background-color: #f5f5f5;
                            page-break-inside: avoid;
                            font-size: 9pt;
                        }
                        .instructions-title {
                            font-weight: bold;
                            font-size: 10pt;
                            margin-bottom: 2px;
                            text-transform: uppercase;
                        }
                        .instructions ol {
                            margin: 2px 0 2px 15px;
                            padding: 0;
                        }
                        .instructions li {
                            margin-bottom: 1px;
                        }
                        .questions-section {
                            margin-top: 5px;
                        }
                        .question { 
                            margin-bottom: 6px; 
                            border: 1px solid #eee; 
                            padding: 5px;
                            page-break-inside: avoid;
                            background-color: #fff;
                            box-shadow: none;
                        }
                        .question-number { 
                            font-weight: bold;
                            margin-bottom: 3px;
                            display: flex;
                            justify-content: space-between;
                            font-size: 10pt;
                        }
                        .question-marks {
                            color: #777;
                            font-size: 9pt;
                        }
                        .question-text { 
                            margin-bottom: 5px; 
                            font-size: 10.5pt;
                        }
                        .options { 
                            margin-left: 10px; 
                        }
                        .option-item {
                            margin-bottom: 1px;
                            display: flex;
                            font-size: 10pt;
                        }
                        .option-letter {
                            min-width: 15px;
                            font-weight: bold;
                        }
                        .footer { 
                            text-align: center; 
                            font-size: 8pt;
                            margin-top: 8px;
                            border-top: 1px solid #eee;
                            padding-top: 3px;
                            color: #999;
                        }
                        .answer-space {
                            height: 15px;
                            border-bottom: 1px dashed #eee;
                            margin-top: 3px;
                        }
                        @media print {
                            body { 
                                margin: 0;
                                padding: 5px;
                                font-size: 10pt;
                            }
                            .no-print {
                                display: none;
                            }
                            .question {
                                break-inside: avoid;
                                box-shadow: none;
                                border: 1px solid #eee;
                                margin-bottom: 5px;
                                padding: 4px;
                            }
                            .exam-info-box, .instructions {
                                margin: 4px 0;
                                padding: 3px;
                            }
                        }
                        table.marks-distribution {
                            width: 100%;
                            border-collapse: collapse;
                            margin-bottom: 5px;
                            font-size: 8pt;
                        }
                        table.marks-distribution th,
                        table.marks-distribution td {
                            border: 1px solid #eee;
                            padding: 2px;
                            text-align: center;
                        }
                        table.marks-distribution th {
                            background-color: #f5f5f5;
                        }
                    </style>
                </head>
                <body>';
        
        // Add print button (only shows on screen, not when printing)
        if ($isPDF) {
            $html .= '<div class="no-print" style="text-align: right; margin-bottom: 10px;">
                        <button onclick="window.print();" style="padding: 6px 12px; background-color: #4CAF50; color: white; border: none; cursor: pointer; font-size: 14px;">
                            Print / Save as PDF
                        </button>
                      </div>';
        }
        
        // Exam header section
        $html .= '<div class="exam-header">
                    <div class="exam-logo">Created by Sir Hassan Tariq</div>
                    <div class="exam-title">' . htmlspecialchars($quiz['quizname']) . '</div>
                    <div class="exam-subtitle">Class: ' . htmlspecialchars($quiz['class_name'] ?? 'N/A') . ' | Subject: ' . htmlspecialchars($quiz['subject_name'] ?? 'N/A') . '</div>
                  </div>';
        
        // Quiz information in single row
        $html .= '<div class="exam-info-box">
                    <div class="exam-info-section">
                        <div class="exam-info-title">Quiz Info</div>
                        <div>Quiz: #' . htmlspecialchars($quiz['quiznumber']) . '</div>
                        <div>Time: ' . htmlspecialchars($duration) . ' min</div>
                        <div>Marks: ' . htmlspecialchars($totalMarks) . '</div>
                    </div>
                    <div class="exam-info-section">
                        <div class="exam-info-title">Class Info</div>
                        <div>Class: ' . htmlspecialchars($quiz['class_name'] ?? 'N/A') . '</div>
                        <div>Subject: ' . htmlspecialchars($quiz['subject_name'] ?? 'N/A') . '</div>
                        <div>Section: ' . htmlspecialchars($quiz['section'] ?? 'N/A') . '</div>
                    </div>
                    <div class="exam-info-section">
                        <div class="exam-info-title">Student Info</div>
                        <div>Name:_________________ Roll#:_______</div>
                    </div>
                  </div>';
        
        
        
        // Instructions section (simplified)
        $html .= '<div class="instructions">
                    <div class="instructions-title">Instructions:</div>
                    <ol>
                        <li>Total Questions: ' . array_sum([$quiz['mcq'], $quiz['numerical'], $quiz['dropdown'], $quiz['fill'], $quiz['short'], $quiz['essay']]) . ' | Marks: ' . $totalMarks . ' | Time: ' . $duration . ' min</li>
                        <li>Read each question carefully. Circle the correct answer for MCQs.</li>
                    </ol>
                  </div>';
        
        // Questions section
        $html .= '<div class="questions-section">';
        
        if (!empty($questions)) {
            $questionNumber = 1;
            $mcqIndex = 1;  // For labeling MCQ options as a, b, c, d
            
            foreach ($questions as $question) {
                $html .= '<div class="question">
                            <div class="question-number">
                                <div>Q' . $questionNumber . ' <span style="font-weight: normal; font-style: italic; color: #666;">(' . htmlspecialchars($question['questiontype']) . ')</span></div>
                                <div class="question-marks">' . htmlspecialchars($question['marks']) . ' marks</div>
                            </div>
                            <div class="question-text">' . htmlspecialchars($question['questiontext']) . '</div>';
                
                // Different question types
                if ($question['questiontype'] == 'mcq') {
                    $html .= '<div class="options">';
                    $options = json_decode($question['options'], true);
                    if (is_array($options)) {
                        $optionLetters = ['a', 'b', 'c', 'd'];
                        foreach ($options as $index => $option) {
                            if (isset($optionLetters[$index])) {
                                $html .= '<div class="option-item">
                                            <div class="option-letter">' . $optionLetters[$index] . ')</div>
                                            <div>' . htmlspecialchars($option) . '</div>
                                          </div>';
                            }
                        }
                    }
                    $html .= '</div>';
                    $mcqIndex++;
                } else if ($question['questiontype'] == 'dropdown') {
                    $html .= '<div class="options">';
                    $options = json_decode($question['options'], true);
                    if (is_array($options)) {
                        $html .= 'Options: ';
                        $html .= implode(', ', array_map('htmlspecialchars', $options));
                    }
                    $html .= '</div>';
                } else {
                    // For other question types, add space for answer
                    $html .= '<div class="answer-space"></div>';
                }
                
                $html .= '</div>';
                $questionNumber++;
            }
        } else {
            $html .= '<p>No questions available for this quiz.</p>';
        }
        
        $html .= '</div>'; // End of questions section
        
        // Footer
        $html .= '<div class="footer">
                    <div>End of Question Paper</div>
                    <div>Generated by studyht.com Quiz System on ' . date('Y-m-d H:i:s') . '</div>
                    <div>Designed & Maintained By Sir Hassan Tariq</div>
                  </div>';
        
        $html .= '</body></html>';
        
        return $html;
    }
} 