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

echo "<h2>Password Hash Debug Test</h2>";
echo "<hr>";

// Get the hash from database
$stmt = $pdo->prepare("SELECT id, username, password FROM admin_account WHERE id = 1");
$stmt->execute();
$admin = $stmt->fetch();

echo "<h3>1. Current Hash in Database:</h3>";
echo "<p><strong>" . $admin['password'] . "</strong></p>";

echo "<h3>2. Test Password Verification:</h3>";

$testPassword = "admin";
$isMatch = password_verify($testPassword, $admin['password']);

echo "<p>Testing password: <strong>" . $testPassword . "</strong></p>";
echo "<p>Result: <strong style='color: " . ($isMatch ? 'green' : 'red') . "'>" . ($isMatch ? "✓ PASSWORD MATCHES" : "✗ PASSWORD DOES NOT MATCH") . "</strong></p>";

echo "<h3>3. Generate Fresh Hash for 'admin':</h3>";
$newHash = password_hash("admin", PASSWORD_BCRYPT);
echo "<p><strong>" . $newHash . "</strong></p>";

echo "<h3>4. Verify Fresh Hash Works:</h3>";
$freshTest = password_verify("admin", $newHash);
echo "<p>Result: <strong style='color: " . ($freshTest ? 'green' : 'red') . "'>" . ($freshTest ? "✓ WORKS" : "✗ FAILED") . "</strong></p>";

if (!$isMatch) {
    echo "<h3 style='color: red;'>5. FIX: Run this SQL to update with fresh hash:</h3>";
    echo "<pre style='background: #f0f0f0; padding: 10px;'>UPDATE admin_account SET password = '" . $newHash . "' WHERE id = 1;</pre>";
    echo "<p>Copy the hash above and run it in phpMyAdmin</p>";
}
?>