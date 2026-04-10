<?php
/**
 * CIG Admin Dashboard - Logout Page
 * Handles user session termination
 */

session_start();

// Clear session data
$_SESSION = [];
session_destroy();

// Redirect to login page
header('Location: login.php');
exit();
?>
