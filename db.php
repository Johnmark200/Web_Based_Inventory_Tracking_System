<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$host = 'localhost';
$user = 'root';
$password = '';
$database = 'inventory_db';

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header("Location: {$path}");
    exit;
}

function requireLogin(): void
{
    if (empty($_SESSION['user_id'])) {
        redirect('login.php');
    }
}

function currentUser(mysqli $conn): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = $conn->prepare('SELECT id, name, email FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    return $user;
}

function tokenValue(int $bytes = 16): string
{
    return bin2hex(random_bytes($bytes));
}

function sendTwoFactorCode(mysqli $conn, int $userId, string $email): bool
{
    $code = (string) random_int(100000, 999999);
    $update = $conn->prepare(
        'UPDATE users
         SET two_factor_code = ?,
             two_factor_expires = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 10 MINUTE)
         WHERE id = ?'
    );
    $update->bind_param('si', $code, $userId);
    $update->execute();
    $update->close();

    return sendSmtpMail(
        $email,
        'Your 2FA Code',
        "Your 2FA code is: {$code}\nIt expires in 10 minutes."
    );
}

function smtpConfig(): array
{
    return [
        'host' => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
        'port' => (int) (getenv('SMTP_PORT') ?: 587),
        'username' => getenv('SMTP_USERNAME') ?: 'johnmarkomale200@gmail.com',
        'password' => getenv('SMTP_PASSWORD') ?: 'tgmhhrhucisdnsey',
        'from_email' => getenv('SMTP_FROM_EMAIL') ?: 'johnmarkomale200@gmail.com',
        'from_name' => getenv('SMTP_FROM_NAME') ?: 'Inventory System',
        'encryption' => strtolower(getenv('SMTP_ENCRYPTION') ?: 'tls'),
    ];
}

function smtpReadResponse($socket): array
{
    $response = '';
    $code = 0;

    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (preg_match('/^(\d{3})([ -])/', $line, $matches)) {
            $code = (int) $matches[1];
            if ($matches[2] === ' ') {
                break;
            }
        }
    }

    return [$code, $response];
}

function smtpCommand($socket, string $command, array $expectedCodes): array
{
    fwrite($socket, $command . "\r\n");
    [$code, $response] = smtpReadResponse($socket);

    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException("SMTP command failed: {$command} | {$response}");
    }

    return [$code, $response];
}

function sendSmtpMail(string $to, string $subject, string $body): bool
{
    try {
        $config = smtpConfig();

        if ($config['host'] === '' || $config['host'] === 'smtp.example.com') {
            return false;
        }

        $remote = (($config['encryption'] === 'ssl') ? 'ssl://' : 'tcp://') . $config['host'] . ':' . $config['port'];
        $socket = @stream_socket_client($remote, $errno, $errstr, 30, STREAM_CLIENT_CONNECT);
        if (!$socket) {
            return false;
        }

        stream_set_timeout($socket, 30);
        [$code] = smtpReadResponse($socket);
        if ($code !== 220) {
            fclose($socket);
            return false;
        }

        $hostName = $_SERVER['SERVER_NAME'] ?? 'localhost';
        smtpCommand($socket, "EHLO {$hostName}", [250]);

        if ($config['encryption'] === 'tls') {
            smtpCommand($socket, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);
                return false;
            }
            smtpCommand($socket, "EHLO {$hostName}", [250]);
        }

        if ($config['username'] !== '') {
            smtpCommand($socket, 'AUTH LOGIN', [334]);
            smtpCommand($socket, base64_encode($config['username']), [334]);
            smtpCommand($socket, base64_encode($config['password']), [235]);
        }

        $fromEmail = $config['from_email'] ?: $config['username'];
        $fromName = preg_replace('/[\r\n]+/', ' ', $config['from_name']);
        $subject = preg_replace('/[\r\n]+/', ' ', $subject);
        $body = preg_replace("/\r?\n/", "\r\n", $body);
        $body = preg_replace('/^\./m', '..', $body);

        smtpCommand($socket, "MAIL FROM:<{$fromEmail}>", [250]);
        smtpCommand($socket, "RCPT TO:<{$to}>", [250, 251]);
        smtpCommand($socket, 'DATA', [354]);

        $message = implode("\r\n", [
            "From: {$fromName} <{$fromEmail}>",
            "To: <{$to}>",
            "Subject: {$subject}",
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            '',
            $body,
            '.',
        ]);

        fwrite($socket, $message . "\r\n");
        [$finalCode] = smtpReadResponse($socket);
        smtpCommand($socket, 'QUIT', [221]);
        fclose($socket);

        return $finalCode === 250;
    } catch (Throwable $e) {
        return false;
    }
}

function foreignKeyExists(mysqli $conn, string $table, string $constraintName): bool
{
    $sql = 'SELECT COUNT(*) AS total
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND CONSTRAINT_NAME = ?
              AND CONSTRAINT_TYPE = "FOREIGN KEY"';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $table, $constraintName);
    $stmt->execute();
    $exists = (int) $stmt->get_result()->fetch_assoc()['total'] > 0;
    $stmt->close();

    return $exists;
}

