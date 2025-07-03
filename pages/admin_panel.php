<?php
session_start();
require_once __DIR__ . '/../db.php'; // Подключение к базе данных

// Проверка прав доступа
if (!isset($_SESSION["user_id"]) || $_SESSION["role_id"] != 1) {
    header("Location: ../pages/register.php");
    exit();
}

// Получение данных пользователя
$username = $_SESSION["username"];
$user_id = $_SESSION["user_id"];

// Получаем аватар пользователя из базы данных
$sql = "SELECT avatar FROM users WHERE user_id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("index.php: Ошибка подготовки запроса для аватара: " . $conn->error);
    die("Ошибка сервера");
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$userAvatar = (!empty($user['avatar']) && file_exists(__DIR__ . '/../Uploads/' . $user['avatar'])) 
    ? $user['avatar'] 
    : 'default_avatar.jpg';
$result->free();
$stmt->close();

// Функция для получения всех проектов с деталями
function getAllProjectsWithDetails($conn) {
    $stmt = $conn->prepare("
        SELECT 
            p.project_id,
            p.name,
            p.status,
            p.planned_budget,
            p.planned_start_date,
            p.planned_end_date,
            COUNT(pp.user_id) as participants
        FROM projects p
        LEFT JOIN project_participants pp ON p.project_id = pp.project_id
        GROUP BY p.project_id
    ");
    if ($stmt === false) {
        error_log("Ошибка подготовки запроса getAllProjectsWithDetails: " . $conn->error);
        return [];
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $projects = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $projects;
}

// Функция для получения количества пользователей
function getUserCount($conn) {
    $stmt = $conn->prepare("SELECT COUNT(*) as user_count FROM users");
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result['user_count'];
}

// Функция для получения количества проектов
function getProjectCount($conn) {
    $stmt = $conn->prepare("SELECT COUNT(*) as project_count FROM projects");
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result['project_count'];
}

// Функция для получения русского названия статуса
function getRussianStatus($status) {
    switch ($status) {
        case 'planning': return 'Планирование';
        case 'active': return 'Активные';
        case 'in_progress': return 'В процессе';
        case 'completed': return 'Завершённые';
        case 'on_hold': return 'Приостановлены';
        default: return 'Неизвестный';
    }
}

// Получение статистики
$user_count = getUserCount($conn);
$project_count = getProjectCount($conn);

// Получение проектов для статического вывода и диаграмм
$projects = getAllProjectsWithDetails($conn);
error_log("Количество проектов из базы: " . count($projects));
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админская панель</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background: #f8f9fa; transition: background-color 0.3s, color 0.3s; }
        .navbar { z-index: 1050; }
        .offcanvas-start { width: 280px; }
        .content-container { margin-top: 80px; transition: margin-left 0.3s; }
        .dark-mode { background-color: #121212 !important; color: #e0e0e0 !important; }
        .dark-mode .navbar, .dark-mode .offcanvas, .dark-mode .card, .dark-mode .modal-content { background-color: #121212 !important; color: #e0e0e0 !important; }
        .dark-mode .navbar-brand, .dark-mode .nav-link { color: #ffffff !important; }
        .dark-mode .form-control, .dark-mode .form-select { background-color: #2c2c2c !important; color: #e0e0e0 !important; border-color: #555 !important; }
        .dark-mode .navbar-toggler-icon { filter: invert(1) brightness(2); }
        .dark-mode .btn-close { filter: invert(1); }
        .card { transition: transform 0.2s; border-radius: 0.75rem; }
        .card:hover { transform: translateY(-2px); }
        .table-hover tbody tr:hover { background-color: rgba(0,0,0,0.05); }
        .badge { font-size: 0.85em; padding: 0.5em 0.75em; }
        .bi { font-size: 1.2rem; vertical-align: middle; }
        .card-header { background: rgba(0,0,0,0.03); font-weight: 600; }
        .form-label { font-weight: 500; }
        .form-control, .form-select { border-radius: 0.5rem; margin-bottom: 10px; }
        .sortable {
            cursor: pointer;
            position: relative;
            padding-right: 20px;
        }
        .sortable::after {
            content: '↕';
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
        }
        .sortable.asc::after {
            content: '↑';
        }
        .sortable.desc::after {
            content: '↓';
        }
        .table .btn-sm {
            margin-right: 5px;
        }
        .required::after {
            content: '*';
            color: red;
            margin-left: 4px;
        }
        .file-upload-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .file-upload-icon {
            position: relative;
            width: 40px;
            height: 40px;
        }
        .file-upload-front, .file-upload-back {
            position: absolute;
            width: 100%;
            height: 100%;
        }
        .file-upload-front {
            z-index: 2;
        }
        .file-upload-back {
            background: #e0e0e0;
            top: 5px;
            left: 5px;
        }
        .file-upload-body {
            background: #ffffff;
            border: 2px solid #6c757d;
            border-radius: 5px;
            width: 100%;
            height: 80%;
            top: 20%;
        }
        .file-upload-tab {
            background: #ffffff;
            border: 2px solid #6c757d;
            border-bottom: none;
            border-radius: 5px 5px 0 0;
            width: 60%;
            height: 20%;
            left: 20%;
        }
        .file-upload-button {
            cursor: pointer;
            padding: 5px 10px;
            background: #6c757d;
            color: white;
            border-radius: 5px;
            display: inline-block;
        }
        .file-input {
            display: none;
        }
        .password-toggle {
            cursor: pointer;
            padding: 0 10px;
            display: flex;
            align-items: center;
        }
        #reportError {
            display: none;
            margin-bottom: 20px;
        }
        /* Стили для меню */
        .list {
            list-style: none;
            padding: 0;
        }
        .element {
            display: flex;
            align-items: center;
            padding: 10px;
            cursor: pointer;
            border-radius: 5px;
            transition: background-color 0.2s;
        }
        .element:hover {
            background-color: #f0f0f0;
        }
        .element svg {
            margin-right: 10px;
        }
        .label {
            margin: 0;
            font-size: 1rem;
        }
        .input-group .btn-sm {
            padding: 0.25rem 0.5rem; /* Уменьшаем внутренние отступы для компактности */
            font-size: 0.875rem; /* Уменьшаем размер шрифта */
            line-height: 1.5; /* Выравниваем высоту с input */
            height: 38px; /* Соответствует высоте form-control */
            margin-left: 2px; /* Небольшой отступ между кнопками */
        }
        .input-group .password-toggle {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            line-height: 1.5;
            height: 38px;
            margin-left: 2px;
        }
        .input-group .form-control {
            flex: 1 1 auto; /* Позволяет input растягиваться, но не перекрывать кнопки */
        }
    </style>
</head>
<body>
    <!-- Навигационная панель -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light fixed-top shadow-sm">
        <div class="container-fluid">
            <button class="btn btn-outline-secondary me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasSidebar" aria-controls="offcanvasSidebar">
                <span class="navbar-toggler-icon"></span>
            </button>
            <a class="navbar-brand" href="../index.php">Главная</a>
        </div>
    </nav>

    <!-- Боковая панель -->
    <div class="offcanvas offcanvas-start" tabindex="-1" id="offcanvasSidebar" aria-labelledby="offcanvasSidebarLabel">
        <div class="offcanvas-header">
            <h5 id="offcanvasSidebarLabel">Меню</h5>
            <button type="button" class="btn-close text-reset" aria-label="Закрыть"></button>
        </div>
        <div class="offcanvas-body">
            <div class="text-center mb-4">
                <a href="./profile.php?user_id=<?php echo htmlspecialchars($user_id); ?>">
                    <img src="../Uploads/<?php echo htmlspecialchars($userAvatar); ?>" alt="Avatar" class="rounded-circle" width="100" height="100" style="display: block; margin: 0 auto;">
                </a>
                <p class="mt-2"><?php echo htmlspecialchars($username); ?></p>
            </div>
            <ul class="list">
                <li class="element" onclick="window.location.href='../../index.php'">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#7e8590" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-home">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                    </svg>
                    <p class="label">Главная</p>
                </li>
                <li class="element" onclick="window.location.href='admin_paneluser.php'">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#7e8590" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-users">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                    <p class="label">Панель пользователей</p>
                </li>
                <li class="element" data-bs-toggle="modal" data-bs-target="#reportFormatModal">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#7e8590" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-text">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="12" y1="18" x2="12" y2="12"></line>
                        <line x1="9" y1="15" x2="15" y2="15"></line>
                    </svg>
                    <p class="label">Сформировать отчет</p>
                </li>
                <li class="element" onclick="window.location.href='../process/logout.php'">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#7e8590" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-log-out">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                    <p class="label">Выход</p>
                </li>
            </ul>
        </div>
    </div>

    <div class="modal fade" id="reportFormatModal" tabindex="-1" aria-labelledby="reportFormatModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 id="reportFormatModalLabel" class="modal-title">Выберите формат отчета</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="radio" name="reportFormat" id="formatWord" value="word" checked>
                        <label class="form-check-label" for="formatWord">Word</label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="radio" name="reportFormat" id="formatPDF" value="pdf">
                        <label class="form-check-label" for="formatPDF">PDF</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="button" class="btn btn-primary" id="confirmReportFormat">Сформировать</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="createProjectModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content form">
                <div class="modal-header">
                    <h5 class="modal-title title">Создание проекта</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post" action="process/create_project.php" id="createProjectForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label required">Название проекта</label>
                            <input type="text" name="name" class="form-control input" maxlength="255" required placeholder="Например, Строительство жилого комплекса">
                            <small class="form-text text-muted">Осталось символов: <span id="nameChars">255</span></small>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label required">Планируемая дата начала</label>
                                <input type="date" name="planned_start_date" class="form-control input" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Планируемая дата завершения</label>
                                <input type="date" name="planned_end_date" class="form-control input">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Планируемый бюджет</label>
                            <input type="number" name="planned_budget" class="form-control input" step="0.01" min="0" placeholder="Например, 100000.00 BYN">
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">Статус</label>
                            <select name="status" class="form-select input" required>
                                <option value="planning">Планирование</option>
                                <option value="in_progress">В процессе</option>
                                <option value="on_hold">На паузе</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Короткое название</label>
                            <input type="text" name="short_name" class="form-control input" maxlength="50" placeholder="Например, ЖК Солнечный">
                            <small class="form-text text-muted">Осталось символов: <span id="shortNameChars">50</span></small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Описание</label>
                            <textarea name="description" class="form-control input" rows="2" maxlength="1000" placeholder="Например, Проект по строительству жилого комплекса на 200 квартир"></textarea>
                            <small class="form-text text-muted">Осталось символов: <span id="descriptionChars">1000</span></small>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Фактическая дата начала</label>
                                <input type="date" name="actual_start_date" class="form-control input">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Фактическая дата завершения</label>
                                <input type="date" name="actual_end_date" class="form-control input">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Фактический бюджет</label>
                            <input type="number" name="actual_budget" class="form-control input" step="0.01" min="0" placeholder="Например, 95000.00 BYN">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Уровень цифровизации (план)</label>
                                <input type="number" name="planned_digitalization_level" class="form-control input" min="0" max="100" placeholder="Например, 50">
                                <small class="form-text text-muted">0–30: низкий (Excel, PDF, ручное управление); 31–70: средний (BIM LOD 2-4, облачные системы); 71–100: высокий (BIM LOD 5+, IoT, AI).</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Уровень цифровизации (факт)</label>
                                <input type="number" name="actual_digitalization_level" class="form-control input" min="0" max="100" placeholder="Например, 45">
                                <small class="form-text text-muted">0–30: низкий (Excel, PDF, ручное управление); 31–70: средний (BIM LOD 2-4, облачные системы); 71–100: высокий (BIM LOD 5+, IoT, AI).</small>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Трудозатраты (план)</label>
                            <input type="number" name="planned_labor_costs" class="form-control input" min="0" placeholder="Например, 1000 часов">
                            <small class="form-text text-muted">В часах. Суммарное время, необходимое для выполнения задач (например, работа команды).</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Трудозатраты (факт)</label>
                            <input type="number" name="actual_labor_costs" class="form-control input" min="0" placeholder="Например, 950 часов">
                            <small class="form-text text-muted">В часах. Фактическое время, затраченное на задачи.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Этап жизненного цикла</label>
                            <select name="lifecycle_stage" class="form-select input">
                                <option value="">Не выбрано</option>
                                <option value="initiation">Инициация</option>
                                <option value="planning">Планирование</option>
                                <option value="execution">Исполнение</option>
                                <option value="monitoring">Мониторинг</option>
                                <option value="closure">Завершение</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">Масштаб</label>
                            <select name="scale" class="form-select input" required>
                                <option value="small">Малый</option>
                                <option value="medium">Средний</option>
                                <option value="large">Крупный</option>
                                <option value="megaproject">Мегапроект</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Ресурсы</label>
                            <textarea name="expected_resources" class="form-control input" maxlength="500" placeholder="Например, 10 инженеров, 2 крана, BIM-софт"></textarea>
                            <small class="form-text text-muted">Осталось символов: <span id="resourcesChars">500</span></small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Файл устава</label>
                            <div class="file-upload-wrapper">
                                <div class="file-upload-icon">
                                    <div class="file-upload-front">
                                        <div class="file-upload-tab"></div>
                                        <div class="file-upload-body"></div>
                                    </div>
                                    <div class="file-upload-back file-upload-body"></div>
                                </div>
                                <label class="file-upload-button">
                                    <input type="file" name="charter_file" class="file-input" accept=".pdf,.doc,.docx" />
                                    Выберите файл
                                </label>
                            </div>
                            <small class="form-text text-muted">Поддерживаемые форматы: PDF, DOC, DOCX</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">Код доступа</label>
                            <div class="input-group">
                                <input type="text" name="access_code" id="accessCodeInput" class="form-control input" maxlength="20" required placeholder="Например, ABC123XYZ">
                                <button type="button" class="btn btn-outline-secondary submit" onclick="generateAccessCode()">Сгенерировать</button>
                            </div>
                            <small class="form-text text-muted">Осталось символов: <span id="accessCodeChars">20</span></small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary submit" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn btn-primary submit">Создать проект</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="userManagementModal" tabindex="-1" aria-labelledby="userManagementModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content form">
                <div class="modal-header">
                    <h5 class="modal-title title" id="userManagementModalLabel">Создание пользователя</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post" action="../process/create_user.php" id="createUserForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label required">Имя пользователя</label>
                            <input type="text" name="username" class="form-control input" maxlength="50" required placeholder="Например, ivanov">
                            <small class="form-text text-muted">Осталось символов: <span id="usernameChars">50</span></small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">Пароль</label>
                            <div class="input-group">
                                <input type="password" name="password" id="passwordInput" class="form-control input" maxlength="255" required placeholder="Введите пароль">
                                <button type="button" class="btn btn-outline-secondary btn-sm submit" onclick="generatePassword()">Сгенерировать</button>
                                <span class="btn btn-outline-secondary btn-sm submit password-toggle" onclick="togglePasswordVisibility()">
                                    <i class="bi bi-eye" id="passwordToggleIcon"></i>
                                </span>
                                <button type="button" class="btn btn-outline-secondary btn-sm submit" onclick="copyCredentials()">Копировать</button>
                            </div>
                            <small class="form-text text-muted">Осталось символов: <span id="passwordChars">255</span></small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">Электронная почта</label>
                            <input type="email" name="email" class="form-control input" maxlength="100" required placeholder="Например, ivanov@example.com">
                            <small class="form-text text-muted">Осталось символов: <span id="emailChars">100</span></small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">ФИО</label>
                            <input type="text" name="full_name" class="form-control input" maxlength="100" required placeholder="Например, Иванов Иван Иванович">
                            <small class="form-text text-muted">Осталось символов: <span id="fullNameChars">100</span></small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">Роль</label>
                            <select name="role_id" class="form-select input" required>
                                <option value="1">Админ</option>
                                <option value="2">Менеджер</option>
                                <option value="3">Сотрудник</option>
                                <option value="4">Клиент</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Должность</label>
                            <input type="text" name="position" class="form-control input" maxlength="100" placeholder="Например, Инженер">
                            <small class="form-text text-muted">Осталось символов: <span id="positionChars">100</span></small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Подразделение</label>
                            <input type="text" name="department" class="form-control input" maxlength="100" placeholder="Например, Отдел проектирования">
                            <small class="form-text text-muted">Осталось символов: <span id="departmentChars">100</span></small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary submit" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn btn-primary submit">Создать пользователя</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="content-container container-fluid mt-5 pt-4">
        <div class="row">
            <div class="col-md-3">
                <div class="card shadow-sm mb-4">
                    <div class="card-header">Быстрые действия</div>
                    <div class="card-body">
                        <button class="btn btn-success w-100 mb-2" data-bs-toggle="modal" data-bs-target="#createProjectModal">Новый проект</button>
                        <button class="btn btn-warning w-100 mb-2" data-bs-toggle="modal" data-bs-target="#userManagementModal">Добавить пользователя</button>
                    </div>
                </div>
                <div class="card shadow-sm">
                    <div class="card-header">Статистика системы</div>
                    <div class="card-body">
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Проектов всего <span class="badge bg-primary" id="projectCount"><?= $project_count ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Пользователей всего <span class="badge bg-success" id="userCount"><?= $user_count ?></span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-md-9">
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="projectSearch" class="form-label">Поиск по названию</label>
                                <input type="text" class="form-control" id="projectSearch" placeholder="Введите название">
                            </div>
                            <div class="col-md-2">
                                <label for="budgetMin" class="form-label">Мин. бюджет</label>
                                <input type="number" class="form-control" id="budgetMin" placeholder="BYN">
                            </div>
                            <div class="col-md-2">
                                <label for="budgetMax" class="form-label">Макс. бюджет</label>
                                <input type="number" class="form-control" id="budgetMax" placeholder="BYN">
                            </div>
                            <div class="col-md-3">
                                <label for="statusFilter" class="form-label">Статус</label>
                                <select class="form-select" id="statusFilter">
                                    <option value="">Все</option>
                                    <option value="planning">Планирование</option>
                                    <option value="active">Активные</option>
                                    <option value="in_progress">В процессе</option>
                                    <option value="completed">Завершённые</option>
                                    <option value="on_hold">Приостановлены</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="startDate" class="form-label">Дата начала</label>
                                <input type="date" class="form-control" id="startDate">
                            </div>
                            <div class="col-md-2">
                                <label for="endDate" class="form-label">Дата окончания</label>
                                <input type="date" class="form-control" id="endDate">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label"> </label>
                                <button class="btn btn-primary w-100" id="resetFilters">Сброс</button>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label"> </label>
                                <button class="btn btn-info w-100" id="generateReport" data-bs-toggle="modal" data-bs-target="#reportFormatModal">Сформировать отчет</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="reportError" class="alert alert-danger"></div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header">Все проекты</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="projectsTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th class="sortable" data-sort="name">Название</th>
                                        <th>Статус</th>
                                        <th>Участники</th>
                                        <th class="sortable" data-sort="planned_budget">Бюджет</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($projects)): ?>
                                        <tr><td colspan="6">Нет проектов в базе данных</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($projects as $project): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($project['project_id']) ?></td>
                                                <td><?= htmlspecialchars($project['name']) ?></td>
                                                <td><span class="badge bg-<?= getStatusBadge($project['status']) ?>"><?= htmlspecialchars(getRussianStatus($project['status'])) ?></span></td>
                                                <td><?= $project['participants'] ?></td>
                                                <td><?= number_format($project['planned_budget'], 2) ?> BYN</td>
                                                <td>
                                                    <a href="project_details.php?id=<?= $project['project_id'] ?>" class="btn btn-sm btn-info">Подробно</a>
                                                    <a href="../pages/edit_project.php?id=<?= $project['project_id'] ?>" class="btn btn-sm btn-warning">Изменить</a>
                                                    <a href="admingenerate_report.php?project_id=<?= $project['project_id'] ?>" class="btn btn-sm btn-primary">Отчет</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4" id="unfinishedProjectsCard" style="display: none;">
                    <div class="card-header">Незавершенные проекты на дату окончания</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="unfinishedProjectsTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th class="sortable" data-sort="name">Название</th>
                                        <th>Статус</th>
                                        <th>Участники</th>
                                        <th class="sortable" data-sort="planned_budget">Бюджет</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card shadow-sm">
                            <div class="card-header">Распределение проектов</div>
                            <div class="card-body">
                                <canvas id="projectsChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card shadow-sm">
                            <div class="card-header">Бюджет проектов</div>
                            <div class="card-body">
                                <canvas id="budgetChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
    <script>
        // Данные для начальной инициализации диаграмм
        const initialProjects = <?= json_encode($projects) ?>;

        // Функция для получения русского названия статуса
        function getRussianStatus(status) {
            switch (status) {
                case 'planning': return 'Планирование';
                case 'active': return 'Активные';
                case 'in_progress': return 'В процессе';
                case 'completed': return 'Завершённые';
                case 'on_hold': return 'Приостановлены';
                default: return 'Неизвестный';
            }
        }

        // Функция для сортировки проектов
        function sortProjects(projects, key, order) {
            return projects.sort((a, b) => {
                let valA = a[key] || (key === 'planned_budget' ? 0 : '');
                let valB = b[key] || (key === 'planned_budget' ? 0 : '');
                if (key === 'planned_budget') {
                    valA = parseFloat(valA);
                    valB = parseFloat(valB);
                } else {
                    valA = valA.toLowerCase();
                    valB = valB.toLowerCase();
                }
                if (order === 'asc') {
                    return valA > valB ? 1 : -1;
                } else {
                    return valA < valB ? 1 : -1;
                }
            });
        }

        let currentSortKey = null;
        let currentSortOrder = 'asc';

        function updateProjects() {
            const filters = {
                name: $('#projectSearch').val(),
                budget_min: $('#budgetMin').val(),
                budget_max: $('#budgetMax').val(),
                status: $('#statusFilter').val(),
                start_date: $('#startDate').val(),
                end_date: $('#endDate').val()
            };

            console.log('Отправка AJAX-запроса с фильтрами:', filters);

            $.ajax({
                url: 'search_projects.php',
                method: 'GET',
                data: filters,
                dataType: 'json',
                success: function(data) {
                    console.log('Полученные данные:', data);
                    try {
                        let projects = data;
                        console.log('Обработанные проекты:', projects);
                        const endDate = filters.end_date ? new Date(filters.end_date) : null;
                        const finishedProjects = [];
                        const unfinishedProjects = [];

                        projects.forEach(project => {
                            const projectEndDate = new Date(project.planned_end_date);
                            if (endDate && project.status !== 'completed' && projectEndDate > endDate) {
                                unfinishedProjects.push(project);
                            } else {
                                finishedProjects.push(project);
                            }
                        });

                        // Применяем сортировку, если она задана
                        if (currentSortKey) {
                            sortProjects(finishedProjects, currentSortKey, currentSortOrder);
                            sortProjects(unfinishedProjects, currentSortKey, currentSortOrder);
                        }

                        // Обновление основной таблицы
                        $('#projectsTable tbody').empty();
                        if (finishedProjects.length === 0) {
                            $('#projectsTable tbody').append('<tr><td colspan="6">Нет проектов по заданным фильтрам</td></tr>');
                        } else {
                            finishedProjects.forEach(project => {
                                const row = `<tr>
                                    <td>${project.project_id}</td>
                                    <td>${project.name}</td>
                                    <td><span class="badge bg-${getStatusBadge(project.status)}">${getRussianStatus(project.status)}</span></td>
                                    <td>${project.participants}</td>
                                    <td>${Number(project.planned_budget).toLocaleString('by-BY')} BYN</td>
                                    <td>
                                        <a href="project_details.php?id=${project.project_id}" class="btn btn-sm btn-info">Подробно</a>
                                        <a href="../pages/edit_project.php?id=${project.project_id}" class="btn btn-sm btn-warning">Изменить</a>
                                        <a href="admingenerate_report.php?project_id=${project.project_id}" class="btn btn-sm btn-primary">Отчет</a>
                                    </td>
                                </tr>`;
                                $('#projectsTable tbody').append(row);
                            });
                        }

                        // Обновление таблицы незавершенных проектов
                        $('#unfinishedProjectsTable tbody').empty();
                        if (unfinishedProjects.length > 0) {
                            $('#unfinishedProjectsCard').show();
                            unfinishedProjects.forEach(project => {
                                const row = `<tr>
                                    <td>${project.project_id}</td>
                                    <td>${project.name}</td>
                                    <td><span class="badge bg-${getStatusBadge(project.status)}">${getRussianStatus(project.status)}</span></td>
                                    <td>${project.participants}</td>
                                    <td>${Number(project.planned_budget).toLocaleString('by-BY')} BYN</td>
                                    <td>
                                        <a href="project_details.php?id=${project.project_id}" class="btn btn-sm btn-info">Подробно</a>
                                        <a href="../pages/edit_project.php?id=${project.project_id}" class="btn btn-sm btn-warning">Изменить</a>
                                        <a href="admingenerate_report.php?project_id=${project.project_id}" class="btn btn-sm btn-primary">Отчет</a>
                                    </td>
                                </tr>`;
                                $('#unfinishedProjectsTable tbody').append(row);
                            });
                        } else {
                            $('#unfinishedProjectsCard').hide();
                        }

                        // Обновление диаграмм
                        updateCharts(projects);
                    } catch (e) {
                        console.error('Ошибка обработки данных:', e, data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Ошибка AJAX:', status, error, xhr.responseText);
                }
            });
        }

        function getStatusBadge(status) {
            switch (status) {
                case 'planning': return 'warning';
                case 'active': return 'success';
                case 'completed': return 'secondary';
                case 'on_hold': return 'danger';
                case 'in_progress': return 'primary';
                default: return 'info';
            }
        }

        function updateCharts(projects) {
            const statusCounts = projects.reduce((acc, project) => {
                acc[project.status] = (acc[project.status] || 0) + 1;
                return acc;
            }, {});
            const statusLabels = Object.keys(statusCounts).map(status => getRussianStatus(status));
            const statusData = Object.values(statusCounts);

            const projectNames = projects.map(p => p.name);
            const budgetData = projects.map(p => p.planned_budget);

            projectsChart.data.labels = statusLabels;
            projectsChart.data.datasets[0].data = statusData;
            projectsChart.update();

            budgetChart.data.labels = projectNames;
            budgetChart.data.datasets[0].data = budgetData;
            budgetChart.update();
        }

        function generateAccessCode() {
            const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            let result = '';
            for (let i = 0; i < 10; i++) {
                result += characters.charAt(Math.floor(Math.random() * characters.length));
            }
            document.getElementById('accessCodeInput').value = result;
            document.getElementById('accessCodeChars').textContent = 20 - result.length;
        }

        function generatePassword() {
            const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()';
            let result = '';
            for (let i = 0; i < 12; i++) {
                result += characters.charAt(Math.floor(Math.random() * characters.length));
            }
            document.getElementById('passwordInput').value = result;
            document.getElementById('passwordChars').textContent = 255 - result.length;
        }

        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('passwordInput');
            const toggleIcon = document.getElementById('passwordToggleIcon');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('bi-eye');
                toggleIcon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('bi-eye-slash');
                toggleIcon.classList.add('bi-eye');
            }
        }

        function copyCredentials() {
            const form = document.getElementById('createUserForm');
            const username = form.querySelector('input[name="username"]').value.trim();
            const password = form.querySelector('input[name="password"]').value.trim();
            const email = form.querySelector('input[name="email"]').value.trim();
            const fullName = form.querySelector('input[name="full_name"]').value.trim();
            const role = form.querySelector('select[name="role_id"]').value;
            const roleText = form.querySelector(`select[name="role_id"] option[value="${role}"]`).text;

            if (!username || !password) {
                alert('Заполните имя пользователя и пароль перед копированием');
                return;
            }
            const text = `Username: ${username}\nPassword: ${password}\nEmail: ${email}\nFull Name: ${fullName}\nRole: ${roleText}`;

            // Проверяем поддержку navigator.clipboard
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(() => {
                    alert('Учетные данные скопированы в буфер обмена!');
                }).catch(err => {
                    console.error('Ошибка копирования через navigator.clipboard:', err);
                    fallbackCopyTextToClipboard(text);
                });
            } else {
                fallbackCopyTextToClipboard(text);
            }
        }

        function fallbackCopyTextToClipboard(text) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed'; // Убираем из видимости
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();

            try {
                const successful = document.execCommand('copy');
                const msg = successful ? 'Учетные данные скопированы в буфер обмена!' : 'Не удалось скопировать учетные данные';
                alert(msg);
            } catch (err) {
                console.error('Ошибка при копировании:', err);
                alert('Не удалось скопировать учетные данные');
            }

            document.body.removeChild(textArea);
        }

        function setupCharCounter(inputSelector, spanId, maxLength) {
            const input = document.querySelector(inputSelector);
            if (!input) return;
            const span = document.getElementById(spanId);
            span.textContent = maxLength;
            input.addEventListener('input', () => {
                span.textContent = maxLength - input.value.length;
            });
        }

        function updateStatistics() {
            $.ajax({
                url: 'process/get_statistics.php',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#userCount').text(response.user_count);
                        console.log('Статистика обновлена: user_count =', response.user_count);
                    } else {
                        console.error('Ошибка обновления статистики:', response.error);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Ошибка AJAX при обновлении статистики:', status, error, xhr.responseText);
                }
            });
        }

        let projectsChart, budgetChart;

        $(document).ready(function() {
            if (localStorage.getItem('darkMode') === 'true') {
                $("body").addClass("dark-mode");
                $("#themeSwitchModal").prop("checked", true);
            }
            $("#themeSwitchModal").on("change", function() {
                if($(this).is(":checked")) {
                    $("body").addClass("dark-mode");
                    localStorage.setItem('darkMode', 'true');
                } else {
                    $("body").removeClass("dark-mode");
                    localStorage.setItem('darkMode', 'false');
                }
            });

            $('#projectSearch, #budgetMin, #budgetMax, #statusFilter, #startDate, #endDate').on('change input', updateProjects);
            $('#resetFilters').on('click', function() {
                $('#projectSearch, #budgetMin, #budgetMax, #startDate, #endDate').val('');
                $('#statusFilter').val('');
                currentSortKey = null;
                currentSortOrder = 'asc';
                $('.sortable').removeClass('asc desc');
                updateProjects();
            });

            ['generateReport', 'generateReportSidebar'].forEach(id => {
                $(`#${id}`).on("click", function() {
                    $('#reportFormatModal').modal('show');
                });
            });

            $('#confirmReportFormat').on('click', function() {
                const format = $('input[name="reportFormat"]:checked').val();
                const filters = {
                    name: $('#projectSearch').val(),
                    budget_min: $('#budgetMin').val(),
                    budget_max: $('#budgetMax').val(),
                    status: $('#statusFilter').val(),
                    start_date: $('#startDate').val(),
                    end_date: $('#endDate').val(),
                    format: format
                };

                // Проверка наличия данных для отчета
                $.ajax({
                    url: 'search_projects.php',
                    method: 'GET',
                    data: filters,
                    dataType: 'json',
                    success: function(data) {
                        if (data.length === 0) {
                            $('#reportError').text('Нет данных для формирования отчета').show();
                            $('#reportFormatModal').modal('hide');
                            setTimeout(() => {
                                $('#reportError').hide();
                            }, 5000);
                        } else {
                            const queryString = $.param(filters);
                            window.location.href = 'admingenerate_report.php?' + queryString;
                            $('#reportFormatModal').modal('hide');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Ошибка AJAX при проверке данных отчета:', status, error, xhr.responseText);
                        $('#reportError').text('Ошибка при проверке данных отчета').show();
                        $('#reportFormatModal').modal('hide');
                        setTimeout(() => {
                            $('#reportError').hide();
                        }, 5000);
                    }
                });
            });

            $('.sortable').on('click', function() {
                const key = $(this).data('sort');
                const table = $(this).closest('table');
                const isUnfinished = table.attr('id') === 'unfinishedProjectsTable';
                const newOrder = (currentSortKey === key && currentSortOrder === 'asc') ? 'desc' : 'asc';

                $('.sortable').removeClass('asc desc');
                $(this).addClass(newOrder);

                currentSortKey = key;
                currentSortOrder = newOrder;

                updateProjects();
            });

            // Инициализация создания проекта
            setupCharCounter('[name="name"]', 'nameChars', 255);
            setupCharCounter('[name="short_name"]', 'shortNameChars', 50);
            setupCharCounter('[name="description"]', 'descriptionChars', 1000);
            setupCharCounter('[name="expected_resources"]', 'resourcesChars', 500);
            setupCharCounter('[name="access_code"]', 'accessCodeChars', 20);

            const plannedStartDate = document.querySelector('input[name="planned_start_date"]');
            const plannedEndDate = document.querySelector('input[name="planned_end_date"]');
            const actualStartDate = document.querySelector('input[name="actual_start_date"]');
            const actualEndDate = document.querySelector('input[name="actual_end_date"]');

            plannedEndDate.addEventListener('change', function() {
                if (plannedStartDate.value && plannedEndDate.value < plannedStartDate.value) {
                    alert('Планируемая дата завершения не может быть раньше даты начала.');
                    plannedEndDate.value = '';
                }
            });

            actualEndDate.addEventListener('change', function() {
                if (actualStartDate.value && actualEndDate.value < actualStartDate.value) {
                    alert('Фактическая дата завершения не может быть раньше даты начала.');
                    actualEndDate.value = '';
                }
            });

            $('#createProjectForm').on('submit', function(e) {
                e.preventDefault();

                // Валидация дат
                const plannedStart = document.querySelector('input[name="planned_start_date"]').value;
                const plannedEnd = document.querySelector('input[name="planned_end_date"]').value;
                const actualStart = document.querySelector('input[name="actual_start_date"]').value;
                const actualEnd = document.querySelector('input[name="actual_end_date"]').value;

                if (plannedStart && plannedEnd && plannedEnd < plannedStart) {
                    alert('Планируемая дата завершения не может быть раньше даты начала.');
                    return;
                }
                if (actualStart && actualEnd && actualEnd < actualStart) {
                    alert('Фактическая дата завершения не может быть раньше даты начала.');
                    return;
                }

                // Валидация масштаба
                const scale = document.querySelector('select[name="scale"]').value;
                const validScales = ['small', 'medium', 'large', 'megaproject'];
                if (!validScales.includes(scale)) {
                    alert('Недопустимый масштаб проекта. Выберите: Малый, Средний, Крупный или Мегапроект.');
                    return;
                }

                // Валидация статуса
                const status = document.querySelector('select[name="status"]').value;
                const validStatuses = ['planning', 'in_progress', 'on_hold'];
                if (!validStatuses.includes(status)) {
                    alert('Недопустимый статус проекта. Выберите: Планирование, В процессе или На паузе.');
                    return;
                }

                // Логирование данных формы для отладки
                const formData = new FormData(this);
                console.log('Отправляемые данные формы:', Object.fromEntries(formData));

                $.ajax({
                    url: $(this).attr('action'),
                    method: 'POST',
                    data: formData,
                    contentType: false,
                    processData: false,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#createProjectModal').modal('hide');
                            updateProjects();
                            alert('Проект успешно создан!');
                        } else {
                            alert(response.error || 'Ошибка при создании проекта');
                        }
                    },
                    error: function(xhr) {
                        console.error('Ошибка AJAX:', xhr.status, xhr.responseText);
                        const response = xhr.responseJSON || {};
                        let errorMessage = 'Ошибка при создании проекта';
                        if (xhr.status === 400 && response.errors) {
                            errorMessage = response.errors.join('\n');
                        } else if (response.error) {
                            errorMessage = response.error;
                        } else if (xhr.status === 500) {
                            errorMessage = 'Внутренняя ошибка сервера. Пожалуйста, попробуйте позже.';
                        }
                        alert(errorMessage);
                    }
                });
            });

            // Инициализация создания пользователя
            setupCharCounter('[name="username"]', 'usernameChars', 50);
            setupCharCounter('[name="password"]', 'passwordChars', 255);
            setupCharCounter('[name="email"]', 'emailChars', 100);
            setupCharCounter('[name="full_name"]', 'fullNameChars', 100);
            setupCharCounter('[name="position"]', 'positionChars', 100);
            setupCharCounter('[name="department"]', 'departmentChars', 100);

            $('#createUserForm').on('submit', function(e) {
                e.preventDefault();

                // Валидация формы
                const username = document.querySelector('input[name="username"]').value.trim();
                const password = document.querySelector('input[name="password"]').value.trim();
                const email = document.querySelector('input[name="email"]').value.trim();
                const full_name = document.querySelector('input[name="full_name"]').value.trim();
                const role_id = document.querySelector('select[name="role_id"]').value;

                if (!username) {
                    alert('Имя пользователя обязательно');
                    return;
                }
                if (!password) {
                    alert('Пароль обязателен');
                    return;
                }
                if (!email) {
                    alert('Электронная почта обязательна');
                    return;
                }
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    alert('Недопустимый формат электронной почты');
                    return;
                }
                if (!full_name) {
                    alert('ФИО обязательно');
                    return;
                }
                if (!['1', '2', '3', '4'].includes(role_id)) {
                    alert('Выберите действительную роль: Админ, Менеджер, Сотрудник или Клиент');
                    return;
                }

                // Логирование данных формы и URL для отладки
                const formData = new FormData(this);
                const requestUrl = $(this).attr('action');
                console.log('Отправляемые данные формы:', Object.fromEntries(formData));
                console.log('URL запроса:', requestUrl);

                $.ajax({
                    url: requestUrl,
                    method: 'POST',
                    data: formData,
                    contentType: false,
                    processData: false,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#userManagementModal').modal('hide');
                            updateStatistics();
                            alert('Пользователь успешно создан!');
                        } else {
                            alert(response.error || 'Ошибка при создании пользователя');
                        }
                    },
                    error: function(xhr) {
                        console.error('Ошибка AJAX:', xhr.status, xhr.responseText);
                        const response = xhr.responseJSON || {};
                        let errorMessage = 'Ошибка при создании пользователя';
                        if (xhr.status === 400 && response.errors) {
                            errorMessage = response.errors.join('\n');
                        } else if (response.error) {
                            errorMessage = response.error;
                        } else if (xhr.status === 404) {
                            errorMessage = 'Файл create_user.php не найден. Проверьте путь: ' + requestUrl;
                        } else if (xhr.status === 500) {
                            errorMessage = 'Внутренняя ошибка сервера. Пожалуйста, попробуйте позже.';
                        }
                        alert(errorMessage);
                    }
                });
            });

            // Инициализация диаграмм с начальными данными
            projectsChart = new Chart(document.getElementById('projectsChart'), {
                type: 'bar',
                data: {
                    labels: Object.keys(initialProjects.reduce((acc, p) => {
                        acc[p.status] = (acc[p.status] || 0) + 1;
                        return acc;
                    }, {})).map(status => getRussianStatus(status)),
                    datasets: [{
                        label: 'Количество проектов',
                        data: Object.values(initialProjects.reduce((acc, p) => {
                            acc[p.status] = (acc[p.status] || 0) + 1;
                            return acc;
                        }, {})),
                        backgroundColor: '#4e73df'
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { stepSize: 1 }
                        }
                    }
                }
            });

            budgetChart = new Chart(document.getElementById('budgetChart'), {
                type: 'pie',
                data: {
                    labels: initialProjects.map(p => p.name),
                    datasets: [{
                        label: 'Бюджет проектов',
                        data: initialProjects.map(p => p.planned_budget),
                        backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40']
                    }]
                }
            });

            updateProjects();
        });
    </script>
</body>
</html>