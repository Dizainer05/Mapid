<?php
// edit_stage.php
session_start();
require_once __DIR__ . '/../db.php';

// Доступ только для админа и менеджера
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["role_id"], [1, 2])) {
    header("Location: ../index.php");
    exit();
}

if (!isset($_GET["stage_id"]) || !is_numeric($_GET["stage_id"])) {
    die("Некорректный идентификатор этапа.");
}
$stage_id = intval($_GET["stage_id"]);

// Загружаем данные этапа напрямую (так как функции getStage() нет, выполняем запрос)
$query = "SELECT * FROM project_stages WHERE stage_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $stage_id);
$stmt->execute();
$result = $stmt->get_result();
$stage = $result->fetch_assoc();
$stmt->close();

if (!$stage) {
    die("Этап не найден.");
}

$project_id = $stage["project_id"];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Редактировать этап</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
  <div class="container mt-5">
    <h2>Редактировать этап проекта</h2>
    <form method="post" action="process/process_edit_stage.php">
      <input type="hidden" name="stage_id" value="<?= htmlspecialchars($stage_id) ?>">
      <input type="hidden" name="project_id" value="<?= htmlspecialchars($project_id) ?>">
      <div class="mb-3">
        <label for="stage_name" class="form-label">Название этапа</label>
        <input type="text" class="form-control" id="stage_name" name="stage_name" value="<?= htmlspecialchars($stage["stage_name"] ?? '') ?>" required>
      </div>
      <div class="mb-3">
        <label for="description" class="form-label">Описание</label>
        <textarea class="form-control" id="description" name="description" rows="4"><?= htmlspecialchars($stage["description"] ?? '') ?></textarea>
      </div>
      <div class="row mb-3">
         <div class="col-md-6">
            <label for="start_date" class="form-label">Дата начала</label>
            <input type="date" class="form-control" id="start_date" name="start_date" value="<?= htmlspecialchars($stage["start_date"] ?? '') ?>">
         </div>
         <div class="col-md-6">
            <label for="end_date" class="form-label">Дата завершения</label>
            <input type="date" class="form-control" id="end_date" name="end_date" value="<?= htmlspecialchars($stage["end_date"] ?? '') ?>">
         </div>
      </div>
      <div class="mb-3">
        <label for="status" class="form-label">Статус этапа</label>
        <select class="form-select" id="status" name="status">
          <option value="not_started" <?= ($stage["status"] ?? '') == 'not_started' ? "selected" : "" ?>>Не начат</option>
          <option value="in_progress" <?= ($stage["status"] ?? '') == 'in_progress' ? "selected" : "" ?>>В процессе</option>
          <option value="completed" <?= ($stage["status"] ?? '') == 'completed' ? "selected" : "" ?>>Завершен</option>
        </select>
      </div>
      <div class="mb-3">
        <label for="stage_order" class="form-label">Порядок этапа</label>
        <input type="number" class="form-control" id="stage_order" name="stage_order" value="<?= htmlspecialchars($stage["stage_order"] ?? '1') ?>" required>
      </div>
      <button type="submit" class="btn btn-primary">Сохранить изменения</button>
      <a href="project_details.php?id=<?= $project_id ?>" class="btn btn-secondary">Отмена</a>
    </form>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