function ensureUnsignedColumn(mysqli $conn, string $table, string $column, string $definition): void
{
    $stmt = $conn->prepare(
        'SELECT COLUMN_TYPE
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?'
    );
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $needsAlter = !$result || stripos($result['COLUMN_TYPE'], 'unsigned') === false;
    if ($needsAlter) {
        $conn->query("ALTER TABLE `{$table}` MODIFY `{$column}` {$definition}");
    }
}

function ensureUnsignedNotNullColumn(mysqli $conn, string $table, string $column, string $definition): void
{
    $stmt = $conn->prepare(
        'SELECT COLUMN_TYPE, IS_NULLABLE
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?'
    );
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $isUnsigned = $result && stripos($result['COLUMN_TYPE'], 'unsigned') !== false;
    $isNullable = $result && $result['IS_NULLABLE'] === 'YES';

    if (!$isUnsigned || $isNullable) {
        $conn->query("ALTER TABLE `{$table}` MODIFY `{$column}` {$definition}");
    }
}

function ensureNullableUnsignedColumn(mysqli $conn, string $table, string $column): void
{
    $stmt = $conn->prepare(
        'SELECT COLUMN_TYPE, IS_NULLABLE
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?'
    );
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $isUnsigned = $result && stripos($result['COLUMN_TYPE'], 'unsigned') !== false;
    $isNullable = $result && $result['IS_NULLABLE'] === 'YES';

    if (!$isUnsigned || !$isNullable) {
        $conn->query("ALTER TABLE `{$table}` MODIFY `{$column}` INT UNSIGNED NULL DEFAULT NULL");
    }
}

function ensureColumnExists(mysqli $conn, string $table, string $column, string $definition, string $afterColumn = ''): void
{
    $stmt = $conn->prepare(
        'SELECT COUNT(*) AS total
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?'
    );
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $exists = (int) $stmt->get_result()->fetch_assoc()['total'] > 0;
    $stmt->close();

    if (!$exists) {
        $position = $afterColumn !== '' ? " AFTER `{$afterColumn}`" : '';
        $conn->query("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}{$position}");
    }
}

function ensureColumnDefaultZero(mysqli $conn, string $table, string $column, string $afterColumn = ''): void
{
    $stmt = $conn->prepare(
        'SELECT COUNT(*) AS total
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?'
    );
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $exists = (int) $stmt->get_result()->fetch_assoc()['total'] > 0;
    $stmt->close();

    $position = $afterColumn !== '' ? " AFTER `{$afterColumn}`" : '';
    if ($exists) {
        $conn->query("ALTER TABLE `{$table}` MODIFY `{$column}` INT NOT NULL DEFAULT 0{$position}");
    } else {
        $conn->query("ALTER TABLE `{$table}` ADD COLUMN `{$column}` INT NOT NULL DEFAULT 0{$position}");
    }
}

function dropForeignKeys(mysqli $conn, string $table): void
{
    $stmt = $conn->prepare(
        'SELECT CONSTRAINT_NAME
         FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND REFERENCED_TABLE_NAME IS NOT NULL'
    );
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $constraintName = $row['CONSTRAINT_NAME'];
        $conn->query("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraintName}`");
    }

    $stmt->close();
}

function categoryIdByName(mysqli $conn, string $name): int
{
    $stmt = $conn->prepare('SELECT id FROM categories WHERE name = ? LIMIT 1');
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $category = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int) ($category['id'] ?? 0);
}

function resolveCategoryId(mysqli $conn, int $categoryId, string $categoryName = ''): int
{
    $categoryName = trim($categoryName);

    if ($categoryName !== '') {
        $existingCategoryId = categoryIdByName($conn, $categoryName);
        if ($existingCategoryId > 0) {
            return $existingCategoryId;
        }

        try {
            $stmt = $conn->prepare('INSERT INTO categories (name) VALUES (?)');
            $stmt->bind_param('s', $categoryName);
            $stmt->execute();
            $newCategoryId = (int) $stmt->insert_id;
            $stmt->close();

            return $newCategoryId;
        } catch (mysqli_sql_exception $e) {
            if ((int) $e->getCode() === 1062) {
                return categoryIdByName($conn, $categoryName);
            }

            throw $e;
        }
    }

    if ($categoryId <= 0) {
        return 0;
    }

    $stmt = $conn->prepare('SELECT id FROM categories WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $categoryId);
    $stmt->execute();
    $category = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int) ($category['id'] ?? 0);
}

function validateProductInput(array $input): array
{
    $errors = [];
    $name = trim($input['name'] ?? '');
    $categoryId = (int) ($input['category_id'] ?? 0);
    $categoryName = trim($input['category_name'] ?? '');
    $stockQuantity = filter_var($input['stock_quantity'] ?? null, FILTER_VALIDATE_INT);
    $description = trim($input['description'] ?? '');

    if ($name === '') {
        $errors[] = 'Product name is required.';
    }

    if ($categoryId <= 0 && $categoryName === '') {
        $errors[] = 'Select a category or enter a new one.';
    }

    if ($categoryName !== '' && mb_strlen($categoryName) > 100) {
        $errors[] = 'Category name must be 100 characters or fewer.';
    }

    if ($stockQuantity === false || $stockQuantity < 0) {
        $errors[] = 'Stock quantity must be a non-negative whole number.';
    }

    return [
        'errors' => $errors,
        'data' => [
            'name' => $name,
            'category_id' => $categoryId,
            'category_name' => $categoryName,
            'stock_quantity' => $stockQuantity === false ? 0 : $stockQuantity,
            'description' => $description,
        ],
    ];
}

