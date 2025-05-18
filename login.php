<?php
header('Content-Type: text/html; charset=utf-8');
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$db_host = 'localhost';
$db_user = 'u68532';
$db_pass = '9110579';
$db_name = 'u68532';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($login) || empty($password)) {
        $error = 'Логин и пароль обязательны для заполнения';
    } else {
        $stmt = $pdo->prepare("SELECT u.UserID, u.Login, u.ApplicationID, a.FIO 
                              FROM Users u
                              JOIN Application a ON u.ApplicationID = a.ID
                              WHERE u.Login = ?");
        $stmt->execute([$login]);
        $user = $stmt->fetch();

        if ($user) {
            // Проверяем пароль
            $stmt = $pdo->prepare("SELECT PasswordHash FROM Users WHERE Login = ?");
            $stmt->execute([$login]);
            $dbPassword = $stmt->fetchColumn();
            
            if ($dbPassword && password_verify($password, $dbPassword)) {
                $_SESSION['user_id'] = $user['UserID'];
                $_SESSION['login'] = $user['Login'];
                $_SESSION['app_id'] = $user['ApplicationID'];
                $_SESSION['fio'] = $user['FIO'];
                header('Location: index.php');
                exit();
            }
        }
        $error = 'Неверный логин или пароль';
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Вход</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="form-container">
        <h1>Вход в систему</h1>
        
        <?php if ($error): ?>
            <div class="error-messages"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label>Логин:</label>
                <input type="text" name="login" required>
            </div>
            
            <div class="form-group">
                <label>Пароль:</label>
                <input type="password" name="password" required>
            </div>
            
            <button type="submit">Войти</button>
        </form>
    </div>
</body>
</html>