<?php
// add_participant.php
session_start();
require_once __DIR__ . '/../db.php';

// Проверяем авторизацию
if (!isset($_SESSION["user_id"])) {
    header("Location: ../pages/auth.php");
    exit();
}

// Получаем ID проекта
if (!isset($_GET["project_id"]) || !is_numeric($_GET["project_id"])) {
    die("Некорректный идентификатор проекта.");
}
$project_id = intval($_GET["project_id"]);

// Данные пользователя
$user_id = $_SESSION["user_id"];
$role_id = $_SESSION["role_id"];

// Получаем проектную роль пользователя
$project_role = null;
$sql = "SELECT project_role FROM project_participants WHERE project_id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Ошибка подготовки запроса (project_role): " . $conn->error);
}
$stmt->bind_param("ii", $project_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $project_role = $row["project_role"];
}
$stmt->close();

// Если пользователь не является участником проекта, но имеет глобальную роль admin или manager
if ($project_role === null && ($role_id == 1 || $role_id == 2)) {
    $project_role = ($role_id == 1) ? 'admin' : 'manager';
}

// Проверяем, имеет ли пользователь право доступа
if ($project_role !== 'admin' && $project_role !== 'manager') {
    header("Location: ../index.php");
    exit();
}

// Получаем список пользователей
$query = "SELECT u.user_id, u.full_name, u.avatar
          FROM users u
          WHERE u.role_id IN (3, 4)
          AND u.user_id NOT IN (SELECT pp.user_id FROM project_participants pp WHERE pp.project_id = ?)";
$stmt = $conn->prepare($query);
if ($stmt === false) {
    die("Ошибка подготовки запроса (users): " . $conn->error);
}
$stmt->bind_param("i", $project_id);
$stmt->execute();
$result = $stmt->get_result();
$users = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Проверяем и корректируем данные пользователей
foreach ($users as &$user) {
    $user['full_name'] = !empty($user['full_name']) ? $user['full_name'] : 'Имя не указано';
    $avatarPath = __DIR__ . "/../Uploads/" . ($user['avatar'] ?? 'default_avatar.jpg');
    if (!file_exists($avatarPath) || empty($user['avatar'])) {
        $user['avatar'] = 'default_avatar.jpg';
    }
}
unset($user);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Добавить участника</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #f4f7fa;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
    }
    .navbar {
      background-color: #ffffff;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      padding: 0.75rem 1rem;
    }
    .navbar-brand {
      font-weight: 600;
      color: #333;
    }
    .container {
      max-width: 1200px;
      margin-top: 80px;
      padding: 2rem;
      background: #ffffff;
      border-radius: 15px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    }
    h2 {
      font-size: 1.8rem;
      font-weight: 600;
      color: #1a1a1a;
      margin-bottom: 1.5rem;
    }
    /* Поле поиска */
    .search-container {
      position: relative;
      max-width: 500px;
      margin-bottom: 2rem;
    }
    .search-container input {
      width: 100%;
      padding: 12px 40px 12px 16px;
      border: 2px solid #e0e0e0;
      border-radius: 10px;
      font-size: 0.95rem;
      transition: all 0.3s ease;
      background: #f8f9fa;
    }
    .search-container input:focus {
      border-color: #007bff;
      box-shadow: 0 0 8px rgba(0,123,255,0.2);
      outline: none;
    }
    .search-container::before {
      content: '🔍';
      position: absolute;
      right: 16px;
      top: 50%;
      transform: translateY(-50%);
      font-size: 1.1rem;
      color: #6c757d;
    }
    /* Карточки пользователей */
    .users-container {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem;
    }
    .user-card {
      background: #ffffff;
      border-radius: 12px;
      padding: 1rem;
      box-shadow: 0 4px 12px rgba(0,0,0,0.05);
      text-align: center;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
      position: relative;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: space-between;
      min-height: 180px;
    }
    .user-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    }
    .user-card .avatar {
      width: 70px;
      height: 70px;
      border-radius: 50%;
      object-fit: cover;
      border: 3px solid #e0e0e0;
      margin-bottom: 0.5rem;
      transition: border-color 0.3s ease;
    }
    .user-card:hover .avatar {
      border-color: #007bff;
    }
    .user-card span {
      font-size: 0.95rem;
      font-weight: 500;
      color: #333;
      word-break: break-word;
      max-width: 90%;
    }
    /* Кастомный чекбокс */
    .user-card input[type="checkbox"] {
      appearance: none;
      width: 20px;
      height: 20px;
      border: 2px solid #007bff;
      border-radius: 5px;
      position: absolute;
      top: 10px;
      right: 10px;
      cursor: pointer;
      transition: background-color 0.3s ease;
    }
    .user-card input[type="checkbox"]:checked {
      background-color: #007bff;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23fff' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='20 6 9 17 4 12'%3E%3C/polyline%3E%3C/svg%3E");
      background-size: 14px;
      background-position: center;
      background-repeat: no-repeat;
    }
    /* Форма и кнопки */
    .form-select {
      border-radius: 8px;
      padding: 10px;
      border: 2px solid #e0e0e0;
      font-size: 0.95rem;
      transition: border-color 0.3s ease;
    }
    .form-select:focus {
      border-color: #007bff;
      box-shadow: 0 0 8px rgba(0,123,255,0.2);
    }
    .btn-primary {
      background-color: #007bff;
      border: none;
      padding: 10px 24px;
      border-radius: 8px;
      font-weight: 500;
      transition: background-color 0.3s ease, transform 0.2s ease;
    }
    .btn-primary:hover {
      background-color: #0056b3;
      transform: translateY(-2px);
    }
    .btn-secondary {
      background-color: #6c757d;
      border: none;
      padding: 10px 24px;
      border-radius: 8px;
      font-weight: 500;
      transition: background-color 0.3s ease, transform 0.2s ease;
    }
    .btn-secondary:hover {
      background-color: #5a6268;
      transform: translateY(-2px);
    }
    /* Адаптивность */
    @media (max-width: 768px) {
      .container {
        margin-top: 60px;
        padding: 1.5rem;
      }
      .users-container {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
      }
      .user-card {
        min-height: 160px;
      }
      .user-card .avatar {
        width: 60px;
        height: 60px;
      }
      h2 {
        font-size: 1.5rem;
      }
    }
  </style>
