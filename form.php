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
    // In a real app, log this error and show a user-friendly message
    // For this lab, we can die or set an error and redirect.
    // Let's set an error to be shown on index.php for consistency with other errors
    setcookie("error_db", "Ошибка подключения к базе данных: " . $e->getMessage(), time() + 600, '/');
    header('Location: index.php');
    exit;
}
// --- End Database Connection ---

header('Content-Type: text/html; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $formData = [];

    // Sanitize and collect form data
    $fieldNames = ['fio', 'phone', 'email', 'dob', 'gender', 'bio'];
    foreach ($fieldNames as $field) {
        if (isset($_POST[$field])) {
            $formData[$field] = trim($_POST[$field]);
        } else {
            $formData[$field] = ''; 
        }
    }
    $formData['language'] = $_POST['language'] ?? [];
    $formData['contract'] = isset($_POST['contract']) ? '1' : null;


    // Validation logic
    if (empty($formData['fio'])) {
        $errors['fio'] = 'Поле ФИО обязательно для заполнения';
    } elseif (!preg_match('/^[A-Za-zА-Яа-яЁё\s\-]+$/u', $formData['fio'])) {
        $errors['fio'] = 'ФИО должно содержать только буквы, пробелы и дефисы';
    }

    if (empty($formData['phone'])) {
        $errors['phone'] = 'Поле Телефон обязательно для заполнения';
    } elseif (!preg_match('/^\+?[0-9\s\-()]{10,20}$/', $formData['phone'])) {
        $errors['phone'] = 'Телефон введен некорректно (10-20 символов, цифры, +, пробелы, -, ())';
    }

    if (empty($formData['email'])) {
        $errors['email'] = 'Поле Email обязательно для заполнения';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Введите корректный email (пример: user@example.com)';
    }

    if (empty($formData['dob'])) {
        $errors['dob'] = 'Поле Дата рождения обязательно для заполнения';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $formData['dob'])) {
        $errors['dob'] = 'Введите дату в формате ГГГГ-ММ-ДД';
    } else {
        $dateParts = explode('-', $formData['dob']);
        if (count($dateParts) === 3 && !checkdate((int)$dateParts[1], (int)$dateParts[2], (int)$dateParts[0])) {
            $errors['dob'] = 'Введена некорректная дата.';
        } elseif (new DateTime($formData['dob']) > new DateTime()) {
            $errors['dob'] = 'Дата рождения не может быть в будущем.';
        }
    }

    if (empty($formData['gender'])) {
        $errors['gender'] = 'Укажите ваш пол';
    } elseif (!in_array($formData['gender'], ['male', 'female'])) {
        $errors['gender'] = 'Выбран недопустимый пол';
    }

    if (empty($formData['language'])) {
        $errors['language'] = 'Выберите хотя бы один язык программирования';
    } else {
        $allowed_languages = ['Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python', 'Java', 'Haskell', 'Clojure', 'Prolog', 'Scala', 'Go'];
        foreach($formData['language'] as $lang) {
            if (!in_array($lang, $allowed_languages)) {
                $errors['language'] = 'Выбран недопустимый язык программирования: ' . htmlspecialchars($lang);
                break;
            }
        }
    }

    if (empty($formData['bio'])) {
        $errors['bio'] = 'Поле Биография обязательно для заполнения';
    }

    if ($formData['contract'] !== '1') {
        $errors['contract'] = 'Необходимо согласиться с условиями';
    }

    if (!empty($errors)) {
        // Save errors and current values in cookies
        foreach ($errors as $key => $message) {
            setcookie("error_$key", $message, time() + 600, '/'); 
        }
        foreach ($formData as $key => $value) {
            if (is_array($value)) {
                setcookie("value_$key", implode(',', $value), time() + 600, '/');
            } else {
                setcookie("value_$key", $value, time() + 600, '/');
            }
        }
        header('Location: index.php');
        exit;
    } else {
        // NO VALIDATION ERRORS

        // Clear any old error cookies and value cookies
        $cookieNames = array_keys($_COOKIE);
        foreach ($cookieNames as $cookieName) {
            if (strpos($cookieName, 'error_') === 0 || strpos($cookieName, 'value_') === 0) {
                setcookie($cookieName, '', time() - 3600, '/');
            }
        }

        $is_logged_in = isset($_SESSION['user_id']);

        try {
            $pdo->beginTransaction();

            if ($is_logged_in) {
                // UPDATE existing data
                $application_id = $_SESSION['user_id'];
                $stmt = $pdo->prepare("UPDATE Application SET FIO = :fio, Phone_number = :phone, Email = :email, 
                                      Birth_day = :dob, Gender = :gender, Biography = :bio, Contract_accepted = :contract
                                      WHERE Application_ID = :app_id");
                $stmt->execute([
                    ':fio' => $formData['fio'],
                    ':phone' => $formData['phone'],
                    ':email' => $formData['email'],
                    ':dob' => $formData['dob'],
                    ':gender' => $formData['gender'],
                    ':bio' => $formData['bio'],
                    ':contract' => ($formData['contract'] === '1' ? 1 : 0),
                    ':app_id' => $application_id
                ]);

                $stmt_delete_lang = $pdo->prepare("DELETE FROM Application_Languages WHERE Application_ID = :app_id");
                $stmt_delete_lang->execute([':app_id' => $application_id]);

                $stmt_insert_lang = $pdo->prepare("INSERT INTO Application_Languages (Application_ID, Language_ID) 
                                                  SELECT :app_id, lang.Language_ID FROM Programming_Languages lang WHERE lang.Name = :language_name");
                foreach ($formData['language'] as $language_name) {
                    $stmt_insert_lang->execute([
                        ':app_id' => $application_id,
                        ':language_name' => $language_name
                    ]);
                }
                $pdo->commit();
                header('Location: index.php?updated=1');
                exit;

            } else {
                // INSERT new data and GENERATE credentials
                // Generate a more robust unique login
                $baseLogin = preg_replace("/[^a-zA-Z0-9]/", "", strtolower($formData['email']));
                if (empty($baseLogin)) $baseLogin = "user";
                $login = $baseLogin . substr(uniqid(), -4); // Shorter unique suffix

                $password = bin2hex(random_bytes(6)); // 12 char hex password
                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare("INSERT INTO Application (FIO, Phone_number, Email, Birth_day, Gender, Biography, Contract_accepted, login, password_hash) 
                                      VALUES (:fio, :phone, :email, :dob, :gender, :bio, :contract, :login, :password_hash)");
                $stmt->execute([
                    ':fio' => $formData['fio'],
                    ':phone' => $formData['phone'],
                    ':email' => $formData['email'],
                    ':dob' => $formData['dob'],
                    ':gender' => $formData['gender'],
                    ':bio' => $formData['bio'],
                    ':contract' => ($formData['contract'] === '1' ? 1 : 0),
                    ':login' => $login,
                    ':password_hash' => $password_hash
                ]);
                $application_id = $pdo->lastInsertId();

                $stmt_insert_lang = $pdo->prepare("INSERT INTO Application_Languages (Application_ID, Language_ID) 
                                                  SELECT :app_id, lang.Language_ID FROM Programming_Languages lang WHERE lang.Name = :language_name");
                foreach ($formData['language'] as $language_name) {
                    $stmt_insert_lang->execute([
                        ':app_id' => $application_id,
                        ':language_name' => $language_name
                    ]);
                }
                $pdo->commit();

                $_SESSION['generated_login'] = $login;
                $_SESSION['generated_password'] = $password;

                // Set success cookies for non-logged-in state or first view
                foreach ($formData as $field => $value) {
                    $cookieValue = is_array($value) ? implode(',', $value) : $value;
                    setcookie("success_$field", $cookieValue, time() + 3600 * 24, '/'); 
                }
                header('Location: index.php?success=1&new_user=1');
                exit;
            }

        } catch (PDOException $e) {
            $pdo->rollBack();
            // Log error: $e->getMessage()
            // Set a generic error cookie/session message for display on index.php
            setcookie("error_db_save", "Ошибка при сохранении данных: " . $e->getMessage(), time() + 600, '/');
            header('Location: index.php');
            exit;
        }
    }
} else {
    header('Location: index.php');
    exit;
}
?>