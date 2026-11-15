
<?php

session_start();

header("Content-Type: application/json");

require_once "../config/db.php";

// Check database connection
if (!isset($conn) || $conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database connection failed."
    ]);
    exit;
}

// Require logged-in user
if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Please log in to use your shopping cart."
    ]);
    exit;
}

$userId = (int) $_SESSION["user_id"];

function respond($success, $message, $data = []) {
    echo json_encode([
        "success" => $success,
        "message" => $message,
        "data" => $data
    ]);
    exit;
}

// Get or create the user's cart
function getCartId($conn, $userId) {

    $stmt = $conn->prepare(
        "SELECT id FROM carts WHERE user_id = ?"
    );

    $stmt->bind_param("i", $userId);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $stmt->close();
        return (int) $row["id"];
    }

    $stmt->close();

    $stmt = $conn->prepare(
        "INSERT INTO carts (user_id) VALUES (?)"
    );

    $stmt->bind_param("i", $userId);
    $stmt->execute();

    $cartId = $conn->insert_id;

    $stmt->close();

    return $cartId;
}

$cartId = getCartId($conn, $userId);

$method = $_SERVER["REQUEST_METHOD"];

// GET: View cart items
if ($method === "GET") {

    $stmt = $conn->prepare(
        "SELECT
            ci.id,
            ci.book_id,
            ci.quantity,
            b.title,
            b.author,
            b.price,
            b.cover_image,
            (ci.quantity * b.price) AS subtotal
         FROM cart_items ci
         JOIN books b ON ci.book_id = b.id
         WHERE ci.cart_id = ?
         ORDER BY ci.id DESC"
    );

    $stmt->bind_param("i", $cartId);
    $stmt->execute();

    $result = $stmt->get_result();

    $items = [];
    $total = 0;

    while ($row = $result->fetch_assoc()) {
        $row["price"] = (float) $row["price"];
        $row["subtotal"] = (float) $row["subtotal"];
        $row["quantity"] = (int) $row["quantity"];

        $total += $row["subtotal"];
        $items[] = $row;
    }

    $stmt->close();

    respond(true, "Cart loaded successfully.", [
        "items" => $items,
        "total" => round($total, 2),
        "item_count" => count($items)
    ]);
}

// POST: Add, update, remove or clear items
if ($method === "POST") {

    $input = json_decode(file_get_contents("php://input"), true);

    if (!is_array($input)) {
        respond(false, "Invalid request data.");
    }

    $action = $input["action"] ?? "";

    // Add book to cart
    if ($action === "add") {

        $bookId = filter_var(
            $input["book_id"] ?? null,
            FILTER_VALIDATE_INT
        );

        $quantity = filter_var(
            $input["quantity"] ?? 1,
            FILTER_VALIDATE_INT
        );

        if (!$bookId || !$quantity || $quantity < 1 || $quantity > 99) {
            respond(false, "Invalid book or quantity.");
        }

        $check = $conn->prepare(
            "SELECT id FROM books WHERE id = ?"
        );

        $check->bind_param("i", $bookId);
        $check->execute();

        $bookResult = $check->get_result();
        $bookExists = $bookResult->num_rows > 0;

        $check->close();

        if (!$bookExists) {
            respond(false, "Book not found.");
        }

        $stmt = $conn->prepare(
            "INSERT INTO cart_items (cart_id, book_id, quantity)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)"
        );

        $stmt->bind_param(
            "iii",
            $cartId,
            $bookId,
            $quantity
        );

        $stmt->execute();
        $stmt->close();

        respond(true, "Book added to cart.");
    }

    // Update item quantity
    if ($action === "update") {

        $bookId = filter_var(
            $input["book_id"] ?? null,
            FILTER_VALIDATE_INT
        );

        $quantity = filter_var(
            $input["quantity"] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$bookId || !$quantity || $quantity < 1 || $quantity > 99) {
            respond(false, "Invalid quantity.");
        }

        $stmt = $conn->prepare(
            "UPDATE cart_items
             SET quantity = ?
             WHERE cart_id = ? AND book_id = ?"
        );

        $stmt->bind_param(
            "iii",
            $quantity,
            $cartId,
            $bookId
        );

        $stmt->execute();
        $stmt->close();

        respond(true, "Cart quantity updated.");
    }

    // Remove item
    if ($action === "remove") {

        $bookId = filter_var(
            $input["book_id"] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$bookId) {
            respond(false, "Invalid book ID.");
        }

        $stmt = $conn->prepare(
            "DELETE FROM cart_items
             WHERE cart_id = ? AND book_id = ?"
        );

        $stmt->bind_param(
            "ii",
            $cartId,
            $bookId
        );

        $stmt->execute();
        $stmt->close();

        respond(true, "Book removed from cart.");
    }

    // Clear cart
    if ($action === "clear") {

        $stmt = $conn->prepare(
            "DELETE FROM cart_items WHERE cart_id = ?"
        );

        $stmt->bind_param("i", $cartId);
        $stmt->execute();
        $stmt->close();

        respond(true, "Cart cleared successfully.");
    }

    respond(false, "Unknown cart action.");
}

http_response_code(405);

respond(false, "Method not allowed.");

?>