</head>
<body>
  <!-- Навигационное меню -->
  <nav class="navbar fixed-top">
    <div class="container-fluid">
      <a class="navbar-brand" href="../index.php">Панель управления</a>
      <div class="d-flex">
        <a href="../pages/project_details.php?id=<?= $project_id ?>" class="btn btn-secondary">Назад</a>
      </div>
    </div>
  </nav>

  <div class="container">
    <h2>Добавить участника в проект</h2>
    <form method="post" action="../process/process_add_participant.php">
      <input type="hidden" name="project_id" value="<?= $project_id ?>">

      <!-- Поле поиска -->
      <div class="search-container">
        <input type="text" id="search" placeholder="Поиск по ФИО..." autocomplete="off">
      </div>

      <!-- Список пользователей -->
      <div class="users-container">
        <?php if (!empty($users)): ?>
          <?php foreach ($users as $user): ?>
            <div class="user-card" data-fullname="<?= htmlspecialchars($user['full_name']) ?>">
              <img src="../Uploads/<?= htmlspecialchars($user['avatar']) ?>" alt="Avatar" class="avatar">
              <span><?= htmlspecialchars($user['full_name']) ?></span>
              <input type="checkbox" name="user_ids[]" value="<?= $user['user_id'] ?>">
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p class="text-muted">Нет доступных пользователей для добавления.</p>
        <?php endif; ?>
      </div>

      <!-- Выбор роли -->
      <div class="mb-4">
        <label for="project_role" class="form-label fw-medium">Роль в проекте</label>
        <select class="form-select" id="project_role" name="project_role" required>
          <option value="employee">Сотрудник</option>
          <option value="manager">Менеджер</option>
          <option value="user">Пользователь</option>
        </select>
      </div>

      <!-- Кнопки -->
      <div class="d-flex gap-3">
        <button type="submit" class="btn btn-primary">Добавить участников</button>
        <a href="../pages/project_details.php?id=<?= $project_id ?>" class="btn btn-secondary">Отмена</a>
      </div>
    </form>
  </div>

  <!-- JavaScript -->
  <script>
    document.getElementById('search').addEventListener('input', function() {
      const searchValue = this.value.toLowerCase();
      const userCards = document.querySelectorAll('.user-card');
      userCards.forEach(card => {
        const fullname = card.getAttribute('data-fullname').toLowerCase();
        card.style.display = fullname.includes(searchValue) ? 'flex' : 'none';
      });
    });
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>