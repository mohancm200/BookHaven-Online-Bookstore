
<?php
session_start();
require_once __DIR__ . "/config/db.php";

function e($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

$search = trim($_GET["search"] ?? "");
$category = trim($_GET["category"] ?? "");

// Fetch categories
$categories = [];
$result = $conn->query(
    "SELECT id, category_name FROM categories ORDER BY category_name"
);

if ($result) {
    $categories = $result->fetch_all(MYSQLI_ASSOC);
}

// Fetch books
$sql = "
    SELECT b.id, b.title, b.author, b.isbn,
           b.price, b.stock, b.cover_image,
           b.category_id, c.category_name
    FROM books b
    LEFT JOIN categories c ON b.category_id = c.id
    WHERE 1=1
";

$params = [];
$types = "";

if ($search !== "") {
    $sql .= " AND (
        b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ?
    )";

    $like = "%" . $search . "%";
    $params = [$like, $like, $like];
    $types = "sss";
}

if ($category !== "" && ctype_digit($category)) {
    $sql .= " AND b.category_id = ?";
    $params[] = (int)$category;
    $types .= "i";
}

$sql .= " ORDER BY b.id DESC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Unable to load books. Please check your database query.");
}

if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$books = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Explore Books | BookHaven</title>

<link rel="stylesheet" href="assets/css/style.css">

<style>

/* ==================================
   CATALOGUE
================================== */

.catalogue {
    max-width: 1250px;
    margin: auto;
    padding: 125px 5% 80px;
    min-height: 100vh;
}

.catalogue-heading {
    margin-bottom: 30px;
}

.catalogue-heading h1 {
    color: #e6a65d;
    font-family: 'Playfair Display', Georgia, serif;
    font-size: clamp(34px, 5vw, 52px);
    margin: 10px 0;
}

.catalogue-heading p {
    color: #9caabd;
}

.section-eyebrow {
    color: #e6a65d;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 2px;
}

/* ==================================
   TOP NAVIGATION BUTTONS
================================== */

.back-to-home {
    position: fixed;
    top: 18px;
    left: 24px;
    z-index: 1500;
    padding: 11px 16px;
    background: #111e30;
    border: 1px solid #344254;
    border-radius: 7px;
    color: #e6a65d;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
}

.back-to-home:hover {
    background: #e6a65d;
    color: #101722;
}

.catalogue-cart-tools {
    position: fixed;
    top: 18px;
    right: 24px;
    z-index: 1500;
    display: flex;
    gap: 10px;
}

.catalogue-cart-tools button {
    padding: 12px 16px;
    border: 1px solid #e6a65d;
    border-radius: 7px;
    background: #e6a65d;
    color: #101722;
    font-weight: 600;
    cursor: pointer;
}

.catalogue-cart-tools button:hover {
    background: #f4bd7d;
}

/* ==================================
   SEARCH
================================== */

.catalogue-filters {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin: 30px 0;
}

.catalogue-filters input,
.catalogue-filters select {
    flex: 1;
    min-width: 200px;
    height: 50px;
    padding: 0 14px;
    background: #111e30;
    color: #fff;
    border: 1px solid #304052;
    border-radius: 7px;
    outline: none;
}

.catalogue-filters input:focus,
.catalogue-filters select:focus {
    border-color: #e6a65d;
}

.catalogue-filters button,
.catalogue-filters a {
    min-height: 48px;
    padding: 0 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 7px;
    cursor: pointer;
    text-decoration: none;
}

.catalogue-filters button {
    border: none;
    background: #e6a65d;
    color: #101722;
    font-weight: 700;
}

.catalogue-filters a {
    border: 1px solid #304052;
    color: #9caabd;
}

/* ==================================
   BOOK CARDS
================================== */

.catalogue-result {
    color: #9caabd;
    margin-bottom: 20px;
    font-size: 14px;
}

.catalogue-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 22px;
}

.catalogue-card {
    background: #111e30;
    border: 1px solid #304052;
    border-radius: 10px;
    padding: 13px;
    display: flex;
    flex-direction: column;
    transition: .25s;
}

.catalogue-card:hover {
    transform: translateY(-4px);
    border-color: rgba(230,166,93,.6);
}

.catalogue-cover {
    height: 220px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #192a40;
    margin-bottom: 18px;
    overflow: hidden;
    border-radius: 5px;
}

.catalogue-cover img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.catalogue-card .product-details {
    flex: 1;
}

.catalogue-card .product-details h3 {
    color: #f7f3ed;
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 17px;
    margin-bottom: 7px;
}

