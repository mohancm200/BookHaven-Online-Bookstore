
<?php
require_once __DIR__ . "/config/db.php";

$email = "mohancm2022@gmail.com";
$newPassword = "BookHaven@123";

$hash = password_hash($newPassword, PASSWORD_DEFAULT);

$stmt = $conn->prepare(
    "UPDATE users SET password = ? WHERE email = ?"
);

$stmt->bind_param("ss", $hash, $email);
$stmt->execute();

if ($stmt->affected_rows >= 0) {
    $check = $conn->prepare(
        "SELECT password FROM users WHERE email = ?"
    );
    $check->bind_param("s", $email);
    $check->execute();

    $user = $check->get_result()->fetch_assoc();

    if ($user && password_verify($newPassword, $user["password"])) {
        echo "Admin password reset and verified successfully!";
    } else {
        echo "Password verification failed. Check the database.";
    }

    $check->close();
} else {
    echo "Database update failed.";
}

$stmt->close();
$conn->close();
?>
