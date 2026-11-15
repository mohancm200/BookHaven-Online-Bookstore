
<?php
// ==========================================
// BOOKHAVEN CUSTOMER DASHBOARD
// ==========================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/config/db.php";

// ==========================================
// LOGIN CHECK
// ==========================================

if (empty($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];
$userName = $_SESSION["full_name"] ?? "Customer";

// Create CSRF token for wishlist removal
if (empty($_SESSION["wishlist_csrf"])) {
    $_SESSION["wishlist_csrf"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["wishlist_csrf"];

// ==========================================
// HELPER FUNCTIONS
// ==========================================

function dashboard_escape($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}

function dashboard_cover_path($filename) {

    $filename = trim((string)$filename);

    if ($filename === "") {
        return "";
    }

    // External image URL
    if (preg_match('~^https?://~i', $filename)) {
        return $filename;
    }

    // Already a project-relative path
    if (
        strpos($filename, "uploads/") === 0 ||
        strpos($filename, "assets/") === 0
    ) {
        return $filename;
    }

    // Bare filename stored in database
    return "uploads/book_covers/" . basename($filename);
}

// ==========================================
// FETCH CUSTOMER WISHLIST
// ==========================================

$wishlistBooks = [];
$errorMessage = "";

$sql = "
    SELECT
        w.id AS wishlist_id,
        w.book_id,
        w.created_at,
        b.title,
        b.author,
        b.price,
        b.cover_image,
        b.stock
    FROM wishlist AS w
    INNER JOIN books AS b
        ON b.id = w.book_id
    WHERE w.user_id = ?
    ORDER BY w.created_at DESC
";

$stmt = $conn->prepare($sql);

if ($stmt) {

    $stmt->bind_param("i", $userId);

    if ($stmt->execute()) {

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {

            $row["cover_url"] = dashboard_cover_path(
                $row["cover_image"]
            );

            $wishlistBooks[] = $row;
        }

        $result->free();

    } else {
        $errorMessage = "Unable to retrieve your wishlist: " .
                        $stmt->error;
    }

    $stmt->close();

} else {
    $errorMessage = "Wishlist query failed: " . $conn->error;
}

$wishlistCount = count($wishlistBooks);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>My Dashboard | BookHaven</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link rel="preconnect"
          href="https://fonts.gstatic.com"
          crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@500;600;700;800&display=swap"
          rel="stylesheet">

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #09111d;
            color: #f7f1e9;
            font-family: 'DM Sans', sans-serif;
            min-height: 100vh;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        /* NAVBAR */

        .navbar {
            min-height: 74px;
            padding: 0 7%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #080e18;
            border-bottom: 1px solid rgba(255,255,255,.1);
        }

        .logo {
            font-family: 'Playfair Display', serif;
            font-size: 27px;
            font-weight: 700;
        }

        .logo span {
            color: #e7a45c;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 24px;
            color: #aab7c6;
            font-size: 14px;
        }

        .nav-actions a:hover {
            color: #e7a45c;
        }

        .logout {
            padding: 11px 19px;
            border: 1px solid #e7a45c;
            border-radius: 6px;
            color: #e7a45c !important;
        }

        .logout:hover {
            background: #e7a45c;
            color: #101827 !important;
        }

        /* MAIN */

        .container {
            width: min(1150px, 90%);
            margin: 60px auto;
        }

        .eyebrow {
            color: #e7a45c;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 2px;
        }

        .welcome h1 {
            font-family: 'Playfair Display', serif;
            font-size: clamp(32px, 5vw, 45px);
            margin: 13px 0;
        }

        .welcome h1 span {
            color: #e7a45c;
        }

        .welcome p {
            color: #9eacbd;
            line-height: 1.7;
        }

        /* SUMMARY */

        .summary {
            margin: 35px 0 55px;
            padding: 24px;
            width: 230px;
            background: #132135;
            border: 1px solid #293b50;
            border-radius: 12px;
        }

        .summary strong {
            display: block;
            color: #e7a45c;
            font-size: 35px;
            margin-bottom: 5px;
        }

        .summary span {
            color: #aab7c6;
            font-size: 14px;
        }

        /* WISHLIST HEADER */

        .section-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 27px;
        }

        .section-heading h2 {
            font-family: 'Playfair Display', serif;
            font-size: 29px;
        }

        .section-heading h2 span {
            color: #e7a45c;
        }

        .back-link {
            color: #e7a45c;
            font-size: 14px;
        }

        /* BOOK GRID */

        .wishlist-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 24px;
        }

        .book-card {
            overflow: hidden;
            background: #111e30;
            border: 1px solid #293b50;
            border-radius: 10px;
            transition: transform .25s, border-color .25s;
        }

        .book-card:hover {
            transform: translateY(-5px);
            border-color: #e7a45c;
        }

        .book-cover {
            height: 270px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #1b2a3e;
            overflow: hidden;
        }

        .book-cover img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 10px;
        }

        .no-cover {
            padding: 20px;
            text-align: center;
            color: #e7a45c;
            font-family: 'Playfair Display', serif;
            font-size: 20px;
        }

        .book-info {
            padding: 18px;
        }

        .book-info h3 {
            font-size: 16px;
            line-height: 1.5;
            margin-bottom: 7px;
        }

        .author {
            color: #9eacbd;
            font-size: 13px;
            margin-bottom: 13px;
        }

        .price {
            color: #e7a45c;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .stock {
            color: #9eacbd;
            font-size: 12px;
            margin-bottom: 16px;
        }

        .remove-button {
            width: 100%;
            padding: 11px;
            border: 1px solid rgba(255,120,120,.45);
            border-radius: 6px;
            background: rgba(180,50,50,.12);
            color: #ffaaaa;
            cursor: pointer;
            font-family: inherit;
            font-weight: 600;
        }

        .remove-button:hover {
            background: rgba(180,50,50,.3);
        }

        .remove-button:disabled {
            opacity: .5;
            cursor: not-allowed;
        }

        /* EMPTY STATE */

        .empty-state {
            padding: 65px 20px;
            text-align: center;
            background: #111e30;
            border: 1px solid #293b50;
            border-radius: 12px;
        }

        .empty-heart {
            font-size: 48px;
            color: #e7a45c;
            margin-bottom: 15px;
        }

        .empty-state h3 {
            font-family: 'Playfair Display', serif;
            font-size: 25px;
            margin-bottom: 12px;
        }

        .empty-state p {
            color: #9eacbd;
            margin-bottom: 22px;
        }

        .explore-button {
            display: inline-block;
            padding: 13px 23px;
            border-radius: 6px;
            background: #e7a45c;
            color: #101827;
            font-weight: 700;
        }

        /* MESSAGES */

        .message {
            padding: 14px;
            margin-bottom: 20px;
            background: #392020;
            color: #ffaaaa;
            border-radius: 7px;
            overflow-wrap: anywhere;
        }

        .notice {
            display: none;
            padding: 13px;
            margin-bottom: 20px;
            border-radius: 7px;
            background: #183b2e;
            color: #a8f0c7;
        }

        /* RESPONSIVE */

        @media(max-width: 950px) {
            .wishlist-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media(max-width: 650px) {

            .wishlist-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 13px;
            }

            .navbar {
                padding: 15px 5%;
                gap: 15px;
                flex-wrap: wrap;
            }

            .nav-actions {
                gap: 12px;
                flex-wrap: wrap;
            }

            .book-cover {
                height: 200px;
            }

            .container {
                margin: 35px auto;
            }

            .section-heading {
                align-items: flex-start;
                flex-direction: column;
            }
        }

        @media(max-width: 380px) {
            .wishlist-grid {
                grid-template-columns: 1fr;
            }
        }

    </style>

