
<?php
// ==========================================
// BOOKHAVEN HOMEPAGE
// ==========================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/config/db.php";

// ==========================================
// LOGIN SESSION
// ==========================================

$isLoggedIn = !empty($_SESSION["user_id"]);

$userName = $_SESSION["full_name"] ?? "Customer";
$userRole = $_SESSION["role"] ?? "";

$normalizedRole = strtolower(trim($userRole));

$dashboardLink = "";

if ($normalizedRole === "admin") {
    $dashboardLink = "admin/dashboard.php";
} elseif ($normalizedRole === "sub-admin") {
    $dashboardLink = "subadmin/dashboard.php";
} elseif ($normalizedRole === "customer") {
    $dashboardLink = "customer_dashboard.php";
}

// Escape output safely.
function bookhaven_escape($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}

// ==========================================
// LOAD CATEGORIES
// ==========================================

$categories = $conn->query(
    "SELECT id, category_name
     FROM categories
     ORDER BY category_name"
);

// ==========================================
// LOAD BOOKS
// Include stock for cart functionality.
// ==========================================

$books = $conn->query(
    "SELECT id, title, author, price, cover_image, stock
     FROM books
     ORDER BY id DESC
     LIMIT 8"
);

// ==========================================
// CATEGORY ICONS
// ==========================================

$categoryIcons = [
    "Fiction" => "✦",
    "Technology" => "⌘",
    "Science" => "⚛",
    "History" => "◈",
    "Fantasy" => "✧",
    "Biography" => "❖",
    "Education" => "⌑",
    "Romance" => "♡"
];

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>BookHaven | Immersive Online Bookstore</title>

    <link rel="stylesheet" href="assets/css/style.css">

    <script src="assets/js/app.js" defer></script>

</head>

<body>

<div class="scroll-progress" id="scrollProgress"></div>


<!-- ==========================================
     NAVIGATION
========================================== -->

<header class="navbar" id="navbar">

    <a href="index.php" class="logo">
        BOOK<span>HAVEN</span><small>.</small>
    </a>


    <nav class="nav-links" id="navLinks">

        <a href="#home" class="active">Discover</a>

        <a href="#categories">Collections</a>

        <a href="#books">Books</a>

        <a href="#about">Our Story</a>

    </nav>


    <div class="nav-actions">

        <!-- WISHLIST BUTTON -->

        <button
            class="icon-button"
            id="wishlistButton"
            type="button"
            aria-label="Open wishlist"
        >
            ♡

            <span class="count-badge" id="wishlistCount">
                0
            </span>

        </button>


        <!-- CART BUTTON -->

        <button
            class="icon-button"
            id="cartButton"
            type="button"
            aria-label="Open cart"
        >
            🛒

            <span class="count-badge" id="cartCount">
                0
            </span>

        </button>


        <!-- LOGGED-IN CUSTOMER / ADMIN / SUB-ADMIN -->

        <?php if ($isLoggedIn): ?>

            <span class="nav-user">
                Hi, <?= bookhaven_escape($userName) ?>
            </span>


            <?php if ($dashboardLink !== ""): ?>

                <a
                    href="<?= bookhaven_escape($dashboardLink) ?>"
                    class="nav-login"
                >
                    <?= $normalizedRole === "customer"
                        ? "My Dashboard"
                        : "Dashboard" ?>
                </a>

            <?php endif; ?>


            <a href="logout.php" class="nav-signup">
                Logout ↗
            </a>


        <!-- GUEST NAVIGATION -->

        <?php else: ?>

            <a href="login.php" class="nav-login">
                Login
            </a>

            <a href="register.php" class="nav-signup">
                Join Us ↗
            </a>

        <?php endif; ?>

    </div>


    <button
        class="menu-toggle"
        id="menuToggle"
        type="button"
        aria-label="Toggle navigation"
    >
        ☰
    </button>

</header>


<main>


<!-- ==========================================
     HERO SECTION
========================================== -->

