<?php
// Подключаем автозагрузчик Composer, который Docker заботливо собрал для нас
require '/var/www/html/vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    
    if (!empty($name) && $price > 0 && isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['image']['tmp_name'];
        $fileName = time() . '_' . basename($_FILES['image']['name']);
        
        try {
            // 1. Инициализируем S3-клиент под параметрами нашего MinIO
            $s3Client = new S3Client([
                'version'     => 'latest',
                'region'      => 'us-east-1', // Для MinIO регион может быть любым дефолтным
                'endpoint'    => 'http://minio-s3:9000', // Имя сервиса во внутренней сети Docker
                'use_path_style_endpoint' => true,
                's3_us_path_style'        => true, 
                'credentials' => [
                    'key'    => getenv('MINIO_USER'),
                    'secret' => getenv('MINIO_PASSWORD'),
                ],
            ]);

            // 2. Безопасно загружаем файл в закрытый бакет через SDK
            $result = $s3Client->putObject([
                'Bucket'     => 'shop-products',
                'Key'        => $fileName,
                'SourceFile' => $fileTmpPath,
                'ContentType'=> $_FILES['image']['type']
            ]);

            // Если SDK не выбросил ошибку (Exception) — файл гарантированно в S3!
            $host = 'mysql-db';
            $db   = getenv('MYSQL_DATABASE');
            $user = getenv('MYSQL_USER');
            $pass = getenv('MYSQL_PASSWORD');
            
            // Благодаря твоему Nginx-прокси, на витрину пишем красивый урл
            $imageUrl = '/' . $fileName;
            
            $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            $stmt = $pdo->prepare("INSERT INTO products (name, price, image_url) VALUES (?, ?, ?)");
            $stmt->execute([$name, $price, $imageUrl]);
            
            $success = "🎉 Изумительно! AWS SDK успешно авторизовался в MinIO и загрузил файл. Товар на витрине!";

        } catch (AwsException $e) {
            $error = 'Ошибка AWS S3 SDK: ' . $e->getAwsErrorMessage();
        } catch (\PDOException $e) {
            $error = 'Ошибка записи в базу данных: ' . $e->getMessage();
        }
    } else {
        $error = 'Пожалуйста, заполните форму корректно и выберите файл';
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Админка — Добавление товара (AWS SDK)</title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>
    <div style="text-align: center; margin-bottom: 20px;">
        <a href="/" style="color: #3498db; text-decoration: none; font-weight: bold;">← На главную витрину</a>
    </div>

    <div class="form-card">
        <h2>➕ Добавить новый фрукт</h2>
        <?php if (!empty($error)): ?><div class="error-msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if (!empty($success)): ?><div class="success-msg"><?= htmlspecialchars($success) ?></div><?php endif; ?>

        <form action="/admin" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Название фрукта:</label>
                <input type="text" name="name" required placeholder="например, Лайм">
            </div>
            <div class="form-group">
                <label>Цена ($):</label>
                <input type="number" step="0.01" name="price" required placeholder="4.20">
            </div>
            <div class="form-group">
                <label>Картинка фрукта:</label>
                <input type="file" name="image" accept="image/*" required>
            </div>
            <button type="submit" class="btn">Загрузить через AWS SDK</button>
        </form>
    </div>
</body>
</body>
</html>
