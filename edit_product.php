<?php
include 'db.php';
requireLogin();

$productId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$productId) {
    redirect('index.php');
}

$productStmt = $conn->prepare(
    'SELECT id, name, category_id, stock_quantity, description
     FROM products
     WHERE id = ? LIMIT 1'
);
$productStmt->bind_param('i', $productId);
$productStmt->execute();
$product = $productStmt->get_result()->fetch_assoc();
$productStmt->close();

if (!$product) {
    redirect('index.php');
}

$errors = [];
$formData = $product;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validation = validateProductInput($_POST);
    $errors = $validation['errors'];
    $formData = $validation['data'];

    if (!$errors) {
        $duplicateCheck = $conn->prepare('SELECT id FROM products WHERE name = ? AND id <> ? LIMIT 1');
        $duplicateCheck->bind_param('si', $formData['name'], $productId);
        $duplicateCheck->execute();
        $duplicateExists = $duplicateCheck->get_result()->fetch_assoc();
        $duplicateCheck->close();

        if ($duplicateExists) {
            $errors[] = 'A product with that name already exists.';
        } else {
            $conn->begin_transaction();
            try {
                $updateStmt = $conn->prepare(
                    'UPDATE products
                     SET name = ?, category_id = ?, stock_quantity = ?, description = ?, quantity = 0, price = 0
                     WHERE id = ?'
                );
                $updateStmt->bind_param(
                    'siisi',
                    $formData['name'],
                    $formData['category_id'],
                    $formData['stock_quantity'],
                    $formData['description'],
                    $productId
                );
                $updateStmt->execute();
                $updateStmt->close();

                $stockChange = $formData['stock_quantity'] - (int) $product['stock_quantity'];
                if ($stockChange !== 0) {
                    $history = $conn->prepare(
                        'INSERT INTO stock_history (product_id, change_quantity, previous_quantity, new_quantity, action_type)
                         VALUES (?, ?, ?, ?, ?)'
                    );
                    $actionType = 'update';
                    $previousQuantity = (int) $product['stock_quantity'];
                    $history->bind_param(
                        'iiiis',
                        $productId,
                        $stockChange,
                        $previousQuantity,
                        $formData['stock_quantity'],
                        $actionType
                    );
                    $history->execute();
                    $history->close();
                }

                $conn->commit();
                redirect('index.php');
            } catch (Throwable $e) {
                $conn->rollback();
                throw $e;
            }
        }
    }
}

$categories = $conn->query('SELECT id, name FROM categories ORDER BY name ASC');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="page-shell">
        <section class="form-panel">
            <div class="panel-header">
                <div>
                    <p class="eyebrow">Update</p>
                    <h1>Edit Product</h1>
                    <p>Change product details and adjust the current stock quantity.</p>
                </div>
                <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
            </div>

            <?php if ($errors): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo h($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" class="product-form">
                <label>
                    Product Name
                    <input type="text" name="name" value="<?php echo h($formData['name']); ?>" required maxlength="150">
                </label>
                <label>
                    Category
                    <select name="category_id" required>
                        <option value="">Select a category</option>
                        <?php while ($category = $categories->fetch_assoc()): ?>
                            <option value="<?php echo (int) $category['id']; ?>" <?php echo (string) $formData['category_id'] === (string) $category['id'] ? 'selected' : ''; ?>>
                                <?php echo h($category['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </label>
                <label>
                    Stock Quantity
                    <input type="number" name="stock_quantity" value="<?php echo h((string) $formData['stock_quantity']); ?>" required min="0" step="1">
                </label>
                <label>
                    Description
                    <textarea name="description" rows="5" placeholder="Optional details"><?php echo h($formData['description']); ?></textarea>
                </label>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Update Product</button>
                </div>
            </form>
        </section>
    </div>
</body>
</html>
