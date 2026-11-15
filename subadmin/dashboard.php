
<?php
session_start();

/* Sub-Admin authentication */
if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "Sub-Admin"
) {
    header("Location: ../login.php");
    exit;
}

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

require_once __DIR__ . "/../config/db.php";

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

$subAdminName = e($_SESSION["full_name"] ?? "Sub-Admin");

/* Book statistics */
$totalBooks = 0;
$outOfStockCount = 0;
$lowStockCount = 0;

$stockResult = $conn->query(
    "SELECT
        COUNT(*) AS total_count,
        SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) AS out_count,
        SUM(CASE WHEN stock > 0 AND stock <= 5 THEN 1 ELSE 0 END) AS low_count
     FROM books"
);

if ($stockResult) {

    $stockData = $stockResult->fetch_assoc();

    $totalBooks = (int)($stockData["total_count"] ?? 0);
    $outOfStockCount = (int)($stockData["out_count"] ?? 0);
    $lowStockCount = (int)($stockData["low_count"] ?? 0);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>BookHaven | Sub-Admin Dashboard</title>

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

.staff-label {
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
    margin: 32px 0 18px;
    text-transform: uppercase;
}

.stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
}

.stat-card {
    padding: 25px;
    background: #121f30;
    border: 1px solid #304052;
    border-radius: 14px;
}

.stat-card h3 {
    color: #a9b8cc;
    font-size: 14px;
    font-weight: normal;
    margin-bottom: 15px;
}

.stat-card strong {
    font-family: Georgia, serif;
    font-size: 32px;
    color: #eaa45c;
}

.stat-card.out strong {
    color: #ff9999;
}

.stat-card.low strong {
    color: #ffd17e;
}

.notice {
    margin-top: 22px;
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
    font-size: 19px;
    margin-bottom: 8px;
}

.notice p {
    line-height: 1.6;
}

.notice a {
    display: inline-block;
    margin-top: 10px;
    color: inherit;
    font-weight: bold;
}

.cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
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

    .staff-label {
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
            Sub-Admin
        </small>
    </div>

    <div class="header-right">

        <span class="staff-label">
            Sub-Admin: <?= $subAdminName ?>
        </span>

        <a href="dashboard.php?logout=1" class="logout">
            Logout
        </a>

    </div>

</header>

<main>

    <section class="welcome">

        <h1>
            Welcome back, <span><?= $subAdminName ?></span>!
        </h1>

        <p>
            Manage the bookstore collection, monitor stock levels
            and keep book information up to date.
        </p>

    </section>

    <p class="section-title">Bookstore Overview</p>

    <div class="stats">

        <div class="stat-card">
            <h3>Total Books</h3>
            <strong><?= $totalBooks ?></strong>
        </div>

        <div class="stat-card out">
            <h3>Out-of-Stock Books</h3>
            <strong><?= $outOfStockCount ?></strong>
        </div>

        <div class="stat-card low">
            <h3>Low-Stock Books</h3>
            <strong><?= $lowStockCount ?></strong>
        </div>

    </div>

    <?php if ($outOfStockCount > 0): ?>

        <section class="notice">

            <h2>
                ⚠ Out-of-Stock Alert — <?= $outOfStockCount ?> book(s)
            </h2>

            <p>
                Some books currently have zero copies available.
                Please review the inventory and update their stock.
            </p>

            <a href="../admin/books.php?view=out">
                View Out-of-Stock Books →
            </a>

        </section>

    <?php endif; ?>

    <?php if ($lowStockCount > 0): ?>

        <section class="notice low">

            <h2>
                Low-Stock Warning — <?= $lowStockCount ?> book(s)
            </h2>

            <p>
                Some books have between 1 and 5 copies remaining.
                Consider restocking them.
            </p>

            <a href="../admin/books.php?view=all">
                Review Inventory →
            </a>

        </section>

    <?php endif; ?>

    <p class="section-title">Management Panel</p>

    <div class="cards">

        <section class="card">

            <div class="card-icon">📚</div>

            <h2>Book Management</h2>

            <p>
                Add new books, edit existing book information,
                update prices and manage stock quantities.
            </p>

            <a href="../admin/books.php" class="card-link">
                Manage Books →
            </a>

        </section>

        <section class="card">

            <div class="card-icon">📖</div>

            <h2>View All Books</h2>

            <p>
                Browse your bookstore collection, search books
                and filter books by category.
            </p>

            <a href="../admin/books.php?view=all" class="card-link">
                View Collection →
            </a>

        </section>

        <section class="card">

            <div class="card-icon">⚠️</div>

            <h2>Out-of-Stock Books</h2>

            <p>
                Check books with zero available copies
                and update their stock quantities.
            </p>

            <a href="../admin/books.php?view=out" class="card-link">
                Check Stock →
            </a>

        </section>

    </div>

</main>

<footer>
    © 2026 BookHaven. Sub-Admin Panel.
</footer>

</body>
</html>
