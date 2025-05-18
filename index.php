<?php
header('Content-Type: text/html; charset=utf-8');
session_start();

function getFieldValue($fieldName, $default = '') {
    if (isset($_COOKIE["value_$fieldName"])) {
        $value = $_COOKIE["value_$fieldName"];
        return htmlspecialchars($value);
    }
    if (isset($_COOKIE["success_$fieldName"])) {
        return htmlspecialchars($_COOKIE["success_$fieldName"]);
    }
    return $default;
}

function getFieldError($fieldName) {
    if (isset($_COOKIE["error_$fieldName"])) {
        $error = $_COOKIE["error_$fieldName"];
        return htmlspecialchars($error);
    }
    return '';
}

$formErrors = array();
foreach ($_COOKIE as $name => $value) {
    if (strpos($name, 'error_') === 0) {
        $formErrors[] = $value;
    }
}

// Загрузка данных пользователя, если авторизован
$userData = array();
if (isset($_SESSION['user_id'])) {
    $db_host = 'localhost';
    $db_user = 'u68532';
    $db_pass = '9110579';
    $db_name = 'u68532';
    
    try {
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        
        // Загружаем основную информацию
        $stmt = $pdo->prepare("SELECT * FROM Application WHERE ID = ?");
        $stmt->execute(array($_SESSION['app_id']));
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Загружаем выбранные языки
        $stmt = $pdo->prepare("SELECT pl.Name 
                              FROM Programming_Languages pl
                              JOIN Application_Languages al ON pl.Language_ID = al.Language_ID
                              WHERE al.Application_ID = ?");
        $stmt->execute(array($_SESSION['app_id']));
        $userData['language'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
    } catch (PDOException $e) {
        die("Ошибка загрузки данных: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Форма обратной связи</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="form-container">
        <h1>Форма обратной связи</h1>

        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="logout-panel">
                Вы вошли как: <?php echo htmlspecialchars($_SESSION['login']); ?>
                <form action="logout.php" method="post">
                    <button type="submit">Выйти</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if (!empty($formErrors)): ?>
            <div class="error-messages">
                <h3>Ошибки при заполнении формы:</h3>
                <ul>
                    <?php foreach ($formErrors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['success'])): ?>
            <div class="success-message">
                Данные успешно сохранены!
                <?php if (isset($_COOKIE['generated_login'])): ?>
                    <p>Ваши данные для входа:</p>
                    <p><strong>Логин:</strong> <?php echo htmlspecialchars($_COOKIE['generated_login']); ?></p>
                    <p><strong>Пароль:</strong> <?php echo htmlspecialchars($_COOKIE['generated_password']); ?></p>
                    <p>Вы можете <a href="login.php">войти</a> для изменения данных.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form action="form.php" method="post">
            <div class="form-group <?php echo getFieldError('fio') ? 'has-error' : ''; ?>">
                <label for="fio">ФИО:</label>
                <input type="text" id="fio" name="fio" 
                       value="<?php echo isset($userData['FIO']) ? htmlspecialchars($userData['FIO']) : getFieldValue('fio'); ?>" 
                       required>
                <?php if ($error = getFieldError('fio')): ?>
                    <div class="error"><?php echo $error; ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group <?php echo getFieldError('phone') ? 'has-error' : ''; ?>">
                <label for="phone">Телефон:</label>
                <input type="tel" id="phone" name="phone" 
                       value="<?php echo isset($userData['Phone_number']) ? htmlspecialchars($userData['Phone_number']) : getFieldValue('phone'); ?>" 
                       required>
                <?php if ($error = getFieldError('phone')): ?>
                    <div class="error"><?php echo $error; ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group <?php echo getFieldError('email') ? 'has-error' : ''; ?>">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" 
                       value="<?php echo isset($userData['Email']) ? htmlspecialchars($userData['Email']) : getFieldValue('email'); ?>" 
                       required>
                <?php if ($error = getFieldError('email')): ?>
                    <div class="error"><?php echo $error; ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group <?php echo getFieldError('dob') ? 'has-error' : ''; ?>">
                <label for="dob">Дата рождения:</label>
                <input type="date" id="dob" name="dob" 
                       value="<?php echo isset($userData['Birth_day']) ? htmlspecialchars($userData['Birth_day']) : getFieldValue('dob'); ?>" 
                       required>
                <?php if ($error = getFieldError('dob')): ?>
                    <div class="error"><?php echo $error; ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group <?php echo getFieldError('gender') ? 'has-error' : ''; ?>">
                <label>Пол:</label>
                <label>
                    <input type="radio" name="gender" value="male" 
                           <?php echo (isset($userData['Gender']) ? ($userData['Gender'] === 'male' ? 'checked' : '') : (getFieldValue('gender') === 'male' ? 'checked' : ''); ?> 
                           required> Мужской
                </label>
                <label>
                    <input type="radio" name="gender" value="female" 
                           <?php echo (isset($userData['Gender']) ? ($userData['Gender'] === 'female' ? 'checked' : '') : (getFieldValue('gender') === 'female' ? 'checked' : ''); ?>> Женский
                </label>
                <?php if ($error = getFieldError('gender')): ?>
                    <div class="error"><?php echo $error; ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group <?php echo getFieldError('language') ? 'has-error' : ''; ?>">
                <label for="language">Любимые языки программирования:</label>
                <select id="language" name="language[]" multiple required>
                    <?php
                    $languages = array('Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python', 'Java', 'Haskell', 'Clojure', 'Prolog', 'Scala', 'Go');
                    $selected = isset($userData['language']) ? $userData['language'] : explode(',', getFieldValue('language', ''));
                    foreach ($languages as $lang): ?>
                        <option value="<?php echo htmlspecialchars($lang); ?>" 
                                <?php echo in_array($lang, $selected) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($lang); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($error = getFieldError('language')): ?>
                    <div class="error"><?php echo $error; ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group <?php echo getFieldError('bio') ? 'has-error' : ''; ?>">
                <label for="bio">Биография:</label>
                <textarea id="bio" name="bio" rows="5" required><?php echo isset($userData['Biography']) ? htmlspecialchars($userData['Biography']) : getFieldValue('bio'); ?></textarea>
                <?php if ($error = getFieldError('bio')): ?>
                    <div class="error"><?php echo $error; ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group <?php echo getFieldError('contract') ? 'has-error' : ''; ?>">
                <label>
                    <input type="checkbox" name="contract" 
                           <?php echo isset($userData['Contract_accepted']) ? ($userData['Contract_accepted'] ? 'checked' : '') : (getFieldValue('contract') === '1' ? 'checked' : ''); ?> 
                           required>
                    Согласен с условиями
                </label>
                <?php if ($error = getFieldError('contract')): ?>
                    <div class="error"><?php echo $error; ?></div>
                <?php endif; ?>
            </div>

            <button type="submit">Отправить</button>
        </form>
    </div>
</body>
</html>