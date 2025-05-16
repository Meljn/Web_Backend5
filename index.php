<?php
session_start();

// --- Database Connection ---
// ... (your DB connection code) ...
// --- End Database Connection ---

// --- Logout Logic ---
// ... (your logout logic) ...
// --- End Logout Logic ---

header('Content-Type: text/html; charset=utf-8');

// ---- START DEBUGGING ----
echo "<pre>DEBUG INFO:\n";
echo "SESSION DATA:\n";
var_dump($_SESSION);

$is_logged_in_debug = isset($_SESSION['user_id']);
echo "Is Logged In (isset(\$_SESSION['user_id'])): ";
var_dump($is_logged_in_debug);

if ($is_logged_in_debug) {
    echo "User ID from session: ";
    var_dump($_SESSION['user_id']);
}
echo "</pre><hr>";
// ---- END DEBUGGING ----


// Function to fetch application data for logged-in user
function fetchApplicationData($pdo_conn, $application_id) {
    // ---- START DEBUGGING fetchApplicationData ----
    echo "<pre>DEBUG fetchApplicationData:\n";
    echo "Fetching data for Application ID: ";
    var_dump($application_id);
    // ---- END DEBUGGING fetchApplicationData ----

    // MAKE SURE 'Application_ID' IS THE CORRECT COLUMN NAME FOR YOUR TABLE'S PRIMARY KEY
    $stmt = $pdo_conn->prepare("SELECT * FROM Application WHERE Application_ID = :id");
    $stmt->execute([':id' => $application_id]);
    $db_row = $stmt->fetch(PDO::FETCH_ASSOC);

    // ---- START DEBUGGING fetchApplicationData ----
    echo "DB Row Fetched:\n";
    var_dump($db_row);
    // ---- END DEBUGGING fetchApplicationData ----

    if (!$db_row) return null;

    $form_data = [];
    // ENSURE THESE COLUMN NAMES MATCH YOUR 'Application' TABLE EXACTLY
    $form_data['fio'] = $db_row['FIO'] ?? null;
    $form_data['phone'] = $db_row['Phone_number'] ?? null;
    $form_data['email'] = $db_row['Email'] ?? null;
    $form_data['dob'] = $db_row['Birth_day'] ?? null;
    $form_data['gender'] = $db_row['Gender'] ?? null;
    $form_data['bio'] = $db_row['Biography'] ?? null;
    $form_data['contract'] = !empty($db_row['Contract_accepted']) ? '1' : '0';

    // ---- START DEBUGGING fetchApplicationData ----
    echo "Languages query for Application ID: ";
    var_dump($application_id);
    // ---- END DEBUGGING fetchApplicationData ----

    // ENSURE 'Application_ID' IS THE CORRECT FOREIGN KEY COLUMN NAME IN 'Application_Languages'
    // AND 'Language_ID' IS THE CORRECT PK IN 'Programming_Languages' AND FK IN 'Application_Languages'
    // AND 'Name' IS THE CORRECT COLUMN IN 'Programming_Languages'
    $stmt_lang = $pdo_conn->prepare("
        SELECT pl.Name
        FROM Application_Languages al
        JOIN Programming_Languages pl ON al.Language_ID = pl.Language_ID
        WHERE al.Application_ID = :id
    ");
    $stmt_lang->execute([':id' => $application_id]);
    $form_data['language'] = $stmt_lang->fetchAll(PDO::FETCH_COLUMN, 0);

    // ---- START DEBUGGING fetchApplicationData ----
    echo "Languages fetched:\n";
    var_dump($form_data['language']);
    echo "Full form_data to be returned:\n";
    var_dump($form_data);
    echo "</pre><hr>";
    // ---- END DEBUGGING fetchApplicationData ----
    return $form_data;
}

$user_data_for_form = null;
$is_logged_in = isset($_SESSION['user_id']); // This is the one used by the main logic

if ($is_logged_in) {
    $user_data_for_form = fetchApplicationData($pdo, $_SESSION['user_id']);
    // ---- START DEBUGGING ----
    echo "<pre>DEBUG After fetchApplicationData call:\n";
    echo "User Data For Form:\n";
    var_dump($user_data_for_form);
    echo "</pre><hr>";
    // ---- END DEBUGGING ----
}

// ... (rest of your index.php: getFieldValue, getFieldError, HTML, etc.) ...
// Make sure to replace Application_ID with your actual ID column name in fetchApplicationData
// And ensure FIO, Phone_number, etc. are correct column names in your Application table.
// And Application_ID, Language_ID, Name are correct in the languages query.


function getFieldValue($fieldName, $default = '', $loggedInDbData = null) {
    // Priority 1: Value from a previous failed submission (sticky form)
    if (isset($_COOKIE["value_$fieldName"])) {
        $value = $_COOKIE["value_$fieldName"];
        // For select multiple, cookie stores comma-separated string
        if ($fieldName === 'language' && is_string($value)) {
            return !empty($value) ? array_map('htmlspecialchars', explode(',', $value)) : [];
        }
        return htmlspecialchars($value);
    }

    // Priority 2: Value from the database if logged in and editing
    if ($loggedInDbData !== null && isset($loggedInDbData[$fieldName])) {
        if (is_array($loggedInDbData[$fieldName])) { // For 'language'
            return array_map('htmlspecialchars', $loggedInDbData[$fieldName]);
        }
        return htmlspecialchars($loggedInDbData[$fieldName]);
    }

    // Priority 3: Value from a successful submission (if success cookies are still around from non-logged in state)
    // Only use success_ cookies if NOT logged in, to prevent them overriding DB data after logout->login
    if (!$loggedInDbData && isset($_COOKIE["success_$fieldName"])) {
        $value = $_COOKIE["success_$fieldName"];
        if ($fieldName === 'language' && is_string($value)) {
            return !empty($value) ? array_map('htmlspecialchars', explode(',', $value)) : [];
        }
        return htmlspecialchars($value);
    }
    return is_array($default) ? array_map('htmlspecialchars', $default) : htmlspecialchars($default);
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
    if (strpos($name, 'error_') === 0 && $name !== 'error_login') { // Exclude general login error
        $formErrors[] = htmlspecialchars($value); // Ensure errors are escaped
    }
}

// Display generated credentials if available
$generated_credentials_message = '';
if (isset($_GET['new_user']) && $_GET['new_user'] == '1' && isset($_SESSION['generated_login'])) {
    $generated_credentials_message = "<div class='success-message credentials-box'><strong>Ваша заявка успешно отправлена!</strong><br>Для последующего редактирования используйте следующие данные для входа:<br>Логин: <strong>" . htmlspecialchars($_SESSION['generated_login']) . "</strong><br>Пароль: <strong>" . htmlspecialchars($_SESSION['generated_password']) . "</strong><p class='warning'>Запомните или сохраните эти данные. Пароль отображается только один раз.</p></div>";
    unset($_SESSION['generated_login']);
    unset($_SESSION['generated_password']);
}

// Login error message
$login_error_message = '';
if (isset($_SESSION['login_error'])) {
    $login_error_message = "<div class='error-messages'><p>" . htmlspecialchars($_SESSION['login_error']) . "</p></div>";
    unset($_SESSION['login_error']);
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

        <?php if ($is_logged_in): ?>
            <div class="user-panel">
                <span>Вы вошли как: <strong><?= htmlspecialchars($_SESSION['login']) ?></strong></span>
                <a href="index.php?action=logout" class="button">Выйти</a>
            </div>
            <h1>Редактирование данных</h1>
        <?php else: ?>
            <h1>Форма обратной связи</h1>
            <div id="login-form" class="login-form login-panel">
                <h2>Вход для редактирования</h2>
                <?= $login_error_message ?>
                <form action="login.php" method="post">
                    <div class="form-group">
                        <label for="login_user">Логин:</label>
                        <input type="text" id="login_user" name="login" required>
                    </div>
                    <div class="form-group">
                        <label for="login_pass">Пароль:</label>
                        <input type="password" id="login_pass" name="password" required>
                    </div>
                    <button type="submit">Войти</button>
                </form>
                <hr style="margin: 20px 0; border-color: #404040;">
            </div>
        <?php endif; ?>


        <?= $generated_credentials_message ?>

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

        <?php if (isset($_GET['success']) && $_GET['success'] == '1' && empty($generated_credentials_message)): ?>
            <div class="success-message">
                Данные успешно сохранены!
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['updated']) && $_GET['updated'] == '1'): ?>
            <div class="success-message">
                Данные успешно обновлены!
            </div>
        <?php endif; ?>


        <form action="form.php" method="post">
            <div class="form-group <?= getFieldError('fio') ? 'has-error' : '' ?>">
                <label for="fio">ФИО:</label>
                <input type="text" id="fio" name="fio" value="<?= getFieldValue('fio', '', $user_data_for_form) ?>" required>
                <?php if ($error = getFieldError('fio')): ?>
                    <div class="error"><?= $error ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group <?= getFieldError('phone') ? 'has-error' : '' ?>">
                <label for="phone">Телефон:</label>
                <input type="tel" id="phone" name="phone" value="<?= getFieldValue('phone', '', $user_data_for_form) ?>" required>
                <?php if ($error = getFieldError('phone')): ?>
                    <div class="error"><?= $error ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group <?= getFieldError('email') ? 'has-error' : '' ?>">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?= getFieldValue('email', '', $user_data_for_form) ?>" required>
                <?php if ($error = getFieldError('email')): ?>
                    <div class="error"><?= $error ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group <?= getFieldError('dob') ? 'has-error' : '' ?>">
                <label for="dob">Дата рождения:</label>
                <input type="date" id="dob" name="dob" value="<?= getFieldValue('dob', '', $user_data_for_form) ?>" required>
                <?php if ($error = getFieldError('dob')): ?>
                    <div class="error"><?= $error ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group <?= getFieldError('gender') ? 'has-error' : '' ?>">
                <label>Пол:</label>
                <label>
                    <input type="radio" name="gender" value="male" <?= getFieldValue('gender', '', $user_data_for_form) === 'male' ? 'checked' : '' ?> required> Мужской
                </label>
                <label>
                    <input type="radio" name="gender" value="female" <?= getFieldValue('gender', '', $user_data_for_form) === 'female' ? 'checked' : '' ?>> Женский
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
                    $selected_languages = getFieldValue('language', [], $user_data_for_form);
                    if (!is_array($selected_languages)) { 
                        $selected_languages = !empty($selected_languages) ? explode(',', $selected_languages) : [];
                    }
                    foreach ($languages as $lang): ?>
                        <option value="<?= htmlspecialchars($lang) ?>" <?= in_array(htmlspecialchars($lang), $selected_languages) ? 'selected' : '' ?>>
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
                <textarea id="bio" name="bio" rows="5" required><?= getFieldValue('bio', '', $user_data_for_form) ?></textarea>
                <?php if ($error = getFieldError('bio')): ?>
                    <div class="error"><?= $error ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group <?= getFieldError('contract') ? 'has-error' : '' ?>">
                <label>
                    <input type="checkbox" name="contract" value="1" <?= getFieldValue('contract', '', $user_data_for_form) === '1' ? 'checked' : '' ?> required>
                    Согласен с условиями
                </label>
                <?php if ($error = getFieldError('contract')): ?>
                    <div class="error"><?= $error ?></div>
                <?php endif; ?>
            </div>

            <button type="submit"><?= $is_logged_in ? 'Сохранить изменения' : 'Отправить' ?></button>
        </form>
    </div>
</body>
</html>