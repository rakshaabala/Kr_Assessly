<?php
// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

// Define access constant before including dbconn
define('DB_ACCESS', true);

// Include database connection
require_once '../dbconn.php';

// Check if connection was successful
if ($conn === null) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database connection failed. Please try again later.'
    ]);
    exit();
}
// Get action
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch($action) {
    case 'upload':
        handleUpload($conn);
        break;
    case 'fetch':
        handleFetch($conn);
        break;
    case 'get':
        handleGet($conn);
        break;
    case 'update':
        handleUpdate($conn);
        break;
    case 'delete':
        handleDelete($conn);
        break;
    case 'toggle':
        handleToggle($conn);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

$conn = null;

// Upload data from Excel
function handleUpload($conn) {
    $accountType = $_POST['accountType'] ?? '';
    $data = json_decode($_POST['data'] ?? '[]', true);

    if (empty($accountType) || empty($data)) {
        echo json_encode(['success' => false, 'message' => 'Invalid data received']);
        return;
    }

    $successCount = 0;
    $errorCount = 0;
    $errors = [];

    try {
        $conn->beginTransaction();

        if ($accountType === 'student') {
            $stmt = $conn->prepare("INSERT INTO student_account 
                (name_of_student, register_number, programme, department, batch, year, section, mobile_number, mail_id, password) 
                VALUES (:name, :register_number, :programme, :department, :batch, :year, :section, :mobile, :email, :password)");

            foreach ($data as $index => $record) {
                try {
                    $stmt->execute([
                        ':name' => $record['Name of Student'] ?? '',
                        ':register_number' => $record['Register Number'] ?? '',
                        ':programme' => $record['Programme'] ?? '',
                        ':department' => $record['Department'] ?? '',
                        ':batch' => $record['Batch'] ?? '',
                        ':year' => $record['Year'] ?? '',
                        ':section' => $record['Section'] ?? '',
                        ':mobile' => $record['Mobile Number'] ?? '',
                        ':email' => $record['Mail ID'] ?? '',
                        ':password' => password_hash($record['Password'] ?? 'default123', PASSWORD_DEFAULT)
                    ]);
                    $successCount++;
                } catch(PDOException $e) {
                    $errorCount++;
                    $errors[] = "Row " . ($index + 2) . ": " . $e->getMessage();
                }
            }
        } elseif ($accountType === 'faculty') {
            $stmt = $conn->prepare("INSERT INTO faculty_account 
                (name_of_faculty, faculty_id, department, mobile_number, mail_id, role, password) 
                VALUES (:name, :faculty_id, :department, :mobile, :email, :role, :password)");

            foreach ($data as $index => $record) {
                try {
                    $stmt->execute([
                        ':name' => $record['Name of Faculty'] ?? '',
                        ':faculty_id' => $record['Faculty ID'] ?? '',
                        ':department' => $record['Department'] ?? '',
                        ':mobile' => $record['Mobile Number'] ?? '',
                        ':email' => $record['Mail ID'] ?? '',
                        ':role' => $record['Role'] ?? '',
                        ':password' => password_hash($record['Password'] ?? 'default123', PASSWORD_DEFAULT)
                    ]);
                    $successCount++;
                } catch(PDOException $e) {
                    $errorCount++;
                    $errors[] = "Row " . ($index + 2) . ": " . $e->getMessage();
                }
            }
        }

        $conn->commit();

        $response = [
            'success' => true,
            'message' => "Successfully inserted $successCount records",
            'successCount' => $successCount,
            'errorCount' => $errorCount
        ];

        if ($errorCount > 0) {
            $response['errors'] = $errors;
        }

        echo json_encode($response);

    } catch(Exception $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// Fetch all data
function handleFetch($conn) {
    $type = $_GET['type'] ?? '';
    
    try {
        if ($type === 'student') {
            $stmt = $conn->prepare("SELECT * FROM student_account ORDER BY created_at DESC");
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $data]);
        } elseif ($type === 'faculty') {
            $stmt = $conn->prepare("SELECT * FROM faculty_account ORDER BY created_at DESC");
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $data]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid type']);
        }
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Fetch error: ' . $e->getMessage()]);
    }
}

// Get single record
function handleGet($conn) {
    $type = $_GET['type'] ?? '';
    $id = $_GET['id'] ?? '';
    
    if (empty($type) || empty($id)) {
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
        return;
    }
    
    try {
        if ($type === 'student') {
            $stmt = $conn->prepare("SELECT * FROM student_account WHERE id = :id");
        } elseif ($type === 'faculty') {
            $stmt = $conn->prepare("SELECT * FROM faculty_account WHERE id = :id");
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid type']);
            return;
        }
        
        $stmt->execute([':id' => $id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($data) {
            echo json_encode(['success' => true, 'data' => $data]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Record not found']);
        }
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Get error: ' . $e->getMessage()]);
    }
}

// Update record
function handleUpdate($conn) {
    $type = $_POST['type'] ?? '';
    $id = $_POST['id'] ?? '';
    $data = json_decode($_POST['data'] ?? '{}', true);
    
    if (empty($type) || empty($id) || empty($data)) {
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
        return;
    }
    
    try {
        if ($type === 'student') {
            $sql = "UPDATE student_account SET 
                    name_of_student = :name,
                    register_number = :register_number,
                    programme = :programme,
                    department = :department,
                    batch = :batch,
                    year = :year,
                    section = :section,
                    mobile_number = :mobile,
                    mail_id = :email";
            
            $params = [
                ':name' => $data['name_of_student'],
                ':register_number' => $data['register_number'],
                ':programme' => $data['programme'],
                ':department' => $data['department'],
                ':batch' => $data['batch'],
                ':year' => $data['year'],
                ':section' => $data['section'],
                ':mobile' => $data['mobile_number'],
                ':email' => $data['mail_id'],
                ':id' => $id
            ];
            
            if (!empty($data['password'])) {
                $sql .= ", password = :password";
                $params[':password'] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            
            $sql .= " WHERE id = :id";
            
        } elseif ($type === 'faculty') {
            $sql = "UPDATE faculty_account SET 
                    name_of_faculty = :name,
                    faculty_id = :faculty_id,
                    department = :department,
                    mobile_number = :mobile,
                    mail_id = :email,
                    role = :role";
            
            $params = [
                ':name' => $data['name_of_faculty'],
                ':faculty_id' => $data['faculty_id'],
                ':department' => $data['department'],
                ':mobile' => $data['mobile_number'],
                ':email' => $data['mail_id'],
                ':role' => $data['role'],
                ':id' => $id
            ];
            
            if (!empty($data['password'])) {
                $sql .= ", password = :password";
                $params[':password'] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            
            $sql .= " WHERE id = :id";
            
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid type']);
            return;
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        echo json_encode(['success' => true, 'message' => 'Record updated successfully']);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Update error: ' . $e->getMessage()]);
    }
}

// Delete record
function handleDelete($conn) {
    $type = $_POST['type'] ?? '';
    $id = $_POST['id'] ?? '';
    
    if (empty($type) || empty($id)) {
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
        return;
    }
    
    try {
        if ($type === 'student') {
            $stmt = $conn->prepare("DELETE FROM student_account WHERE id = :id");
        } elseif ($type === 'faculty') {
            $stmt = $conn->prepare("DELETE FROM faculty_account WHERE id = :id");
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid type']);
            return;
        }
        
        $stmt->execute([':id' => $id]);
        echo json_encode(['success' => true, 'message' => 'Record deleted successfully']);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Delete error: ' . $e->getMessage()]);
    }
}

// Toggle status
function handleToggle($conn) {
    $type = $_POST['type'] ?? '';
    $id = $_POST['id'] ?? '';
    $status = $_POST['status'] ?? '';
    
    if (empty($type) || empty($id) || empty($status)) {
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
        return;
    }
    
    try {
        if ($type === 'student') {
            $stmt = $conn->prepare("UPDATE student_account SET status = :status WHERE id = :id");
        } elseif ($type === 'faculty') {
            $stmt = $conn->prepare("UPDATE faculty_account SET status = :status WHERE id = :id");
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid type']);
            return;
        }
        
        $stmt->execute([':status' => $status, ':id' => $id]);
        echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Toggle error: ' . $e->getMessage()]);
    }
}
?>