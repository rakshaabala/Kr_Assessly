<?php
session_start();

if (
    !isset($_SESSION['user_type']) ||
    $_SESSION['user_type'] !== 'faculty'
) {
    header("Location: ../index.php");
    exit;
}

$facultyId = $_SESSION['user_id'];
$facultyName = $_SESSION['user_name'];

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

// Get test ID from URL if specified
$hostedTestId = isset($_GET['test_id']) ? (int)$_GET['test_id'] : null;

// Fetch tests created by this faculty member
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
            COUNT(hts.id) as total_students,
            SUM(CASE WHEN (hts.test_status = 'completed' OR hts.submit_time IS NOT NULL) THEN 1 ELSE 0 END) as completed_students,
            AVG(hts.total_score) as avg_score,
            MAX(hts.total_score) as highest_score
        FROM hosted_tests ht
        INNER JOIN tests t ON ht.test_id = t.id
        LEFT JOIN hosted_test_students hts ON ht.id = hts.hosted_test_id
        WHERE t.created_by_type = 'faculty' 
        AND t.created_by_id = :faculty_id
        AND ht.id = :hosted_test_id
        GROUP BY ht.id, t.test_name, t.test_code, ht.test_date, ht.start_time, ht.end_time, ht.test_duration, ht.show_answers
    ");
    $stmt->execute([
        'faculty_id' => $facultyId,
        'hosted_test_id' => $hostedTestId
    ]);
    $testReport = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$testReport) {
        header("Location: test_report.php");
        exit();
    }
    
    // Fetch detailed student results for this test
    $studentsStmt = $pdo->prepare("
        SELECT 
            s.name_of_student as name,
            s.register_number,
            s.department,
            s.batch,
            s.section,
            hts.total_score,
            hts.submit_time,
            hts.test_status,
            GROUP_CONCAT(
                CONCAT(sc.section_id, ':', COALESCE(sc.section_score, 0)) 
                SEPARATOR '|'
            ) as section_scores_str
        FROM hosted_test_students hts
        INNER JOIN student_account s ON hts.student_id = s.id
        LEFT JOIN section_scores sc ON hts.id = sc.hosted_test_student_id
        WHERE hts.hosted_test_id = :hosted_test_id
        GROUP BY s.id, hts.total_score, hts.submit_time, hts.test_status
        ORDER BY hts.total_score DESC
    ");
    $studentsStmt->execute(['hosted_test_id' => $hostedTestId]);
    $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Parse section scores
    foreach ($students as &$student) {
        $scores = [];
        if ($student['section_scores_str']) {
            $pairs = explode('|', $student['section_scores_str']);
            foreach ($pairs as $pair) {
                list($sectionId, $score) = explode(':', $pair);
                $scores[$sectionId] = floatval($score);
            }
        }
        $student['section_scores'] = $scores;
        unset($student['section_scores_str']);
    }
    
    // Fetch sections for this test
    $sectionsStmt = $pdo->prepare("
        SELECT 
            ts.id as section_id,
            ts.section_name,
            ts.section_order
        FROM test_sections ts
        INNER JOIN tests t ON ts.test_id = t.id
        INNER JOIN hosted_tests ht ON t.id = ht.test_id
        WHERE ht.id = :hosted_test_id
        ORDER BY ts.section_order
    ");
    $sectionsStmt->execute(['hosted_test_id' => $hostedTestId]);
    $sections = $sectionsStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // All tests for the faculty
    $stmt = $pdo->prepare("
        SELECT 
            ht.id as hosted_test_id,
            ht.test_date,
            ht.start_time,
            ht.end_time,
            ht.show_answers,
            t.test_name,
            t.test_code,
            COUNT(hts.id) as total_students,
            SUM(CASE WHEN (hts.test_status = 'completed' OR hts.submit_time IS NOT NULL) THEN 1 ELSE 0 END) as completed_students,
            AVG(hts.total_score) as avg_score,
            MAX(hts.total_score) as highest_score
        FROM hosted_tests ht
        INNER JOIN tests t ON ht.test_id = t.id
        LEFT JOIN hosted_test_students hts ON ht.id = hts.hosted_test_id
        WHERE t.created_by_type = 'faculty' 
        AND t.created_by_id = :faculty_id
        GROUP BY ht.id, t.test_name, t.test_code, ht.test_date, ht.start_time, ht.end_time
        ORDER BY ht.test_date DESC, ht.start_time DESC
    ");
    $stmt->execute(['faculty_id' => $facultyId]);
    $testReports = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Test Reports - KR ASSESSLY</title>
  <link rel="stylesheet" href="../vendors/feather/feather.css">
  <link rel="stylesheet" href="../vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" href="../vendors/css/vendor.bundle.base.css">
  <link rel="stylesheet" href="../css/vertical-layout-light/style.css">
  <link rel="shortcut icon" href="../images/favicon.jpg" />
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.5/dist/sweetalert2.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    .report-card { background: white; border-radius: 8px; padding: 25px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    .report-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #e0e0e0; }
    .test-title { font-size: 20px; font-weight: bold; color: #333; }
    .test-meta { color: #666; font-size: 14px; margin-top: 5px; }
    .stats-row { display: flex; gap: 20px; margin: 15px 0; flex-wrap: wrap; }
    .stat-box { flex: 1; min-width: 150px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px; border-radius: 8px; text-align: center; }
    .stat-value { font-size: 28px; font-weight: bold; margin-bottom: 5px; }
    .stat-label { font-size: 14px; opacity: 0.9; }
    .toggle-container { display: flex; align-items: center; gap: 10px; }
    .toggle-switch { position: relative; display: inline-block; width: 50px; height: 24px; }
    .toggle-switch input { opacity: 0; width: 0; height: 0; }
    .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 24px; }
    .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
    input:checked + .slider { background-color: #28a745; }
    input:checked + .slider:before { transform: translateX(26px); }
    .btn-download { background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; }
    .btn-download:hover { background: #218838; }
    .btn-view-details { background: #17a2b8; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; }
    .btn-view-details:hover { background: #138496; }

    .swal2-popup { 
      border-radius: 10px; 
      width: 32em !important; 
      max-width: 90% !important; 
      padding: 2em !important; 
      font-size: 1rem !important; 
    }
    .swal2-popup { border-radius: 10px; }
    .swal2-styled.swal2-confirm { background: linear-gradient(90deg, #594ba1ff 0%, #2575fc 100%) !important; }
    .swal2-styled.swal2-cancel { background: #6c757d !important; }
    
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
  </style>
</head>
<body>
  <div class="container-scroller">
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
            <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown">
              <img src="../images/profile-logout-wo-bg.png" alt="profile"/>
            </a>
            <div class="dropdown-menu dropdown-menu-right navbar-dropdown">
              <a class="dropdown-item" href="logout.php"><i class="ti-power-off text-primary"></i>Logout</a>
            </div>
          </li>
        </ul>
      </div>
    </nav>

    <div class="container-fluid page-body-wrapper">
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

      <div class="main-panel">
        <div class="content-wrapper">
          <div class="row">
            <div class="col-12 grid-margin">
              <div class="row">
                <div class="col-12">
                  <h3 class="font-weight-bold">Test Reports</h3>
                  <h6 class="font-weight-normal mb-0">View and analyze test results for your tests</h6>
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
              
              <div class="report-card">
                <div class="report-header">
                  <div>
                    <div class="test-title"><?php echo htmlspecialchars($testReport['test_name']); ?></div>
                    <div class="test-meta">
                      Date: <?php echo date('d M Y', strtotime($testReport['test_date'])); ?> | Time: <?php echo date('h:i A', strtotime($testReport['start_time'])); ?> - <?php echo date('h:i A', strtotime($testReport['end_time'])); ?> | 
                      Duration: <?php echo $testReport['test_duration']; ?> minutes
                    </div>
                  </div>
                  <div class="toggle-container">
                    <span>Show Answers to Students:</span>
                    <label class="toggle-switch">
                      <input type="checkbox" <?php echo $testReport['show_answers'] === 'yes' ? 'checked' : ''; ?> 
                             onchange="toggleShowAnswers(<?php echo $hostedTestId; ?>, this.checked)">
                      <span class="slider"></span>
                    </label>
                  </div>
                </div>

                <div class="stats-row">
                  <?php $completionRate = ((int)$testReport['total_students'] > 0) ? round(((int)$testReport['completed_students'] * 100) / (int)$testReport['total_students'], 2) : 0; ?>
                  <div class="stat-box">
                    <div class="stat-value"><?php echo $testReport['total_students']; ?></div>
                    <div class="stat-label">Total Students</div>
                  </div>
                  <div class="stat-box">
                    <div class="stat-value"><?php echo $testReport['completed_students']; ?></div>
                    <div class="stat-label">Completed</div>
                  </div>
                  <div class="stat-box">
                    <div class="stat-value"><?php echo $testReport['avg_score'] ? number_format($testReport['avg_score'], 2) : '0.00'; ?></div>
                    <div class="stat-label">Average Score</div>
                  </div>
                  <div class="stat-box">
                    <div class="stat-value"><?php echo $testReport['highest_score'] ? number_format($testReport['highest_score'], 2) : '0.00'; ?></div>
                    <div class="stat-label">Highest Score</div>
                  </div>
                  <div class="stat-box">
                    <div class="stat-value"><?php echo number_format($completionRate, 2); ?>%</div>
                    <div class="stat-label">Completion Rate</div>
                  </div>
                </div>

                <div class="mt-3">
                  <button class="btn-download" onclick="downloadReport(<?php echo $hostedTestId; ?>)">
                    <i class="ti-download mr-2"></i>Download Excel Report
                  </button>
                </div>

                <div class="table-responsive mt-4">
                  <table class="table table-bordered" id="detailTable<?php echo $hostedTestId; ?>">
                    <thead>
                      <tr>
                        <th>Name</th>
                        <th>Register No</th>
                        <th>Department</th>
                        <th>Batch</th>
                        <th>Section</th>
                        <?php foreach ($sections as $section): ?>
                          <th><?php echo htmlspecialchars($section['section_name']); ?></th>
                        <?php endforeach; ?>
                        <th>Total Score</th>
                        <th>Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($students as $student): ?>
                      <tr>
                        <td><?php echo htmlspecialchars($student['name']); ?></td>
                        <td><?php echo htmlspecialchars($student['register_number']); ?></td>
                        <td><?php echo htmlspecialchars($student['department']); ?></td>
                        <td><?php echo htmlspecialchars($student['batch']); ?></td>
                        <td><?php echo htmlspecialchars($student['section']); ?></td>
                        <?php foreach ($sections as $section): ?>
                          <td><?php echo isset($student['section_scores'][$section['section_id']]) ? $student['section_scores'][$section['section_id']] : '0'; ?></td>
                        <?php endforeach; ?>
                        <td><strong><?php echo $student['total_score']; ?></strong></td>
                        <td><?php echo $student['test_status'] === 'completed' ? '<span class="badge badge-success">Completed</span>' : '<span class="badge badge-warning">Pending</span>'; ?></td>
                      </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
          <?php else: ?>
          <!-- All Test Reports -->
          <div class="row">
            <div class="col-12">
              <div class="report-card">
                <h4 class="test-title">Your Tests</h4>
                
                <?php if (empty($testReports)): ?>
                  <div class="text-center py-5">
                    <i class="fas fa-chart-bar" style="font-size: 3rem; color: #ddd; margin-bottom: 20px;"></i>
                    <h4>No Test Reports Available</h4>
                    <p class="text-muted">You haven't created any tests yet. Once you create and host a test, the reports will appear here.</p>
                  </div>
                <?php else: ?>
                  <div class="table-responsive">
                    <table class="table table-striped" id="reportsTable">
                      <thead>
                        <tr>
                          <th>Test Name</th>
                          <th>Date</th>
                          <th>Time</th>
	                          <th>Total Students</th>
	                          <th>Completed</th>
	                          <th>Completion %</th>
	                          <th>Answers</th>
	                          <th>Avg Score</th>
	                          <th>Highest Score</th>
	                          <th>Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($testReports as $report): ?>
                        <tr>
                          <td><?php echo htmlspecialchars($report['test_name']); ?></td>
                          <td><?php echo date('d M Y', strtotime($report['test_date'])); ?></td>
                          <td><?php echo date('h:i A', strtotime($report['start_time'])); ?> - <?php echo date('h:i A', strtotime($report['end_time'])); ?></td>
	                          <td><?php echo $report['total_students']; ?></td>
	                          <td><?php echo $report['completed_students']; ?></td>
                              <td>
                                <?php
                                  $rowCompletion = ((int)$report['total_students'] > 0)
                                    ? round(((int)$report['completed_students'] * 100) / (int)$report['total_students'], 2)
                                    : 0;
                                  echo number_format($rowCompletion, 2) . '%';
                                ?>
                              </td>
                              <td>
                                <span class="badge <?php echo ($report['show_answers'] === 'yes') ? 'badge-success' : 'badge-secondary'; ?>">
                                  <?php echo ($report['show_answers'] === 'yes') ? 'On' : 'Off'; ?>
                                </span>
                              </td>
	                          <td><?php echo $report['avg_score'] ? number_format($report['avg_score'], 2) : '0.00'; ?></td>
	                          <td><?php echo $report['highest_score'] ? number_format($report['highest_score'], 2) : '0.00'; ?></td>
                          <td>
                            <button class="btn-view-details" onclick="window.location.href='test_report.php?test_id=<?php echo $report['hosted_test_id']; ?>'">
                              <i class="ti-eye"></i> View Details
                            </button>
                          </td>
                        </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php endif; ?>
        </div>

        <footer class="footer" style="background: linear-gradient(90deg, #594ba1ff 0%, #2575fc 100%);color: white;padding: 30px 0;">
          <div class="text-center">
            <span style="font-family: 'Segoe UI', 'Roboto', 'Helvetica Neue', sans-serif;font-size: 1rem;color: white;letter-spacing: 0.3px;">
              © 2025, Designed and Developed by <strong style="font-weight: 700;text-transform: uppercase;letter-spacing: 0.8px;color: white; font-style: italic; font-family:'Franklin Gothic Medium', 'Arial Narrow', Arial, sans-serif;">KR ASSESSLY TEAM</strong> - All rights reserved.
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
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.5/dist/sweetalert2.all.min.js"></script>

  <script>
    let reportsTable;
    const API_URL = 'test_api.php';

    $(document).ready(function() {
      if ($('#reportsTable').length) {
        reportsTable = $('#reportsTable').DataTable({
          pageLength: 10,
          order: [[1, 'desc']],
          language: { emptyTable: "No test reports available" }
        });
      }
      
      // Initialize DataTable for detail view if exists
      <?php if ($hostedTestId && $testReport): ?>
      $('#detailTable<?php echo $hostedTestId; ?>').DataTable({
        pageLength: 25,
        order: [[<?php echo 5 + count($sections); ?>, 'desc']] // Order by total score column
      });
      <?php endif; ?>
    });

    function toggleShowAnswers(hostedTestId, showAnswers) {
      const formData = new FormData();
      formData.append('action', 'toggle_show_answers');
      formData.append('hosted_test_id', hostedTestId);
      formData.append('show_answers', showAnswers ? 'yes' : 'no');
      
      fetch(API_URL, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(result => {
          if (result.success) {
            Swal.fire({
              icon: 'success',
              title: 'Updated!',
              text: showAnswers ? 'Students can now view answers' : 'Answer visibility disabled',
              timer: 2000,
              timerProgressBar: true,
              toast: true,
              position: 'top-end',
              showConfirmButton: false
            });
          } else {
            Swal.fire('Error', result.message, 'error');
          }
        });
    }

    function downloadReport(hostedTestId) {
      // In a real implementation, this would fetch the report data and generate an Excel file
      // For now, we'll just show a message
      // Generate the report data dynamically
      generateExcelReport(<?php echo json_encode([
        'test_name' => $testReport['test_name'],
        'test_date' => $testReport['test_date'],
        'students' => array_map(function($s) {
          return [
            'name' => $s['name'],
            'register_number' => $s['register_number'],
            'department' => $s['department'],
            'batch' => $s['batch'],
            'section' => $s['section'],
            'total_score' => $s['total_score'],
            'test_status' => $s['test_status']
          ];
        }, $students),
        'sections' => array_map(function($s) {
          return ['section_name' => $s['section_name']];
        }, $sections)
      ]); ?>);
    }

    function generateExcelReport(data) {
      const wb = XLSX.utils.book_new();
      
      const headers = [
        'Name', 'Register Number', 'Department', 'Batch', 'Section',
        ...data.sections.map(s => s.section_name),
        'Total Score', 'Status'
      ];
      
      const rows = data.students.map(student => [
        student.name,
        student.register_number,
        student.department,
        student.batch,
        student.section,
        ...data.sections.map(() => '0'), // Placeholder for section scores
        student.total_score,
        student.test_status
      ]);
      
      const wsData = [headers, ...rows];
      const ws = XLSX.utils.aoa_to_sheet(wsData);
      
      ws['!cols'] = headers.map(() => ({wch: 15}));
      
      XLSX.utils.book_append_sheet(wb, ws, 'Test Report');
      
      const filename = `${data.test_name.replace(/[^a-z0-9]/gi, '_')}_Report_${data.test_date}.xlsx`;
      XLSX.writeFile(wb, filename);
      
      Swal.fire({
        icon: 'success',
        title: 'Downloaded!',
        text: 'Excel report has been downloaded',
        timer: 2000,
        timerProgressBar: true
      });
    }
  </script>
</body>
</html>
