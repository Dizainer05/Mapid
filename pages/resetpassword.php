<?php
session_start();
// Подключаем файл db.php
require __DIR__ . '/../db.php'; // Путь к db.php в корне проекта
require __DIR__ . '/../vendor/autoload.php'; // Composer autoload
use PHPMailer\PHPMailer\PHPMailer;

// Функция для отправки email
function sendResetEmail($email, $token) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'gmail.com'; // Твой Gmail адрес Поменяйте на свой
        $mail->Password = 'sqsy juwu cdok vbwl'; // Пароль приложения Поменяйте на свой
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        // Настройки кодировки
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->setLanguage('ru');

        $mail->setFrom('gmail.com', 'МАПИД');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Сброс пароля МАПИД';
        $mail->Body = "<h2>Сброс пароля</h2><p>Перейдите по ссылке для сброса пароля: <a href='http://dbkurs/pages/resetpassword.php?step=reset&token=$token&email=$email'>Сбросить пароль</a></p>";
        $mail->AltBody = "Перейдите по ссылке для сброса пароля: http://dbkurs/pages/resetpassword.php?step=reset&token=$token&email=$email";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return "Ошибка: " . $mail->ErrorInfo;
    }
}

$step = isset($_GET['step']) ? $_GET['step'] : 'forgot';

// Обрабатываем POST-запрос для отправки email перед выводом
if ($step === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $_SESSION['temp_email'] = $email; // Сохраняем email временно
    $token = createPasswordResetToken($conn, $email);

    if ($token === false) {
        $_SESSION['error_msg'] = 'Email не найден.';
    } else {
        $emailResult = sendResetEmail($email, $token);
        if ($emailResult === true) {
            $_SESSION['success_msg'] = 'Ссылка для сброса пароля отправлена на ваш email.';
        } else {
            $_SESSION['error_msg'] = $emailResult;
        }
    }
    // Перенаправление для синхронизации сессии
    header('Location: resetpassword.php?step=send');
    exit();
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Восстановление пароля - Учет строительных проектов</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Подключение стилей -->
    <link rel="stylesheet" href="../css/index.css">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .alert-error {
            margin-bottom: 20px;
        }
        .alert-success {
            margin-bottom: 20px;
        }
    </style>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const offcanvasElement = document.getElementById('offcanvasSidebar');
            const burgerCheckbox = document.getElementById('burgerCheckbox');
            offcanvasElement.addEventListener('hidden.bs.offcanvas', function() {
                burgerCheckbox.checked = false;
            });
            let isDarkMode = localStorage.getItem('darkMode') !== 'false';
            let theme = isDarkMode ? 'dark-mode' : 'light-mode';
            document.body.classList.add(theme);
            document.body.classList.add("loaded");

            $("#themeSwitchModal").prop("checked", isDarkMode);
            $("#themeSwitchModal").on("change", function() {
                if ($(this).is(":checked")) {
                    $("body").addClass("dark-mode").removeClass("light-mode");
                    localStorage.setItem('darkMode', 'true');
                } else {
                    $("body").removeClass("dark-mode").addClass("light-mode");
                    localStorage.setItem('darkMode', 'false');
                }
            });

            // Автозакрытие уведомлений через 5 секунд
            setTimeout(() => {
                document.querySelectorAll('.alert').forEach(alert => alert.remove());
            }, 5000);
        });
    </script>
