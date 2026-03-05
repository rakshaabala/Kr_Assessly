<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
session_start();
date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/../dbconn.php';

$pdo = getDBConnection();
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

$userType = $_SESSION['user_type'] ?? '';
$accountType = $_SESSION['account_type'] ?? '';
$currentUserId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$isAdminUser = $accountType === 'admin';

if (!in_array($userType, ['faculty', 'admin'], true) || !in_array($accountType, ['faculty', 'admin'], true) || $currentUserId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

function getRequestIntValue($key) {
    if (isset($_POST[$key])) {
        $value = $_POST[$key];
    } elseif (isset($_GET[$key])) {
        $value = $_GET[$key];
    } else {
        return null;
    }

    if (!is_numeric($value)) {
        return null;
    }

    return (int) $value;
}

function facultyOwnsTest($pdo, $facultyId, $testId) {
    $stmt = $pdo->prepare("SELECT 1 FROM tests WHERE id = ? AND created_by_type = 'faculty' AND created_by_id = ? LIMIT 1");
    $stmt->execute([$testId, $facultyId]);
    return (bool) $stmt->fetchColumn();
}

function facultyOwnsHostedTest($pdo, $facultyId, $hostedTestId) {
    $stmt = $pdo->prepare("
        SELECT 1
        FROM hosted_tests ht
        INNER JOIN tests t ON ht.test_id = t.id
        WHERE ht.id = ?
          AND t.created_by_type = 'faculty'
          AND t.created_by_id = ?
        LIMIT 1
    ");
    $stmt->execute([$hostedTestId, $facultyId]);
    return (bool) $stmt->fetchColumn();
}

function facultyOwnsSection($pdo, $facultyId, $sectionId) {
    $stmt = $pdo->prepare("
        SELECT 1
        FROM test_sections ts
        INNER JOIN tests t ON ts.test_id = t.id
        WHERE ts.id = ?
          AND t.created_by_type = 'faculty'
          AND t.created_by_id = ?
        LIMIT 1
    ");
    $stmt->execute([$sectionId, $facultyId]);
    return (bool) $stmt->fetchColumn();
}

function facultyOwnsQuestion($pdo, $facultyId, $questionId) {
    $stmt = $pdo->prepare("
        SELECT 1
        FROM questions q
        INNER JOIN test_sections ts ON q.section_id = ts.id
        INNER JOIN tests t ON ts.test_id = t.id
        WHERE q.id = ?
          AND t.created_by_type = 'faculty'
          AND t.created_by_id = ?
        LIMIT 1
    ");
    $stmt->execute([$questionId, $facultyId]);
    return (bool) $stmt->fetchColumn();
}

function denyForbiddenAction() {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You do not have permission to access this resource.']);
    exit;
}

if (!$isAdminUser) {
    $ownershipMap = [
        'delete_test' => ['type' => 'test', 'param' => 'test_id'],
        'host_test' => ['type' => 'test', 'param' => 'test_id'],
        'delete_hosted_test' => ['type' => 'hosted_test', 'param' => 'hosted_test_id'],
        'update_hosted_test' => ['type' => 'hosted_test', 'param' => 'hosted_test_id'],
        'get_report_details' => ['type' => 'hosted_test', 'param' => 'hosted_test_id'],
        'toggle_show_answers' => ['type' => 'hosted_test', 'param' => 'hosted_test_id'],
        'get_hosted_test_details' => ['type' => 'hosted_test', 'param' => 'hosted_test_id'],
        'get_test_details' => ['type' => 'test', 'param' => 'test_id'],
        'add_section' => ['type' => 'test', 'param' => 'test_id'],
        'update_section' => ['type' => 'section', 'param' => 'section_id'],
        'delete_section' => ['type' => 'section', 'param' => 'section_id'],
        'add_question' => ['type' => 'section', 'param' => 'section_id'],
        'add_questions_to_section' => ['type' => 'section', 'param' => 'section_id'],
        'update_question' => ['type' => 'question', 'param' => 'question_id'],
        'delete_question' => ['type' => 'question', 'param' => 'question_id']
    ];

    if (isset($ownershipMap[$action])) {
        $resourceType = $ownershipMap[$action]['type'];
        $resourceParam = $ownershipMap[$action]['param'];
        $resourceId = getRequestIntValue($resourceParam);

        if ($resourceId === null || $resourceId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Required ID is missing or invalid.']);
            exit;
        }

        $hasAccess = false;

        if ($resourceType === 'test') {
            $hasAccess = facultyOwnsTest($pdo, $currentUserId, $resourceId);
        } elseif ($resourceType === 'hosted_test') {
            $hasAccess = facultyOwnsHostedTest($pdo, $currentUserId, $resourceId);
        } elseif ($resourceType === 'section') {
            $hasAccess = facultyOwnsSection($pdo, $currentUserId, $resourceId);
        } elseif ($resourceType === 'question') {
            $hasAccess = facultyOwnsQuestion($pdo, $currentUserId, $resourceId);
        }

        if (!$hasAccess) {
            denyForbiddenAction();
        }
    }
}

// =====================================================================
// EXISTING ENDPOINTS (MODIFIED FOR FACULTY FILTERING)
// =====================================================================

// CREATE TEST
if ($action === 'create_test') {
    $testName = $_POST['test_name'];
    $sections = json_decode($_POST['sections'], true);
    $createdByType = 'faculty';
    $createdById = $currentUserId;
    
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

// GET TESTS - MODIFIED TO FILTER BY LOGGED-IN FACULTY
elseif ($action === 'get_tests') {

    // Get session data
    $userType = $_SESSION['account_type'] ?? null;
    $userId = $_SESSION['user_id'] ?? null;

    // Build WHERE clause based on user type
    $whereClause = "";
    $params = [];

    if ($userType === 'faculty' && $userId) {
        // Faculty can only see their own tests
        $whereClause = "WHERE t.created_by_type = 'faculty' AND t.created_by_id = ?";
        $params[] = $userId;
    } elseif ($userType === 'admin') {
        // Admin can see all tests (no WHERE clause needed)
        $whereClause = "";
    } else {
        // Invalid session - return empty result
        echo json_encode(['success' => true, 'data' => []]);
        exit;
    }

    $sql = "
        SELECT 
            t.id,
            t.test_name,
            t.test_code,
            t.created_by_type,
            t.created_at,
            COUNT(DISTINCT ts.id) AS section_count,

            /* CREATED BY DISPLAY */
            CASE 
                WHEN t.created_by_type = 'faculty' THEN 
                    CONCAT(f.faculty_id, ' - ', f.name_of_faculty, ' (', f.department, ')')
                WHEN t.created_by_type = 'admin' THEN 
                    CONCAT('Admin - ', a.username)
                ELSE 'Unknown'
            END AS created_by_display

        FROM tests t
        LEFT JOIN test_sections ts 
            ON ts.test_id = t.id

        LEFT JOIN faculty_account f 
            ON t.created_by_type = 'faculty' 
           AND t.created_by_id = f.id

        LEFT JOIN admin_account a 
            ON t.created_by_type = 'admin' 
           AND t.created_by_id = a.id

        $whereClause

        GROUP BY t.id
        ORDER BY t.created_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode([
        'success' => true,
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
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
    
    error_log("get_students returning " . count($students) . " students");
    foreach ($students as $student) {
        error_log("Student: {$student['name_of_student']} - Dept: {$student['department']}, Batch: {$student['batch']}, Section: {$student['section']}");
    }
    
    echo json_encode(['success' => true, 'data' => $students]);
}

// GET FILTER OPTIONS
elseif ($action === 'get_filter_options') {
    $deptStmt = $pdo->query("SELECT DISTINCT department FROM student_account WHERE status = 'active' ORDER BY department");
    $departments = $deptStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Order batches by extracting the start year for proper sorting
    $batchStmt = $pdo->query("SELECT DISTINCT batch FROM student_account WHERE status = 'active' ORDER BY CAST(SUBSTRING(batch, 1, 4) AS UNSIGNED) DESC");
    $batches = $batchStmt->fetchAll(PDO::FETCH_COLUMN);
    
    error_log("get_filter_options - Departments: " . json_encode($departments));
    error_log("get_filter_options - Batches: " . json_encode($batches));
    
    echo json_encode(['success' => true, 'departments' => $departments, 'batches' => $batches]);
}

// GET SECTIONS
elseif ($action === 'get_sections') {
    $dept = $_GET['dept'] ?? '';
    $batch = $_GET['batch'] ?? '';
    
    error_log("get_sections called with dept: '$dept', batch: '$batch'");
    
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
    
    error_log("SQL Query: $sql with params: " . json_encode($params));
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $sections = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    error_log("Found sections: " . json_encode($sections));
    
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
    $sectionConfigs = isset($_POST['section_configs']) ? json_decode($_POST['section_configs'], true) : [];
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO hosted_tests (test_id, test_date, start_time, end_time, test_duration, question_shuffle, option_shuffle) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$testId, $testDate, $startTime, $endTime, $testDuration, $questionShuffle, $optionShuffle]);
        $hostedTestId = $pdo->lastInsertId();

        if (is_array($sectionConfigs)) {
            $updateSection = $pdo->prepare("
                UPDATE test_sections
                SET questions_to_display = ?, marks_per_question = ?
                WHERE id = ? AND test_id = ?
            ");
            foreach ($sectionConfigs as $cfg) {
                if (!isset($cfg['id'])) continue;
                $qid = (int)$cfg['id'];
                $display = max(1, (int)($cfg['questions_to_display'] ?? 1));
                $marks = (float)($cfg['marks_per_question'] ?? 0);
                $updateSection->execute([$display, $marks, $qid, $testId]);
            }
        }
        
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


// GET REPORTS - MODIFIED TO FILTER BY LOGGED-IN FACULTY
elseif ($action === 'get_reports') {
    
    // Get session data
    $userType = $_SESSION['account_type'] ?? null;
    $userId = $_SESSION['user_id'] ?? null;

    // Build WHERE clause based on user type
    $whereClause = "";
    $params = [];

    if ($userType === 'faculty' && $userId) {
        // Faculty can only see reports for their own tests
        $whereClause = "WHERE t.created_by_type = 'faculty' AND t.created_by_id = ?";
        $params[] = $userId;
    } elseif ($userType === 'admin') {
        // Admin can see all reports
        $whereClause = "";
    } else {
        // Invalid session
        echo json_encode(['success' => true, 'data' => []]);
        exit;
    }

    $sql = "
        SELECT ht.*, 
               t.test_name,
               COUNT(DISTINCT hts.id) as total_students,
               COUNT(DISTINCT CASE WHEN hts.test_status = 'completed' THEN hts.id END) as completed_students
        FROM hosted_tests ht
        JOIN tests t ON ht.test_id = t.id
        LEFT JOIN hosted_test_students hts ON ht.id = hts.hosted_test_id
        $whereClause
        GROUP BY ht.id
        ORDER BY ht.test_date DESC, ht.start_time DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $reports]);
}

/* ============================
   DELETE HOSTED TEST  ✅✅✅
============================ */
elseif ($action === 'delete_hosted_test') {

    $hostedTestId = $_POST['hosted_test_id'] ?? null;

    if (!$hostedTestId) {
        echo json_encode(['success' => false, 'message' => 'Hosted Test ID missing']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // CASCADE will delete students, answers, scores automatically
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
        $testId = (int)($_POST['test_id'] ?? 0);
        $sectionConfigs = isset($_POST['section_configs']) ? json_decode($_POST['section_configs'], true) : [];

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

        if ($testId > 0 && is_array($sectionConfigs)) {
            $updateSection = $pdo->prepare("
                UPDATE test_sections
                SET questions_to_display = ?, marks_per_question = ?
                WHERE id = ? AND test_id = ?
            ");
            foreach ($sectionConfigs as $cfg) {
                if (!isset($cfg['id'])) {
                    continue;
                }
                $qid = (int)$cfg['id'];
                $display = max(1, (int)($cfg['questions_to_display'] ?? 1));
                $marks = (float)($cfg['marks_per_question'] ?? 0);
                $updateSection->execute([$display, $marks, $qid, $testId]);
            }
        }

        // Remove old students
        $stmt = $pdo->prepare("DELETE FROM hosted_test_students WHERE hosted_test_id = ?");
        $stmt->execute([$hostedTestId]);

        // Insert new students
        $stmt = $pdo->prepare("
            INSERT INTO hosted_test_students (hosted_test_id, student_id)
            VALUES (?, ?)
        ");

        foreach ($studentIds as $sid) {
            $stmt->execute([$hostedTestId, $sid]);
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

    if (!in_array($showAnswers, ['yes', 'no'], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid show_answers value']);
        exit;
    }
    
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
// NEW ENDPOINTS FOR TEST PREVIEW & EDIT PAGE (Non-Conflicting)
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
        $stmt = $pdo->prepare("
            SELECT * FROM hosted_tests WHERE id = ?
        ");
        $stmt->execute([$hostedTestId]);
        $hostedTest = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$hostedTest) {
            echo json_encode(['success' => false, 'message' => 'Hosted test not found']);
            exit;
        }

        // Assigned students
        $stmt = $pdo->prepare("
            SELECT student_id FROM hosted_test_students WHERE hosted_test_id = ?
        ");
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

// GET TEST SECTIONS CONFIG (for hosting UI)
elseif ($action === 'get_test_sections_config') {
    $testId = $_GET['test_id'] ?? null;
    if (!$testId) {
        echo json_encode(['success' => false, 'message' => 'Test ID missing']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT id, section_name, total_questions, questions_to_display, marks_per_question, negative_marks
        FROM test_sections
        WHERE test_id = ?
        ORDER BY section_order ASC
    ");
    $stmt->execute([$testId]);
    $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'sections' => $sections]);
}

else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
