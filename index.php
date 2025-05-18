<?php
header('Content-Type: text/html; charset=utf-8');
session_start();

function getFieldValue($fieldName, $default = '') {
    if (isset($_COOKIE["value_$fieldName"])) {
        return htmlspecialchars($_COOKIE["value_$fieldName"]);
    }
    if (isset($_COOKIE["success_$fieldName"])) {
        return htmlspecialchars($_COOKIE["success_$fieldName"]);
    }
    return $default;
}

function getFieldError($fieldName) {
    if (isset($_COOKIE["error_$fieldName"])) {
        return htmlspecialchars($_COOKIE["error_$fieldName"]);
    }
    return '';
}

$formErrors = [];
foreach ($_COOKIE as $name => $value) {
    if (strpos($name, 'error_') === 0) {
        $formErrors[] = $value;
    }
}

$userData = [];
if (isset($_SESSION['user_id'])) {
    $db_host = 'localhost';
    $db_user = 'u68532';
    $db_pass = '9110579';
    $db_name = 'u68532';

    try {
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $stmt = $pdo->prepare("SELECT * FROM Application WHERE ID = ?");
        $stmt->execute([$_SESSION['app_id']]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT pl.Name 
                               FROM Programming_Languages pl
                               JOIN Application_Languages al ON pl.Language_ID = al.Language_ID
                               WHERE al.Application_ID = ?");
        $stmt->execute([$_SESSION['app_id']]);
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
    <title>Форма</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="form-container">
    <h1>Форма заявки</h1>

    <?php if (isset($_SESSION['user_id'])): ?>
        <div>Вы вошли как <?= htmlspecialchars($_SESSION['login']) ?></div>
        <form action="logout.php" method="post">
            <button type="submit">Выйти</button>
        </form>
    <?php endif; ?>

    <?php if (!empty($formErrors)): ?>
        <div class="error">
            <ul>
                <?php foreach ($formErrors as $error): ?>
                    <li><?= $error ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['success'])): ?>
        <div class="success">Данные успешно сохранены!</div>
    <?php endif; ?>

    <form action="save.php" method="POST">
        <div class="form-group <?= getFieldError('name') ? 'has-error' : '' ?>">
            <label for="name">Имя:</label>
            <input type="text" name="name" value="<?= isset($userData['Name']) ? htmlspecialchars($userData['Name']) : getFieldValue('name') ?>" required>
        </div>

        <div class="form-group <?= getFieldError('email') ? 'has-error' : '' ?>">
            <label for="email">Email:</label>
            <input type="email" name="email" value="<?= isset($userData['Email']) ? htmlspecialchars($userData['Email']) : getFieldValue('email') ?>" required>
        </div>

        <div class="form-group <?= getFieldError('year') ? 'has-error' : '' ?>">
            <label for="year">Год рождения:</label>
            <select name="year" required>
                <?php
                $selectedYear = isset($userData['Year']) ? $userData['Year'] : getFieldValue('year');
                for ($i = 2005; $i >= 1900; $i--) {
                    $selected = ($i == $selectedYear) ? 'selected' : '';
                    echo "<option value=\"$i\" $selected>$i</option>";
                }
                ?>
            </select>
        </div>

        <div class="form-group <?= getFieldError('gender') ? 'has-error' : '' ?>">
            <label>Пол:</label>
            <?php
            $gender = isset($userData['Gender']) ? $userData['Gender'] : getFieldValue('gender');
            ?>
            <label>
                <input type="radio" name="gender" value="male" <?= $gender === 'male' ? 'checked' : '' ?> required> Мужской
            </label>
            <label>
                <input type="radio" name="gender" value="female" <?= $gender === 'female' ? 'checked' : '' ?> required> Женский
            </label>
        </div>

        <div class="form-group <?= getFieldError('limbs') ? 'has-error' : '' ?>">
            <label>Конечности:</label>
            <?php
            $limbs = isset($userData['Limbs']) ? $userData['Limbs'] : getFieldValue('limbs');
            for ($i = 1; $i <= 4; $i++): ?>
                <label>
                    <input type="radio" name="limbs" value="<?= $i ?>" <?= $limbs == $i ? 'checked' : '' ?> required> <?= $i ?>
                </label>
            <?php endfor; ?>
        </div>

        <div class="form-group <?= getFieldError('languages') ? 'has-error' : '' ?>">
            <label for="languages">Любимые языки программирования:</label>
            <select name="languages[]" multiple>
                <?php
                $allLanguages = ['Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python', 'Java', 'Haskell', 'Clojure', 'Prolog', 'Scala'];
                $selectedLangs = isset($userData['language']) ? $userData['language'] : explode(',', getFieldValue('languages'));

                foreach ($allLanguages as $lang) {
                    $selected = in_array($lang, $selectedLangs) ? 'selected' : '';
                    echo "<option value=\"$lang\" $selected>$lang</option>";
                }
                ?>
            </select>
        </div>

        <div class="form-group <?= getFieldError('bio') ? 'has-error' : '' ?>">
            <label for="bio">Биография:</label>
            <textarea name="bio"><?= isset($userData['Bio']) ? htmlspecialchars($userData['Bio']) : getFieldValue('bio') ?></textarea>
        </div>

        <div class="form-group <?= getFieldError('policy') ? 'has-error' : '' ?>">
            <label>
                <input type="checkbox" name="policy" required <?= getFieldValue('policy') ? 'checked' : '' ?>> Согласен с политикой обработки данных
            </label>
        </div>

        <button type="submit">Отправить</button>
    </form>
</div>
</body>
</html>
