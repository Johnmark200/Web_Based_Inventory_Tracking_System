<?php
include 'db.php';
requireLogin();

$errors = [];
$formData = [
    'name' => '',
    'category_id' => '',
    'stock_quantity' => 0,
    'description' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validation = validateProductInput($_POST);
    $errors = $validation['errors'];
    $formData = $validation['data'];

    if (!$errors) {
        $duplicateCheck = $conn->prepare('SELECT id FROM products WHERE name = ? LIMIT 1');
        $duplicateCheck->bind_param('s', $formData['name']);
        $duplicateCheck->execute();
        $duplicateExists = $duplicateCheck->get_result()->fetch_assoc();
        $duplicateCheck->close();

        if ($duplicateExists) {
            $errors[] = 'A product with that name already exists.';
        } else {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare(
                    'INSERT INTO products (name, category_id, stock_quantity, description, quantity, price)
                     VALUES (?, ?, ?, ?, 0, 0)'
                );
                $stmt->bind_param(
                    'siis',
                    $formData['name'],
                    $formData['category_id'],
                    $formData['stock_quantity'],
                    $formData['description']
                );
                $stmt->execute();
                $productId = $stmt->insert_id;
                $stmt->close();

                $history = $conn->prepare(
                    'INSERT INTO stock_history (product_id, change_quantity, previous_quantity, new_quantity, action_type)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $actionType = 'create';
                $previousQuantity = 0;
                $history->bind_param(
                    'iiiis',
                    $productId,
                    $formData['stock_quantity'],
                    $previousQuantity,
                    $formData['stock_quantity'],
                    $actionType
                );
                $history->execute();
                $history->close();

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
    <title>Add Product</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="page-shell">
        <section class="form-panel">
            <div class="panel-header">
                <div>
                    <p class="eyebrow">Create</p>
                    <h1>Add Product</h1>
                    <p>Save a new inventory item with category, stock level, and optional details.</p>
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
                    <button type="submit" class="btn btn-primary">Save Product</button>
                </div>
            </form>
        </section>
    </div>
</body>
</html>
