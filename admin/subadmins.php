
<?php
session_start();
require_once "../config/db.php";

// Only the main Admin can manage Sub-Admins.
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "Admin"
) {
    header("Location: ../login.php");
    exit;
}

$message = "";
$error = "";

// Create Sub-Admin
if ($_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["create_subadmin"])) {

    $name = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($name === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Enter a valid name and email address.";
    } elseif (strlen($password) < 8) {
        $error = "Password must contain at least 8 characters.";
    } else {
        $check = $conn->prepare(
            "SELECT id FROM users WHERE email = ? LIMIT 1"
        );
        $check->bind_param("s", $email);
        $check->execute();
        $existing = $check->get_result()->fetch_assoc();
        $check->close();

        if ($existing) {
            $error = "An account with this email already exists.";
        } else {
            $roleQuery = $conn->prepare(
                "SELECT id FROM roles WHERE role_name = 'Sub-Admin' LIMIT 1"
            );
            $roleQuery->execute();
            $role = $roleQuery->get_result()->fetch_assoc();
            $roleQuery->close();

            if (!$role) {
                $error = "Sub-Admin role was not found.";
            } else {
                $hashedPassword = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );
                $roleId = (int)$role["id"];

                $insert = $conn->prepare(
                    "INSERT INTO users
                    (role_id, full_name, email, password)
                    VALUES (?, ?, ?, ?)"
                );

                $insert->bind_param(
                    "isss",
                    $roleId,
                    $name,
                    $email,
                    $hashedPassword
                );

                if ($insert->execute()) {
                    $message = "Sub-Admin account created successfully.";
                } else {
                    $error = "Could not create the Sub-Admin account.";
                }

                $insert->close();
            }
        }
    }
}

// Delete Sub-Admin
if ($_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["delete_subadmin"])) {

    $deleteId = filter_input(
        INPUT_POST,
        "delete_subadmin",
        FILTER_VALIDATE_INT
    );

    if ($deleteId && $deleteId !== (int)$_SESSION["user_id"]) {
        $delete = $conn->prepare(
            "DELETE FROM users WHERE id = ? AND role_id = 3"
        );
        $delete->bind_param("i", $deleteId);

        if ($delete->execute() && $delete->affected_rows > 0) {
            $message = "Sub-Admin removed successfully.";
        } else {
            $error = "Sub-Admin could not be removed.";
        }

        $delete->close();
    } else {
        $error = "Invalid Sub-Admin account.";
    }
}

// Get all Sub-Admins
$result = $conn->query(
    "SELECT id, full_name, email, created_at
     FROM users
     WHERE role_id = 3
     ORDER BY id DESC"
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>BookHaven | Sub-Admin Management</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 35px;
    font-family: Arial, sans-serif;
    color: #f5f2ed;
    background: #09111e;
}

.container {
    max-width: 1100px;
    margin: auto;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 30px;
}

.logo {
    font-family: Georgia, serif;
    font-size: 28px;
    font-weight: bold;
}

.logo span {
    color: #eaa45c;
}

.back {
    color: #eaa45c;
    text-decoration: none;
}

.panel {
    padding: 28px;
    margin-bottom: 25px;
    border: 1px solid #293b50;
    border-radius: 15px;
    background: #121f30;
}

h2 {
    margin-top: 0;
    font-family: Georgia, serif;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 18px;
}

.field label {
    display: block;
    margin-bottom: 9px;
    font-size: 14px;
}

.field input {
    width: 100%;
    padding: 14px;
    border: 1px solid #35465a;
    border-radius: 8px;
    background: #0b1726;
    color: white;
}

button {
    padding: 13px 20px;
    border: none;
    border-radius: 8px;
    background: #eaa45c;
    color: #111827;
    font-weight: bold;
    cursor: pointer;
}

.create-button {
    margin-top: 20px;
}

.message, .error {
    padding: 13px;
    margin-bottom: 18px;
    border-radius: 8px;
}

.message {
    color: #a8efc0;
    background: #173c30;
}

.error {
    color: #ffaaaa;
    background: #45272c;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

th, td {
    padding: 15px 12px;
    text-align: left;
    border-bottom: 1px solid #304052;
}

th {
    color: #eaa45c;
}

.delete-button {
    padding: 9px 13px;
    background: #9e3945;
    color: white;
}

@media(max-width: 650px) {
    body {
        padding: 18px;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .panel {
        padding: 18px;
        overflow-x: auto;
    }

    table {
        min-width: 600px;
    }
}
</style>
</head>

<body>
<div class="container">

    <div class="header">
        <div class="logo">Book<span>Haven.</span></div>
        <a class="back" href="../index.php">← Back to Bookstore</a>
    </div>

    <section class="panel">
        <h2>Sub-Admin Management</h2>
        <p>Create accounts for people who help manage your bookstore.</p>

        <?php if ($message): ?>
            <div class="message">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-grid">

                <div class="field">
                    <label>Full Name</label>
                    <input
                        type="text"
                        name="full_name"
                        placeholder="Enter full name"
                        maxlength="100"
                        required
                    >
                </div>

                <div class="field">
                    <label>Email Address</label>
                    <input
                        type="email"
                        name="email"
                        placeholder="Enter email address"
                        maxlength="150"
                        required
                    >
                </div>

                <div class="field">
                    <label>Password</label>
                    <input
                        type="password"
                        name="password"
                        placeholder="Minimum 8 characters"
                        minlength="8"
                        required
                    >
                </div>

            </div>

            <button
                class="create-button"
                type="submit"
                name="create_subadmin"
                value="1"
            >
                + Create Sub-Admin
            </button>
        </form>
    </section>

    <section class="panel">
        <h2>Registered Sub-Admins</h2>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Created</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($admin = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= (int)$admin["id"] ?></td>
                        <td><?= htmlspecialchars($admin["full_name"]) ?></td>
                        <td><?= htmlspecialchars($admin["email"]) ?></td>
                        <td><?= htmlspecialchars($admin["created_at"]) ?></td>
                        <td>
                            <form method="POST"
                                  onsubmit="return confirm('Remove this Sub-Admin?');">
                                <button
                                    class="delete-button"
                                    type="submit"
                                    name="delete_subadmin"
                                    value="<?= (int)$admin["id"] ?>"
                                >
                                    Remove
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5">No Sub-Admins created yet.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </section>

</div>
</body>
</html>

