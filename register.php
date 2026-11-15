
<?php
session_start();
require_once __DIR__ . "/config/db.php";

$message = "";
$messageType = "";

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["csrf_token"] ?? "";

    if (!is_string($token) || !hash_equals($_SESSION["csrf_token"], $token)) {
        $message = "Invalid request. Please refresh and try again.";
        $messageType = "error";
    } else {

        $fullName = trim($_POST["full_name"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $password = $_POST["password"] ?? "";
        $phone = trim($_POST["phone"] ?? "");
        $address = trim($_POST["address"] ?? "");

        if ($fullName === "" || $email === "" || $password === "") {
            $message = "Please fill in all required fields.";
            $messageType = "error";

        } elseif (mb_strlen($fullName) > 100) {
            $message = "Full name must not exceed 100 characters.";
            $messageType = "error";

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
            $message = "Please enter a valid email address.";
            $messageType = "error";

        } elseif (strlen($password) < 8) {
            $message = "Password must contain at least 8 characters.";
            $messageType = "error";

        } elseif (strlen($phone) > 20) {
            $message = "Phone number is too long.";
            $messageType = "error";

        } else {

            // Check whether email already exists
            $check = $conn->prepare(
                "SELECT id FROM users WHERE email = ?"
            );

            $check->bind_param("s", $email);
            $check->execute();

            $existingUser = $check->get_result()->fetch_assoc();
            $check->close();

            if ($existingUser) {
                $message = "An account with this email already exists.";
                $messageType = "error";

            } else {

                // Find the Customer role from the roles table
                $roleQuery = $conn->prepare(
                    "SELECT id FROM roles WHERE role_name = ?"
                );

                $customerRole = "Customer";
                $roleQuery->bind_param("s", $customerRole);
                $roleQuery->execute();

                $role = $roleQuery->get_result()->fetch_assoc();
                $roleQuery->close();

                if (!$role) {
                    $message = "Customer role is missing in the database.";
                    $messageType = "error";

                } else {

                    // Hash the password before storing it
                    $hashedPassword = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    $roleId = (int)$role["id"];

                    $insert = $conn->prepare(
                        "INSERT INTO users
                        (role_id, full_name, email, password, phone, address)
                        VALUES (?, ?, ?, ?, ?, ?)"
                    );

                    $insert->bind_param(
                        "isssss",
                        $roleId,
                        $fullName,
                        $email,
                        $hashedPassword,
                        $phone,
                        $address
                    );

                    if ($insert->execute()) {
                        $insert->close();

                        header("Location: login.php?registered=1");
                        exit;

                    } else {
                        $message = "Registration failed. Please try again.";
                        $messageType = "error";
                        $insert->close();
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Join BookHaven | Create Account</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            background:
                radial-gradient(circle at 80% 20%, #1d3540, transparent 35%),
                #09111d;
            color: #f7f1e9;
            font-family: 'DM Sans', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 35px 18px;
        }

        .register-container {
            width: 100%;
            max-width: 510px;
            padding: 42px;
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 18px;
            background: rgba(18, 31, 46, .95);
            box-shadow: 0 25px 80px rgba(0,0,0,.35);
        }

        .brand {
            text-align: center;
            font-family: 'Playfair Display', serif;
            font-size: 30px;
            margin-bottom: 28px;
        }

        .brand span {
            color: #e7a45c;
        }

        h1 {
            font-family: 'Playfair Display', serif;
            font-size: 32px;
            text-align: center;
            margin-bottom: 9px;
        }

        .subtitle {
            text-align: center;
            color: #a8b5c5;
            font-size: 14px;
            margin-bottom: 28px;
        }

        .form-group {
            margin-bottom: 17px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-size: 13px;
            color: #d9e0e8;
        }

        input, textarea {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid #344456;
            border-radius: 8px;
            outline: none;
            background: #101e30;
            color: white;
            font-family: inherit;
            font-size: 14px;
        }

        input:focus, textarea:focus {
            border-color: #e7a45c;
            box-shadow: 0 0 0 3px rgba(231,164,92,.12);
        }

        textarea {
            resize: vertical;
            min-height: 75px;
        }

        .submit-button {
            width: 100%;
            padding: 15px;
            margin-top: 8px;
            border: none;
            border-radius: 8px;
            background: #e7a45c;
            color: #101827;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: .25s;
        }

        .submit-button:hover {
            background: #f4ba79;
            transform: translateY(-2px);
        }

        .message {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-size: 13px;
            text-align: center;
        }

        .error {
            color: #ffb8b8;
            background: rgba(180, 50, 50, .16);
            border: 1px solid rgba(255,100,100,.25);
        }

        .login-link {
            margin-top: 24px;
            text-align: center;
            color: #a8b5c5;
            font-size: 14px;
        }

        .login-link a {
            color: #e7a45c;
            text-decoration: none;
            font-weight: 600;
        }

        .back-link {
            display: block;
            margin-top: 20px;
            text-align: center;
            color: #a8b5c5;
            font-size: 13px;
            text-decoration: none;
        }

        .back-link:hover {
            color: #e7a45c;
        }

        @media (max-width: 500px) {
            .register-container {
                padding: 28px 22px;
            }
        }

    </style>

</head>

<body>

<div class="register-container">

    <div class="brand">Book<span>Haven.</span></div>

    <h1>Create Account</h1>

    <p class="subtitle">
        Begin your next reading journey with us.
    </p>

    <?php if ($message !== ""): ?>

        <div class="message <?= htmlspecialchars($messageType) ?>">
            <?= htmlspecialchars($message) ?>
        </div>

    <?php endif; ?>

    <form method="POST" action="register.php">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>"
        >

        <div class="form-group">

            <label for="full_name">Full Name *</label>

            <input
                type="text"
                id="full_name"
                name="full_name"
                maxlength="100"
                value="<?= htmlspecialchars($_POST["full_name"] ?? "") ?>"
                placeholder="Enter your full name"
                autocomplete="name"
                required
            >

        </div>

        <div class="form-group">

            <label for="email">Email Address *</label>

            <input
                type="email"
                id="email"
                name="email"
                maxlength="150"
                value="<?= htmlspecialchars($_POST["email"] ?? "") ?>"
                placeholder="you@example.com"
                autocomplete="email"
                required
            >

        </div>

        <div class="form-group">

            <label for="password">Password *</label>

            <input
                type="password"
                id="password"
                name="password"
                minlength="8"
                placeholder="Minimum 8 characters"
                autocomplete="new-password"
                required
            >

        </div>

        <div class="form-group">

            <label for="phone">Phone Number</label>

            <input
                type="tel"
                id="phone"
                name="phone"
                maxlength="20"
                value="<?= htmlspecialchars($_POST["phone"] ?? "") ?>"
                placeholder="Enter your phone number"
                autocomplete="tel"
            >

        </div>

        <div class="form-group">

            <label for="address">Address</label>

            <textarea
                id="address"
                name="address"
                placeholder="Enter your address"
            ><?= htmlspecialchars($_POST["address"] ?? "") ?></textarea>

        </div>

        <button type="submit" class="submit-button">
            Create My Account →
        </button>

    </form>

    <p class="login-link">
        Already have an account?
        <a href="login.php">Log in</a>
    </p>

    <!-- Back to the actual BookHaven homepage -->
    <a href="/apex-task4-bookstore/index.php" class="back-link">
        ← Back to BookHaven
    </a>

</div>

</body>
</html>
