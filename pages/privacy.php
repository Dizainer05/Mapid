<?php
session_start();
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Политика конфиденциальности - Учет строительных проектов</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Подключение стилей -->
    <link rel="stylesheet" href="../css/index.css">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .content-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .title {
            font-size: 28px;
            font-weight: 600;
            letter-spacing: -1px;
            position: relative;
            display: flex;
            align-items: center;
            padding-left: 30px;
            color: #00bfff; /* Тёмная тема */
        }
        body.light-mode .title {
            color: #007bff; /* Светлая тема */
        }
        .title::before,
        .title::after {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            border-radius: 50%;
            left: 0px;
            background-color: #00bfff;
        }
        body.light-mode .title::before,
        body.light-mode .title::after {
            background-color: #007bff;
        }
        .title::after {
            animation: pulse 1s linear infinite;
        }
        h2 {
            font-size: 1.5rem;
            margin-top: 2rem;
            color: #ffffff; /* Тёмная тема */
        }
        body.light-mode h2 {
            color: #333333; /* Светлая тема */
        }
        p, ul {
            font-size: 1rem;
            line-height: 1.6;
            color: rgba(255, 255, 255, 0.7); /* Тёмная тема */
        }
        body.light-mode p,
        body.light-mode ul {
            color: rgba(0, 0, 0, 0.7); /* Светлая тема */
        }
        ul {
            padding-left: 20px;
        }
        ul li {
            margin-bottom: 10px;
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
            <!-- <ul class="list">
                <li class="element" data-bs-toggle="modal" data-bs-target="#settingsModal">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#7e8590" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-settings">
                        <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l-.43-.25a2 2 0 0 1-2 0l-.15.08a2 2 0 0 0-2.73-.73l-.22-.39a2 2 0 0 0 .73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l-.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path>
                        <circle r="3" cy="12" cx="12"></circle>
                    </svg>
                    <p class="label">Настройки</p>
                </li>
            </ul> -->
        </div>
    </div>

    <!-- Основной контент -->
    <div class="content-container container mt-5 pt-5">
        <h1 class="title">Политика конфиденциальности</h1>
        <p>Дата вступления в силу: 23 мая 2025 года</p>

        <h2>1. Общие положения</h2>
        <p>Настоящая Политика конфиденциальности (далее — Политика) описывает, как система "Учет строительных проектов" (далее — Сервис) собирает, использует, хранит и защищает персональные данные пользователей. Мы стремимся обеспечить конфиденциальность и безопасность ваших данных в соответствии с действующим законодательством "О персональных данных".</p>

        <h2>2. Какие данные мы собираем</h2>
        <p>При использовании Сервиса мы можем собирать следующие персональные данные:</p>
        <ul>
            <li>Логин и пароль (в зашифрованном виде);</li>
            <li>Адрес электронной почты;</li>
            <li>ФИО;</li>
            <li>Должность и подразделение (если предоставлено);</li>
            <li>Технические данные: IP-адрес, тип браузера, данные об активности в Сервисе.</li>
        </ul>

        <h2>3. Цели обработки персональных данных</h2>
        <p>Мы обрабатываем персональные данные для следующих целей:</p>
        <ul>
            <li>Предоставление доступа к функционалу Сервиса;</li>
            <li>Обеспечение безопасности учетных записей;</li>
            <li>Отправка уведомлений, связанных с использованием Сервиса (например, сброс пароля);</li>
            <li>Анализ и улучшение работы Сервиса;</li>
            <li>Соблюдение требований законодательства.</li>
        </ul>

        <h2>4. Передача данных третьим лицам</h2>
        <p>Мы не передаем ваши персональные данные третьим лицам, за исключением случаев:</p>
        <ul>
            <li>Вашего явного согласия;</li>
            <li>Необходимости соблюдения законодательства;</li>
            <li>Использования доверенных сервисов (например, SMTP для отправки писем), которые обязуются соблюдать конфиденциальность.</li>
        </ul>

        <h2>5. Права пользователей</h2>
        <p>Вы имеете право:</p>
        <ul>
            <li>Запрашивать информацию о собранных данных;</li>
            <li>Требовать исправления или удаления ваших данных;</li>
            <li>Отозвать согласие на обработку данных;</li>
            <li>Подать жалобу в надзорный орган.</li>
        </ul>
        <p>Для реализации этих прав свяжитесь с нами по контактам, указанным ниже.</p>

        <h2>6. Безопасность данных</h2>
        <p>Мы применяем следующие меры для защиты ваших данных:</p>
        <ul>
            <li>Шифрование паролей;</li>
            <li>Использование защищённых протоколов (HTTPS, TLS);</li>
            <li>Ограничение доступа к данным только уполномоченным сотрудникам;</li>
            <li>Регулярное обновление систем безопасности.</li>
        </ul>

        <h2>7. Хранение данных</h2>
        <p>Персональные данные хранятся в течение периода, необходимого для достижения целей обработки, или до тех пор, пока вы не запросите их удаление. После этого данные удаляются или анонимизируются.</p>

        <h2>8. Изменения в Политике</h2>
        <p>Мы можем обновлять Политику конфиденциальности. Изменения вступают в силу с момента публикации на этой странице. Мы уведомим вас о существенных изменениях через Сервис или по электронной почте.</p>

        <h2>9. Контакты</h2>
        <p>Если у вас есть вопросы или запросы, связанные с обработкой персональных данных, свяжитесь с нами:</p>
        <ul>
            <li>Email: <a href="mailto:support@mapid.ru">mail@mapid.by</a></li>
            <li>Адрес: г.Минск, ул.Р.Люксембург, 205</li>
        </ul>
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