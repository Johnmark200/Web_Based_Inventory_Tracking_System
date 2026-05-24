<?php
include 'db.php';
requireLogin();

$productId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$productId) {
    redirect('index.php');
}

$productStmt = $conn->prepare('SELECT stock_quantity FROM products WHERE id = ? LIMIT 1');
$productStmt->bind_param('i', $productId);
$productStmt->execute();
$product = $productStmt->get_result()->fetch_assoc();
$productStmt->close();

if ($product) {
    $conn->begin_transaction();
    try {
        $history = $conn->prepare(
            'INSERT INTO stock_history (product_id, change_quantity, previous_quantity, new_quantity, action_type)
             VALUES (?, ?, ?, ?, ?)'
        );
        $actionType = 'delete';
        $previousQuantity = (int) $product['stock_quantity'];
        $changeQuantity = $previousQuantity * -1;
        $newQuantity = 0;
        $history->bind_param('iiiis', $productId, $changeQuantity, $previousQuantity, $newQuantity, $actionType);
        $history->execute();
        $history->close();

        $deleteStmt = $conn->prepare('DELETE FROM products WHERE id = ?');
        $deleteStmt->bind_param('i', $productId);
        $deleteStmt->execute();
        $deleteStmt->close();

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

redirect('index.php');
?>
