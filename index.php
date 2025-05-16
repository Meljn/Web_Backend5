<?php
header('Content-Type: text/html; charset=utf-8');

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

$formErrors = [];
foreach ($_COOKIE as $name => $value) {
    if (strpos($name, 'error_') === 0) {
        $formErrors[] = $value;
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

        <?php if (!empty($formErrors)): ?>
            <div class="error-messages">
                <h3>Ошибки при заполнении формы:</h3>
                <ul>
                    <?php foreach ($formErrors as $error): ?>
                        <li><?= $error ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['success'])): ?>
            <div class="success-message">
                Данные успешно сохранены!
            </div>
        <?php endif; ?>

        <form action="form.php" method="post">
            <div class="form-group <?= getFieldError('fio') ? 'has-error' : '' ?>">
                <label for="fio">ФИО:</label>
                <input type="text" id="fio" name="fio" value="<?= getFieldValue('fio') ?>" required>
                <?php if ($error = getFieldError('fio')): ?>
                    <div class="error"><?= $error ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group <?= getFieldError('phone') ? 'has-error' : '' ?>">
                <label for="phone">Телефон:</label>
                <input type="tel" id="phone" name="phone" value="<?= getFieldValue('phone') ?>" required>
                <?php if ($error = getFieldError('phone')): ?>
                    <div class="error"><?= $error ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group <?= getFieldError('email') ? 'has-error' : '' ?>">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?= getFieldValue('email') ?>" required>
                <?php if ($error = getFieldError('email')): ?>
                    <div class="error"><?= $error ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group <?= getFieldError('dob') ? 'has-error' : '' ?>">
                <label for="dob">Дата рождения:</label>
                <input type="date" id="dob" name="dob" value="<?= getFieldValue('dob') ?>" required>
                <?php if ($error = getFieldError('dob')): ?>
                    <div class="error"><?= $error ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group <?= getFieldError('gender') ? 'has-error' : '' ?>">
                <label>Пол:</label>
                <label>
                    <input type="radio" name="gender" value="male" <?= getFieldValue('gender') === 'male' ? 'checked' : '' ?> required> Мужской
                </label>
                <label>
                    <input type="radio" name="gender" value="female" <?= getFieldValue('gender') === 'female' ? 'checked' : '' ?>> Женский
                </label>
                <?php if ($error = getFieldError('gender')): ?>
                    <div class="error"><?= $error ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group <?= getFieldError('language') ? 'has-error' : '' ?>">
                <label for="language">Любимые языки программирования:</label>
                <select id="language" name="language[]" multiple required>
                    <?php
                    $languages = ['Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python', 'Java', 'Haskell', 'Clojure', 'Prolog', 'Scala', 'Go'];
                    $selected = explode(',', getFieldValue('language', ''));
                    foreach ($languages as $lang): ?>
                        <option value="<?= htmlspecialchars($lang) ?>" <?= in_array($lang, $selected) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($lang) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($error = getFieldError('language')): ?>
                    <div class="error"><?= $error ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group <?= getFieldError('bio') ? 'has-error' : '' ?>">
                <label for="bio">Биография:</label>
                <textarea id="bio" name="bio" rows="5" required><?= getFieldValue('bio') ?></textarea>
                <?php if ($error = getFieldError('bio')): ?>
                    <div class="error"><?= $error ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group <?= getFieldError('contract') ? 'has-error' : '' ?>">
                <label>
                    <input type="checkbox" name="contract" <?= getFieldValue('contract') === '1' ? 'checked' : '' ?> required>
                    Согласен с условиями
                </label>
                <?php if ($error = getFieldError('contract')): ?>
                    <div class="error"><?= $error ?></div>
                <?php endif; ?>
            </div>

            <button type="submit">Отправить</button>
        </form>
    </div>
</body>
</html>