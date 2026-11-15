<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../config/db.php";

header("Content-Type: application/json; charset=UTF-8");


function sendWishlistResponse(
    $success,
    $message,
    $data = [],
    $status = 200
) {
    http_response_code($status);

    echo json_encode([
        "success" => $success,
        "message" => $message,
        "data" => $data
    ]);

    exit;
}


// ==========================================
// CUSTOMER LOGIN CHECK
// ==========================================

if (empty($_SESSION["user_id"])) {
    sendWishlistResponse(
        false,
        "Please log in to use your wishlist.",
        [],
        401
    );
}

$role = strtolower(
    trim((string)($_SESSION["role"] ?? ""))
);

if ($role !== "customer") {
    sendWishlistResponse(
        false,
        "Only customers can access the wishlist.",
        [],
        403
    );
}

$userId = (int)$_SESSION["user_id"];


// ==========================================
// GET - LOAD WISHLIST BOOKS
// ==========================================

if ($_SERVER["REQUEST_METHOD"] === "GET") {

    $sql = "
        SELECT
            w.id AS wishlist_id,
            b.id AS book_id,
            b.title,
            b.author,
            b.price,
            b.cover_image,
            b.stock,
            w.created_at

        FROM wishlist AS w

        INNER JOIN books AS b
            ON b.id = w.book_id

        WHERE w.user_id = ?

        ORDER BY w.created_at DESC
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        sendWishlistResponse(
            false,
            "Unable to load your wishlist.",
            [],
            500
        );
    }

    $stmt->bind_param("i", $userId);

    if (!$stmt->execute()) {
        $stmt->close();

        sendWishlistResponse(
            false,
            "Unable to retrieve wishlist books.",
            [],
            500
        );
    }

    $result = $stmt->get_result();

    $books = [];

    while ($row = $result->fetch_assoc()) {

        $cover = trim(
            (string)($row["cover_image"] ?? "")
        );

        if ($cover === "") {

            $row["cover_url"] = "";

        } elseif (
            preg_match('~^https?://~i', $cover) ||
            strpos($cover, "uploads/") === 0 ||
            strpos($cover, "assets/") === 0 ||
            strpos($cover, "/") === 0
        ) {

            $row["cover_url"] = $cover;

        } else {

            $row["cover_url"] =
                "uploads/book_covers/" .
                rawurlencode(basename($cover));
        }

        $books[] = $row;
    }

    $stmt->close();

    sendWishlistResponse(
        true,
        "Wishlist loaded successfully.",
        $books
    );
}


// ==========================================
// POST - ADD OR REMOVE WISHLIST BOOK
// ==========================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $input = json_decode(
        file_get_contents("php://input"),
        true
    );

    if (!is_array($input)) {
        sendWishlistResponse(
            false,
            "Invalid request data.",
            [],
            400
        );
    }

    $action = $input["action"] ?? "";
    $bookId = (int)($input["book_id"] ?? 0);

    if ($bookId <= 0) {
        sendWishlistResponse(
            false,
            "Invalid book selected.",
            [],
            400
        );
    }


    // CHECK IF BOOK EXISTS
    $check = $conn->prepare(
        "SELECT id FROM books WHERE id = ?"
    );

    if (!$check) {
        sendWishlistResponse(
            false,
            "Database error.",
            [],
            500
        );
    }

    $check->bind_param("i", $bookId);
    $check->execute();

    $book = $check->get_result()->fetch_assoc();

    $check->close();

    if (!$book) {
        sendWishlistResponse(
            false,
            "Book not found.",
            [],
            404
        );
    }


    // ======================================
    // ADD TO WISHLIST
    // ======================================

    if ($action === "add") {

        // Check whether the book is already saved.
        $existing = $conn->prepare(
            "SELECT id
             FROM wishlist
             WHERE user_id = ? AND book_id = ?
             LIMIT 1"
        );

        if (!$existing) {
            sendWishlistResponse(
                false,
                "Unable to check your wishlist.",
                [],
                500
            );
        }

        $existing->bind_param(
            "ii",
            $userId,
            $bookId
        );

        $existing->execute();

        $alreadySaved = $existing
            ->get_result()
            ->fetch_assoc();

        $existing->close();

        if ($alreadySaved) {
            sendWishlistResponse(
                true,
                "This book is already in your wishlist."
            );
        }


        // Save the new wishlist entry.
        $stmt = $conn->prepare(
            "INSERT INTO wishlist (user_id, book_id)
             VALUES (?, ?)"
        );

        if (!$stmt) {
            sendWishlistResponse(
                false,
                "Unable to add book to wishlist.",
                [],
                500
            );
        }

        $stmt->bind_param(
            "ii",
            $userId,
            $bookId
        );

        if (!$stmt->execute()) {
            $stmt->close();

            sendWishlistResponse(
                false,
                "Unable to save this book.",
                [],
                500
            );
        }

        $stmt->close();

        sendWishlistResponse(
            true,
            "Book added to your wishlist."
        );
    }


    // ======================================
    // REMOVE FROM WISHLIST
    // ======================================

    if ($action === "remove") {

        $stmt = $conn->prepare(
            "DELETE FROM wishlist
             WHERE user_id = ? AND book_id = ?"
        );

        if (!$stmt) {
            sendWishlistResponse(
                false,
                "Unable to remove book.",
                [],
                500
            );
        }

        $stmt->bind_param(
            "ii",
            $userId,
            $bookId
        );

        if (!$stmt->execute()) {
            $stmt->close();

            sendWishlistResponse(
                false,
                "Unable to remove book.",
                [],
                500
            );
        }

        $stmt->close();

        sendWishlistResponse(
            true,
            "Book removed from your wishlist."
        );
    }


    sendWishlistResponse(
        false,
        "Invalid wishlist action.",
        [],
        400
    );
}


// ==========================================
// UNSUPPORTED REQUEST
// ==========================================

sendWishlistResponse(
    false,
    "Request method not allowed.",
    [],
    405
);

?>