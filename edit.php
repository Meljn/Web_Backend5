<?php
header('Content-Type: text/html; charset=utf-8');
session_start();

// Проверка авторизации
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Настройки базы данных
$db_host = 'localhost';
$db_user = 'u68532';
$db_pass = '9110579';
$db_name = 'u68532';

try {
    // Подключение к базе данных
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $user_id = $_SESSION['user_id'];
    $errors = [];
    $success = false;
    
    // Получение данных пользователя из БД
    $stmt = $pdo->prepare("SELECT FIO, Phone_number, Email, Birth_day, Gender, Biography FROM Application WHERE ID = :id");
    $stmt->execute([':id' => $user_id]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Получение языков программирования пользователя
    $stmt = $pdo->prepare("
        SELECT pl.Name 
        FROM Application_Languages al
        JOIN Programming_Languages pl ON al.Language_ID = pl.Language_ID
        WHERE al.Application_ID = :id
    ");
    $stmt->execute([':id' => $user_id]);
    $userLanguages = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Обработка формы обновления данных
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $formData = [];
        
        foreach ($_POST as $key => $value) {
            $formData[$key] = is_array($value) ? $value : trim($value);
        }
        
        // Валидация полей формы
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
        
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                // Обновление основных данных
                $stmt = $pdo->prepare("
                    UPDATE Application 
                    SET FIO = :fio, 
                        Phone_number = :phone, 
                        Email = :email, 
                        Birth_day = :dob, 
                        Gender = :gender, 
                        Biography = :bio
                    WHERE ID = :id
                ");
                
                $stmt->execute([
                    ':fio' => $formData['fio'],
                    ':phone' => $formData['phone'],
                    ':email' => $formData['email'],
                    ':dob' => $formData['dob'],
                    ':gender' => $formData['gender'],
                    ':bio' => $formData['bio'],
                    ':id' => $user_id
                ]);
                
                // Удаление старых языков программирования
                $stmt = $pdo->prepare("DELETE FROM Application_Languages WHERE Application_ID = :id");
                $stmt->execute([':id' => $user_id]);
                
                // Добавление новых языков
                $stmt = $pdo->prepare("
                    INSERT INTO Application_Languages (Application_ID, Language_ID) 
                    SELECT :app_id, Language_ID FROM Programming_Languages WHERE Name = :language
                ");
                
                foreach ($formData['language'] as $language) {
                    $stmt->execute([
                        ':app_id' => $user_id,
                        ':language' => $language
                    ]);
                }
                
                $pdo->commit();
                $success = true;
                
                // Обновляем локальные данные для отображения
                $userData['FIO'] = $formData['fio'];
                $userData['Phone_number'] = $formData['phone'];
                $userData['Email'] = $formData['email'];
                $userData['Birth_day'] = $formData['dob'];
                $userData['Gender'] = $formData['gender'];
                $userData['Biography'] = $formData['bio'];
                $userLanguages = $formData['language'];
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                $errors['general'] = 'Ошибка при обновлении данных: ' . $e->getMessage();
            }
        }
    }
    
} catch (PDOException $e) {
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактирование данных</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="form-container">
        <div class="user-panel">
            <p>Вы вошли как <?= htmlspecialchars($_SESSION['username']) ?></p>
            <a href="index.php" class="button">На главную</a>
            <a href="logout.php" class="button">Выйти</a>
        </div>
        
        <h1>Редактирование данных</h1>
        
        <?php if (!empty($errors['general'])): ?>
            <div class="error-messages">
                <p><?= htmlspecialchars($errors['general']) ?></p>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success-message">
                <p>Данные успешно обновлены!</p>
            </div>
        <?php endif; ?>
        
        <form method="post" action="edit.php">
            <div class="form-group <?= isset($errors['fio']) ? 'has-error' : '' ?>">
                <label for="fio">ФИО:</label>
                <input type="text" id="fio" name="fio" value="<?= htmlspecialchars($userData['FIO']) ?>" required>
                <?php if (isset($errors['fio'])): ?>
                    <div class="error"><?= htmlspecialchars($errors['fio']) ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group <?= isset($errors['phone']) ? 'has-error' : '' ?>">
                <label for="phone">Телефон:</label>
                <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($userData['Phone_number']) ?>" required>
                <?php if (isset($errors['phone'])): ?>
                    <div class="error"><?= htmlspecialchars($errors['phone']) ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group <?= isset($errors['email']) ? 'has-error' : '' ?>">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($userData['Email']) ?>" required>
                <?php if (isset($errors['email'])): ?>
                    <div class="error"><?= htmlspecialchars($errors['email']) ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group <?= isset($errors['dob']) ? 'has-error' : '' ?>">
                <label for="dob">Дата рождения:</label>
                <input type="date" id="dob" name="dob" value="<?= htmlspecialchars($userData['Birth_day']) ?>" required>
                <?php if (isset($errors['dob'])): ?>
                    <div class="error"><?= htmlspecialchars($errors['dob']) ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group <?= isset($errors['gender']) ? 'has-error' : '' ?>">
                <label>Пол:</label>
                <label>
                    <input type="radio" name="gender" value="male" <?= $userData['Gender'] === 'male' ? 'checked' : '' ?> required> Мужской
                </label>
                <label>
                    <input type="radio" name="gender" value="female" <?= $userData['Gender'] === 'female' ? 'checked' : '' ?>> Женский
                </label>
                <?php if (isset($errors['gender'])): ?>
                    <div class="error"><?= htmlspecialchars($errors['gender']) ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group <?= isset($errors['language']) ? 'has-error' : '' ?>">
                <label for="language">Любимые языки программирования:</label>
                <select id="language" name="language[]" multiple required>
                    <?php
                    $languages = ['Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python', 'Java', 'Haskell', 'Clojure', 'Prolog', 'Scala', 'Go'];
                    foreach ($languages as $lang): ?>
                        <option value="<?= htmlspecialchars($lang) ?>" <?= in_array($lang, $userLanguages) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($lang) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['language'])): ?>
                    <div class="error"><?= htmlspecialchars($errors['language']) ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group <?= isset($errors['bio']) ? 'has-error' : '' ?>">
                <label for="bio">Биография:</label>
                <textarea id="bio" name="bio" rows="5" required><?= htmlspecialchars($userData['Biography']) ?></textarea>
                <?php if (isset($errors['bio'])): ?>
                    <div class="error"><?= htmlspecialchars($errors['bio']) ?></div>
                <?php endif; ?>
            </div>
            
            <button type="submit">Сохранить изменения</button>
        </form>
    </div>
</body>
</html>