
<?php
require_once __DIR__ . "/config/db.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $role = $_POST["role"] ?? "";

    $stmt = $conn->prepare(
        "SELECT u.id, u.password, u.role_id, r.role_name
         FROM users u
         LEFT JOIN roles r ON u.role_id = r.id
         WHERE u.email = ?
         LIMIT 1"
    );

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        $message = "EMAIL CHECK: Account not found.";
    } else {
        $passwordOK = password_verify($password, $user["password"]);
        $roleOK = ($role === $user["role_name"]);

        $message =
            "EMAIL CHECK: Account found.<br>" .
            "DATABASE ROLE: " .
            htmlspecialchars($user["role_name"] ?? "Missing role") .
            "<br>ROLE ID: " . (int)$user["role_id"] .
            "<br>PASSWORD CHECK: " .
            ($passwordOK ? "PASS" : "FAIL") .
            "<br>SELECTED ROLE CHECK: " .
            ($roleOK ? "PASS" : "FAIL");
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>BookHaven Login Diagnostic</title>
</head>
<body>
    <h2>BookHaven Login Diagnostic</h2>

    <form method="POST">
        <label>Email:</label><br>
        <input type="email" name="email" required><br><br>

        <label>Password:</label><br>
        <input type="password" name="password" required><br><br>

        <label>Account type:</label><br>
        <select name="role">
            <option value="Customer">Customer</option>
            <option value="Sub-Admin">Sub-Admin</option>
            <option value="Admin">Admin</option>
        </select><br><br>

        <button type="submit">Check Login</button>
    </form>

    <hr>

    <p><?php echo $message; ?></p>
</body>
</html>
