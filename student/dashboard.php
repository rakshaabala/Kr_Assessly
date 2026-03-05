<?php
session_start();

// Set timezone to Indian Standard Time
date_default_timezone_set('Asia/Kolkata');

// Check if student is logged in
if (!isset($_SESSION['student_id']) || $_SESSION['user_type'] !== 'student') {
    header("Location: ../index.php");
    exit();
}

// Define database constants using environment variables for security
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'db_kr_assessly');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

// Function to get database connection
function getDBConnection() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        die("Database connection error. Please try again later.");
    }
}

// Function to sanitize input
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

$pdo = getDBConnection();

$studentId = $_SESSION['student_id'];
$studentName = $_SESSION['student_name'] ?? 'Student';
$registerNumber = $_SESSION['register_number'] ?? '';

// Fetch student statistics
$statsQuery = "
    SELECT 
        COUNT(DISTINCT hts.id) as total_tests,
        SUM(CASE WHEN hts.submit_time IS NOT NULL THEN 1 ELSE 0 END) as completed_tests,
        SUM(CASE 
            WHEN hts.submit_time IS NULL 
             AND NOW() < CONCAT(ht.test_date, ' ', ht.start_time) THEN 1 
            ELSE 0 
        END) as upcoming_tests,
        SUM(CASE 
            WHEN hts.submit_time IS NULL 
             AND NOW() BETWEEN CONCAT(ht.test_date, ' ', ht.start_time) AND CONCAT(ht.test_date, ' ', ht.end_time) THEN 1 
            ELSE 0 
        END) as ongoing_tests,
        AVG(
            CASE 
                WHEN hts.submit_time IS NOT NULL AND tm.max_marks > 0 THEN (hts.total_score / tm.max_marks) * 100 
                ELSE NULL 
            END
        ) as avg_score
    FROM hosted_test_students hts
    INNER JOIN hosted_tests ht ON hts.hosted_test_id = ht.id
    LEFT JOIN (
        SELECT test_id, SUM(questions_to_display * marks_per_question) AS max_marks
        FROM test_sections
        GROUP BY test_id
    ) tm ON tm.test_id = ht.test_id
    WHERE hts.student_id = :student_id
";

