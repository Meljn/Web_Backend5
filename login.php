<?php
session_start();

// --- Database Connection ---
$db_host = 'localhost';
$db_user = 'u68532'; // Replace with your actual database username
$db_pass = '9110579'; // Replace with your actual database password
$db_name = 'u68532'; // Replace with your actual database name

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['login_error'] = "Ошибка подключения к базе данных: " . $e->getMessage();
    header('Location: index.php#login-form');
    exit;
}
// --- End Database Connection ---


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    $errors = [];

    if (empty($login)) {
        $errors[] = 'Логин не может быть пустым.';
    }
    if (empty($password)) {
        $errors[] = 'Пароль не может быть пустым.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT Application_ID, login, password_hash FROM Application WHERE login = :login");
            $stmt->execute([':login' => $login]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['Application_ID'];
                $_SESSION['login'] = $user['login'];
                if(isset($_SESSION['login_error'])) unset($_SESSION['login_error']);
                
                // Clear any 'value_' or 'error_' cookies from previous attempts on this browser
                $cookieNames = array_keys($_COOKIE);
                foreach ($cookieNames as $cookieName) {
                    if (strpos($cookieName, 'error_') === 0 || strpos($cookieName, 'value_') === 0 || strpos($cookieName, 'success_') === 0) {
                        setcookie($cookieName, '', time() - 3600, '/');
                    }
                }
                header('Location: index.php');
                exit;
            } else {
                $_SESSION['login_error'] = 'Неверный логин или пароль.';
            }
        } catch (PDOException $e) {
            $_SESSION['login_error'] = 'Ошибка на сервере при попытке входа.';
        }
    } else {
        $_SESSION['login_error'] = implode(' ', $errors);
    }
    header('Location: index.php#login-form'); 
    exit;
} else {
    header('Location: index.php');
    exit;
}
?>