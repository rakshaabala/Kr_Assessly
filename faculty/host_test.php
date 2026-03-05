<?php
session_start();

if (
    !isset($_SESSION['user_type']) ||
    $_SESSION['user_type'] !== 'faculty'
) {
    header("Location: ../index.php");
    exit;
}

$facultyName = $_SESSION['user_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>KR ASSESSLY - Host Test</title>
  <link rel="stylesheet" href="../vendors/feather/feather.css">
  <link rel="stylesheet" href="../vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" href="../vendors/css/vendor.bundle.base.css">
  <link rel="stylesheet" href="../css/vertical-layout-light/style.css">
  <link rel="shortcut icon" href="../images/favicon.jpg" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.5/dist/sweetalert2.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    .hosting-form { background: white; border-radius: 8px; padding: 30px; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    .loaded-test-info { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
    .student-selection { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
    .student-list-container { height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 5px; background: white; }
    .student-item { padding: 12px; margin: 8px 0; background: white; border: 1px solid #e0e0e0; border-radius: 4px; display: flex; align-items: center; gap: 10px; cursor: pointer; transition: all 0.2s; }
    .student-item:hover { background: #f8f9fa; border-color: #594ba1; }
    .student-item.selected { background: #d4edda; border-color: #28a745; }
    .student-item input[type="checkbox"] { cursor: pointer; width: 18px; height: 18px; }
    .student-info { flex: 1; }
    .student-name { font-weight: 600; font-size: 13px; color: #333; margin: 0; }
    .student-meta { font-size: 12px; color: #666; margin: 0; }
    .btn-primary-custom { background: linear-gradient(90deg, #594ba1ff 0%, #2575fc 100%); color: white; border: none; padding: 12px 30px; border-radius: 5px; cursor: pointer; font-size: 16px; font-weight: 600; transition: all 0.3s; }
    .btn-primary-custom:hover { opacity: 0.9; }
    .btn-secondary-custom { background: #6c757d; color: white; border: none; padding: 10px 25px; border-radius: 5px; cursor: pointer; margin: 5px 0; }
    .btn-move-students { background: #17a2b8; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 10px 5px 0 0; font-weight: 600; }
    .btn-move-students:disabled { background: #ccc; cursor: not-allowed; }
    .btn-clear-all { background: #dc3545; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 10px 5px 0 0; font-weight: 600; }
    .btn-clear-all:disabled { background: #ccc; cursor: not-allowed; }
    .filter-section { background: white; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
    .filter-info { font-size: 13px; color: #666; padding: 10px; background: #f8f9fa; border-radius: 4px; margin-top: 10px; }
    .selected-count-badge { display: inline-block; background: #28a745; color: white; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600; }
    .swal2-popup { border-radius: 10px; }
    .swal2-styled.swal2-confirm { background: linear-gradient(90deg, #594ba1ff 0%, #2575fc 100%) !important; }
    .swal2-styled.swal2-cancel { background: #6c757d !important; }
    .hosted-test-card { background: white; padding: 20px; border-radius: 8px; margin-bottom: 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); position: relative; }
    .hosted-test-actions { position: absolute; top: 20px; right: 20px; }
    .btn-action { padding: 6px 12px; margin-left: 5px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; transition: all 0.2s; }
    .btn-edit { background: #ffc107; color: white; }
    .btn-edit:hover { background: #e0a800; }
    .btn-delete { background: #dc3545; color: white; }
    .btn-delete:hover { background: #c82333; }
    .status-badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
    .status-pending { background: #ffc107; color: #000; }
    .status-completed { background: #28a745; color: white; }
    .status-ongoing { background: #17a2b8; color: white; }
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
        <button class="navbar-toggler navbar-toggler align-self-center" type="button" data-toggle="minimize"><span class="icon-menu"></span></button>
        <ul class="navbar-nav navbar-nav-right">
          <li class="nav-item nav-profile dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown"><img src="../images/profile-logout-wo-bg.png" alt="profile"/></a>
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
          <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="fa-solid fa-gauge-high menu-icon"></i><span class="menu-title">Dashboard</span></a></li>
          
          <li class="nav-item"><a class="nav-link" href="create_test.php"><i class="fa-solid fa-file-pen menu-icon"></i><span class="menu-title">Create Test</span></a></li>
          <li class="nav-item"><a class="nav-link active" href="host_test.php"><i class="fa-solid fa-play menu-icon"></i><span class="menu-title">Host Test</span></a></li>
          <li class="nav-item"><a class="nav-link" href="test_report.php"><i class="fa-solid fa-chart-column menu-icon"></i><span class="menu-title">Test Reports</span></a></li>
          <li class="nav-item"><a class="nav-link" href="profile.php"><i class="fa-solid fa-user-gear menu-icon"></i><span class="menu-title">Profile</span></a></li>
        </ul>
      </nav>

      <div class="main-panel">
        <div class="content-wrapper">
          <div class="hosting-form" id="hostingForm">
            <h4 class="mb-4"><i class="fa-solid fa-play mr-2" style="color: #594ba1;"></i><span id="formTitle">Host New Test</span></h4>
            
            <div class="form-group">
              <label><strong>Select Test to Host</strong></label>
              <select class="form-control" id="testSelect" onchange="loadTestForHosting()">
                <option value="">-- Select Test --</option>
              </select>
            </div>

            <div id="testDetailsSection" style="display:none;">
              <div class="loaded-test-info">
                <h5><i class="fa-solid fa-clipboard mr-2"></i>Test: <span id="loadedTestName"></span></h5>
                <p class="mb-0">Code: <span id="loadedTestCode"></span> | Sections: <span id="loadedSections"></span></p>
              </div>

              <div class="card mt-3 mb-4">
                <div class="card-body">
                  <h6 class="mb-3"><i class="fa-solid fa-layer-group mr-2"></i>Section Configuration</h6>
                  <div id="sectionConfigContainer" class="row"></div>
                  <div class="mt-3 p-3 bg-light border rounded" id="sectionTotalsBox" style="display:none;">
                    <div class="d-flex flex-wrap">
                      <div class="mr-4 mb-2"><strong>Total questions (uploaded):</strong> <span id="totalQuestionsUploaded">0</span></div>
                      <div class="mr-4 mb-2"><strong>Total questions (to display):</strong> <span id="totalQuestionsDisplay">0</span></div>
                      <div class="mr-4 mb-2"><strong>Total marks:</strong> <span id="totalMarksPossible">0</span></div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="student-selection">
                <h5 class="mb-3"><i class="fa-solid fa-users mr-2"></i>Select Students</h5>
                
                <div class="filter-section">
                  <h6 class="mb-3">Filter Students</h6>
                  <div class="row">
                    <div class="col-md-4">
                      <label>Department</label>
                      <select class="form-control" id="deptFilter" onchange="applyFilters()">
                        <option value="">All Departments</option>
                      </select>
                    </div>
                    <div class="col-md-4">
                      <label>Batch</label>
                      <select class="form-control" id="batchFilter" onchange="applyFilters()">
                        <option value="">All Batches</option>
                      </select>
                    </div>
                    <div class="col-md-4">
                      <label>Section</label>
                      <select class="form-control" id="sectionFilter" onchange="applyFilters()">
                        <option value="">All Sections</option>
                      </select>
                    </div>
                  </div>
                  <div class="filter-info">
                    <i class="fa-solid fa-info-circle"></i> Showing <strong id="filteredCount">0</strong> students | <strong id="checkedCount">0</strong> selected
                  </div>
                </div>

                <div class="row">
                  <div class="col-md-6">
                    <h6><i class="fa-solid fa-list mr-2"></i>Available Students <span class="selected-count-badge" id="availableBadge">0</span></h6>
                    <div class="student-list-container" id="availableStudents"></div>
                    <button class="btn-move-students" onclick="moveToSelected()" id="moveBtn" disabled><i class="fa-solid fa-arrow-right mr-2"></i>Move Selected →</button>
                    <button class="btn-secondary-custom ml-2" onclick="selectAllAvailable()" id="selectAllBtn"><i class="fa-solid fa-check-double mr-2"></i>Select All</button>
                  </div>
                  <div class="col-md-6">
                    <h6><i class="fa-solid fa-check-circle mr-2"></i>Selected Students <span class="selected-count-badge" id="selectedBadge">0</span></h6>
                    <div class="student-list-container" id="selectedStudents"></div>
                    <button class="btn-move-students btn-secondary-custom" onclick="moveToAvailable()" id="moveBackBtn" disabled><i class="fa-solid fa-arrow-left mr-2"></i>← Move Back</button>
                    <button class="btn-clear-all" onclick="clearAllSelected()" id="clearAllBtn" disabled><i class="fa-solid fa-trash mr-2"></i>Clear All</button>
                  </div>
                </div>
              </div>

              <div class="row mt-4">
                <div class="col-md-6">
                  <div class="form-group"><label><strong>Test Date</strong></label><input type="date" class="form-control" id="testDate"></div>
                </div>
                <div class="col-md-3">
                  <div class="form-group"><label><strong>Start Time</strong></label><input type="time" class="form-control" id="startTime" onchange="validateDuration()"></div>
                </div>
                <div class="col-md-3">
                  <div class="form-group"><label><strong>End Time</strong></label><input type="time" class="form-control" id="endTime" onchange="validateDuration()"></div>
                </div>
              </div>

              <div class="row">
                <div class="col-md-4">
                  <div class="form-group"><label><strong>Duration (mins)</strong></label><input type="number" class="form-control" id="testDuration" min="1" value="60" onchange="validateDuration()"><small class="text-danger" id="durationError"></small></div>
                </div>
                <div class="col-md-4">
                  <div class="form-group"><label><strong>Question Shuffle</strong></label><select class="form-control" id="questionShuffle"><option value="yes">Yes</option><option value="no">No</option></select></div>
                </div>
                <div class="col-md-4">
                  <div class="form-group"><label><strong>Option Shuffle</strong></label><select class="form-control" id="optionShuffle"><option value="yes">Yes</option><option value="no">No</option></select></div>
                </div>
              </div>

              <div class="mt-4">
                <button class="btn-primary-custom" onclick="hostTest()" id="submitBtn"><i class="fa-solid fa-check mr-2"></i>Host Test</button>
                <button class="btn-secondary-custom ml-2" onclick="cancelHosting()"><i class="fa-solid fa-times mr-2"></i>Cancel</button>
              </div>
            </div>
          </div>

          <div class="card">
            <div class="card-body">
              <h4 class="card-title mb-4"><i class="fa-solid fa-history mr-2"></i>Hosted Tests</h4>
              <div id="hostedTestsContainer" style="max-height: 600px; overflow-y: auto;"></div>
            </div>
          </div>
        </div>

        <footer class="footer" style="background: linear-gradient(90deg, #594ba1ff 0%, #2575fc 100%);color: white;padding: 30px 0;">
          <div class="text-center"><span style="font-family: 'Segoe UI', 'Roboto', 'Helvetica Neue', sans-serif;font-size: 1rem;color: white;">© 2025, Designed and Developed by <strong style="font-weight: 700;text-transform: uppercase;color: white; font-style: italic;">KR ASSESSLY TEAM</strong> - All rights reserved.</span></div>
        </footer>
      </div>
    </div>
  </div>

  <script src="../vendors/js/vendor.bundle.base.js"></script>
  <script src="../js/off-canvas.js"></script>
  <script src="../js/hoverable-collapse.js"></script>
  <script src="../js/template.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.5/dist/sweetalert2.all.min.js"></script>

  <script>
    let loadedTestId = null;
    let allActiveStudents = [];
    let selectedStudentIds = [];
    let currentFilteredStudents = [];
    let editingHostedTestId = null;
    let sectionConfigs = [];
    const API_URL = 'test_api.php';

    $(document).ready(function() {
      loadTests();
      setMinDate();
      loadFilterOptions();
      loadActiveStudents();
      loadHostedTests();
    });

    function setMinDate() {
      const today = new Date().toISOString().split('T')[0];
      document.getElementById('testDate').min = today;
      document.getElementById('testDate').value = today;
    }

    function loadTests() {
      fetch(API_URL + '?action=get_tests')
        .then(response => response.json())
        .then(data => {
          const select = document.getElementById('testSelect');
          select.innerHTML = '<option value="">-- Select Test --</option>';
          if (data.success && data.data.length > 0) {
            data.data.forEach(test => {
              select.innerHTML += `<option value="${test.id}" data-name="${test.test_name}" data-code="${test.test_code}" data-sections="${test.section_count}">${test.test_name} (${test.section_count} sections)</option>`;
            });
          }
        });
    }

    function renderSectionConfigs() {
      const container = document.getElementById('sectionConfigContainer');
      if (!container) return;
      if (!sectionConfigs.length) {
        container.innerHTML = '<div class="col-12 text-muted">No sections found for this test.</div>';
        document.getElementById('sectionTotalsBox').style.display = 'none';
        return;
      }

      let totalUploaded = 0;
      let totalDisplay = 0;
      let totalMarks = 0;

      let html = '';
      sectionConfigs.forEach((s, idx) => {
        const uploaded = parseInt(s.total_questions || 0);
        const displayCount = parseInt(s.questions_to_display || 0);
        const marksPerQ = parseFloat(s.marks_per_question || 0);
        totalUploaded += uploaded;
        totalDisplay += displayCount;
        totalMarks += displayCount * marksPerQ;

        html += `
          <div class="col-md-6 col-lg-4 mb-3">
            <div class="p-3 border rounded">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <strong>Section ${idx + 1}</strong>
                <span class="badge badge-light">${s.section_name || 'Untitled'}</span>
              </div>
              <div class="small text-muted mb-2">Uploaded: ${s.total_questions} questions</div>
              <div class="form-group mb-2">
                <label class="small mb-1">Questions to display</label>
                <input type="number" class="form-control form-control-sm"
                  min="1" max="${s.total_questions}"
                  value="${s.questions_to_display}"
                  onchange="updateSectionConfig(${s.id}, 'questions_to_display', this.value)">
              </div>
              <div class="form-group mb-1">
                <label class="small mb-1">Marks per question</label>
                <input type="number" step="0.25" class="form-control form-control-sm"
                  value="${s.marks_per_question}"
                  onchange="updateSectionConfig(${s.id}, 'marks_per_question', this.value)">
              </div>
            </div>
          </div>
        `;
      });
      container.innerHTML = html;

      const totalsBox = document.getElementById('sectionTotalsBox');
      if (totalsBox) {
        document.getElementById('totalQuestionsUploaded').textContent = totalUploaded;
        document.getElementById('totalQuestionsDisplay').textContent = totalDisplay;
        document.getElementById('totalMarksPossible').textContent = totalMarks.toFixed(2);
        totalsBox.style.display = 'block';
      }
    }

    function updateSectionConfig(id, field, value) {
      sectionConfigs = sectionConfigs.map(s => {
        if (parseInt(s.id) === parseInt(id)) {
          const copy = {...s};
          if (field === 'marks_per_question') {
            copy[field] = parseFloat(value || 0);
          } else {
            const num = parseInt(value || 1);
            const max = parseInt(copy.total_questions || num);
            copy[field] = Math.max(1, Math.min(num, max));
          }
          return copy;
        }
        return s;
      });
    }

    function loadActiveStudents() {
      fetch(API_URL + '?action=get_students')
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            allActiveStudents = data.data.filter(s => s.status === 'active');
            currentFilteredStudents = [...allActiveStudents];
            
            // If a test is already loaded, select all students by default
            if (loadedTestId && !editingHostedTestId) {
              selectedStudentIds = currentFilteredStudents.map(s => parseInt(s.id));
            }
            
            displayAvailableStudents();
            displaySelectedStudents();
          }
        });
    }

    function loadFilterOptions() {
      fetch(API_URL + '?action=get_filter_options')
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            const deptSelect = document.getElementById('deptFilter');
            const batchSelect = document.getElementById('batchFilter');
            deptSelect.innerHTML = '<option value="">All Departments</option>';
            data.departments.forEach(dept => {
              deptSelect.innerHTML += `<option value="${dept}">${dept}</option>`;
            });
            batchSelect.innerHTML = '<option value="">All Batches</option>';
            data.batches.forEach(batch => {
              batchSelect.innerHTML += `<option value="${batch}">${batch}</option>`;
            });
          }
        });
    }

    function applyFilters() {
      const dept = document.getElementById('deptFilter').value;
      const batch = document.getElementById('batchFilter').value;
      const section = document.getElementById('sectionFilter').value;
      
      console.log('Applying filters:', { dept, batch, section });
      
      // Reset section filter when dept or batch changes
      if (dept || batch) {
        console.log('Fetching sections for:', { dept, batch });
        fetch(API_URL + `?action=get_sections&dept=${dept}&batch=${batch}`)
          .then(response => response.json())
          .then(data => {
            console.log('Sections response:', data);
            const sectionSelect = document.getElementById('sectionFilter');
            sectionSelect.innerHTML = '<option value="">All Sections</option>';
            if (data.success) {
              data.sections.forEach(sec => {
                sectionSelect.innerHTML += `<option value="${sec}">${sec}</option>`;
              });
            }
            // Clear section selection since we're reloading sections
            document.getElementById('sectionFilter').value = '';
          })
          .catch(error => {
            console.error('Error fetching sections:', error);
          });
      }
      
      currentFilteredStudents = allActiveStudents.filter(s => {
        const matchesDept = !dept || s.department === dept;
        const matchesBatch = !batch || s.batch == batch;
        const matchesSection = !section || s.section === section;
        
        console.log('Student filter check:', {
          student: s.name_of_student,
          dept: s.department,
          batch: s.batch,
          section: s.section,
          matchesDept,
          matchesBatch,
          matchesSection,
          included: matchesDept && matchesBatch && matchesSection
        });
        
        return matchesDept && matchesBatch && matchesSection;
      });
      
      console.log('Filtered students count:', currentFilteredStudents.length);
      
      // Maintain "select all" behavior when filters change
      if (loadedTestId && !editingHostedTestId) {
        selectedStudentIds = currentFilteredStudents.map(s => parseInt(s.id));
      }
      
      displayAvailableStudents();
    }

    function displayAvailableStudents() {
      const container = document.getElementById('availableStudents');
      const availableOnly = currentFilteredStudents.filter(s => !selectedStudentIds.includes(parseInt(s.id)));
      
      if (availableOnly.length === 0) {
        container.innerHTML = '<p class="text-center text-muted p-3">No available students</p>';
      } else {
        container.innerHTML = availableOnly.map(s => `
          <label class="student-item m-0">
            <input type="checkbox" class="student-checkbox" data-id="${s.id}" onchange="updateMoveButton()">
            <div class="student-info">
              <p class="student-name">${s.name_of_student}</p>
              <p class="student-meta">${s.register_number} | ${s.department} - ${s.section} (Batch ${s.batch})</p>
            </div>
          </label>
        `).join('');
      }
      updateCounts();
    }

    function displaySelectedStudents() {
      const container = document.getElementById('selectedStudents');
      const selected = allActiveStudents.filter(s => selectedStudentIds.includes(parseInt(s.id)));
      
      if (selected.length === 0) {
        container.innerHTML = '<p class="text-center text-muted p-3">No students selected</p>';
      } else {
        container.innerHTML = selected.map(s => `
          <label class="student-item selected m-0">
            <input type="checkbox" class="selected-checkbox" data-id="${s.id}" onchange="updateMoveBackButton()">
            <div class="student-info">
              <p class="student-name">${s.name_of_student}</p>
              <p class="student-meta">${s.register_number}</p>
            </div>
          </label>
        `).join('');
      }
      updateCounts();
    }

    function updateMoveButton() {
      const checkboxes = document.querySelectorAll('#availableStudents input[type="checkbox"]:checked');
      document.getElementById('moveBtn').disabled = checkboxes.length === 0;
    }

    function updateMoveBackButton() {
      const checkboxes = document.querySelectorAll('#selectedStudents input[type="checkbox"]:checked');
      document.getElementById('moveBackBtn').disabled = checkboxes.length === 0;
    }

    function updateCounts() {
      const availableCount = currentFilteredStudents.filter(s => !selectedStudentIds.includes(parseInt(s.id))).length;
      document.getElementById('filteredCount').textContent = currentFilteredStudents.length;
      document.getElementById('checkedCount').textContent = selectedStudentIds.length;
      document.getElementById('availableBadge').textContent = availableCount;
      document.getElementById('selectedBadge').textContent = selectedStudentIds.length;
      document.getElementById('clearAllBtn').disabled = selectedStudentIds.length === 0;
      document.getElementById('selectAllBtn').disabled = availableCount === 0;
    }

    function moveToSelected() {
      const checkboxes = document.querySelectorAll('#availableStudents input[type="checkbox"]:checked');
      checkboxes.forEach(checkbox => {
        const studentId = parseInt(checkbox.dataset.id);
        if (!selectedStudentIds.includes(studentId)) {
          selectedStudentIds.push(studentId);
        }
      });
      displayAvailableStudents();
      displaySelectedStudents();
      updateMoveButton();
    }

    function moveToAvailable() {
      const checkboxes = document.querySelectorAll('#selectedStudents input[type="checkbox"]:checked');
      checkboxes.forEach(checkbox => {
        const studentId = parseInt(checkbox.dataset.id);
        selectedStudentIds = selectedStudentIds.filter(id => id !== studentId);
      });
      displayAvailableStudents();
      displaySelectedStudents();
      updateMoveBackButton();
    }

    function selectAllAvailable() {
      const availableStudents = currentFilteredStudents.filter(s => !selectedStudentIds.includes(parseInt(s.id)));
      const newSelections = availableStudents.map(s => parseInt(s.id));
      selectedStudentIds = [...selectedStudentIds, ...newSelections];
      displayAvailableStudents();
      displaySelectedStudents();
    }

    function clearAllSelected() {
      Swal.fire({
        title: 'Clear All Selected Students?',
        text: 'This will remove all students from the selected list',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, Clear All'
      }).then((result) => {
        if (result.isConfirmed) {
          selectedStudentIds = [];
          displayAvailableStudents();
          displaySelectedStudents();
        }
      });
    }

    function loadTestForHosting() {
      const select = document.getElementById('testSelect');
      if (!select.value) {
        document.getElementById('testDetailsSection').style.display = 'none';
        return;
      }
      const selected = select.options[select.selectedIndex];
      loadedTestId = select.value;
      document.getElementById('loadedTestName').textContent = selected.dataset.name;
      document.getElementById('loadedTestCode').textContent = selected.dataset.code;
      document.getElementById('loadedSections').textContent = selected.dataset.sections;
      document.getElementById('testDetailsSection').style.display = 'block';

      fetch(`${API_URL}?action=get_test_sections_config&test_id=${loadedTestId}`)
        .then(res => res.json())
        .then(res => {
          if (res.success) {
            sectionConfigs = res.sections || [];
            renderSectionConfigs();
          }
        });
      
      if (!editingHostedTestId) {
        // Select all students by default
        selectedStudentIds = currentFilteredStudents.map(s => parseInt(s.id));
        displayAvailableStudents();
        displaySelectedStudents();
      }
    }

    function validateDuration() {
      const startTime = document.getElementById('startTime').value;
      const endTime = document.getElementById('endTime').value;
      const duration = parseInt(document.getElementById('testDuration').value);
      const errorDiv = document.getElementById('durationError');
      
      if (startTime && endTime) {
        const start = new Date('2000-01-01 ' + startTime);
        const end = new Date('2000-01-01 ' + endTime);
        const maxDuration = (end - start) / 60000;
        
        if (duration > maxDuration) {
          errorDiv.textContent = `Duration cannot exceed ${maxDuration} minutes`;
          document.getElementById('testDuration').value = maxDuration;
        } else {
          errorDiv.textContent = '';
        }
      }
    }

    function validateSectionConfigs() {
      if (!sectionConfigs.length) {
        Swal.fire('Validation Error', 'No sections found for this test.', 'error');
        return false;
      }
      for (const s of sectionConfigs) {
        const display = parseInt(s.questions_to_display || 0);
        const total = parseInt(s.total_questions || 0);
        if (!display || display < 1) {
          Swal.fire('Validation Error', `Section "${s.section_name}" must display at least 1 question.`, 'error');
          return false;
        }
        if (display > total) {
          Swal.fire('Validation Error', `Section "${s.section_name}" cannot display more than uploaded questions (${total}).`, 'error');
          return false;
        }
      }
      return true;
    }

    function hostTest() {
      if (!loadedTestId) {
        Swal.fire('Error', 'No test selected', 'error');
        return;
      }
      if (selectedStudentIds.length === 0) {
        Swal.fire('Warning', 'Please select at least one student', 'warning');
        return;
      }
      
      const testDate = document.getElementById('testDate').value;
      const startTime = document.getElementById('startTime').value;
      const endTime = document.getElementById('endTime').value;
      const duration = document.getElementById('testDuration').value;
      
      if (!testDate || !startTime || !endTime) {
        Swal.fire('Warning', 'Please fill all date and time fields', 'warning');
        return;
      }
      if (!validateSectionConfigs()) {
        return;
      }

      const action = editingHostedTestId ? 'update_hosted_test' : 'host_test';
      const title = editingHostedTestId ? 'Confirm Update' : 'Confirm Hosting';
      const successMsg = editingHostedTestId ? 'Test updated successfully' : 'Test hosted successfully';

      Swal.fire({
        title: title,
        html: `<p><strong>Students:</strong> ${selectedStudentIds.length}</p><p><strong>Date:</strong> ${testDate}</p><p><strong>Time:</strong> ${startTime} - ${endTime}</p><p><strong>Duration:</strong> ${duration} mins</p>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: editingHostedTestId ? 'Yes, Update!' : 'Yes, Host Test!'
      }).then((result) => {
        if (result.isConfirmed) {
          const formData = new FormData();
          formData.append('action', action);
          if (editingHostedTestId) {
            formData.append('hosted_test_id', editingHostedTestId);
          }
          formData.append('test_id', loadedTestId);
          formData.append('test_date', testDate);
          formData.append('start_time', startTime);
          formData.append('end_time', endTime);
          formData.append('test_duration', duration);
          formData.append('question_shuffle', document.getElementById('questionShuffle').value);
          formData.append('option_shuffle', document.getElementById('optionShuffle').value);
          formData.append('student_ids', JSON.stringify(selectedStudentIds));
          formData.append('section_configs', JSON.stringify(sectionConfigs));
          
          Swal.fire({ title: editingHostedTestId ? 'Updating Test...' : 'Hosting Test...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
          
          fetch(API_URL, { method: 'POST', body: formData })
            .then(response => response.json())
            .then(result => {
              Swal.close();
              if (result.success) {
                Swal.fire('Success!', successMsg, 'success');
                cancelHosting();
                loadHostedTests();
              } else {
                Swal.fire('Error', result.message, 'error');
              }
            });
        }
      });
    }

    function editHostedTest(hostedTestId) {

fetch(API_URL + '?action=get_hosted_test_details&hosted_test_id=' + hostedTestId)
  .then(res => res.json())
  .then(res => {

    if (!res.success) {
      Swal.fire('Error', res.message, 'error');
      return;
    }

    const d = res.data;
    editingHostedTestId = hostedTestId;

    // Change form mode
    document.getElementById('formTitle').innerText = 'Edit Hosted Test';
    document.getElementById('submitBtn').innerHTML =
      '<i class="fa-solid fa-save mr-2"></i>Update Test';

    // Select test
    document.getElementById('testSelect').value = d.test_id;
    loadTestForHosting();

    // Fill form fields
    document.getElementById('testDate').value = d.test_date;
    document.getElementById('startTime').value = d.start_time;
    document.getElementById('endTime').value = d.end_time;
    document.getElementById('testDuration').value = d.test_duration;
    document.getElementById('questionShuffle').value = d.question_shuffle;
    document.getElementById('optionShuffle').value = d.option_shuffle;

    // Selected students
    selectedStudentIds = d.student_ids.map(Number);

    displayAvailableStudents();
    displaySelectedStudents();

    // Show form
    document.getElementById('testDetailsSection').style.display = 'block';

    window.scrollTo({ top: 0, behavior: 'smooth' });
  });
}


    function deleteHostedTest(hostedTestId) {
      console.log('Deleting hosted test:', hostedTestId);
      
      Swal.fire({
        title: 'Delete Hosted Test?',
        text: 'This will delete the test and all associated student assignments. This action cannot be undone!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, Delete!',
        confirmButtonColor: '#dc3545'
      }).then((result) => {
        if (result.isConfirmed) {
          const formData = new FormData();
          formData.append('action', 'delete_hosted_test');
          formData.append('hosted_test_id', hostedTestId);
          
          Swal.fire({ 
            title: 'Deleting...', 
            allowOutsideClick: false, 
            didOpen: () => Swal.showLoading() 
          });
          
          fetch(API_URL, { method: 'POST', body: formData })
            .then(response => response.json())
            .then(result => {
              console.log('Delete response:', result);
              Swal.close();
              
              if (result.success) {
                Swal.fire('Deleted!', result.message || 'Test has been deleted', 'success');
                loadHostedTests();
              } else {
                Swal.fire('Error', result.message || 'Failed to delete test', 'error');
              }
            })
            .catch(error => {
              console.error('Delete error:', error);
              Swal.close();
              Swal.fire('Error', 'Failed to delete test', 'error');
            });
        }
      });
    }

    function loadHostedTests() {
      fetch(API_URL + '?action=get_reports')
        .then(response => response.json())
        .then(data => {
          const container = document.getElementById('hostedTestsContainer');
          if (data.success && data.data.length > 0) {
            // Sort by id descending (newest first)
            const sortedTests = data.data.sort((a, b) => b.id - a.id);
            
            container.innerHTML = '<div class="row">' + sortedTests.map(ht => {
              const canEdit = ht.status !== 'completed';
              const statusClass = ht.status === 'completed' ? 'status-completed' : (ht.status === 'ongoing' ? 'status-ongoing' : 'status-pending');
              
              return `
              <div class="col-md-6 mb-3">
                <div class="hosted-test-card">
                  ${canEdit ? `
                  <div class="hosted-test-actions">
                    <button class="btn-action btn-edit" onclick="editHostedTest(${ht.id})" title="Edit Test">
                      <i class="fa-solid fa-edit"></i> Edit
                    </button>
                    <button class="btn-action btn-delete" onclick="deleteHostedTest(${ht.id})" title="Delete Test">
                      <i class="fa-solid fa-trash"></i> Delete
                    </button>
                  </div>
                  ` : ''}
                  <div class="d-flex justify-content-between mb-2" style="padding-right: ${canEdit ? '150px' : '0'};">
                    <div>
                      <h6 class="mb-1">${ht.test_name}</h6>
                      <small class="text-muted">${ht.test_date} | ${ht.start_time} - ${ht.end_time} (${ht.test_duration} mins)</small>
                    </div>
                  </div>
                  <div style="font-size:13px;">
                    <span class="status-badge ${statusClass}">${ht.status || 'pending'}</span>
                    <span class="badge badge-info">${ht.total_students} Students</span>
                    <span class="badge badge-success">${ht.completed_students || 0} Completed</span>
                  </div>
                </div>
              </div>
            `}).join('') + '</div>';
          } else {
            container.innerHTML = '<p class="text-center text-muted">No hosted tests</p>';
          }
        });
    }

    function cancelHosting() {
      loadedTestId = null;
      editingHostedTestId = null;
      selectedStudentIds = [];
      document.getElementById('testSelect').value = '';
      document.getElementById('testDetailsSection').style.display = 'none';
      document.getElementById('formTitle').textContent = 'Host New Test';
      document.getElementById('submitBtn').innerHTML = '<i class="fa-solid fa-check mr-2"></i>Host Test';
      document.getElementById('deptFilter').value = '';
      document.getElementById('batchFilter').value = '';
      document.getElementById('sectionFilter').value = '';
      currentFilteredStudents = [...allActiveStudents];
      displayAvailableStudents();
    }

    setInterval(loadHostedTests, 30000);
  </script>
</body>
</html>