$statsStmt = $pdo->prepare($statsQuery);
$statsStmt->execute(['student_id' => $studentId]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

$totalTests = $stats['total_tests'] ?? 0;
$completedTests = $stats['completed_tests'] ?? 0;
$upcomingTests = $stats['upcoming_tests'] ?? 0;
$ongoingTests = $stats['ongoing_tests'] ?? 0;
$avgScore = $stats['avg_score'] ? round($stats['avg_score'], 2) : 0;

// Fetch recent tests
$recentTestsQuery = "
    SELECT 
        ht.id as hosted_test_id,
        t.test_name,
        t.test_code,
        ht.test_date,
        ht.start_time,
        ht.end_time,
        ht.show_answers,
        hts.submit_time,
        hts.total_score,
        hts.test_status,
        COALESCE(tm.max_marks, 0) AS max_marks
    FROM hosted_tests ht
    INNER JOIN hosted_test_students hts ON ht.id = hts.hosted_test_id
    INNER JOIN tests t ON ht.test_id = t.id
    LEFT JOIN (
        SELECT test_id, SUM(questions_to_display * marks_per_question) AS max_marks
        FROM test_sections
        GROUP BY test_id
    ) tm ON tm.test_id = t.id
    WHERE hts.student_id = :student_id
    ORDER BY ht.test_date DESC, ht.start_time DESC
    LIMIT 5
";

$recentTestsStmt = $pdo->prepare($recentTestsQuery);
$recentTestsStmt->execute(['student_id' => $studentId]);
$recentTests = $recentTestsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get current date
$currentDate = date('d M Y');
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Dashboard - KR ASSESSLY</title>
  <link rel="stylesheet" href="../vendors/feather/feather.css">
  <link rel="stylesheet" href="../vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" href="../vendors/css/vendor.bundle.base.css">
  <link rel="stylesheet" href="../css/vertical-layout-light/style.css">
  <link rel="shortcut icon" href="../images/favicon.jpg" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  
  <style>
    .stat-card {
      border-radius: 12px;
      padding: 24px;
      height: 100%;
      transition: all 0.3s ease;
      border: none;
      box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    }
    
    .stat-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 6px 20px rgba(0,0,0,0.15);
    }
    
    .stat-card-primary {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
    }
    
    .stat-card-success {
      background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
      color: white;
    }
    
    .stat-card-warning {
      background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
      color: white;
    }
    
    .stat-card-info {
      background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
      color: white;
    }
    
    .stat-icon {
      font-size: 2.5rem;
      opacity: 0.9;
      margin-bottom: 12px;
    }
    
    .stat-value {
      font-size: 2.2rem;
      font-weight: 700;
      margin: 8px 0;
    }
    
    .stat-label {
      font-size: 0.9rem;
      opacity: 0.95;
      font-weight: 500;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .recent-tests-card {
      border-radius: 12px;
      box-shadow: 0 2px 12px rgba(0,0,0,0.08);
      border: none;
    }
    
    .test-item {
      padding: 16px;
      border-bottom: 1px solid #e8ecf1;
      transition: background 0.2s ease;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .test-item:last-child {
      border-bottom: none;
    }
    
    .test-item:hover {
      background: #f8f9fc;
    }
    
    .test-info-main {
      flex: 1;
    }
    
    .test-title {
      font-size: 0.95rem;
      font-weight: 600;
      color: #2c3e50;
      margin-bottom: 4px;
    }
    
    .test-code {
      font-size: 0.75rem;
      color: #6c757d;
      font-family: 'Courier New', monospace;
    }
    
    .test-date {
      font-size: 0.8rem;
      color: #6c757d;
      margin-top: 4px;
    }
    
    .test-status-badge {
      font-size: 0.75rem;
      padding: 4px 12px;
      border-radius: 12px;
      font-weight: 600;
      white-space: nowrap;
    }
    
    .status-completed {
      background: #d4edda;
      color: #155724;
    }
    
    .status-upcoming {
      background: #fff3cd;
      color: #856404;
    }
    
    .status-not-attempted {
      background: #f8d7da;
      color: #721c24;
    }
    
    .welcome-card {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border-radius: 12px;
      padding: 30px;
      margin-bottom: 24px;
      box-shadow: 0 4px 16px rgba(102, 126, 234, 0.3);
    }
    
    .welcome-title {
      font-size: 1.8rem;
      font-weight: 700;
      margin-bottom: 8px;
    }
    
    .welcome-subtitle {
      font-size: 1rem;
      opacity: 0.95;
    }
    
    .section-title {
      font-size: 1.3rem;
      font-weight: 700;
      color: #2c3e50;
      margin-bottom: 20px;
      padding-bottom: 10px;
      border-bottom: 2px solid #667eea;
    }
    
    .no-tests-msg {
      text-align: center;
      padding: 40px;
      color: #6c757d;
    }
    
    .no-tests-msg i {
      font-size: 3rem;
      color: #ddd;
      margin-bottom: 16px;
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
          <!-- Welcome Card -->
          <div class="welcome-card">
            <div class="welcome-title">Welcome back, <?php echo htmlspecialchars($studentName); ?>!</div>
            <div class="welcome-subtitle">
              <i class="fas fa-calendar-alt"></i> <?php echo $currentDate; ?> | <?php echo (int)$ongoingTests; ?> ongoing test(s) right now
            </div>
          </div>

          <!-- Statistics Cards -->
          <div class="row mb-4">
            <div class="col-md-3 mb-3">
              <div class="stat-card stat-card-primary">
                <div class="stat-icon">
                  <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="stat-value"><?php echo $totalTests; ?></div>
                <div class="stat-label">Total Tests</div>
              </div>
            </div>
            
            <div class="col-md-3 mb-3">
              <div class="stat-card stat-card-success">
                <div class="stat-icon">
                  <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-value"><?php echo $completedTests; ?></div>
                <div class="stat-label">Completed</div>
              </div>
            </div>
            
            <div class="col-md-3 mb-3">
              <div class="stat-card stat-card-warning">
                <div class="stat-icon">
                  <i class="fas fa-clock"></i>
                </div>
                <div class="stat-value"><?php echo $upcomingTests; ?></div>
                <div class="stat-label">Upcoming</div>
              </div>
            </div>
            
            <div class="col-md-3 mb-3">
              <div class="stat-card stat-card-info">
                <div class="stat-icon">
                  <i class="fas fa-trophy"></i>
                </div>
                <div class="stat-value"><?php echo $avgScore; ?>%</div>
                <div class="stat-label">Avg Score</div>
              </div>
            </div>
          </div>

          <!-- Recent Tests -->
          <div class="row">
            <div class="col-md-12">
              <div class="section-title">
                <i class="fas fa-history"></i> Recent Tests
              </div>
              
              <div class="card recent-tests-card">
                <div class="card-body p-0">
                  <?php if (empty($recentTests)): ?>
                    <div class="no-tests-msg">
                      <i class="fas fa-inbox"></i>
                      <p>No tests available yet. Check back soon!</p>
                    </div>
	                  <?php else: ?>
	                    <?php foreach ($recentTests as $test): ?>
	                      <?php
	                        $testDateTime = new DateTime($test['test_date'] . ' ' . $test['end_time']);
	                        $currentDateTime = new DateTime();
	                        
	                        if ($test['submit_time'] && $test['total_score'] !== null) {
	                          $statusClass = 'status-completed';
	                          $statusText = 'Completed';
	                          $statusIcon = 'fa-check-circle';
	                        } else {
	                          $testStartDateTime = new DateTime($test['test_date'] . ' ' . $test['start_time']);

	                          if ($currentDateTime < $testStartDateTime) {
	                            $statusClass = 'status-upcoming';
	                            $statusText = 'Upcoming';
	                            $statusIcon = 'fa-clock';
	                          } elseif ($currentDateTime <= $testDateTime) {
	                            $statusClass = 'status-upcoming';
	                            $statusText = 'Ongoing';
	                            $statusIcon = 'fa-bolt';
	                          } else {
	                            $statusClass = 'status-not-attempted';
	                            $statusText = 'Missed';
	                            $statusIcon = 'fa-times-circle';
	                          }
	                        }

	                        $percentage = 0;
	                        if (!empty($test['max_marks']) && (float)$test['max_marks'] > 0) {
	                          $percentage = round(((float)$test['total_score'] / (float)$test['max_marks']) * 100, 2);
	                        }
	                      ?>
	                      <div class="test-item">
	                        <div class="test-info-main">
                          <div class="test-title">
                            <i class="fas fa-file-alt" style="color: #667eea; margin-right: 6px;"></i>
                            <?php echo htmlspecialchars($test['test_name']); ?>
                          </div>
                          <div class="test-code"><?php echo htmlspecialchars($test['test_code']); ?></div>
                          <div class="test-date">
                            <i class="far fa-calendar"></i> 
	                            <?php echo date('d M Y', strtotime($test['test_date'])); ?> at 
	                            <?php echo date('h:i A', strtotime($test['start_time'])); ?>
	                            <?php if ($test['submit_time'] && $test['total_score'] !== null): ?>
	                              <span style="margin-left: 12px; color: #667eea; font-weight: 600;">
	                                <i class="fas fa-star"></i> Marks: <?php echo number_format((float)$test['total_score'], 2); ?> / <?php echo number_format((float)$test['max_marks'], 2); ?> (<?php echo number_format($percentage, 2); ?>%)
	                              </span>
	                            <?php endif; ?>
	                          </div>
	                        </div>
                        <div>
                          <span class="test-status-badge <?php echo $statusClass; ?>">
                            <i class="fas <?php echo $statusIcon; ?>"></i> <?php echo $statusText; ?>
                          </span>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
                
                <?php if (!empty($recentTests)): ?>
                  <div class="card-footer text-center" style="background: #f8f9fc; border-top: 1px solid #e8ecf1;">
                    <a href="test_list.php" class="btn btn-sm btn-outline-primary" style="border-radius: 20px; padding: 8px 24px; font-weight: 600;">
                      <i class="fas fa-list"></i> View All Tests
                    </a>
                  </div>
                <?php endif; ?>
              </div>
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
  
  <style>
    .card:hover {
      transform: translateY(-3px);
      box-shadow: 0 6px 20px rgba(0,0,0,0.15);
    }
  </style>
</body>
</html>