.catalogue-card .product-details p {
    color: #9caabd;
    font-size: 12px;
    margin: 4px 0;
}

.catalogue-card .product-bottom {
    border-top: 1px solid #304052;
    margin-top: 18px;
    padding-top: 12px;
}

.catalogue-card .product-bottom strong {
    color: #e6a65d;
    font-size: 15px;
}

.catalogue-stock {
    color: #9caabd;
    font-size: 12px;
    margin-top: 6px;
}

.catalogue-actions {
    display: flex;
    gap: 7px;
    margin-top: 12px;
}

.catalogue-actions button {
    flex: 1;
    min-height: 42px;
    padding: 8px;
    border: none;
    border-radius: 6px;
    background: #e6a65d;
    color: #101722;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
}

.catalogue-actions button:hover {
    background: #f4bd7d;
}

.catalogue-actions button[data-wishlist] {
    flex: 0 0 38px;
    background: #263b54;
    color: #fff;
    font-size: 18px;
}

.catalogue-actions button[data-wishlist].selected {
    color: #ff7185;
}

.catalogue-actions button:disabled {
    opacity: .45;
    cursor: not-allowed;
}

.catalogue-empty {
    padding: 50px 20px;
    text-align: center;
    color: #9caabd;
    background: #111e30;
    border-radius: 10px;
}

.catalogue-empty h2 {
    color: #e6a65d;
}

/* ==================================
   LIBRARY BAG OVERLAY
================================== */

.cart-overlay {
    position: fixed;
    inset: 0;
    z-index: 1600;
    display: none;
}

.cart-overlay.open {
    display: block;
}

.cart-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,.76);
    backdrop-filter: blur(5px);
}

/* Drawer size */

.cart-overlay .cart-drawer {
    position: absolute;
    top: 0;
    right: 0;
    width: 480px;
    max-width: 94vw;
    height: 100%;
    box-sizing: border-box;
    padding: 30px;
    background: #101d2d;
    border-left: 1px solid #344254;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    animation: libraryDrawerIn .3s ease;
}

@keyframes libraryDrawerIn {
    from {
        transform: translateX(100%);
    }
    to {
        transform: translateX(0);
    }
}

/* Drawer header */

.cart-overlay .cart-header {
    flex-shrink: 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 22px;
    border-bottom: 1px solid #344254;
}

.cart-overlay .cart-header h2 {
    color: #f7f3ed;
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 25px;
    line-height: 1.4;
    margin: 0;
}

.cart-overlay .cart-header button {
    width: 38px;
    height: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: none;
    border-radius: 50%;
    color: #fff;
    font-size: 26px;
    cursor: pointer;
}

.cart-overlay .cart-header button:hover {
    background: #263b54;
    color: #e6a65d;
}

/* Scrollable item area */

.cart-overlay .cart-content {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    padding: 18px 0;
    color: #aab8ca;
    font-size: 14px;
}

.cart-overlay .cart-content::-webkit-scrollbar {
    width: 6px;
}

.cart-overlay .cart-content::-webkit-scrollbar-thumb {
    background: #344254;
    border-radius: 10px;
}

/* Individual cart items */

.cart-overlay .cart-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 18px;
    padding: 22px 0;
    border-bottom: 1px solid #344254;
}

.cart-overlay .cart-item h4 {
    color: #f7f3ed;
    font-size: 17px;
    line-height: 1.5;
    margin: 0 0 9px;
}

.cart-overlay .cart-item p {
    color: #e6a65d;
    font-size: 14px;
    line-height: 1.6;
    margin: 6px 0;
}

.cart-overlay .cart-item > button {
    flex-shrink: 0;
    background: transparent;
    border: none;
    color: #ff8c83;
    font-size: 13px;
    cursor: pointer;
    white-space: nowrap;
}

.cart-overlay .cart-item > button:hover {
    color: #ffb0a8;
    text-decoration: underline;
}

/* Quantity buttons */

.cart-overlay .bookhaven-quantity {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-top: 14px;
}

.cart-overlay .bookhaven-quantity button {
    width: 36px;
    height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    border: 1px solid #e6a65d;
    border-radius: 6px;
    background: #e6a65d;
    color: #101722;
    font-size: 20px;
    font-weight: 700;
    cursor: pointer;
}

.cart-overlay .bookhaven-quantity button:hover {
    background: #f4bd7d;
}

.cart-overlay .bookhaven-quantity button:disabled {
    opacity: .4;
    cursor: not-allowed;
}

