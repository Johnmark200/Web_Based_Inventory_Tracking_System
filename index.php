<?php
include 'db.php';
requireLogin();

$lowStockThreshold = 5;
$search = trim($_GET['search'] ?? '');
$categoryFilter = (int) ($_GET['category'] ?? 0);
$currentUser = currentUser($conn);

$categories = $conn->query('SELECT id, name FROM categories ORDER BY name ASC');

$summary = $conn->query(
    "SELECT
        COUNT(*) AS total_products,
        COALESCE(SUM(stock_quantity), 0) AS total_units,
        SUM(CASE WHEN stock_quantity <= {$lowStockThreshold} THEN 1 ELSE 0 END) AS low_stock_count
     FROM products"
)->fetch_assoc();

$changeSummary = $conn->query(
    'SELECT COALESCE(SUM(change_quantity), 0) AS net_stock_change, COUNT(*) AS total_changes
     FROM stock_history'
)->fetch_assoc();

$sql = 'SELECT p.id, p.name, p.stock_quantity, p.description, c.name AS category_name
        FROM products p
        INNER JOIN categories c ON c.id = p.category_id';

if ($search !== '' && $categoryFilter > 0) {
    $sql .= ' WHERE (p.name LIKE ? OR c.name LIKE ? OR p.description LIKE ?) AND p.category_id = ?
              ORDER BY p.id DESC, p.name ASC';
    $stmt = $conn->prepare($sql);
    $likeSearch = '%' . $search . '%';
    $stmt->bind_param('sssi', $likeSearch, $likeSearch, $likeSearch, $categoryFilter);
} elseif ($search !== '') {
    $sql .= ' WHERE p.name LIKE ? OR c.name LIKE ? OR p.description LIKE ?
              ORDER BY p.id DESC, p.name ASC';
    $stmt = $conn->prepare($sql);
    $likeSearch = '%' . $search . '%';
    $stmt->bind_param('sss', $likeSearch, $likeSearch, $likeSearch);
} elseif ($categoryFilter > 0) {
    $sql .= ' WHERE p.category_id = ? ORDER BY p.id DESC, p.name ASC';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $categoryFilter);
} else {
    $sql .= ' ORDER BY p.id DESC, p.name ASC';
    $stmt = $conn->prepare($sql);
}

$stmt->execute();
$products = $stmt->get_result();

$recentChanges = $conn->query(
    'SELECT h.created_at, h.action_type, h.change_quantity, h.previous_quantity, h.new_quantity, COALESCE(p.name, "[Deleted product]") AS name
     FROM stock_history h
     LEFT JOIN products p ON p.id = h.product_id
     ORDER BY h.created_at DESC, h.id DESC
     LIMIT 8'
);

