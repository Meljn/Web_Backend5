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
} catch (PDOException $e) {
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $formData = [];

    foreach ($_POST as $key => $value) {
        $formData[$key] = is_array($value) ? $value : trim($value);
    }

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
    } elseif (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $formData['email'])) {
        $errors['email'] = 'Введите корректный email (пример: user@example.com)';
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
    } else {
        foreach ($_COOKIE as $name => $value) {
            if (strpos($name, 'error_') === 0 || strpos($name, 'value_') === 0) {
                setcookie($name, '', time() - 3600, '/');
            }
        }

        try {
            $pdo->beginTransaction();

            // Проверяем, авторизован ли пользователь
            $user_id = null;
            if (!empty($_SESSION['user_id'])) {
                $user_id = $_SESSION['user_id'];
                
                // Обновляем существующую заявку
                $stmt = $pdo->prepare("UPDATE Application SET 
                    FIO = :fio, 
                    Phone_number = :phone, 
                    Email = :email, 
                    Birth_day = :dob, 
                    Gender = :gender, 
                    Biography = :bio, 
                    Contract_accepted = :contract 
                    WHERE user_id = :user_id");
                $stmt->execute([
                    ':fio' => $formData['fio'],
                    ':phone' => $formData['phone'],
                    ':email' => $formData['email'],
                    ':dob' => $formData['dob'],
                    ':gender' => $formData['gender'],
                    ':bio' => $formData['bio'],
                    ':contract' => 1,
                    ':user_id' => $user_id
                ]);
                
                // Удаляем старые языки программирования
                $stmt = $pdo->prepare("DELETE FROM Application_Languages 
                                      WHERE Application_ID IN (SELECT Application_ID FROM Application WHERE user_id = ?)");
                $stmt->execute([$user_id]);
            } else {
                // Создаем нового пользователя
                $login = uniqid('user_');
                $password = bin2hex(random_bytes(4));
                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare("INSERT INTO Users (login, password) VALUES (?, ?)");
                $stmt->execute([$login, $password_hash]);
                $user_id = $pdo->lastInsertId();

                // Сохраняем данные для показа пользователю
                setcookie('generated_login', $login, time() + 3600, '/');
                setcookie('generated_password', $password, time() + 3600, '/');

                // Создаем новую заявку
                $stmt = $pdo->prepare("INSERT INTO Application 
                    (user_id, FIO, Phone_number, Email, Birth_day, Gender, Biography, Contract_accepted) 
                    VALUES (:user_id, :fio, :phone, :email, :dob, :gender, :bio, :contract)");
                $stmt->execute([
                    ':user_id' => $user_id,
                    ':fio' => $formData['fio'],
                    ':phone' => $formData['phone'],
                    ':email' => $formData['email'],
                    ':dob' => $formData['dob'],
                    ':gender' => $formData['gender'],
                    ':bio' => $formData['bio'],
                    ':contract' => 1
                ]);
            }

            $application_id = $pdo->lastInsertId();

            // Добавляем языки программирования
            $stmt = $pdo->prepare("INSERT INTO Application_Languages (Application_ID, Language_ID) 
                                  SELECT :app_id, Language_ID FROM Programming_Languages WHERE Name = :language");

            foreach ($formData['language'] as $language) {
                $stmt->execute([
                    ':app_id' => $application_id,
                    ':language' => $language
                ]);
            }

            $pdo->commit();

            // Сохраняем данные в куки
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
    }
} else {
    header('Location: index.php');
    exit;
}