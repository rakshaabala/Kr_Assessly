<?php
session_start();

// Check if student is logged in
if (!isset($_SESSION['student_id']) || $_SESSION['user_type'] !== 'student') {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../dbconn.php';
$pdo = getDBConnection();
if (!$pdo) {
    die("Database connection failed.");
}

$studentId = $_SESSION['student_id'];
$studentName = $_SESSION['user_name'] ?? ($_SESSION['student_name'] ?? 'Student');
$registerNumber = $_SESSION['register_number'] ?? '';
$testReports = [];
$answers = [];
$sections = [];
$summaryTotalTests = 0;
$summaryAnswersVisible = 0;
$summaryAvgPercent = 0;
$summaryBestPercent = 0;

// Get test ID from URL if specified
$hostedTestId = isset($_GET['test_id']) ? (int)$_GET['test_id'] : null;

// Fetch test reports for the student
if ($hostedTestId) {
    // Specific test report
    $stmt = $pdo->prepare("
        SELECT 
            ht.id as hosted_test_id,
            ht.test_date,
            ht.start_time,
            ht.end_time,
            ht.test_duration,
            ht.show_answers,
            t.test_name,
            t.test_code,
            hts.total_score,
            hts.submit_time,
            hts.test_status as student_test_status,
            COALESCE((
                SELECT SUM(ts.questions_to_display * ts.marks_per_question)
                FROM test_sections ts
                WHERE ts.test_id = t.id
            ), 0) AS possible_marks
        FROM hosted_tests ht
        INNER JOIN hosted_test_students hts ON ht.id = hts.hosted_test_id
        INNER JOIN tests t ON ht.test_id = t.id
        WHERE hts.student_id = :student_id 
        AND ht.id = :hosted_test_id
        AND (hts.test_status = 'completed' OR hts.submit_time IS NOT NULL)
    ");
    $stmt->execute([
        'student_id' => $studentId,
        'hosted_test_id' => $hostedTestId
    ]);
    $testReport = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$testReport) {
        header("Location: test_report.php");
        exit();
    }

    $possibleMarks = (float) ($testReport['possible_marks'] ?? 0);
    $totalScore = (float) ($testReport['total_score'] ?? 0);
    $testReport['score_percent'] = $possibleMarks > 0 ? round(($totalScore / $possibleMarks) * 100, 2) : 0;

    // Fetch detailed answers only when teacher enabled answer visibility
    if (($testReport['show_answers'] ?? 'no') === 'yes') {
        $answersStmt = $pdo->prepare("
            SELECT 
                q.id as question_id,
                q.question_type,
                q.question_text,
                q.option_a,
                q.option_b,
                q.option_c,
                q.option_d,
                q.correct_answer,
                sa.student_answer,
                sa.marks_obtained,
                sa.is_correct,
                ts.section_name
            FROM student_answers sa
            INNER JOIN questions q ON sa.question_id = q.id
            INNER JOIN test_sections ts ON q.section_id = ts.id
            WHERE sa.hosted_test_student_id = (
                SELECT hts.id FROM hosted_test_students hts 
                WHERE hts.student_id = :student_id AND hts.hosted_test_id = :hosted_test_id
            )
            ORDER BY ts.section_order, q.id
        ");
        $answersStmt->execute([
            'student_id' => $studentId,
            'hosted_test_id' => $hostedTestId
        ]);
        $answers = $answersStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($answers as $answer) {
            $sectionName = $answer['section_name'];
            if (!isset($sections[$sectionName])) {
                $sections[$sectionName] = [];
            }
            $sections[$sectionName][] = $answer;
        }
    }
} else {
    // All test reports for the student
    $stmt = $pdo->prepare("
        SELECT 
            ht.id as hosted_test_id,
            ht.test_date,
            ht.start_time,
            ht.end_time,
            ht.test_duration,
            ht.show_answers,
            t.test_name,
            t.test_code,
            hts.total_score,
            hts.submit_time,
            hts.test_status as student_test_status,
            COALESCE((
                SELECT SUM(ts.questions_to_display * ts.marks_per_question)
                FROM test_sections ts
                WHERE ts.test_id = t.id
            ), 0) AS possible_marks
        FROM hosted_tests ht
        INNER JOIN hosted_test_students hts ON ht.id = hts.hosted_test_id
        INNER JOIN tests t ON ht.test_id = t.id
        WHERE hts.student_id = :student_id 
        AND (hts.test_status = 'completed' OR hts.submit_time IS NOT NULL)
        ORDER BY ht.test_date DESC, ht.start_time DESC
    ");
    $stmt->execute(['student_id' => $studentId]);
    $testReports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $summaryTotalTests = count($testReports);
    $summaryAnswersVisible = 0;
    $summaryBestPercent = 0;
    $sumPercent = 0;

    foreach ($testReports as &$report) {
        $possibleMarks = (float) ($report['possible_marks'] ?? 0);
        $score = (float) ($report['total_score'] ?? 0);
        $percent = $possibleMarks > 0 ? round(($score / $possibleMarks) * 100, 2) : 0;
        $report['score_percent'] = $percent;

        $sumPercent += $percent;
        if ($percent > $summaryBestPercent) {
            $summaryBestPercent = $percent;
        }
        if (($report['show_answers'] ?? 'no') === 'yes') {
            $summaryAnswersVisible++;
        }
    }
    unset($report);

    if ($summaryTotalTests > 0) {
        $summaryAvgPercent = round($sumPercent / $summaryTotalTests, 2);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Test Reports - KR ASSESSLY</title>
    <!-- plugins:css -->
    <link rel="stylesheet" href="../vendors/feather/feather.css">
    <link rel="stylesheet" href="../vendors/ti-icons/css/themify-icons.css">
    <link rel="stylesheet" href="../vendors/css/vendor.bundle.base.css">
    <!-- endinject -->
    <!-- Plugin css for this page -->
    <link rel="stylesheet" href="../vendors/ti-icons/css/themify-icons.css">
    <!-- End plugin css for this page -->
    <!-- inject:css -->
    <link rel="stylesheet" href="../css/vertical-layout-light/style.css">
    <!-- endinject -->
    <link rel="shortcut icon" href="../images/favicon.jpg" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        .report-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border: 1px solid #e8ecf1;
        }
        
        .report-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        
        .test-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0 0 5px 0;
        }
        
        .test-meta {
            font-size: 0.9rem;
            opacity: 0.9;
            margin: 0;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 25px 0;
        }
        
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid #667eea;
        }
        
        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #667eea;
            margin: 5px 0;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
            margin: 0;
        }
        
        .section-card {
            background: #f8f9fc;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            border-left: 4px solid #28a745;
        }
        
        .section-header {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .question-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
            border: 1px solid #e8ecf1;
        }
        
        .question-text {
            font-weight: 600;
            margin-bottom: 10px;
            color: #2c3e50;
        }
        
        .option-item {
            margin: 8px 0;
            padding: 8px;
            border-radius: 4px;
        }
        
        .option-correct {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .option-incorrect {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .option-other {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            color: #495057;
        }
        
        .answer-indicator {
            font-weight: 600;
            margin-top: 5px;
        }
        
        .correct-answer {
            color: #28a745;
            font-weight: 600;
        }
        
        .incorrect-answer {
            color: #dc3545;
            font-weight: 600;
        }
        
        .back-button {
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 20px;
        }
        
        .back-button:hover {
            background: #5a6268;
        }
        
        .overall-score {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
            text-align: center;
            margin: 20px 0;
        }
        
        .score-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
            margin-top: 10px;
        }
        
        .score-good {
            background: #d4edda;
            color: #155724;
        }
        
        .score-average {
            background: #fff3cd;
            color: #856404;
        }
        
        .score-poor {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container-scroller">
        <!-- partial:partials/_navbar.html -->
        <nav class="navbar col-lg-12 col-12 p-0 fixed-top d-flex flex-row">
            <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-center">
                <a class="navbar-brand brand-logo " href="dashboard.php"><img src="../images/full-logo-wo-bg.png"  width="100px"  class="mr-2" alt="logo"/></a>
                <a class="navbar-brand brand-logo-mini" href="dashboard.php"><img src="../images/small-logo-wo-bg.png" alt="logo"/></a>
            </div>
            <div class="navbar-menu-wrapper d-flex align-items-center justify-content-end">
                <button class="navbar-toggler navbar-toggler align-self-center" type="button" data-toggle="minimize">
                    <span class="icon-menu"></span>
                </button>
                
                <ul class="navbar-nav navbar-nav-right">
                  
                  <li class="nav-item nav-profile dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown" id="profileDropdown">
                      <img src="../images/profile-logout-wo-bg.png" alt="profile"/>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right navbar-dropdown" aria-labelledby="profileDropdown">
                      <a class="dropdown-item" href="logout.php">
                        <i class="ti-power-off text-primary"></i>
                        Logout
                      </a>
                    </div>
                  </li>
                  
                </ul>
                <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button" data-toggle="offcanvas">
                    <span class="icon-menu"></span>
                </button>
            </div>
        </nav>
        <!-- partial -->
        <div class="container-fluid page-body-wrapper">
            <!-- partial:partials/_settings-panel.html -->
            <!-- partial -->
            <!-- partial:partials/_sidebar.html -->
            <nav class="sidebar sidebar-offcanvas" id="sidebar">
                <ul class="nav">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fa-solid fa-gauge-high menu-icon"></i>
                            <span class="menu-title">Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="test_list.php">
                            <i class="fa-solid fa-clipboard-list menu-icon"></i>
                            <span class="menu-title">Test List</span>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="test_report.php">
                            <i class="fa-solid fa-chart-column menu-icon"></i>
                            <span class="menu-title">Test Reports</span>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">
                            <i class="fa-solid fa-user-gear menu-icon"></i>
                            <span class="menu-title">Profile</span>
                        </a>
                    </li>
                    
                </ul>
            </nav>
            <!-- partial -->
            <div class="main-panel">
                <div class="content-wrapper">
                    <div class="row">
                        <div class="col-12 grid-margin">
                            <div class="row">
                                <div class="col-12">
                                    <h3 class="font-weight-bold">Test Reports</h3>
                                    <h6 class="font-weight-normal mb-0">View your test performance and detailed reports</h6>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($hostedTestId && $testReport): ?>
                    <!-- Specific Test Report -->
                    <div class="row">
                        <div class="col-12">
                            <button class="back-button" onclick="window.location.href='test_report.php'">
                                <i class="fas fa-arrow-left"></i> Back to All Reports
                            </button>
                            
                            <div class="report-header">
                                <h2 class="test-title"><?php echo htmlspecialchars($testReport['test_name']); ?></h2>
                                <p class="test-meta">
                                    <i class="fas fa-calendar"></i> <?php echo date('d M Y', strtotime($testReport['test_date'])); ?> | 
                                    <i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($testReport['start_time'])); ?> - <?php echo date('h:i A', strtotime($testReport['end_time'])); ?> | 
                                    <i class="fas fa-hourglass-half"></i> <?php echo $testReport['test_duration']; ?> minutes
                                </p>
                            </div>
                            
                            <div class="stats-grid">
                                <div class="stat-card">
                                    <div class="stat-value"><?php echo number_format((float)$testReport['total_score'], 2); ?></div>
                                    <div class="stat-label">Marks Scored</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-value"><?php echo number_format((float)$testReport['possible_marks'], 2); ?></div>
                                    <div class="stat-label">Total Marks</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-value"><?php echo number_format((float)$testReport['score_percent'], 2); ?>%</div>
                                    <div class="stat-label">Percentage</div>
                                </div>
                            </div>
                            
                            <div class="overall-score">
                                <?php echo number_format((float)$testReport['total_score'], 2); ?> / <?php echo number_format((float)$testReport['possible_marks'], 2); ?>
                                <?php 
                                $score = (float)$testReport['score_percent'];
                                $badgeClass = 'score-poor';
                                if ($score >= 70) $badgeClass = 'score-good';
                                elseif ($score >= 50) $badgeClass = 'score-average';
                                ?>
                                <span class="score-badge <?php echo $badgeClass; ?>">
                                    <?php 
                                    if ($score >= 85) echo 'Excellent!';
                                    elseif ($score >= 70) echo 'Good Job!';
                                    elseif ($score >= 50) echo 'Satisfactory';
                                    else echo 'Needs Improvement';
                                    ?>
                                </span>
                            </div>
                            
                            <div class="alert <?php echo $testReport['show_answers'] === 'yes' ? 'alert-success' : 'alert-warning'; ?>">
                                <i class="fas <?php echo $testReport['show_answers'] === 'yes' ? 'fa-unlock' : 'fa-lock'; ?>"></i>
                                <?php echo $testReport['show_answers'] === 'yes'
                                    ? 'Teacher enabled answer review for this test.'
                                    : 'Teacher has hidden detailed answers for this test.'; ?>
                            </div>

                            <?php if ($testReport['show_answers'] === 'yes'): ?>
                            <?php if (!empty($sections)): ?>
                            <?php foreach ($sections as $sectionName => $sectionQuestions): ?>
                            <div class="section-card">
                                <div class="section-header"><?php echo htmlspecialchars($sectionName); ?></div>
                                
                                <?php foreach ($sectionQuestions as $answer): ?>
                                <div class="question-card">
                                    <div class="question-text">
                                        <i class="fas fa-question-circle"></i> 
                                        <?php echo htmlspecialchars($answer['question_text']); ?>
                                    </div>
                                    
                                    <?php if ($answer['question_type'] === 'mcq'): ?>
                                        <?php 
                                        $options = [
                                            'A' => $answer['option_a'],
                                            'B' => $answer['option_b'],
                                            'C' => $answer['option_c'],
                                            'D' => $answer['option_d']
                                        ];
                                        
                                        foreach ($options as $key => $value):
                                            if (empty($value)) continue;
                                            $class = '';
                                            if ($key === $answer['student_answer']) {
                                                $class = $key === $answer['correct_answer'] ? 'option-correct' : 'option-incorrect';
                                            } else {
                                                $class = 'option-other';
                                            }
                                        ?>
                                            <div class="option-item <?php echo $class; ?>">
                                                <strong><?php echo $key; ?>.</strong> <?php echo htmlspecialchars($value); ?>
                                                <?php if ($key === $answer['student_answer']): ?>
                                                    <span class="answer-indicator">(Your Answer)</span>
                                                <?php endif; ?>
                                                <?php if ($key === $answer['correct_answer']): ?>
                                                    <span class="answer-indicator">(Correct)</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php elseif ($answer['question_type'] === 'truefalse'): ?>
                                        <div class="option-item <?php echo $answer['student_answer'] === $answer['correct_answer'] ? 'option-correct' : 'option-incorrect'; ?>">
                                            <strong>Your Answer:</strong> <?php echo htmlspecialchars($answer['student_answer']); ?>
                                        </div>
                                        <div class="option-item option-other">
                                            <strong>Correct Answer:</strong> <?php echo htmlspecialchars($answer['correct_answer']); ?>
                                        </div>
                                    <?php elseif ($answer['question_type'] === 'fillup'): ?>
                                        <div class="option-item <?php echo strtolower(trim($answer['student_answer'])) === strtolower(trim($answer['correct_answer'])) ? 'option-correct' : 'option-incorrect'; ?>">
                                            <strong>Your Answer:</strong> <?php echo htmlspecialchars($answer['student_answer']); ?>
                                        </div>
                                        <div class="option-item option-other">
                                            <strong>Expected Answer:</strong> <?php echo htmlspecialchars($answer['correct_answer']); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="answer-indicator mt-2">
                                        <?php if ($answer['is_correct']): ?>
                                            <span class="correct-answer"><i class="fas fa-check-circle"></i> Correct (+<?php echo $answer['marks_obtained']; ?>)</span>
                                        <?php else: ?>
                                            <span class="incorrect-answer"><i class="fas fa-times-circle"></i> Incorrect (<?php echo $answer['marks_obtained']; ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endforeach; ?>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> Answers are not available yet for this submission.
                                </div>
                            <?php endif; ?>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> Detailed answers are currently hidden by your teacher.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <!-- All Test Reports -->
                    <div class="row">
                        <div class="col-12">
                            <?php if (!empty($testReports)): ?>
                            <div class="stats-grid">
                                <div class="stat-card">
                                    <div class="stat-value"><?php echo $summaryTotalTests; ?></div>
                                    <div class="stat-label">Completed Tests</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-value"><?php echo number_format((float)$summaryAvgPercent, 2); ?>%</div>
                                    <div class="stat-label">Average Percentage</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-value"><?php echo number_format((float)$summaryBestPercent, 2); ?>%</div>
                                    <div class="stat-label">Best Percentage</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-value"><?php echo $summaryAnswersVisible; ?></div>
                                    <div class="stat-label">Reports With Answers</div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if (empty($testReports)): ?>
                                <div class="report-card text-center">
                                    <i class="fas fa-chart-bar" style="font-size: 3rem; color: #ddd; margin-bottom: 20px;"></i>
                                    <h4>No Test Reports Available</h4>
                                    <p class="text-muted">You haven't completed any tests yet. Once you finish a test, the report will appear here.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($testReports as $report): ?>
                                <div class="report-card">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h4><?php echo htmlspecialchars($report['test_name']); ?></h4>
                                            <p class="text-muted mb-2">
                                                <i class="fas fa-calendar"></i> <?php echo date('d M Y', strtotime($report['test_date'])); ?> | 
                                                <i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($report['start_time'])); ?> - <?php echo date('h:i A', strtotime($report['end_time'])); ?>
                                            </p>
                                            <p class="text-muted mb-0">
                                                Test Code: <strong><?php echo htmlspecialchars($report['test_code']); ?></strong>
                                            </p>
                                            <p class="mb-0 mt-2">
                                                <span class="badge <?php echo ($report['show_answers'] === 'yes') ? 'badge-success' : 'badge-secondary'; ?>">
                                                    <?php echo ($report['show_answers'] === 'yes') ? 'Answers Visible' : 'Answers Hidden'; ?>
                                                </span>
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <div class="overall-score" style="font-size: 1.2rem; margin: 0;">
                                                <?php echo number_format((float)$report['total_score'], 2); ?> / <?php echo number_format((float)$report['possible_marks'], 2); ?>
                                            </div>
                                            <div style="font-weight: 700; color: #667eea;"><?php echo number_format((float)$report['score_percent'], 2); ?>%</div>
                                            <a href="test_report.php?test_id=<?php echo $report['hosted_test_id']; ?>" class="btn btn-primary btn-sm mt-2">
                                                <i class="fas fa-eye"></i> View Details
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <!-- content-wrapper ends -->
                <!-- partial:partials/_footer.html -->
                <footer class="footer" style="background: linear-gradient(90deg, #594ba1ff 0%, #2575fc 100%);color: white;padding: 30px 0;">
                    <div class="text-center">
                        <span style="font-family: 'Segoe UI', 'Roboto', 'Helvetica Neue', sans-serif;font-size: 1rem;color: white;letter-spacing: 0.3px;">
                            © 2025, Designed and Developed by
                            <strong style="font-weight: 700;text-transform: uppercase;letter-spacing: 0.8px;color: white; font-style: italic; font-family:'Franklin Gothic Medium', 'Arial Narrow', Arial, sans-serif;">KR ASSESSLY TEAM</strong>- All rights reserved.
                        </span>
                    </div>
                </footer>
                <!-- partial -->
            </div>
            <!-- main-panel ends -->
        </div>   
        <!-- page-body-wrapper ends -->
    </div>
    <!-- container-scroller -->

    <!-- plugins:js -->
    <script src="../vendors/js/vendor.bundle.base.js"></script>
    <!-- endinject -->
    <!-- Plugin js for this page -->
    <script src="../vendors/chart.js/Chart.min.js"></script>

    <!-- End plugin js for this page -->
    <!-- inject:js -->
    <script src="../js/off-canvas.js"></script>
    <script src="../js/hoverable-collapse.js"></script>
    <script src="../js/template.js"></script>
    <script src="../js/settings.js"></script>
    <script src="../js/todolist.js"></script>
    <!-- endinject -->
    <!-- Custom js for this page-->
    <script src="../js/dashboard.js"></script>
    <script src="../js/Chart.roundedBarCharts.js"></script>
    <!-- End custom js for this page-->
</body>
</html>
