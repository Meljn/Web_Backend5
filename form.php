<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'FIO' => $_POST['FIO'],
        'Phone_number' => $_POST['Phone_number'],
        'Email' => $_POST['Email'],
        'Birth_day' => $_POST['Birth_day'],
        'Gender' => $_POST['Gender'],
        'Biography' => $_POST['Biography'],
        'Contract_accepted' => isset($_POST['Contract_accepted']) ? 1 : 0,
        'language' => isset($_POST['language']) ? $_POST['language'] : []
    ];

    try {
        $db_host = 'localhost';
        $db_user = 'u68532';
        $db_pass = '9110579';
        $db_name = 'u68532';
        
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        if (!isset($_SESSION['user_id'])) {
            $login = 'user_' . bin2hex(random_bytes(4));
            $password = bin2hex(random_bytes(4));
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("INSERT INTO Users (login, password) VALUES (?, ?)");
            $stmt->execute([$login, $password_hash]);
            $user_id = $pdo->lastInsertId();

            $stmt = $pdo->prepare("INSERT INTO Application 
                (user_id, FIO, Phone_number, Email, Birth_day, Gender, Biography, Contract_accepted) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $user_id,
                $formData['FIO'],
                $formData['Phone_number'],
                $formData['Email'],
                $formData['Birth_day'],
                $formData['Gender'],
                $formData['Biography'],
                $formData['Contract_accepted']
            ]);
            $application_id = $pdo->lastInsertId();

            foreach ($formData['language'] as $language) {
                $stmt = $pdo->prepare("SELECT Language_ID FROM Programming_Languages WHERE Name = ?");
                $stmt->execute([$language]);
                $language_id = $stmt->fetchColumn();

                $stmt = $pdo->prepare("INSERT INTO Application_Languages (Application_ID, Language_ID) VALUES (?, ?)");
                $stmt->execute([$application_id, $language_id]);
            }

            setcookie('generated_login', $login, time() + 3600, '/');
            setcookie('generated_password', $password, time() + 3600, '/');

            header('Location: index.php?success=1');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Ошибка при выполнении запроса: " . $e->getMessage());
    }
}
?>