<section class="hero" id="home">

    <div class="hero-glow glow-one"></div>

    <div class="hero-glow glow-two"></div>


    <div class="hero-content reveal">

        <div class="hero-tag">

            <span class="status-dot"></span>

            A WORLD BEYOND THE PAGES

        </div>


        <h1>

            Every book is

            <br>

            <span>another universe.</span>

        </h1>


        <p class="hero-description">

            Lose yourself in extraordinary stories.

            Discover timeless classics, explore new ideas,

            and find the book that speaks to you.

        </p>


        <div class="hero-buttons">

            <a href="#books" class="primary-button">
                Explore the Library ↗
            </a>


            <a href="#categories" class="secondary-button">
                Discover Collections
            </a>

        </div>


        <div class="hero-stats">

            <div>
                <strong>01</strong>
                <span>Discover</span>
            </div>


            <div class="stat-divider"></div>


            <div>
                <strong>02</strong>
                <span>Explore</span>
            </div>


            <div class="stat-divider"></div>


            <div>
                <strong>03</strong>
                <span>Experience</span>
            </div>

        </div>

    </div>


    <!-- 3D BOOK DISPLAY -->

    <div class="hero-visual">

        <div class="scene-light"></div>

        <div class="scene-orbit orbit-one"></div>

        <div class="scene-orbit orbit-two"></div>


        <div class="floating-particle particle-one"></div>

        <div class="floating-particle particle-two"></div>

        <div class="floating-particle particle-three"></div>

        <div class="floating-particle particle-four"></div>


        <div class="book-scene" id="bookScene">


            <div class="floating-book book-one">

                <div class="book-spine"></div>

                <div class="book-face">

                    <span>THE</span>

                    <strong>
                        ART OF<br>IMAGINATION
                    </strong>

                    <small>BOOKHAVEN EDITION</small>

                </div>

            </div>


            <div class="floating-book book-two">

                <div class="book-spine"></div>

                <div class="book-face">

                    <span>EXPLORE</span>

                    <strong>
                        BEYOND<br>THE STARS
                    </strong>

                    <small>A NEW JOURNEY</small>

                </div>

            </div>


            <div class="floating-book book-three">

                <div class="book-spine"></div>

                <div class="book-face">

                    <span>THE</span>

                    <strong>
                        UNKNOWN<br>WORLD
                    </strong>

                    <small>DISCOVER MORE</small>

                </div>

            </div>

        </div>


        <div class="scene-caption caption-top">

            <span class="caption-dot"></span>

            YOUR NEXT CHAPTER

        </div>


        <div class="scene-caption caption-bottom">

            <strong>Explore without limits.</strong>

            <span>Read. Learn. Imagine.</span>

        </div>

    </div>


    <div class="hero-bottom-label">

        SCROLL TO EXPLORE

        <span class="scroll-line"></span>

    </div>

</section>


<!-- ==========================================
     SEARCH SECTION
========================================== -->

<section class="search-section">

    <div class="search-heading">

        <span>FIND YOUR NEXT READ</span>

        <p>
            Somewhere between these pages is your next favourite story.
        </p>

    </div>


    <form
        action="books.php"
        method="GET"
        class="search-form"
    >

        <span class="search-icon">⌕</span>


        <input
            type="text"
            name="search"
            placeholder="Search books, authors or stories..."
            aria-label="Search books"
        >


        <button type="submit">
            Discover ↗
        </button>

    </form>


    <div class="search-hints">

        <span>POPULAR:</span>

        <a href="books.php?search=Fiction">
            Fiction
        </a>

        <a href="books.php?search=Technology">
            Technology
        </a>

        <a href="books.php?search=Science">
            Science
        </a>

    </div>

</section>


<!-- ==========================================
     CATEGORIES
========================================== -->

<section
    class="categories-section section-padding"
    id="categories"