$serverConnection = new mysqli($host, $user, $password);
$serverConnection->set_charset('utf8mb4');
$serverConnection->query(
    "CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
);
$serverConnection->close();

$conn = new mysqli($host, $user, $password, $database);
$conn->set_charset('utf8mb4');

$conn->query(
    'CREATE TABLE IF NOT EXISTS categories (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB'
);

$conn->query(
    'CREATE TABLE IF NOT EXISTS products (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL UNIQUE,
        category_id INT UNSIGNED NOT NULL,
        stock_quantity INT NOT NULL DEFAULT 0,
        description TEXT NULL,
        quantity INT NOT NULL DEFAULT 0,
        price INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT chk_stock_quantity CHECK (stock_quantity >= 0)
    ) ENGINE=InnoDB'
);

$conn->query(
    'CREATE TABLE IF NOT EXISTS stock_history (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        product_id INT UNSIGNED NULL DEFAULT NULL,
        change_quantity INT NOT NULL,
        previous_quantity INT NOT NULL,
        new_quantity INT NOT NULL,
        action_type VARCHAR(20) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB'
);

$conn->query(
    'CREATE TABLE IF NOT EXISTS users (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        is_verified TINYINT(1) NOT NULL DEFAULT 0,
        two_factor_code VARCHAR(6) DEFAULT NULL,
        two_factor_expires DATETIME DEFAULT NULL,
        reset_token VARCHAR(255) DEFAULT NULL,
        reset_expires DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB'
);

$categoryCount = (int) $conn->query('SELECT COUNT(*) AS total FROM categories')->fetch_assoc()['total'];
if ($categoryCount === 0) {
    $defaultCategories = ['Electronics', 'Office Supplies', 'Furniture', 'Accessories'];
    $stmt = $conn->prepare('INSERT INTO categories (name) VALUES (?)');
    foreach ($defaultCategories as $categoryName) {
        $stmt->bind_param('s', $categoryName);
        $stmt->execute();
    }
    $stmt->close();
}

dropForeignKeys($conn, 'stock_history');
dropForeignKeys($conn, 'products');

ensureUnsignedColumn($conn, 'categories', 'id', 'INT UNSIGNED NOT NULL AUTO_INCREMENT');
ensureUnsignedColumn($conn, 'products', 'id', 'INT UNSIGNED NOT NULL AUTO_INCREMENT');
ensureColumnExists($conn, 'products', 'category_id', 'INT UNSIGNED NULL DEFAULT NULL', 'name');
ensureColumnExists($conn, 'products', 'stock_quantity', 'INT NOT NULL DEFAULT 0', 'category_id');
ensureColumnExists($conn, 'products', 'description', 'TEXT NULL', 'stock_quantity');
ensureColumnDefaultZero($conn, 'products', 'quantity', 'description');
ensureColumnDefaultZero($conn, 'products', 'price', 'quantity');
ensureUnsignedColumn($conn, 'stock_history', 'id', 'INT UNSIGNED NOT NULL AUTO_INCREMENT');
ensureColumnExists($conn, 'stock_history', 'product_id', 'INT UNSIGNED NULL DEFAULT NULL', 'id');

$defaultCategoryRow = $conn->query('SELECT id FROM categories ORDER BY id ASC LIMIT 1')->fetch_assoc();
$defaultCategoryId = (int) ($defaultCategoryRow['id'] ?? 0);
if ($defaultCategoryId > 0) {
    $conn->query(
        'UPDATE products p
         LEFT JOIN categories c ON c.id = p.category_id
         SET p.category_id = ' . $defaultCategoryId . '
         WHERE p.category_id IS NULL
            OR c.id IS NULL'
    );
}

ensureUnsignedNotNullColumn($conn, 'products', 'category_id', 'INT UNSIGNED NOT NULL');
ensureNullableUnsignedColumn($conn, 'stock_history', 'product_id');

$conn->query(
    'DELETE h
     FROM stock_history h
     LEFT JOIN products p ON p.id = h.product_id
     WHERE h.product_id IS NOT NULL
       AND p.id IS NULL'
);

$conn->query(
    'ALTER TABLE products
     ADD CONSTRAINT fk_products_category
     FOREIGN KEY (category_id) REFERENCES categories(id)
     ON DELETE RESTRICT ON UPDATE CASCADE'
);

$conn->query(
    'ALTER TABLE stock_history
     ADD CONSTRAINT fk_history_product
     FOREIGN KEY (product_id) REFERENCES products(id)
     ON DELETE SET NULL ON UPDATE CASCADE'
);

?>
