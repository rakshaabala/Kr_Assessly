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
  <title>KR ASSESSLY - Test Preview & Edit</title>
  <link rel="stylesheet" href="../vendors/feather/feather.css">
  <link rel="stylesheet" href="../vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" href="../vendors/css/vendor.bundle.base.css">
  <link rel="stylesheet" href="../css/vertical-layout-light/style.css">
  <link rel="shortcut icon" href="../images/favicon.jpg" />
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.5/dist/sweetalert2.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
  <style>
    .test-header { background: linear-gradient(90deg, #594ba1ff 0%, #2575fc 100%); color: white; padding: 20px 30px; border-radius: 8px; margin-bottom: 30px; }
    .test-header h3 { margin: 0; font-weight: 600; }
    .test-header .test-code { font-size: 14px; opacity: 0.9; margin-top: 5px; }
    .section-card { background: white; border-radius: 8px; padding: 25px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border-left: 4px solid #594ba1; }
    .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
    .section-title { font-size: 20px; font-weight: 600; color: #333; }
    .section-meta { display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 15px; }
    .meta-item { background: #f8f9fa; padding: 10px 15px; border-radius: 5px; }
    .meta-item label { font-weight: 600; color: #666; font-size: 12px; display: block; margin-bottom: 3px; }
    .meta-item .value { font-size: 16px; color: #333; font-weight: 600; }
    .btn-action { padding: 8px 15px; margin: 0 3px; border: none; border-radius: 5px; cursor: pointer; transition: all 0.3s; font-size: 13px; }
    .btn-edit { background: #ffc107; color: white; }
    .btn-edit:hover { background: #e0a800; transform: translateY(-2px); }
    .btn-delete { background: #dc3545; color: white; }
    .btn-delete:hover { background: #c82333; transform: translateY(-2px); }
    .btn-download { background: #17a2b8; color: white; }
    .btn-download:hover { background: #138496; transform: translateY(-2px); }
    .btn-add { background: #28a745; color: white; }
    .btn-add:hover { background: #218838; transform: translateY(-2px); }
    .btn-primary-custom { background: linear-gradient(90deg, #594ba1ff 0%, #2575fc 100%); color: white; border: none; padding: 10px 25px; border-radius: 5px; cursor: pointer; transition: opacity 0.3s; }
    .btn-primary-custom:hover { opacity: 0.9; }
    .action-buttons { display: flex; gap: 10px; margin-bottom: 20px; }
    .table-action-btn { padding: 4px 8px; font-size: 11px; }
    .question-type-badge { padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
    .badge-mcq { background: #e3f2fd; color: #1976d2; }
    .badge-truefalse { background: #f3e5f5; color: #7b1fa2; }
    .badge-fillup { background: #e8f5e9; color: #388e3c; }
    .file-upload-area { border: 2px dashed #594ba1; padding: 20px; border-radius: 5px; text-align: center; background: white; margin: 15px 0; }
    .swal2-popup { border-radius: 10px; }
    .swal2-styled.swal2-confirm { background: linear-gradient(90deg, #594ba1ff 0%, #2575fc 100%) !important; }
    .download-all-btn { position: fixed; bottom: 30px; right: 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 15px 25px; border-radius: 50px; cursor: pointer; box-shadow: 0 4px 15px rgba(0,0,0,0.3); z-index: 1000; font-weight: 600; transition: all 0.3s; }
    .download-all-btn:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,0.4); }
    .add-section-btn { background: #28a745; color: white; border: none; padding: 12px 30px; border-radius: 5px; cursor: pointer; font-weight: 600; margin: 20px 0; transition: all 0.3s; }
    .add-section-btn:hover { background: #218838; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.2); }
    .template-info { background: #e7f3ff; border-left: 4px solid #2196F3; padding: 15px; margin: 15px 0; border-radius: 5px; }
    .template-info h6 { color: #1976D2; margin-bottom: 10px; }
    .template-info ul { margin-bottom: 0; padding-left: 20px; }
    .main-panel .content-wrapper { padding: 20px; }
    .test-creation-container { background: white; border-radius: 8px; padding: 30px; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); position: relative; }
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
          <div class="test-creation-container">
            <div class="test-header">
              <h3 id="testName">Loading Test...</h3>
              <div class="test-code">Test Code: <span id="testCode"></span></div>
            </div>

            <div class="action-buttons">
              <button class="btn-action btn-add" onclick="showAddSectionModal()">
                <i class="ti-plus"></i> Add New Section
              </button>
              <button class="btn-action btn-download" onclick="downloadEntireTest()">
                <i class="ti-download"></i> Download Complete Test
              </button>
              <button class="btn-action btn-primary-custom" onclick="window.location.href='create_test.php'">
                <i class="ti-arrow-left"></i> Back to Create Test
              </button>
            </div>

            <div class="template-info">
              <h6><i class="ti-info-alt"></i> Excel Template Format:</h6>
              <ul>
                <li><strong>MCQ (2-4 options):</strong> Question Type = "mcq", fill Option A, B (and optionally C, D), Correct Answer = letter (A/B/C/D)</li>
                <li><strong>True/False:</strong> Question Type = "truefalse", Option A = "True", Option B = "False", Correct Answer = "True" or "False"</li>
                <li><strong>Fill in the Blank:</strong> Question Type = "fillup", leave options blank, Correct Answer = expected answer</li>
              </ul>
            </div>

            <div id="sectionsContainer"></div>
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
    const API_URL = 'test_api.php';
    let testData = null;
    let testId = null;
    let testCode = null;
    let sectionTables = {};

    $(document).ready(function() {
      const urlParams = new URLSearchParams(window.location.search);
      testCode = urlParams.get('code');
      testId = urlParams.get('id');
      
      if (!testCode || !testId) {
        Swal.fire('Error', 'Invalid test parameters', 'error').then(() => {
          window.location.href = 'create_test.php';
        });
        return;
      }
      
      loadTestData();
    });

    function loadTestData() {
      fetch(`${API_URL}?action=get_test_details&test_id=${testId}`)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            testData = data.data;
            renderTestData();
          } else {
            Swal.fire('Error', 'Failed to load test data', 'error');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          Swal.fire('Error', 'Failed to load test data', 'error');
        });
    }

    function getTestDetails() {
      return new Promise((resolve, reject) => {
        fetch(`${API_URL}?action=get_test_details&test_id=${testId}`)
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              resolve(data.data);
            } else {
              reject(new Error(data.message));
            }
          })
          .catch(reject);
      });
    }

    function renderTestData() {
      document.getElementById('testName').textContent = testData.test_name;
      document.getElementById('testCode').textContent = testData.test_code;
      
      const container = document.getElementById('sectionsContainer');
      let html = '';
      
      testData.sections.forEach((section, index) => {
        html += `
          <div class="section-card" data-section-id="${section.id}">
            <div class="section-header">
              <div class="section-title">
                <i class="ti-bookmark-alt"></i> Section ${index + 1}: ${section.section_name}
              </div>
              <div>
                <button class="btn-action btn-edit" onclick="editSection(${section.id})">
                  <i class="ti-pencil"></i> Edit Section
                </button>
                <button class="btn-action btn-download" onclick="downloadSection(${section.id})">
                  <i class="ti-download"></i> Download
                </button>
                <button class="btn-action btn-delete" onclick="deleteSection(${section.id})">
                  <i class="ti-trash"></i> Delete
                </button>
              </div>
            </div>
            
            <div class="section-meta">
              <div class="meta-item">
                <label>Total Questions</label>
                <div class="value">${section.total_questions}</div>
              </div>
              <div class="meta-item">
                <label>Questions to Display</label>
                <div class="value">${section.questions_to_display}</div>
              </div>
              <div class="meta-item">
                <label>Marks per Question</label>
                <div class="value">${section.marks_per_question}</div>
              </div>
              <div class="meta-item">
                <label>Negative Marks</label>
                <div class="value">${section.negative_marks}</div>
              </div>
            </div>
            
            <div class="table-responsive">
              <table class="table table-hover" id="questionsTable${section.id}">
                <thead>
                  <tr>
                    <th width="5%">#</th>
                    <th width="10%">Type</th>
                    <th width="40%">Question</th>
                    <th width="25%">Options/Answer</th>
                    <th width="10%">Correct Answer</th>
                    <th width="10%">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  ${renderQuestions(section.questions)}
                </tbody>
              </table>
            </div>
            
            <div style="margin-top: 15px;">
              <button class="btn-action btn-add" onclick="addQuestionToSection(${section.id})">
                <i class="ti-plus"></i> Add Question
              </button>
              <button class="btn-action" onclick="uploadMoreQuestions(${section.id})" style="background: #6c757d; color: white;">
                <i class="ti-upload"></i> Upload More Questions
              </button>
            </div>
          </div>
        `;
      });
      
      container.innerHTML = html;
      
      // Initialize DataTables
      testData.sections.forEach(section => {
        if (sectionTables[section.id]) {
          sectionTables[section.id].destroy();
        }
        sectionTables[section.id] = $(`#questionsTable${section.id}`).DataTable({
          pageLength: 10,
          order: [[0, 'asc']],
          language: { emptyTable: "No questions in this section" }
        });
      });
    }

    function renderQuestions(questions) {
      let html = '';
      questions.forEach((q, index) => {
        const typeClass = `badge-${q.question_type}`;
        const typeLabel = q.question_type === 'mcq' ? 'MCQ' : 
                         q.question_type === 'truefalse' ? 'True/False' : 'Fill Up';
        
        let optionsHtml = '';
        if (q.question_type === 'mcq') {
          optionsHtml = `
            A: ${q.option_a || '-'}<br>
            B: ${q.option_b || '-'}<br>
            ${q.option_c ? 'C: ' + q.option_c + '<br>' : ''}
            ${q.option_d ? 'D: ' + q.option_d : ''}
          `;
        } else if (q.question_type === 'truefalse') {
          optionsHtml = `A: True<br>B: False`;
        } else {
          optionsHtml = '<em>Fill in the blank</em>';
        }
        
        html += `
          <tr>
            <td>${index + 1}</td>
            <td><span class="question-type-badge ${typeClass}">${typeLabel}</span></td>
            <td>${q.question_text}</td>
            <td><small>${optionsHtml}</small></td>
            <td><strong>${q.correct_answer}</strong></td>
            <td>
              <button class="btn-action btn-edit table-action-btn" onclick='editQuestion(${JSON.stringify(q)})'>
                <i class="ti-pencil"></i>
              </button>
              <button class="btn-action btn-delete table-action-btn" onclick="deleteQuestion(${q.id}, ${q.section_id})">
                <i class="ti-trash"></i>
              </button>
            </td>
          </tr>
        `;
      });
      return html || '<tr><td colspan="6" class="text-center">No questions available</td></tr>';
    }

    function editSection(sectionId) {
      // Fetch fresh data to ensure accuracy
      fetch(`${API_URL}?action=get_test_details&test_id=${testId}`)
        .then(response => response.json())
        .then(data => {
          if (!data.success) {
            Swal.fire('Error', 'Failed to load section data', 'error');
            return;
          }

          // Find section in fresh data
          const section = data.data.sections.find(s => s.id == sectionId);
          
          if (!section) {
            Swal.fire('Error', 'Section not found', 'error');
            return;
          }
          
          Swal.fire({
            title: 'Edit Section Details',
            html: `
              <div style="text-align: left;">
                <div class="form-group">
                  <label>Section Name</label>
                  <input type="text" id="sectionName" class="swal2-input" value="${section.section_name}" placeholder="Section Name">
                </div>
                <div class="form-group">
                  <label>Questions to Display</label>
                  <input type="number" id="questionsDisplay" class="swal2-input" value="${section.questions_to_display}" min="1" max="${section.total_questions}">
                  <small class="text-muted">Maximum: ${section.total_questions} questions available</small>
                </div>
                <div class="form-group">
                  <label>Marks per Question</label>
                  <input type="number" id="marksPerQuestion" class="swal2-input" value="${section.marks_per_question}" step="0.5" min="0">
                </div>
                <div class="form-group">
                  <label>Negative Marks</label>
                  <input type="number" id="negativeMarks" class="swal2-input" value="${section.negative_marks}" step="0.25" min="0">
                </div>
              </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Update Section',
            preConfirm: () => {
              const name = document.getElementById('sectionName').value.trim();
              const display = document.getElementById('questionsDisplay').value;
              const marks = document.getElementById('marksPerQuestion').value;
              const negative = document.getElementById('negativeMarks').value;
              
              if (!name) {
                Swal.showValidationMessage('Section name is required');
                return false;
              }
              
              if (parseInt(display) > section.total_questions) {
                Swal.showValidationMessage(`Cannot display more questions than available (${section.total_questions})`);
                return false;
              }
              
              return { 
                name, 
                display: parseInt(display), 
                marks: parseFloat(marks), 
                negative: parseFloat(negative) 
              };
            }
          }).then(result => {
            if (result.isConfirmed) {
              updateSection(sectionId, result.value);
            }
          });
        })
        .catch(error => {
          console.error('Error:', error);
          Swal.fire('Error', 'Failed to load section data: ' + error.message, 'error');
        });
    }

    function updateSection(sectionId, data) {
      const formData = new FormData();
      formData.append('action', 'update_section');
      formData.append('section_id', sectionId);
      formData.append('section_name', data.name);
      formData.append('questions_to_display', data.display);
      formData.append('marks_per_question', data.marks);
      formData.append('negative_marks', data.negative);
      
      Swal.fire({
        title: 'Updating Section...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
      });
      
      fetch(API_URL, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(result => {
          Swal.close();
          if (result.success) {
            Swal.fire('Updated!', 'Section details have been updated', 'success');
            loadTestData(); // Reload to show updated data
          } else {
            Swal.fire('Error', result.message, 'error');
          }
        })
        .catch(error => {
          Swal.close();
          Swal.fire('Error', 'An error occurred: ' + error.message, 'error');
        });
    }

    function deleteSection(sectionId) {
      Swal.fire({
        title: 'Delete Section?',
        text: 'This will delete all questions in this section permanently!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
      }).then(result => {
        if (result.isConfirmed) {
          const formData = new FormData();
          formData.append('action', 'delete_section');
          formData.append('section_id', sectionId);
          
          Swal.fire({
            title: 'Deleting...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
          });
          
          fetch(API_URL, { method: 'POST', body: formData })
            .then(response => response.json())
            .then(result => {
              Swal.close();
              if (result.success) {
                Swal.fire('Deleted!', 'Section has been deleted', 'success');
                loadTestData();
              } else {
                Swal.fire('Error', result.message, 'error');
              }
            })
            .catch(error => {
              Swal.close();
              Swal.fire('Error', 'An error occurred: ' + error.message, 'error');
            });
        }
      });
    }

    function downloadSection(sectionId) {
      // Reload test data first to ensure we have the latest
      fetch(`${API_URL}?action=get_test_details&test_id=${testId}`)
        .then(response => response.json())
        .then(data => {
          if (!data.success) {
            Swal.fire('Error', 'Failed to load test data', 'error');
            return;
          }

          // Find section in fresh data
          const section = data.data.sections.find(s => s.id == sectionId);
          
          if (!section || !section.questions) {
            Swal.fire('Error', 'Section or questions not found', 'error');
            return;
          }
          
          // Create Excel workbook
          const wb = XLSX.utils.book_new();
          
          // Prepare data
          const wsData = [
            ['Question Type', 'Question Text', 'Option A', 'Option B', 'Option C', 'Option D', 'Correct Answer']
          ];
          
          section.questions.forEach(q => {
            wsData.push([
              q.question_type || '',
              q.question_text || '',
              q.option_a || '',
              q.option_b || '',
              q.option_c || '',
              q.option_d || '',
              q.correct_answer || ''
            ]);
          });
          
          // Create worksheet
          const ws = XLSX.utils.aoa_to_sheet(wsData);
          
          // Set column widths
          ws['!cols'] = [
            { wch: 15 }, // Question Type
            { wch: 50 }, // Question Text
            { wch: 20 }, // Option A
            { wch: 20 }, // Option B
            { wch: 20 }, // Option C
            { wch: 20 }, // Option D
            { wch: 20 }  // Correct Answer
          ];
          
          // Add worksheet to workbook
          XLSX.utils.book_append_sheet(wb, ws, 'Questions');
          
          // Generate filename
          const fileName = `${section.section_name.replace(/[^a-z0-9]/gi, '_')}_Questions.xlsx`;
          
          // Save file
          XLSX.writeFile(wb, fileName);
          
          Swal.fire({
            icon: 'success',
            title: 'Downloaded!',
            text: `${section.questions.length} questions downloaded successfully`,
            timer: 2000,
            timerProgressBar: true
          });
        })
        .catch(error => {
          console.error('Error:', error);
          Swal.fire('Error', 'Failed to download section: ' + error.message, 'error');
        });
    }

    function editQuestion(question) {
      const isMultiChoice = question.question_type === 'mcq';
      const isTrueFalse = question.question_type === 'truefalse';
      const isFillUp = question.question_type === 'fillup';
      
      let optionsHtml = '';
      if (isMultiChoice) {
        optionsHtml = `
          <div class="form-group">
            <label>Option A</label>
            <input type="text" id="optionA" class="swal2-input" value="${question.option_a || ''}" placeholder="Option A" required>
          </div>
          <div class="form-group">
            <label>Option B</label>
            <input type="text" id="optionB" class="swal2-input" value="${question.option_b || ''}" placeholder="Option B" required>
          </div>
          <div class="form-group">
            <label>Option C (Optional)</label>
            <input type="text" id="optionC" class="swal2-input" value="${question.option_c || ''}" placeholder="Option C">
          </div>
          <div class="form-group">
            <label>Option D (Optional)</label>
            <input type="text" id="optionD" class="swal2-input" value="${question.option_d || ''}" placeholder="Option D">
          </div>
          <div class="form-group">
            <label>Correct Answer</label>
            <select id="correctAnswer" class="swal2-input" required>
              <option value="A" ${question.correct_answer === 'A' ? 'selected' : ''}>A</option>
              <option value="B" ${question.correct_answer === 'B' ? 'selected' : ''}>B</option>
              ${question.option_c ? `<option value="C" ${question.correct_answer === 'C' ? 'selected' : ''}>C</option>` : ''}
              ${question.option_d ? `<option value="D" ${question.correct_answer === 'D' ? 'selected' : ''}>D</option>` : ''}
            </select>
          </div>
        `;
      } else if (isTrueFalse) {
        optionsHtml = `
          <div class="form-group">
            <label>Correct Answer</label>
            <select id="correctAnswer" class="swal2-input" required>
              <option value="True" ${question.correct_answer === 'True' ? 'selected' : ''}>True</option>
              <option value="False" ${question.correct_answer === 'False' ? 'selected' : ''}>False</option>
            </select>
          </div>
        `;
      } else {
        optionsHtml = `
          <div class="form-group">
            <label>Correct Answer</label>
            <input type="text" id="correctAnswer" class="swal2-input" value="${question.correct_answer}" placeholder="Correct Answer" required>
          </div>
        `;
      }
      
      Swal.fire({
        title: 'Edit Question',
        html: `
          <div style="text-align: left;">
            <div class="form-group">
              <label>Question Type</label>
              <select id="questionType" class="swal2-input" disabled>
                <option value="mcq" ${isMultiChoice ? 'selected' : ''}>Multiple Choice (MCQ)</option>
                <option value="truefalse" ${isTrueFalse ? 'selected' : ''}>True/False</option>
                <option value="fillup" ${isFillUp ? 'selected' : ''}>Fill in the Blank</option>
              </select>
            </div>
            <div class="form-group">
              <label>Question Text</label>
              <textarea id="questionText" class="swal2-textarea" rows="3" placeholder="Question text" required>${question.question_text}</textarea>
            </div>
            ${optionsHtml}
          </div>
        `,
        width: '600px',
        showCancelButton: true,
        confirmButtonText: 'Update Question',
        preConfirm: () => {
          const questionText = document.getElementById('questionText').value.trim();
          let correctAnswer = document.getElementById('correctAnswer').value.trim();
          
          if (!questionText || !correctAnswer) {
            Swal.showValidationMessage('All required fields must be filled');
            return false;
          }
          
          // Standardize correct answer format based on question type
          if (isMultiChoice) {
            correctAnswer = correctAnswer.toUpperCase(); // Convert to uppercase (A, B, C, D)
            const validAnswers = ['A', 'B', 'C', 'D'];
            if (!validAnswers.includes(correctAnswer)) {
              Swal.showValidationMessage('For MCQ, correct answer must be A, B, C, or D');
              return false;
            }
          } else if (isTrueFalse) {
            correctAnswer = correctAnswer.charAt(0).toUpperCase() + correctAnswer.slice(1).toLowerCase(); // Capitalize first letter
            if (!['True', 'False'].includes(correctAnswer)) {
              Swal.showValidationMessage('For True/False, correct answer must be True or False');
              return false;
            }
          }
          // For fillup, keep the case as entered
          
          const data = {
            question_text: questionText,
            correct_answer: correctAnswer
          };
          
          if (isMultiChoice) {
            data.option_a = document.getElementById('optionA').value.trim();
            data.option_b = document.getElementById('optionB').value.trim();
            data.option_c = document.getElementById('optionC')?.value.trim() || '';
            data.option_d = document.getElementById('optionD')?.value.trim() || '';
            
            if (!data.option_a || !data.option_b) {
              Swal.showValidationMessage('Options A and B are required for MCQ');
              return false;
            }
          }
          
          return data;
        }
      }).then(result => {
        if (result.isConfirmed) {
          updateQuestion(question.id, result.value);
        }
      });
    }

    function updateQuestion(questionId, data) {
      const formData = new FormData();
      formData.append('action', 'update_question');
      formData.append('question_id', questionId);
      formData.append('question_text', data.question_text);
      formData.append('correct_answer', data.correct_answer);
      if (data.option_a !== undefined) formData.append('option_a', data.option_a);
      if (data.option_b !== undefined) formData.append('option_b', data.option_b);
      if (data.option_c !== undefined) formData.append('option_c', data.option_c);
      if (data.option_d !== undefined) formData.append('option_d', data.option_d);
      
      Swal.fire({
        title: 'Updating...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
      });
      
      fetch(API_URL, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(result => {
          Swal.close();
          if (result.success) {
            Swal.fire('Updated!', 'Question has been updated', 'success');
            loadTestData();
          } else {
            Swal.fire('Error', result.message, 'error');
          }
        })
        .catch(error => {
          Swal.close();
          Swal.fire('Error', 'An error occurred: ' + error.message, 'error');
        });
    }

    function deleteQuestion(questionId, sectionId) {
      Swal.fire({
        title: 'Delete Question?',
        text: 'This action cannot be undone!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
      }).then(result => {
        if (result.isConfirmed) {
          const formData = new FormData();
          formData.append('action', 'delete_question');
          formData.append('question_id', questionId);
          
          Swal.fire({
            title: 'Deleting...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
          });
          
          fetch(API_URL, { method: 'POST', body: formData })
            .then(response => response.json())
            .then(result => {
              Swal.close();
              if (result.success) {
                Swal.fire('Deleted!', 'Question has been deleted', 'success');
                loadTestData();
              } else {
                Swal.fire('Error', result.message, 'error');
              }
            })
            .catch(error => {
              Swal.close();
              Swal.fire('Error', 'An error occurred: ' + error.message, 'error');
            });
        }
      });
    }

    function addQuestionToSection(sectionId) {
      Swal.fire({
        title: 'Add New Question',
        html: `
          <div style="text-align: left;">
            <div class="form-group">
              <label>Question Type</label>
              <select id="questionType" class="swal2-input" onchange="toggleQuestionOptions()">
                <option value="mcq">Multiple Choice (MCQ)</option>
                <option value="truefalse">True/False</option>
                <option value="fillup">Fill in the Blank</option>
              </select>
            </div>
            <div class="form-group">
              <label>Question Text *</label>
              <textarea id="questionText" class="swal2-textarea" rows="3" placeholder="Enter question text" required></textarea>
            </div>
            <div id="optionsContainer">
              <!-- Options will be loaded here based on question type -->
            </div>
            <div id="correctAnswerContainer">
              <div class="form-group">
                <label>Correct Answer *</label>
                <input type="text" id="correctAnswer" class="swal2-input" placeholder="Enter correct answer" required>
              </div>
            </div>
            <div class="template-info mt-3">
              <h6><i class="ti-info-alt"></i> Format Guidelines:</h6>
              <ul>
                <li><strong>MCQ:</strong> Correct Answer must be A, B, C, or D</li>
                <li><strong>True/False:</strong> Correct Answer must be True or False</li>
                <li><strong>Fill in the Blank:</strong> Enter exact expected answer</li>
              </ul>
            </div>
          </div>
        `,
        width: '700px',
        showCancelButton: true,
        confirmButtonText: 'Add Question',
        didOpen: () => {
          // Initialize options based on default selection
          window.toggleQuestionOptions = function() {
            const type = document.getElementById('questionType').value;
            const container = document.getElementById('optionsContainer');
            const correctAnswerInput = document.getElementById('correctAnswer');
            
            if (type === 'mcq') {
              container.innerHTML = `
                <div class="form-group">
                  <label>Option A *</label>
                  <input type="text" id="optionA" class="swal2-input" placeholder="Option A" required>
                </div>
                <div class="form-group">
                  <label>Option B *</label>
                  <input type="text" id="optionB" class="swal2-input" placeholder="Option B" required>
                </div>
                <div class="form-group">
                  <label>Option C (Optional)</label>
                  <input type="text" id="optionC" class="swal2-input" placeholder="Option C">
                </div>
                <div class="form-group">
                  <label>Option D (Optional)</label>
                  <input type="text" id="optionD" class="swal2-input" placeholder="Option D">
                </div>
              `;
              correctAnswerInput.type = 'text';
              correctAnswerInput.placeholder = 'Enter A, B, C, or D';
            } else if (type === 'truefalse') {
              container.innerHTML = `
                <div class="form-group">
                  <p class="text-muted"><em>Options are automatically set as True/False</em></p>
                </div>
              `;
              correctAnswerInput.type = 'text';
              correctAnswerInput.placeholder = 'Enter True or False';
            } else {
              container.innerHTML = `
                <div class="form-group">
                  <p class="text-muted"><em>No options needed for fill-in-the-blank questions</em></p>
                </div>
              `;
              correctAnswerInput.type = 'text';
              correctAnswerInput.placeholder = 'Enter expected answer';
            }
          };
          
          // Call initially to set up the form
          toggleQuestionOptions();
        },
        preConfirm: () => {
          const type = document.getElementById('questionType').value;
          const questionText = document.getElementById('questionText').value.trim();
          let correctAnswer = document.getElementById('correctAnswer').value.trim();
          
          // Basic validation
          if (!questionText || !correctAnswer) {
            Swal.showValidationMessage('Question text and correct answer are required');
            return false;
          }
          
          // Standardize correct answer based on question type
          if (type === 'mcq') {
            // Convert to uppercase for MCQ
            correctAnswer = correctAnswer.toUpperCase();
            const validAnswers = ['A', 'B', 'C', 'D'];
            
            // Validate MCQ options
            const optionA = document.getElementById('optionA').value.trim();
            const optionB = document.getElementById('optionB').value.trim();
            
            if (!optionA || !optionB) {
              Swal.showValidationMessage('Options A and B are required for MCQ');
              return false;
            }
            
            if (!validAnswers.includes(correctAnswer)) {
              Swal.showValidationMessage('For MCQ, correct answer must be A, B, C, or D');
              return false;
            }
            
            const optionC = document.getElementById('optionC')?.value.trim() || '';
            const optionD = document.getElementById('optionD')?.value.trim() || '';
            
            // Check if selected answer has a valid option
            if ((correctAnswer === 'C' && !optionC) || (correctAnswer === 'D' && !optionD)) {
              Swal.showValidationMessage(`Option ${correctAnswer} is selected but not provided`);
              return false;
            }
            
            return {
              question_type: type,
              question_text: questionText,
              correct_answer: correctAnswer,
              option_a: optionA,
              option_b: optionB,
              option_c: optionC,
              option_d: optionD,
              section_id: sectionId
            };
            
          } else if (type === 'truefalse') {
            // Standardize True/False format
            correctAnswer = correctAnswer.charAt(0).toUpperCase() + correctAnswer.slice(1).toLowerCase();
            if (!['True', 'False'].includes(correctAnswer)) {
              Swal.showValidationMessage('For True/False, correct answer must be True or False');
              return false;
            }
            
            return {
              question_type: type,
              question_text: questionText,
              correct_answer: correctAnswer,
              option_a: 'True',
              option_b: 'False',
              option_c: '',
              option_d: '',
              section_id: sectionId
            };
            
          } else { // fillup
            // Keep case as entered for fill in the blank
            return {
              question_type: type,
              question_text: questionText,
              correct_answer: correctAnswer,
              option_a: '',
              option_b: '',
              option_c: '',
              option_d: '',
              section_id: sectionId
            };
          }
        }
      }).then(result => {
        if (result.isConfirmed) {
          addQuestion(result.value);
        }
      });
    }

    function addQuestion(data) {
      const formData = new FormData();
      formData.append('action', 'add_question');
      formData.append('section_id', data.section_id);
      formData.append('question_type', data.question_type);
      formData.append('question_text', data.question_text);
      formData.append('correct_answer', data.correct_answer);
      formData.append('option_a', data.option_a);
      formData.append('option_b', data.option_b);
      formData.append('option_c', data.option_c);
      formData.append('option_d', data.option_d);
      
      Swal.fire({
        title: 'Adding Question...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
      });
      
      fetch(API_URL, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(result => {
          Swal.close();
          if (result.success) {
            Swal.fire('Added!', 'Question has been added successfully', 'success');
            loadTestData();
          } else {
            Swal.fire('Error', result.message, 'error');
          }
        })
        .catch(error => {
          Swal.close();
          Swal.fire('Error', 'An error occurred: ' + error.message, 'error');
        });
    }

    function uploadMoreQuestions(sectionId) {
      Swal.fire({
        title: 'Upload More Questions',
        html: `
          <div style="text-align: left;">
            <div class="file-upload-area">
              <input type="file" id="sectionFileUpload" accept=".xlsx,.xls" style="display:none">
              <label for="sectionFileUpload" class="btn-primary-custom" style="cursor:pointer; display:inline-block; padding: 10px 20px;">
                <i class="ti-upload"></i> Select Excel File
              </label>
              <div id="uploadStatus" class="mt-2">
                <span class="text-muted">No file selected</span>
              </div>
            </div>
            <div class="template-info mt-3">
              <h6><i class="ti-info-alt"></i> File Format:</h6>
              <ul>
                <li>Use the same Excel template format</li>
                <li>Questions will be appended to existing ones</li>
                <li><strong>Note:</strong> Correct answers will be standardized (A/B/C/D for MCQ, True/False for True/False)</li>
              </ul>
            </div>
          </div>
        `,
        width: '600px',
        showCancelButton: true,
        confirmButtonText: 'Upload & Add Questions',
        didOpen: () => {
          document.getElementById('sectionFileUpload').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
              document.getElementById('uploadStatus').innerHTML = 
                `<span class="text-success"><i class="ti-check"></i> ${file.name} (${(file.size/1024).toFixed(1)} KB)</span>`;
            }
          });
        },
        preConfirm: () => {
          const file = document.getElementById('sectionFileUpload').files[0];
          
          if (!file) {
            Swal.showValidationMessage('Please select an Excel file');
            return false;
          }
          
          return { file };
        }
      }).then(result => {
        if (result.isConfirmed) {
          processQuestionsUpload(sectionId, result.value.file);
        }
      });
    }

    function processQuestionsUpload(sectionId, file) {
      const reader = new FileReader();
      reader.onload = function(e) {
        try {
          const arrayData = new Uint8Array(e.target.result);
          const workbook = XLSX.read(arrayData, {type: 'array'});
          const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
          const jsonData = XLSX.utils.sheet_to_json(firstSheet);
          
          if (jsonData.length === 0) {
            throw new Error('No data found in Excel file');
          }
          
          // Process and validate questions
          const validQuestions = [];
          const errors = [];
          
          jsonData.forEach((q, index) => {
            const rowNum = index + 2; // +2 because header is row 1
            
            if (!q['Question Type'] || !q['Question Text'] || !q['Correct Answer']) {
              errors.push(`Row ${rowNum}: Missing required fields (Question Type, Question Text, or Correct Answer)`);
              return;
            }
            
            const questionType = (q['Question Type'] || '').toLowerCase().trim();
            const questionText = (q['Question Text'] || '').trim();
            let correctAnswer = (q['Correct Answer'] || '').trim();
            const optionA = (q['Option A'] || '').trim();
            const optionB = (q['Option B'] || '').trim();
            const optionC = (q['Option C'] || '').trim();
            const optionD = (q['Option D'] || '').trim();
            
            // Standardize correct answer based on question type
            if (questionType === 'mcq') {
              correctAnswer = correctAnswer.toUpperCase();
              const validAnswers = ['A', 'B', 'C', 'D'];
              
              if (!validAnswers.includes(correctAnswer)) {
                errors.push(`Row ${rowNum}: MCQ correct answer must be A, B, C, or D (got "${correctAnswer}")`);
                return;
              }
              
              if (!optionA || !optionB) {
                errors.push(`Row ${rowNum}: MCQ requires at least Option A and Option B`);
                return;
              }
              
            } else if (questionType === 'truefalse') {
              correctAnswer = correctAnswer.charAt(0).toUpperCase() + correctAnswer.slice(1).toLowerCase();
              if (!['True', 'False'].includes(correctAnswer)) {
                errors.push(`Row ${rowNum}: True/False correct answer must be True or False (got "${correctAnswer}")`);
                return;
              }
            }
            // For fillup, keep case as entered
            
            validQuestions.push({
              'Question Type': questionType,
              'Question Text': questionText,
              'Correct Answer': correctAnswer,
              'Option A': optionA,
              'Option B': optionB,
              'Option C': optionC,
              'Option D': optionD
            });
          });
          
          if (errors.length > 0) {
            let errorMessage = `Found ${errors.length} error(s) in the Excel file:\n\n`;
            errorMessage += errors.slice(0, 5).join('\n'); // Show first 5 errors
            if (errors.length > 5) {
              errorMessage += `\n... and ${errors.length - 5} more errors`;
            }
            throw new Error(errorMessage);
          }
          
          if (validQuestions.length === 0) {
            throw new Error('No valid questions found after validation');
          }
          
          // Add questions to section
          const formData = new FormData();
          formData.append('action', 'add_questions_to_section');
          formData.append('section_id', sectionId);
          formData.append('questions', JSON.stringify(validQuestions));
          
          Swal.fire({
            title: 'Processing...',
            text: `Validating and adding ${validQuestions.length} questions`,
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
          });
          
          fetch(API_URL, { method: 'POST', body: formData })
            .then(response => response.json())
            .then(result => {
              Swal.close();
              if (result.success) {
                Swal.fire({
                  icon: 'success',
                  title: 'Questions Added!',
                  html: `<p>${validQuestions.length} questions added to section successfully.</p>
                         <p class="text-muted">Total questions in section: ${result.count || validQuestions.length}</p>`,
                  timer: 3000,
                  timerProgressBar: true
                });
                loadTestData();
              } else {
                Swal.fire('Error', result.message, 'error');
              }
            })
            .catch(error => {
              Swal.close();
              Swal.fire('Error', error.message, 'error');
            });
          
        } catch (error) {
          Swal.fire('Error', error.message, 'error');
        }
      };
      reader.readAsArrayBuffer(file);
    }

    function downloadEntireTest() {
      getTestDetails().then(testData => {
        const zip = new JSZip();
        
        // Add test info document
        let testInfo = `TEST INFORMATION\n`;
        testInfo += `===============================\n`;
        testInfo += `Test Name: ${testData.test_name}\n`;
        testInfo += `Test Code: ${testData.test_code}\n`;
        testInfo += `Total Sections: ${testData.sections.length}\n`;
        testInfo += `Created: ${testData.created_at}\n`;
        testInfo += `\nSECTION DETAILS:\n`;
        testInfo += `===============================\n`;
        
        testData.sections.forEach((section, idx) => {
          testInfo += `\nSECTION ${idx + 1}: ${section.section_name}\n`;
          testInfo += `----------------------------\n`;
          testInfo += `Total Questions: ${section.total_questions}\n`;
          testInfo += `Questions to Display: ${section.questions_to_display}\n`;
          testInfo += `Marks per Question: ${section.marks_per_question}\n`;
          testInfo += `Negative Marks: ${section.negative_marks}\n`;
        });
        
        zip.file('Test_Information.txt', testInfo);
        
        // Add each section as Excel file
        testData.sections.forEach((section, idx) => {
          const wb = XLSX.utils.book_new();
          const wsData = [
            ['Question Type', 'Question Text', 'Option A', 'Option B', 'Option C', 'Option D', 'Correct Answer']
          ];
          
          section.questions.forEach(q => {
            wsData.push([
              q.question_type,
              q.question_text,
              q.option_a || '',
              q.option_b || '',
              q.option_c || '',
              q.option_d || '',
              q.correct_answer
            ]);
          });
          
          const ws = XLSX.utils.aoa_to_sheet(wsData);
          ws['!cols'] = [{wch:15},{wch:50},{wch:20},{wch:20},{wch:20},{wch:20},{wch:20}];
          XLSX.utils.book_append_sheet(wb, ws, 'Questions');
          
          const wbout = XLSX.write(wb, {bookType:'xlsx', type:'array'});
          zip.file(`Section_${idx + 1}_${section.section_name.replace(/[^a-z0-9]/gi, '_')}.xlsx`, wbout);
        });
        
        // Add section configuration file
        let sectionConfig = `SECTION CONFIGURATION\n`;
        sectionConfig += `===============================\n\n`;
        testData.sections.forEach((section, idx) => {
          sectionConfig += `[Section ${idx + 1}]\n`;
          sectionConfig += `Name: ${section.section_name}\n`;
          sectionConfig += `Questions to Display: ${section.questions_to_display}\n`;
          sectionConfig += `Marks per Question: ${section.marks_per_question}\n`;
          sectionConfig += `Negative Marks: ${section.negative_marks}\n`;
          sectionConfig += `Total Questions: ${section.total_questions}\n\n`;
        });
        zip.file('Section_Configuration.txt', sectionConfig);
        
        zip.generateAsync({type:'blob'}).then(content => {
          saveAs(content, `${testData.test_name.replace(/[^a-z0-9]/gi, '_')}_Complete_Test.zip`);
          Swal.fire({
            icon: 'success',
            title: 'Downloaded!',
            text: 'Complete test downloaded as ZIP file',
            timer: 2000,
            timerProgressBar: true
          });
        });
      }).catch(error => {
        Swal.fire('Error', 'Failed to download test: ' + error.message, 'error');
      });
    }

    function showAddSectionModal() {
      Swal.fire({
        title: 'Add New Section',
        html: `
          <div style="text-align: left;">
            <div class="form-group">
              <label>Section Name *</label>
              <input type="text" id="newSectionName" class="swal2-input" placeholder="e.g., Mathematics, Science" required>
            </div>
            <div class="form-group">
              <label>Questions to Display *</label>
              <input type="number" id="newQuestionsDisplay" class="swal2-input" value="10" min="1" required>
            </div>
            <div class="form-group">
              <label>Marks per Question *</label>
              <input type="number" id="newMarksPerQuestion" class="swal2-input" value="1" step="0.5" min="0" required>
            </div>
            <div class="form-group">
              <label>Negative Marks</label>
              <input type="number" id="newNegativeMarks" class="swal2-input" value="0" step="0.25" min="0">
            </div>
            <div class="file-upload-area">
              <input type="file" id="newSectionFile" accept=".xlsx,.xls" style="display:none" required>
              <label for="newSectionFile" class="btn-primary-custom" style="cursor:pointer; display:inline-block; padding: 10px 20px;">
                <i class="ti-upload"></i> Upload Questions (Excel) *
              </label>
              <div id="uploadStatus" class="mt-2">
                <span class="text-muted">No file selected</span>
              </div>
            </div>
            <div class="template-info mt-3">
              <h6><i class="ti-info-alt"></i> Excel Template Format:</h6>
              <ul>
                <li><strong>MCQ:</strong> Question Type = "mcq", fill options A-D, Correct Answer = A/B/C/D</li>
                <li><strong>True/False:</strong> Question Type = "truefalse", Correct Answer = "True" or "False"</li>
                <li><strong>Fill in the Blank:</strong> Question Type = "fillup", Correct Answer = expected answer</li>
              </ul>
            </div>
          </div>
        `,
        width: '700px',
        showCancelButton: true,
        confirmButtonText: 'Add Section',
        didOpen: () => {
          document.getElementById('newSectionFile').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
              document.getElementById('uploadStatus').innerHTML = 
                `<span class="text-success"><i class="ti-check"></i> ${file.name} (${(file.size/1024).toFixed(1)} KB)</span>`;
            }
          });
        },
        preConfirm: () => {
          const name = document.getElementById('newSectionName').value.trim();
          const display = document.getElementById('newQuestionsDisplay').value;
          const marks = document.getElementById('newMarksPerQuestion').value;
          const negative = document.getElementById('newNegativeMarks').value;
          const file = document.getElementById('newSectionFile').files[0];
          
          if (!name) {
            Swal.showValidationMessage('Section name is required');
            return false;
          }
          
          if (!file) {
            Swal.showValidationMessage('Please upload questions Excel file');
            return false;
          }
          
          return { name, display, marks, negative, file };
        }
      }).then(result => {
        if (result.isConfirmed) {
          processNewSection(result.value);
        }
      });
    }

    function processNewSection(data) {
      const reader = new FileReader();
      reader.onload = function(e) {
        try {
          const arrayData = new Uint8Array(e.target.result);
          const workbook = XLSX.read(arrayData, {type: 'array'});
          const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
          const jsonData = XLSX.utils.sheet_to_json(firstSheet);
          
          if (jsonData.length === 0) {
            throw new Error('No data found in Excel file');
          }
          
          // Process and validate questions
          const validQuestions = [];
          const errors = [];
          
          jsonData.forEach((q, index) => {
            const rowNum = index + 2;
            
            if (!q['Question Type'] || !q['Question Text'] || !q['Correct Answer']) {
              errors.push(`Row ${rowNum}: Missing required fields`);
              return;
            }
            
            const questionType = (q['Question Type'] || '').toLowerCase().trim();
            const questionText = (q['Question Text'] || '').trim();
            let correctAnswer = (q['Correct Answer'] || '').trim();
            const optionA = (q['Option A'] || '').trim();
            const optionB = (q['Option B'] || '').trim();
            const optionC = (q['Option C'] || '').trim();
            const optionD = (q['Option D'] || '').trim();
            
            // Standardize correct answers
            if (questionType === 'mcq') {
              correctAnswer = correctAnswer.toUpperCase();
              const validAnswers = ['A', 'B', 'C', 'D'];
              
              if (!validAnswers.includes(correctAnswer)) {
                errors.push(`Row ${rowNum}: MCQ correct answer must be A, B, C, or D`);
                return;
              }
              
              if (!optionA || !optionB) {
                errors.push(`Row ${rowNum}: MCQ requires at least Option A and Option B`);
                return;
              }
              
            } else if (questionType === 'truefalse') {
              correctAnswer = correctAnswer.charAt(0).toUpperCase() + correctAnswer.slice(1).toLowerCase();
              if (!['True', 'False'].includes(correctAnswer)) {
                errors.push(`Row ${rowNum}: True/False correct answer must be True or False`);
                return;
              }
            }
            
            validQuestions.push({
              'Question Type': questionType,
              'Question Text': questionText,
              'Correct Answer': correctAnswer,
              'Option A': optionA,
              'Option B': optionB,
              'Option C': optionC,
              'Option D': optionD
            });
          });
          
          if (errors.length > 0) {
            let errorMessage = `Found ${errors.length} error(s):\n\n`;
            errorMessage += errors.slice(0, 5).join('\n');
            if (errors.length > 5) errorMessage += `\n... and ${errors.length - 5} more`;
            throw new Error(errorMessage);
          }
          
          if (validQuestions.length === 0) {
            throw new Error('No valid questions found');
          }
          
          // Add section with questions
          const formData = new FormData();
          formData.append('action', 'add_section');
          formData.append('test_id', testId);
          formData.append('section_name', data.name);
          formData.append('questions_to_display', data.display);
          formData.append('marks_per_question', data.marks);
          formData.append('negative_marks', data.negative);
          formData.append('questions', JSON.stringify(validQuestions));
          
          Swal.fire({
            title: 'Adding Section...',
            text: `Processing ${validQuestions.length} questions`,
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
          });
          
          fetch(API_URL, { method: 'POST', body: formData })
            .then(response => response.json())
            .then(result => {
              Swal.close();
              if (result.success) {
                Swal.fire({
                  icon: 'success',
                  title: 'Section Added!',
                  html: `<p>New section "<strong>${data.name}</strong>" added successfully.</p>
                         <p class="text-muted">${validQuestions.length} questions imported</p>`,
                  timer: 3000,
                  timerProgressBar: true
                });
                loadTestData();
              } else {
                Swal.fire('Error', result.message, 'error');
              }
            })
            .catch(error => {
              Swal.close();
              Swal.fire('Error', error.message, 'error');
            });
          
        } catch (error) {
          Swal.fire('Error', error.message, 'error');
        }
      };
      reader.readAsArrayBuffer(data.file);
    }
  </script>
</body>
</html>