>

    <div class="section-top reveal">

        <div>

            <span class="section-eyebrow">
                EXPLORE THE UNKNOWN
            </span>

            <h2>
                Find your <span>universe.</span>
            </h2>

        </div>


        <p>

            Every genre has a different world waiting

            to be discovered.

        </p>

    </div>


    <div class="category-grid">


        <?php if ($categories && $categories->num_rows > 0): ?>


            <?php while ($category = $categories->fetch_assoc()): ?>


                <?php

                $categoryName = $category["category_name"];

                $icon = $categoryIcons[$categoryName] ?? "✧";

                ?>


                <a
                    class="category-card reveal"
                    href="books.php?category=<?= (int)$category["id"] ?>"
                >

                    <div class="category-symbol">

                        <?= bookhaven_escape($icon) ?>

                    </div>


                    <div class="category-card-info">

                        <h3>
                            <?= bookhaven_escape($categoryName) ?>
                        </h3>

                        <span>
                            Explore collection ↗
                        </span>

                    </div>


                    <div class="category-arrow">
                        ↗
                    </div>

                </a>


            <?php endwhile; ?>


        <?php else: ?>


            <div class="empty-state">

                <span>✧</span>

                <h3>New worlds are coming.</h3>

                <p>
                    Book categories will appear here once added.
                </p>

            </div>


        <?php endif; ?>

    </div>

</section>


<!-- ==========================================
     BOOK COLLECTION
========================================== -->

<section
    class="books-section section-padding"
    id="books"
>

    <div class="section-top reveal">

        <div>

            <span class="section-eyebrow">
                CURATED FOR CURIOUS MINDS
            </span>

            <h2>
                Stories worth <span>discovering.</span>
            </h2>

        </div>


        <a href="books.php" class="text-link">
            View all books ↗
        </a>

    </div>


    <div class="book-grid">


        <?php if ($books && $books->num_rows > 0): ?>


            <?php while ($book = $books->fetch_assoc()): ?>


                <?php
                $bookStock = max(0, (int)$book["stock"]);
                ?>


                <article
                    class="product-card reveal"
                    data-book-id="<?= (int)$book["id"] ?>"
                    data-stock="<?= $bookStock ?>"
                >

                    <div class="product-cover">


                        <?php if (!empty($book["cover_image"])): ?>


                            <img
                                src="<?= bookhaven_escape($book["cover_image"]) ?>"
                                alt="<?= bookhaven_escape($book["title"]) ?>"
                                loading="lazy"
                            >


                        <?php else: ?>


                            <div class="product-placeholder">

                                <span>BOOKHAVEN</span>

                                <strong>
                                    <?= bookhaven_escape($book["title"]) ?>
                                </strong>

                                <small>AN OPEN WORLD</small>

                            </div>


                        <?php endif; ?>


                        <!-- WISHLIST BUTTON -->

                        <button
                            class="product-wishlist"
                            type="button"
                            data-wishlist="<?= (int)$book["id"] ?>"
                            aria-label="Add to wishlist"
                        >
                            ♡
                        </button>


                        <!-- QUICK VIEW -->

                        <div class="product-overlay">

                            <button
                                class="quick-view"
                                type="button"
                                data-quick-view="<?= (int)$book["id"] ?>"
                            >
                                Quick View ↗
                            </button>

                        </div>

                    </div>


                    <div class="product-details">


                        <h3>
                            <?= bookhaven_escape($book["title"]) ?>
                        </h3>


                        <p>
                            By <?= bookhaven_escape($book["author"]) ?>
                        </p>


                        <div class="product-bottom">


                            <strong>
                                ₹<?= number_format((float)$book["price"], 2) ?>
                            </strong>


                            <?php if ($bookStock > 0): ?>


                                <button
                                    class="add-cart"
                                    type="button"
                                    data-add-cart="<?= (int)$book["id"] ?>"
                                >
                                    + Add
                                </button>


                            <?php else: ?>


                                <span class="stock-empty">
                                    Out of Stock
                                </span>


                            <?php endif; ?>

                        </div>

                    </div>

                </article>


            <?php endwhile; ?>


        <?php else: ?>


            <div class="empty-state">

                <span>📖</span>

                <h3>The shelves are waiting.</h3>

                <p>
                    Books will appear here once the Admin adds them.
                </p>

            </div>


        <?php endif; ?>

    </div>

</section>


<!-- ==========================================
     ABOUT SECTION
========================================== -->

<section
    class="experience-section"
    id="about"
