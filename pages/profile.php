<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['user_id'])) {
    die("Пользователь не авторизован.");
}

$user_id = $_SESSION['user_id'];
$role_id = $_SESSION['role_id'];
$search = $_GET['search'] ?? '';
$status = isset($_GET['status']) ? $_GET['status'] : 'all';

// Маппинг статусов для перевода
$statusTranslations = [
    'planning' => 'Планирование',
    'in_progress' => 'В процессе',
    'completed' => 'Завершено',
    'on_hold' => 'На паузе'
];

// Получаем данные пользователя
$sql = "SELECT username, email, full_name, position, department, avatar, created_at, role_id FROM users WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $username = $user['username'];
    $email = $user['email'];
    $fullname = $user['full_name'];
    $position = $user['position'];
    $department = $user['department'];
    $userAvatar = (!empty($user['avatar']) && file_exists(__DIR__ . '/../Uploads/' . $user['avatar'])) 
        ? $user['avatar'] 
        : 'default_avatar.jpg';
    $accountCreated = $user['created_at'] ? date('d.m.Y H:i', strtotime($user['created_at'])) : 'Не указана';
    $role_id = $user['role_id'];
} else {
    die("Пользователь не найден.");
}

$roleTranslations = [
    1 => 'Администратор',
    2 => 'Менеджер',
    3 => 'Сотрудник',
    4 => 'Пользователь'
];
$role = $roleTranslations[$role_id] ?? 'Не указана';

// Функция для получения проектов
function fetchProjects($conn, $sql, $params, $types, $statusTranslations) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("profile.php: Ошибка подготовки запроса: " . $conn->error);
        return ['success' => false, 'message' => 'Ошибка подготовки запроса: ' . $conn->error];
    }
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $projects = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
    $stmt->close();

    // Переводим статусы
    foreach ($projects as &$project) {
        $project['status_display'] = $statusTranslations[$project['status']] ?? $project['status'];
    }
    return ['success' => true, 'projects' => $projects];
}

// Получаем проекты
$activeProjects = [];
$completedProjects = [];

if ($role_id == 1 || $role_id == 2) {
    // Админ или менеджер: все проекты
    $sqlActive = "SELECT project_id, name, status, planned_budget, actual_budget FROM projects WHERE status != 'completed'";
    $params = [];
    $types = "";
    if (!empty($search)) {
        $sqlActive .= " AND (name LIKE ?)";
        $params = ["%$search%"];
        $types = "s";
    }
    if ($status !== 'all') {
        $sqlActive .= " AND status = ?";
        $params[] = $status;
        $types .= "s";
    }
    $sqlActive .= " ORDER BY name ASC";
    $activeResult = fetchProjects($conn, $sqlActive, $params, $types, $statusTranslations);
    if ($activeResult['success']) {
        $activeProjects = $activeResult['projects'];
    }

    $sqlCompleted = "SELECT project_id, name, status, planned_budget, actual_budget FROM projects WHERE status = 'completed'";
    $params = [];
    $types = "";
    if (!empty($search)) {
        $sqlCompleted .= " AND (name LIKE ?)";
        $params = ["%$search%"];
        $types = "s";
    }
    $sqlCompleted .= " ORDER BY name ASC";
    $completedResult = fetchProjects($conn, $sqlCompleted, $params, $types, $statusTranslations);
    if ($completedResult['success']) {
        $completedProjects = $completedResult['projects'];
    }
} else {
    // Сотрудник или пользователь: только проекты, где участвуют
    $sqlActive = "SELECT p.project_id, p.name, p.status, p.planned_budget, p.actual_budget 
                  FROM projects p 
                  INNER JOIN project_participants pp ON p.project_id = pp.project_id 
                  WHERE pp.user_id = ?";
    $params = [$user_id];
    $types = "i";
    if ($status !== 'all') {
        $sqlActive .= " AND p.status = ?";
        $params[] = $status;
        $types .= "s";
    } else {
        $sqlActive .= " AND p.status != 'completed'";
    }
    if (!empty($search)) {
        $sqlActive .= " AND (p.name LIKE ?)";
        $params[] = "%$search%";
        $types .= "s";
    }
    $sqlActive .= " ORDER BY p.name ASC";
    $activeResult = fetchProjects($conn, $sqlActive, $params, $types, $statusTranslations);
    if ($activeResult['success']) {
        $activeProjects = $activeResult['projects'];
    }

    $sqlCompleted = "SELECT p.project_id, p.name, p.status, p.planned_budget, p.actual_budget 
                     FROM projects p 
                     INNER JOIN project_participants pp ON p.project_id = pp.project_id 
                     WHERE pp.user_id = ? AND p.status = 'completed'";
    $params = [$user_id];
    $types = "i";
    if (!empty($search)) {
        $sqlCompleted .= " AND (p.name LIKE ?)";
        $params[] = "%$search%";
        $types .= "s";
    }
    $sqlCompleted .= " ORDER BY p.name ASC";
    $completedResult = fetchProjects($conn, $sqlCompleted, $params, $types, $statusTranslations);
    if ($completedResult['success']) {
        $completedProjects = $completedResult['projects'];
    }
}

