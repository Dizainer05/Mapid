<?php
// pages/register.php
session_start();
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Регистрация - Учет строительных проектов</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Подключение стилей -->
    <link rel="stylesheet" href="../css/index.css">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .error-message {
            color: red;
            font-size: 0.9rem;
            margin-top: 5px;
            display: none;
        }
        .input-error {
            border-color: red;
        }
        .alert-error {
            margin-bottom: 20px;
        }
        .form-check-label {
            font-size: 14.5px;
        }
        .form-check-label a {
            color: #00bfff; /* Тёмная тема */
            text-decoration: none;
        }
        .form-check-label a:hover {
            text-decoration: underline;
        }
        body.light-mode .form-check-label a {
            color: #007bff; /* Светлая тема */
        }
    </style>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const offcanvasElement = document.getElementById('offcanvasSidebar');
            const burgerCheckbox = document.getElementById('burgerCheckbox');
            offcanvasElement.addEventListener('hidden.bs.offcanvas', function() {
                burgerCheckbox.checked = false; // Сбрасываем чекбокс при закрытии
            });
            let theme = localStorage.getItem('darkMode') === 'true' ? 'dark-mode' : 'light-mode';
            document.body.classList.add(theme);
            document.body.classList.add("loaded");

            $("#themeSwitchModal").prop("checked", localStorage.getItem('darkMode') === 'true');
            $("#themeSwitchModal").on("change", function() {
                if ($(this).is(":checked")) {
                    $("body").addClass("dark-mode").removeClass("light-mode");
                    localStorage.setItem('darkMode', 'true');
                } else {
                    $("body").removeClass("dark-mode").addClass("light-mode");
                    localStorage.setItem('darkMode', 'false');
                }
            });

            // Валидация формы
            const passwordInput = document.querySelector('input[name="password"]');
            const fullNameInput = document.querySelector('input[name="full_name"]');
            const consentCheckbox = document.querySelector('input[name="consent"]');
            const passwordError = document.getElementById('passwordError');
            const fullNameError = document.getElementById('fullNameError');
            const submitButton = document.querySelector('.submit');

            function validateForm() {
                const password = passwordInput.value;
                const fullName = fullNameInput.value;
                const fullNameRegex = /^[А-Яа-яЁё\s]+$/; // Только кириллица и пробелы
                const fullNameNoDigitsRegex = /^[^0-9]*$/; // Без цифр
                const digitCount = (password.match(/\d/g) || []).length; // Подсчет цифр
                const isPasswordValid = password.length >= 8 && digitCount >= 8; // Длина >= 8 и >= 8 цифр
                const isFullNameValid = fullNameRegex.test(fullName) && fullNameNoDigitsRegex.test(fullName);
                const isConsentChecked = consentCheckbox.checked;

                // Валидация пароля
                if (!isPasswordValid) {
                    passwordInput.classList.add('input-error');
                    passwordError.style.display = 'block';
                } else {
                    passwordInput.classList.remove('input-error');
                    passwordError.style.display = 'none';
                }

                // Валидация ФИО
                if (!isFullNameValid) {
                    fullNameInput.classList.add('input-error');
                    fullNameError.style.display = 'block';
                } else {
                    fullNameInput.classList.remove('input-error');
                    fullNameError.style.display = 'none';
                }

                // Проверка согласия и активация кнопки
                submitButton.disabled = !(isPasswordValid && isFullNameValid && isConsentChecked);
            }

            passwordInput.addEventListener('input', validateForm);
            fullNameInput.addEventListener('input', validateForm);
            consentCheckbox.addEventListener('change', validateForm);

            // Изначальная проверка при загрузке
            validateForm();
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
                        <a class="nav-link" href="auth.php">Войти</a>
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
                <li class="element" onclick="window.location.href='auth.php'">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#7e8590" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-log-in">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                        <polyline points="10 17 15 12 10 7"></polyline>
                        <line x1="15" y1="12" x2="3" y2="12"></line>
                    </svg>
                    <p class="label">Войти</p>
                </li>
            </ul>
            <div class="separator"></div>
        </div>
    </div>

    <!-- Основной контент -->
    <div class="content-container container mt-5 pt-5">
        <?php if (isset($_SESSION["error_msg"])): ?>
            <div class="alert alert-danger alert-error">
                <?php echo htmlspecialchars($_SESSION["error_msg"]); ?>
            </div>
        <?php endif; ?>
        <form class="form" method="post" action="../process/process_registration.php" id="registrationForm">
            <p class="title">Регистрация</p>
            <p class="message">Зарегистрируйтесь, чтобы получить доступ к приложению.</p>
            <label>
                <input class="input" type="text" placeholder="" required="" name="username" value="<?php echo isset($_SESSION['form_data']['username']) ? htmlspecialchars($_SESSION['form_data']['username']) : ''; ?>">
                <span>Логин*</span>
            </label>
            <label>
                <input class="input" type="password" placeholder="" required="" name="password">
                <span>Пароль*</span>
                <div id="passwordError" class="error-message">Пароль должен быть не короче 8 символов и содержать не менее 8 цифр.</div>
            </label>
            <label>
                <input class="input" type="email" placeholder="" required="" name="email" value="<?php echo isset($_SESSION['form_data']['email']) ? htmlspecialchars($_SESSION['form_data']['email']) : ''; ?>">
                <span>Электронная почта*</span>
            </label>
            <label>
                <input class="input" type="text" placeholder="" required="" name="full_name" value="<?php echo isset($_SESSION['form_data']['full_name']) ? htmlspecialchars($_SESSION['form_data']['full_name']) : ''; ?>">
                <span>ФИО*</span>
                <div id="fullNameError" class="error-message">ФИО должно содержать только кириллицу и пробелы, без цифр.</div>
            </label>
            <label>
                <input class="input" type="text" placeholder="" name="position" value="<?php echo isset($_SESSION['form_data']['position']) ? htmlspecialchars($_SESSION['form_data']['position']) : ''; ?>">
                <span>Должность</span>
            </label>
            <label>
                <input class="input" type="text" placeholder="" name="department" value="<?php echo isset($_SESSION['form_data']['department']) ? htmlspecialchars($_SESSION['form_data']['department']) : ''; ?>">
                <span>Подразделение</span>
            </label>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="consentCheckbox" name="consent" required>
                <label class="form-check-label" for="consentCheckbox">
                    Я согласен на обработку персональных данных (<a href="privacy.php" target="_blank">политика конфиденциальности</a>)
                </label>
            </div>
            <button class="submit" type="submit">Зарегистрироваться</button>
            <p class="signin">Уже есть аккаунт? <a href="auth.php">Войти</a></p>
        </form>
        <?php
        // Очищаем данные формы и сообщения об ошибках после отображения
        unset($_SESSION['form_data']);
        unset($_SESSION['error_msg']);
        ?>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>