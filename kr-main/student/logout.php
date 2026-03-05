<?php
session_start();

// Destroy all session variables
session_destroy();

// Redirect to login page
header("Location: ../index.php");
exit();
?>