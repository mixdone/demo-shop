<?php
$host = 'mysql-db'; 
$db   = getenv('MYSQL_DATABASE');
$user = getenv('MYSQL_USER');
$pass = getenv('MYSQL_PASSWORD');
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $products = $pdo->query("SELECT * FROM products")->fetchAll();
} catch (\PDOException $e) {
    die("<h1>Ошибка подключения к базе данных:</h1> " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Demo Shop</title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>

    <h1 class="shop-title">📦 Интернет-Магазин работает!</h1>
    <p class="shop-subtitle">Статика отдает Nginx, данные — MySQL, изображения — MinIO S3.</p>

    <div class="product-container">
        <?php if (empty($products)): ?>
            <p>В каталоге пока нет товаров. Проверьте инициализацию базы.</p>
        <?php else: ?>
            <?php foreach ($products as $product): ?>
                <div class="product-card">
                    <div class="product-image">
                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                    </div>
                    <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                    <div class="price">$<?php echo htmlspecialchars($product['price']); ?></div>
                    <button class="btn">Купить</button>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</body>
</html>
