<?php
session_start();

// Defense-in-depth: помимо Nginx basic-auth проверяем сессию
if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit;
}

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
            $s3Client = new S3Client([
                'version'                   => 'latest',
                'region'                    => 'us-east-1',
                'endpoint'                  => 'http://seaweedfs:8333',
                'use_path_style_endpoint'   => true,
                'credentials'               => [
                    'key'    => getenv('SEAWEED_ACCESS_KEY'),
                    'secret' => getenv('SEAWEED_SECRET_KEY'),
                ],
            ]);

            $result = $s3Client->putObject([
                'Bucket'      => 'shop-products',
                'Key'         => $fileName,
                'SourceFile'  => $fileTmpPath,
                'ContentType' => $_FILES['image']['type']
            ]);

            $host = 'mysql-db';
            $db   = getenv('MYSQL_DATABASE');
            $user = getenv('MYSQL_USER');
            $pass = getenv('MYSQL_PASSWORD');

            $imageUrl = '/' . $fileName;
            $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            $stmt = $pdo->prepare("INSERT INTO products (name, price, image_url) VALUES (?, ?, ?)");
            $stmt->execute([$name, $price, $imageUrl]);
            $success = "🎉 Успешно! Файл загружен в SeaweedFS. Товар на витрине!";
        } catch (AwsException $e) {
            $error = 'Ошибка AWS S3 SDK: ' . $e->getAwsErrorMessage();
        } catch (\PDOException $e) {
            $error = 'Ошибка записи в базу данных: ' . $e->getMessage();
        } catch (\Exception $e) {
            $error = 'Ошибка: ' . $e->getMessage();
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
    <title>Админка — Добавление товара (SeaweedFS)</title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>
    <div style="text-align: center; margin-bottom: 20px;">
        <a href="/index" style="color: #3498db; text-decoration: none; font-weight: bold;">← На главную витрину</a>
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
</html>
