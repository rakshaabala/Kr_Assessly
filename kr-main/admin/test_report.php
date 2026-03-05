<?php session_start();

// Check if user is logged in as faculty (admin in this case)
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'faculty') {
    header("Location: ../index.php");
    exit();
} ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>KR ASSESSLY - Smart Assessment Portal</title>
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
      width: 32em !important; /* Default is 32em, adjust as needed */
      max-width: 90% !important; /* Responsive max width */
      padding: 2em !important; /* Adjust padding */
      font-size: 1rem !important; /* Adjust font size */
    }
    .swal2-popup { border-radius: 10px; }
    .swal2-styled.swal2-confirm { background: linear-gradient(90deg, #594ba1ff 0%, #2575fc 100%) !important; }
    .swal2-styled.swal2-cancel { background: #6c757d !important; }
  </style>
</head>
<body>
  <div class="container-scroller">
    <nav class="navbar col-lg-12 col-12 p-0 fixed-top d-flex flex-row">
      <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-center">
        <a class="navbar-brand brand-logo" href="#"><img src="../images/full-logo-wo-bg.png" width="100px" class="mr-2" alt="logo"/></a>
        <a class="navbar-brand brand-logo-mini" href="#"><img src="../images/small-logo-wo-bg.png" alt="logo"/></a>
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
              <a class="dropdown-item"  href="logout.php"><i class="ti-power-off text-primary"></i>Logout</a>
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
  <a class="nav-link" href="account_data.php">
    <i class="fa-solid fa-users menu-icon"></i>
    <span class="menu-title">Account Data</span>
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
      initDataTables();
      loadReports();
    });

    function initDataTables() {
      reportsTable = $('#reportsTable').DataTable({
        pageLength: 10,
        order: [[1, 'desc']],
        language: { emptyTable: "No test reports available" }
      });
    }

    function loadReports() {
      fetch(API_URL + '?action=get_reports')
        .then(response => response.json())
        .then(data => {
          reportsTable.clear();
          if (data.success && data.data.length > 0) {
            data.data.forEach(report => {
              let statusBadge;
              if (report.status === 'completed') {
                statusBadge = '<label class="badge badge-success">Completed</label>';
              } else if (report.status === 'ongoing') {
                statusBadge = '<label class="badge badge-warning">Ongoing</label>';
              } else if (report.status === 'scheduled') {
                statusBadge = '<label class="badge badge-info">Scheduled</label>';
              } else {
                statusBadge = '<label class="badge badge-secondary">Cancelled</label>';
              }
              
              const actions = `
                <button class="btn-view-details" onclick="viewReport(${report.id})">
                  <i class="ti-eye"></i> View Details
                </button>
              `;
              
              reportsTable.row.add([
                report.test_name,
                report.test_date,
                `${report.start_time} - ${report.end_time}`,
                report.total_students,
                report.completed_students,
                statusBadge,
                actions
              ]);
            });
          }
          reportsTable.draw();
        });
    }

    function viewReport(hostedTestId) {
      fetch(API_URL + `?action=get_report_details&hosted_test_id=${hostedTestId}`)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            displayReportDetails(data.data, hostedTestId);
          } else {
            Swal.fire('Error', 'Failed to load report details', 'error');
          }
        });
    }

    function displayReportDetails(data, hostedTestId) {
      const showAnswers = data.show_answers === 'yes';
      
      let html = `
        <div class="report-card">
          <div class="report-header">
            <div>
              <div class="test-title">${data.test_name}</div>
              <div class="test-meta">
                Date: ${data.test_date} | Time: ${data.start_time} - ${data.end_time} | 
                Duration: ${data.test_duration} minutes
              </div>
            </div>
            <div class="toggle-container">
              <span>Show Answers to Students:</span>
              <label class="toggle-switch">
                <input type="checkbox" ${showAnswers ? 'checked' : ''} 
                       onchange="toggleShowAnswers(${hostedTestId}, this.checked)">
                <span class="slider"></span>
              </label>
            </div>
          </div>

          <div class="stats-row">
            <div class="stat-box">
              <div class="stat-value">${data.total_students}</div>
              <div class="stat-label">Total Students</div>
            </div>
            <div class="stat-box">
              <div class="stat-value">${data.completed_students}</div>
              <div class="stat-label">Completed</div>
            </div>
            <div class="stat-box">
              <div class="stat-value">${data.avg_score ? data.avg_score.toFixed(2) : '0.00'}</div>
              <div class="stat-label">Average Score</div>
            </div>
            <div class="stat-box">
              <div class="stat-value">${data.highest_score ? data.highest_score.toFixed(2) : '0.00'}</div>
              <div class="stat-label">Highest Score</div>
            </div>
          </div>

          <div class="mt-3">
            <button class="btn-download" onclick="downloadReport(${hostedTestId})">
              <i class="ti-download mr-2"></i>Download Excel Report
            </button>
          </div>

          <div class="table-responsive mt-4">
            <table class="table table-bordered" id="detailTable${hostedTestId}">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Register No</th>
                  <th>Department</th>
                  <th>Batch</th>
                  <th>Section</th>
                  ${data.sections.map(s => `<th>${s.section_name}</th>`).join('')}
                  <th>Total Score</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                ${data.students.map(student => `
                  <tr>
                    <td>${student.name}</td>
                    <td>${student.register_number}</td>
                    <td>${student.department}</td>
                    <td>${student.batch}</td>
                    <td>${student.section}</td>
                    ${data.sections.map(s => {
                      const score = student.section_scores[s.section_id] || 0;
                      return `<td>${score}</td>`;
                    }).join('')}
                    <td><strong>${student.total_score}</strong></td>
                    <td>${student.test_status === 'completed' ? 
                      '<span class="badge badge-success">Completed</span>' : 
                      '<span class="badge badge-warning">Pending</span>'}</td>
                  </tr>
                `).join('')}
              </tbody>
            </table>
          </div>
        </div>
      `;
      
      document.getElementById('reportDetailsContainer').innerHTML = html;
      
      $(`#detailTable${hostedTestId}`).DataTable({
        pageLength: 25,
        dom: 'Bfrtip',
        order: [[data.sections.length + 5, 'desc']]
      });
    }

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
      fetch(API_URL + `?action=get_report_details&hosted_test_id=${hostedTestId}`)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            generateExcelReport(data.data);
          }
        });
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
        ...data.sections.map(s => student.section_scores[s.section_id] || 0),
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