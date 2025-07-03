<?php
// pages/auth.php
session_start();
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Авторизация - Учет строительных проектов</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
                burgerCheckbox.checked = false; // Сбрасываем чекбокс при закрытии
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
            <a class="navbar-brand" href="index.php">Учет проектов</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">Регистрация</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Боковая панель -->
    <div class="offcanvas offcanvas-start" tabindex="-1" id="offcanvasSidebar" aria-labelledby="offcanvasSidebarLabel">
        <div class="offcanvas-header">
            <h5 id="offcanvasSidebarLabel">Меню</h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Закрыть"></button>
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
            </ul>
            <div class="separator"></div>
            <!-- <ul class="list">
                <li class="element" data-bs-toggle="modal" data-bs-target="#settingsModal">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#7e8590" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-settings">
                        <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1     <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l-.43-.25a2 2 0 0 1-2 0l-.15.08a2 2 0 0 0-2.73-.73l-.22-.39a2 2 0 0 0 .73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path>
                        <circle r="3" cy="12" cx="12"></circle>
                    </svg>
                    <p class="label">Настройки</p>
                </li>
            </ul> -->
        </div>
    </div>

    <!-- Основной контент -->
    <div class="content-container container mt-5 pt-5">
        <?php if (isset($_SESSION["success_msg"])): ?>
            <div class="alert alert-success alert-success">
                <?php echo htmlspecialchars($_SESSION["success_msg"]); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION["error_msg"])): ?>
            <div class="alert alert-danger alert-error">
                <?php echo htmlspecialchars($_SESSION["error_msg"]); ?>
            </div>
        <?php endif; ?>
        <form class="form" method="post" action="../process/process_login.php" id="loginForm">
            <p class="title">Авторизация</p>
            <p class="message">Войдите, чтобы получить доступ к приложению.</p>
            <label>
                <input class="input" type="text" placeholder="" required="" name="username" value="<?php echo isset($_SESSION['form_data']['username']) ? htmlspecialchars($_SESSION['form_data']['username']) : ''; ?>">
                <span>Логин</span>
            </label>
            <label>
                <input class="input" type="password" placeholder="" required="" name="password">
                <span>Пароль</span>
            </label>
            <button class="submit" type="submit">Войти</button>
            <p class="signin">Нет аккаунта? <a href="register.php">Зарегистрироваться</a></p>
            <p class="signin">Забыли пароль? <a href="resetpassword.php">Восстановить</a></p>
        </form>
        <?php
        // Очищаем данные сессии после отображения
        unset($_SESSION['form_data']);
        unset($_SESSION['error_msg']);
        unset($_SESSION['success_msg']);
        ?>
    </div>

    <!-- Модальное окно настроек -->
    <!-- <div class="modal fade" id="settingsModal" tabindex="-1" aria-labelledby="settingsModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 id="settingsModalLabel" class="modal-title">Настройки</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="themeSwitchModal">
                        <label class="form-check-label" for="themeSwitchModal">Темная тема</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Сохранить настройки</button>
                </div>
            </div>
        </div>
    </div> -->

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>