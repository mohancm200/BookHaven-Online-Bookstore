
<?php
session_start();

require_once __DIR__ . "/config/db.php";

$error = "";
$allowedRoles = ["Customer", "Sub-Admin", "Admin"];

$selectedRole = $_POST["role"] ?? "Customer";
$emailValue = $_POST["email"] ?? "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $selectedRole = $_POST["role"] ?? "";

    if (
        $email === "" ||
        $password === "" ||
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        $error = "Please enter a valid email and password.";

    } elseif (!in_array($selectedRole, $allowedRoles, true)) {
        $error = "Please select a valid account type.";

    } else {

        $stmt = $conn->prepare(
            "SELECT
                u.id,
                u.full_name,
                u.email,
                u.password,
                u.role_id,
                r.role_name
             FROM users u
             INNER JOIN roles r ON u.role_id = r.id
             WHERE u.email = ?
             LIMIT 1"
        );

        if (!$stmt) {
            $error = "Database query failed: " . $conn->error;

        } else {

            $stmt->bind_param("s", $email);
            $stmt->execute();

            $result = $stmt->get_result();
            $user = $result->fetch_assoc();

            $stmt->close();

            if (
                $user &&
                password_verify($password, $user["password"]) &&
                $user["role_name"] === $selectedRole
            ) {

                session_regenerate_id(true);

                $_SESSION["user_id"] = (int)$user["id"];
                $_SESSION["full_name"] = $user["full_name"];
                $_SESSION["email"] = $user["email"];
                $_SESSION["role"] = $user["role_name"];
                $_SESSION["role_id"] = (int)$user["role_id"];

                /* ADMIN LOGIN */
                if ($user["role_name"] === "Admin") {

                    header("Location: admin/dashboard.php");
                    exit;

                /* SUB-ADMIN LOGIN */
                } elseif ($user["role_name"] === "Sub-Admin") {

                    header("Location: subadmin/dashboard.php");
                    exit;

                /* CUSTOMER LOGIN */
                } elseif ($user["role_name"] === "Customer") {

                    header("Location: index.php?login=success");
                    exit;
                }

            } else {
                $error = "Incorrect email, password, or account type.";
            }
        }
    }
}

$emailValue = htmlspecialchars(
    $emailValue,
    ENT_QUOTES,
    "UTF-8"
);

if (!in_array($selectedRole, $allowedRoles, true)) {
    $selectedRole = "Customer";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>BookHaven | Login</title>

<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 25px;
    font-family: Arial, sans-serif;
    color: #f7f3ed;
    background:
        radial-gradient(circle at top right, #18323b, transparent 50%),
        #080f1b;
}

.login-card {
    width: 100%;
    max-width: 480px;
    padding: 42px;
    border: 1px solid #304052;
    border-radius: 20px;
    background: #121f30;
    box-shadow: 0 25px 70px rgba(0,0,0,.35);
}

.logo {
    text-align: center;
    font-family: Georgia, serif;
    font-size: 32px;
    font-weight: bold;
    margin-bottom: 30px;
}

.logo span {
    color: #eaa45c;
}

h1 {
    text-align: center;
    font-family: Georgia, serif;
    font-size: 30px;
    margin-bottom: 10px;
}

.subtitle {
    text-align: center;
    color: #a9b8cc;
    font-size: 14px;
    margin-bottom: 28px;
    line-height: 1.6;
}

.error {
    background: rgba(220,70,70,.13);
    border: 1px solid #a94a4a;
    color: #ffaaaa;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 18px;
    font-size: 14px;
}

.role-options {
    display: grid;
    grid-template-columns: repeat(3,1fr);
    gap: 10px;
    margin-bottom: 25px;
}

.role-option input {
    position: absolute;
    opacity: 0;
}

.role-option label {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    min-height: 100px;
    text-align: center;
    padding: 12px 6px;
    border: 1px solid #35465a;
    border-radius: 12px;
    color: #b7c4d5;
    cursor: pointer;
    transition: .25s;
    font-size: 12px;
    line-height: 1.5;
}

.role-option label strong {
    display: block;
    color: white;
    font-size: 13px;
    margin-bottom: 6px;
}

.role-option input:checked + label {
    border-color: #eaa45c;
    background: rgba(234,164,92,.12);
    color: #eaa45c;
}

.field {
    margin-bottom: 19px;
}

.field label {
    display: block;
    margin-bottom: 9px;
    font-size: 14px;
}

.field input {
    width: 100%;
    padding: 15px;
    border: 1px solid #35465a;
    border-radius: 9px;
    background: #0d1928;
    color: white;
    outline: none;
}

.field input:focus {
    border-color: #eaa45c;
}

.login-button {
    width: 100%;
    padding: 16px;
    border: none;
    border-radius: 9px;
    background: #eaa45c;
    color: #101722;
    font-weight: bold;
    font-size: 15px;
    cursor: pointer;
    margin-top: 8px;
    transition: .25s;
}

.login-button:hover {
    background: #f4b873;
    transform: translateY(-2px);
}

.links {
    text-align: center;
    margin-top: 25px;
    color: #a9b8cc;
    font-size: 14px;
    line-height: 2.2;
}

.links a {
    color: #eaa45c;
    text-decoration: none;
}

.links a:hover {
    text-decoration: underline;
}

@media(max-width:480px) {

    .login-card {
        padding: 28px 20px;
    }

    .role-options {
        gap: 6px;
    }

    .role-option label {
        font-size: 11px;
    }

    .role-option label strong {
        font-size: 12px;
    }
}
</style>

</head>

<body>

<div class="login-card">

    <div class="logo">Book<span>Haven.</span></div>

    <h1>Welcome Back</h1>

    <p class="subtitle">
        Choose your account type and continue.
    </p>

    <?php if ($error !== ""): ?>

        <div class="error">
            <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
        </div>

    <?php endif; ?>

    <form method="POST" action="login.php">

        <div class="role-options">

            <div class="role-option">

                <input
                    type="radio"
                    name="role"
                    id="customer"
                    value="Customer"
                    <?= $selectedRole === "Customer" ? "checked" : "" ?>
                    required
                >

                <label for="customer">
                    <strong>👤 Customer</strong>
                    Shop and explore books
                </label>

            </div>

            <div class="role-option">

                <input
                    type="radio"
                    name="role"
                    id="subadmin"
                    value="Sub-Admin"
                    <?= $selectedRole === "Sub-Admin" ? "checked" : "" ?>
                >

                <label for="subadmin">
                    <strong>📚 Sub-Admin</strong>
                    Manage bookstore
                </label>

            </div>

            <div class="role-option">

                <input
                    type="radio"
                    name="role"
                    id="admin"
                    value="Admin"
                    <?= $selectedRole === "Admin" ? "checked" : "" ?>
                >

                <label for="admin">
                    <strong>⚙️ Admin</strong>
                    Full control
                </label>

            </div>

        </div>

        <div class="field">

            <label for="email">Email Address</label>

            <input
                type="email"
                id="email"
                name="email"
                placeholder="Enter your email"
                value="<?= $emailValue ?>"
                autocomplete="username"
                required
            >

        </div>

        <div class="field">

            <label for="password">Password</label>

            <input
                type="password"
                id="password"
                name="password"
                placeholder="Enter your password"
                autocomplete="current-password"
                required
            >

        </div>

        <button type="submit" class="login-button">
            Login to BookHaven →
        </button>

    </form>

    <div class="links">

        Don't have an account?
        <a href="register.php">Create Account</a>

        <br>

        <a href="index.php">← Back to BookHaven</a>

    </div>

</div>

</body>
</html>
