<?php
ob_start(); // Start output buffering
session_start();
require_once __DIR__ . '/../db.php'; // Include database connection
require_once __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php'; // Include TCPDF library
require_once __DIR__ . '/../vendor/autoload.php'; // Include Composer autoloader for PhpWord

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;

// Enable error reporting and logging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Check user authorization
if (!isset($_SESSION["user_id"])) {
    error_log("Ошибка: пользователь не авторизован.");
    die("Доступ запрещен. Пожалуйста, авторизуйтесь.");
}

// Function to get Russian status name
function getRussianStatus($status) {
    switch ($status) {
        case 'planning': return 'Планирование';
        case 'active': return 'Активные';
        case 'in_progress': return 'В процессе';
        case 'completed': return 'Завершённые';
        case 'on_hold': return 'Приостановленные';
        default: return 'Неизвестный';
    }
}

// Function to count projects by status
function getStatusCounts($projects) {
    $counts = [
        'planning' => 0,
        'active' => 0,
        'in_progress' => 0,
        'completed' => 0,
        'on_hold' => 0
    ];
    foreach ($projects as $project) {
        if (isset($counts[$project['status']])) {
            $counts[$project['status']]++;
        }
    }
    return $counts;
}

try {
    // Get parameters from GET request
    $project_id = $_GET['project_id'] ?? null;
    $filters = [
        'name' => $_GET['name'] ?? '',
        'budget_min' => $_GET['budget_min'] ?? 0,
        'budget_max' => $_GET['budget_max'] ?? PHP_INT_MAX,
        'status' => $_GET['status'] ?? '',
        'start_date' => $_GET['start_date'] ?? '',
        'end_date' => $_GET['end_date'] ?? '',
        'format' => $_GET['format'] ?? 'pdf' // Default to PDF
    ];

    // Build SQL query
    $sql = "
        SELECT 
            p.project_id,
            p.name,
            p.status,
            p.planned_budget,
            p.planned_start_date,
            p.planned_end_date
        FROM projects p
        WHERE 1=1
    ";
    $params = [];
    $types = '';

    if ($project_id) {
        $sql .= " AND p.project_id = ?";
        $params[] = (int)$project_id;
        $types .= 'i';
    }
    if (!empty($filters['name'])) {
        $sql .= " AND p.name LIKE ?";
        $params[] = "%{$filters['name']}%";
        $types .= 's';
    }
    if (!empty($filters['budget_min'])) {
        $sql .= " AND p.planned_budget >= ?";
        $params[] = (float)$filters['budget_min'];
        $types .= 'd';
    }
    if (!empty($filters['budget_max'])) {
        $sql .= " AND p.planned_budget <= ?";
        $params[] = (float)$filters['budget_max'];
        $types .= 'd';
    }
    if (!empty($filters['status'])) {
        $sql .= " AND p.status = ?";
        $params[] = $filters['status'];
        $types .= 's';
    }
    if (!empty($filters['start_date'])) {
        $sql .= " AND p.planned_start_date >= ?";
        $params[] = $filters['start_date'];
        $types .= 's';
    }
    if (!empty($filters['end_date'])) {
        $sql .= " AND p.planned_end_date <= ?";
        $params[] = $filters['end_date'];
        $types .= 's';
    }

    // Prepare and execute query
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception("Ошибка подготовки запроса: " . $conn->error);
    }
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $projects = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($projects)) {
        throw new Exception("Нет данных для формирования отчета.");
    }

    // Format dates for header
    $start_date = $filters['start_date'] ? date('d.m.Y', strtotime($filters['start_date'])) : 'не указана';
    $end_date = $filters['end_date'] ? date('d.m.Y', strtotime($filters['end_date'])) : 'не указана';
    $report_title = ($start_date === 'не указана' && $end_date === 'не указана') 
        ? "Отчет за весь период времени" 
        : "Отчет за период: {$start_date} – {$end_date}";

    // Calculate project statistics
    $total_projects = count($projects);
    $status_counts = getStatusCounts($projects);
    $status_summary = [];
    foreach ($status_counts as $status => $count) {
        if ($count > 0) {
            $status_summary[] = "$count проект" . ($count % 10 == 1 && $count % 100 != 11 ? '' : 'а') 
                . " в " . getRussianStatus($status);
        }
    }
    $statistics_lines = ["Всего проектов: $total_projects"];
    $statistics_lines = array_merge($statistics_lines, $status_summary);
    $statistics_text = implode("\n", $statistics_lines);

    // Define logo path
    $logoPath = __DIR__ . '/../images/doc.png';

    // Check if the logo file exists
    if (!file_exists($logoPath)) {
        throw new Exception("Файл логотипа не найден: " . $logoPath);
    }

    if ($filters['format'] === 'word') {
        // Word report
        $phpWord = new PhpWord();
        $section = $phpWord->addSection(['marginLeft' => 1200, 'marginRight' => 1200, 'marginTop' => 600, 'marginBottom' => 1200]);

        // Add logo
        $section->addImage($logoPath, ['width' => 100, 'height' => 100, 'alignment' => 'center']);

        // Add company header
        $section->addText('СУ-246 ОАО «МАПИД»', ['name' => 'Arial', 'size' => 14, 'bold' => true], ['alignment' => 'center']);
        $section->addText('220075, г. Минск, ул. Селицкая, 31', ['name' => 'Arial', 'size' => 12], ['alignment' => 'center']);
        $section->addText('Тел. (+37517) 373-27-92, факс (+37517) 397-51-42', ['name' => 'Arial', 'size' => 12], ['alignment' => 'center']);
        $section->addText('E-mail: su246mapid@mail.ru', ['name' => 'Arial', 'size' => 12], ['alignment' => 'center']);
        $section->addText('Документ №_________', ['name' => 'Arial', 'size' => 12], ['alignment' => 'center']);
        $section->addTextBreak(1);

        // Add report title
        $section->addText($report_title, ['name' => 'Arial', 'size' => 12, 'bold' => true], ['alignment' => 'center']);
        $section->addTextBreak(1);

        // Add statistics
        foreach ($statistics_lines as $line) {
            $section->addText($line, ['name' => 'Arial', 'size' => 12], ['alignment' => 'left']);
        }
        $section->addTextBreak(1);

        // Add table
        $table = $section->addTable(['borderSize' => 1, 'borderColor' => '000000', 'cellMargin' => 80]);
        $table->addRow();
        $table->addCell(4000)->addText('Название', ['name' => 'Arial', 'size' => 12, 'bold' => true]);
        $table->addCell(3000)->addText('Статус', ['name' => 'Arial', 'size' => 12, 'bold' => true]);
        $table->addCell(3000)->addText('Бюджет (BYN)', ['name' => 'Arial', 'size' => 12, 'bold' => true]);

        foreach ($projects as $project) {
            $table->addRow();
            $table->addCell(4000)->addText(htmlspecialchars($project['name']), ['name' => 'Arial', 'size' => 12]);
            $table->addCell(3000)->addText(htmlspecialchars(getRussianStatus($project['status'])), ['name' => 'Arial', 'size' => 12]);
            $table->addCell(3000)->addText(number_format($project['planned_budget'], 2, '.', ','), ['name' => 'Arial', 'size' => 12]);
        }

        // Add signature fields
        $section->addTextBreak(2);
        $section->addText('Прораб(мастер) СУ-246 ОАО «МАПИД» РБ ________________', ['name' => 'Arial', 'size' => 12], ['alignment' => 'left']);
        $section->addTextBreak(1);
        $section->addText('Начальник ОТК ________________', ['name' => 'Arial', 'size' => 12], ['alignment' => 'left']);

        // Save file
        $filename = 'report_' . time() . '.docx';
        $writer = WordIOFactory::createWriter($phpWord, 'Word2007');
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
        exit;

    } elseif ($filters['format'] === 'pdf') {
        // PDF report
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Project Manager');
        $pdf->SetAuthor('Admin Panel');
        $pdf->SetTitle('Отчет');
        $pdf->SetMargins(15, 10, 15);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();

        // Add logo
        $pdf->Image($logoPath, 90, 5, 30, 30, '', '', '', false, 300, '', false, false, 0);

        // Add company header
        $pdf->SetFont('dejavusans', '', 12);
        $pdf->SetY(35);
        $pdf->Cell(0, 10, 'СУ-246 ОАО «МАПИД»', 0, 1, 'C');
        $pdf->Cell(0, 10, '220075, г. Минск, ул. Селицкая, 31', 0, 1, 'C');
        $pdf->Cell(0, 10, 'Тел. (+37517) 373-27-92, факс (+37517) 397-51-42', 0, 1, 'C');
        $pdf->Cell(0, 10, 'E-mail: su246mapid@mail.ru', 0, 1, 'C');
        $pdf->Cell(0, 10, 'Документ №_________', 0, 1, 'C');
        $pdf->Ln(10);

        // Add report title
        $pdf->SetFont('dejavusans', 'B', 12);
        $pdf->Cell(0, 10, $report_title, 0, 1, 'C');
        $pdf->Ln(5);

        // Add statistics
        $pdf->SetFont('dejavusans', '', 12);
        $pdf->MultiCell(0, 10, $statistics_text, 0, 'L');
        $pdf->Ln(5);

        // Add table
        $html = '<table border="1" cellpadding="5">
            <tr>
                <th width="40%">Название</th>
                <th width="30%">Статус</th>
                <th width="30%">Бюджет (BYN)</th>
            </tr>';
        foreach ($projects as $project) {
            $html .= '<tr>
                <td>' . htmlspecialchars($project['name']) . '</td>
                <td>' . htmlspecialchars(getRussianStatus($project['status'])) . '</td>
                <td>' . number_format($project['planned_budget'], 2, '.', ',') . '</td>
            </tr>';
        }
        $html .= '</table>';
        $pdf->writeHTML($html, true, false, true, false, '');

        // Add signature fields
        $pdf->Ln(20);
        $pdf->Cell(0, 10, 'Прораб(мастер) СУ-246 ОАО «МАПИД» РБ ________________', 0, 1, 'L');
        $pdf->Ln(10);
        $pdf->Cell(0, 10, 'Начальник ОТК ________________', 0, 1, 'L');

        // Output file
        $filename = 'report_' . time() . '.pdf';
        ob_end_clean(); // Clear output buffer
        $pdf->Output($filename, 'D');
        exit;

    } else {
        throw new Exception("Неверный формат отчета");
    }

} catch (Exception $e) {
    error_log("Ошибка генерации отчета: " . $e->getMessage());
    ob_end_clean();
    die("Ошибка генерации отчета: " . $e->getMessage());
}
?>