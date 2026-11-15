
<?php
session_start();

/* Logout */
if (isset($_GET["logout"])) {

    $_SESSION = [];

    if (ini_get("session.use_cookies")) {

        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            "",
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();

    header("Location: ../login.php");
    exit;
}

/* Admin authentication */
if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "Admin"
) {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . "/../config/db.php";

$adminName = htmlspecialchars(
    $_SESSION["full_name"] ?? "Admin",
    ENT_QUOTES,
    "UTF-8"
);

/* Stock notifications */
$outOfStockCount = 0;
$lowStockCount = 0;

$stockResult = $conn->query(
    "SELECT
        SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) AS out_count,
        SUM(CASE WHEN stock > 0 AND stock <= 5 THEN 1 ELSE 0 END) AS low_count
     FROM books"
);

if ($stockResult) {

    $stockData = $stockResult->fetch_assoc();

    $outOfStockCount = (int)($stockData["out_count"] ?? 0);
    $lowStockCount = (int)($stockData["low_count"] ?? 0);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>BookHaven | Admin Dashboard</title>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    min-height: 100vh;
    background:
        radial-gradient(circle at top right, #18323b, transparent 45%),
        #080f1b;
    color: #f7f3ed;
    font-family: Arial, sans-serif;
}

header {
    min-height: 80px;
    padding: 18px 6%;
    background: #121f30;
    border-bottom: 1px solid #304052;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.logo {
    font-family: Georgia, serif;
    font-size: 28px;
    font-weight: bold;
}

.logo span {
    color: #eaa45c;
}

.header-right {
    display: flex;
    align-items: center;
    gap: 20px;
}

.admin-label {
    color: #a9b8cc;
    font-size: 14px;
}

.logout {
    padding: 11px 20px;
    border: 1px solid #eaa45c;
    border-radius: 7px;
    color: #eaa45c;
    text-decoration: none;
    transition: .2s;
}

.logout:hover {
    background: #eaa45c;
    color: #101722;
}

main {
    max-width: 1250px;
    margin: auto;
    padding: 45px 30px;
}

.welcome {
    margin-bottom: 30px;
}

.welcome h1 {
    font-family: Georgia, serif;
    font-size: 36px;
    margin-bottom: 12px;
}

.welcome h1 span {
    color: #eaa45c;
}

.welcome p {
    color: #a9b8cc;
    line-height: 1.6;
}

.section-title {
    color: #eaa45c;
    font-size: 13px;
    letter-spacing: 2px;
    margin: 28px 0 18px;
    text-transform: uppercase;
}

.cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 22px;
}

.card {
    padding: 30px;
    background: #121f30;
    border: 1px solid #304052;
    border-radius: 15px;
    transition: .25s;
}

.card:hover {
    transform: translateY(-5px);
    border-color: #eaa45c;
    box-shadow: 0 15px 35px rgba(0,0,0,.2);
}

.card-icon {
    font-size: 32px;
    margin-bottom: 20px;
}

.card h2 {
    color: #eaa45c;
    font-family: Georgia, serif;
    font-size: 23px;
    margin-bottom: 13px;
}

.card p {
    color: #a9b8cc;
    line-height: 1.7;
    font-size: 14px;
}

.card-link {
    display: inline-block;
    margin-top: 22px;
    padding: 12px 18px;
    border-radius: 7px;
    background: #eaa45c;
    color: #101722;
    text-decoration: none;
    font-weight: bold;
    font-size: 14px;
}

.card-link:hover {
    background: #f4b873;
}

.card-link.disabled {
    background: #26364a;
    color: #a9b8cc;
    cursor: not-allowed;
}

.notice {
    margin: 0 0 20px;
    padding: 18px 20px;
    border-radius: 11px;
    background: #49242c;
    border: 1px solid #a94a4a;
    color: #ffd0d0;
}

.notice.low {
    background: #493b20;
    border-color: #a87931;
    color: #ffe0a3;
}

.notice h2 {
    font-family: Georgia, serif;
    font-size: 20px;
    margin-bottom: 8px;
    color: inherit;
}

.notice p {
    line-height: 1.6;
}

.notice a {
    color: inherit;
    font-weight: bold;
}

footer {
    text-align: center;
    padding: 25px;
    color: #8293a9;
    border-top: 1px solid #26364a;
    font-size: 13px;
}

@media(max-width: 600px) {

    header {
        padding: 18px;
    }

    .logo {
        font-size: 22px;
    }

    .admin-label {
        display: none;
    }

    main {
        padding: 30px 18px;
    }

    .welcome h1 {
        font-size: 28px;
    }
}
</style>

</head>

<body>

<header>

    <div class="logo">
        Book<span>Haven.</span>

        <small style="font-family:Arial;font-size:13px;color:#a9b8cc;">
            Admin
        </small>
    </div>

    <div class="header-right">

        <span class="admin-label">
            Administrator: <?= $adminName ?>
        </span>

        <a href="dashboard.php?logout=1" class="logout">
            Logout
        </a>

    </div>

</header>

<main>

    <section class="welcome">

        <h1>
            Welcome back, <span><?= $adminName ?></span>!
        </h1>

        <p>
            Manage your bookstore, supervise Sub-Admins
            and monitor your BookHaven platform.
        </p>

    </section>

    <?php if ($outOfStockCount > 0): ?>

        <section class="notice">

            <h2>
                ⚠ Out-of-Stock Alert — <?= $outOfStockCount ?> book(s)
            </h2>

            <p>
                The following books currently have zero copies available.
                Please restock them.
            </p>

            <p>
                <a href="books.php">Open Book Management →</a>
            </p>

        </section>

    <?php else: ?>

        <section
            class="notice"
            style="background:#153c30;border-color:#28684f;color:#baf2d3;"
        >

            <h2>✓ Stock Status</h2>

            <p>
                There are currently no books with zero stock.
            </p>

        </section>

    <?php endif; ?>

    <?php if ($lowStockCount > 0): ?>

        <section class="notice low">

            <h2>
                Low-Stock Warning — <?= $lowStockCount ?> book(s)
            </h2>

            <p>
                These books have 1 to 5 copies remaining.
                Review the inventory and consider restocking.
            </p>

            <a href="books.php">Review inventory →</a>

        </section>

    <?php endif; ?>

    <p class="section-title">
        Administration panel
    </p>

    <div class="cards">

        <section class="card">

            <div class="card-icon">📚</div>

            <h2>Book Management</h2>

            <p>
                Add new books, update book information,
                manage prices and remove books from your collection.
                Check stock levels and search by category.
            </p>

            <a href="books.php" class="card-link">
                Manage Books →
            </a>

        </section>

        <section class="card">

            <div class="card-icon">👥</div>

            <h2>Sub-Admin Management</h2>

            <p>
                Create Sub-Admin accounts and manage
                the people responsible for maintaining your bookstore.
            </p>

            <a href="subadmins.php" class="card-link">
                Manage Sub-Admins →
            </a>

        </section>

        <section class="card">

            <div class="card-icon">🛍️</div>

            <h2>Customer Management</h2>

            <p>
                View registered customers and manage customer accounts.
            </p>

            <span class="card-link disabled">
                Coming Next
            </span>

        </section>

        <section class="card">

            <div class="card-icon">📦</div>

            <h2>Order Management</h2>

            <p>
                Monitor customer orders and manage bookstore order information.
            </p>

            <span class="card-link disabled">
                Coming Next
            </span>

        </section>

    </div>

</main>

<footer>
    © 2026 BookHaven. Admin Control Panel.
</footer>

</body>
</html>