$formatProductNumber = static function (int $id): string {
    return str_pad((string) $id, 12, '0', STR_PAD_LEFT);
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="page-shell">
        <header class="hero">
            <div>
                <p class="eyebrow">Inventory Tracking</p>
                <h1>Inventory Dashboard</h1>
                <p class="hero-copy">Monitor products, current stock levels, and recent stock movement from one view.</p>
            </div>
            <div class="hero-actions">
                <span class="user-chip"><?php echo h($currentUser['name'] ?? 'User'); ?></span>
                <a href="add_product.php" class="btn btn-primary">Add Product</a>
                <a href="logout.php" class="btn btn-secondary">Logout</a>
            </div>
        </header>

        <section class="summary-grid">
            <article class="summary-card">
                <span>Total Products</span>
                <strong><?php echo (int) $summary['total_products']; ?></strong>
            </article>
            <article class="summary-card">
                <span>Total Units In Stock</span>
                <strong><?php echo (int) $summary['total_units']; ?></strong>
            </article>
            <article class="summary-card">
                <span>Low Stock Items</span>
                <strong><?php echo (int) $summary['low_stock_count']; ?></strong>
            </article>
            <article class="summary-card">
                <span>Net Stock Change</span>
                <strong><?php echo (int) $changeSummary['net_stock_change']; ?></strong>
            </article>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2>Products</h2>
                    <p>Search or filter the current inventory list.</p>
                </div>
            </div>
            <form method="get" class="filter-form">
                <input type="text" name="search" placeholder="Search name, category, description" value="<?php echo h($search); ?>">
                <select name="category">
                    <option value="0">All Categories</option>
                    <?php while ($category = $categories->fetch_assoc()): ?>
                        <option value="<?php echo (int) $category['id']; ?>" <?php echo $categoryFilter === (int) $category['id'] ? 'selected' : ''; ?>>
                            <?php echo h($category['name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <button type="submit" class="btn btn-secondary">Apply</button>
            </form>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Product ID</th>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Remaining Stock</th>
                            <th>Details</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($products->num_rows === 0): ?>
                            <tr>
                                <td colspan="6" class="empty-state">No products found.</td>
                            </tr>
                        <?php endif; ?>
                        <?php while ($row = $products->fetch_assoc()): ?>
                            <tr class="<?php echo (int) $row['stock_quantity'] <= $lowStockThreshold ? 'low-stock-row' : ''; ?>">
                                <td class="product-id"><?php echo h($formatProductNumber((int) $row['id'])); ?></td>
                                <td><?php echo h($row['name']); ?></td>
                                <td><?php echo h($row['category_name']); ?></td>
                                <td>
                                    <span class="stock-pill <?php echo (int) $row['stock_quantity'] <= $lowStockThreshold ? 'stock-low' : 'stock-ok'; ?>">
                                        <?php echo (int) $row['stock_quantity']; ?>
                                    </span>
                                </td>
                                <td><?php echo h($row['description'] ?: 'No details provided'); ?></td>
                                <td class="actions">
                                    <?php
                                    $needsRestock = (int) $row['stock_quantity'] <= $lowStockThreshold;
                                    $restockMessage = $needsRestock
                                        ? $row['name'] . ' stock needs to be restocked.'
                                        : $row['name'] . ' stock is sufficient.';
                                    ?>
                                    <button
                                        type="button"
                                        class="restock-indicator <?php echo $needsRestock ? 'restock-warning' : 'restock-ok'; ?>"
                                        title="Stock notification"
                                        aria-label="Stock notification"
                                        data-restock-message="<?php echo h($restockMessage); ?>"
                                    >
                                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                            <path d="M12 22a2.25 2.25 0 0 0 2.236-2H9.764A2.25 2.25 0 0 0 12 22Zm7-6.75V14a7 7 0 1 0-14 0v1.25L3 18v1h18v-1l-2-2.75Zm-2.2 1.5H7.2L8 16.4V14a4 4 0 1 1 8 0v2.4l.8 1.35Z"/>
                                        </svg>
                                    </button>
                                    <a href="edit_product.php?id=<?php echo (int) $row['id']; ?>">Edit</a>
                                    <a href="delete_product.php?id=<?php echo (int) $row['id']; ?>" data-confirm-delete="Delete this product?">Delete</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2>Basic Inventory Report</h2>
                    <p>Recent stock changes for auditing and quick review.</p>
                </div>
                <span class="report-badge"><?php echo (int) $changeSummary['total_changes']; ?> change records</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Product</th>
                            <th>Action</th>
                            <th>Change</th>
                            <th>Previous</th>
                            <th>New</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recentChanges->num_rows === 0): ?>
                            <tr>
                                <td colspan="6" class="empty-state">No stock history available yet.</td>
                            </tr>
                        <?php endif; ?>
                        <?php while ($history = $recentChanges->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo h($history['created_at']); ?></td>
                                <td><?php echo h($history['name']); ?></td>
                                <td><?php echo h(ucfirst($history['action_type'])); ?></td>
                                <td><?php echo (int) $history['change_quantity']; ?></td>
                                <td><?php echo (int) $history['previous_quantity']; ?></td>
                                <td><?php echo (int) $history['new_quantity']; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
    <script src="scripts.js"></script>
</body>
</html>
