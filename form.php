<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

// Проверка, если форма была отправлена
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_SESSION['user_id'])) {  // Если пользователь авторизован
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

            // Подключение к базе данных
            $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Обновление данных в таблице Application
            $stmt = $pdo->prepare("UPDATE Application 
                                   SET FIO = ?, Phone_number = ?, Email = ?, Birth_day = ?, Gender = ?, Biography = ?, Contract_accepted = ? 
                                   WHERE user_id = ?");
            $stmt->execute([
                $formData['FIO'],
                $formData['Phone_number'],
                $formData['Email'],
                $formData['Birth_day'],
                $formData['Gender'],
                $formData['Biography'],
                $formData['Contract_accepted'],
                $_SESSION['user_id']
            ]);

            // Удаляем старые языки программирования из таблицы Application_Languages
            $stmt = $pdo->prepare("DELETE FROM Application_Languages WHERE Application_ID = (SELECT ID FROM Application WHERE user_id = ?)");
            $stmt->execute([$_SESSION['user_id']]);

            // Вставляем новые языки программирования
            foreach ($formData['language'] as $language) {
                $stmt = $pdo->prepare("SELECT Language_ID FROM Programming_Languages WHERE Name = ?");
                $stmt->execute([$language]);
                $language_id = $stmt->fetchColumn();

                $stmt = $pdo->prepare("INSERT INTO Application_Languages (Application_ID, Language_ID)
                                       SELECT ID, ? FROM Application WHERE user_id = ?");
                $stmt->execute([$language_id, $_SESSION['user_id']]);
            }

            // Перенаправляем на главную страницу с успешным сообщением
            header('Location: index.php?success=1');
            exit;
        } catch (PDOException $e) {
            // Логируем ошибку
            error_log("Ошибка при выполнении запроса: " . $e->getMessage());
        }
    }
}
?>
