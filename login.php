<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    setcookie('generated_login', '', time() - 3600, '/');
    setcookie('generated_password', '', time() - 3600, '/');
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db_host = 'localhost';
        $db_user = 'u68532';
        $db_pass = '9110579';
        $db_name = 'u68532';
        
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $login = $_POST['login'];
        $password = $_POST['password'];

        $stmt = $pdo->prepare("SELECT id, password FROM Users WHERE login = ?");
        $stmt->execute([$login]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['login'] = $login;
            header('Location: index.php');
            exit;
        } else {
            $error = "Неверный логин или пароль";
        }
    } catch (PDOException $e) {
        $error = "Ошибка при подключении к базе данных";
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
        <h1>Вход в систему</h1>

        <?php if (isset($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="login">Логин:</label>
                <input type="text" id="login" name="login" required>
            </div>

            <div class="form-group">
                <label for="password">Пароль:</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit">Войти</button>
        </form>

        <a href="index.php">Вернуться на главную</a>
    </div>
</body>
</html>
