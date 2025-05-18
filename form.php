<?php
header('Content-Type: text/html; charset=utf-8');
session_start();

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $formData = [];

    // Обработка данных формы
    foreach ($_POST as $key => $value) {
        $formData[$key] = is_array($value) ? $value : trim($value);
    }

    // Валидация данных
    if (empty($formData['fio'])) {
        $errors['fio'] = 'Поле ФИО обязательно для заполнения';
    } elseif (!preg_match('/^[A-Za-zА-Яа-яЁё\s]+$/u', $formData['fio'])) {
        $errors['fio'] = 'ФИО должно содержать только буквы и пробелы';
    }

    if (empty($formData['phone'])) {
        $errors['phone'] = 'Поле Телефон обязательно для заполнения';
    } elseif (!preg_match('/^\+?[0-9]{10,15}$/', $formData['phone'])) {
        $errors['phone'] = 'Телефон должен содержать 10-15 цифр, может начинаться с +';
    }

    if (empty($formData['email'])) {
        $errors['email'] = 'Поле Email обязательно для заполнения';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Введите корректный email';
    }

    if (empty($formData['dob'])) {
        $errors['dob'] = 'Поле Дата рождения обязательно для заполнения';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $formData['dob'])) {
        $errors['dob'] = 'Введите дату в формате ГГГГ-ММ-ДД';
    }

    if (empty($formData['gender'])) {
        $errors['gender'] = 'Укажите ваш пол';
    } elseif (!in_array($formData['gender'], ['male', 'female'])) {
        $errors['gender'] = 'Выбран недопустимый пол';
    }

    if (empty($formData['language'])) {
        $errors['language'] = 'Выберите хотя бы один язык программирования';
    }

    if (empty($formData['bio'])) {
        $errors['bio'] = 'Поле Биография обязательно для заполнения';
    }

    if (!isset($formData['contract'])) {
        $errors['contract'] = 'Необходимо согласиться с условиями';
    }

    if (!empty($errors)) {
        // Сохраняем ошибки и значения в куки
        foreach ($errors as $key => $message) {
            setcookie("error_$key", $message, time() + 3600, '/');
        }
        foreach ($formData as $key => $value) {
            if (!is_array($value)) {
                setcookie("value_$key", $value, time() + 3600, '/');
            }
        }
        header('Location: index.php');
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Проверяем, авторизован ли пользователь
        $isUpdate = isset($_SESSION['user_id']);
        $application_id = $isUpdate ? $_SESSION['app_id'] : null;

        if ($isUpdate) {
            // Обновляем существующую заявку
            $stmt = $pdo->prepare("UPDATE Application SET 
                                  FIO = :fio, 
                                  Phone_number = :phone, 
                                  Email = :email, 
                                  Birth_day = :dob, 
                                  Gender = :gender, 
                                  Biography = :bio
                                  WHERE ID = :id");
            $stmt->execute([
                ':fio' => $formData['fio'],
                ':phone' => $formData['phone'],
                ':email' => $formData['email'],
                ':dob' => $formData['dob'],
                ':gender' => $formData['gender'],
                ':bio' => $formData['bio'],
                ':id' => $application_id
            ]);

            // Удаляем старые языки
            $stmt = $pdo->prepare("DELETE FROM Application_Languages WHERE Application_ID = ?");
            $stmt->execute([$application_id]);
        } else {
            // Создаем новую заявку
            $stmt = $pdo->prepare("INSERT INTO Application 
                                  (FIO, Phone_number, Email, Birth_day, Gender, Biography, Contract_accepted) 
                                  VALUES (:fio, :phone, :email, :dob, :gender, :bio, :contract)");
            $stmt->execute([
                ':fio' => $formData['fio'],
                ':phone' => $formData['phone'],
                ':email' => $formData['email'],
                ':dob' => $formData['dob'],
                ':gender' => $formData['gender'],
                ':bio' => $formData['bio'],
                ':contract' => 1
            ]);
            $application_id = $pdo->lastInsertId();
        }

        // Добавляем выбранные языки
        $stmt = $pdo->prepare("INSERT INTO Application_Languages (Application_ID, Language_ID) 
                              SELECT :app_id, Language_ID FROM Programming_Languages WHERE Name = :language");

        foreach ($formData['language'] as $language) {
            $stmt->execute([
                ':app_id' => $application_id,
                ':language' => $language
            ]);
        }

        if (!$isUpdate) {
            // Генерируем учетные данные только для новой заявки
            $login = generateRandomString(8);
            $password = generateRandomString(10);
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("INSERT INTO Users (Login, PasswordHash, ApplicationID) 
                                  VALUES (:login, :password, :app_id)");
            $stmt->execute([
                ':login' => $login,
                ':password' => $passwordHash,
                ':app_id' => $application_id
            ]);

            // Сохраняем логин и пароль в куки
            setcookie('generated_login', $login, time() + 3600, '/');
            setcookie('generated_password', $password, time() + 60, '/');
        }

        $pdo->commit();

        // Очищаем куки с ошибками
        foreach ($_COOKIE as $name => $value) {
            if (strpos($name, 'error_') === 0 || strpos($name, 'value_') === 0) {
                setcookie($name, '', time() - 3600, '/');
            }
        }

        // Сохраняем данные в куки для автозаполнения
        foreach ($formData as $field => $value) {
            if ($field !== 'contract') {
                $value = is_array($value) ? implode(',', $value) : $value;
                setcookie("success_$field", $value, time() + 60*60*24*365, '/');
            }
        }
        setcookie('success_contract', '1', time() + 60*60*24*365, '/');

        header('Location: index.php?success=1');
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        die("Ошибка при сохранении данных: " . $e->getMessage());
    }
} else {
    header('Location: index.php');
    exit;
}

function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}