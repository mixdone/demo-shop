<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        $host = 'mysql-db';
        $db   = getenv('MYSQL_DATABASE');
        $user = getenv('MYSQL_USER');
        $pass = getenv('MYSQL_PASSWORD');
        
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);

            // Проверяем, не занят ли логин
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = 'Этот логин уже занят';
            } else {
                // ХЭШИРУЕМ ПАРОЛЬ (DevOps-стандарт безопасности)
                $hash = password_hash($password, PASSWORD_BCRYPT);

                // Записываем пользователя в твою новую таблицу users
                $stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
                $stmt->execute([$username, $hash]);

                $success = '🎉 Аккаунт успешно создан! Теперь вы можете войти.';
            }
        } catch (\PDOException $e) {
            $error = 'Ошибка базы данных: ' . $e->getMessage();
        }
    } else {
        $error = 'Заполните все поля';
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Регистрация</title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>

    <div class="auth-card">
        <h2 style="text-align: center; margin-bottom: 20px; color: #2c3e50;">📝 Регистрация</h2>
        
        <?php if (!empty($error)): ?>
            <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="success-msg"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form action="/register" method="POST">
            <div class="form-group">
                <label>Придумайте логин:</label>
                <input type="text" name="username" required placeholder="FruitLover">
            </div>
            <div class="form-group">
                <label>Придумайте пароль:</label>
                <input type="password" name="password" required placeholder="••••••••">
            </div>
            <button type="submit" class="btn" style="margin-top: 10px;">Создать аккаунт</button>
        </form>
        <a href="/login.php" class="switch-link">Уже есть аккаунт? Войти</a>
    </div>

</body>
</html>
