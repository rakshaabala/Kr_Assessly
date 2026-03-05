<?php
session_start();

/* ================= AUTH ================= */
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'faculty') {
    header("Location: ../index.php");
    exit;
}

$facultyId   = $_SESSION['user_id'];
$facultyName = $_SESSION['user_name'];

/* ================= TIMEZONE ================= */
date_default_timezone_set('Asia/Kolkata');

/* ================= DB ================= */
$host = 'localhost';
$db   = 'db_kr_assessly';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}

/* ================= FACULTY STATS ================= */
/* ================= FACULTY DETAILS ================= */
$stmt = $pdo->prepare("
    SELECT name_of_faculty 
    FROM faculty_account 
    WHERE id = ?
");
$stmt->execute([$facultyId]);
$facultyRow = $stmt->fetch(PDO::FETCH_ASSOC);

$facultyName = $facultyRow ? $facultyRow['name_of_faculty'] : 'Faculty';

// My Tests
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM tests
    WHERE created_by_type='faculty'
      AND created_by_id=?
      AND status='active'
");
$stmt->execute([$facultyId]);
$totalTests = $stmt->fetchColumn();

// Active Tests
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM hosted_tests ht
    JOIN tests t ON ht.test_id = t.id
    WHERE t.created_by_type='faculty'
      AND t.created_by_id=?
      AND CONCAT(ht.test_date,' ',ht.start_time) <= NOW()
      AND CONCAT(ht.test_date,' ',ht.end_time) >= NOW()
");
$stmt->execute([$facultyId]);
$activeTests = $stmt->fetchColumn();

// Recent Hosted Tests
$stmt = $pdo->prepare("
    SELECT 
        ht.id,
        t.test_name,
        t.test_code,
        ht.test_date,
        ht.start_time,
        ht.end_time,
        COUNT(hts.id) AS total_students,
        SUM(CASE WHEN hts.submit_time IS NOT NULL THEN 1 ELSE 0 END) AS completed_students
    FROM hosted_tests ht
    JOIN tests t ON ht.test_id = t.id
    LEFT JOIN hosted_test_students hts ON ht.id = hts.hosted_test_id
    WHERE t.created_by_type='faculty'
      AND t.created_by_id=?
    GROUP BY ht.id
    ORDER BY ht.test_date DESC, ht.start_time DESC
    LIMIT 5
");
$stmt->execute([$facultyId]);
$recentHosted = $stmt->fetchAll(PDO::FETCH_ASSOC);

$currentDate = date('d M Y');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Admin Dashboard - KR ASSESSLY</title>
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
    
    .stat-card-orange {
      background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
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
    
    .data-card {
      border-radius: 12px;
      box-shadow: 0 2px 12px rgba(0,0,0,0.08);
      border: none;
      transition: all 0.3s ease;
    }
    
    .data-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 6px 20px rgba(0,0,0,0.15);
    }
    
    .item-row {
      padding: 16px;
      border-bottom: 1px solid #e8ecf1;
      transition: background 0.2s ease;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .item-row:last-child {
      border-bottom: none;
    }
    
    .item-row:hover {
      background: #f8f9fc;
    }
    
    .item-info-main {
      flex: 1;
    }
    
    .item-title {
      font-size: 0.95rem;
      font-weight: 600;
      color: #2c3e50;
      margin-bottom: 4px;
    }
    
    .item-subtitle {
      font-size: 0.75rem;
      color: #6c757d;
      font-family: 'Courier New', monospace;
    }
    
    .item-detail {
      font-size: 0.8rem;
      color: #6c757d;
      margin-top: 4px;
    }
    
    .role-badge {
      font-size: 0.7rem;
      padding: 3px 10px;
      border-radius: 12px;
      font-weight: 600;
      background: #667eea;
      color: white;
      display: inline-block;
      margin-top: 4px;
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
    
    .no-data-msg {
      text-align: center;
      padding: 40px;
      color: #6c757d;
    }
    
    .no-data-msg i {
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
            <a class="nav-link" href="create_test.php">
              <i class="fa-solid fa-file-pen menu-icon"></i>
              <span class="menu-title">Create Test</span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="host_test.php">
              <i class="fa-solid fa-play menu-icon"></i>
              <span class="menu-title">Host Test</span>
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
      
<!-- MAIN -->
<div class="main-panel">
<div class="content-wrapper">

<!-- WELCOME -->
<div class="welcome-card">
  <h2>Welcome back, <?php echo htmlspecialchars($facultyName); ?> !</h2>
  <p><i class="fas fa-calendar-alt"></i> <?php echo $currentDate; ?> • Faculty Dashboard</p>
</div>


</div>
    </div>
  </div>

  <script src="../vendors/js/vendor.bundle.base.js"></script>
  <script src="../js/off-canvas.js"></script>
  <script src="../js/hoverable-collapse.js"></script>
  <script src="../js/template.js"></script>
  <script src="../js/settings.js"></script>
  <script src="../js/todolist.js"></script>
</body>
</html>