<?php

session_start();

require_once __DIR__ . "/../config/db.php";

/* ----------------------------------
   AUTHENTICATION
---------------------------------- */

$role = $_SESSION["role"] ?? "";

if (
    !isset($_SESSION["user_id"]) ||
    !in_array($role, ["Admin", "Sub-Admin"], true)
) {
    header("Location: ../login.php");
    exit;
}

/* ----------------------------------
   HELPER
---------------------------------- */

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

$uploadDir = __DIR__ . "/../uploads/book_covers/";
$uploadUrl = "../uploads/book_covers/";

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if (empty($_SESSION["books_csrf"])) {
    $_SESSION["books_csrf"] = bin2hex(random_bytes(32));
}

$error = "";
$success = "";
$editBook = null;

/* ----------------------------------
   SELECT CURRENT SCREEN
---------------------------------- */

$view = $_GET["view"] ?? "add";

$allowedViews = ["add", "all", "out"];

if (!in_array($view, $allowedViews, true)) {
    $view = "add";
}

$successMessages = [
    "added" => "Book added successfully!",
    "updated" => "Book updated successfully!",
    "deleted" => "Book deleted successfully!"
];

$success = $successMessages[$_GET["success"] ?? ""] ?? "";

/* ----------------------------------
   LOAD CATEGORIES
---------------------------------- */

$categories = [];

$categoryResult = $conn->query(
    "SELECT id, category_name
     FROM categories
     ORDER BY category_name ASC"
);

if ($categoryResult) {
    $categories = $categoryResult->fetch_all(MYSQLI_ASSOC);
}