</head>
<body>
    <!-- Навигационная панель -->
    <nav class="navbar navbar-expand-lg fixed-top shadow-sm">
        <div class="container-fluid">
            <!-- Бургер с анимацией -->
            <label class="hamburger me-2">
                <input type="checkbox" id="burgerCheckbox" data-bs-toggle="offcanvas" data-bs-target="#offcanvasSidebar" aria-controls="offcanvasSidebar">
                <svg viewBox="0 0 32 32">
                    <path class="line line-top-bottom" d="M27 10 13 10C10.8 10 9 8.2 9 6 9 3.5 10.8 2 13 2 15.2 2 17 3.8 17 6L17 26C17 28.2 18.8 30 21 30 23.2 30 25 28.2 25 26 25 23.8 23.2 22 21 22L7 22"></path>
                    <path class="line" d="M7 16 27 16"></path>
                </svg>
            </label>
            <a class="navbar-brand" href="../index.php">Учет проектов</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">Регистрация</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="auth.php">Авторизация</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Боковая панель -->
    <div class="offcanvas offcanvas-start" tabindex="-1" id="offcanvasSidebar" aria-labelledby="offcanvasSidebarLabel">
        <div class="offcanvas-header">
            <h5 id="offcanvasSidebarLabel">Меню</h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <ul class="list">
                <li class="element" onclick="window.location.href='../index.php'">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#7e8590" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-home">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                    </svg>
                    <p class="label">Главная</p>
                </li>
                <li class="element" onclick="window.location.href='register.php'">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#7e8590" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-plus">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="8.5" cy="7" r="4"></circle>
                        <line x1="20" y1="8" x2="20" y2="14"></line>
                        <line x1="23" y1="11" x2="17" y2="11"></line>
                    </svg>
                    <p class="label">Регистрация</p>
                </li>
                <li class="element" onclick="window.location.href='auth.php'">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#7e8590" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-log-in">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                        <polyline points="10 17 15 12 10 7"></polyline>
                        <line x1="15" y1="12" x2="3" y2="12"></line>
                    </svg>
                    <p class="label">Авторизация</p>
                </li>
            </ul>
            <div class="separator"></div>
        </div>
    </div>

    <!-- Основной контент -->
    <div class="content-container container mt-5 pt-5">
        <?php if ($step === 'forgot' || $step === 'send'): ?>
            <!-- Форма для ввода email -->
            <form class="form" method="post" action="resetpassword.php?step=send">
                <p class="title">Восстановление пароля</p>
                <p class="message">Введите ваш email для получения ссылки на сброс пароля.</p>
                <?php if (isset($_SESSION['error_msg'])): ?>
                    <div class="alert alert-danger alert-error">
                        <?php echo htmlspecialchars($_SESSION['error_msg']); ?>
                    </div>
                    <?php unset($_SESSION['error_msg']); // Очищаем после отображения ?>
                <?php endif; ?>
                <?php if (isset($_SESSION['success_msg'])): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($_SESSION['success_msg']); ?>
                    </div>
                    <?php unset($_SESSION['success_msg']); // Очищаем после отображения ?>
                <?php endif; ?>
                <label>
                    <input class="input" type="email" placeholder="" required name="email" value="<?php echo isset($_SESSION['temp_email']) ? htmlspecialchars($_SESSION['temp_email']) : (isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''); ?>">
                    <span>Email</span>
                </label>
                <button class="submit" type="submit">Отправить ссылку</button>
                <p class="signin">Вернуться к <a href="auth.php">авторизации</a></p>
            </form>
            <?php unset($_SESSION['temp_email']); // Очищаем временный email после рендеринга ?>

        <?php elseif ($step === 'reset'): ?>
            <!-- Форма для ввода нового пароля -->
            <?php
            $error_msg = ''; // Локальная переменная для ошибок этапа reset
            $token = $_GET['token'] ?? '';
            $email = $_GET['email'] ?? '';
            $userId = verifyPasswordResetToken($conn, $token);

            if ($userId === false) {
                $error_msg = 'Недействительный или истекший токен.';
                echo '<div class="alert alert-danger alert-error">' . htmlspecialchars($error_msg) . '</div>';
            } else {
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $newPassword = $_POST['password'] ?? '';
                    if (strlen($newPassword) < 8) {
                        $error_msg = 'Пароль должен быть не менее 8 символов.';
                        echo '<div class="alert alert-danger alert-error">' . htmlspecialchars($error_msg) . '</div>';
                    } else {
                        updateUserPassword($conn, $userId, $newPassword);
                        echo '<div class="alert alert-success">Пароль успешно обновлен. <a href="auth.php">Войти</a></div>';
                    }
                } else {
            ?>
                    <form class="form" method="post" action="resetpassword.php?step=reset&token=<?php echo htmlspecialchars($token); ?>&email=<?php echo htmlspecialchars($email); ?>">
                        <p class="title">Новый пароль</p>
                        <p class="message">Введите новый пароль для вашего аккаунта.</p>
                        <?php if ($error_msg): ?>
                            <div class="alert alert-danger alert-error">
                                <?php echo htmlspecialchars($error_msg); ?>
                            </div>
                        <?php endif; ?>
                        
                        <label>
    <input class="input" type="password" placeholder="" required name="password">
    <span>Пароль*</span>
    <div id="passwordError" class="error-message">
        Пароль должен состоять из не менее 8 символов.
    </div>
</label>
                        <button class="submit" type="submit">Сохранить</button>
                        <p class="signin">Вернуться к <a href="auth.php">авторизации</a></p>
                    </form>
            <?php
                }
            }
            ?>

        <?php else: ?>
            <div class="alert alert-danger alert-error">Неверный шаг процесса восстановления.</div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>