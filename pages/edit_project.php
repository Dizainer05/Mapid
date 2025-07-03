<?php
// edit_project.php
session_start();
require_once __DIR__ . '/../db.php';

// Проверяем авторизацию
if (!isset($_SESSION["user_id"])) {
    header("Location: ../pages/auth.php");
    exit();
}

// Получаем ID проекта
if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    die("Некорректный идентификатор проекта.");
}
$project_id = intval($_GET["id"]);

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

// Проверяем, имеет ли пользователь право доступа (должен быть admin или manager в проекте)
if ($project_role !== 'admin' && $project_role !== 'manager') {
    header("Location: ../index.php");
    exit();
}

// Получаем данные проекта
$project = getProjectDetails($conn, $project_id);
if (!$project) {
    die("Проект не найден.");
}

// Логируем данные проекта для отладки
error_log("Данные проекта ID $project_id: " . json_encode($project));

// Отладочный вывод значения scale
error_log("Значение scale перед отображением: " . var_export($project["scale"], true));
?>

<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Редактировать проект – <?= htmlspecialchars($project["name"] ?? '') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: #f8f9fa;
    }
    .container {
      margin-top: 50px;
    }
    .card {
      border-radius: 10px;
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }
    .card-header {
      background-color: #007bff;
      color: white;
    }
    .form-label span.required {
      color: red;
    }
    .text-muted {
      font-size: 0.85rem;
    }
    .btn-primary {
      background-color: #007bff;
      border: none;
    }
    .btn-primary:hover {
      background-color: #0056b3;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      <div class="card-header">
        <h2 class="mb-0">Редактировать проект</h2>
      </div>
      <div class="card-body">
        <?php if (isset($_SESSION["error_msg"])): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($_SESSION["error_msg"]) ?>
                <?php unset($_SESSION["error_msg"]); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION["success_msg"])): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($_SESSION["success_msg"]) ?>
                <?php unset($_SESSION["success_msg"]); ?>
            </div>
        <?php endif; ?>
        <form method="post" action="../process/process_edit_project.php" enctype="multipart/form-data" onsubmit="return validateForm()">
          <input type="hidden" name="project_id" value="<?= htmlspecialchars($project_id) ?>">
          
          <div class="mb-3">
            <label for="name" class="form-label"><span class="required">*</span> Название проекта</label>
            <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($project["name"] ?? '') ?>" required>
          </div>
          
          <div class="mb-3">
            <label for="short_name" class="form-label">Сокращенное название <span class="text-muted">(не обязательно)</span></label>
            <input type="text" class="form-control" id="short_name" name="short_name" value="<?= htmlspecialchars($project["short_name"] ?? '') ?>">
          </div>
          
          <div class="mb-3">
            <label for="description" class="form-label">Описание <span class="text-muted">(не обязательно)</span></label>
            <textarea class="form-control" id="description" name="description" rows="4"><?= htmlspecialchars($project["description"] ?? '') ?></textarea>
          </div>
          
          <div class="row mb-3">
            <div class="col-md-6">
              <label for="planned_start_date" class="form-label">Плановая дата начала <span class="text-muted">(не обязательно)</span></label>
              <input type="date" class="form-control" id="planned_start_date" name="planned_start_date" value="<?= htmlspecialchars($project["planned_start_date"] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label for="actual_start_date" class="form-label">Фактическая дата начала <span class="text-muted">(не обязательно)</span></label>
              <input type="date" class="form-control" id="actual_start_date" name="actual_start_date" value="<?= htmlspecialchars($project["actual_start_date"] ?? '') ?>">
            </div>
          </div>
          
          <div class="row mb-3">
            <div class="col-md-6">
              <label for="planned_end_date" class="form-label">Плановая дата завершения <span class="text-muted">(не обязательно)</span></label>
              <input type="date" class="form-control" id="planned_end_date" name="planned_end_date" value="<?= htmlspecialchars($project["planned_end_date"] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label for="actual_end_date" class="form-label">Фактическая дата завершения <span class="text-muted">(не обязательно)</span></label>
              <input type="date" class="form-control" id="actual_end_date" name="actual_end_date" value="<?= htmlspecialchars($project["actual_end_date"] ?? '') ?>">
            </div>
          </div>
          
          <div class="row mb-3">
            <div class="col-md-4">
              <label for="planned_budget" class="form-label">Плановый бюджет <span class="text-muted">(не обязательно)</span></label>
              <input type="number" step="0.01" min="0" class="form-control" id="planned_budget" name="planned_budget" value="<?= htmlspecialchars($project["planned_budget"] ?? 0) ?>">
            </div>
            <div class="col-md-4">
              <label for="actual_budget" class="form-label">Фактический бюджет <span class="text-muted">(не обязательно)</span></label>
              <input type="number" step="0.01" min="0" class="form-control" id="actual_budget" name="actual_budget" value="<?= htmlspecialchars($project["actual_budget"] ?? 0) ?>">
            </div>
            <div class="col-md-4">
              <label for="status" class="form-label"><span class="required">*</span> Статус проекта</label>
              <select class="form-control" id="status" name="status" required>
                <option value="planning" <?= $project["status"] === 'planning' ? 'selected' : '' ?>>Планирование</option>
                <option value="in_progress" <?= $project["status"] === 'in_progress' ? 'selected' : '' ?>>В процессе</option>
                <option value="completed" <?= $project["status"] === 'completed' ? 'selected' : '' ?>>Завершённые</option>
                <option value="on_hold" <?= $project["status"] === 'on_hold' ? 'selected' : '' ?>>Приостановленные</option>
              </select>
            </div>
          </div>
          
          <div class="row mb-3">
            <div class="col-md-4">
              <label for="planned_digitalization" class="form-label">Плановый уровень цифровизации <span class="text-muted">(не обязательно)</span></label>
              <input type="number" step="0.01" min="0" class="form-control" id="planned_digitalization" name="planned_digitalization" value="<?= htmlspecialchars($project["planned_digitalization_level"] ?? 0) ?>">
            </div>
            <div class="col-md-4">
              <label for="actual_digitalization" class="form-label">Фактический уровень цифровизации <span class="text-muted">(не обязательно)</span></label>
              <input type="number" step="0.01" min="0" class="form-control" id="actual_digitalization" name="actual_digitalization" value="<?= htmlspecialchars($project["actual_digitalization_level"] ?? 0) ?>">
            </div>
            <div class="col-md-4">
              <label for="lifecycle_stage" class="form-label">Этап жизненного цикла <span class="text-muted">(не обязательно)</span></label>
              <select class="form-control" id="lifecycle_stage" name="lifecycle_stage">
                <option value="initiation" <?= $project["lifecycle_stage"] === 'initiation' ? 'selected' : '' ?>>Инициирование</option>
                <option value="planning" <?= $project["lifecycle_stage"] === 'planning' ? 'selected' : '' ?>>Планирование</option>
                <option value="execution" <?= $project["lifecycle_stage"] === 'execution' ? 'selected' : '' ?>>Выполнение</option>
                <option value="monitoring" <?= $project["lifecycle_stage"] === 'monitoring' ? 'selected' : '' ?>>Мониторинг</option>
                <option value="closure" <?= $project["lifecycle_stage"] === 'closure' ? 'selected' : '' ?>>Завершение</option>
              </select>
            </div>
          </div>
          
          <div class="row mb-3">
            <div class="col-md-4">
              <label for="planned_labor" class="form-label">Плановые затраты на труд <span class="text-muted">(не обязательно)</span></label>
              <input type="number" step="0.01" min="0" class="form-control" id="planned_labor" name="planned_labor" value="<?= htmlspecialchars($project["planned_labor_costs"] ?? 0) ?>">
            </div>
            <div class="col-md-4">
              <label for="actual_labor" class="form-label">Фактические затраты на труд <span class="text-muted">(не обязательно)</span></label>
              <input type="number" step="0.01" min="0" class="form-control" id="actual_labor" name="actual_labor" value="<?= htmlspecialchars($project["actual_labor_costs"] ?? 0) ?>">
            </div>
            <div class="col-md-4">
              <label for="scale" class="form-label">Масштаб проекта <span class="text-muted">(не обязательно)</span></label>
              <select class="form-control" id="scale" name="scale">
                <option value="small" <?= ($project["scale"] === 'small' || $project["scale"] === null) ? 'selected' : '' ?>>Малый</option>
                <option value="medium" <?= $project["scale"] === 'medium' ? 'selected' : '' ?>>Средний</option>
                <option value="large" <?= $project["scale"] === 'large' ? 'selected' : '' ?>>Крупный</option>
                <option value="megaproject" <?= $project["scale"] === 'megaproject' ? 'selected' : '' ?>>Мегапроект</option>
              </select>
            </div>
          </div>
          
          <div class="mb-3">
            <label for="charter_file" class="form-label">Устав <span class="text-muted">(не обязательно, можно загрузить новый файл)</span></label>
            <?php if (!empty($project["charter_file_path"])): ?>
              <p>Текущий файл: <a href="<?= htmlspecialchars($project["charter_file_path"]) ?>" target="_blank">Скачать</a></p>
            <?php endif; ?>
            <input type="file" class="form-control" id="charter_file" name="charter_file">
          </div>
          
          <div class="mb-3">
            <label for="expected_resources" class="form-label">Ожидаемые ресурсы <span class="text-muted">(не обязательно)</span></label>
            <textarea class="form-control" id="expected_resources" name="expected_resources" rows="3"><?= htmlspecialchars($project["expected_resources"] ?? '') ?></textarea>
          </div>
          
          <div class="mb-3">
            <label for="access_code" class="form-label"><span class="required">*</span> Код доступа</label>
            <input type="text" class="form-control" id="access_code" name="access_code" value="<?= htmlspecialchars($project["access_code"] ?? '') ?>" required>
          </div>
          
          <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label">Рейтинг проекта</label>
                <p class="form-control-plaintext"><?= htmlspecialchars($project["rating"] ?? 'Не указан') ?></p>
            </div>
            <!-- <div class="col-md-6">
                <label for="start_date" class="form-label">Дополнительная дата начала <span class="text-muted">(не обязательно)</span></label>
                <input type="date" class="form-control" id="start_date" name="start_date" value="<?= htmlspecialchars($project["start_date"] ?? '') ?>">
            </div> -->
        </div>
          
          <div class="d-flex justify-content-between">
            <button type="submit" class="btn btn-primary">Сохранить изменения</button>
            <a href="project_details.php?id=<?= $project_id ?>" class="btn btn-secondary">Отмена</a>
          </div>
        </form>
      </div>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function validateForm() {
      const inputs = [
        { id: 'planned_budget', name: 'Плановый бюджет' },
        { id: 'actual_budget', name: 'Фактический бюджет' },
        { id: 'planned_digitalization', name: 'Плановый уровень цифровизации' },
        { id: 'actual_digitalization', name: 'Фактический уровень цифровизации' },
        { id: 'planned_labor', name: 'Плановые затраты на труд' },
        { id: 'actual_labor', name: 'Фактические затраты на труд' }
      ];
      
      for (const input of inputs) {
        const value = document.getElementById(input.id).value;
        if (value && parseFloat(value) < 0) {
          alert(`${input.name} не может быть отрицательным.`);
          return false;
        }
      }
      return true;
    }
  </script>
</body>
</html>