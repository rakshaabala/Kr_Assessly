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
    .upload-container { background: white; border-radius: 8px; padding: 30px; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    .download-section { display: flex; gap: 20px; margin-bottom: 30px; flex-wrap: wrap; }
    .download-card { flex: 1; min-width: 250px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; cursor: pointer; transition: transform 0.2s; }
    .download-card:hover { transform: translateY(-5px); }
    .download-card h5 { color: white; margin-bottom: 10px; }
    .upload-form { background: #f8f9fa; padding: 25px; border-radius: 8px; margin-bottom: 20px; }
    .file-input-wrapper { position: relative; overflow: hidden; display: inline-block; width: 100%; }
    .file-input-wrapper input[type=file] { position: absolute; left: -9999px; }
    .file-input-label { display: block; padding: 10px 15px; background: white; border: 2px dashed #594ba1; border-radius: 5px; cursor: pointer; text-align: center; transition: all 0.3s; }
    .file-input-label:hover { background: #f0f0f0; border-color: #2575fc; }
    .file-name { margin-top: 10px; color: #28a745; font-weight: bold; }
    .submit-btn { background: linear-gradient(90deg, #594ba1ff 0%, #2575fc 100%); color: white; border: none; padding: 12px 30px; border-radius: 5px; cursor: pointer; font-size: 16px; transition: opacity 0.3s; }
    .submit-btn:hover { opacity: 0.9; }
    .submit-btn:disabled { opacity: 0.6; cursor: not-allowed; }
    .nav-tabs .nav-link { color: #594ba1; border: none; border-bottom: 3px solid transparent; }
    .nav-tabs .nav-link.active { color: #2575fc; background: transparent; border-bottom: 3px solid #2575fc; font-weight: bold; }
    .action-btn { padding: 5px 10px; margin: 0 2px; border: none; border-radius: 4px; cursor: pointer; transition: all 0.3s; }
    .btn-edit { background: #ffc107; color: white; }
    .btn-edit:hover { background: #e0a800; }
    .btn-delete { background: #dc3545; color: white; }
    .btn-delete:hover { background: #c82333; }
    .btn-toggle { background: #17a2b8; color: white; }
    .btn-toggle:hover { background: #138496; }
    .modal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
    .modal-content { background-color: #fefefe; margin: 5% auto; padding: 0; border: 1px solid #888; width: 80%; max-width: 600px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); }
    .modal-header { padding: 20px; background: linear-gradient(90deg, #594ba1ff 0%, #2575fc 100%); color: white; border-radius: 8px 8px 0 0; }
    .modal-body { padding: 20px; max-height: 60vh; overflow-y: auto; }
    .modal-footer { padding: 15px 20px; border-top: 1px solid #ddd; text-align: right; }
    .close { color: white; float: right; font-size: 28px; font-weight: bold; line-height: 20px; cursor: pointer; }
    .close:hover { opacity: 0.8; }
    .dataTables_wrapper .dataTables_length select, .dataTables_wrapper .dataTables_filter input { border: 1px solid #ddd; border-radius: 4px; padding: 5px 10px; }
    
    /* Custom SweetAlert2 styling */
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
            <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown" id="profileDropdown">
              <img src="../images/profile-logout-wo-bg.png" alt="profile"/>
            </a>
            <div class="dropdown-menu dropdown-menu-right navbar-dropdown" aria-labelledby="profileDropdown">
              <a class="dropdown-item"  href="logout.php"><i class="ti-power-off text-primary"></i>Logout</a>
            </div>
          </li>
        </ul>
        <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button" data-toggle="offcanvas">
          <span class="icon-menu"></span>
        </button>
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
          <div class="upload-container">
            <h4 class="mb-4">Account Data Upload System</h4>
            <div class="download-section">
              <div class="download-card" onclick="downloadStudentTemplate()">
                <h5><i class="ti-download mr-2"></i>Student Template</h5>
                <p class="mb-0">Download Excel format for student accounts</p>
              </div>
              <div class="download-card" onclick="downloadFacultyTemplate()">
                <h5><i class="ti-download mr-2"></i>Faculty Template</h5>
                <p class="mb-0">Download Excel format for faculty accounts</p>
              </div>
            </div>
            <div class="upload-form">
              <h5 class="mb-3">Upload Account Data</h5>
              <div class="row">
                <div class="form-group col-lg-6">
                  <label for="accountType">Account Type</label>
                  <select class="form-control" id="accountType">
                    <option value="">Select Account Type</option>
                    <option value="student">Student</option>
                    <option value="faculty">Faculty</option>
                  </select>
                </div>
                <div class="form-group col-lg-6">
                  <label>Choose Excel File</label>
                  <div class="file-input-wrapper">
                    <input type="file" id="excelFile" accept=".xlsx,.xls" onchange="handleFileSelect(event)">
                    <label for="excelFile" class="file-input-label"><i class="ti-upload mr-2"></i>Click to select Excel file</label>
                  </div>
                  <div id="fileName" class="file-name"></div>
                </div>
              </div>
              <button class="submit-btn" onclick="uploadData()" id="submitBtn" disabled><i class="ti-check mr-2"></i>Submit Data</button>
            </div>
          </div>

          <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <h4 class="card-title mb-4">Account Data Management</h4>
                  <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item"><a class="nav-link active" id="student-tab" data-toggle="tab" href="#studentTab" role="tab"><i class="ti-user mr-2"></i>Student Accounts</a></li>
                    <li class="nav-item"><a class="nav-link" id="faculty-tab" data-toggle="tab" href="#facultyTab" role="tab"><i class="ti-briefcase mr-2"></i>Faculty Accounts</a></li>
                  </ul>
                  <div class="tab-content mt-4">
                    <div class="tab-pane fade show active" id="studentTab" role="tabpanel">
                      <div class="table-responsive">
                        <table class="table table-hover" id="studentTable">
                          <thead>
                            <tr><th>Name</th><th>Register No</th><th>Programme</th><th>Department</th><th>Batch</th><th>Year</th><th>Section</th><th>Mobile</th><th>Email</th><th>Status</th><th>Actions</th></tr>
                          </thead>
                          <tbody></tbody>
                        </table>
                      </div>
                    </div>
                    <div class="tab-pane fade" id="facultyTab" role="tabpanel">
                      <div class="table-responsive">
                        <table class="table table-hover" id="facultyTable">
                          <thead>
                            <tr><th>Name</th><th>Faculty ID</th><th>Department</th><th>Mobile</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr>
                          </thead>
                          <tbody></tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <footer class="footer" style="background: linear-gradient(90deg, #594ba1ff 0%, #2575fc 100%);color: white;padding: 30px 0;">
          <div class="text-center">
            <span style="font-family: 'Segoe UI', 'Roboto', 'Helvetica Neue', sans-serif;font-size: 1rem;color: white;letter-spacing: 0.3px;">
              © 2025, Designed and Developed by <strong style="font-weight: 700;text-transform: uppercase;letter-spacing: 0.8px;color: white; font-style: italic; font-family:'Franklin Gothic Medium', 'Arial Narrow', Arial, sans-serif;">KR ASSESSLY TEAM</strong>- All rights reserved.
            </span>
          </div>
        </footer>
      </div>
    </div>   
  </div>

  <div id="editModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h5 id="modalTitle">Edit Account</h5>
        <span class="close" onclick="closeModal()">&times;</span>
      </div>
      <div class="modal-body" id="modalBody"></div>
      <div class="modal-footer">
        <button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
        <button class="btn btn-primary" onclick="saveEdit()">Save Changes</button>
      </div>
    </div>
  </div>

  <script src="../vendors/js/vendor.bundle.base.js"></script>
  <script src="../js/off-canvas.js"></script>
  <script src="../js/hoverable-collapse.js"></script>
  <script src="../js/template.js"></script>
  <script src="../js/settings.js"></script>
  <script src="../js/todolist.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.5/dist/sweetalert2.all.min.js"></script>

  <script>
    let selectedFile = null, studentTable, facultyTable, currentEditId = null, currentEditType = null;
    const API_URL = 'upload_data.php';

    $(document).ready(function() { initDataTables(); loadAllData(); });

    function initDataTables() {
      studentTable = $('#studentTable').DataTable({ pageLength: 10, lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]], order: [[0, 'asc']], language: { search: "Search:", lengthMenu: "Show _MENU_ entries", info: "Showing _START_ to _END_ of _TOTAL_ entries", emptyTable: "No student data available" } });
      facultyTable = $('#facultyTable').DataTable({ pageLength: 10, lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]], order: [[0, 'asc']], language: { search: "Search:", lengthMenu: "Show _MENU_ entries", info: "Showing _START_ to _END_ of _TOTAL_ entries", emptyTable: "No faculty data available" } });
    }

    function downloadStudentTemplate() {
      const wb = XLSX.utils.book_new();
      const wsData = [['Name of Student', 'Register Number', 'Programme', 'Department', 'Batch', 'Year', 'Section', 'Mobile Number', 'Mail ID', 'Password'], ['John Doe', 'STU2024001', 'B.Tech', 'Computer Science', '2024', '1', 'A', '9876543210', 'john@example.com', 'Pass@123']];
      const ws = XLSX.utils.aoa_to_sheet(wsData);
      ws['!cols'] = [{wch:20},{wch:15},{wch:15},{wch:20},{wch:10},{wch:8},{wch:10},{wch:15},{wch:25},{wch:15}];
      XLSX.utils.book_append_sheet(wb, ws, 'Student Data');
      XLSX.writeFile(wb, 'Student_Account_Template.xlsx');
      
      Swal.fire({
        icon: 'success',
        title: 'Download Successful!',
        text: 'Student template has been downloaded successfully.',
        confirmButtonText: 'OK',
        timer: 3000,
        timerProgressBar: true
      });
    }

    function downloadFacultyTemplate() {
      const wb = XLSX.utils.book_new();
      const wsData = [['Name of Faculty', 'Faculty ID', 'Department', 'Mobile Number', 'Mail ID', 'Role', 'Password'], ['Dr. Robert Johnson', 'FAC001', 'Computer Science', '9876543212', 'robert@example.com', 'Professor', 'Pass@789']];
      const ws = XLSX.utils.aoa_to_sheet(wsData);
      ws['!cols'] = [{wch:25},{wch:12},{wch:25},{wch:15},{wch:25},{wch:20},{wch:15}];
      XLSX.utils.book_append_sheet(wb, ws, 'Faculty Data');
      XLSX.writeFile(wb, 'Faculty_Account_Template.xlsx');
      
      Swal.fire({
        icon: 'success',
        title: 'Download Successful!',
        text: 'Faculty template has been downloaded successfully.',
        confirmButtonText: 'OK',
        timer: 3000,
        timerProgressBar: true
      });
    }

    function handleFileSelect(event) {
      selectedFile = event.target.files[0];
      if (selectedFile) { 
        document.getElementById('fileName').textContent = `Selected: ${selectedFile.name}`; 
        checkFormValidity(); 
      }
    }

    function checkFormValidity() {
      const accountType = document.getElementById('accountType').value;
      document.getElementById('submitBtn').disabled = !(accountType && selectedFile);
    }

    document.getElementById('accountType').addEventListener('change', checkFormValidity);

    function uploadData() {
      const accountType = document.getElementById('accountType').value;
      if (!selectedFile) { 
        Swal.fire({
          icon: 'warning',
          title: 'No File Selected',
          text: 'Please select an Excel file to upload.',
          confirmButtonText: 'OK'
        });
        return; 
      }
      
      Swal.fire({
        title: 'Uploading...',
        text: 'Please wait while we process your file.',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
          Swal.showLoading();
        }
      });
      
      const reader = new FileReader();
      reader.onload = function(e) {
        try {
          const data = new Uint8Array(e.target.result);
          const workbook = XLSX.read(data, {type: 'array'});
          const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
          const jsonData = XLSX.utils.sheet_to_json(firstSheet);
          
          if (jsonData.length === 0) { 
            Swal.fire({
              icon: 'error',
              title: 'Invalid File',
              text: 'Excel file is empty or invalid. Please check your file and try again.',
              confirmButtonText: 'OK'
            });
            return; 
          }
          
          const formData = new FormData();
          formData.append('action', 'upload');
          formData.append('accountType', accountType);
          formData.append('data', JSON.stringify(jsonData));
          
          fetch(API_URL, { method: 'POST', body: formData })
          .then(response => response.json())
          .then(result => {
            if (result.success) {
              Swal.fire({
                icon: 'success',
                title: 'Upload Successful!',
                html: `Successfully uploaded <strong>${result.successCount}</strong> records!`,
                confirmButtonText: 'Great!',
                timer: 3000,
                timerProgressBar: true
              });
              loadAllData();
              document.getElementById('excelFile').value = '';
              document.getElementById('fileName').textContent = '';
              document.getElementById('accountType').value = '';
              selectedFile = null;
              checkFormValidity();
            } else { 
              Swal.fire({
                icon: 'error',
                title: 'Upload Failed',
                text: result.message,
                confirmButtonText: 'OK'
              });
            }
          })
          .catch(error => { 
            Swal.fire({
              icon: 'error',
              title: 'Upload Error',
              text: 'An error occurred during upload: ' + error.message,
              confirmButtonText: 'OK'
            });
          });
        } catch (error) { 
          Swal.fire({
            icon: 'error',
            title: 'Processing Error',
            text: 'Error processing Excel file: ' + error.message,
            confirmButtonText: 'OK'
          });
        }
      };
      reader.readAsArrayBuffer(selectedFile);
    }

    function loadAllData() { loadStudentData(); loadFacultyData(); }

    function loadStudentData() {
      fetch(API_URL + '?action=fetch&type=student')
        .then(response => response.json())
        .then(data => {
          studentTable.clear();
          if (data.success && data.data.length > 0) {
            data.data.forEach(row => {
              const statusBadge = row.status === 'active' ? '<label class="badge badge-success">Active</label>' : '<label class="badge badge-danger">Inactive</label>';
              const actions = `<button class="action-btn btn-edit" onclick="editRecord(${row.id}, 'student')" title="Edit"><i class="ti-pencil"></i></button><button class="action-btn btn-delete" onclick="deleteRecord(${row.id}, 'student')" title="Delete"><i class="ti-trash"></i></button><button class="action-btn btn-toggle" onclick="toggleStatus(${row.id}, 'student', '${row.status}')" title="Toggle Status"><i class="ti-reload"></i></button>`;
              studentTable.row.add([row.name_of_student, row.register_number, row.programme, row.department, row.batch, row.year, row.section, row.mobile_number, row.mail_id, statusBadge, actions]);
            });
          }
          studentTable.draw();
        })
        .catch(error => {
          console.error('Error loading student data:', error);
          Swal.fire({
            icon: 'error',
            title: 'Data Loading Error',
            text: 'Failed to load student data. Please refresh the page.',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000
          });
        });
    }

    function loadFacultyData() {
      fetch(API_URL + '?action=fetch&type=faculty')
        .then(response => response.json())
        .then(data => {
          facultyTable.clear();
          if (data.success && data.data.length > 0) {
            data.data.forEach(row => {
              const statusBadge = row.status === 'active' ? '<label class="badge badge-success">Active</label>' : '<label class="badge badge-danger">Inactive</label>';
              const actions = `<button class="action-btn btn-edit" onclick="editRecord(${row.id}, 'faculty')" title="Edit"><i class="ti-pencil"></i></button><button class="action-btn btn-delete" onclick="deleteRecord(${row.id}, 'faculty')" title="Delete"><i class="ti-trash"></i></button><button class="action-btn btn-toggle" onclick="toggleStatus(${row.id}, 'faculty', '${row.status}')" title="Toggle Status"><i class="ti-reload"></i></button>`;
              facultyTable.row.add([row.name_of_faculty, row.faculty_id, row.department, row.mobile_number, row.mail_id, row.role, statusBadge, actions]);
            });
          }
          facultyTable.draw();
        })
        .catch(error => {
          console.error('Error loading faculty data:', error);
          Swal.fire({
            icon: 'error',
            title: 'Data Loading Error',
            text: 'Failed to load faculty data. Please refresh the page.',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000
          });
        });
    }

    function editRecord(id, type) {
      currentEditId = id;
      currentEditType = type;
      fetch(API_URL + `?action=get&type=${type}&id=${id}`)
        .then(response => response.json())
        .then(data => { 
          if (data.success) { 
            showEditModal(data.data, type); 
          } else {
            Swal.fire({
              icon: 'error',
              title: 'Error',
              text: 'Failed to load record data.',
              confirmButtonText: 'OK'
            });
          }
        })
        .catch(error => {
          console.error('Error:', error);
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'An error occurred while loading the record.',
            confirmButtonText: 'OK'
          });
        });
    }

    function showEditModal(data, type) {
      document.getElementById('modalTitle').textContent = `Edit ${type.charAt(0).toUpperCase() + type.slice(1)} Account`;
      let formHtml = '';
      if (type === 'student') {
        formHtml = `<div class="form-group"><label>Name of Student</label><input type="text" class="form-control" id="edit_name" value="${data.name_of_student}"></div><div class="form-group"><label>Register Number</label><input type="text" class="form-control" id="edit_register_number" value="${data.register_number}"></div><div class="form-group"><label>Programme</label><input type="text" class="form-control" id="edit_programme" value="${data.programme}"></div><div class="form-group"><label>Department</label><input type="text" class="form-control" id="edit_department" value="${data.department}"></div><div class="row"><div class="col-md-4"><div class="form-group"><label>Batch</label><input type="text" class="form-control" id="edit_batch" value="${data.batch}"></div></div><div class="col-md-4"><div class="form-group"><label>Year</label><input type="text" class="form-control" id="edit_year" value="${data.year}"></div></div><div class="col-md-4"><div class="form-group"><label>Section</label><input type="text" class="form-control" id="edit_section" value="${data.section}"></div></div></div><div class="form-group"><label>Mobile Number</label><input type="text" class="form-control" id="edit_mobile" value="${data.mobile_number}"></div><div class="form-group"><label>Mail ID</label><input type="email" class="form-control" id="edit_email" value="${data.mail_id}"></div><div class="form-group"><label>Password (leave blank to keep current)</label><input type="password" class="form-control" id="edit_password" placeholder="Enter new password"></div>`;
      } else {
        formHtml = `<div class="form-group"><label>Name of Faculty</label><input type="text" class="form-control" id="edit_name" value="${data.name_of_faculty}"></div><div class="form-group"><label>Faculty ID</label><input type="text" class="form-control" id="edit_faculty_id" value="${data.faculty_id}"></div><div class="form-group"><label>Department</label><input type="text" class="form-control" id="edit_department" value="${data.department}"></div><div class="form-group"><label>Mobile Number</label><input type="text" class="form-control" id="edit_mobile" value="${data.mobile_number}"></div><div class="form-group"><label>Mail ID</label><input type="email" class="form-control" id="edit_email" value="${data.mail_id}"></div><div class="form-group"><label>Role</label><input type="text" class="form-control" id="edit_role" value="${data.role}"></div><div class="form-group"><label>Password (leave blank to keep current)</label><input type="password" class="form-control" id="edit_password" placeholder="Enter new password"></div>`;
      }
      document.getElementById('modalBody').innerHTML = formHtml;
      document.getElementById('editModal').style.display = 'block';
    }

    function closeModal() {
      document.getElementById('editModal').style.display = 'none';
      currentEditId = null;
      currentEditType = null;
    }

    function saveEdit() {
      if (!currentEditId || !currentEditType) return;
      let updateData = {};
      if (currentEditType === 'student') {
        updateData = { name_of_student: document.getElementById('edit_name').value, register_number: document.getElementById('edit_register_number').value, programme: document.getElementById('edit_programme').value, department: document.getElementById('edit_department').value, batch: document.getElementById('edit_batch').value, year: document.getElementById('edit_year').value, section: document.getElementById('edit_section').value, mobile_number: document.getElementById('edit_mobile').value, mail_id: document.getElementById('edit_email').value };
      } else {
        updateData = { name_of_faculty: document.getElementById('edit_name').value, faculty_id: document.getElementById('edit_faculty_id').value, department: document.getElementById('edit_department').value, mobile_number: document.getElementById('edit_mobile').value, mail_id: document.getElementById('edit_email').value, role: document.getElementById('edit_role').value };
      }
      const password = document.getElementById('edit_password').value;
      if (password) { updateData.password = password; }
      
      Swal.fire({
        title: 'Saving...',
        text: 'Please wait while we update the record.',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
          Swal.showLoading();
        }
      });
      
      const formData = new FormData();
      formData.append('action', 'update');
      formData.append('type', currentEditType);
      formData.append('id', currentEditId);
      formData.append('data', JSON.stringify(updateData));
      
      fetch(API_URL, { method: 'POST', body: formData })
      .then(response => response.json())
      .then(result => {
        if (result.success) { 
          Swal.fire({
            icon: 'success',
            title: 'Updated!',
            text: 'Record has been updated successfully.',
            confirmButtonText: 'OK',
            timer: 2000,
            timerProgressBar: true
          });
          loadAllData(); 
          closeModal(); 
        } else { 
          Swal.fire({
            icon: 'error',
            title: 'Update Failed',
            text: result.message,
            confirmButtonText: 'OK'
          });
        }
      })
      .catch(error => { 
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'An error occurred: ' + error.message,
          confirmButtonText: 'OK'
        });
      });
    }

    function deleteRecord(id, type) {
      Swal.fire({
        title: 'Are you sure?',
        text: `Do you really want to delete this ${type} account? This action cannot be undone!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel',
        reverseButtons: true
      }).then((result) => {
        if (result.isConfirmed) {
          Swal.fire({
            title: 'Deleting...',
            text: 'Please wait',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => {
              Swal.showLoading();
            }
          });
          
          const formData = new FormData();
          formData.append('action', 'delete');
          formData.append('type', type);
          formData.append('id', id);
          
          fetch(API_URL, { method: 'POST', body: formData })
          .then(response => response.json())
          .then(result => {
            if (result.success) { 
              Swal.fire({
                icon: 'success',
                title: 'Deleted!',
                text: 'Record has been deleted successfully.',
                confirmButtonText: 'OK',
                timer: 2000,
                timerProgressBar: true
              });
              loadAllData(); 
            } else { 
              Swal.fire({
                icon: 'error',
                title: 'Delete Failed',
                text: result.message,
                confirmButtonText: 'OK'
              });
            }
          })
          .catch(error => { 
            Swal.fire({
              icon: 'error',
              title: 'Error',
              text: 'An error occurred: ' + error.message,
              confirmButtonText: 'OK'
            });
          });
        }
      });
    }

    function toggleStatus(id, type, currentStatus) {
      const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
      
      Swal.fire({
        title: 'Change Status?',
        text: `Do you want to change the status to ${newStatus}?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#17a2b8',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, change it!',
        cancelButtonText: 'Cancel'
      }).then((result) => {
        if (result.isConfirmed) {
          const formData = new FormData();
          formData.append('action', 'toggle');
          formData.append('type', type);
          formData.append('id', id);
          formData.append('status', newStatus);
          
          fetch(API_URL, { method: 'POST', body: formData })
          .then(response => response.json())
          .then(result => {
            if (result.success) { 
              Swal.fire({
                icon: 'success',
                title: 'Status Updated!',
                text: `Status has been changed to ${newStatus}.`,
                confirmButtonText: 'OK',
                timer: 2000,
                timerProgressBar: true
              });
              loadAllData(); 
            } else { 
              Swal.fire({
                icon: 'error',
                title: 'Update Failed',
                text: result.message,
                confirmButtonText: 'OK'
              });
            }
          })
          .catch(error => { 
            Swal.fire({
              icon: 'error',
              title: 'Error',
              text: 'An error occurred: ' + error.message,
              confirmButtonText: 'OK'
            });
          });
        }
      });
    }

    window.onclick = function(event) {
      const modal = document.getElementById('editModal');
      if (event.target == modal) { closeModal(); }
    }
  </script>
</body>
</html>