.cart-overlay .bookhaven-quantity span {
    min-width: 24px;
    text-align: center;
    color: #fff;
    font-size: 16px;
    font-weight: 600;
}

/* Footer */

.cart-overlay .cart-footer {
    flex-shrink: 0;
    padding-top: 24px;
    border-top: 1px solid #344254;
}

.cart-overlay .cart-total {
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: #f7f3ed;
    font-size: 17px;
    margin-bottom: 22px;
}

.cart-overlay .cart-total strong {
    color: #e6a65d;
    font-size: 23px;
}

.cart-overlay .cart-checkout {
    width: 100%;
    min-height: 54px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-sizing: border-box;
    border-radius: 7px;
    font-size: 15px;
    font-weight: 700;
    text-decoration: none;
}

/* Empty bag */

.cart-overlay .cart-content > p {
    padding: 12px 0;
    color: #aab8ca;
    font-size: 14px;
    line-height: 1.8;
}

/* ==================================
   WISHLIST
================================== */

#bookhavenWishlistPanel {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 1700;
    background: rgba(0,0,0,.75);
    align-items: center;
    justify-content: center;
    padding: 20px;
}

#bookhavenWishlistPanel.open {
    display: flex;
}

.bookhaven-wishlist-box {
    width: min(550px, 100%);
    max-height: 80vh;
    overflow-y: auto;
    background: #101d2d;
    color: #fff;
    padding: 25px;
    border: 1px solid #e6a65d;
    border-radius: 12px;
}

.bookhaven-wishlist-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}

.bookhaven-wishlist-header h2 {
    color: #e6a65d;
}

.bookhaven-wishlist-header button {
    background: none;
    border: none;
    color: white;
    font-size: 22px;
    cursor: pointer;
}

.bookhaven-wishlist-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 14px 0;
    border-bottom: 1px solid #344254;
}

.bookhaven-wishlist-item button {
    background: #e6a65d;
    color: #101722;
    border: none;
    padding: 8px;
    border-radius: 5px;
    cursor: pointer;
}

/* ==================================
   QUICK VIEW
================================== */

#quickViewModal {
    display: none;
}

#quickViewModal.open {
    display: flex;
}

#quickViewModal .modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.8);
    z-index: 1800;
}

#quickViewModal .modal-content {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: min(500px, 92%);
    max-height: 85vh;
    overflow-y: auto;
    padding: 30px;
    background: #101d2d;
    color: #fff;
    border: 1px solid #e6a65d;
    border-radius: 12px;
    z-index: 1801;
    text-align: center;
}

#quickViewModal .modal-close {
    position: absolute;
    top: 12px;
    right: 15px;
    background: none;
    border: none;
    color: white;
    font-size: 25px;
    cursor: pointer;
}

/* ==================================
   RESPONSIVE
================================== */

@media(max-width: 600px) {

    .catalogue {
        padding: 120px 5% 60px;
    }

    .back-to-home {
        top: 65px;
        left: 10px;
        font-size: 12px;
        padding: 9px 11px;
    }

    .catalogue-cart-tools {
        top: 8px;
        right: 8px;
    }

    .catalogue-cart-tools button {
        padding: 9px 11px;
        font-size: 12px;
    }

    .catalogue-filters {
        flex-direction: column;
    }

    .catalogue-filters input,
    .catalogue-filters select,
    .catalogue-filters button,
    .catalogue-filters a {
        width: 100%;
        min-height: 48px;
    }

    .catalogue-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .catalogue-card {
        padding: 10px;
    }

    .catalogue-cover {
        height: 165px;
    }

    .cart-overlay .cart-drawer {
        width: 100%;
        max-width: 100%;
        padding: 22px;
    }

    .cart-overlay .cart-header h2 {
        font-size: 22px;
    }

    .cart-overlay .cart-item h4 {
        font-size: 15px;
    }
}

</style>
</head>

<body>

<!-- BACK TO BOOKHAVEN -->

<a href="index.php" class="back-to-home">
    ← Back to BookHaven
</a>

<!-- CART AND WISHLIST BUTTONS -->

<div class="catalogue-cart-tools">

    <button type="button" id="wishlistButton">
        ♥ Wishlist (<span id="wishlistCount">0</span>)
    </button>

    <button type="button" id="cartButton">
        🛒 Cart (<span id="cartCount">0</span>)
    </button>

</div>

<!-- MAIN CATALOGUE -->