>

    <div class="experience-content reveal">

        <span class="section-eyebrow">
            MORE THAN JUST A BOOKSTORE
        </span>


        <h2>

            Step inside.

            <br>

            <span>Stay curious.</span>

        </h2>


        <p>

            A great book can take you across galaxies,

            introduce you to new ideas and change

            the way you see the world.

        </p>


        <a href="#books" class="primary-button">

            Find Your Next Story ↗

        </a>

    </div>


    <div class="experience-decoration">

        <div class="experience-ring ring-one"></div>

        <div class="experience-ring ring-two"></div>

        <div class="experience-book">
            READ<br>BEYOND<br>LIMITS.
        </div>

    </div>

</section>


<!-- ==========================================
     FINAL SECTION
========================================== -->

<section class="newsletter-section">

    <span class="section-eyebrow">
        A LITTLE INSPIRATION
    </span>


    <h2>
        Keep turning pages.
    </h2>


    <p>

        Every journey begins with opening a book.

        Your next adventure is waiting.

    </p>


    <a href="#books" class="newsletter-link">
        Explore the collection ↗
    </a>

</section>

</main>


<!-- ==========================================
     FOOTER
========================================== -->

<footer class="footer">


    <div class="footer-top">

        <div>

            <a href="index.php" class="logo">
                BOOK<span>HAVEN</span><small>.</small>
            </a>

            <p>
                A universe of stories, one page at a time.
            </p>

        </div>


        <div class="footer-links">

            <a href="#home">Discover</a>

            <a href="#categories">Collections</a>

            <a href="#books">Books</a>


            <?php if ($isLoggedIn): ?>

                <a href="<?= bookhaven_escape($dashboardLink) ?>">
                    My Dashboard
                </a>

                <a href="logout.php">
                    Logout
                </a>

            <?php else: ?>

                <a href="login.php">
                    Login
                </a>

                <a href="register.php">
                    Join Us
                </a>

            <?php endif; ?>

        </div>

    </div>


    <div class="footer-bottom">

        <span>
            © <?= date("Y") ?> BookHaven.
        </span>

        <span>
            MADE FOR THE LOVE OF READING.
        </span>

    </div>

</footer>


<!-- ==========================================
     QUICK VIEW MODAL
========================================== -->

<div
    class="modal"
    id="quickViewModal"
    aria-hidden="true"
>

    <div
        class="modal-backdrop"
        data-close-modal
    ></div>


    <div class="modal-content">

        <button
            class="modal-close"
            type="button"
            data-close-modal
        >
            ×
        </button>


        <div id="quickViewContent">

            <p>Select a book to preview it.</p>

        </div>

    </div>

</div>


<!-- ==========================================
     CART DRAWER
========================================== -->

<div
    class="cart-overlay"
    id="cartOverlay"
>

    <div
        class="cart-backdrop"
        data-close-cart
    ></div>


    <aside class="cart-drawer">


        <div class="cart-header">

            <h2>Your Library Bag</h2>

            <button
                type="button"
                data-close-cart
            >
                ×
            </button>

        </div>


        <div
            id="cartContent"
            class="cart-content"
        >

            <p>
                Your shopping bag is waiting for a story.
            </p>

        </div>


        <div class="cart-footer">


            <div class="cart-total">

                <span>Total</span>

                <strong id="cartTotal">
                    ₹0.00
                </strong>

            </div>


            <a
                href="login.php"
                class="primary-button cart-checkout"
            >
                Continue to Checkout ↗
            </a>

        </div>

    </aside>

</div>


<!-- ==========================================
     WISHLIST PANEL
     JavaScript fills this panel.
========================================== -->

<div
    id="bookhavenWishlistPanel"
    aria-hidden="true"
>

    <div class="bookhaven-wishlist-box">


        <div class="bookhaven-wishlist-header">

            <h2>
                Your Wishlist ♥
            </h2>


            <button
                type="button"
                id="closeBookhavenWishlist"
                aria-label="Close wishlist"
            >
                ✕
            </button>

        </div>


        <div id="bookhavenWishlistItems">

            <p>Your wishlist is empty.</p>

        </div>

    </div>

</div>


<!-- TOAST -->

<div class="toast" id="toast"></div>


<!-- BACK TO TOP -->

<button
    class="back-to-top"
    id="backToTop"
    type="button"
>
    ↑
</button>


</body>
</html>
