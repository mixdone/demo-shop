<?php
session_start();

$host = 'mysql-db';
$db   = getenv('MYSQL_DATABASE');
$user = getenv('MYSQL_USER');
$pass = getenv('MYSQL_PASSWORD');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy_product_id'])) {
        if (!isset($_SESSION['user_id'])) {
            header("Location: /login");
            exit;
        }

        $userId = $_SESSION['user_id'];
        $productId = intval($_POST['buy_product_id']);

        $orderPayload = json_encode([
            'user_id'    => $userId,
            'product_id' => $productId,
            'timestamp'  => time()
        ]);

        $conf = new RdKafka\Conf();
        $conf->set('metadata.broker.list', 'kafka-broker:9092');
        $conf->set('enable.idempotence', 'true');
        $conf->set('acks', 'all');
        $conf->set('message.timeout.ms', '5000');

        $delivered = false;
        $conf->setDrMsgCb(function ($kafka, $message) use (&$delivered) {
            if ($message->err) {
                error_log("Kafka delivery failed: " . $message->errstr());
            } else {
                $delivered = true;
            }
        });

        $producer = new RdKafka\Producer($conf);
        $topic = $producer->newTopic("new-orders");
        $topic->produce(RD_KAFKA_PARTITION_UA, 0, $orderPayload);
        $producer->flush(5000);

        if ($delivered) {
            $_SESSION['flash_message'] = "🚀 Заказ успешно отправлен в обработку";
        } else {
            $_SESSION['flash_message'] = "⚠️ Не удалось отправить заказ, попробуйте позже";
        }

        header("Location: /index");
        exit;
    }

    $cartCount = 0;
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $cartCount = $stmt->fetchColumn();
    }

    $products = $pdo->query("SELECT * FROM products ORDER BY id DESC")->fetchAll();

} catch (\PDOException $e) {
    die("<h1>Ошибка платформы:</h1> " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>DevOps Demo Shop — Kafka Edition</title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>

    <div style="display: flex; justify-content: space-between; align-items: center; background: white; padding: 15px 40px; margin:-40px -40px 40px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
        <h2 style="margin:0; color:#2c3e50;">📦 DevOps Shop <span style="font-size:12px; color:#9b59b6; vertical-align:super;">Asynchronous Inside</span></h2>
        <div style="font-size: 15px;">
            <?php if (isset($_SESSION['username'])): ?>
                Привет, <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>!
                (Успешных заказов в БД: <span style="color:#27ae60; font-weight:bold;"><?= $cartCount ?></span>) |
                <a href="/admin" style="color:#3498db; font-weight:bold; text-decoration:none; margin-right:10px;">Админка</a> |
                <a href="/logout" style="color:#e74c3c; text-decoration:none;">Выйти</a>
            <?php else: ?>
                Вы вошли как гость. | <a href="/login" style="color:#3498db; font-weight:bold; text-decoration:none;">Войти</a> или <a href="/register" style="text-decoration:none;">Регистрация</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($_SESSION['flash_message'])): ?>
        <div class="success-msg" style="max-width: 550px; margin: 0 auto 20px; background:#f5eef8; border:1px solid #9b59b6; padding:15px; border-radius:8px; color:#8e44ad;">
            <?= htmlspecialchars($_SESSION['flash_message']) ?>
        </div>
        <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>

    <h1 class="shop-title">📦 Витрина</h1>

    <div class="product-container">
        <?php foreach ($products as $product): ?>
            <div class="product-card">
                <div class="product-image">
                    <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                </div>
                <h3><?= htmlspecialchars($product['name']) ?></h3>
                <div class="price">$<?= htmlspecialchars($product['price']) ?></div>

                <form action="/index" method="POST">
                    <input type="hidden" name="buy_product_id" value="<?= $product['id'] ?>">
                    <button type="submit" class="btn" style="background:#9b59b6;">Купить</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>

</body>
</html>