/* ----------------------------------
   HANDLE FORM SUBMISSIONS
---------------------------------- */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["csrf_token"] ?? "";

    if (
        !is_string($token) ||
        !hash_equals($_SESSION["books_csrf"], $token)
    ) {
        $error = "Session verification failed. Refresh the page.";

    } else {

        $action = $_POST["action"] ?? "";

        /* DELETE BOOK */

        if ($action === "delete") {

            $id = (int)($_POST["id"] ?? 0);

            if ($id < 1) {
                $error = "Invalid book selected.";

            } else {

                $stmt = $conn->prepare(
                    "SELECT cover_image FROM books WHERE id = ?"
                );

                $stmt->bind_param("i", $id);
                $stmt->execute();

                $existing = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$existing) {
                    $error = "Book not found.";

                } else {

                    $stmt = $conn->prepare(
                        "DELETE FROM books WHERE id = ?"
                    );

                    $stmt->bind_param("i", $id);

                    if ($stmt->execute()) {

                        $stmt->close();

                        if (!empty($existing["cover_image"])) {

                            $oldPath = $uploadDir .
                                basename($existing["cover_image"]);

                            if (is_file($oldPath)) {
                                unlink($oldPath);
                            }
                        }

                        header("Location: books.php?view=all&success=deleted");
                        exit;

                    } else {

                        $error = "Unable to delete this book. It may be linked to an order.";
                        $stmt->close();
                    }
                }
            }

        /* ADD OR UPDATE BOOK */

        } elseif ($action === "save") {

            $id = (int)($_POST["id"] ?? 0);

            $title = trim($_POST["title"] ?? "");
            $author = trim($_POST["author"] ?? "");
            $isbn = trim($_POST["isbn"] ?? "");
            $description = trim($_POST["description"] ?? "");

            $priceInput = trim($_POST["price"] ?? "");
            $stockInput = trim($_POST["stock"] ?? "0");
            $categoryInput = trim($_POST["category_id"] ?? "");

            if ($title === "" || mb_strlen($title) > 200) {
                $error = "Enter a valid book title (maximum 200 characters).";

            } elseif ($author === "" || mb_strlen($author) > 150) {
                $error = "Enter a valid author name (maximum 150 characters).";

            } elseif (
                $priceInput === "" ||
                !is_numeric($priceInput) ||
                (float)$priceInput < 0 ||
                (float)$priceInput > 99999999.99
            ) {
                $error = "Enter a valid book price.";

            } elseif (
                !preg_match('/^\d+$/', $stockInput) ||
                (float)$stockInput > 2147483647
            ) {
                $error = "Stock must be a whole number greater than or equal to zero.";

            } elseif (strlen($isbn) > 30) {
                $error = "ISBN cannot exceed 30 characters.";

            } elseif (
                $categoryInput !== "" &&
                !ctype_digit($categoryInput)
            ) {
                $error = "Select a valid category.";
            }

            if ($error === "") {

                $price = (float)$priceInput;
                $stock = (int)$stockInput;
                $categoryValue = null;

                /* CHECK CATEGORY */

                if ($categoryInput !== "") {

                    $categoryId = (int)$categoryInput;

                    $check = $conn->prepare(
                        "SELECT id FROM categories WHERE id = ?"
                    );

                    $check->bind_param("i", $categoryId);
                    $check->execute();

                    $categoryExists = $check->get_result()->fetch_assoc();
                    $check->close();

                    if (!$categoryExists) {
                        $error = "The selected category does not exist.";
                    } else {
                        $categoryValue = $categoryId;
                    }
                }

                /* GET EXISTING COVER */

                $oldCover = "";

                if ($error === "" && $id > 0) {

                    $check = $conn->prepare(
                        "SELECT cover_image FROM books WHERE id = ?"
                    );

                    $check->bind_param("i", $id);
                    $check->execute();

                    $existing = $check->get_result()->fetch_assoc();
                    $check->close();

                    if (!$existing) {
                        $error = "Book not found.";
                    } else {
                        $oldCover = $existing["cover_image"] ?? "";
                    }
                }

                $coverName = $oldCover;
                $newCover = "";

                /* UPLOAD COVER IMAGE */

                if (
                    $error === "" &&
                    isset($_FILES["cover_image"]) &&
                    $_FILES["cover_image"]["error"] !== UPLOAD_ERR_NO_FILE
                ) {

                    $file = $_FILES["cover_image"];

                    if ($file["error"] !== UPLOAD_ERR_OK) {
                        $error = "Cover image upload failed.";

                    } elseif ($file["size"] > 3 * 1024 * 1024) {
                        $error = "Cover image must be 3 MB or smaller.";

                    } else {

                        $imageInfo = @getimagesize($file["tmp_name"]);
                        $mime = $imageInfo["mime"] ?? "";

                        $allowed = [
                            "image/jpeg" => "jpg",
                            "image/png" => "png",
                            "image/webp" => "webp"
                        ];

                        if (!$imageInfo || !isset($allowed[$mime])) {
                            $error = "Only JPG, PNG and WEBP images are allowed.";

                        } else {

                            $newCover = bin2hex(random_bytes(16)) .
                                "." . $allowed[$mime];

                            if (!move_uploaded_file(
                                $file["tmp_name"],
                                $uploadDir . $newCover
                            )) {
                                $error = "Could not save the cover image.";
                                $newCover = "";

                            } else {
                                $coverName = $newCover;
                            }
                        }
                    }
                }

                /* SAVE BOOK */

                if ($error === "") {

                    $isbnValue = $isbn === "" ? null : $isbn;
                    $descriptionValue = $description === "" ? null : $description;
                    $coverValue = $coverName === "" ? null : $coverName;

                    if ($id > 0) {

                        $stmt = $conn->prepare(
                            "UPDATE books
                             SET category_id = ?,
                                 title = ?,
                                 author = ?,
                                 isbn = ?,
                                 description = ?,
                                 price = ?,
                                 stock = ?,
                                 cover_image = ?
                             WHERE id = ?"
                        );

                        if ($stmt) {
                            $stmt->bind_param(
                                "issssdisi",
                                $categoryValue,
                                $title,
                                $author,
                                $isbnValue,
                                $descriptionValue,
                                $price,
                                $stock,
                                $coverValue,
                                $id
                            );
                        }

                    } else {

                        $stmt = $conn->prepare(
                            "INSERT INTO books
                            (
                                category_id,
                                title,
                                author,
                                isbn,
                                description,
                                price,
                                stock,
                                cover_image
                            )
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                        );

                        if ($stmt) {
                            $stmt->bind_param(
                                "issssdis",
                                $categoryValue,
                                $title,
                                $author,
                                $isbnValue,
                                $descriptionValue,
                                $price,
                                $stock,
                                $coverValue
                            );
                        }
                    }

                    if (!$stmt) {

                        $error = "Database query failed: " . $conn->error;

                    } elseif ($stmt->execute()) {

                        $stmt->close();

                        if ($newCover !== "" && $oldCover !== "") {

                            $oldPath = $uploadDir .
                                basename($oldCover);

                            if (is_file($oldPath)) {
                                unlink($oldPath);
                            }
                        }

                        header(
                            "Location: books.php?view=add&success=" .
                            ($id > 0 ? "updated" : "added")
                        );

                        exit;

                    } else {

                        $databaseError = $stmt->error;
                        $stmt->close();

                        if ($newCover !== "" && is_file($uploadDir . $newCover)) {
                            unlink($uploadDir . $newCover);
                        }

                        $error = stripos($databaseError, "Duplicate") !== false
                            ? "This ISBN is already registered."
                            : "Unable to save book: " . $databaseError;
                    }
                }
            }

        } else {
            $error = "Invalid form action.";
        }
    }
}

