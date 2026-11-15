
<?php

// Start a secure PHP session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check whether a user is logged in
function isLoggedIn() {
    return isset($_SESSION["user_id"]);
}

// Get the logged-in user's ID
function currentUserId() {
    return $_SESSION["user_id"] ?? null;
}

// Get the logged-in user's role
function currentUserRole() {
    return $_SESSION["role"] ?? "Guest";
}

// Require login
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: ../login.php");
        exit;
    }
}

// Require administrator access
function requireAdmin() {
    requireLogin();

    if (strtolower(currentUserRole()) !== "admin") {
        http_response_code(403);
        exit("Access denied. Administrator permission required.");
    }
}

// Redirect already logged-in users
function redirectIfLoggedIn() {
    if (isLoggedIn()) {
        header("Location: dashboard.php");
        exit;
    }
}

?>
