<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

$db_host = 'localhost';
$db_user = 'u68532';
$db_pass = '9110579';
$db_name = 'u68532';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4");
} catch (PDOException $e) {
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $formData = [];

    foreach ($_POST as $key => $value) {
        $formData[$key] = is_array($value) ? $value : trim($value);
    }

    if (empty($formData['FIO']) || !preg_match('/^[A-Za-zА-Яа-яЁё\s]+$/u', $formData['FIO'])) {
        $errors['FIO'] = 'ФИО должно содержать только буквы и пробелы';
    }

    if (empty($formData['Phone_number']) || !preg_match('/^\+?[0-9]{10,15}$/', $formData['Phone_number'])) {
        $errors['Phone_number'] = 'Телефон должен содержать 10-15 цифр, может начинаться с +';
    }

    if (empty($formData['Email']) || !filter_var($formData['Email'], FILTER_VALIDATE_EMAIL)) {
        $errors['Email'] = 'Введите корректный email';
    }

    if (empty($formData['Birth_day']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $formData['Birth_day'])) {
        $errors['Birth_day'] = 'Введите дату в формате ГГГГ-ММ-ДД';
    }

    if (empty($formData['Gender']) || !in_array($formData['Gender'], ['male', 'female'])) {
        $errors['Gender'] = 'Выберите пол';
    }

    if (empty($formData['language'])) {
        $errors['language'] = 'Выберите хотя бы один язык программирования';
    }

    if (empty($formData['Biography'])) {
        $errors['Biography'] = 'Поле Биография обязательно для заполнения';
    }

    if (!isset($formData['Contract_accepted'])) {
        $errors['Contract_accepted'] = 'Необходимо согласие';
    }

    if (!empty($errors)) {
        foreach ($errors as $key => $message) {
            setcookie("error_$key", $message, time() + 3600, '/');
        }
        foreach ($formData as $key => $value) {
            $value = is_array($value) ? implode(',', $value) : $value;
            setcookie("value_$key", $value, time() + 3600, '/');
        }
        header('Location: index.php');
        exit;
    }

    foreach ($_COOKIE as $name => $value) {
        if (strpos($name, 'error_') === 0 || strpos($name, 'value_') === 0) {
            setcookie($name, '', time() - 3600, '/');
        }
    }

    try {
        $pdo->beginTransaction();

        if (isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
            $stmt = $pdo->prepare("SELECT ID FROM Application WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $application_id = $stmt->fetchColumn();

            if ($application_id) {
                $stmt = $pdo->prepare("UPDATE Application SET 
                    FIO = :fio, 
                    Phone_number = :phone, 
                    Email = :email, 
                    Birth_day = :birth_day, 
                    Gender = :gender, 
                    Biography = :bio, 
                    Contract_accepted = :contract
                    WHERE ID = :id");
                
                $stmt->execute([
                    ':fio' => $formData['FIO'],
                    ':phone' => $formData['Phone_number'],
                    ':email' => $formData['Email'],
                    ':birth_day' => $formData['Birth_day'],
                    ':gender' => $formData['Gender'],
                    ':bio' => $formData['Biography'],
                    ':contract' => $formData['Contract_accepted'],
                    ':id' => $application_id
                ]);

                $stmt = $pdo->prepare("DELETE FROM Application_Languages WHERE Application_ID = ?");
                $stmt->execute([$application_id]);
            } else {
                throw new Exception("Не найдена заявка пользователя. Обратитесь к администратору.");
            }
        } else {
            $login = 'user_' . bin2hex(random_bytes(4));
            $password = bin2hex(random_bytes(4));
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("INSERT INTO Users (login, password) VALUES (?, ?)");
            $stmt->execute([$login, $password_hash]);
            $user_id = $pdo->lastInsertId();

            $_SESSION['user_id'] = $user_id;
            $_SESSION['login'] = $login;

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

            setcookie('generated_login', $login, time() + 3600, '/');
            setcookie('generated_password', $password, time() + 3600, '/');
        }

        $stmt = $pdo->prepare("INSERT INTO Application_Languages (Application_ID, Language_ID) 
                              SELECT ?, Language_ID FROM Programming_Languages WHERE Name = ?");

        foreach ($formData['language'] as $language) {
            $stmt->execute([$application_id, $language]);
        }

        $pdo->commit();

        header('Location: index.php?success=1');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Ошибка при сохранении данных: " . $e->getMessage());
    }
} else {
    header('Location: index.php');
    exit;
}