// Обработка формы обновления данных
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['fullname']);
    $position = trim($_POST['position']);
    $department = trim($_POST['department']);
    $new_email = trim($_POST['email']);

    if (empty($new_email)) {
        $_SESSION['error_msg'] = "Email не может быть пустым.";
        header("Location: profile.php");
        exit();
    }

    if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_msg'] = "Неверный формат email.";
        header("Location: profile.php");
        exit();
    }

    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
    $stmt->bind_param("si", $new_email, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $_SESSION['error_msg'] = "Этот email уже используется другим пользователем.";
        header("Location: profile.php");
        exit();
    }
    $stmt->close();

    $stmt = $conn->prepare("UPDATE users SET full_name = ?, position = ?, department = ?, email = ? WHERE user_id = ?");
    $stmt->bind_param("ssssi", $full_name, $position, $department, $new_email, $user_id);

    if ($stmt->execute()) {
        header("Location: profile.php");
        exit();
    } else {
        $_SESSION['error_msg'] = "Ошибка при обновлении данных: " . $stmt->error;
        header("Location: profile.php");
        exit();
    }
    $stmt->close();
}

// Обработка формы удаления пользователя
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    if (deleteUser($conn, $user_id)) {
        session_destroy();
        header("Location: auth.php");
        exit();
    } else {
        $_SESSION['error_msg'] = "Ошибка при удалении аккаунта.";
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Профиль пользователя</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="/css/acaunt.css">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <style>
    .modal-content {
      border-radius: 15px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
      padding: 20px;
      background-color: #ffffff;
    }
    .modal-header {
      border-bottom: none;
      padding-bottom: 0;
    }
    .modal-title {
      font-weight: 600;
      color: #333;
    }
    .modal-body {
      padding: 20px;
    }
    .modal-body form .form-label {
      font-weight: 500;
      color: #555;
    }
    .modal-body form .form-control {
      border-radius: 8px;
      border: 1px solid #ced4da;
      padding: 10px;
      transition: border-color 0.3s ease;
    }
    .modal-body form .form-control:focus {
      border-color: #007bff;
      box-shadow: 0 0 5px rgba(0, 123, 255, 0.3);
    }
    .modal-footer {
      border-top: none;
      padding-top: 0;
    }
    .modal-footer .btn-primary {
      background-color: #007bff;
      border: none;
      border-radius: 8px;
      padding: 10px 20px;
      transition: background-color 0.3s ease;
    }
    .modal-footer .btn-primary:hover {
      background-color: #0056b3;
    }
    .modal-footer .btn-danger {
      border-radius: 8px;
      padding: 10px 20px;
    }
    .avatar-container {
      width: 100px;
      height: 100px;
      border-radius: 50%;
      overflow: hidden;
      display: flex;
      align-items: center;
      justify-content: center;
      background-color: #f0f0f0;
      margin: 0 auto 15px;
      cursor: pointer;
    }
    .avatar-container img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .hidden-file-input {
      display: none;
    }
    .card {
      border-radius: 15px;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
      padding: 20px;
    }
    .btn-1, .btn-2, .btn-danger {
      transition: background-color 0.3s ease;
    }
    .btn-1:hover {
      background-color: #0056b3 !important;
    }
    .btn-2:hover {
      background-color: #5a6268 !important;
    }
    .profile-row {
      margin-top: 80px;
    }
    .hamburger {
      cursor: pointer;
    }
    .hamburger input {
      display: none;
    }
    .hamburger svg {
      height: 3.5em;
      transition: transform 600ms cubic-bezier(0.4, 0, 0.2, 1);
    }
    .line {
      fill: none;
      stroke: #333;
      stroke-linecap: round;
      stroke-linejoin: round;
      stroke-width: 3;
      transition: stroke-dasharray 600ms cubic-bezier(0.4, 0, 0.2, 1),
                  stroke-dashoffset 600ms cubic-bezier(0.4, 0, 0.2, 1);
    }
    .line-top-bottom {
      stroke-dasharray: 12 63;
    }
    .hamburger input:checked + svg {
      transform: rotate(-45deg);
    }
    .hamburger input:checked + svg .line-top-bottom {
      stroke-dasharray: 20 300;
      stroke-dashoffset: -32.42;
    }
    #completedProjectsTable {
      display: none;
    }
    .form-switch {
      margin-left: 10px;
    }
    .table-container {
      margin-bottom: 20px;
      max-width: 100%;
    }
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
    .btn-sm {
      min-width: 100px;
      text-align: center;
    }
    .table .btn-sm {
      margin-right: 5px;
    }
  </style>