</head>

<body>

<!-- ==========================================
     NAVIGATION
========================================== -->

<header class="navbar">

    <a href="index.php" class="logo">
        BOOK<span>HAVEN.</span>
    </a>

    <div class="nav-actions">

        <span>
            Hi, <?= dashboard_escape($userName) ?>
        </span>

        <a href="index.php">
            Home
        </a>

        <a href="books.php">
            Discover Books
        </a>

        <a href="logout.php" class="logout">
            Logout ↗
        </a>

    </div>

</header>


<!-- ==========================================
     CUSTOMER DASHBOARD
========================================== -->

<main class="container">

    <section class="welcome">

        <span class="eyebrow">
            YOUR PERSONAL READING SPACE
        </span>

        <h1>
            Welcome back,
            <span><?= dashboard_escape($userName) ?>.</span>
        </h1>

        <p>
            Your favourite books, all in one place.
            Keep track of the stories you want to read next.
        </p>

    </section>


    <!-- WISHLIST COUNT -->

    <div class="summary">

        <strong id="wishlistTotal">
            <?= $wishlistCount ?>
        </strong>

        <span>
            Books in your wishlist
        </span>

    </div>


    <!-- WISHLIST SECTION -->

    <section>

        <div class="section-heading">

            <h2>
                My <span>Wishlist</span> ♥
            </h2>

            <a href="books.php" class="back-link">
                Explore more books ↗
            </a>

        </div>

        <div id="dashboardNotice" class="notice"></div>


        <?php if ($errorMessage !== ""): ?>

            <div class="message">
                <?= dashboard_escape($errorMessage) ?>
            </div>

        <?php elseif ($wishlistCount > 0): ?>

            <div class="wishlist-grid" id="wishlistGrid">

                <?php foreach ($wishlistBooks as $book): ?>

                    <article
                        class="book-card"
                        data-book-id="<?= (int)$book["book_id"] ?>"
                    >

                        <div class="book-cover">

                            <?php if (!empty($book["cover_url"])): ?>

                                <img
                                    src="<?= dashboard_escape($book["cover_url"]) ?>"
                                    alt="<?= dashboard_escape($book["title"]) ?>"
                                    loading="lazy"
                                    onerror="this.style.display='none'; this.nextElementSibling.style.display='block';"
                                >

                                <div class="no-cover" style="display:none;">
                                    <?= dashboard_escape($book["title"]) ?>
                                </div>

                            <?php else: ?>

                                <div class="no-cover">
                                    <?= dashboard_escape($book["title"]) ?>
                                </div>

                            <?php endif; ?>

                        </div>


                        <div class="book-info">

                            <h3>
                                <?= dashboard_escape($book["title"]) ?>
                            </h3>

                            <p class="author">
                                By <?= dashboard_escape($book["author"]) ?>
                            </p>

                            <p class="price">
                                ₹<?= number_format((float)$book["price"], 2) ?>
                            </p>

                            <p class="stock">

                                <?php if ((int)$book["stock"] > 0): ?>

                                    <?= (int)$book["stock"] ?> copies available

                                <?php else: ?>

                                    Currently out of stock

                                <?php endif; ?>

                            </p>

                            <button
                                type="button"
                                class="remove-button"
                                data-remove-wishlist="<?= (int)$book["book_id"] ?>"
                            >
                                Remove from Wishlist
                            </button>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="empty-state" id="emptyWishlist">

                <div class="empty-heart">
                    ♡
                </div>

                <h3>
                    Your wishlist is waiting.
                </h3>

                <p>
                    You haven't saved any books yet.
                    Explore the library and add your favourites.
                </p>

                <a href="books.php" class="explore-button">
                    Explore Books ↗
                </a>

            </div>

        <?php endif; ?>

    </section>