/* ----------------------------------
   LOAD BOOK FOR EDITING
---------------------------------- */

if (isset($_GET["edit"])) {

    $editId = (int)$_GET["edit"];

    $stmt = $conn->prepare(
        "SELECT * FROM books WHERE id = ?"
    );

    $stmt->bind_param("i", $editId);
    $stmt->execute();

    $editBook = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$editBook) {
        $error = "Book not found.";
    } else {
        $view = "add";
    }
}

/* ----------------------------------
   SEARCH AND CATEGORY FILTER
---------------------------------- */

$search = trim($_GET["search"] ?? "");
$filterCategory = trim($_GET["category"] ?? "");

$sql = "SELECT b.*, c.category_name
        FROM books b
        LEFT JOIN categories c ON b.category_id = c.id
        WHERE 1=1";

$params = [];
$types = "";

if ($search !== "") {

    $sql .= " AND (
        b.title LIKE ?
        OR b.author LIKE ?
        OR b.isbn LIKE ?
    )";

    $like = "%" . $search . "%";

    $params[] = $like;
    $params[] = $like;
    $params[] = $like;

    $types .= "sss";
}

if ($filterCategory !== "" && ctype_digit($filterCategory)) {

    $sql .= " AND b.category_id = ?";

    $params[] = (int)$filterCategory;
    $types .= "i";
}

$sql .= " ORDER BY b.id DESC";

$stmt = $conn->prepare($sql);

if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();

$books = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* ----------------------------------
   STOCK COUNTS
---------------------------------- */

$stockSummary = $conn->query(
    "SELECT
        SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) AS out_count,
        SUM(CASE WHEN stock > 0 AND stock <= 5 THEN 1 ELSE 0 END) AS low_count
     FROM books"
)->fetch_assoc();

$outOfStockCount = (int)($stockSummary["out_count"] ?? 0);
$lowStockCount = (int)($stockSummary["low_count"] ?? 0);

/* ----------------------------------
   FETCH ONLY OUT-OF-STOCK BOOKS
---------------------------------- */

$outOfStockBooks = [];

$outResult = $conn->query(
    "SELECT
        b.id,
        b.title,
        b.author,
        b.isbn,
        b.price,
        b.stock,
        b.cover_image,
        c.category_name
     FROM books b
     LEFT JOIN categories c ON b.category_id = c.id
     WHERE b.stock = 0
     ORDER BY b.title ASC"
);

