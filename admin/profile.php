<?php session_start();

// Check if user is logged in as faculty (admin in this case)
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'faculty') {
    header("Location: ../index.php");
    exit();
} ?>
<?php
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

$message = '';
$messageType = '';
$adminUsername = 'admin';
$adminId = 1; // Hardcoded admin ID for now

// Fetch admin username
$stmt = $pdo->prepare("SELECT username FROM admin_account WHERE id = ?");
$stmt->execute([$adminId]);
$admin = $stmt->fetch();
if ($admin) {
    $adminUsername = $admin['username'];
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $message = "All fields are required.";
        $messageType = "danger";
    } elseif (strlen($newPassword) < 8) {
        $message = "New password must be at least 8 characters long.";
        $messageType = "danger";
    } elseif ($newPassword !== $confirmPassword) {
        $message = "New passwords do not match.";
        $messageType = "danger";
    } else {
        // Fetch current password hash
        $stmt = $pdo->prepare("SELECT password FROM admin_account WHERE id = ?");
        $stmt->execute([$adminId]);
        $adminData = $stmt->fetch();

        // Verify current password
        if (!password_verify($currentPassword, $adminData['password'])) {
            $message = "Current password is incorrect.";
            $messageType = "danger";
        } else {
            // Hash new password
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

            // Update password in database
            $updateStmt = $pdo->prepare("UPDATE admin_account SET password = ? WHERE id = ?");
            $updateResult = $updateStmt->execute([$hashedPassword, $adminId]);

            if ($updateResult) {
                $message = "Password changed successfully!";
                $messageType = "success";
            } else {
                $message = "An error occurred while updating the password.";
                $messageType = "danger";
            }
        }
    }
}
?>

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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .profile-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 30px;
            margin-bottom: 30px;
        }
        .form-section {
            margin-bottom: 40px;
        }
        .form-section h4 {
            margin-bottom: 20px;
            color: #333;
            font-weight: 600;
            border-bottom: 2px solid #594ba1;
            padding-bottom: 10px;
        }
        .form-group label {
            font-weight: 500;
            color: #555;
            margin-bottom: 8px;
        }
        .form-control {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px 12px;
            font-size: 14px;
        }
        .form-control:focus {
            border-color: #594ba1;
            box-shadow: 0 0 0 0.2rem rgba(89, 75, 161, 0.25);
        }
        .btn-change-password {
            background: linear-gradient(90deg, #594ba1 0%, #2575fc 100%);
            color: white;
            padding: 10px 30px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: opacity 0.3s;
        }
        .btn-change-password:hover {
            opacity: 0.9;
        }
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-left-color: #28a745;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-left-color: #f5c6cb;
        }
        .admin-info {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
        }
        .admin-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(90deg, #594ba1 0%, #2575fc 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 32px;
            margin-right: 20px;
        }
        .admin-details h3 {
            margin: 0;
            color: #333;
        }
        .admin-details p {
            margin: 5px 0 0 0;
            color: #666;
            font-size: 14px;
        }
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
                    <div class="row">
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="profile-card">
                                <div class="admin-info">
                                    <div class="admin-avatar">
                                        <i class="fa-solid fa-user"></i>
                                    </div>
                                    <div class="admin-details">
                                        <h3><?php echo htmlspecialchars($adminUsername); ?></h3>
                                        <p>Administrator Account</p>
                                        <p>Last updated: <?php echo date('F d, Y'); ?></p>
                                    </div>
                                </div>

                                <?php if ($message): ?>
                                    <div class="alert alert-<?php echo $messageType; ?>">
                                        <i class="fa-solid fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                                        <?php echo htmlspecialchars($message); ?>
                                    </div>
                                <?php endif; ?>

                                <div class="form-section">
                                    <h4><i class="fa-solid fa-lock"></i> Change Password</h4>
                                    <form method="POST" action="">
                                      <div class="row">
                                        <div class="form-group col-4">
                                            <label for="current_password">Current Password</label>
                                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                                            <small class="form-text text-muted">Enter your current password for verification</small>
                                        </div>

                                        <div class="form-group col-4">
                                            <label for="new_password">New Password</label>
                                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                                            <small class="form-text text-muted">Minimum 8 characters required</small>
                                        </div>

                                        <div class="form-group col-4">
                                            <label for="confirm_password">Confirm Password</label>
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                            <small class="form-text text-muted">Re-enter your new password</small>
                                        </div></div>

                                        <button type="submit" name="change_password" class="btn-change-password">
                                            <i class="fa-solid fa-key"></i> Change Password
                                        </button>
                                    </form>
                                </div>

                                
                            </div>
                        </div>

                    </div>
                </div>

                <footer class="footer" style="background: linear-gradient(90deg, #594ba1ff 0%, #2575fc 100%); color: white; padding: 30px 0;">
                    <div class="text-center">
                        <span style="font-family: 'Segoe UI', 'Roboto', 'Helvetica Neue', sans-serif; font-size: 1rem; color: white; letter-spacing: 0.3px;">
                            © 2025, Designed and Developed by
                            <strong style="font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: white; font-style: italic;">KR ASSESSLY TEAM</strong> - All rights reserved.
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
</body>
</html>