<main class="catalogue">

    <div class="catalogue-heading">

        <span class="section-eyebrow">
            THE BOOKHAVEN COLLECTION
        </span>

        <h1>Discover Your Next Story</h1>

        <p>Explore books, authors and collections.</p>

    </div>

    <!-- SEARCH AND FILTER -->

    <form class="catalogue-filters"
          method="GET"
          action="books.php">

        <input
            type="search"
            name="search"
            placeholder="Search title, author or ISBN..."
            value="<?= e($search) ?>"
        >

        <select name="category">

            <option value="">All Categories</option>

            <?php foreach ($categories as $item): ?>

                <option
                    value="<?= (int)$item["id"] ?>"
                    <?= $category === (string)$item["id"] ? "selected" : "" ?>
                >
                    <?= e($item["category_name"]) ?>
                </option>

            <?php endforeach; ?>

        </select>

        <button type="submit">Search Books</button>

        <a href="books.php">Clear</a>

    </form>

    <p class="catalogue-result">
        <?= count($books) ?> book(s) found
    </p>

    <!-- BOOK CARDS -->

    <?php if (empty($books)): ?>

        <div class="catalogue-empty">
            <h2>No Books Found</h2>
            <p>Try another search or category.</p>
        </div>

    <?php else: ?>

        <div class="catalogue-grid">

            <?php foreach ($books as $book): ?>

                <?php $stock = max(0, (int)$book["stock"]); ?>

                <article
                    class="catalogue-card product-card"
                    data-book-id="<?= (int)$book["id"] ?>"
                    data-stock="<?= $stock ?>"
                >

                    <div class="catalogue-cover product-cover">

                        <?php if (!empty($book["cover_image"])): ?>

                            <img
                                src="uploads/book_covers/<?= e(basename($book["cover_image"])) ?>"
                                alt="<?= e($book["title"]) ?>"
                                loading="lazy"
                            >

                        <?php else: ?>

                            <div class="product-placeholder">
                                <?= e($book["title"]) ?>
                            </div>

                        <?php endif; ?>

                    </div>

                    <div class="product-details">

                        <h3><?= e($book["title"]) ?></h3>

                        <p><?= e($book["author"]) ?></p>

                        <p>
                            <?= e($book["category_name"] ?? "Uncategorized") ?>
                        </p>

                    </div>

                    <div class="product-bottom">
                        <strong>
                            ₹<?= number_format((float)$book["price"], 2) ?>
                        </strong>
                    </div>

                    <p class="catalogue-stock">
                        <?php if ($stock > 0): ?>
                            In stock: <?= $stock ?>
                        <?php else: ?>
                            Currently unavailable
                        <?php endif; ?>
                    </p>

                    <div class="catalogue-actions">

                        <button
                            type="button"
                            data-wishlist="<?= (int)$book["id"] ?>"
                            aria-label="Add to wishlist"
                        >♡</button>

                        <button
                            type="button"
                            data-quick-view="<?= (int)$book["id"] ?>"
                        >Quick View</button>

                        <button
                            type="button"
                            data-add-cart="<?= (int)$book["id"] ?>"
                            <?= $stock <= 0 ? "disabled" : "" ?>
                        >Add to Cart</button>

                    </div>

                </article>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</main>

<!-- QUICK VIEW -->

<div class="modal"
     id="quickViewModal"
     aria-hidden="true">

    <div class="modal-backdrop" data-close-modal></div>

    <div class="modal-content">

        <button
            class="modal-close"
            type="button"
            data-close-modal
            aria-label="Close quick view"
        >&times;</button>

        <div id="quickViewContent"></div>

    </div>

</div>

<!-- LIBRARY BAG -->

<div class="cart-overlay" id="cartOverlay">

    <div class="cart-backdrop" data-close-cart></div>

    <div class="cart-drawer">

        <div class="cart-header">

            <h2>Your Library Bag</h2>

            <button
                type="button"
                data-close-cart
                aria-label="Close cart"
            >&times;</button>

        </div>

        <div class="cart-content" id="cartContent">
            <p>Your shopping bag is waiting for a story.</p>
        </div>

        <div class="cart-footer">

            <div class="cart-total">
                <span>Total</span>
                <strong id="cartTotal">₹0.00</strong>
            </div>

            <a href="login.php"
               class="primary-button cart-checkout">
                Continue to Checkout →
            </a>

        </div>

    </div>

</div>

<!-- TOAST -->

<div id="toast" class="toast"></div>

<!-- JAVASCRIPT -->

<script src="assets/js/app.js?v=7"></script>

</body>
</html>
