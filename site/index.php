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
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 40px; }
        .shop-title { color: #333; text-align: center; }
        .product-container { display: flex; gap: 20px; justify-content: center; margin-top: 30px; }
        .product-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 250px; text-align: center; }
        .product-image { width: 100%; height: 150px; background: #ddd; display: flex; align-items: center; justify-content: center; border-radius: 4px; overflow: hidden; margin-bottom: 15px; }
        .product-image img { max-width: 100%; max-height: 100%; object-fit: contain; }
        .price { color: #2ecc71; font-weight: bold; font-size: 18px; margin: 10px 0; }
        .btn { background: #3498db; color: white; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; }
    </style>
</head>
<body>

    <h1 class="shop-title">📦 Интернет-Каталог работает!</h1>

    <div class="product-container">
        <?php if (empty($products)): ?>
            <p>В каталоге пока нет товаров.</p>
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