</main>


<!-- ==========================================
     WISHLIST REMOVE FUNCTION
========================================== -->

<script>

const wishlistCsrfToken = <?= json_encode($csrfToken) ?>;

document.addEventListener("click", async function(event) {

    const button = event.target.closest("[data-remove-wishlist]");

    if (!button) return;

    const bookId = Number(button.dataset.removeWishlist);

    if (!confirm("Remove this book from your wishlist?")) {
        return;
    }

    button.disabled = true;
    button.textContent = "Removing...";

    try {

        const response = await fetch("api/wishlist.php", {

            method: "POST",

            credentials: "same-origin",

            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json",
                "X-CSRF-Token": wishlistCsrfToken
            },

            body: JSON.stringify({
                action: "remove",
                book_id: bookId
            })

        });

        const result = await response.json();

        if (!response.ok || result.success !== true) {
            throw new Error(
                result.message || "Unable to remove book."
            );
        }

        const card = button.closest(".book-card");

        if (card) {
            card.remove();
        }

        const remaining = document.querySelectorAll(".book-card").length;

        document.getElementById("wishlistTotal").textContent = remaining;

        if (remaining === 0) {

            document.getElementById("wishlistGrid")?.remove();

            const section = document.querySelector(".section-heading");

            const empty = document.createElement("div");

            empty.className = "empty-state";

            empty.innerHTML = `
                <div class="empty-heart">♡</div>
                <h3>Your wishlist is waiting.</h3>
                <p>You haven't saved any books yet. Explore the library and add your favourites.</p>
                <a href="books.php" class="explore-button">Explore Books ↗</a>
            `;

            section.after(empty);
        }

        const notice = document.getElementById("dashboardNotice");

        notice.textContent = "Book removed from your wishlist.";
        notice.style.display = "block";

    } catch (error) {

        alert(error.message);

        button.disabled = false;
        button.textContent = "Remove from Wishlist";

    }

});

</script>

</body>
</html>
