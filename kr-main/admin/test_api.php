<?php
header('Content-Type: application/json');
session_start();
date_default_timezone_set('Asia/Kolkata');

// Database connection
$host = 'localhost';
$dbname = 'db_kr_assessly';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

$action = $_REQUEST['action'] ?? '';

// =====================================================================
// CREATE TEST
// =====================================================================
if ($action === 'create_test') {
    $testName = $_POST['test_name'];
    $sections = json_decode($_POST['sections'], true);
    $createdByType = 'admin';
    $createdById = 1;
    
    try {
        $pdo->beginTransaction();
        
        $testCode = 'TEST' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
        
        $stmt = $pdo->prepare("INSERT INTO tests (test_name, test_code, created_by_type, created_by_id, status) VALUES (?, ?, ?, ?, 'active')");
        $stmt->execute([$testName, $testCode, $createdByType, $createdById]);
        $testId = $pdo->lastInsertId();
        
        foreach ($sections as $section) {
            $stmt = $pdo->prepare("INSERT INTO test_sections (test_id, section_name, section_order, total_questions, questions_to_display, marks_per_question, negative_marks) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $testId,
                $section['section_name'],
                $section['section_order'],
                count($section['questions']),
                $section['questions_to_display'],
                $section['marks_per_question'],
                $section['negative_marks']
            ]);
            $sectionId = $pdo->lastInsertId();
            
            $questionStmt = $pdo->prepare("INSERT INTO questions (section_id, question_type, question_text, option_a, option_b, option_c, option_d, correct_answer) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            
            foreach ($section['questions'] as $q) {
                $questionType = strtolower($q['Question Type']);
                $questionText = $q['Question Text'];
                $optionA = $q['Option A'] ?? null;
                $optionB = $q['Option B'] ?? null;
                $optionC = $q['Option C'] ?? null;
                $optionD = $q['Option D'] ?? null;
                $correctAnswer = $q['Correct Answer'];
                
                $questionStmt->execute([
                    $sectionId,
                    $questionType,
                    $questionText,
                    $optionA,
                    $optionB,
                    $optionC,
                    $optionD,
                    $correctAnswer
                ]);
            }
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'test_code' => $testCode]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// =====================================================================
// GET TESTS
elseif ($action === 'get_tests') {
    $stmt = $pdo->query("
        SELECT t.*, 
               COUNT(DISTINCT ts.id) as section_count,
               CASE 
                   WHEN t.created_by_type = 'faculty' THEN CONCAT(fa.faculty_id, ' - ', fa.name_of_faculty, ' - ', fa.department)
                   WHEN t.created_by_type = 'admin' THEN 'Admin'
                   ELSE t.created_by_type
               END as created_by_name
        FROM tests t 
        LEFT JOIN test_sections ts ON t.id = ts.test_id
        LEFT JOIN faculty_account fa ON t.created_by_type = 'faculty' AND t.created_by_id = fa.id
        GROUP BY t.id 
        ORDER BY t.created_at DESC
    ");
    $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $tests]);
}

// DELETE TEST
elseif ($action === 'delete_test') {
    $testId = $_POST['test_id'];
    $stmt = $pdo->prepare("DELETE FROM tests WHERE id = ?");
    $stmt->execute([$testId]);
    echo json_encode(['success' => true]);
}

// GET STUDENTS
elseif ($action === 'get_students') {
    $stmt = $pdo->query("SELECT * FROM student_account WHERE status = 'active' ORDER BY name_of_student");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $students]);
}

// GET FILTER OPTIONS
elseif ($action === 'get_filter_options') {
    $deptStmt = $pdo->query("SELECT DISTINCT department FROM student_account WHERE status = 'active' ORDER BY department");
    $departments = $deptStmt->fetchAll(PDO::FETCH_COLUMN);
    
    $batchStmt = $pdo->query("SELECT DISTINCT batch FROM student_account WHERE status = 'active' ORDER BY batch DESC");
    $batches = $batchStmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode(['success' => true, 'departments' => $departments, 'batches' => $batches]);
}

