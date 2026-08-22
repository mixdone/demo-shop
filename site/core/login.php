<?php
// 1. Стартуем сессию (PHP сам создаст куку PHPSESSID в браузере пользователя)
session_start();

// Если пользователь уже авторизован, сразу перекидываем его на витрину
if (isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit;
}

$error = '';

// 2. Обрабатываем POST-запрос от формы входа
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        // Подключаемся к нашей MySQL
        $host = 'mysql-db';
        $db   = getenv('MYSQL_DATABASE');
        $user = getenv('MYSQL_USER');
        $pass = getenv('MYSQL_PASSWORD');
        
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);

            // Ищем пользователя по его логину
            $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $dbUser = $stmt->fetch();

            // 3. Безопасная проверка пароля через хэш
            if ($dbUser && password_verify($password, $dbUser['password_hash'])) {
                // Авторизация успешна! Записываем ID в сессию на сервере
                $_SESSION['user_id'] = $dbUser['id'];
                $_SESSION['username'] = $username;
                
                // Перенаправляем на главную страницу магазина
                header("Location: /index");
                exit;
            } else {
                $error = 'Неверный логин или пароль';
            }
        } catch (\PDOException $e) {
            $error = 'Ошибка базы данных: ' . $e->getMessage();
        }
    } else {
        $error = 'Пожалуйста, заполните все поля';
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Вход в магазин</title>
    <!-- Подключаем наш статичный файл стилей через Nginx -->
    <link rel="stylesheet" href="/style.css">
</head>
<body>

    <div class="auth-card">
        <h2 style="text-align: center; margin-bottom: 20px; color: #2c3e50;">🔑 Вход в аккаунт</h2>
        
        <?php if (!empty($error)): ?>
            <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form action="/login" method="POST">
            <div class="form-group">
                <label>Логин:</label>
                <input type="text" name="username" required placeholder="Ivan123">
            </div>
            <div class="form-group">
                <label>Пароль:</label>
                <input type="password" name="password" required placeholder="••••••••">
            </div>
            <button type="submit" class="btn" style="margin-top: 10px;">Войти</button>
        </form>
        <a href="/register.php" class="switch-link">Еще не зарегистрированы? Создать аккаунт</a>
    </div>

</body>
</html>
