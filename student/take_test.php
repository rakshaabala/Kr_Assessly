<?php
// Enhanced test taking with persistent shuffling, section navigation, and advanced features
session_start();

// Set timezone to Indian Standard Time
date_default_timezone_set('Asia/Kolkata');

// Check if student is logged in
if (!isset($_SESSION['student_id']) || $_SESSION['user_type'] !== 'student') {
    header("Location: ../index.php");
    exit();
}

define('MAX_SECURITY_VIOLATIONS', 10);
define('FORCE_SUBMIT_ON_SECURITY_VIOLATIONS', false);
define('REFRESH_RESUME_GRACE_SECONDS', 120);
define('LOW_INTERNET_THRESHOLD_MBPS', 1.00);
define('NETWORK_SAMPLE_INTERVAL_SECONDS', 10);
define('LOW_NETWORK_STREAK_FOR_RELAXATION', 2);
define('AUTO_SAVE_MIN_INTERVAL_SECONDS', 1);
define('MAX_ANSWER_LENGTH', 1000);

function jsonResponse(array $payload, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit();
}

function getStudentTestContext(PDO $pdo, int $studentId, int $hostedTestId): ?array {
    $stmt = $pdo->prepare("
        SELECT
            hts.id AS student_test_id,
            hts.test_status,
            hts.start_time AS student_start_time,
            hts.submit_time,
            ht.test_date,
            ht.start_time,
            ht.end_time,
            ht.test_duration
        FROM hosted_test_students hts
        INNER JOIN hosted_tests ht ON ht.id = hts.hosted_test_id
        WHERE hts.student_id = :student_id
          AND hts.hosted_test_id = :hosted_test_id
        LIMIT 1
    ");
    $stmt->execute([
        'student_id' => $studentId,
        'hosted_test_id' => $hostedTestId
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function getAllowedEndDateTime(array $context): DateTime {
    $testStart = new DateTime($context['test_date'] . ' ' . $context['start_time']);
    $testEnd = new DateTime($context['test_date'] . ' ' . $context['end_time']);
    if ($testEnd <= $testStart) {
        $testEnd->modify('+1 day');
    }

    $studentStart = !empty($context['student_start_time'])
        ? new DateTime($context['student_start_time'])
        : clone $testStart;
    $durationEnd = clone $studentStart;
    $durationEnd->modify('+' . (int)$context['test_duration'] . ' minutes');

    return $durationEnd < $testEnd ? $durationEnd : $testEnd;
}

function isAttemptWindowValid(array $context): bool {
    if (empty($context['student_start_time'])) {
        return false;
    }

    $testStart = new DateTime($context['test_date'] . ' ' . $context['start_time']);
    $testEnd = getAllowedEndDateTime($context);
    $now = new DateTime();

    return $now >= $testStart && $now <= $testEnd;
}

function getAllowedQuestionIdMap(PDO $pdo, int $hostedTestId): array {
    $stmt = $pdo->prepare("
        SELECT q.id
        FROM questions q
        INNER JOIN test_sections ts ON ts.id = q.section_id
        INNER JOIN hosted_tests ht ON ht.test_id = ts.test_id
        WHERE ht.id = :hosted_test_id
    ");
    $stmt->execute(['hosted_test_id' => $hostedTestId]);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    return array_fill_keys(array_map('intval', $ids), true);
}

function finalizeSubmission(PDO $pdo, int $studentTestId): array {
    $pdo->beginTransaction();

    try {
        $lockStmt = $pdo->prepare("
            SELECT submit_time
            FROM hosted_test_students
            WHERE id = :student_test_id
            FOR UPDATE
        ");
        $lockStmt->execute(['student_test_id' => $studentTestId]);
        $lockRow = $lockStmt->fetch(PDO::FETCH_ASSOC);

        if (!$lockRow) {
            throw new Exception('Test attempt not found.');
        }
        if (!empty($lockRow['submit_time'])) {
            throw new Exception('Test already submitted.');
        }

        $submitStmt = $pdo->prepare("
            UPDATE hosted_test_students
            SET submit_time = NOW(), test_status = 'completed'
            WHERE id = :student_test_id
        ");
        $submitStmt->execute(['student_test_id' => $studentTestId]);

        $answersStmt = $pdo->prepare("
            SELECT
                sa.question_id,
                sa.student_answer,
                q.correct_answer,
                ts.id AS section_id,
                ts.marks_per_question,
                ts.negative_marks
            FROM student_answers sa
            INNER JOIN questions q ON q.id = sa.question_id
            INNER JOIN test_sections ts ON ts.id = q.section_id
            WHERE sa.hosted_test_student_id = :student_test_id
        ");
        $answersStmt->execute(['student_test_id' => $studentTestId]);
        $answers = $answersStmt->fetchAll(PDO::FETCH_ASSOC);

        $totalScore = 0.0;
        $sectionScores = [];

        foreach ($answers as $answer) {
            $studentAnswer = strtolower(trim((string)$answer['student_answer']));
            $correctAnswer = strtolower(trim((string)$answer['correct_answer']));
            $isCorrect = ($studentAnswer !== '' && $studentAnswer === $correctAnswer);

            $marks = $isCorrect
                ? (float)$answer['marks_per_question']
                : ((trim((string)$answer['student_answer']) === '') ? 0.0 : -1 * (float)$answer['negative_marks']);

            $updateStmt = $pdo->prepare("
                UPDATE student_answers
                SET is_correct = :is_correct, marks_obtained = :marks
                WHERE hosted_test_student_id = :student_test_id
                  AND question_id = :question_id
            ");
            $updateStmt->execute([
                'is_correct' => $isCorrect ? 1 : 0,
                'marks' => $marks,
                'student_test_id' => $studentTestId,
                'question_id' => (int)$answer['question_id']
            ]);

            $sectionId = (int)$answer['section_id'];
            if (!isset($sectionScores[$sectionId])) {
                $sectionScores[$sectionId] = 0.0;
            }
            $sectionScores[$sectionId] += $marks;
            $totalScore += $marks;
        }

        $upsertSectionScoreStmt = $pdo->prepare("
            INSERT INTO section_scores (hosted_test_student_id, section_id, section_score)
            VALUES (:student_test_id, :section_id, :section_score)
            ON DUPLICATE KEY UPDATE section_score = VALUES(section_score)
        ");
        foreach ($sectionScores as $sectionId => $score) {
            $upsertSectionScoreStmt->execute([
                'student_test_id' => $studentTestId,
                'section_id' => $sectionId,
                'section_score' => $score
            ]);
        }

        $totalStmt = $pdo->prepare("
            UPDATE hosted_test_students
            SET total_score = :total_score
            WHERE id = :student_test_id
        ");
        $totalStmt->execute([
            'total_score' => $totalScore,
            'student_test_id' => $studentTestId
        ]);

        $pdo->commit();
        return [
            'total_score' => $totalScore,
            'answers_processed' => count($answers)
        ];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

// Handle form submissions FIRST (before any HTML output)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    include('../dbconn.php');
    $pdo = getDBConnection();
    if (!$pdo) {
        jsonResponse([
            'success' => false,
            'message' => 'Database connection failed.'
        ], 500);
    }
    
    $studentId = $_SESSION['student_id'];
    $hostedTestId = isset($_GET['test_id']) ? (int)$_GET['test_id'] : 0;

    if ($hostedTestId <= 0) {
        jsonResponse([
            'success' => false,
            'message' => 'Invalid test request.'
        ], 400);
    }

    $context = getStudentTestContext($pdo, $studentId, $hostedTestId);
    if (!$context) {
        jsonResponse([
            'success' => false,
            'message' => 'Unauthorized test access.'
        ], 403);
    }

    $violationSessionKey = 'test_violation_count_' . $hostedTestId;

    if (isset($_POST['record_violation'])) {
        $currentViolations = (int)($_SESSION[$violationSessionKey] ?? 0);
        $currentViolations++;
        $_SESSION[$violationSessionKey] = $currentViolations;

        $forceSubmit = FORCE_SUBMIT_ON_SECURITY_VIOLATIONS && ($currentViolations >= MAX_SECURITY_VIOLATIONS);
        if ($forceSubmit && empty($context['submit_time'])) {
            try {
                finalizeSubmission($pdo, (int)$context['student_test_id']);
            } catch (Exception $e) {
                // no-op: frontend will still be redirected to report/test list
            }
        }

        jsonResponse([
            'success' => true,
            'violations' => $currentViolations,
            'max_violations' => MAX_SECURITY_VIOLATIONS,
            'force_submit' => $forceSubmit
        ]);
    }

    if (isset($_POST['heartbeat'])) {
        if (!empty($context['submit_time'])) {
            jsonResponse([
                'success' => false,
                'submitted' => true
            ], 409);
        }

        if (!isAttemptWindowValid($context)) {
            try {
                finalizeSubmission($pdo, (int)$context['student_test_id']);
            } catch (Exception $e) {
                // no-op
            }

            jsonResponse([
                'success' => false,
                'expired' => true,
                'force_submit' => true
            ], 403);
        }

        jsonResponse([
            'success' => true,
            'violations' => (int)($_SESSION[$violationSessionKey] ?? 0),
            'max_violations' => MAX_SECURITY_VIOLATIONS
        ]);
    }

    if (!empty($context['submit_time'])) {
        jsonResponse([
            'success' => false,
            'message' => 'Test already submitted.'
        ], 409);
    }

    if (!isAttemptWindowValid($context)) {
        jsonResponse([
            'success' => false,
            'message' => 'Your test session has expired. Please submit the test.'
        ], 403);
    }

    $allowedQuestionMap = getAllowedQuestionIdMap($pdo, $hostedTestId);
    if (empty($allowedQuestionMap)) {
        jsonResponse([
            'success' => false,
            'message' => 'Question map not available for this test.'
        ], 500);
    }

    $studentTestId = (int)$context['student_test_id'];

    // Auto-save answers
    if (isset($_POST['auto_save'])) {
        $lastAutoSaveKey = 'last_auto_save_' . $hostedTestId;
        $lastAutoSaveAt = (int)($_SESSION[$lastAutoSaveKey] ?? 0);
        if ((time() - $lastAutoSaveAt) < AUTO_SAVE_MIN_INTERVAL_SECONDS) {
            jsonResponse([
                'success' => false,
                'message' => 'Too many auto-save requests.'
            ], 429);
        }
        $_SESSION[$lastAutoSaveKey] = time();

        if (isset($_POST['answers']) && is_array($_POST['answers'])) {
            foreach ($_POST['answers'] as $questionId => $answer) {
                $questionId = (int)$questionId;
                if (!isset($allowedQuestionMap[$questionId])) {
                    continue;
                }

                $answer = trim((string)$answer);
                if (strlen($answer) > MAX_ANSWER_LENGTH) {
                    $answer = substr($answer, 0, MAX_ANSWER_LENGTH);
                }

                $checkStmt = $pdo->prepare("
                    SELECT id
                    FROM student_answers
                    WHERE hosted_test_student_id = :student_test_id
                      AND question_id = :question_id
                ");
                $checkStmt->execute(['student_test_id' => $studentTestId, 'question_id' => $questionId]);
                $existingAnswer = $checkStmt->fetch(PDO::FETCH_ASSOC);

                if ($existingAnswer) {
                    $updateStmt = $pdo->prepare("
                        UPDATE student_answers
                        SET student_answer = :answer, answered_at = NOW()
                        WHERE hosted_test_student_id = :student_test_id
                          AND question_id = :question_id
                    ");
                    $updateStmt->execute([
                        'answer' => $answer,
                        'student_test_id' => $studentTestId,
                        'question_id' => $questionId
                    ]);
                } else {
                    $insertStmt = $pdo->prepare("
                        INSERT INTO student_answers (hosted_test_student_id, question_id, student_answer, answered_at)
                        VALUES (:student_test_id, :question_id, :answer, NOW())
                    ");
                    $insertStmt->execute([
                        'student_test_id' => $studentTestId,
                        'question_id' => $questionId,
                        'answer' => $answer
                    ]);
                }
            }
        }

        jsonResponse(['success' => true]);
    }
    
    // Save shuffle configuration
    if (isset($_POST['save_shuffle'])) {
        $shuffleConfig = $_POST['shuffle_config'] ?? '{}';
        if (strlen((string)$shuffleConfig) > 10000) {
            jsonResponse(['success' => false, 'message' => 'Invalid shuffle config payload.'], 400);
        }

        $stmt = $pdo->prepare("
            UPDATE hosted_test_students
            SET shuffle_config = :shuffle_config
            WHERE student_id = :student_id
              AND hosted_test_id = :hosted_test_id
              AND submit_time IS NULL
        ");
        $stmt->execute([
            'shuffle_config' => $shuffleConfig,
            'student_id' => $studentId,
            'hosted_test_id' => $hostedTestId
        ]);

        jsonResponse(['success' => true]);
    }
    
    // Submit test
    if (isset($_POST['submit_test'])) {
        try {
            if (!empty($_POST['answers']) && is_array($_POST['answers'])) {
                foreach ($_POST['answers'] as $questionId => $answer) {
                    $questionId = (int)$questionId;
                    if (!isset($allowedQuestionMap[$questionId])) {
                        continue;
                    }

                    $answer = trim((string)$answer);
                    if (strlen($answer) > MAX_ANSWER_LENGTH) {
                        $answer = substr($answer, 0, MAX_ANSWER_LENGTH);
                    }

                    $upsertStmt = $pdo->prepare("
                        INSERT INTO student_answers (hosted_test_student_id, question_id, student_answer, answered_at)
                        VALUES (:student_test_id, :question_id, :answer, NOW())
                        ON DUPLICATE KEY UPDATE student_answer = VALUES(student_answer), answered_at = NOW()
                    ");
                    $upsertStmt->execute([
                        'student_test_id' => $studentTestId,
                        'question_id' => $questionId,
                        'answer' => $answer
                    ]);
                }
            }

            $result = finalizeSubmission($pdo, $studentTestId);

            jsonResponse([
                'success' => true,
                'message' => 'Test submitted successfully',
                'redirect_url' => 'test_report.php?test_id=' . $hostedTestId,
                'total_score' => $result['total_score'],
                'answers_processed' => $result['answers_processed']
            ]);
        } catch (Exception $e) {
            jsonResponse([
                'success' => false,
                'message' => 'Error submitting test: ' . $e->getMessage()
            ], 500);
        }
    }

    jsonResponse([
        'success' => false,
        'message' => 'Invalid request.'
    ], 400);
    }

// Database connection for page display
include('../dbconn.php');
$pdo = getDBConnection();
if (!$pdo) {
    die("Database connection failed.");
}

$studentId = $_SESSION['student_id'];
$studentName = $_SESSION['student_name'] ?? 'Student';
$studentRegisterNumber = $_SESSION['register_number'] ?? '';

// Get test ID from URL
$hostedTestId = isset($_GET['test_id']) ? (int)$_GET['test_id'] : 0;

if (!$hostedTestId) {
    header("Location: test_list.php");
    exit();
}

// Check if student has access to this test and if it's active
$testCheckQuery = "
    SELECT 
        ht.id as hosted_test_id,
        ht.test_id,
        ht.test_date,
        ht.start_time,
        ht.end_time,
        ht.test_duration,
        ht.question_shuffle,
        ht.option_shuffle,
        ht.show_answers,
        t.test_name,
        t.test_code,
        hts.id as student_test_id,
        hts.test_status as student_test_status,
        hts.start_time as student_start_time,
        hts.submit_time,
        hts.shuffle_config
    FROM hosted_tests ht
    INNER JOIN hosted_test_students hts ON ht.id = hts.hosted_test_id
    INNER JOIN tests t ON ht.test_id = t.id
    WHERE hts.student_id = :student_id 
    AND ht.id = :hosted_test_id
";

$stmt = $pdo->prepare($testCheckQuery);
$stmt->execute([
    'student_id' => $studentId,
    'hosted_test_id' => $hostedTestId
]);

$testData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$testData) {
    // Student doesn't have access to this test
    header("Location: test_list.php");
    exit();
}

// Check if test is currently active and student hasn't already started
$currentDateTime = new DateTime();
$testStartDateTime = new DateTime($testData['test_date'] . ' ' . $testData['start_time']);
$testEndDateTime = new DateTime($testData['test_date'] . ' ' . $testData['end_time']);
if ($testEndDateTime <= $testStartDateTime) {
    $testEndDateTime->modify('+1 day');
}

// Check if test is within the active hosting window
$isTestActive = ($currentDateTime >= $testStartDateTime && $currentDateTime <= $testEndDateTime);

// Check if student has already started the test
$hasStarted = !empty($testData['student_start_time']);

// Check if test has already been submitted
$hasSubmitted = !empty($testData['submit_time']);

if ($hasSubmitted) {
    // Test already submitted
    header("Location: test_report.php?test_id=" . $hostedTestId);
    exit();
} elseif (!$isTestActive && !$hasStarted) {
    // Test is not active and student hasn't started yet
    header("Location: test_list.php");
    exit();
}

// No extra time for refresh/resume; keep flag only for informational notice
$resumeMode = isset($_GET['resume']) && $_GET['resume'] === '1';

// If student hasn't started the test yet, record the start time
if (!$hasStarted) {
    $startStmt = $pdo->prepare("
        UPDATE hosted_test_students
        SET start_time = NOW(), test_status = 'in_progress'
        WHERE id = :student_test_id
          AND submit_time IS NULL
    ");
    $startStmt->execute(['student_test_id' => $testData['student_test_id']]);
    $testData['student_start_time'] = date('Y-m-d H:i:s');

    // Reset security violation counter for fresh attempts
    $_SESSION['test_violation_count_' . $hostedTestId] = 0;
} elseif ($testData['student_test_status'] !== 'in_progress') {
    $statusStmt = $pdo->prepare("
        UPDATE hosted_test_students
        SET test_status = 'in_progress'
        WHERE id = :student_test_id
          AND submit_time IS NULL
    ");
    $statusStmt->execute(['student_test_id' => $testData['student_test_id']]);
}

// If attempt window already expired, finalize and move to report.
$allowedEndDateTime = getAllowedEndDateTime($testData);
if ($currentDateTime > $allowedEndDateTime && !$hasSubmitted) {
    try {
        finalizeSubmission($pdo, (int)$testData['student_test_id']);
    } catch (Exception $e) {
        // no-op
    }
    header("Location: test_report.php?test_id=" . $hostedTestId);
    exit();
}

$currentViolationCount = (int)($_SESSION['test_violation_count_' . $hostedTestId] ?? 0);
$remainingViolationCount = max(0, MAX_SECURITY_VIOLATIONS - $currentViolationCount);

// Fetch test questions
$questionsQuery = "
    SELECT 
        q.id,
        q.question_text as text,
        q.question_type as type,
        q.option_a,
        q.option_b,
        q.option_c,
        q.option_d,
        q.correct_answer,
        ts.section_name as section,
        ts.id as section_id,
        ts.questions_to_display,
        ts.marks_per_question,
        ts.negative_marks,
        ts.section_order
    FROM test_sections ts
    INNER JOIN questions q ON ts.id = q.section_id
    INNER JOIN hosted_tests ht ON ts.test_id = ht.test_id
    INNER JOIN hosted_test_students hts ON ht.id = hts.hosted_test_id
    WHERE hts.student_id = :student_id 
    AND ht.id = :hosted_test_id
    ORDER BY ts.section_order, q.id
";

$questionsStmt = $pdo->prepare($questionsQuery);
$questionsStmt->execute([
    'student_id' => $studentId,
    'hosted_test_id' => $hostedTestId
]);

$allQuestions = $questionsStmt->fetchAll(PDO::FETCH_ASSOC);

// Group questions by section
$sections = [];

foreach ($allQuestions as $question) {
    $sectionName = $question['section'];
    if (!isset($sections[$sectionName])) {
        $sections[$sectionName] = [
            'id' => $question['section_id'],
            'name' => $sectionName,
            'order' => $question['section_order'],
            'questions_to_display' => (int)$question['questions_to_display'],
            'marks_per_question' => $question['marks_per_question'],
            'negative_marks' => $question['negative_marks'],
            'questions' => []
        ];
    }
    $sections[$sectionName]['questions'][] = $question;
}

// Sort sections by order
uasort($sections, function($a, $b) {
    return $a['order'] <=> $b['order'];
});

// Apply shuffle configuration if exists, otherwise generate new shuffle
$shuffleConfig = [];
if (!empty($testData['shuffle_config'])) {
    $shuffleConfig = json_decode($testData['shuffle_config'], true) ?: [];
} else {
    // Generate new shuffle configuration
    foreach ($sections as $sectionName => $section) {
        $totalAvailable = count($section['questions']);
        $displayLimit = max(1, min($section['questions_to_display'], $totalAvailable));
        
        // Create array of all question indices
        $questionIndices = array_keys($section['questions']);
        
        // Shuffle questions if enabled
        if ($testData['question_shuffle'] === 'yes') {
            shuffle($questionIndices);
        }
        
        // Only store the indices we'll actually use (limited by questions_to_display)
        $selectedIndices = array_slice($questionIndices, 0, $displayLimit);
        
        $shuffleConfig[$sectionName] = [
            'question_order' => $selectedIndices,
            'option_shuffles' => []
        ];
        
        // Shuffle options if enabled (only for selected questions)
        if ($testData['option_shuffle'] === 'yes') {
            foreach ($selectedIndices as $idx) {
                if (isset($section['questions'][$idx]) && $section['questions'][$idx]['type'] === 'mcq') {
                    $options = ['A', 'B', 'C', 'D'];
                    shuffle($options);
                    $shuffleConfig[$sectionName]['option_shuffles'][$section['questions'][$idx]['id']] = $options;
                }
            }
        }
    }
    
    // Save shuffle configuration
    $saveShuffleStmt = $pdo->prepare("UPDATE hosted_test_students SET shuffle_config = :shuffle_config WHERE id = :student_test_id");
    $saveShuffleStmt->execute([
        'shuffle_config' => json_encode($shuffleConfig),
        'student_test_id' => $testData['student_test_id']
    ]);
}

// Apply shuffle configuration to questions and enforce display limits
foreach ($sections as $sectionName => &$section) {
    $totalAvailable = count($section['questions']);
    $displayLimit = max(1, min($section['questions_to_display'], $totalAvailable));
    
    // Get the ordered indices from shuffle config, or create default order
    $orderedIndices = isset($shuffleConfig[$sectionName]['question_order']) && is_array($shuffleConfig[$sectionName]['question_order'])
        ? $shuffleConfig[$sectionName]['question_order']
        : array_slice(array_keys($section['questions']), 0, $displayLimit);

    // Build the final question list based on the ordered indices
    $shuffledQuestions = [];
    foreach ($orderedIndices as $index) {
        if (isset($section['questions'][$index])) {
            $shuffledQuestions[] = $section['questions'][$index];
            // Double-check we don't exceed display limit
            if (count($shuffledQuestions) >= $displayLimit) {
                break;
            }
        }
    }
    
    // If we somehow have fewer questions than expected, fill with remaining questions
    if (count($shuffledQuestions) < $displayLimit && count($shuffledQuestions) < $totalAvailable) {
        $usedIndices = array_flip($orderedIndices);
        foreach (array_keys($section['questions']) as $idx) {
            if (!isset($usedIndices[$idx])) {
                $shuffledQuestions[] = $section['questions'][$idx];
                if (count($shuffledQuestions) >= $displayLimit) {
                    break;
                }
            }
        }
    }
    
    $section['questions'] = $shuffledQuestions;

    // Apply option shuffle
    if (isset($shuffleConfig[$sectionName]['option_shuffles'])) {
        foreach ($section['questions'] as &$question) {
            if ($question['type'] === 'mcq' && isset($shuffleConfig[$sectionName]['option_shuffles'][$question['id']])) {
                $optionOrder = $shuffleConfig[$sectionName]['option_shuffles'][$question['id']];
                $originalOptions = [
                    'A' => $question['option_a'],
                    'B' => $question['option_b'],
                    'C' => $question['option_c'],
                    'D' => $question['option_d']
                ];
                
                $newOptions = [];
                foreach ($optionOrder as $i => $letter) {
                    $newOptions[['A', 'B', 'C', 'D'][$i]] = $originalOptions[$letter];
                    if ($question['correct_answer'] === $letter) {
                        $question['correct_answer'] = ['A', 'B', 'C', 'D'][$i];
                    }
                }
                
                $question['option_a'] = $newOptions['A'];
                $question['option_b'] = $newOptions['B'];
                $question['option_c'] = $newOptions['C'];
                $question['option_d'] = $newOptions['D'];
            }
        }
    }
}

// Recalculate question numbers after shuffling
$questionNumbers = [];
$questionIndex = 1;
foreach ($sections as $section) {
    foreach ($section['questions'] as $question) {
        $questionNumbers[$question['id']] = $questionIndex;
        $questionIndex++;
    }
}

$allQuestionsFlat = [];
foreach ($sections as $section) {
    foreach ($section['questions'] as $question) {
        $allQuestionsFlat[] = $question;
    }
}
$totalQuestions = count($allQuestionsFlat);

// Calculate remaining time
$currentTime = new DateTime();
$remainingSeconds = max(0, $allowedEndDateTime->getTimestamp() - $currentTime->getTimestamp());

// Function to get student's saved answer for a question
function getSavedAnswer($pdo, $studentTestId, $questionId) {
    $stmt = $pdo->prepare("
        SELECT student_answer 
        FROM student_answers 
        WHERE hosted_test_student_id = :student_test_id 
        AND question_id = :question_id
    ");
    $stmt->execute([
        'student_test_id' => $studentTestId,
        'question_id' => $questionId
    ]);
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['student_answer'] : null;
}

// Get all saved answers for navigation status
$savedAnswers = [];
$stmt = $pdo->prepare("
    SELECT question_id, student_answer 
    FROM student_answers 
    WHERE hosted_test_student_id = :student_test_id
");
$stmt->execute(['student_test_id' => $testData['student_test_id']]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $savedAnswers[$row['question_id']] = $row['student_answer'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Take Test - <?php echo htmlspecialchars($testData['test_name']); ?> - KR ASSESSLY</title>
    <link rel="stylesheet" href="../vendors/feather/feather.css">
    <link rel="stylesheet" href="../vendors/ti-icons/css/themify-icons.css">
    <link rel="stylesheet" href="../vendors/css/vendor.bundle.base.css">
    <link rel="stylesheet" href="../css/vertical-layout-light/style.css">
    <link rel="shortcut icon" href="../images/favicon.jpg" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        /* Fullscreen test mode */
        body.test-fullscreen {
            margin: 0;
            padding: 0;
            overflow: hidden;
            background: #f5f7ff;
        }
        
        .fullscreen-container {
            display: flex;
            height: 100vh;
            width: 100vw;
        }
        
        /* Side navigation panel */
        .side-navigation {
            width: 280px;
            background: white;
            border-right: 1px solid #e8ecf1;
            display: flex;
            flex-direction: column;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        
        .nav-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .timer-display {
            background: #fff3cd;
            color: #856404;
            padding: 12px;
            border-radius: 6px;
            font-weight: 600;
            margin-bottom: 15px;
            text-align: center;
            border: 1px solid #ffeaa7;
        }

        .network-speed-display {
            background: #eef5ff;
            color: #1b4a84;
            border-color: #c8dcf7;
        }

        .network-line {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-bottom: 4px;
        }

        .network-badge {
            display: inline-block;
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 999px;
            font-weight: 700;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }

        .network-badge-good {
            background: #d1f0dc;
            color: #1f7a39;
        }

        .network-badge-low {
            background: #ffe4c2;
            color: #a65602;
        }

        .network-badge-offline {
            background: #f8d7da;
            color: #8e1b28;
        }

        .network-badge-checking {
            background: #e2e3e5;
            color: #495057;
        }

        .network-meta {
            font-size: 11px;
            opacity: 0.9;
        }

        .refresh-resume-display {
            background: #eaf9ef;
            color: #205c2f;
            border-color: #c9e8d2;
        }
        
        .sections-nav {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
        }
        
        .section-item {
            margin-bottom: 15px;
            border: 1px solid #e8ecf1;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .section-title {
            background: #f8f9fa;
            padding: 12px 15px;
            font-weight: 600;
            color: #495057;
            cursor: pointer;
            border-bottom: 1px solid #e8ecf1;
        }
        
        .section-title:hover {
            background: #e9ecef;
        }
        
        .questions-list {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }
        
        .section-item.active .questions-list {
            max-height: 500px;
        }
        
        .question-nav-item {
            padding: 10px 15px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            display: flex;
            align-items: center;
        }
        
        .question-nav-item:hover {
            background: #f8f9fa;
        }
        
        .question-nav-item.active {
            background: #667eea;
            color: white;
        }
        
        .question-number {
            width: 25px;
            height: 25px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .question-nav-item:not(.active) .question-number {
            background: #e9ecef;
        }
        
        .question-nav-item.answered .question-number {
            background: #28a745;
            color: white;
        }
        
        .question-nav-item.active .question-number {
            background: white;
            color: #667eea;
        }
        
        /* Main content area */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .test-header {
            background: white;
            padding: 15px 25px;
            border-bottom: 1px solid #e8ecf1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .submit-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .submit-btn:disabled {
            background: #cccccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .submit-btn:disabled:hover {
            transform: none;
            box-shadow: none;
        }
        
        .question-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            border: 1px solid #e8ecf1;
        }
        
        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .question-text {
            font-size: 1.1rem;
            font-weight: 500;
            margin-bottom: 20px;
            color: #333;
            line-height: 1.6;
        }
        
        .option {
            margin-bottom: 12px;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .option:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }
        
        .option.selected {
            border-color: #667eea;
            background: #667eea;
            color: white;
        }
        
        .navigation-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
        
        .nav-btn {
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }
        
        .prev-btn {
            background: #6c757d;
            color: white;
        }
        
        .next-btn {
            background: #667eea;
            color: white;
        }
        
        .nav-btn:hover, .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .nav-btn:disabled {
            background: #e9ecef;
            color: #6c757d;
            cursor: not-allowed;
            transform: none;
        }
        
        /* Warning modal */
        .fullscreen-warning {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0,0,0,0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            color: white;
            text-align: center;
        }
        
        .warning-content {
            background: white;
            color: #333;
            padding: 30px;
            border-radius: 12px;
            max-width: 500px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .warning-content h3 {
            color: #dc3545;
            margin-top: 0;
        }
        
        /* Resume notification */
        .resume-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #fff3cd;
            color: #856404;
            padding: 15px 20px;
            border-radius: 8px;
            border: 1px solid #ffeaa7;
            z-index: 9999;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        /* Competitive-exam style overrides */
        .exam-topbar {
            height: 72px;
            background: linear-gradient(90deg, #0f2b53 0%, #163e73 60%, #1f4f8e 100%);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 18px;
            border-bottom: 2px solid rgba(255,255,255,0.2);
        }

        .exam-title {
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            margin-bottom: 2px;
        }

        .exam-subtitle {
            font-size: 0.78rem;
            opacity: 0.92;
        }

        .exam-top-stats {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .top-stat-chip {
            background: rgba(255,255,255,0.14);
            border: 1px solid rgba(255,255,255,0.24);
            border-radius: 20px;
            padding: 6px 12px;
            font-size: 0.8rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .fullscreen-container {
            height: calc(100vh - 72px);
            background: #eef2f8;
        }

        .main-content {
            order: 1;
            background: #f2f5fb;
        }

        .side-navigation {
            order: 2;
            width: 340px;
            border-left: 1px solid #dce2ef;
            border-right: none;
            box-shadow: -4px 0 14px rgba(0, 0, 0, 0.08);
        }

        .nav-header {
            background: #fff;
            color: #1f2e46;
            border-bottom: 1px solid #e2e8f2;
            text-align: left;
            padding: 14px;
        }

        .nav-header h4 {
            margin: 0;
            font-size: 0.95rem;
            font-weight: 700;
        }

        .nav-header div {
            margin-top: 4px;
            font-size: 0.78rem;
            color: #5d6778;
        }

        .timer-display {
            margin: 10px 12px 0;
            border-radius: 10px;
        }

        .sections-nav {
            padding: 10px;
        }

        .section-item {
            border-radius: 10px;
        }

        .section-title {
            font-size: 0.85rem;
            padding: 10px 12px;
            background: #f3f6fb;
        }

        .questions-list {
            max-height: none;
            overflow: visible;
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 8px;
            padding: 10px;
        }

        .section-item.active .questions-list {
            max-height: none;
        }

        .question-nav-item {
            border: 1px solid #d6dfef;
            border-radius: 8px;
            background: #fff;
            justify-content: center;
            min-height: 38px;
            padding: 0;
        }

        .question-nav-item .question-label {
            display: none;
        }

        .question-number {
            margin-right: 0;
            width: 100%;
            height: 100%;
            border-radius: 8px;
            font-size: 0.86rem;
            background: transparent !important;
            color: #24354f !important;
        }

        .question-nav-item.answered .question-number {
            background: #daf4e7 !important;
            color: #13653f !important;
        }

        .question-nav-item.active .question-number {
            background: #2a66d9 !important;
            color: #fff !important;
        }

        .test-header {
            background: #fff;
            padding: 12px 18px;
            border-bottom: 1px solid #dfe6f1;
        }

        .test-header h2 {
            font-size: 1.1rem;
            margin-bottom: 2px;
        }

        .test-header div {
            font-size: 0.82rem;
            color: #58647a;
        }

        .question-card {
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(27, 43, 75, 0.08);
            padding: 22px;
        }

        .question-header {
            font-size: 0.86rem;
            color: #4e5a70;
        }

        .question-text {
            font-size: 1.02rem;
            line-height: 1.55;
        }

        .option {
            border-radius: 10px;
            border-width: 1.5px;
            background: #fcfdff;
        }

        .navigation-buttons {
            margin-top: 24px;
        }

        @media (max-width: 991px) {
            .exam-topbar {
                height: auto;
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
                padding: 10px 12px;
            }

            .fullscreen-container {
                height: calc(100vh - 110px);
                flex-direction: column-reverse;
            }

            .side-navigation {
                width: 100%;
                max-height: 44vh;
                border-left: none;
                border-top: 1px solid #dce2ef;
                box-shadow: 0 -4px 14px rgba(0, 0, 0, 0.08);
            }

            .questions-list {
                grid-template-columns: repeat(8, 1fr);
            }
        }
    </style>
</head>
<body class="test-fullscreen">
    <?php if ($resumeMode): ?>
    <div class="resume-notification">
        <i class="fas fa-info-circle"></i> 
        <strong>Resume Detected:</strong> test continued with original timer (no extra time granted)
    </div>
    <?php endif; ?>
    
    <!-- Fullscreen warning modal -->
    <div class="fullscreen-warning" id="fullscreenWarning" style="display: none;">
        <div class="warning-content">
            <h3><i class="fas fa-exclamation-triangle"></i> Test Security Violation</h3>
            <p id="securityWarningText">You have attempted to leave fullscreen mode. Please return to fullscreen immediately.</p>
            <p id="securityWarningSubText">The test timer continues to run while you reconnect or reload.</p>
            <button class="submit-btn" onclick="enterFullscreen()">Return to Test</button>
        </div>
    </div>

    <div class="exam-topbar">
        <div>
            <div class="exam-title"><?php echo htmlspecialchars($testData['test_name']); ?></div>
            <div class="exam-subtitle">
                Code: <?php echo htmlspecialchars($testData['test_code']); ?> |
                Candidate: <?php echo htmlspecialchars($studentName); ?>
                <?php if (!empty($studentRegisterNumber)): ?>
                (<?php echo htmlspecialchars($studentRegisterNumber); ?>)
                <?php endif; ?>
            </div>
        </div>
        <div class="exam-top-stats">
            <div class="top-stat-chip">Question <span id="topCurrentQuestion">1</span> / <?php echo $totalQuestions; ?></div>
            <div class="top-stat-chip">Answered <span id="topAnsweredCount">0</span></div>
            <button class="submit-btn" onclick="submitTest()" id="submitButtonTop">
                <i class="fas fa-paper-plane"></i> Submit
            </button>
        </div>
    </div>

    <div class="fullscreen-container">
        <!-- Side Navigation Panel -->
        <div class="side-navigation">
            <div class="nav-header">
                <h4><?php echo htmlspecialchars($testData['test_name']); ?></h4>
                <div>Code: <?php echo htmlspecialchars($testData['test_code']); ?></div>
            </div>
            
            <div class="timer-display" id="timer">
                <i class="fas fa-clock"></i> 
                <span id="timeRemaining"><?php echo gmdate("H:i:s", $remainingSeconds); ?></span>
            </div>

            <div class="timer-display" id="securityInfo" style="background:#f8d7da;color:#721c24;border-color:#f5c6cb;">
                <i class="fas fa-shield-alt"></i>
                Security Events:
                <span id="violationCount"><?php echo (int)$currentViolationCount; ?></span>
                / <?php echo (int)MAX_SECURITY_VIOLATIONS; ?>
                <div style="font-size:11px;margin-top:4px;">Tracking left: <span id="violationLeft"><?php echo (int)$remainingViolationCount; ?></span></div>
            </div>

            <div class="timer-display network-speed-display" id="networkSpeedBox">
                <div class="network-line">
                    <i class="fas fa-wifi"></i>
                    Speed: <span id="networkSpeedMbps">--</span> Mbps
                    <span class="network-badge network-badge-checking" id="networkQualityBadge">Checking</span>
                </div>
                <div class="network-meta" id="networkSpeedMeta">Measuring network...</div>
            </div>

            <div class="timer-display refresh-resume-display" id="refreshResumeBox" style="display:none;">
                <i class="fas fa-redo-alt"></i>
                Refresh recovery active:
                <span id="refreshResumeTimer">02:00</span>
            </div>
            
            <div class="sections-nav">
                <?php $sectionIndex = 0; foreach ($sections as $sectionName => $section): ?>
                <div class="section-item <?php echo $sectionIndex === 0 ? 'active' : ''; ?>" data-section="<?php echo $sectionIndex; ?>">
                    <div class="section-title" onclick="toggleSection(<?php echo $sectionIndex; ?>)">
                        <i class="fas fa-chevron-down toggle-icon"></i>
                        <?php echo htmlspecialchars($sectionName); ?>
                        <span class="float-right">
                            <small>+<?php echo $section['marks_per_question']; ?>/-<?php echo $section['negative_marks']; ?></small>
                        </span>
                    </div>
                    <div class="questions-list">
                        <?php foreach ($section['questions'] as $question): ?>
                        <div class="question-nav-item" data-question="<?php echo $question['id']; ?>" onclick="showQuestion(<?php echo $question['id']; ?>)" title="Question <?php echo $questionNumbers[$question['id']]; ?>">
                            <div class="question-number"><?php echo $questionNumbers[$question['id']]; ?></div>
                            <div class="question-label">Question <?php echo $questionNumbers[$question['id']]; ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php $sectionIndex++; endforeach; ?>
            </div>
        </div>
        
        <!-- Main Content Area -->
        <div class="main-content">
            <div class="test-header">
                <div>
                    <h2><?php echo htmlspecialchars($testData['test_name']); ?></h2>
                    <div>Test Code: <?php echo htmlspecialchars($testData['test_code']); ?></div>
                </div>
                <button class="submit-btn" onclick="submitTest()" id="submitButton">
                    <i class="fas fa-paper-plane"></i> Submit Test
                </button>
            </div>
            
            <div style="flex: 1; overflow-y: auto; padding: 25px;">
                <?php for ($i = 0; $i < $totalQuestions; $i++): 
                    $question = $allQuestionsFlat[$i];
                    $prevQuestionId = $i > 0 ? $allQuestionsFlat[$i-1]['id'] : null;
                    $nextQuestionId = $i < $totalQuestions - 1 ? $allQuestionsFlat[$i+1]['id'] : null;
                ?>
                <div class="question-card" id="question-<?php echo $question['id']; ?>" style="display: <?php echo $i === 0 ? 'block' : 'none'; ?>;">
                    <div class="question-header">
                        <div>Question <?php echo $questionNumbers[$question['id']]; ?> of <?php echo $totalQuestions; ?></div>
                        <div>+<?php echo $sections[$question['section']]['marks_per_question']; ?> / -<?php echo $sections[$question['section']]['negative_marks']; ?></div>
                    </div>
                    
                    <div class="question-text">
                        <?php echo htmlspecialchars($question['text']); ?>
                    </div>
                    
                    <div>
                        <?php if ($question['type'] === 'mcq'): ?>
                            <div class="option option-A <?php echo (isset($savedAnswers[$question['id']]) && $savedAnswers[$question['id']] === 'A') ? 'selected' : ''; ?>" 
                                 onclick="selectOption(<?php echo $question['id']; ?>, 'A')">
                                <strong>A.</strong> <?php echo htmlspecialchars($question['option_a']); ?>
                            </div>
                            <div class="option option-B <?php echo (isset($savedAnswers[$question['id']]) && $savedAnswers[$question['id']] === 'B') ? 'selected' : ''; ?>" 
                                 onclick="selectOption(<?php echo $question['id']; ?>, 'B')">
                                <strong>B.</strong> <?php echo htmlspecialchars($question['option_b']); ?>
                            </div>
                            <div class="option option-C <?php echo (isset($savedAnswers[$question['id']]) && $savedAnswers[$question['id']] === 'C') ? 'selected' : ''; ?>" 
                                 onclick="selectOption(<?php echo $question['id']; ?>, 'C')">
                                <strong>C.</strong> <?php echo htmlspecialchars($question['option_c']); ?>
                            </div>
                            <div class="option option-D <?php echo (isset($savedAnswers[$question['id']]) && $savedAnswers[$question['id']] === 'D') ? 'selected' : ''; ?>" 
                                 onclick="selectOption(<?php echo $question['id']; ?>, 'D')">
                                <strong>D.</strong> <?php echo htmlspecialchars($question['option_d']); ?>
                            </div>
                            
                            <input type="hidden" name="answers[<?php echo $question['id']; ?>]" 
                                   id="answer-<?php echo $question['id']; ?>" 
                                   value="<?php echo htmlspecialchars($savedAnswers[$question['id']] ?? ''); ?>">
                                   
                        <?php elseif ($question['type'] === 'truefalse'): ?>
                            <div class="option option-A <?php echo (isset($savedAnswers[$question['id']]) && $savedAnswers[$question['id']] === 'True') ? 'selected' : ''; ?>" 
                                 onclick="selectOption(<?php echo $question['id']; ?>, 'True')">
                                <strong>True</strong>
                            </div>
                            <div class="option option-B <?php echo (isset($savedAnswers[$question['id']]) && $savedAnswers[$question['id']] === 'False') ? 'selected' : ''; ?>" 
                                 onclick="selectOption(<?php echo $question['id']; ?>, 'False')">
                                <strong>False</strong>
                            </div>
                            
                            <input type="hidden" name="answers[<?php echo $question['id']; ?>]" 
                                   id="answer-<?php echo $question['id']; ?>" 
                                   value="<?php echo htmlspecialchars($savedAnswers[$question['id']] ?? ''); ?>">
                                   
                        <?php elseif ($question['type'] === 'fillup'): ?>
                            <div>
                                <input type="text" style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px;" 
                                       name="answers[<?php echo $question['id']; ?>]" 
                                       id="answer-<?php echo $question['id']; ?>"
                                       placeholder="Enter your answer" 
                                       value="<?php echo htmlspecialchars($savedAnswers[$question['id']] ?? ''); ?>"
                                       autocomplete="off"
                                       autocorrect="off"
                                       autocapitalize="off"
                                       spellcheck="false"
                                       oninput="updateFillupAnswer(<?php echo $question['id']; ?>, this.value)">
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="navigation-buttons">
                        <button class="nav-btn prev-btn" id="prevBtn-<?php echo $question['id']; ?>" 
                                <?php echo $prevQuestionId ? '' : 'disabled'; ?>
                                onclick="<?php echo $prevQuestionId ? 'showQuestion(' . $prevQuestionId . ')' : ''; ?>">
                            <i class="fas fa-arrow-left"></i> Previous
                        </button>
                        <button class="nav-btn next-btn" id="nextBtn-<?php echo $question['id']; ?>" 
                                <?php echo $nextQuestionId ? '' : 'style="background: #28a745;"'; ?>
                                onclick="<?php echo $nextQuestionId ? 'showQuestion(' . $nextQuestionId . ')' : ($nextQuestionId ? '' : 'submitTest()'); ?>">
                            <?php echo $nextQuestionId ? 'Next <i class="fas fa-arrow-right"></i>' : '<i class="fas fa-paper-plane"></i> Submit Test'; ?>
                        </button>
                    </div>
                </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <script src="../vendors/js/vendor.bundle.base.js"></script>
    
    <script>
        // Enhanced Security Features - fullscreen-first, refresh recovery, network-aware
        let currentQuestionId = <?php echo $allQuestionsFlat[0]['id'] ?? 0; ?>;
        let totalQuestions = <?php echo count($allQuestionsFlat); ?>;
        let answeredQuestions = new Set();
        let fullscreenInterval;
        let devToolsInterval;
        let heartbeatInterval;
        let networkSpeedInterval;
        let refreshGraceInterval;
        let submissionInProgress = false;
        let isRecordingViolation = false;
        let lastViolationAt = 0;
        let lastFullscreenAttemptAt = 0;
        let plannedRefresh = false;
        let refreshGraceUntil = 0;
        let lowInternetMode = false;
        let refreshRecoveryTriggered = false;
        let violationCount = <?php echo (int)$currentViolationCount; ?>;
        const maxBlurAttempts = <?php echo (int)MAX_SECURITY_VIOLATIONS; ?>;
        const refreshResumeSeconds = <?php echo (int)REFRESH_RESUME_GRACE_SECONDS; ?>;
        const refreshResumeKey = 'kr_refresh_resume_<?php echo (int)$hostedTestId; ?>';
        const lowInternetThresholdMbps = <?php echo number_format((float)LOW_INTERNET_THRESHOLD_MBPS, 2, '.', ''); ?>;
        const networkSampleIntervalMs = <?php echo (int)NETWORK_SAMPLE_INTERVAL_SECONDS * 1000; ?>;
        const lowNetworkStreakNeeded = <?php echo (int)LOW_NETWORK_STREAK_FOR_RELAXATION; ?>;
        let lowNetworkStreak = 0;

        function isFullscreenActive() {
            return !!(document.fullscreenElement ||
                document.mozFullScreenElement ||
                document.webkitFullscreenElement ||
                document.msFullscreenElement);
        }

        function formatAsClock(seconds) {
            const safeSeconds = Math.max(0, Math.floor(seconds));
            const mins = Math.floor(safeSeconds / 60);
            const secs = safeSeconds % 60;
            return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        }

        function isRefreshGraceActive() {
            return refreshGraceUntil > Date.now();
        }

        function isReducedSecurityMode() {
            return isRefreshGraceActive() || (refreshRecoveryTriggered && lowInternetMode);
        }

        function updateViolationUI() {
            const violationEl = document.getElementById('violationCount');
            const violationLeftEl = document.getElementById('violationLeft');
            if (violationEl) {
                violationEl.textContent = violationCount;
            }
            if (violationLeftEl) {
                const left = Math.max(0, maxBlurAttempts - violationCount);
                violationLeftEl.textContent = left;
            }
        }

        function updateTopProgress() {
            const answeredCountEl = document.getElementById('topAnsweredCount');
            const currentQuestionEl = document.getElementById('topCurrentQuestion');

            if (answeredCountEl) {
                answeredCountEl.textContent = answeredQuestions.size;
            }

            if (currentQuestionEl && currentQuestionId) {
                const currentNo = document.querySelector(`[data-question="${currentQuestionId}"] .question-number`);
                if (currentNo) {
                    currentQuestionEl.textContent = currentNo.textContent.trim();
                }
            }
        }

        function updateReducedSecurityState() {
            const securityEl = document.getElementById('securityInfo');
            if (!securityEl) {
                return;
            }

            if (isReducedSecurityMode()) {
                securityEl.style.background = '#fff4e5';
                securityEl.style.color = '#7d4b00';
                securityEl.style.borderColor = '#ffd9a8';
            } else {
                securityEl.style.background = '#f8d7da';
                securityEl.style.color = '#721c24';
                securityEl.style.borderColor = '#f5c6cb';
            }
        }

        function updateRefreshResumeUI() {
            const box = document.getElementById('refreshResumeBox');
            const timerEl = document.getElementById('refreshResumeTimer');
            if (!box || !timerEl) {
                return;
            }

            if (isRefreshGraceActive()) {
                const secondsLeft = Math.ceil((refreshGraceUntil - Date.now()) / 1000);
                timerEl.textContent = formatAsClock(secondsLeft);
                box.style.display = 'block';
            } else {
                box.style.display = 'none';
            }
            updateReducedSecurityState();
        }

        function startRefreshResume(untilTs) {
            refreshGraceUntil = untilTs;
            updateRefreshResumeUI();

            if (refreshGraceInterval) {
                clearInterval(refreshGraceInterval);
            }

            refreshGraceInterval = setInterval(() => {
                if (!isRefreshGraceActive()) {
                    refreshGraceUntil = 0;
                    sessionStorage.removeItem(refreshResumeKey);
                    clearInterval(refreshGraceInterval);
                    refreshGraceInterval = null;
                }
                updateRefreshResumeUI();
            }, 1000);
        }

        function prepareRefreshResume() {
            const untilTs = Date.now() + (refreshResumeSeconds * 1000);
            plannedRefresh = true;
            refreshRecoveryTriggered = true;
            sessionStorage.setItem(refreshResumeKey, String(untilTs));
            startRefreshResume(untilTs);
            setTimeout(() => {
                plannedRefresh = false;
            }, 10000);
        }

        function restoreRefreshResumeIfAny() {
            const storedTs = Number(sessionStorage.getItem(refreshResumeKey) || 0);
            if (storedTs > Date.now()) {
                refreshRecoveryTriggered = true;
                startRefreshResume(storedTs);
            } else {
                sessionStorage.removeItem(refreshResumeKey);
            }
        }

        function setNetworkSpeedState(mbps, qualityLabel, qualityClass, metaLabel, isLowNetwork) {
            const speedEl = document.getElementById('networkSpeedMbps');
            const qualityEl = document.getElementById('networkQualityBadge');
            const metaEl = document.getElementById('networkSpeedMeta');

            if (speedEl) {
                speedEl.textContent = Number.isFinite(mbps) ? mbps.toFixed(2) : '--';
            }
            if (qualityEl) {
                qualityEl.className = `network-badge ${qualityClass}`;
                qualityEl.textContent = qualityLabel;
            }
            if (metaEl) {
                metaEl.textContent = metaLabel;
            }

            if (isLowNetwork) {
                lowNetworkStreak += 1;
            } else {
                lowNetworkStreak = 0;
            }

            lowInternetMode = lowNetworkStreak >= lowNetworkStreakNeeded;

            if (metaEl) {
                if (lowInternetMode) {
                    metaEl.textContent = `${metaLabel} | Low-network mode active`;
                } else if (isLowNetwork) {
                    metaEl.textContent = `${metaLabel} | Verifying low speed (${lowNetworkStreak}/${lowNetworkStreakNeeded})`;
                } else {
                    metaEl.textContent = `${metaLabel} | Threshold ${lowInternetThresholdMbps.toFixed(2)} Mbps`;
                }
            }
            updateReducedSecurityState();
        }

        async function measureNetworkSpeed() {
            if (!navigator.onLine) {
                setNetworkSpeedState(0, 'Offline', 'network-badge-offline', 'No internet connection detected.', true);
                return;
            }

            const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
            if (connection && typeof connection.downlink === 'number' && connection.downlink > 0) {
                const downlink = Number(connection.downlink);
                const effectiveType = connection.effectiveType || 'network';
                const isLow = downlink < lowInternetThresholdMbps || effectiveType === 'slow-2g' || effectiveType === '2g';
                setNetworkSpeedState(
                    downlink,
                    isLow ? 'Low' : 'Good',
                    isLow ? 'network-badge-low' : 'network-badge-good',
                    `Connection: ${effectiveType}`,
                    isLow
                );
                return;
            }

            const startedAt = performance.now();
            try {
                await fetch('../images/favicon.jpg?speed=' + Date.now(), { cache: 'no-store' });
                const latencyMs = performance.now() - startedAt;
                const estimatedMbps = latencyMs > 1800 ? 0.25 :
                    latencyMs > 1300 ? 0.45 :
                    latencyMs > 900 ? 0.70 :
                    latencyMs > 600 ? 1.10 : 2.00;
                const isLow = estimatedMbps < lowInternetThresholdMbps;
                setNetworkSpeedState(
                    estimatedMbps,
                    isLow ? 'Low' : 'Good',
                    isLow ? 'network-badge-low' : 'network-badge-good',
                    `Latency sample: ${Math.round(latencyMs)} ms`,
                    isLow
                );
            } catch (e) {
                setNetworkSpeedState(0, 'Offline', 'network-badge-offline', 'Unable to measure internet speed.', true);
            }
        }

        function startNetworkSpeedMonitoring() {
            measureNetworkSpeed();
            if (networkSpeedInterval) {
                clearInterval(networkSpeedInterval);
            }
            networkSpeedInterval = setInterval(measureNetworkSpeed, networkSampleIntervalMs);

            const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
            if (connection && connection.addEventListener) {
                connection.addEventListener('change', measureNetworkSpeed);
            }
            window.addEventListener('online', measureNetworkSpeed);
            window.addEventListener('offline', measureNetworkSpeed);
        }

        async function recordViolation(reason) {
            if (submissionInProgress || isRecordingViolation) {
                return;
            }

            isRecordingViolation = true;
            try {
                const formData = new FormData();
                formData.append('record_violation', '1');
                formData.append('reason', reason);

                const response = await fetch('take_test.php?test_id=<?php echo $hostedTestId; ?>', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (typeof result.violations !== 'undefined') {
                    violationCount = Number(result.violations) || violationCount;
                    updateViolationUI();
                }
                if (result.force_submit) {
                    forceSubmitTest(true);
                }
            } catch (e) {
                // no-op
            } finally {
                isRecordingViolation = false;
            }
        }

        function registerViolation(reason, shouldWarn = true) {
            if (submissionInProgress) {
                return;
            }

            const now = Date.now();
            if (now - lastViolationAt < 2500) {
                return;
            }
            lastViolationAt = now;

            if (isReducedSecurityMode()) {
                if (shouldWarn) {
                    showFullscreenWarning(
                        'Returning to fullscreen mode...',
                        (refreshRecoveryTriggered && lowInternetMode && !isRefreshGraceActive())
                            ? 'Low internet detected: strict actions are reduced temporarily.'
                            : 'Refresh recovery active for 2 minutes. Timer is still running.'
                    );
                }
                tryAutoFullscreen(false);
                return;
            }

            violationCount += 1;
            updateViolationUI();
            if (shouldWarn) {
                showFullscreenWarning(
                    'Fullscreen is mandatory for this test.',
                    'Please continue in fullscreen mode.'
                );
            }

            recordViolation(reason);
        }
        
        // Enhanced fullscreen functionality
        function tryAutoFullscreen(withWarning = false) {
            if (submissionInProgress) {
                return;
            }
            const now = Date.now();
            if (now - lastFullscreenAttemptAt < 1200) {
                return;
            }
            lastFullscreenAttemptAt = now;

            if (withWarning) {
                showFullscreenWarning(
                    'Fullscreen is mandatory for the test.',
                    'The test is trying to return to fullscreen automatically.'
                );
            }
            enterFullscreen();
        }

        function enterFullscreen() {
            const elem = document.documentElement;
            
            if (elem.requestFullscreen) {
                elem.requestFullscreen().catch(() => {});
            } else if (elem.mozRequestFullScreen) {
                elem.mozRequestFullScreen();
            } else if (elem.webkitRequestFullscreen) {
                elem.webkitRequestFullscreen();
            } else if (elem.msRequestFullscreen) {
                elem.msRequestFullscreen();
            }
            
            const warningEl = document.getElementById('fullscreenWarning');
            if (warningEl) {
                warningEl.style.display = 'none';
            }
            startFullscreenMonitoring();
        }
        
        // Continuous fullscreen monitoring
        function startFullscreenMonitoring() {
            if (fullscreenInterval) clearInterval(fullscreenInterval);
            
            fullscreenInterval = setInterval(() => {
                if (!isFullscreenActive()) {
                    tryAutoFullscreen(false);
                    registerViolation('fullscreen_exit', false);
                }
            }, 600);
        }

        function startDevToolsMonitoring() {
            if (devToolsInterval) clearInterval(devToolsInterval);
            devToolsInterval = setInterval(() => {
                const widthGap = Math.abs(window.outerWidth - window.innerWidth);
                const heightGap = Math.abs(window.outerHeight - window.innerHeight);
                if ((widthGap > 170 || heightGap > 170) && !submissionInProgress) {
                    registerViolation('devtools_open', false);
                }
            }, 2000);
        }

        function startHeartbeat() {
            if (heartbeatInterval) clearInterval(heartbeatInterval);
            heartbeatInterval = setInterval(async () => {
                if (submissionInProgress) {
                    return;
                }
                try {
                    const formData = new FormData();
                    formData.append('heartbeat', '1');

                    const response = await fetch('take_test.php?test_id=<?php echo $hostedTestId; ?>', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();

                    if (!result.success && result.force_submit) {
                        forceSubmitTest(true);
                    }
                    if (typeof result.violations !== 'undefined') {
                        violationCount = Number(result.violations) || violationCount;
                        updateViolationUI();
                    }
                } catch (e) {
                    // no-op
                }
            }, 15000);
        }
        
        function showFullscreenWarning(message = null, subMessage = null) {
            const warningEl = document.getElementById('fullscreenWarning');
            const warningTextEl = document.getElementById('securityWarningText');
            const warningSubTextEl = document.getElementById('securityWarningSubText');

            if (warningTextEl && message) {
                warningTextEl.textContent = message;
            }
            if (warningSubTextEl && subMessage) {
                warningSubTextEl.textContent = subMessage;
            }

            if (warningEl) {
                warningEl.style.display = 'flex';
                warningEl.focus();
            }
        }
        
        // Prevent tab switching and window blur
        window.addEventListener('blur', function() {
            registerViolation('window_blur', false);
            setTimeout(() => {
                if (!isFullscreenActive()) {
                    tryAutoFullscreen(true);
                }
            }, 160);
        });

        // Keyboard prevention with explicit Ctrl+R/F5 refresh recovery support
        document.addEventListener('keydown', function(e) {
            const wantsRefresh = e.key === 'F5' || ((e.ctrlKey || e.metaKey) && (e.key === 'r' || e.key === 'R'));
            if (wantsRefresh) {
                prepareRefreshResume();
                return;
            }

            const forbiddenKeys = ['F11', 'F12', 'Escape', 'Tab', 'PrintScreen'];
            const isCtrlShortcut = e.ctrlKey && ['w', 'W', 't', 'T', 'n', 'N', 'p', 'P', '+', '-', '0'].includes(e.key);
            const isAltShortcut = e.altKey && e.key !== 'Alt';
            const isMetaShortcut = (e.metaKey || e.cmdKey) && ['w', 'W', 't', 'T', 'p', 'P'].includes(e.key);

            if (forbiddenKeys.includes(e.key) || isCtrlShortcut || isAltShortcut || isMetaShortcut) {
                e.preventDefault();
                e.stopPropagation();
                registerViolation('forbidden_key_' + e.key, true);
                return false;
            }

            if (e.ctrlKey && ['a', 'A', 'c', 'C', 'v', 'V', 'x', 'X'].includes(e.key)) {
                e.preventDefault();
                registerViolation('copy_paste_shortcut', false);
                return false;
            }
        });
        
        // Additional security measures
        document.addEventListener('contextmenu', e => { e.preventDefault(); registerViolation('context_menu', false); return false; });
        document.addEventListener('dragstart', e => { e.preventDefault(); return false; });
        document.addEventListener('selectstart', e => { e.preventDefault(); return false; });
        document.addEventListener('copy', e => { e.preventDefault(); registerViolation('copy_attempt', false); return false; });
        document.addEventListener('paste', e => { e.preventDefault(); registerViolation('paste_attempt', false); return false; });
        document.addEventListener('cut', e => { e.preventDefault(); registerViolation('cut_attempt', false); return false; });
        window.addEventListener('resize', function() {
            registerViolation('window_resize', false);
            if (!isFullscreenActive()) {
                tryAutoFullscreen(false);
            }
        });
        
        window.addEventListener('beforeunload', function(e) {
            if (!submissionInProgress && !plannedRefresh) {
                e.preventDefault();
                e.returnValue = 'Test in progress. Are you sure you want to leave?';
                return e.returnValue;
            }
        });
        
        // Visibility API for tab switching detection
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                registerViolation('tab_hidden', false);
            } else if (!submissionInProgress) {
                tryAutoFullscreen(true);
            }
        });
        
        // Force submit function for expiry/critical events
        function forceSubmitTest(skipAlert = false) {
            if (submissionInProgress) {
                return;
            }
            submissionInProgress = true;

            if (!skipAlert) {
                alert('Your test session ended. Submitting current answers.');
            }

            if (fullscreenInterval) clearInterval(fullscreenInterval);
            if (devToolsInterval) clearInterval(devToolsInterval);
            if (heartbeatInterval) clearInterval(heartbeatInterval);
            if (networkSpeedInterval) clearInterval(networkSpeedInterval);
            if (refreshGraceInterval) clearInterval(refreshGraceInterval);

            // Exit fullscreen
            if (document.exitFullscreen) document.exitFullscreen();
            else if (document.mozCancelFullScreen) document.mozCancelFullScreen();
            else if (document.webkitExitFullscreen) document.webkitExitFullscreen();
            else if (document.msExitFullscreen) document.msExitFullscreen();

            // Submit test
            const formData = new FormData();
            formData.append('submit_test', '1');
            document.querySelectorAll('input[name^="answers["]').forEach(input => {
                formData.append(input.name, input.value);
            });

            fetch('take_test.php?test_id=<?php echo $hostedTestId; ?>', {
                method: 'POST',
                body: formData
            })
            .finally(() => {
                window.location.href = 'test_report.php?test_id=<?php echo $hostedTestId; ?>';
            });
        }
        
        // Setup fullscreen handlers
        ['fullscreenchange', 'mozfullscreenchange', 'webkitfullscreenchange', 'msfullscreenchange']
        .forEach(event => {
            document.addEventListener(event, function() {
                if (!isFullscreenActive() && !submissionInProgress) {
                    tryAutoFullscreen(false);
                    registerViolation('fullscreen_change_event', false);
                }
            });
        });
        
        // Initialize on page load
        window.addEventListener('load', function() {
            updateViolationUI();
            restoreRefreshResumeIfAny();
            updateRefreshResumeUI();
            startNetworkSpeedMonitoring();
            setTimeout(() => {
                enterFullscreen();
                setTimeout(enterFullscreen, 1200);
                startDevToolsMonitoring();
                startHeartbeat();
            }, 400);
        });
        
        // Timer functionality
        let remainingTime = <?php echo $remainingSeconds; ?>;
        
        function updateTimer() {
            const hours = Math.floor(remainingTime / 3600);
            const minutes = Math.floor((remainingTime % 3600) / 60);
            const seconds = remainingTime % 60;
            
            document.getElementById('timeRemaining').textContent = 
                `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            if (remainingTime <= 0) {
                forceSubmitTest(true);
            } else {
                remainingTime--;
                setTimeout(updateTimer, 1000);
            }
        }
        
        updateTimer();
        
        // Question navigation functions
        function showQuestion(questionId) {
            document.querySelectorAll('.question-card').forEach(card => {
                card.style.display = 'none';
            });
            document.getElementById('question-' + questionId).style.display = 'block';
            currentQuestionId = questionId;
            
            document.querySelectorAll('.question-nav-item').forEach(item => {
                item.classList.remove('active');
            });
            document.querySelector(`[data-question="${questionId}"]`).classList.add('active');
            
            // Scroll to top of question
            document.querySelector('.main-content').scrollTop = 0;
            updateTopProgress();
        }
        
        function toggleSection(sectionIndex) {
            const sectionItem = document.querySelector(`[data-section="${sectionIndex}"]`);
            sectionItem.classList.toggle('active');
            
            // Toggle chevron icon
            const icon = sectionItem.querySelector('.toggle-icon');
            if (sectionItem.classList.contains('active')) {
                icon.className = 'fas fa-chevron-up toggle-icon';
            } else {
                icon.className = 'fas fa-chevron-down toggle-icon';
            }
        }
        
        function selectOption(questionId, optionValue) {
            // Remove selected class from all options of this question
            document.querySelectorAll(`#question-${questionId} .option`).forEach(option => {
                option.classList.remove('selected');
            });
            
            // Add selected class to clicked option
            event.currentTarget.classList.add('selected');
            
            // Update hidden input
            document.getElementById('answer-' + questionId).value = optionValue;
            
            // Mark as answered
            answeredQuestions.add(questionId);
            updateQuestionStatus(questionId);
            saveAnswer(questionId, optionValue);
        }
        
        function updateFillupAnswer(questionId, value) {
            if (value.trim() !== '') {
                answeredQuestions.add(questionId);
            } else {
                answeredQuestions.delete(questionId);
            }
            updateQuestionStatus(questionId);
            saveAnswer(questionId, value);
        }
        
        function updateQuestionStatus(questionId) {
            const navItem = document.querySelector(`[data-question="${questionId}"]`);
            if (answeredQuestions.has(questionId)) {
                navItem.classList.add('answered');
            } else {
                navItem.classList.remove('answered');
            }
            updateTopProgress();
        }
        
        function saveAnswer(questionId, answer) {
            clearTimeout(window.saveTimeout);
            window.saveTimeout = setTimeout(function() {
                const formData = new FormData();
                formData.append('auto_save', '1');
                formData.append(`answers[${questionId}]`, answer);
                
                fetch('take_test.php?test_id=<?php echo $hostedTestId; ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('Answer saved for question ' + questionId);
                    }
                });
            }, 2000);
        }
        
        function submitTest() {
            if (submissionInProgress) {
                return;
            }
            const unanswered = totalQuestions - answeredQuestions.size;
            const confirmMsg = unanswered > 0
                ? `You have ${unanswered} unanswered of ${totalQuestions} questions. Submit now?`
                : 'Are you sure you want to submit the test? You cannot make changes after submission.';

            if (confirm(confirmMsg)) {
                submissionInProgress = true;
                const submitBtn = document.getElementById('submitButton');
                const submitBtnTop = document.getElementById('submitButtonTop');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
                }
                if (submitBtnTop) {
                    submitBtnTop.disabled = true;
                    submitBtnTop.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
                }

                if (fullscreenInterval) clearInterval(fullscreenInterval);
                if (devToolsInterval) clearInterval(devToolsInterval);
                if (heartbeatInterval) clearInterval(heartbeatInterval);
                
                const formData = new FormData();
                formData.append('submit_test', '1');
                
                document.querySelectorAll('input[name^="answers["]').forEach(input => {
                    formData.append(input.name, input.value);
                });
                
                fetch('take_test.php?test_id=<?php echo $hostedTestId; ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (document.exitFullscreen) document.exitFullscreen();
                        window.location.href = 'test_report.php?test_id=<?php echo $hostedTestId; ?>';
                    } else {
                        alert(data.message || 'Error submitting test');
                        submissionInProgress = false;
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Test';
                        }
                        if (submitBtnTop) {
                            submitBtnTop.disabled = false;
                            submitBtnTop.innerHTML = '<i class="fas fa-paper-plane"></i> Submit';
                        }
                    }
                })
                .catch(() => {
                    alert('Error submitting test');
                    submissionInProgress = false;
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Test';
                    }
                    if (submitBtnTop) {
                        submitBtnTop.disabled = false;
                        submitBtnTop.innerHTML = '<i class="fas fa-paper-plane"></i> Submit';
                    }
                });
            }
        }
        
        // Initialize question statuses
        <?php foreach ($savedAnswers as $questionId => $answer): ?>
            <?php if (!empty($answer)): ?>
                answeredQuestions.add(<?php echo $questionId; ?>);
                updateQuestionStatus(<?php echo $questionId; ?>);
            <?php endif; ?>
        <?php endforeach; ?>
        
        // Show first question by default
        <?php if (!empty($allQuestionsFlat)): ?>
        showQuestion(<?php echo $allQuestionsFlat[0]['id']; ?>);
        <?php endif; ?>
        updateTopProgress();
    </script>
</body>
</html>