// GET SECTIONS
elseif ($action === 'get_sections') {
    $dept = $_GET['dept'] ?? '';
    $batch = $_GET['batch'] ?? '';
    
    $sql = "SELECT DISTINCT section FROM student_account WHERE status = 'active'";
    $params = [];
    
    if ($dept) {
        $sql .= " AND department = ?";
        $params[] = $dept;
    }
    if ($batch) {
        $sql .= " AND batch = ?";
        $params[] = $batch;
    }
    
    $sql .= " ORDER BY section";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $sections = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode(['success' => true, 'sections' => $sections]);
}

// HOST TEST
elseif ($action === 'host_test') {
    $testId = $_POST['test_id'];
    $testDate = $_POST['test_date'];
    $startTime = $_POST['start_time'];
    $endTime = $_POST['end_time'];
    $testDuration = $_POST['test_duration'];
    $questionShuffle = $_POST['question_shuffle'];
    $optionShuffle = $_POST['option_shuffle'];
    $studentIds = json_decode($_POST['student_ids'], true);
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO hosted_tests (test_id, test_date, start_time, end_time, test_duration, question_shuffle, option_shuffle) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$testId, $testDate, $startTime, $endTime, $testDuration, $questionShuffle, $optionShuffle]);
        $hostedTestId = $pdo->lastInsertId();
        
        $studentStmt = $pdo->prepare("INSERT INTO hosted_test_students (hosted_test_id, student_id) VALUES (?, ?)");
        foreach ($studentIds as $studentId) {
            $studentStmt->execute([$hostedTestId, $studentId]);
        }
        
        $pdo->commit();
        echo json_encode(['success' => true]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// =====================================================================
// GET REPORTS - WORKS FOR BOTH ADMIN AND FACULTY
// =====================================================================
elseif ($action === 'get_reports') {
    $stmt = $pdo->query("
        SELECT 
            ht.id AS hosted_test_id,
            ht.test_id,
            ht.test_date,
            ht.start_time,
            ht.end_time,
            ht.test_duration,

            t.test_name,
            t.test_code,

            CASE 
                WHEN t.created_by_type = 'faculty' 
                    THEN CONCAT(fa.faculty_id, ' - ', fa.name_of_faculty, ' - ', fa.department)
                WHEN t.created_by_type = 'admin' 
                    THEN 'Admin'
                ELSE t.created_by_type
            END AS created_by_name,

            COUNT(DISTINCT hts.id) AS total_students,
            COUNT(DISTINCT CASE 
                WHEN hts.test_status = 'completed' THEN hts.id 
            END) AS completed_students

        FROM hosted_tests ht
        LEFT JOIN tests t ON ht.test_id = t.id
        LEFT JOIN faculty_account fa 
            ON t.created_by_type = 'faculty'
            AND t.created_by_id = fa.id
        LEFT JOIN hosted_test_students hts 
            ON ht.id = hts.hosted_test_id

        GROUP BY ht.id
        ORDER BY ht.test_date DESC, ht.start_time DESC
    ");

    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate dynamic status based on current date/time
    $currentDateTime = new DateTime();
    foreach ($reports as &$report) {
        $testDate = $report['test_date'];
        $startTime = $report['start_time'];
        $endTime = $report['end_time'];
        
        $testStart = new DateTime("$testDate $startTime");
        $testEnd = new DateTime("$testDate $endTime");
        
        // Handle tests that cross midnight
        if ($testEnd <= $testStart) {
            $testEnd->modify('+1 day');
        }
        
        if ($currentDateTime < $testStart) {
            $report['status'] = 'pending';
        } elseif ($currentDateTime >= $testStart && $currentDateTime <= $testEnd) {
            $report['status'] = 'ongoing';
        } else {
            $report['status'] = 'completed';
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $reports
    ]);
}


// DELETE HOSTED TEST
elseif ($action === 'delete_hosted_test') {
    $hostedTestId = $_POST['hosted_test_id'] ?? null;

    if (!$hostedTestId) {
        echo json_encode(['success' => false, 'message' => 'Hosted Test ID missing']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Check if any student has completed or started the test
        $checkStmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM hosted_test_students 
            WHERE hosted_test_id = ? 
            AND (test_status = 'completed' OR start_time IS NOT NULL)
        ");
        $checkStmt->execute([$hostedTestId]);
        $hasActivity = $checkStmt->fetchColumn();

        if ($hasActivity > 0) {
            $pdo->rollBack();
            echo json_encode([
                'success' => false, 
                'message' => 'Cannot delete - students have already started or completed this test'
            ]);
            exit;
        }

        // Safe to delete - no student activity
        $stmt = $pdo->prepare("DELETE FROM hosted_test_students WHERE hosted_test_id = ?");
        $stmt->execute([$hostedTestId]);

        $stmt = $pdo->prepare("DELETE FROM hosted_tests WHERE id = ?");
        $stmt->execute([$hostedTestId]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Hosted test deleted successfully'
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

// UPDATE HOSTED TEST
elseif ($action === 'update_hosted_test') {
    $pdo->beginTransaction();

    try {
        $hostedTestId = $_POST['hosted_test_id'];
        $studentIds = json_decode($_POST['student_ids'], true);

        // Check if any student has already started the test
        $checkStmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM hosted_test_students 
            WHERE hosted_test_id = ? 
            AND (test_status = 'completed' OR start_time IS NOT NULL)
        ");
        $checkStmt->execute([$hostedTestId]);
        $hasStarted = $checkStmt->fetchColumn();

        if ($hasStarted > 0) {
            $pdo->rollBack();
            echo json_encode([
                'success' => false, 
                'message' => 'Cannot edit - some students have already started or completed the test'
            ]);
            exit;
        }

        // Update hosted_tests table
        $stmt = $pdo->prepare("
            UPDATE hosted_tests
            SET test_date = ?, start_time = ?, end_time = ?, test_duration = ?,
                question_shuffle = ?, option_shuffle = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['test_date'],
            $_POST['start_time'],
            $_POST['end_time'],
            $_POST['test_duration'],
            $_POST['question_shuffle'],
            $_POST['option_shuffle'],
            $hostedTestId
        ]);

        // Get existing student IDs
        $stmt = $pdo->prepare("SELECT student_id FROM hosted_test_students WHERE hosted_test_id = ?");
        $stmt->execute([$hostedTestId]);
        $existingIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        // Find differences
        $toRemove = array_diff($existingIds, $studentIds);
        $toAdd = array_diff($studentIds, $existingIds);

        // Remove students not in new list
        if (!empty($toRemove)) {
            $placeholders = implode(',', array_fill(0, count($toRemove), '?'));
            $stmt = $pdo->prepare("
                DELETE FROM hosted_test_students 
                WHERE hosted_test_id = ? AND student_id IN ($placeholders)
            ");
            $stmt->execute(array_merge([$hostedTestId], $toRemove));
        }

        // Add new students
        if (!empty($toAdd)) {
            $stmt = $pdo->prepare("
                INSERT INTO hosted_test_students (hosted_test_id, student_id)
                VALUES (?, ?)
            ");
            foreach ($toAdd as $sid) {
                $stmt->execute([$hostedTestId, $sid]);
            }
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Hosted test updated successfully'
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// GET REPORT DETAILS
elseif ($action === 'get_report_details') {
    $hostedTestId = $_GET['hosted_test_id'];
    
    $stmt = $pdo->prepare("
        SELECT ht.*, t.test_name 
        FROM hosted_tests ht 
        JOIN tests t ON ht.test_id = t.id 
        WHERE ht.id = ?
    ");
    $stmt->execute([$hostedTestId]);
    $testInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("
        SELECT * FROM test_sections 
        WHERE test_id = ? 
        ORDER BY section_order
    ");
    $stmt->execute([$testInfo['test_id']]);
    $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("
        SELECT 
            hts.*,
            sa.name_of_student as name,
            sa.register_number,
            sa.department,
            sa.batch,
            sa.section
        FROM hosted_test_students hts
        JOIN student_account sa ON hts.student_id = sa.id
        WHERE hts.hosted_test_id = ?
        ORDER BY sa.name_of_student
    ");
    $stmt->execute([$hostedTestId]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($students as &$student) {
        $stmt = $pdo->prepare("
            SELECT section_id, section_score 
            FROM section_scores 
            WHERE hosted_test_student_id = ?
        ");
        $stmt->execute([$student['id']]);
        $sectionScores = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $student['section_scores'] = $sectionScores;
    }
    
    $avgScore = 0;
    $highestScore = 0;
    $completedCount = 0;
    
    foreach ($students as $student) {
        if ($student['test_status'] === 'completed') {
            $completedCount++;
            $avgScore += $student['total_score'];
            if ($student['total_score'] > $highestScore) {
                $highestScore = $student['total_score'];
            }
        }
    }
    
    if ($completedCount > 0) {
        $avgScore = $avgScore / $completedCount;
    }
    
    echo json_encode([
        'success' => true,
        'data' => array_merge($testInfo, [
            'sections' => $sections,
            'students' => $students,
            'total_students' => count($students),
            'completed_students' => $completedCount,
            'avg_score' => $avgScore,
            'highest_score' => $highestScore
        ])
    ]);
}

// TOGGLE SHOW ANSWERS
elseif ($action === 'toggle_show_answers') {
    $hostedTestId = $_POST['hosted_test_id'];
    $showAnswers = $_POST['show_answers'];
    
    $stmt = $pdo->prepare("UPDATE hosted_tests SET show_answers = ? WHERE id = ?");
    $stmt->execute([$showAnswers, $hostedTestId]);
    
    echo json_encode(['success' => true]);
}

// GET TEST FOR STUDENT
elseif ($action === 'get_test_for_student') {
    $testCode = $_GET['test_code'];
    $studentId = $_GET['student_id'] ?? null;
    
    $stmt = $pdo->prepare("
        SELECT t.*, ht.id as hosted_test_id, ht.test_date, ht.start_time, ht.end_time, 
               ht.test_duration, ht.question_shuffle, ht.option_shuffle
        FROM tests t
        LEFT JOIN hosted_tests ht ON t.id = ht.test_id
        WHERE t.test_code = ? AND t.status = 'active'
        ORDER BY ht.test_date DESC, ht.start_time DESC
        LIMIT 1
    ");
    $stmt->execute([$testCode]);
    $test = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$test) {
        echo json_encode(['success' => false, 'message' => 'Test not found']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM test_sections WHERE test_id = ? ORDER BY section_order");
    $stmt->execute([$test['id']]);
    $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($sections as &$section) {
        $stmt = $pdo->prepare("SELECT * FROM questions WHERE section_id = ?");
        $stmt->execute([$section['id']]);
        $allQuestions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($test['question_shuffle'] === 'yes') {
            shuffle($allQuestions);
        }
        $section['questions'] = array_slice($allQuestions, 0, $section['questions_to_display']);
        
        if ($test['option_shuffle'] === 'yes') {
            foreach ($section['questions'] as &$question) {
                if ($question['question_type'] === 'mcq') {
                    $options = [
                        'A' => $question['option_a'],
                        'B' => $question['option_b'],
                        'C' => $question['option_c'],
                        'D' => $question['option_d']
                    ];
                    $options = array_filter($options);
                    $correctAnswer = $question['correct_answer'];
                    $question['original_correct'] = $correctAnswer;
                }
            }
        }
    }
    
    $test['sections'] = $sections;
    
    echo json_encode(['success' => true, 'data' => $test]);
}

// SUBMIT TEST
elseif ($action === 'submit_test') {
    $hostedTestId = $_POST['hosted_test_id'];
    $studentId = $_POST['student_id'];
    $answers = json_decode($_POST['answers'], true);
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("SELECT id FROM hosted_test_students WHERE hosted_test_id = ? AND student_id = ?");
        $stmt->execute([$hostedTestId, $studentId]);
        $htsRecord = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$htsRecord) {
            $stmt = $pdo->prepare("INSERT INTO hosted_test_students (hosted_test_id, student_id, start_time) VALUES (?, ?, NOW())");
            $stmt->execute([$hostedTestId, $studentId]);
            $htsId = $pdo->lastInsertId();
        } else {
            $htsId = $htsRecord['id'];
        }
        
        $stmt = $pdo->prepare("UPDATE hosted_test_students SET test_status = 'completed', submit_time = NOW() WHERE id = ?");
        $stmt->execute([$htsId]);
        
        $totalScore = 0;
        $sectionScores = [];
        
        foreach ($answers as $answer) {
            $questionId = $answer['question_id'];
            $studentAnswer = $answer['answer'];
            
            $stmt = $pdo->prepare("
                SELECT q.*, ts.marks_per_question, ts.negative_marks, ts.id as section_id
                FROM questions q
                JOIN test_sections ts ON q.section_id = ts.id
                WHERE q.id = ?
            ");
            $stmt->execute([$questionId]);
            $question = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $isCorrect = 0;
            $marksObtained = 0;
            
            if (strtolower(trim($studentAnswer)) === strtolower(trim($question['correct_answer']))) {
                $isCorrect = 1;
                $marksObtained = $question['marks_per_question'];
            } else if (!empty($studentAnswer)) {
                $marksObtained = -$question['negative_marks'];
            }
            
            $stmt = $pdo->prepare("INSERT INTO student_answers (hosted_test_student_id, question_id, student_answer, is_correct, marks_obtained) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$htsId, $questionId, $studentAnswer, $isCorrect, $marksObtained]);
            
            $sectionId = $question['section_id'];
            if (!isset($sectionScores[$sectionId])) {
                $sectionScores[$sectionId] = 0;
            }
            $sectionScores[$sectionId] += $marksObtained;
            $totalScore += $marksObtained;
        }
        
        foreach ($sectionScores as $sectionId => $score) {
            $stmt = $pdo->prepare("INSERT INTO section_scores (hosted_test_student_id, section_id, section_score) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE section_score = ?");
            $stmt->execute([$htsId, $sectionId, $score, $score]);
        }
        
        $stmt = $pdo->prepare("UPDATE hosted_test_students SET total_score = ? WHERE id = ?");
        $stmt->execute([$totalScore, $htsId]);
        
        $pdo->commit();
        echo json_encode(['success' => true, 'total_score' => $totalScore]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// =====================================================================
// TEST PREVIEW & EDIT ENDPOINTS
// =====================================================================

// GET TEST DETAILS (for preview/edit page)
elseif ($action === 'get_test_details') {
    $testId = $_GET['test_id'];
    
    try {
        // Get test info
        $stmt = $pdo->prepare("SELECT * FROM tests WHERE id = ?");
        $stmt->execute([$testId]);
        $test = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$test) {
            echo json_encode(['success' => false, 'message' => 'Test not found']);
            exit;
        }
        
        // Get sections
        $stmt = $pdo->prepare("SELECT * FROM test_sections WHERE test_id = ? ORDER BY section_order ASC");
        $stmt->execute([$testId]);
        $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get questions for each section
        foreach ($sections as &$section) {
            $stmt = $pdo->prepare("SELECT * FROM questions WHERE section_id = ? ORDER BY id ASC");
            $stmt->execute([$section['id']]);
            $section['questions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $section['total_questions'] = count($section['questions']);
        }
        
        echo json_encode([
            'success' => true,
            'data' => array_merge($test, ['sections' => $sections])
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// UPDATE SECTION
elseif ($action === 'update_section') {
    $sectionId = $_POST['section_id'];
    $sectionName = $_POST['section_name'];
    $questionsToDisplay = $_POST['questions_to_display'];
    $marksPerQuestion = $_POST['marks_per_question'];
    $negativeMarks = $_POST['negative_marks'];
    
    try {
        // Verify questions_to_display doesn't exceed total_questions
        $checkStmt = $pdo->prepare("SELECT COUNT(*) as total FROM questions WHERE section_id = ?");
        $checkStmt->execute([$sectionId]);
        $totalQuestions = $checkStmt->fetchColumn();
        
        if ($questionsToDisplay > $totalQuestions) {
            echo json_encode(['success' => false, 'message' => "Cannot display more questions ($questionsToDisplay) than available ($totalQuestions)"]);
            exit;
        }
        
        $stmt = $pdo->prepare("UPDATE test_sections SET section_name = ?, questions_to_display = ?, marks_per_question = ?, negative_marks = ? WHERE id = ?");
        $stmt->execute([$sectionName, $questionsToDisplay, $marksPerQuestion, $negativeMarks, $sectionId]);
        
        echo json_encode(['success' => true, 'message' => 'Section updated successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// DELETE SECTION
elseif ($action === 'delete_section') {
    $sectionId = $_POST['section_id'];
    
    try {
        $pdo->beginTransaction();
        
        // Delete questions first
        $stmt = $pdo->prepare("DELETE FROM questions WHERE section_id = ?");
        $stmt->execute([$sectionId]);
        
        // Delete section
        $stmt = $pdo->prepare("DELETE FROM test_sections WHERE id = ?");
        $stmt->execute([$sectionId]);
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Section deleted successfully']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// UPDATE QUESTION
elseif ($action === 'update_question') {
    $questionId = $_POST['question_id'];
    $questionText = $_POST['question_text'];
    $correctAnswer = $_POST['correct_answer'];
    $optionA = $_POST['option_a'] ?? null;
    $optionB = $_POST['option_b'] ?? null;
    $optionC = $_POST['option_c'] ?? null;
    $optionD = $_POST['option_d'] ?? null;
    
    try {
        $stmt = $pdo->prepare("UPDATE questions SET question_text = ?, correct_answer = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ? WHERE id = ?");
        $stmt->execute([$questionText, $correctAnswer, $optionA, $optionB, $optionC, $optionD, $questionId]);
        
        echo json_encode(['success' => true, 'message' => 'Question updated successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// DELETE QUESTION
elseif ($action === 'delete_question') {
    $questionId = $_POST['question_id'];
    
    try {
        $pdo->beginTransaction();
        
        // Get section_id before deletion
        $stmt = $pdo->prepare("SELECT section_id FROM questions WHERE id = ?");
        $stmt->execute([$questionId]);
        $sectionId = $stmt->fetchColumn();
        
        // Delete question
        $stmt = $pdo->prepare("DELETE FROM questions WHERE id = ?");
        $stmt->execute([$questionId]);
        
        // Update section total questions count
        if ($sectionId) {
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE section_id = ?");
            $countStmt->execute([$sectionId]);
            $newTotal = $countStmt->fetchColumn();
            
            $updateStmt = $pdo->prepare("UPDATE test_sections SET total_questions = ? WHERE id = ?");
            $updateStmt->execute([$newTotal, $sectionId]);
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Question deleted successfully']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ADD QUESTION
elseif ($action === 'add_question') {
    $sectionId = $_POST['section_id'];
    $questionType = $_POST['question_type'];
    $questionText = $_POST['question_text'];
    $correctAnswer = $_POST['correct_answer'];
    $optionA = $_POST['option_a'] ?? null;
    $optionB = $_POST['option_b'] ?? null;
    $optionC = $_POST['option_c'] ?? null;
    $optionD = $_POST['option_d'] ?? null;
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO questions (section_id, question_type, question_text, option_a, option_b, option_c, option_d, correct_answer) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$sectionId, $questionType, $questionText, $optionA, $optionB, $optionC, $optionD, $correctAnswer]);
        $questionId = $pdo->lastInsertId();
        
        // Update section total questions count
        $updateStmt = $pdo->prepare("UPDATE test_sections SET total_questions = total_questions + 1 WHERE id = ?");
        $updateStmt->execute([$sectionId]);
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Question added successfully', 'question_id' => $questionId]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ADD QUESTIONS TO SECTION (from Excel)
elseif ($action === 'add_questions_to_section') {
    $sectionId = $_POST['section_id'];
    $questions = json_decode($_POST['questions'], true);
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO questions (section_id, question_type, question_text, option_a, option_b, option_c, option_d, correct_answer) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($questions as $q) {
            $questionType = strtolower($q['Question Type']);
            $questionText = $q['Question Text'];
            $optionA = $q['Option A'] ?? null;
            $optionB = $q['Option B'] ?? null;
            $optionC = $q['Option C'] ?? null;
            $optionD = $q['Option D'] ?? null;
            $correctAnswer = $q['Correct Answer'];
            
            $stmt->execute([$sectionId, $questionType, $questionText, $optionA, $optionB, $optionC, $optionD, $correctAnswer]);
        }
        
        // Update section total questions count
        $updateStmt = $pdo->prepare("UPDATE test_sections SET total_questions = total_questions + ? WHERE id = ?");
        $updateStmt->execute([count($questions), $sectionId]);
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => count($questions) . ' questions added successfully', 'count' => count($questions)]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ADD NEW SECTION WITH QUESTIONS
elseif ($action === 'add_section') {
    $testId = $_POST['test_id'];
    $sectionName = $_POST['section_name'];
    $questionsToDisplay = $_POST['questions_to_display'];
    $marksPerQuestion = $_POST['marks_per_question'];
    $negativeMarks = $_POST['negative_marks'];
    $questions = json_decode($_POST['questions'], true);
    
    try {
        $pdo->beginTransaction();
        
        // Get next section order
        $stmt = $pdo->prepare("SELECT COALESCE(MAX(section_order), 0) + 1 as next_order FROM test_sections WHERE test_id = ?");
        $stmt->execute([$testId]);
        $nextOrder = $stmt->fetch(PDO::FETCH_ASSOC)['next_order'];
        
        // Insert section
        $stmt = $pdo->prepare("INSERT INTO test_sections (test_id, section_name, section_order, total_questions, questions_to_display, marks_per_question, negative_marks) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$testId, $sectionName, $nextOrder, count($questions), $questionsToDisplay, $marksPerQuestion, $negativeMarks]);
        $sectionId = $pdo->lastInsertId();
        
        // Insert questions
        $questionStmt = $pdo->prepare("INSERT INTO questions (section_id, question_type, question_text, option_a, option_b, option_c, option_d, correct_answer) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($questions as $q) {
            $questionType = strtolower($q['Question Type']);
            $questionText = $q['Question Text'];
            $optionA = $q['Option A'] ?? null;
            $optionB = $q['Option B'] ?? null;
            $optionC = $q['Option C'] ?? null;
            $optionD = $q['Option D'] ?? null;
            $correctAnswer = $q['Correct Answer'];
            
            $questionStmt->execute([$sectionId, $questionType, $questionText, $optionA, $optionB, $optionC, $optionD, $correctAnswer]);
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Section and questions added successfully', 'section_id' => $sectionId]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// GET HOSTED TEST DETAILS (FOR EDIT)
elseif ($action === 'get_hosted_test_details') {

    $hostedTestId = $_GET['hosted_test_id'] ?? null;

    if (!$hostedTestId) {
        echo json_encode(['success' => false, 'message' => 'Hosted Test ID missing']);
        exit;
    }

    try {
        // Hosted test details
        $stmt = $pdo->prepare("SELECT * FROM hosted_tests WHERE id = ?");
        $stmt->execute([$hostedTestId]);
        $hostedTest = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$hostedTest) {
            echo json_encode(['success' => false, 'message' => 'Hosted test not found']);
            exit;
        }

        // Assigned students
        $stmt = $pdo->prepare("SELECT student_id FROM hosted_test_students WHERE hosted_test_id = ?");
        $stmt->execute([$hostedTestId]);
        $studentIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        echo json_encode([
            'success' => true,
            'data' => array_merge($hostedTest, [
                'student_ids' => $studentIds
            ])
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>