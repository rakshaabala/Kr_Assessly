<?php
session_start();

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

// Function to generate CSRF token
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Function to verify CSRF token
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

$pdo = getDBConnection();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Invalid request. Please try again.";
    } else {
        $loginId  = sanitizeInput($_POST['login_id'] ?? '');
        $password = $_POST['password'] ?? '';
        $userType = sanitizeInput($_POST['user_type'] ?? '');

        if (empty($loginId) || empty($password) || empty($userType)) {
            $error = "All fields are required.";
        } elseif (!in_array($userType, ['student', 'faculty'])) {
            $error = "Invalid user type.";
        } else {
            /* ================= STUDENT ================= */
            if ($userType === 'student') {
                try {
                    $stmt = $pdo->prepare("
                        SELECT id, name_of_student, password
                        FROM student_account
                        WHERE register_number = ? AND status = 'active'
                        LIMIT 1
                    ");
                    $stmt->execute([$loginId]);
                    $student = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($student && password_verify($password, $student['password'])) {
                        // Regenerate session for security
                        session_regenerate_id(true);
                        $_SESSION['user_type'] = 'student';
                        $_SESSION['account_type'] = 'student';
                        $_SESSION['student_id'] = $student['id'];
                        $_SESSION['user_name'] = $student['name_of_student'];

                        header("Location: student/dashboard.php");
                        exit;
                    } else {
                        $error = "Invalid Student Credentials";
                    }
                } catch (PDOException $e) {
                    error_log("Student login error: " . $e->getMessage());
                    $error = "Login failed. Please try again.";
                }
            }

            /* ================= FACULTY + ADMIN ================= */
            elseif ($userType === 'faculty') {
                try {
                    /* ---- CHECK ADMIN TABLE FIRST ---- */
                    $stmt = $pdo->prepare("
                        SELECT id, username, password
                        FROM admin_account
                        WHERE username = ?
                        LIMIT 1
                    ");
                    $stmt->execute([$loginId]);
                    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($admin && password_verify($password, $admin['password'])) {
                        session_regenerate_id(true);
                        $_SESSION['user_type'] = 'faculty';
                        $_SESSION['account_type'] = 'admin';
                        $_SESSION['user_id'] = $admin['id'];
                        $_SESSION['user_name'] = $admin['username'];

                        header("Location: admin/dashboard.php");
                        exit;
                    }

                    /* ---- CHECK FACULTY TABLE ---- */
                    $stmt = $pdo->prepare("
                        SELECT id, name_of_faculty, password
                        FROM faculty_account
                        WHERE faculty_id = ? AND status = 'active'
                        LIMIT 1
                    ");
                    $stmt->execute([$loginId]);
                    $faculty = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($faculty && password_verify($password, $faculty['password'])) {
                        session_regenerate_id(true);
                        $_SESSION['user_type'] = 'faculty';
                        $_SESSION['account_type'] = 'faculty';
                        $_SESSION['user_id'] = $faculty['id'];
                        $_SESSION['user_name'] = $faculty['name_of_faculty'];

                        header("Location: faculty/dashboard.php");
                        exit;
                    }

                    $error = "Invalid Faculty / Admin Credentials";
                } catch (PDOException $e) {
                    error_log("Faculty/Admin login error: " . $e->getMessage());
                    $error = "Login failed. Please try again.";
                }
            }
        }
    }
}

// Generate CSRF token for the form
$csrfToken = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>KR ASSESSLY - Smart Assessment Portal</title>
     <link rel="stylesheet" href="../vendors/feather/feather.css">
  <link rel="stylesheet" href="../vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" href="../vendors/css/vendor.bundle.base.css">
  <link rel="stylesheet" href="../css/vertical-layout-light/style.css">
  <link rel="shortcut icon" href="images/favicon.jpg" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: linear-gradient(90deg, #594ba1ff 0%, #2575fc 100%);
            overflow-x: hidden;
            overflow-y: auto;
        }

        .main-container {
            flex: 1;
            display: grid;
            grid-template-columns: 50% 50%;
            width: 100%;
            min-height: calc(100vh - 60px);
        }

        /* Left Side - Login Form */
        .login-section {
            background: white;
            padding: 60px 80px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        /* Animated Background Circles - Left Section (3 corners) - Responsive Sizes */
        .bg-circle {
            position: absolute;
            border-radius: 50%;
            background: rgba(89, 75, 161, 0.06);
            pointer-events: none;
        }

        .circle1 {
            width: 280px;
            height: 280px;
            top: -80px;
            left: -80px;
            animation: floatCircle1 8s ease-in-out infinite;
        }

        .circle2 {
            width: 240px;
            height: 240px;
            bottom: -60px;
            left: -60px;
            animation: floatCircle2 10s ease-in-out infinite;
        }

        .circle3 {
            width: 220px;
            height: 220px;
            top: -50px;
            right: -50px;
            animation: floatCircle3 12s ease-in-out infinite;
        }

        @keyframes floatCircle1 {
            0%, 100% {
                transform: translate(0, 0);
            }
            50% {
                transform: translate(15px, -15px);
            }
        }

        @keyframes floatCircle2 {
            0%, 100% {
                transform: translate(0, 0);
            }
            50% {
                transform: translate(-15px, 15px);
            }
        }

        @keyframes floatCircle3 {
            0%, 100% {
                transform: translate(0, 0);
            }
            50% {
                transform: translate(20px, 20px);
            }
        }

        .login-content {
            width: 100%;
            max-width: 450px;
            position: relative;
            z-index: 5;
        }

        .assessly-logo {
            text-align: center;
            margin-bottom: 40px;
        }

        .assessly-logo img {
            height: 60px;
            width: 150px;
            display: inline-block;
        }

        .tabs {
            display: flex;
            gap: 12px;
            margin-bottom: 35px;
            background: #f5f7fa;
            padding: 6px;
            border-radius: 12px;
        }

        .tab {
            flex: 1;
            padding: 14px 20px;
            border: none;
            background: transparent;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            color: #666;
            border-radius: 10px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .tab.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            transform: translateY(-2px);
        }

        .tab:hover:not(.active) {
            background: #e8ebf0;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            padding: 16px 18px;
            border: 2px solid #e0e4eb;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: #fafbfc;
            font-family: inherit;
        }

        .form-group input:focus {
            outline: none;
            border-color: #594ba1;
            background: white;
            box-shadow: 0 0 0 4px rgba(89, 75, 161, 0.1);
        }

        .form-group input::placeholder {
            color: #aaa;
        }

        .login-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(90deg, #594ba1ff 0%, #2575fc 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 6px 20px rgba(89, 75, 161, 0.3);
            margin-top: 10px;
        }

        .login-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(89, 75, 161, 0.4);
        }

        .login-btn:active {
            transform: translateY(-1px);
        }

        .lost-password {
            text-align: center;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid #e0e4eb;
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }

        .lost-password strong {
            color: #594ba1;
            font-weight: 600;
        }

        /* Right Side - Decorative Panel */
        .decorative-section {
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px 40px 40px 40px;
        }

        /* Enhanced Diagonal Glow Effect Animation */
        .glow-effect {
            position: absolute;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.7) 0%, rgba(255, 255, 255, 0.4) 40%, transparent 70%);
            border-radius: 50%;
            filter: blur(80px);
            animation: diagonalGlow 5s ease-in-out infinite;
            pointer-events: none;
        }

        @keyframes diagonalGlow {
            0% {
                top: -200px;
                left: -200px;
                opacity: 0;
                transform: scale(0.8);
            }
            50% {
                opacity: 1;
                transform: scale(1.2);
            }
            100% {
                top: calc(100% + 100px);
                left: calc(100% + 100px);
                opacity: 0;
                transform: scale(0.8);
            }
        }

        /* MKCE Logo Container */
        .mkce-logo-container {
            background: white;
            padding: 10px 10px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            margin-bottom: 35px;
            z-index: 10;
            position: relative;
            animation: floatLogo 4s ease-in-out infinite;
        }

        .mkce-logo-container img {
            max-width: 320px;
            height: auto;
            display: block;
        }

        @keyframes floatLogo {
            0%, 100% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-15px);
            }
        }

        /* Welcome Text with Glow */
        .welcome-text {
            text-align: center;
            color: white;
            margin-bottom: 30px;
            z-index: 10;
            position: relative;
        }

        .welcome-text h2 {
            font-size: 38px;
            font-weight: 800;
            margin-bottom: 12px;
            line-height: 1.2;
            text-shadow: 
                0 0 20px rgba(255, 255, 255, 0.8),
                0 0 40px rgba(255, 255, 255, 0.6),
                0 0 60px rgba(255, 255, 255, 0.4),
                0 0 80px rgba(255, 255, 255, 0.2);
            animation: glowPulse 3s ease-in-out infinite;
        }

        @keyframes glowPulse {
            0%, 100% {
                text-shadow: 
                    0 0 20px rgba(255, 255, 255, 0.8),
                    0 0 40px rgba(255, 255, 255, 0.6),
                    0 0 60px rgba(255, 255, 255, 0.4);
            }
            50% {
                text-shadow: 
                    0 0 30px rgba(255, 255, 255, 1),
                    0 0 60px rgba(255, 255, 255, 0.8),
                    0 0 90px rgba(255, 255, 255, 0.6),
                    0 0 120px rgba(255, 255, 255, 0.4);
            }
        }

        .welcome-text p {
            font-size: 16px;
            opacity: 0.95;
            line-height: 1.6;
        }

        /* Card Carousel Container - Compact Side by Side */
        .carousel-container {
            position: relative;
            width: 100%;
            max-width: 100%;
            height: 220px;
            z-index: 5;
            display: flex;
            align-items: center;
            justify-content: center;
            perspective: 1200px;
        }

        .carousel-card {
            position: absolute;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            transition: all 0.7s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            display: flex;
            flex-direction: column;
        }

        /* Center card - Large rectangle 350px width × 140px height */
        .carousel-card.active {
            width: 350px;
            min-height: 190px;
            padding: 20px 25px;
            transform: translateX(0) translateY(0) scale(1);
            z-index: 10;
            opacity: 1;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
        }

        .carousel-card.active h3 {
            font-size: 24px;
            font-weight: 700;
            color: #594ba1;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .carousel-card.active .icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #594ba1ff 0%, #2575fc 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
            flex-shrink: 0;
        }

        .carousel-card.active p {
            font-size: 14px;
            color: #666;
            line-height: 1.5;
            margin-left: 46px;
        }

        /* Side cards - Small rectangles 200px width × 70px height, partially hidden */
        .carousel-card.prev,
        .carousel-card.next {
            width: 200px;
            min-height: 70px;
            padding: 12px 15px;
            z-index: 5;
            opacity: 0.85;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .carousel-card.prev {
            transform: translateX(-220px) translateY(0) scale(1);
        }

        .carousel-card.next {
            transform: translateX(220px) translateY(0) scale(1);
        }

        .carousel-card.prev h3,
        .carousel-card.next h3 {
            font-size: 13px;
            font-weight: 700;
            color: #594ba1;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .carousel-card.prev .icon,
        .carousel-card.next .icon {
            width: 24px;
            height: 24px;
            background: linear-gradient(135deg, #594ba1ff 0%, #2575fc 100%);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 13px;
            flex-shrink: 0;
        }

        .carousel-card.prev p,
        .carousel-card.next p {
            font-size: 10px;
            color: #666;
            line-height: 1.4;
            margin-left: 30px;
        }

        .carousel-card.hidden {
            transform: translateX(0) scale(0.7);
            z-index: 1;
            opacity: 0;
            pointer-events: none;
        }

        /* Footer */
        footer {
            background: linear-gradient(90deg, rgba(89, 75, 161, 0.3) 0%, rgba(37, 117, 252, 0.3) 100%);
            color: white;
            text-align: center;
            padding: 20px;
            font-size: 14px;
            grid-column: 1 / -1;
            backdrop-filter: blur(10px);
        }

        /* Responsive Design */
        @media (max-width: 1400px) {
            .circle1 {
                width: 240px;
                height: 240px;
                top: -70px;
                left: -70px;
            }

            .circle2 {
                width: 200px;
                height: 200px;
                bottom: -50px;
                left: -50px;
            }

            .circle3 {
                width: 180px;
                height: 180px;
                top: -40px;
                right: -40px;
            }

            .login-section {
                padding: 50px 60px;
            }

            .decorative-section {
                padding: 50px 35px 30px 35px;
            }

            .mkce-logo-container img {
                max-width: 240px;
            }

            .welcome-text h2 {
                font-size: 34px;
            }

            .carousel-card.active {
                width: 350px;
                min-height: 205px;
            }
            .carousel-card.active h3 {
                font-size: 20px;
            }

            .carousel-card.active p {
                font-size: 16px;
                margin-left: 44px;
            }

            .carousel-card.prev,
            .carousel-card.next {
                width: 190px;
                min-height: 68px;
            }

            .carousel-card.prev {
                transform: translateX(-210px) translateY(0) scale(1);
            }

            .carousel-card.next {
                transform: translateX(210px) translateY(0) scale(1);
            }
        }

        @media (max-width: 1200px) {
            .circle1 {
                width: 200px;
                height: 200px;
                top: -60px;
                left: -60px;
            }

            .circle2 {
                width: 170px;
                height: 170px;
                bottom: -40px;
                left: -40px;
            }

            .circle3 {
                width: 150px;
                height: 150px;
                top: -35px;
                right: -35px;
            }

            .mkce-logo-container img {
                max-width: 220px;
            }

            .welcome-text h2 {
                font-size: 30px;
            }

            .carousel-container {
                height: 240px;
            }

            .carousel-card.active {
                width: 330px;
                min-height: 200px;
                padding: 18px 22px;
            }

            .carousel-card.active h3 {
                font-size: 20px;
            }

            .carousel-card.active .icon {
                width: 34px;
                height: 34px;
                font-size: 17px;
            }

            .carousel-card.active p {
                font-size: 16px;
                margin-left: 44px;
            }

            .carousel-card.prev,
            .carousel-card.next {
                width: 180px;
                min-height: 65px;
                padding: 11px 14px;
            }

            .carousel-card.prev {
                transform: translateX(-195px) translateY(0) scale(1);
            }

            .carousel-card.next {
                transform: translateX(195px) translateY(0) scale(1);
            }
        }

        @media (max-width: 968px) {
            .circle1 {
                width: 160px;
                height: 160px;
                top: -50px;
                left: -50px;
            }

            .circle2 {
                width: 140px;
                height: 140px;
                bottom: -35px;
                left: -35px;
            }

            .circle3 {
                width: 120px;
                height: 120px;
                top: -30px;
                right: -30px;
            }

            .main-container {
                grid-template-columns: 1fr;
                grid-template-rows: auto auto;
                min-height: auto;
            }

            .decorative-section {
                order: 1;
                min-height: 500px;
                padding: 40px 30px;
            }

            .login-section {
                order: 2;
                padding: 50px 40px;
                min-height: auto;
            }

            .mkce-logo-container img {
                max-width: 200px;
            }

            .welcome-text h2 {
                font-size: 28px;
            }

            .welcome-text {
                margin-bottom: 25px;
            }

            .carousel-container {
                height: 200px;
            }

            .carousel-card.active {
                width: 320px;
                min-height: 180px;
                padding: 16px 20px;
            }

            .carousel-card.active h3 {
                font-size: 20px;
            }

            .carousel-card.active .icon {
                width: 32px;
                height: 32px;
                font-size: 16px;
            }

            .carousel-card.active p {
                font-size: 14px;
                margin-left: 42px;
            }

            .carousel-card.prev,
            .carousel-card.next {
                width: 220px;
                min-height: 60px;
                padding: 10px 12px;
            }

            .carousel-card.prev {
                transform: translateX(-170px) translateY(0) scale(1);
            }

            .carousel-card.next {
                transform: translateX(170px) translateY(0) scale(1);
            }

            footer {
                order: 3;
            }
        }

        @media (max-width: 768px) {
            .circle1 {
                width: 130px;
                height: 130px;
                top: -40px;
                left: -40px;
            }

            .circle2 {
                width: 110px;
                height: 110px;
                bottom: -30px;
                left: -30px;
            }

            .circle3 {
                width: 100px;
                height: 100px;
                top: -25px;
                right: -25px;
            }

            .welcome-text h2 {
                font-size: 24px;
            }

            .carousel-container {
                height: 250px;
            }

            .carousel-card.active {
                width: 320px;
                min-height: 180px;
                padding: 15px 18px;
            }

            .carousel-card.active h3 {
                font-size: 18px;
            }

            .carousel-card.active .icon {
                width: 30px;
                height: 30px;
                font-size: 15px;
            }

            .carousel-card.active p {
                font-size: 14px;
                margin-left: 40px;
            }

            .carousel-card.prev,
            .carousel-card.next {
                width: 220px;
                min-height: 65px;
                padding: 9px 11px;
            }

            .carousel-card.prev {
                transform: translateX(-150px) translateY(0) scale(1);
            }

            .carousel-card.next {
                transform: translateX(150px) translateY(0) scale(1);
            }

            .mkce-logo-container img {
                max-width: 180px;
            }
        }

        @media (max-width: 576px) {
            .circle1 {
                width: 100px;
                height: 100px;
                top: -30px;
                left: -30px;
            }

            .circle2 {
                width: 85px;
                height: 85px;
                bottom: -25px;
                left: -25px;
            }

            .circle3 {
                width: 75px;
                height: 75px;
                top: -20px;
                right: -20px;
            }

            .login-section {
                padding: 40px 25px;
            }

            .decorative-section {
                padding: 30px 20px;
                min-height: 450px;
            }

            .assessly-logo img {
                height: 50px !important;
                width: 125px !important;
            }

            .tab {
                font-size: 14px;
                padding: 12px 16px;
            }

            .form-group input {
                padding: 14px 16px;
            }

            .login-btn {
                padding: 14px;
            }

            .mkce-logo-container {
                padding: 20px 25px;
                margin-bottom: 25px;
            }

            .mkce-logo-container img {
                max-width: 160px;
            }

            .welcome-text h2 {
                font-size: 20px;
            }

            .welcome-text p {
                font-size: 14px;
            }

            .welcome-text {
                margin-bottom: 20px;
            }

            .carousel-container {
                height: 180px;
            }

            .carousel-card.active {
                width: 230px;
                min-height: 140px;
                padding: 14px 16px;
            }

            .carousel-card.active h3 {
                font-size: 14px;
                gap: 8px;
            }

            .carousel-card.active .icon {
                width: 28px;
                height: 28px;
                font-size: 14px;
            }

            .carousel-card.active p {
                font-size: 11px;
                margin-left: 36px;
                line-height: 1.4;
            }

            .carousel-card.prev,
            .carousel-card.next {
                width: 120px;
                min-height: 60px;
                padding: 8px 10px;
            }

            .carousel-card.prev {
                transform: translateX(-130px) translateY(0) scale(1);
            }

            .carousel-card.next {
                transform: translateX(130px) translateY(0) scale(1);
            }

            .carousel-card.prev h3,
            .carousel-card.next h3 {
                font-size: 8px;
                gap: 5px;
            }

            .carousel-card.prev .icon,
            .carousel-card.next .icon {
                width: 20px;
                height: 20px;
                font-size: 11px;
            }

            .carousel-card.prev p,
            .carousel-card.next p {
                font-size: 6px;
                margin-left: 25px;
                line-height: 1.3;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="login-section">
            <div class="bg-circle circle1"></div>
            <div class="bg-circle circle2"></div>
            <div class="bg-circle circle3"></div>

            <div class="login-content">
                <div class="assessly-logo">
                    <img src="images/full-logo-wo-bg.png" alt="KR ASSESSLY Logo" style="height: 60px; width:150px;" onerror="this.style.display='none'">
                </div>

                <div class="tabs">
                    <button class="tab active" onclick="switchTab('student', event)">Student</button>
                    <button class="tab" onclick="switchTab('faculty', event)">Faculty</button>
                </div>

                <?php if ($error): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <div id="studentForm">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <input type="hidden" name="user_type" value="student">
                        <div class="form-group">
                            <label for="studentId">Register Number</label>
                            <input type="text" id="studentId" name="login_id" placeholder="Enter your Register Number" required>
                        </div>

                        <div class="form-group">
                            <label for="studentPassword">Password</label>
                            <input type="password" id="studentPassword" name="password" placeholder="Enter your password" required>
                        </div>

                        <button type="submit" class="login-btn">Login</button>

                        <div class="lost-password">
                            Lost Password? &nbsp;
                            <strong>Contact Admin</strong>
                        </div>
                    </form>
                </div>

                <div id="facultyForm" style="display: none;">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <input type="hidden" name="user_type" value="faculty">
                        <div class="form-group">
                            <label for="facultyId">Faculty Username</label>
                            <input type="text" id="facultyId" name="login_id" placeholder="Enter your Username" required>
                        </div>

                        <div class="form-group">
                            <label for="facultyPassword">Password</label>
                            <input type="password" id="facultyPassword" name="password" placeholder="Enter your password" required>
                        </div>

                        <button type="submit" class="login-btn">Login</button>

                        <div class="lost-password">
                            Lost Password? &nbsp;
                            <strong>Contact Admin</strong>
                        </div>
                    </form>
                </div>
            </div>
        </div>

         <div class="decorative-section">
            <!-- Enhanced Diagonal Glow Effect -->
            <div class="glow-effect"></div>

            <!-- MKCE Logo Container -->
            <div class="mkce-logo-container">
                <img src="images/mkcenew.png" alt="MKCE Logo" onerror="this.style.display='none'">
            </div>

            <!-- Welcome Text with Glow Effect -->
            <div class="welcome-text">
                <h2>Welcome to KR ASSESSLY</h2>
                <p>Smart Assessment Platform Connecting Students & Faculty</p>
            </div>

            <!-- Card Carousel - Compact Side by Side -->
            <div class="carousel-container">
    <div class="carousel-card active">
        <h3>
            <span class="icon"><i class="fas fa-clipboard-list"></i></span>
            <span>Easy Test Hosting</span>
        </h3>
        <p>Faculty can effortlessly create and host tests with our user-friendly interface. Streamline your assessment process in minutes.</p>
    </div>
    <div class="carousel-card next">
        <h3>
            <span class="icon"><i class="fas fa-chart-bar"></i></span>
            <span>Instant Test Reports</span>
        </h3>
        <p>Students receive comprehensive test reports immediately. Track performance, identify strengths, and improve learning outcomes.</p>
    </div>
    <div class="carousel-card hidden">
        <h3>
            <span class="icon"><i class="fas fa-graduation-cap"></i></span>
            <span>Enhanced Learning</span>
        </h3>
        <p>Bridge the gap between teaching and learning. Real-time scores and analytics help both students and faculty excel together.</p>
    </div>
</div>
        </div>

        <footer>
            © 2025, Designed and Developed by <b>KR ASSESSLY TEAM</b> - All rights reserved.
        </footer>
    </div>

    <script>



         function switchTab(type) {
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            event.target.classList.add('active');

            const studentForm = document.getElementById('studentForm');
            const facultyForm = document.getElementById('facultyForm');

            if (type === 'student') {
                studentForm.style.display = 'block';
                facultyForm.style.display = 'none';
            } else {
                studentForm.style.display = 'none';
                facultyForm.style.display = 'block';
            }
        }

        function handleLogin(event, type) {
            event.preventDefault();
            alert(`${type.charAt(0).toUpperCase() + type.slice(1)} login functionality would be handled here with backend integration.`);
        }

        // Carousel Auto-rotation - Side by Side with Center Focus
        let currentCard = 0;
        const cards = document.querySelectorAll('.carousel-card');
        const totalCards = cards.length;

        function rotateCarousel() {
            cards.forEach((card, index) => {
                card.classList.remove('active', 'prev', 'next', 'hidden');
                
                const position = (index - currentCard + totalCards) % totalCards;
                
                if (position === 0) {
                    card.classList.add('active');
                } else if (position === totalCards - 1) {
                    card.classList.add('prev');
                } else if (position === 1) {
                    card.classList.add('next');
                } else {
                    card.classList.add('hidden');
                }
            });

            currentCard = (currentCard + 1) % totalCards;
        }

        // Initialize carousel
        rotateCarousel();

        // Auto-rotate every 4 seconds
        setInterval(rotateCarousel, 4000);

        // Optional: Click on side cards to bring them to center
        cards.forEach((card, index) => {
            card.addEventListener('click', () => {
                currentCard = index;
                rotateCarousel();
            });
        });
    </script>
</body>
</html>