if ($outResult) {
    $outOfStockBooks = $outResult->fetch_all(MYSQLI_ASSOC);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>BookHaven | Book Management</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #080f1b;
    color: #f7f3ed;
    font-family: Arial, sans-serif;
}

header {
    background: #121f30;
    border-bottom: 1px solid #304052;
    padding: 20px 6%;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.logo {
    font: bold 28px Georgia, serif;
}

.logo span {
    color: #eaa45c;
}

header a {
    color: #eaa45c;
    text-decoration: none;
}

main {
    max-width: 1250px;
    margin: auto;
    padding: 35px 22px;
}

h1, h2 {
    font-family: Georgia, serif;
}

h2 {
    color: #eaa45c;
}

.panel {
    background: #121f30;
    border: 1px solid #304052;
    border-radius: 14px;
    padding: 25px;
    margin-bottom: 28px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
}

.field label {
    display: block;
    margin-bottom: 8px;
    font-size: 14px;
}

.field input,
.field textarea,
.field select,
.filter-control {
    width: 100%;
    padding: 13px;
    border: 1px solid #35465a;
    border-radius: 8px;
    background: #0d1928;
    color: white;
    font-family: Arial, sans-serif;
}

.field select option,
.filter-control option {
    background: #121f30;
    color: white;
}

.field input:focus,
.field textarea:focus,
.field select:focus,
.filter-control:focus {
    outline: none;
    border-color: #eaa45c;
}

.field input[type=file] {
    padding: 12px;
}

.field textarea {
    min-height: 100px;
    resize: vertical;
}

.full {
    grid-column: 1 / -1;
}

button,
.button {
    border: 0;
    padding: 12px 18px;
    border-radius: 8px;
    background: #eaa45c;
    color: #101722;
    font-weight: bold;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
}

button:hover,
.button:hover {
    background: #f4b873;
}

.cancel {
    background: #35465a;
    color: white;
}

.delete {
    background: #a83c4d;
    color: white;
}

.delete:hover {
    background: #c44d5e;
}

.success,
.error,
.stock-alert {
    padding: 13px;
    border-radius: 8px;
    margin-bottom: 18px;
}

.success {
    background: #153c30;
    color: #9cf0c5;
}

.error {
    background: #49242c;
    color: #ffb4b4;
}

.stock-alert {
    background: #49242c;
    border: 1px solid #a94a4a;
    color: #ffd0d0;
}

.stock-alert.low {
    background: #493b20;
    border-color: #a87931;
    color: #ffe0a3;
}

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 900px;
}

th, td {
    text-align: left;
    padding: 14px 12px;
    border-bottom: 1px solid #304052;
    vertical-align: middle;
}

th {
    color: #eaa45c;
}

.cover {
    width: 55px;
    height: 70px;
    object-fit: cover;
    border-radius: 5px;
}

.actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
}

.actions form {
    margin: 0;
}

.empty {
    color: #a9b8cc;
}

