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
    .test-creation-container { background: white; border-radius: 8px; padding: 30px; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); position: relative; }
    .download-template-btn { position: absolute; top: 20px; right: 30px; background: #17a2b8; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; gap: 8px; }
    .download-template-btn:hover { background: #138496; transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.2); }
    .section-card { background: #f8f9fa; border-left: 4px solid #594ba1; padding: 20px; margin-bottom: 20px; border-radius: 5px; }
    .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
    .btn-primary-custom { background: linear-gradient(90deg, #594ba1ff 0%, #2575fc 100%); color: white; border: none; padding: 10px 25px; border-radius: 5px; cursor: pointer; transition: opacity 0.3s; }
    .btn-primary-custom:hover { opacity: 0.9; }
    .btn-add-section { background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin-top: 10px; }
    .btn-add-section:hover { background: #218838; }
    .btn-remove-section { background: #dc3545; color: white; border: none; padding: 5px 15px; border-radius: 5px; cursor: pointer; }
    .file-upload-area { border: 2px dashed #594ba1; padding: 20px; border-radius: 5px; text-align: center; background: white; margin: 10px 0; }
    .question-count-badge { background: #28a745; color: white; padding: 5px 15px; border-radius: 20px; font-weight: bold; display: inline-block; }
    .action-btn { padding: 5px 10px; margin: 0 2px; border: none; border-radius: 4px; cursor: pointer; transition: all 0.3s; }
    .btn-edit { background: #ffc107; color: white; }
    .btn-edit:hover { background: #e0a800; }
    .btn-delete { background: #dc3545; color: white; }
    .btn-delete:hover { background: #c82333; }
    .btn-view { background: #17a2b8; color: white; }
    .btn-view:hover { background: #138496; }
    .template-info { background: #e7f3ff; border-left: 4px solid #2196F3; padding: 15px; margin: 15px 0; border-radius: 5px; }
    .template-info h6 { color: #1976D2; margin-bottom: 10px; }
    .template-info ul { margin-bottom: 0; padding-left: 20px; }

    
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
    <!-- Navigation Bar -->
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
          <!-- Test Creation Form -->
          <div class="test-creation-container">
            <button class="download-template-btn" onclick="downloadQuestionTemplate()">
              <i class="ti-download"></i>Download Excel Template
            </button>
            
            <h4 class="mb-4">Create New Test</h4>
            
            <div class="template-info">
              <h6><i class="ti-info-alt"></i> Excel Template Format:</h6>
              <ul>
                <li><strong>MCQ (2-4 options):</strong> Question Type = "mcq", fill Option A, B (and optionally C, D), Correct Answer = letter (A/B/C/D)</li>
                <li><strong>True/False:</strong> Question Type = "truefalse", Option A = "True", Option B = "False", Correct Answer = "True" or "False"</li>
                <li><strong>Fill in the Blank:</strong> Question Type = "fillup", leave options blank, Correct Answer = expected answer</li>
              </ul>
            </div>
            
            <div class="form-group">
              <label for="testName">Test Name</label>
              <input type="text" class="form-control" id="testName" placeholder="Enter test name">
            </div>

            <div id="sectionsContainer">
              <h5 class="mt-4 mb-3">Test Sections</h5>
            </div>

            <button class="btn-add-section" onclick="addSection()">
              <i class="ti-plus mr-2"></i>Add Section
            </button>

            <div class="mt-4">
              <button class="btn-primary-custom" onclick="createTest()">
                <i class="ti-check mr-2"></i>Create Test
              </button>
            </div>
          </div>

          <!-- Created Tests List -->
          <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <h4 class="card-title mb-4">Created Tests</h4>
                  <div class="table-responsive">
                    <table class="table table-hover" id="testsTable">
                      <thead>
                        <tr>
                          <th>Test Name</th>
                          <th>Test Code</th>
                          <th>Sections</th>
                          <th>Created By</th>
                          <th>Created Date</th>
                          <th>Actions</th>
                        </tr>
                      </thead>
                      <tbody></tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
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
    let sectionCount = 0;
    let sectionsData = {}; // Store section data persistently
    let testsTable;
    const API_URL = 'test_api.php';

    $(document).ready(function() {
      initDataTables();
      loadTests();
    });

    function initDataTables() {
      testsTable = $('#testsTable').DataTable({
        pageLength: 10,
        order: [[4, 'desc']],
        language: { emptyTable: "No tests created yet" }
      });
    }

    function downloadQuestionTemplate() {
      const wb = XLSX.utils.book_new();
      const wsData = [
        ['Question Type', 'Question Text', 'Option A', 'Option B', 'Option C', 'Option D', 'Correct Answer'],
        ['mcq', 'What is 2+2?', '3', '4', '5', '6', 'B'],
        ['mcq', 'Capital of France?', 'London', 'Paris', 'Rome', 'Berlin', 'B'],
        ['mcq', 'Which is a primary color?', 'Red', 'Green', '', '', 'A'],
        ['truefalse', 'Earth is flat', 'True', 'False', '', '', 'False'],
        ['truefalse', 'Water boils at 100°C', 'True', 'False', '', '', 'True'],
        ['fillup', 'The capital of India is ____', '', '', '', '', 'New Delhi'],
        ['fillup', 'H2O is the chemical formula for ____', '', '', '', '', 'Water']
      ];
      const ws = XLSX.utils.aoa_to_sheet(wsData);
      ws['!cols'] = [{wch:15},{wch:50},{wch:20},{wch:20},{wch:20},{wch:20},{wch:20}];
      
      XLSX.utils.book_append_sheet(wb, ws, 'Questions');
      XLSX.writeFile(wb, 'Question_Template.xlsx');
      
      Swal.fire({
        icon: 'success',
        title: 'Template Downloaded!',
        html: '<p>Question template has been downloaded successfully.</p><p class="text-muted mt-2">Use this template to upload questions for each section.</p>',
        timer: 3000,
        timerProgressBar: true
      });
    }

    function addSection() {
      sectionCount++;
      const sectionId = sectionCount;
      
      // Initialize section data if not exists
      if (!sectionsData[sectionId]) {
        sectionsData[sectionId] = {
          name: '',
          questions: [],
          questionsDisplay: 10,
          marksPerQuestion: 1,
          negativeMarks: 0
        };
      }
      
      renderSections();
    }

    function renderSections() {
      const container = document.getElementById('sectionsContainer');
      let html = '<h5 class="mt-4 mb-3">Test Sections</h5>';
      
      const sectionIds = Object.keys(sectionsData).map(Number).sort((a, b) => a - b);
      
      sectionIds.forEach((id, index) => {
        const sectionNumber = index + 1;
        const data = sectionsData[id];
        
        html += `
          <div class="section-card" id="section${id}" data-section-id="${id}">
            <div class="section-header">
              <h6>Section ${sectionNumber}</h6>
              <button class="btn-remove-section" onclick="removeSection(${id})">
                <i class="ti-trash"></i> Remove
              </button>
            </div>
            
            <div class="form-group">
              <label>Section Name</label>
              <input type="text" class="form-control section-name" 
                     value="${data.name}" 
                     onchange="updateSectionData(${id}, 'name', this.value)" 
                     placeholder="e.g., Mathematics, Science, English">
            </div>

            <div class="file-upload-area">
              <input type="file" id="file${id}" accept=".xlsx,.xls" 
                     onchange="handleQuestionUpload(event, ${id})" style="display:none">
              <label for="file${id}" class="btn-primary-custom" style="cursor:pointer;">
                <i class="ti-upload mr-2"></i>Upload Questions (Excel)
              </label>
              <div id="uploadStatus${id}" class="mt-2">
                ${data.questions.length > 0 ? 
                  `<span class="text-success"><i class="ti-check"></i> ${data.questions.length} questions uploaded</span>` : 
                  '<span class="text-muted">No questions uploaded yet</span>'}
              </div>
            </div>

            <div class="row mt-3">
              <div class="col-md-3">
                <label>Total Questions Uploaded</label>
                <div class="question-count-badge" id="totalQ${id}">${data.questions.length}</div>
              </div>
              <div class="col-md-3">
                <label>Questions to Display</label>
                <input type="number" class="form-control questions-display" 
                       min="1" value="${data.questionsDisplay}" 
                       onchange="updateSectionData(${id}, 'questionsDisplay', this.value); validateQuestionCount(${id})">
              </div>
              <div class="col-md-3">
                <label>Marks per Question</label>
                <input type="number" class="form-control marks-per-question" 
                       step="0.5" value="${data.marksPerQuestion}"
                       onchange="updateSectionData(${id}, 'marksPerQuestion', this.value)">
              </div>
              <div class="col-md-3">
                <label>Negative Marks</label>
                <input type="number" class="form-control negative-marks" 
                       step="0.25" value="${data.negativeMarks}"
                       onchange="updateSectionData(${id}, 'negativeMarks', this.value)">
              </div>
            </div>
          </div>
        `;
      });
      
      container.innerHTML = html;
    }

    function updateSectionData(sectionId, field, value) {
      if (sectionsData[sectionId]) {
        sectionsData[sectionId][field] = value;
      }
    }

    function removeSection(id) {
      Swal.fire({
        title: 'Remove Section?',
        text: 'This will remove all questions in this section.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, remove it!'
      }).then((result) => {
        if (result.isConfirmed) {
          delete sectionsData[id];
          renderSections();
        }
      });
    }

    function validateQuestionCount(sectionId) {
      const data = sectionsData[sectionId];
      if (!data) return;
      
      const totalQuestions = data.questions.length;
      const displayValue = parseInt(data.questionsDisplay);
      
      if (displayValue > totalQuestions) {
        Swal.fire({
          icon: 'warning',
          title: 'Invalid Count',
          text: `Questions to display cannot exceed uploaded questions (${totalQuestions})`
        });
        sectionsData[sectionId].questionsDisplay = totalQuestions;
        renderSections();
      }
    }

    function handleQuestionUpload(event, sectionId) {
      const file = event.target.files[0];
      if (!file) return;

      const reader = new FileReader();
      reader.onload = function(e) {
        try {
          const data = new Uint8Array(e.target.result);
          const workbook = XLSX.read(data, {type: 'array'});
          const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
          const jsonData = XLSX.utils.sheet_to_json(firstSheet);
          
          if (jsonData.length === 0) {
            throw new Error('No data found in Excel file');
          }
          
          // Validate questions format
          const validQuestions = jsonData.filter(q => 
            q['Question Type'] && q['Question Text'] && q['Correct Answer']
          );
          
          if (validQuestions.length === 0) {
            throw new Error('No valid questions found. Please check the template format.');
          }
          
          sectionsData[sectionId].questions = validQuestions;
          sectionsData[sectionId].questionsDisplay = validQuestions.length;
          
          renderSections();
          
          Swal.fire({
            icon: 'success',
            title: 'Questions Uploaded!',
            text: `${validQuestions.length} questions uploaded successfully`,
            timer: 2000,
            timerProgressBar: true
          });
        } catch (error) {
          Swal.fire({
            icon: 'error',
            title: 'Upload Error',
            text: error.message
          });
        }
      };
      reader.readAsArrayBuffer(file);
    }

    function createTest() {
      const testName = document.getElementById('testName').value.trim();
      if (!testName) {
        Swal.fire({
          icon: 'warning',
          title: 'Missing Information',
          text: 'Please enter a test name'
        });
        return;
      }

      const sections = [];
      const sectionIds = Object.keys(sectionsData).map(Number).sort((a, b) => a - b);
      
      sectionIds.forEach((id, index) => {
        const data = sectionsData[id];
        
        if (data.name && data.questions.length > 0) {
          sections.push({
            section_name: data.name,
            section_order: index + 1,
            questions: data.questions,
            questions_to_display: data.questionsDisplay,
            marks_per_question: data.marksPerQuestion,
            negative_marks: data.negativeMarks
          });
        }
      });

      if (sections.length === 0) {
        Swal.fire({
          icon: 'warning',
          title: 'No Sections',
          text: 'Please add at least one section with a name and questions'
        });
        return;
      }

      Swal.fire({
        title: 'Creating Test...',
        text: 'Please wait',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
      });

      const formData = new FormData();
      formData.append('action', 'create_test');
      formData.append('test_name', testName);
      formData.append('sections', JSON.stringify(sections));

      fetch(API_URL, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(result => {
          if (result.success) {
            Swal.fire({
              icon: 'success',
              title: 'Test Created!',
              text: 'Test has been created successfully',
              timer: 2000,
              timerProgressBar: true
            });
            document.getElementById('testName').value = '';
            sectionsData = {};
            sectionCount = 0;
            renderSections();
            loadTests();
          } else {
            Swal.fire({
              icon: 'error',
              title: 'Creation Failed',
              text: result.message
            });
          }
        })
        .catch(error => {
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'An error occurred: ' + error.message
          });
        });
    }

    function loadTests() {
    fetch(API_URL + '?action=get_tests')
        .then(response => response.json())
        .then(data => {
            testsTable.clear();
            if (data.success && data.data.length > 0) {
                data.data.forEach(test => {
                    const actions = `
                        <button class="action-btn btn-view" onclick="previewAndEdit('${test.test_code}', ${test.id})" title="Preview & Edit">
                            <i class="ti-eye"></i> Preview & Edit
                        </button>
                        <button class="action-btn btn-delete" onclick="deleteTest(${test.id})" title="Delete">
                            <i class="ti-trash"></i>
                        </button>
                    `;
                    
                    const createdDate = new Date(test.created_at).toLocaleDateString();
                    
                    testsTable.row.add([
                        test.test_name,
                        test.test_code,
                        test.section_count,
                        test.created_by_name,  // Will display: FC001 - Dr. Ramesh - Mechanical
                        createdDate,
                        actions
                    ]);
                });
            }
            testsTable.draw();
        });
}

    function previewAndEdit(testCode, testId) {
      window.open(`test_preview_edit.php?code=${testCode}&id=${testId}`, '_blank');
    }

    function deleteTest(id) {
      Swal.fire({
        title: 'Are you sure?',
        text: 'This will permanently delete the test and all associated data!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!'
      }).then((result) => {
        if (result.isConfirmed) {
          const formData = new FormData();
          formData.append('action', 'delete_test');
          formData.append('test_id', id);

          fetch(API_URL, { method: 'POST', body: formData })
            .then(response => response.json())
            .then(result => {
              if (result.success) {
                Swal.fire('Deleted!', 'Test has been deleted.', 'success');
                loadTests();
              } else {
                Swal.fire('Error', result.message, 'error');
              }
            });
        }
      });
    }
  </script>
</body>
</html>