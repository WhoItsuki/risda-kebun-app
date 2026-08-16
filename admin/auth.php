<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Function to enforce authentication on protected pages
function requireAdminLogin() {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: login.php');
        exit();
    }
}

// Helper to check if admin is authenticated
function isAdminLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}