.stock-badge {
    display: inline-block;
    padding: 6px 9px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.stock-out {
    background: #5a202b;
    color: #ffb5bd;
}

.stock-low {
    background: #59451e;
    color: #ffe09a;
}

.stock-ok {
    background: #174333;
    color: #a5f0c8;
}

.filter-form {
    display: grid;
    grid-template-columns: 2fr 1fr auto auto;
    gap: 12px;
    align-items: end;
    margin: 18px 0 22px;
}

.filter-form label {
    display: block;
    color: #a9b8cc;
    font-size: 13px;
    margin-bottom: 7px;
}

.filter-control {
    height: 43px;
}

.current-cover {
    margin-top: 10px;
}

.nav-card {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
}

.nav-option {
    display: block;
    text-align: center;
    padding: 24px 15px;
    border: 1px solid #35465a;
    border-radius: 12px;
    background: #0d1928;
    text-decoration: none;
    color: #f7f3ed;
    transition: .2s;
}

.nav-option:hover,
.nav-option.active {
    border-color: #eaa45c;
    background: #1c2b3c;
}

.nav-option strong {
    display: block;
    color: #eaa45c;
    font-size: 17px;
    margin-bottom: 9px;
}

.nav-option span {
    color: #a9b8cc;
    font-size: 13px;
    line-height: 1.5;
}

.nav-option .count {
    display: inline-block;
    margin-top: 12px;
    padding: 5px 10px;
    background: #49242c;
    color: #ffb4b4;
    border-radius: 20px;
    font-size: 12px;
}

@media(max-width: 700px) {

    .form-grid {
        grid-template-columns: 1fr;
    }

    .full {
        grid-column: auto;
    }

    .filter-form {
        grid-template-columns: 1fr;
    }

    .nav-card {
        grid-template-columns: 1fr;
    }

    header {
        padding: 18px 5%;
    }

    main {
        padding: 25px 15px;
    }

    .panel {
        padding: 18px;
    }
}

</style>

</head>

<body>

<header>

    <div class="logo">
        Book<span>Haven.</span>
    </div>

    <a href="<?= $role === "Sub-Admin"
        ? "../subadmin/dashboard.php"
        : "dashboard.php" ?>">
        ← Back to Dashboard
    </a>

</header>

<main>

    <h1>Book Management</h1>

    <p>
        Welcome, <?= e($_SESSION["full_name"] ?? "Staff") ?>.
        Manage your bookstore collection.
    </p>

    <?php if ($success !== ""): ?>
        <div class="success"><?= e($success) ?></div>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <div class="error"><?= e($error) ?></div>
    <?php endif; ?>

    <!-- THREE MANAGEMENT OPTIONS -->

    <section class="panel">

        <h2>Book Management Options</h2>

        <div class="nav-card">

            <a
                href="books.php?view=add"
                class="nav-option <?= $view === "add" ? "active" : "" ?>"
            >

                <strong>＋ Add New Book</strong>

                <span>
                    Add a new book to your bookstore.
                </span>

            </a>

            <a
                href="books.php?view=all"
                class="nav-option <?= $view === "all" ? "active" : "" ?>"
            >

                <strong>📚 View All Books</strong>

                <span>
                    Browse, search, edit and delete books.
                </span>

            </a>

            <a
                href="books.php?view=out"
                class="nav-option <?= $view === "out" ? "active" : "" ?>"
            >

                <strong>⚠ Out-of-Stock Books</strong>

                <span>
                    View books that have zero copies remaining.
                </span>

                <span class="count">
                    <?= $outOfStockCount ?> out of stock
                </span>

            </a>

        </div>

    </section>

    <!-- ADD / EDIT BOOK SCREEN -->

    <?php if ($view === "add"): ?>

        <section class="panel">

            <h2>
                <?= $editBook ? "Edit Book" : "Add New Book" ?>
            </h2>

            <form
                method="POST"
                action="books.php"
                enctype="multipart/form-data"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($_SESSION["books_csrf"]) ?>"
                >

                <input type="hidden" name="action" value="save">

                <input
                    type="hidden"
                    name="id"
                    value="<?= (int)($editBook["id"] ?? 0) ?>"
                >

                <div class="form-grid">

                    <div class="field">

                        <label for="title">Book Title *</label>

                        <input
                            type="text"
                            id="title"
                            name="title"
                            maxlength="200"
                            value="<?= e($editBook["title"] ?? "") ?>"
                            required
                        >

                    </div>

                    <div class="field">

                        <label for="author">Author *</label>

                        <input
                            type="text"
                            id="author"
                            name="author"
                            maxlength="150"
                            value="<?= e($editBook["author"] ?? "") ?>"
                            required
                        >

                    </div>

                    <div class="field">

                        <label for="isbn">ISBN</label>

                        <input
                            type="text"
                            id="isbn"
                            name="isbn"
                            maxlength="30"
                            value="<?= e($editBook["isbn"] ?? "") ?>"
                        >

                    </div>

                    <div class="field">

                        <label for="category_id">Book Category</label>

                        <select id="category_id" name="category_id">

                            <option value="">Uncategorized</option>

                            <?php foreach ($categories as $category): ?>

                                <option
                                    value="<?= (int)$category["id"] ?>"
                                    <?= isset($editBook["category_id"]) &&
                                    (int)$editBook["category_id"] ===
                                    (int)$category["id"] ? "selected" : "" ?>
                                >

                                    <?= e($category["category_name"]) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="field">

                        <label for="price">Price (₹) *</label>

                        <input
                            type="number"
                            id="price"
                            name="price"
                            min="0"
                            max="99999999.99"
                            step="0.01"
                            value="<?= e($editBook["price"] ?? "") ?>"
                            required
                        >

                    </div>

                    <div class="field">

                        <label for="stock">Stock Quantity *</label>

                        <input
                            type="number"
                            id="stock"
                            name="stock"
                            min="0"
                            step="1"
                            value="<?= e($editBook["stock"] ?? "0") ?>"
                            required
                        >

                    </div>

                    <div class="field full">

                        <label for="description">
                            Book Description
                        </label>

                        <textarea
                            id="description"
                            name="description"
                        ><?= e($editBook["description"] ?? "") ?></textarea>

                    </div>

                    <div class="field full">

                        <label for="cover_image">
                            Book Cover Image (JPG, PNG or WEBP, max 3 MB)
                        </label>

                        <input
                            type="file"
                            id="cover_image"
                            name="cover_image"
                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                        >

                        <?php if (!empty($editBook["cover_image"])): ?>

                            <div class="current-cover">

                                <p>Current cover:</p>

                                <img
                                    class="cover"
                                    src="<?= e(
                                        $uploadUrl .
                                        rawurlencode(basename($editBook["cover_image"]))
                                    ) ?>"
                                    alt="Current book cover"
                                >

                            </div>

                        <?php endif; ?>

                    </div>

                </div>

                <br>

                <button type="submit">
                    <?= $editBook ? "Update Book" : "+ Add Book" ?>
                </button>

                <?php if ($editBook): ?>

                    <a class="button cancel" href="books.php?view=all">
                        Cancel
                    </a>

                <?php endif; ?>

            </form>

        </section>

    <?php endif; ?>

    <!-- VIEW ALL BOOKS SCREEN -->

    <?php if ($view === "all"): ?>

        <section class="panel" id="book-collection">

            <h2>All Books (<?= count($books) ?>)</h2>

            <form
                class="filter-form"
                method="GET"
                action="books.php"
            >

                <input type="hidden" name="view" value="all">

                <div>

                    <label for="search">
                        Search by title, author or ISBN
                    </label>

                    <input
                        class="filter-control"
                        type="search"
                        id="search"
                        name="search"
                        placeholder="Enter book title, author or ISBN"
                        value="<?= e($search) ?>"
                    >

                </div>

                <div>

                    <label for="category">
                        Filter by category
                    </label>

                    <select
                        class="filter-control"
                        id="category"
                        name="category"
                    >

                        <option value="">All categories</option>

                        <?php foreach ($categories as $category): ?>

                            <option
                                value="<?= (int)$category["id"] ?>"
                                <?= $filterCategory ===
                                (string)$category["id"] ? "selected" : "" ?>
                            >

                                <?= e($category["category_name"]) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <button type="submit">
                    Search / Filter
                </button>

                <a class="button cancel" href="books.php?view=all">
                    Clear
                </a>

            </form>

            <div class="table-wrap">

                <?php if (count($books) > 0): ?>

                    <table>

                        <thead>

                            <tr>
                                <th>Cover</th>
                                <th>Title</th>
                                <th>Author</th>
                                <th>Category</th>
                                <th>ISBN</th>
                                <th>Price</th>
                                <th>Stock Status</th>
                                <th>Actions</th>
                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($books as $book): ?>

                                <tr>

                                    <td>

                                        <?php if (!empty($book["cover_image"])): ?>

                                            <img
                                                class="cover"
                                                src="<?= e(
                                                    $uploadUrl .
                                                    rawurlencode(basename($book["cover_image"]))
                                                ) ?>"
                                                alt="Book cover"
                                            >

                                        <?php else: ?>

                                            No image

                                        <?php endif; ?>

                                    </td>

                                    <td><?= e($book["title"]) ?></td>

                                    <td><?= e($book["author"]) ?></td>

                                    <td>
                                        <?= e($book["category_name"] ?? "Uncategorized") ?>
                                    </td>

                                    <td><?= e($book["isbn"] ?? "—") ?></td>

                                    <td>
                                        ₹<?= number_format((float)$book["price"], 2) ?>
                                    </td>

                                    <td>

                                        <?php if ((int)$book["stock"] === 0): ?>

                                            <span class="stock-badge stock-out">
                                                Out of Stock
                                            </span>

                                        <?php elseif ((int)$book["stock"] <= 5): ?>

                                            <span class="stock-badge stock-low">
                                                Low: <?= (int)$book["stock"] ?>
                                            </span>

                                        <?php else: ?>

                                            <span class="stock-badge stock-ok">
                                                In Stock: <?= (int)$book["stock"] ?>
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td>

                                        <div class="actions">

                                            <a
                                                class="button"
                                                href="books.php?view=add&edit=<?= (int)$book["id"] ?>"
                                            >
                                                Edit
                                            </a>

                                            <form
                                                method="POST"
                                                action="books.php?view=all"
                                                onsubmit="return confirm('Are you sure you want to delete this book?');"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= e($_SESSION["books_csrf"]) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="delete"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value="<?= (int)$book["id"] ?>"
                                                >

                                                <button type="submit" class="delete">
                                                    Delete
                                                </button>

                                            </form>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                <?php else: ?>

                    <p class="empty">
                        No books match your search or category.
                        Try another search or clear the filters.
                    </p>

                <?php endif; ?>

            </div>

        </section>

    <?php endif; ?>

    <!-- OUT OF STOCK BOOKS SCREEN -->

    <?php if ($view === "out"): ?>

        <section class="panel" id="out-of-stock-books">

            <h2>
                Out-of-Stock Books (<?= count($outOfStockBooks) ?>)
            </h2>

            <p class="empty" style="margin: 12px 0 20px;">
                Only books with zero copies available are displayed here.
            </p>

            <?php if (count($outOfStockBooks) > 0): ?>

                <div class="table-wrap">

                    <table>

                        <thead>

                            <tr>
                                <th>Cover</th>
                                <th>Book Title</th>
                                <th>Author</th>
                                <th>Category</th>
                                <th>ISBN</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Action</th>
                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($outOfStockBooks as $book): ?>

                                <tr>

                                    <td>

                                        <?php if (!empty($book["cover_image"])): ?>

                                            <img
                                                class="cover"
                                                src="<?= e(
                                                    $uploadUrl .
                                                    rawurlencode(basename($book["cover_image"]))
                                                ) ?>"
                                                alt="Book cover"
                                            >

                                        <?php else: ?>

                                            No image

                                        <?php endif; ?>

                                    </td>

                                    <td><?= e($book["title"]) ?></td>

                                    <td><?= e($book["author"]) ?></td>

                                    <td>
                                        <?= e($book["category_name"] ?? "Uncategorized") ?>
                                    </td>

                                    <td><?= e($book["isbn"] ?? "—") ?></td>

                                    <td>
                                        ₹<?= number_format((float)$book["price"], 2) ?>
                                    </td>

                                    <td>

                                        <span class="stock-badge stock-out">
                                            Out of Stock (0)
                                        </span>

                                    </td>

                                    <td>

                                        <a
                                            class="button"
                                            href="books.php?view=add&edit=<?= (int)$book["id"] ?>"
                                        >
                                            Update Stock
                                        </a>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <p class="empty">
                    Great! There are currently no out-of-stock books.
                </p>

            <?php endif; ?>

        </section>

    <?php endif; ?>

</main>

</body>
</html>