</head>
<body>
  <nav class="navbar navbar-expand-lg fixed-top shadow-sm bg-light">
    <div class="container-fluid">
      <label class="hamburger me-2">
        <input type="checkbox" id="burgerCheckbox" data-bs-toggle="offcanvas" data-bs-target="#offcanvasSidebar" aria-controls="offcanvasSidebar">
        <svg viewBox="0 0 32 32" width="35" height="35">
          <path class="line line-top-bottom" d="M27 10 13 10C10.8 10 9 8.2 9 6 9 3.5 10.8 2 13 2 15.2 2 17 3.8 17 6L17 26C17 28.2 18.8 30 21 30 23.2 30 25 28.2 25 26 25 23.8 23.2 22 21 22L7 22"></path>
          <path class="line" d="M7 16 27 16"></path>
        </svg>
      </label>
      <a class="navbar-brand" href="../index.php">Панель управления</a>
      <div class="collapse navbar-collapse">
        <form class="d-flex ms-auto">
          <input id="searchInput" class="form-control me-2" type="search" placeholder="Поиск проектов" value="<?= htmlspecialchars($search) ?>">
          <select id="statusSelect" class="form-select me-2">
            <option value="all" <?= $status == "all" ? "selected" : "" ?>>Все</option>
            <option value="planning" <?= $status == "planning" ? "selected" : "" ?>>Планирование</option>
            <option value="in_progress" <?= $status == "in_progress" ? "selected" : "" ?>>В процессе</option>
            <option value="on_hold" <?= $status == "on_hold" ? "selected" : "" ?>>На паузе</option>
          </select>
        </form>
      </div>
    </div>
  </nav>

  <div class="offcanvas offcanvas-start" tabindex="-1" id="offcanvasSidebar" aria-labelledby="offcanvasSidebarLabel">
    <div class="offcanvas-header">
      <h5 id="offcanvasSidebarLabel">Меню</h5>
      <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Закрыть"></button>
    </div>
    <div class="offcanvas-body">
      <div class="text-center mb-4">
        <img src="../Uploads/<?= htmlspecialchars($userAvatar) ?>" alt="Avatar" class="rounded-circle" width="100" height="100">
        <p class="mt-2"><?= htmlspecialchars($username) ?></p>
      </div>
      <ul class="list-unstyled">
        <li class="mb-2" onclick="window.location.href='../index.php'" style="cursor:pointer;">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" stroke="#7e8590" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-home">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
            <polyline points="9 22 9 12 15 12 15 22"></polyline>
          </svg>
          <span>Панель управления</span>
        </li>
        <?php if ($role_id == 1): ?>
          <li class="mb-2" onclick="window.location.href='../pages/admin_panel.php'" style="cursor:pointer;">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" stroke="#7e8590" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-plus">
              <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
              <circle cx="8.5" cy="7" r="4"></circle>
              <line x1="20" y1="8" x2="20" y2="14"></line>
              <line x1="23" y1="11" x2="17" y2="11"></line>
            </svg>
            <span>Админ-панель</span>
          </li>
        <?php endif; ?>
        <li class="mb-2" onclick="window.location.href='../process/logout.php'" style="cursor:pointer;">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" stroke="#7e8590" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-log-out">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
            <polyline points="16 17 21 12 16 7"></polyline>
            <line x1="21" y1="12" x2="9" y2="12"></line>
          </svg>
          <span>Выход</span>
        </li>
      </ul>
    </div>
  </div>

  <div class="container">
    <div class="row profile-row">
      <div class="col-md-6 d-flex justify-content-center">
        <div class="card">
          <div class="header text-center">
            <div class="avatar-container">
              <img src="../Uploads/<?= htmlspecialchars($userAvatar) ?>" alt="Avatar" id="avatar" data-bs-toggle="modal" data-bs-target="#modal-avatar">
            </div>
            <div class="details">
              <h5 class="mb-1"><?= htmlspecialchars($username) ?></h5>
              <p class="mb-0 text-muted"><?= htmlspecialchars($email) ?></p>
            </div>
          </div>
          <div class="description mt-3">
            <p><strong>Пароль:</strong> ********</p>
            <p><strong>ФИО:</strong> <?= htmlspecialchars($fullname) ?></p>
            <p><strong>Роль:</strong> <?= htmlspecialchars($role) ?></p>
            <p><strong>Должность:</strong> <?= htmlspecialchars($position) ?></p>
            <p><strong>Департамент:</strong> <?= htmlspecialchars($department) ?></p>
            <p><strong>Дата создания:</strong> <?= htmlspecialchars($accountCreated) ?></p>
          </div>
          <div class="btns d-flex mt-3 justify-content-center">
            <div class="btn btn-1 me-2 text-center" style="background:#007bff; color:white; border-radius:5px;" data-bs-toggle="modal" data-bs-target="#modal-password">Сменить пароль</div>
            <div class="btn btn-2 me-2 text-center" style="background:#6c757d; color:white; border-radius:5px;" data-bs-toggle="modal" data-bs-target="#modal-profile">Изменить профиль</div>
            <div class="btn btn-danger text-center" style="border-radius:5px;" data-bs-toggle="modal" data-bs-target="#modal-delete">Удалить аккаунт</div>
          </div>
        </div>
      </div>

      <div class="col-md-6 d-flex flex-column align-items-center justify-content-start">
        <h3>Проекты</h3>
        <div class="d-flex justify-content-end mb-3 align-items-center w-100">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="showCompletedSwitch">
            <label class="form-check-label" for="showCompletedSwitch">Показать завершённые</label>
          </div>
        </div>
        <div class="table-container table-responsive w-150">
          <h4>Активные проекты</h4>
          <table class="table table-striped">
            <thead>
              <tr>
                <th class="sortable" data-sort="name">Название</th>
                <th>Статус</th>
                <th class="sortable" data-sort="planned_budget">Плановый бюджет</th>
                <th>Фактический бюджет</th>
                <th>Действия</th>
              </tr>
            </thead>
            <tbody id="projectTableBody">
              <?php if (empty($activeProjects)): ?>
                <tr><td colspan="5">Нет активных проектов.</td></tr>
              <?php else: ?>
                <?php foreach ($activeProjects as $project): ?>
                  <tr id="project-<?= htmlspecialchars($project['project_id']) ?>">
                    <td><?= htmlspecialchars($project['name']) ?></td>
                    <td><?= htmlspecialchars($project['status_display']) ?></td>
                    <td><?= htmlspecialchars($project['planned_budget']) ?> BYN</td>
                    <td><?= htmlspecialchars($project['actual_budget']) ?> BYN</td>
                    <td>
                      <div class="d-flex flex-column align-items-start gap-2">
                        <a href="project_details.php?id=<?= htmlspecialchars($project['project_id']) ?>" class="btn btn-sm btn-info">Подробнее</a>
                        <?php if ($role_id == 1 || $role_id == 2): ?>
                          <button class="btn btn-sm btn-warning change-status-btn" data-bs-toggle="modal" data-bs-target="#changeStatusModal" data-project-id="<?= htmlspecialchars($project['project_id']) ?>" data-current-status="<?= htmlspecialchars($project['status']) ?>">Изменить статус</button>
                          <a href="edit_project.php?id=<?= htmlspecialchars($project['project_id']) ?>" class="btn btn-sm btn-primary">Изменить</a>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <div class="table-container table-responsive w-100" id="completedProjectsTable">
          <h4>Завершённые проекты</h4>
          <table class="table table-striped">
            <thead>
              <tr>
                <th class="sortable" data-sort="name">Название</th>
                <th>Статус</th>
                <th class="sortable" data-sort="planned_budget">Плановый бюджет</th>
                <th>Фактический бюджет</th>
                <th>Действия</th>
              </tr>
            </thead>
            <tbody id="completedProjectTableBody">
              <?php if (empty($completedProjects)): ?>
                <tr><td colspan="5">Нет завершённых проектов.</td></tr>
              <?php else: ?>
                <?php foreach ($completedProjects as $project): ?>
                  <tr id="project-<?= htmlspecialchars($project['project_id']) ?>">
                    <td><?= htmlspecialchars($project['name']) ?></td>
                    <td><?= htmlspecialchars($project['status_display']) ?></td>
                    <td><?= htmlspecialchars($project['planned_budget']) ?> BYN</td>
                    <td><?= htmlspecialchars($project['actual_budget']) ?> BYN</td>
                    <td>
                      <div class="d-flex flex-column align-items-start gap-2">
                        <a href="project_details.php?id=<?= htmlspecialchars($project['project_id']) ?>" class="btn btn-sm btn-info">Подробнее</a>
                        <?php if ($role_id == 1 || $role_id == 2): ?>
                          <button class="btn btn-sm btn-warning change-status-btn" data-bs-toggle="modal" data-bs-target="#changeStatusModal" data-project-id="<?= htmlspecialchars($project['project_id']) ?>" data-current-status="<?= htmlspecialchars($project['status']) ?>">Изменить статус</button>
                          <a href="edit_project.php?id=<?= htmlspecialchars($project['project_id']) ?>" class="btn btn-sm btn-primary">Изменить</a>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="modal-avatar" tabindex="-1" aria-labelledby="modalAvatarLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalAvatarLabel">Сменить аватарку</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
        </div>
        <div class="modal-body">
          <form action="../process/update_user.php" method="post" enctype="multipart/form-data">
            <div class="mb-3">
              <label for="avatar" class="form-label">Выберите файл:</label>
              <input type="file" class="form-control" id="avatar" name="avatar" accept="image/*" required>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
              <button type="submit" class="btn btn-primary">Загрузить</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="modal-password" tabindex="-1" aria-labelledby="modalPasswordLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalPasswordLabel">Сменить пароль</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
        </div>
        <div class="modal-body">
          <form action="../process/update_password.php" method="post" id="passwordForm">
            <div class="mb-3">
              <label for="old_password" class="form-label">Старый пароль:</label>
              <input type="password" class="form-control" id="old_password" name="old_password" required>
            </div>
            <div class="mb-3">
              <label for="new_password" class="form-label">Новый пароль:</label>
              <input type="password" class="form-control" id="new_password" name="new_password" required>
            </div>
            <div class="mb-3">
              <label for="confirm_password" class="form-label">Подтвердите новый пароль:</label>
              <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
              <button type="submit" class="btn btn-primary">Сменить</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="modal-profile" tabindex="-1" aria-labelledby="modalProfileLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalProfileLabel">Редактировать профиль</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
        </div>
        <div class="modal-body">
          <form action="../process/update_user.php" method="post" enctype="multipart/form-data" id="profileForm">
            <div class="mb-3 text-center">
              <label for="avatarInput" class="avatar-container">
                <img src="../Uploads/<?= htmlspecialchars($userAvatar) ?>" alt="Avatar" class="img-fluid" id="avatarPreview">
                <input type="file" class="hidden-file-input" id="avatarInput" name="avatar" accept="image/*">
              </label>
              <small class="form-text text-muted">Нажмите на фото, чтобы загрузить новое</small>
            </div>
            <div class="mb-3">
              <label for="fullname" class="form-label">ФИО:</label>
              <input type="text" class="form-control" id="fullname" name="fullname" value="<?= htmlspecialchars($fullname) ?>" required>
            </div>
            <div class="mb-3">
              <label for="email" class="form-label">Email:</label>
              <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
            </div>
            <div class="mb-3">
              <label for="position" class="form-label">Должность:</label>
              <input type="text" class="form-control" id="position" name="position" value="<?= htmlspecialchars($position) ?>" required>
            </div>
            <div class="mb-3">
              <label for="department" class="form-label">Департамент:</label>
              <input type="text" class="form-control" id="department" name="department" value="<?= htmlspecialchars($department) ?>" required>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
              <button type="submit" name="update_profile" class="btn btn-primary">Сохранить изменения</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="modal-delete" tabindex="-1" aria-labelledby="modalDeleteLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalDeleteLabel">Удалить аккаунт</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
        </div>
        <div class="modal-body">
          <p>Вы уверены, что хотите удалить свой аккаунт? Это действие нельзя отменить.</p>
          <form action="" method="post">
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
              <button type="submit" name="delete_account" class="btn btn-danger">Удалить</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="changeStatusModal" tabindex="-1" aria-labelledby="changeStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="changeStatusModalLabel">Изменить статус проекта</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
        </div>
        <div class="modal-body">
          <form id="changeStatusForm">
            <input type="hidden" id="statusProjectId" name="project_id">
            <div class="mb-3">
              <label for="projectStatus" class="form-label">Новый статус</label>
              <select class="form-select" id="projectStatus" name="status" required>
                <option value="planning">Планирование</option>
                <option value="in_progress">В процессе</option>
                <option value="completed">Завершено</option>
                <option value="on_hold">На паузе</option>
              </select>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
          <button type="button" class="btn btn-primary" id="saveStatusBtn">Сохранить</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.addEventListener("DOMContentLoaded", function() {
      const offcanvasElement = document.getElementById('offcanvasSidebar');
      const burgerCheckbox = document.getElementById('burgerCheckbox');
      offcanvasElement.addEventListener('hidden.bs.offcanvas', function() {
        burgerCheckbox.checked = false;
      });

      const avatarInput = document.getElementById('avatarInput');
      const avatarPreview = document.getElementById('avatarPreview');
      if (avatarInput && avatarPreview) {
        avatarInput.addEventListener('change', function(event) {
          const file = event.target.files[0];
          if (file && file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
              avatarPreview.src = e.target.result;
            };
            reader.readAsDataURL(file);
          }
        });
      }

      // Свитч для показа завершённых проектов
      const showCompletedSwitch = document.getElementById('showCompletedSwitch');
      const completedProjectsTable = document.getElementById('completedProjectsTable');
      const savedShowCompleted = localStorage.getItem('showCompletedProjects') === 'true';
      showCompletedSwitch.checked = savedShowCompleted;
      completedProjectsTable.style.display = savedShowCompleted ? 'block' : 'none';
      showCompletedSwitch.addEventListener('change', function() {
        const isChecked = this.checked;
        completedProjectsTable.style.display = isChecked ? 'block' : 'none';
        localStorage.setItem('showCompletedProjects', isChecked);
      });

      // Поиск и фильтрация
      function refreshProjects() {
        const searchVal = $("#searchInput").val().trim();
        const statusVal = $("#statusSelect").val();
        $.ajax({
          url: "../process/ajax_project_search.php",
          method: "GET",
          data: { 
            search: searchVal, 
            status: statusVal,
            role_id: <?= $role_id ?>,
            user_id: <?= $user_id ?>
          },
          dataType: "json",
          cache: false,
          success: function(data) {
            if (data.success) {
              renderProjects(data.activeProjects, '#projectTableBody');
              renderProjects(data.completedProjects, '#completedProjectTableBody');
            } else {
              alert(data.message || 'Ошибка загрузки проектов');
            }
          },
          error: function(xhr) {
            console.error('Ошибка AJAX:', xhr.status, xhr.responseText);
            alert("Ошибка AJAX запроса: " + (xhr.responseJSON?.message || 'Неизвестная ошибка'));
          }
        });
      }

      // Рендеринг проектов
      function renderProjects(projects, tableBodySelector) {
        const tbody = $(tableBodySelector);
        tbody.empty();
        if (!Array.isArray(projects) || projects.length === 0) {
          const message = tableBodySelector === '#completedProjectTableBody' 
            ? 'Нет завершённых проектов.' 
            : 'Нет активных проектов.';
          tbody.append(`<tr><td colspan="5">${message}</td></tr>`);
          return;
        }
        projects.forEach(project => {
          if (!project.project_id) return;
          const row = `
            <tr id="project-${project.project_id}">
              <td>${project.name || 'Без названия'}</td>
              <td>${project.status_display || 'Неизвестный статус'}</td>
              <td>${project.planned_budget ? project.planned_budget + ' BYN' : '0 BYN'}</td>
              <td>${project.actual_budget ? project.actual_budget + ' BYN' : '0 BYN'}</td>
              <td>
                <div class="d-flex flex-column align-items-start gap-2">
                  <a href="project_details.php?id=${project.project_id}" class="btn btn-sm btn-info">Подробнее</a>
                  <?php if ($role_id == 1 || $role_id == 2): ?>
                    <button class="btn btn-sm btn-warning change-status-btn" data-bs-toggle="modal" data-bs-target="#changeStatusModal" data-project-id="${project.project_id}" data-current-status="${project.status || ''}">Изменить статус</button>
                    <a href="edit_project.php?id=${project.project_id}" class="btn btn-sm btn-primary">Изменить</a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          `;
          tbody.append(row);
        });
      }

      // Сортировка
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

      // Обработка клика по заголовкам для сортировки
      $('.sortable').on('click', function() {
        const key = $(this).data('sort');
        const table = $(this).closest('table');
        const tbodySelector = table.find('tbody').attr('id');
        const isCompleted = tbodySelector === 'completedProjectTableBody';
        const currentOrder = $(this).hasClass('asc') ? 'desc' : 'asc';

        // Удаляем классы сортировки со всех заголовков
        table.find('.sortable').removeClass('asc desc');
        $(this).addClass(currentOrder);

        // Получаем текущие проекты через AJAX
        $.ajax({
          url: "../process/ajax_project_search.php",
          method: "GET",
          data: { 
            search: $("#searchInput").val().trim(), 
            status: $("#statusSelect").val(),
            role_id: <?= $role_id ?>,
            user_id: <?= $user_id ?>
          },
          dataType: "json",
          success: function(data) {
            if (data.success) {
              const projects = isCompleted ? data.completedProjects : data.activeProjects;
              const sortedProjects = sortProjects(projects, key, currentOrder);
              renderProjects(sortedProjects, '#' + tbodySelector);
            }
          },
          error: function(xhr) {
            console.error('Ошибка AJAX при сортировке:', xhr.status, xhr.responseText);
            alert("Ошибка AJAX запроса: " + (xhr.responseJSON?.message || 'Неизвестная ошибка'));
          }
        });
      });

      // Привязка событий для поиска и фильтрации
      $(document).on("keyup", "#searchInput", refreshProjects);
      $(document).on("change", "#statusSelect", refreshProjects);
      refreshProjects();

      // Обработка кнопки изменения статуса
      $(document).on('click', '.change-status-btn', function() {
        const projectId = $(this).data('project-id');
        const currentStatus = $(this).data('current-status');
        $('#statusProjectId').val(projectId);
        $('#projectStatus').val(currentStatus);
      });

      // Сохранение нового статуса
      $('#saveStatusBtn').on('click', function() {
        const projectId = $('#statusProjectId').val();
        const newStatus = $('#projectStatus').val();
        if (!['planning', 'in_progress', 'completed', 'on_hold'].includes(newStatus)) {
          alert('Недопустимый статус');
          return;
        }
        $.ajax({
          url: '../process/update_project_status.php',
          method: 'POST',
          data: { project_id: projectId, status: newStatus },
          dataType: 'json',
          success: function(response) {
            if (response.success) {
              $('#changeStatusModal').modal('hide');
              refreshProjects();
            } else {
              alert(response.message || 'Ошибка обновления статуса');
            }
          },
          error: function(xhr) {
            console.error('Ошибка AJAX при обновлении статуса:', xhr);
            alert('Ошибка AJAX запроса: ' + (xhr.responseJSON?.message || 'Неизвестная ошибка'));
          }
        });
      });

      // Валидация формы смены пароля
      document.querySelector('#passwordForm').addEventListener('submit', function(event) {
        const oldPassword = document.querySelector('#old_password').value.trim();
        const newPassword = document.querySelector('#new_password').value.trim();
        const confirmPassword = document.querySelector('#confirm_password').value.trim();
        const passwordRegex = /^(?=.*[A-Za-z])(?=.*\d).+$/;

        if (!oldPassword || !newPassword || !confirmPassword) {
          alert("Все поля должны быть заполнены.");
          event.preventDefault();
          return;
        }

        if (newPassword.length < 8) {
          alert("Новый пароль должен содержать минимум 8 символов.");
          event.preventDefault();
          return;
        }

        if (!passwordRegex.test(newPassword)) {
          alert("Новый пароль должен содержать как минимум одну букву и одну цифру.");
          event.preventDefault();
          return;
        }

        if (newPassword !== confirmPassword) {
          alert("Новый пароль и подтверждение пароля не совпадают.");
          event.preventDefault();
          return;
        }
      });

      // Валидация формы редактирования профиля
      document.querySelector('#profileForm').addEventListener('submit', function(event) {
        const fullname = document.querySelector('#fullname').value.trim();
        const email = document.querySelector('#email').value.trim();
        const position = document.querySelector('#position').value.trim();
        const department = document.querySelector('#department').value.trim();

        if (!fullname || !email || !position || !department) {
          alert("Все поля должны быть заполнены.");
          event.preventDefault();
          return;
        }

        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
          alert("Неверный формат email.");
          event.preventDefault();
          return;
        }
      });
    });
  </script>
</body>
</html>