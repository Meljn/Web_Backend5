<?php
header('Content-Type: text/html; charset=utf-8');
session_start();

// Если пользователь уже вошел в систему, перенаправляем на главную страницу
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: index.php');
    exit;
}

// Настройки базы данных
$db_host = 'localhost';
$db_user = 'u68532';
$db_pass = '9110579';
$db_name = 'u68532';

$error = '';

// Обработка формы входа
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Пожалуйста, введите логин и пароль';
    } else {
        try {
            // Подключение к базе данных
            $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Поиск пользователя по логину
            $stmt = $pdo->prepare("SELECT ID, Username, Password_hash FROM Application WHERE Username = :username");
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['Password_hash'])) {
                // Успешный вход, устанавливаем сессию
                $_SESSION['logged_in'] = true;
                $_SESSION['username'] = $user['Username'];
                $_SESSION['user_id'] = $user['ID'];
                
                // Перенаправляем на главную страницу
                header('Location: index.php');
                exit;
            } else {
                $error = 'Неверный логин или пароль';
            }
        } catch (PDOException $e) {
            $error = 'Ошибка при подключении к базе данных';
            // В реальном приложении здесь нужно логировать ошибку, а не выводить её
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход в систему</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="form-container login-form">
        <h1>Вход в систему</h1>
        
        <?php if (!empty($error)): ?>
            <div class="error-messages">
                <p><?= htmlspecialchars($error) ?></p>
            </div>
        <?php endif; ?>
        
        <form method="post" action="login.php">
            <div class="form-group">
                <label for="username">Логин:</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="password">Пароль:</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit">Войти</button>
        </form>
        
        <div class="form-footer">
            <a href="index.php">Вернуться на главную</a>
        </div>
    </div>
</body>
</html>