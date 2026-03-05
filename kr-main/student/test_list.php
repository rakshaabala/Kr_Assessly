<?php
session_start();

// Set timezone to Indian Standard Time
date_default_timezone_set('Asia/Kolkata');

// Check if student is logged in
if (!isset($_SESSION['student_id']) || $_SESSION['user_type'] !== 'student') {
    header("Location: ../index.php");
    exit();
}

// Database connection
$host = 'localhost';
$db = 'db_kr_assessly';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$studentId = $_SESSION['student_id'];
$studentName = $_SESSION['student_name'] ?? 'Student';

// Function to determine actual test status based on business logic
function determineTestStatus($pdo, $test) {
    $studentTestId = $test['student_test_id'];
    $submitTime = $test['student_submit_time'];
    $testDate = $test['test_date'];
    $endTime = $test['end_time'];
    
    // Combine test date and end time
    $testEndDateTime = new DateTime($testDate . ' ' . $endTime);
    $currentDateTime = new DateTime();
    
    // If submit_time is NULL or empty
    if ($submitTime === null || $submitTime === '') {
        // Check if test end time has passed
        if ($currentDateTime > $testEndDateTime) {
            return 'not_attempted';
        } else {
            return 'scheduled';
        }
    }
    
    // If submit_time exists, check if marks are present in student_answers
    $checkMarksQuery = "
        SELECT COUNT(*) as answer_count, 
               SUM(CASE WHEN marks_obtained IS NOT NULL THEN 1 ELSE 0 END) as marks_count
        FROM student_answers 
        WHERE hosted_test_student_id = :student_test_id
    ";
    
    $stmt = $pdo->prepare($checkMarksQuery);
    $stmt->execute(['student_test_id' => $studentTestId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If no answers recorded at all
    if ($result['answer_count'] == 0) {
        return 'not_attempted';
    }
    
    // If answers exist but no marks obtained (marks are NULL)
    if ($result['answer_count'] > 0 && $result['marks_count'] == 0) {
        return 'false_activity';
    }
    
    // If answers exist and marks are present
    if ($result['answer_count'] > 0 && $result['marks_count'] > 0) {
        return 'completed';
    }
    
    return 'scheduled';
}

// Fetch ALL tests for the student with test details and sections
$allTestsQuery = "
    SELECT 
        ht.id as hosted_test_id,
        ht.test_id,
        ht.test_date,
        ht.start_time,
        ht.end_time,
        ht.test_duration,
        ht.hosted_at,
        ht.status as test_status,
        hts.id as student_test_id,
        hts.test_status as student_test_status,
        hts.start_time as student_start_time,
        hts.submit_time as student_submit_time,
        hts.total_score,
        t.test_name,
        t.test_code
    FROM hosted_tests ht
    INNER JOIN hosted_test_students hts ON ht.id = hts.hosted_test_id
    INNER JOIN tests t ON ht.test_id = t.id
    WHERE hts.student_id = :student_id
    ORDER BY ht.test_date ASC, ht.start_time ASC
";

$allTestsStmt = $pdo->prepare($allTestsQuery);
$allTestsStmt->execute(['student_id' => $studentId]);
$allTests = $allTestsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get test sections for each test
function getTestSections($pdo, $testId) {
    $sectionsQuery = "
        SELECT 
            section_name,
            section_order,
            total_questions,
            questions_to_display,
            marks_per_question,
            negative_marks
        FROM test_sections
        WHERE test_id = :test_id
        ORDER BY section_order ASC
    ";
    
    $stmt = $pdo->prepare($sectionsQuery);
    $stmt->execute(['test_id' => $testId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Initialize arrays
$scheduledTests = [];
$completedTests = [];
$testNumber = 1;

// Process and determine actual status for each test
foreach ($allTests as $test) {
    $actualStatus = determineTestStatus($pdo, $test);
    $test['actual_status'] = $actualStatus;
    $test['test_number'] = $testNumber++;
    $test['sections'] = getTestSections($pdo, $test['test_id']);
    
    if ($actualStatus == 'scheduled') {
        $scheduledTests[] = $test;
    } else {
        $test['test_status'] = $actualStatus;
        $completedTests[] = $test;
    }
}

// Function to check if test can be taken
function canTakeTest($testDate, $startTime, $endTime) {
    $currentDateTime = new DateTime();
    $testStart = new DateTime($testDate . ' ' . $startTime);
    $testEnd = new DateTime($testDate . ' ' . $endTime);
    
    return ($currentDateTime >= $testStart && $currentDateTime <= $testEnd);
}

// Function to get button status
function getButtonStatus($testDate, $startTime, $endTime, $studentStartTime) {
    if ($studentStartTime !== null) {
        return 'taken';
    }
    
    return canTakeTest($testDate, $startTime, $endTime) ? 'active' : 'inactive';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Test List - KR ASSESSLY</title>
  <link rel="stylesheet" href="../vendors/feather/feather.css">
  <link rel="stylesheet" href="../vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" href="../vendors/css/vendor.bundle.base.css">
  <link rel="stylesheet" href="../css/vertical-layout-light/style.css">
  <link rel="shortcut icon" href="../images/favicon.jpg" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  
  <style>
    .test-card {
      margin-bottom: 18px;
      border-radius: 12px;
      box-shadow: 0 2px 12px rgba(0,0,0,0.08);
      transition: all 0.3s ease;
      border: 1px solid #e8ecf1;
      overflow: hidden;
    }
    
    .test-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 6px 20px rgba(0,0,0,0.12);
      border-color: #667eea;
    }
    
    .test-header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 14px 18px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .test-number {
      font-size: 0.7rem;
      font-weight: 600;
      opacity: 0.9;
      letter-spacing: 0.5px;
      text-transform: uppercase;
    }
    
    .test-name {
      font-size: 1.1rem;
      font-weight: 700;
      margin: 2px 0 0 0;
      line-height: 1.3;
    }
    
    .test-code {
      font-size: 0.75rem;
      opacity: 0.85;
      font-family: 'Courier New', monospace;
      margin-top: 2px;
    }
    
    .test-body {
      padding: 16px 18px;
      background: #fff;
    }
    
    .test-info-row {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      margin-bottom: 14px;
    }
    
    .test-info {
      display: flex;
      align-items: center;
      font-size: 0.8rem;
      color: #5a6169;
      flex: 1;
      min-width: 140px;
    }
    
    .test-info i {
      margin-right: 6px;
      color: #667eea;
      font-size: 0.85rem;
      width: 16px;
      text-align: center;
    }
    
    .sections-container {
      background: #f8f9fc;
      border-radius: 8px;
      padding: 12px;
      margin-top: 12px;
    }
    
    .sections-title {
      font-size: 0.75rem;
      font-weight: 700;
      color: #667eea;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 8px;
      display: flex;
      align-items: center;
    }
    
    .sections-title i {
      margin-right: 6px;
      font-size: 0.8rem;
    }
    
    .section-item {
      background: white;
      border-radius: 6px;
      padding: 8px 10px;
      margin-bottom: 6px;
      border-left: 3px solid #667eea;
      font-size: 0.75rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .section-item:last-child {
      margin-bottom: 0;
    }
    
    .section-name {
      font-weight: 600;
      color: #2c3e50;
      flex: 1;
    }
    
    .section-details {
      display: flex;
      gap: 10px;
      font-size: 0.7rem;
      color: #6c757d;
    }
    
    .section-detail-item {
      display: flex;
      align-items: center;
      gap: 3px;
    }
    
    .badge-scheduled {
      background: #ffc107;
      color: #000;
      font-size: 0.7rem;
      padding: 4px 10px;
      border-radius: 12px;
      font-weight: 600;
    }
    
    .badge-completed {
      background: #28a745;
      color: #fff;
      font-size: 0.7rem;
      padding: 4px 10px;
      border-radius: 12px;
      font-weight: 600;
    }
    
    .badge-false-activity {
      background: #dc3545;
      color: #fff;
      font-size: 0.7rem;
      padding: 4px 10px;
      border-radius: 12px;
      font-weight: 600;
    }
    
    .badge-not-attempted {
      background: #6c757d;
      color: #fff;
      font-size: 0.7rem;
      padding: 4px 10px;
      border-radius: 12px;
      font-weight: 600;
    }
    
    .btn-take-test {
      width: 100%;
      margin-top: 12px;
      padding: 10px;
      font-weight: 600;
      border-radius: 8px;
      font-size: 0.85rem;
      transition: all 0.3s ease;
    }
    
    .btn-take-test:hover:not(:disabled) {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }
    
    .btn-view-report {
      padding: 6px 14px;
      font-size: 0.75rem;
      border-radius: 6px;
      font-weight: 600;
      margin-top: 10px;
    }
    
    .section-title {
      font-size: 1.3rem;
      font-weight: 700;
      margin-bottom: 18px;
      color: #2c3e50;
      display: flex;
      align-items: center;
      border-bottom: 2px solid #667eea;
      padding-bottom: 8px;
    }
    
    .section-title i {
      margin-right: 10px;
      color: #667eea;
    }
    
    .no-tests {
      text-align: center;
      padding: 40px;
      color: #999;
    }
    
    .score-display {
      font-size: 1.3rem;
      font-weight: 700;
      color: #667eea;
      text-align: center;
      padding: 10px;
      background: linear-gradient(135deg, #f8f9fc 0%, #e8ecf1 100%);
      border-radius: 8px;
      margin-top: 10px;
      border: 2px solid #667eea;
    }
    
    .submit-time {
      font-size: 0.75rem;
      color: #6c757d;
      text-align: center;
      margin-top: 6px;
      font-style: italic;
    }
  </style>
</head>

<body>
  <div class="container-scroller">
    <!-- Navbar -->
    <nav class="navbar col-lg-12 col-12 p-0 fixed-top d-flex flex-row">
      <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-center">
        <a class="navbar-brand brand-logo" href="dashboard.php"><img src="../images/full-logo-wo-bg.png" width="100px" class="mr-2" alt="logo"/></a>
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

    <div class="container-fluid page-body-wrapper">
      <!-- Sidebar -->
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

      <!-- Main Panel -->
      <div class="main-panel">
        <div class="content-wrapper">
          <div class="row">
            <div class="col-md-12 grid-margin">
              <div class="row">
                <div class="col-12">
                  <h3 class="font-weight-bold">Test List</h3>
                  <h6 class="font-weight-normal mb-0">View your scheduled and completed tests</h6>
                </div>
              </div>
            </div>
          </div>

          <div class="row">
            <!-- Scheduled Tests Column -->
            <div class="col-md-6">
              <div class="section-title">
                <i class="fas fa-clock"></i> Scheduled Tests
              </div>
              
              <?php if (empty($scheduledTests)): ?>
                <div class="card test-card">
                  <div class="card-body no-tests">
                    <i class="fas fa-calendar-times" style="font-size: 3rem; color: #ddd;"></i>
                    <p class="mt-3">No scheduled tests available</p>
                  </div>
                </div>
              <?php else: ?>
                <?php foreach ($scheduledTests as $test): ?>
                  <?php 
                    $buttonStatus = getButtonStatus(
                      $test['test_date'], 
                      $test['start_time'], 
                      $test['end_time'],
                      $test['student_start_time']
                    );
                  ?>
                  <div class="card test-card">
                    <div class="test-header">
                      <div>
                        <div class="test-number">Test #<?php echo $test['test_number']; ?></div>
                        <h5 class="test-name"><?php echo htmlspecialchars($test['test_name']); ?></h5>
                        <div class="test-code"><?php echo htmlspecialchars($test['test_code']); ?></div>
                      </div>
                      <span class="badge badge-scheduled">Scheduled</span>
                    </div>
                    <div class="test-body">
                      <div class="test-info-row">
                        <div class="test-info">
                          <i class="fas fa-calendar"></i>
                          <span><?php echo date('d M Y', strtotime($test['test_date'])); ?></span>
                        </div>
                        <div class="test-info">
                          <i class="fas fa-hourglass-half"></i>
                          <span><?php echo $test['test_duration']; ?> mins</span>
                        </div>
                      </div>
                      <div class="test-info-row">
                        <div class="test-info">
                          <i class="fas fa-clock"></i>
                          <span><?php echo date('h:i A', strtotime($test['start_time'])); ?> - <?php echo date('h:i A', strtotime($test['end_time'])); ?></span>
                        </div>
                      </div>
                      
                      <?php if (!empty($test['sections'])): ?>
                        <div class="sections-container">
                          <div class="sections-title">
                            <i class="fas fa-layer-group"></i>
                            Sections (<?php echo count($test['sections']); ?>)
                          </div>
                          <?php foreach ($test['sections'] as $section): ?>
                            <div class="section-item">
                              <div class="section-name"><?php echo htmlspecialchars($section['section_name']); ?></div>
                              <div class="section-details">
                                <div class="section-detail-item">
                                  <i class="fas fa-question-circle"></i>
                                  <span><?php echo $section['questions_to_display']; ?>Q</span>
                                </div>
                                <div class="section-detail-item">
                                  <i class="fas fa-star"></i>
                                  <span>+<?php echo $section['marks_per_question']; ?></span>
                                </div>
                                <?php if ($section['negative_marks'] > 0): ?>
                                  <div class="section-detail-item">
                                    <i class="fas fa-minus-circle"></i>
                                    <span>-<?php echo $section['negative_marks']; ?></span>
                                  </div>
                                <?php endif; ?>
                              </div>
                            </div>
                          <?php endforeach; ?>
                        </div>
                      <?php endif; ?>
                      
                      <?php if ($buttonStatus === 'taken'): ?>
                        <button class="btn btn-secondary btn-take-test" disabled>
                          <i class="fas fa-check-circle"></i> Test Already Taken
                        </button>
                      <?php elseif ($buttonStatus === 'active'): ?>
                        <a href="take_test.php?test_id=<?php echo $test['hosted_test_id']; ?>" class="btn btn-primary btn-take-test">
                          <i class="fas fa-play-circle"></i> Take Test Now
                        </a>
                      <?php else: ?>
                        <button class="btn btn-secondary btn-take-test" disabled>
                          <i class="fas fa-lock"></i> Available at <?php echo date('h:i A', strtotime($test['start_time'])); ?>
                        </button>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>

            <!-- Completed Tests Column -->
            <div class="col-md-6">
              <div class="section-title">
                <i class="fas fa-check-circle"></i> Completed Tests
              </div>
              
              <?php if (empty($completedTests)): ?>
                <div class="card test-card">
                  <div class="card-body no-tests">
                    <i class="fas fa-clipboard-check" style="font-size: 3rem; color: #ddd;"></i>
                    <p class="mt-3">No completed tests yet</p>
                  </div>
                </div>
              <?php else: ?>
                <?php foreach ($completedTests as $test): ?>
                  <?php
                    $statusBadge = '';
                    $statusText = '';
                    $badgeClass = '';
                    
                    switch($test['test_status']) {
                      case 'completed':
                        $statusText = 'Completed';
                        $badgeClass = 'badge-completed';
                        break;
                      case 'false_activity':
                        $statusText = 'False Activity';
                        $badgeClass = 'badge-false-activity';
                        break;
                      case 'not_attempted':
                        $statusText = 'Not Attempted';
                        $badgeClass = 'badge-not-attempted';
                        break;
                      default:
                        $statusText = 'Unknown';
                        $badgeClass = 'badge-secondary';
                    }
                  ?>
                  <div class="card test-card">
                    <div class="test-header">
                      <div>
                        <div class="test-number">Test #<?php echo $test['test_number']; ?></div>
                        <h5 class="test-name"><?php echo htmlspecialchars($test['test_name']); ?></h5>
                        <div class="test-code"><?php echo htmlspecialchars($test['test_code']); ?></div>
                      </div>
                      <span class="badge <?php echo $badgeClass; ?>"><?php echo $statusText; ?></span>
                    </div>
                    <div class="test-body">
                      <div class="test-info-row">
                        <div class="test-info">
                          <i class="fas fa-calendar"></i>
                          <span><?php echo date('d M Y', strtotime($test['test_date'])); ?></span>
                        </div>
                        <div class="test-info">
                          <i class="fas fa-hourglass-half"></i>
                          <span><?php echo $test['test_duration']; ?> mins</span>
                        </div>
                      </div>
                      <div class="test-info-row">
                        <div class="test-info">
                          <i class="fas fa-clock"></i>
                          <span><?php echo date('h:i A', strtotime($test['start_time'])); ?> - <?php echo date('h:i A', strtotime($test['end_time'])); ?></span>
                        </div>
                      </div>
                      
                      <?php if (!empty($test['sections'])): ?>
                        <div class="sections-container">
                          <div class="sections-title">
                            <i class="fas fa-layer-group"></i>
                            Sections (<?php echo count($test['sections']); ?>)
                          </div>
                          <?php foreach ($test['sections'] as $section): ?>
                            <div class="section-item">
                              <div class="section-name"><?php echo htmlspecialchars($section['section_name']); ?></div>
                              <div class="section-details">
                                <div class="section-detail-item">
                                  <i class="fas fa-question-circle"></i>
                                  <span><?php echo $section['questions_to_display']; ?>Q</span>
                                </div>
                                <div class="section-detail-item">
                                  <i class="fas fa-star"></i>
                                  <span>+<?php echo $section['marks_per_question']; ?></span>
                                </div>
                                <?php if ($section['negative_marks'] > 0): ?>
                                  <div class="section-detail-item">
                                    <i class="fas fa-minus-circle"></i>
                                    <span>-<?php echo $section['negative_marks']; ?></span>
                                  </div>
                                <?php endif; ?>
                              </div>
                            </div>
                          <?php endforeach; ?>
                        </div>
                      <?php endif; ?>
                      
                      <?php if ($test['test_status'] === 'completed' && $test['total_score'] !== null): ?>
                        <div class="score-display">
                          <i class="fas fa-trophy"></i> Score: <?php echo htmlspecialchars($test['total_score']); ?>
                        </div>
                        <?php if ($test['student_submit_time']): ?>
                          <div class="submit-time">
                            Submitted: <?php echo date('d M Y, h:i A', strtotime($test['student_submit_time'])); ?>
                          </div>
                        <?php endif; ?>
                        <a href="test_report.php?test_id=<?php echo $test['hosted_test_id']; ?>" class="btn btn-outline-primary btn-view-report">
                          <i class="fas fa-chart-line"></i> View Report
                        </a>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <footer class="footer" style="background: linear-gradient(90deg, #594ba1ff 0%, #2575fc 100%);color: white;padding: 30px 0;">
          <div class="text-center">
            <span style="font-family: 'Segoe UI', 'Roboto', 'Helvetica Neue', sans-serif;font-size: 1rem;color: white;letter-spacing: 0.3px;">
              © 2025, Designed and Developed by
              <strong style="font-weight: 700;text-transform: uppercase;letter-spacing: 0.8px;color: white; font-style: italic; font-family:'Franklin Gothic Medium', 'Arial Narrow', Arial, sans-serif;">KR ASSESSLY TEAM</strong> - All rights reserved.
            </span>
          </div>
        </footer>
      </div>
    </div>
  </div>

  <script src="../vendors/js/vendor.bundle.base.js"></script>
  <script src="../js/off-canvas.js"></script>
  <script src="../js/hoverable-collapse.js"></script>
  <script src="../js/template.js"></script>
  <script src="../js/settings.js"></script>
  <script src="../js/todolist.js"></script>
  
  <script>
    // Auto-refresh page every minute to update button status
    setTimeout(function() {
      location.reload();
    }, 60000);
  </script>
</body>
</html>