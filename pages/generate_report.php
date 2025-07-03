<?php
ob_start(); // Start output buffering
session_start();
require_once __DIR__ . '/../db.php'; // Include database connection
require_once __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php'; // Include TCPDF library
require_once __DIR__ . '/../vendor/autoload.php'; // Include Composer autoloader

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Enable error reporting and logging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Check user authorization
if (!isset($_SESSION["user_id"])) {
    error_log("Ошибка: пользователь не авторизован.");
    ob_end_clean();
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
        default: return $status ?? 'Неизвестный';
    }
}

// Function to get Russian lifecycle stage
function getRussianLifecycleStage($stage) {
    switch ($stage) {
        case 'planning': return 'Планирование';
        case 'execution': return 'Выполнение';
        case 'monitoring': return 'Мониторинг';
        case 'closing': return 'Завершение';
        default: return $stage ?? 'Неизвестный';
    }
}

// Function to get Russian project scale
function getRussianScale($scale) {
    switch ($scale) {
        case 'small': return 'Малый';
        case 'medium': return 'Средний';
        case 'large': return 'Крупный';
        default: return $scale ?? 'Неизвестный';
    }
}

// Function to get Russian document type
function getRussianDocumentType($type) {
    switch ($type) {
        case 'photo': return 'Фотография';
        case 'document': return 'Документ';
        case 'report': return 'Отчёт';
        case 'drawing': return 'Чертёж';
        default: return $type ?? 'Неизвестный';
    }
}

// Function to format budget value
function formatBudget($value) {
    if (is_numeric($value)) {
        return number_format((float)$value, 2, ',', ' ') . ' BYN';
    }
    error_log("Некорректное значение бюджета: " . var_export($value, true));
    return '—';
}

// Function to format digitalization level
function formatDigitalization($value) {
    if (is_numeric($value)) {
        return number_format((float)$value, 2, ',', ' ') . '%';
    }
    return $value ?? '—';
}

// Function to format labor costs
function formatLaborCosts($value) {
    if (is_numeric($value)) {
        return number_format((float)$value, 2, ',', ' ') . ' чел.-часов';
    }
    return $value ?? '—';
}

// Function to format date
function formatDate($date) {
    if ($date && strtotime($date)) {
        return date('d.m.Y', strtotime($date));
    }
    return '—';
}

// Function to format rating
function formatRating($value) {
    if (is_numeric($value)) {
        return number_format((float)$value, 2, ',', ' ');
    }
    return '—';
}

// Динамически определяем базовый URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
define('BASE_URL', $protocol . $host . '/');

try {
    // Get POST data
    $project_id = intval($_POST['project_id'] ?? 0);
    $format = $_POST['format'] ?? 'pdf';
    $include_info = isset($_POST['include_info']);
    $include_documents = isset($_POST['include_documents']);
    $include_diagrams = isset($_POST['include_diagrams']);
    $include_photos = isset($_POST['include_photos']);

    if ($project_id < 1) {
        throw new Exception("Неверный ID проекта");
    }

    // Check user permissions
    $user_id = $_SESSION["user_id"];
    $role_id = $_SESSION["role_id"];
    $project_role = null;

    $sql = "SELECT project_role FROM project_participants WHERE project_id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception("Ошибка подготовки запроса (project_role): " . $conn->error);
    }
    $stmt->bind_param("ii", $project_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $project_role = $row["project_role"];
    }
    $stmt->close();

    if ($project_role === null && ($role_id == 1 || $role_id == 2)) {
        $project_role = ($role_id == 1) ? 'admin' : 'manager';
    }

    if (!in_array($project_role, ['admin', 'manager'])) {
        error_log("Ошибка: у пользователя нет прав для создания отчёта (project_role: " . ($project_role ?? 'не определена') . ").");
        throw new Exception("У вас нет прав для создания отчёта.");
    }

    // Fetch project data
    $project = getProjectDetails($conn, $project_id);
    if (!$project) {
        throw new Exception("Проект не найден");
    }

    // Fetch project rating
    $rating = getProjectRating($conn, $project_id);

    // Log budget values for debugging
    error_log("planned_budget: " . var_export($project['planned_budget'] ?? 'null', true));
    error_log("actual_budget: " . var_export($project['actual_budget'] ?? 'null', true));
    error_log("rating: " . var_export($rating ?? 'null', true));

    // Fetch tasks and documents
    $tasks = getProjectTasks($conn, $project_id);
    $documents = [];
    
    // Get project documents
    $project_docs = getProjectDocuments($conn, $project_id);
    foreach ($project_docs as $doc) {
        $documents[] = $doc;
    }

    // Get task documents
    foreach ($tasks as $task) {
        $task_docs = getTaskDocuments($conn, $task["task_id"]);
        foreach ($task_docs as $doc) {
            $documents[] = $doc;
        }
    }

    // Filter photos
    $photos = array_filter($documents, function($doc) {
        return $doc["document_type"] === 'photo';
    });

    // Define logo path
    $logoPath = __DIR__ . '/../images/doc.png';
    if (!file_exists($logoPath)) {
        throw new Exception("Файл логотипа не найден: " . $logoPath);
    }

    // Report title
    $report_title = "Отчет по проекту: " . ($project['short_name'] ?? 'Проект #' . $project_id);

    if ($format === 'docx') {
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

        // Add content
        if ($include_info) {
            $section->addText('Основная информация о проекте', ['name' => 'Arial', 'size' => 12, 'bold' => true]);
            $table = $section->addTable(['borderSize' => 1, 'borderColor' => '000000', 'cellMargin' => 80]);
            $table->addRow();
            $table->addCell(4000)->addText('Поле', ['name' => 'Arial', 'size' => 12, 'bold' => true]);
            $table->addCell(6000)->addText('Значение', ['name' => 'Arial', 'size' => 12, 'bold' => true]);
            $info = [
                'Название' => $project['short_name'] ?? '—',
                'Описание' => $project['description'] ?? '—',
                'Плановая дата начала' => formatDate($project['planned_start_date']),
                'Фактическая дата начала' => formatDate($project['actual_start_date']),
                'Плановая дата завершения' => formatDate($project['planned_end_date']),
                'Фактическая дата завершения' => formatDate($project['actual_end_date']),
                'Плановый бюджет' => formatBudget($project['planned_budget'] ?? 0),
                'Фактический бюджет' => formatBudget($project['actual_budget'] ?? 0),
                'Уровень цифровизации (план/факт)' => formatDigitalization($project['planned_digitalization_level']) . ' / ' . formatDigitalization($project['actual_digitalization_level']),
                'Затраты на труд (план/факт)' => formatLaborCosts($project['planned_labor_costs']) . ' / ' . formatLaborCosts($project['actual_labor_costs']),
                'Статус' => getRussianStatus($project['status']),
                'Этап жизненного цикла' => getRussianLifecycleStage($project['lifecycle_stage']),
                'Масштаб' => getRussianScale($project['scale']),
                'Рейтинг проекта' => formatRating($rating),
                'Устав (файл)' => ''
            ];
            foreach ($info as $label => $value) {
                $table->addRow();
                $table->addCell(4000)->addText($label, ['name' => 'Arial', 'size' => 12]);
                if ($label === 'Устав (файл)') {
                    $link = $project['charter_file_path'] ? BASE_URL . ltrim($project['charter_file_path'], '/') : '';
                    $cell = $table->addCell(6000);
                    if ($link) {
                        $path_parts = pathinfo($link);
                        $encoded_filename = rawurlencode($path_parts['basename']);
                        $encoded_link = $path_parts['dirname'] . '/' . $encoded_filename;
                        $cell->addLink($encoded_link, 'Скачать', ['name' => 'Arial', 'size' => 12, 'color' => '0000FF']);
                    } else {
                        $cell->addText('—', ['name' => 'Arial', 'size' => 12]);
                    }
                } else {
                    $table->addCell(6000)->addText($value, ['name' => 'Arial', 'size' => 12]);
                }
            }
            $section->addTextBreak(1);
        }

        if ($include_documents) {
            $section->addText('Документы проекта', ['name' => 'Arial', 'size' => 12, 'bold' => true]);
            if (empty($documents)) {
                $section->addText('Данные отсутствуют.', ['name' => 'Arial', 'size' => 12]);
            } else {
                $table = $section->addTable(['borderSize' => 1, 'borderColor' => '000000', 'cellMargin' => 80]);
                $table->addRow();
                $table->addCell(3000)->addText('Источник', ['name' => 'Arial', 'size' => 12, 'bold' => true]);
                $table->addCell(4000)->addText('Имя файла', ['name' => 'Arial', 'size' => 12, 'bold' => true]);
                $table->addCell(3000)->addText('Тип', ['name' => 'Arial', 'size' => 12, 'bold' => true]);
                foreach ($documents as $doc) {
                    $table->addRow();
                    $source = $doc['task_id'] ? "Задача #" . $doc['task_id'] : "Проект";
                    $table->addCell(3000)->addText($source, ['name' => 'Arial', 'size' => 12]);
                    $link = BASE_URL . ltrim($doc['file_path'], '/');
                    $path_parts = pathinfo($link);
                    $encoded_filename = rawurlencode($path_parts['basename']);
                    $encoded_link = $path_parts['dirname'] . '/' . $encoded_filename;
                    $table->addCell(4000)->addLink($encoded_link, $doc['file_name'], ['name' => 'Arial', 'size' => 12, 'color' => '0000FF']);
                    $table->addCell(3000)->addText(getRussianDocumentType($doc['document_type']), ['name' => 'Arial', 'size' => 12]);
                }
            }
            $section->addTextBreak(1);
        }

        if ($include_photos) {
            $section->addText('Фотографии проекта', ['name' => 'Arial', 'size' => 12, 'bold' => true]);
            if (empty($photos)) {
                $section->addText('Данные отсутствуют.', ['name' => 'Arial', 'size' => 12]);
            } else {
                $table = $section->addTable(['borderSize' => 1, 'borderColor' => '000000', 'cellMargin' => 80]);
                $table->addRow();
                $table->addCell(3000)->addText('Источник', ['name' => 'Arial', 'size' => 12, 'bold' => true]);
                $table->addCell(7000)->addText('Имя файла', ['name' => 'Arial', 'size' => 12, 'bold' => true]);
                foreach ($photos as $photo) {
                    $table->addRow();
                    $source = $photo['task_id'] ? "Задача #" . $photo['task_id'] : "Проект";
                    $table->addCell(3000)->addText($source, ['name' => 'Arial', 'size' => 12]);
                    $link = BASE_URL . ltrim($photo['file_path'], '/');
                    $path_parts = pathinfo($link);
                    $encoded_filename = rawurlencode($path_parts['basename']);
                    $encoded_link = $path_parts['dirname'] . '/' . $encoded_filename;
                    $table->addCell(7000)->addLink($encoded_link, $photo['file_name'], ['name' => 'Arial', 'size' => 12, 'color' => '0000FF']);
                }
            }
            $section->addTextBreak(1);
        }

        // Add signatures
        $section->addTextBreak(2);
        $section->addText('Прораб(мастер) СУ-246 ОАО «МАПИД» РБ ________________', ['name' => 'Arial', 'size' => 12], ['alignment' => 'left']);
        $section->addTextBreak(1);
        $section->addText('Начальник ОТК ________________', ['name' => 'Arial', 'size' => 12], ['alignment' => 'left']);

        // Save file
        $filename = 'report_' . $project_id . '_' . time() . '.docx';
        ob_end_clean(); // Очищаем буфер перед сохранением
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        $writer = WordIOFactory::createWriter($phpWord, 'Word2007');
        $writer->save('php://output');
        ob_end_flush();
        exit;

    } elseif ($format === 'xlsx') {
        // Excel report
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $row = 1;

        // Styling
        $headerStyle = [
            'font' => ['bold' => true, 'size' => 12],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE6F0FA']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ];
        $cellStyle = [
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ];

        // Add company header
        $sheet->setCellValue("A$row", 'СУ-246 ОАО «МАПИД»');
        $sheet->getStyle("A$row")->applyFromArray(['font' => ['bold' => true, 'size' => 14]]);
        $row++;
        $sheet->setCellValue("A$row", '220075, г. Минск, ул. Селицкая, 31');
        $row++;
        $sheet->setCellValue("A$row", 'Тел. (+37517) 373-27-92, факс (+37517) 397-51-42');
        $row++;
        $sheet->setCellValue("A$row", 'E-mail: su246mapid@mail.ru');
        $row++;
        $sheet->setCellValue("A$row", 'Документ №_________');
        $row += 2;

        // Add report title
        $sheet->setCellValue("A$row", $report_title);
        $sheet->getStyle("A$row")->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);
        $row += 2;

        if ($include_info) {
            $sheet->setCellValue("A$row", 'Основная информация о проекте');
            $sheet->getStyle("A$row")->applyFromArray($headerStyle);
            $sheet->mergeCells("A$row:B$row");
            $row++;
            $sheet->setCellValue("A$row", 'Поле');
            $sheet->setCellValue("B$row", 'Значение');
            $sheet->getStyle("A$row:B$row")->applyFromArray($headerStyle);
            $row++;
            $info = [
                'Название' => $project['short_name'] ?? '—',
                'Описание' => $project['description'] ?? '—',
                'Плановая дата начала' => formatDate($project['planned_start_date']),
                'Фактическая дата начала' => formatDate($project['actual_start_date']),
                'Плановая дата завершения' => formatDate($project['planned_end_date']),
                'Фактическая дата завершения' => formatDate($project['actual_end_date']),
                'Плановый бюджет' => formatBudget($project['planned_budget'] ?? 0),
                'Фактический бюджет' => formatBudget($project['actual_budget'] ?? 0),
                'Уровень цифровизации (план/факт)' => formatDigitalization($project['planned_digitalization_level']) . ' / ' . formatDigitalization($project['actual_digitalization_level']),
                'Затраты на труд (план/факт)' => formatLaborCosts($project['planned_labor_costs']) . ' / ' . formatLaborCosts($project['actual_labor_costs']),
                'Статус' => getRussianStatus($project['status']),
                'Этап жизненного цикла' => getRussianLifecycleStage($project['lifecycle_stage']),
                'Масштаб' => getRussianScale($project['scale']),
                'Рейтинг проекта' => formatRating($rating),
                'Устав (файл)' => $project['charter_file_path'] ? 'Скачать' : '—'
            ];
            foreach ($info as $label => $value) {
                $sheet->setCellValue("A$row", $label);
                $sheet->setCellValue("B$row", $value);
                if ($label === 'Устав (файл)' && $project['charter_file_path']) {
                    $link = BASE_URL . ltrim($project['charter_file_path'], '/');
                    $path_parts = pathinfo($link);
                    $encoded_filename = rawurlencode($path_parts['basename']);
                    $encoded_link = $path_parts['dirname'] . '/' . $encoded_filename;
                    $sheet->getCell("B$row")->getHyperlink()->setUrl($encoded_link);
                }
                $sheet->getStyle("A$row:B$row")->applyFromArray($cellStyle);
                $row++;
            }
            $row++;
        }

        if ($include_documents) {
            $sheet->setCellValue("A$row", 'Документы проекта');
            $sheet->getStyle("A$row")->applyFromArray($headerStyle);
            $sheet->mergeCells("A$row:C$row");
            $row++;
            if (empty($documents)) {
                $sheet->setCellValue("A$row", 'Данные отсутствуют.');
                $sheet->getStyle("A$row")->applyFromArray($cellStyle);
                $row++;
            } else {
                $sheet->setCellValue("A$row", 'Источник');
                $sheet->setCellValue("B$row", 'Имя файла');
                $sheet->setCellValue("C$row", 'Тип');
                $sheet->getStyle("A$row:C$row")->applyFromArray($headerStyle);
                $row++;
                foreach ($documents as $doc) {
                    $source = $doc['task_id'] ? "Задача #" . $doc['task_id'] : "Проект";
                    $sheet->setCellValue("A$row", $source);
                    $link = BASE_URL . ltrim($doc['file_path'], '/');
                    $path_parts = pathinfo($link);
                    $encoded_filename = rawurlencode($path_parts['basename']);
                    $encoded_link = $path_parts['dirname'] . '/' . $encoded_filename;
                    $sheet->setCellValue("B$row", $doc['file_name']);
                    $sheet->getCell("B$row")->getHyperlink()->setUrl($encoded_link);
                    $sheet->setCellValue("C$row", getRussianDocumentType($doc['document_type']));
                    $sheet->getStyle("A$row:C$row")->applyFromArray($cellStyle);
                    $row++;
                }
            }
            $row++;
        }

        if ($include_diagrams) {
            $sheet->setCellValue("A$row", 'Диаграмма бюджета');
            $sheet->getStyle("A$row")->applyFromArray($headerStyle);
            $sheet->mergeCells("A$row:B$row");
            $row++;
            $sheet->setCellValue("A$row", 'Плановый бюджет');
            $sheet->setCellValue("B$row", formatBudget($project['planned_budget'] ?? 0));
            $sheet->getStyle("A$row:B$row")->applyFromArray($cellStyle);
            $row++;
            $sheet->setCellValue("A$row", 'Фактический бюджет');
            $sheet->setCellValue("B$row", formatBudget($project['actual_budget'] ?? 0));
            $sheet->getStyle("A$row:B$row")->applyFromArray($cellStyle);
            $row++;
            $row++;
        }

        if ($include_photos) {
            $sheet->setCellValue("A$row", 'Фотографии проекта');
            $sheet->getStyle("A$row")->applyFromArray($headerStyle);
            $sheet->mergeCells("A$row:B$row");
            $row++;
            if (empty($photos)) {
                $sheet->setCellValue("A$row", 'Данные отсутствуют.');
                $sheet->getStyle("A$row")->applyFromArray($cellStyle);
                $row++;
            } else {
                $sheet->setCellValue("A$row", 'Источник');
                $sheet->setCellValue("B$row", 'Имя файла');
                $sheet->getStyle("A$row:B$row")->applyFromArray($headerStyle);
                $row++;
                foreach ($photos as $photo) {
                    $source = $photo['task_id'] ? "Задача #" . $photo['task_id'] : "Проект";
                    $sheet->setCellValue("A$row", $source);
                    $link = BASE_URL . ltrim($photo['file_path'], '/');
                    $path_parts = pathinfo($link);
                    $encoded_filename = rawurlencode($path_parts['basename']);
                    $encoded_link = $path_parts['dirname'] . '/' . $encoded_filename;
                    $sheet->setCellValue("B$row", $photo['file_name']);
                    $sheet->getCell("B$row")->getHyperlink()->setUrl($encoded_link);
                    $sheet->getStyle("A$row:B$row")->applyFromArray($cellStyle);
                    $row++;
                }
            }
            $row++;
        }

        // Add signatures
        $sheet->setCellValue("A$row", 'Прораб(мастер) СУ-246 ОАО «МАПИД» РБ ________________');
        $row += 2;
        $sheet->setCellValue("A$row", 'Начальник ОТК ________________');

        // Auto-size columns
        foreach (range('A', 'C') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Save file
        $filename = 'report_' . $project_id . '_' . time() . '.xlsx';
        ob_end_clean(); // Очищаем буфер перед сохранением
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        $writer = SpreadsheetIOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        ob_end_flush();
        exit;

    } elseif ($format === 'pdf') {
        // PDF report
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Project Manager');
        $pdf->SetAuthor('Admin Panel');
        $pdf->SetTitle($report_title);
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

        // Add content
        if ($include_info) {
            $pdf->SetFont('dejavusans', 'B', 12);
            $pdf->Cell(0, 10, 'Основная информация о проекте', 0, 1);
            $pdf->SetFont('dejavusans', '', 12);
            $html = '<table border="1" cellpadding="5">
                <tr><th width="50%">Поле</th><th width="50%">Значение</th></tr>';
            $info = [
                'Название' => htmlspecialchars($project['short_name'] ?? '—'),
                'Описание' => htmlspecialchars($project['description'] ?? '—'),
                'Плановая дата начала' => formatDate($project['planned_start_date']),
                'Фактическая дата начала' => formatDate($project['actual_start_date']),
                'Плановая дата завершения' => formatDate($project['planned_end_date']),
                'Фактическая дата завершения' => formatDate($project['actual_end_date']),
                'Плановый бюджет' => formatBudget($project['planned_budget'] ?? 0),
                'Фактический бюджет' => formatBudget($project['actual_budget'] ?? 0),
                'Уровень цифровизации (план/факт)' => formatDigitalization($project['planned_digitalization_level']) . ' / ' . formatDigitalization($project['actual_digitalization_level']),
                'Затраты на труд (план/факт)' => formatLaborCosts($project['planned_labor_costs']) . ' / ' . formatLaborCosts($project['actual_labor_costs']),
                'Статус' => htmlspecialchars(getRussianStatus($project['status'])),
                'Этап жизненного цикла' => htmlspecialchars(getRussianLifecycleStage($project['lifecycle_stage'])),
                'Масштаб' => htmlspecialchars(getRussianScale($project['scale'])),
                'Рейтинг проекта' => formatRating($rating),
                'Устав (файл)' => $project['charter_file_path'] ? '<a href="' . htmlspecialchars(BASE_URL . ltrim($project['charter_file_path'], '/')) . '">Скачать</a>' : '—'
            ];
            foreach ($info as $key => $value) {
                $html .= "<tr><td>$key</td><td>$value</td></tr>";
            }
            $html .= '</table>';
            $pdf->writeHTML($html, true, false, true, false, '');
            $pdf->Ln(5);
        }

        if ($include_documents) {
            $pdf->SetFont('dejavusans', 'B', 12);
            $pdf->Cell(0, 10, 'Документы проекта', 0, 1);
            $pdf->SetFont('dejavusans', '', 10);
            if (empty($documents)) {
                $pdf->Cell(0, 10, 'Данные отсутствуют.', 0, 1);
            } else {
                $html = '<table border="1" cellpadding="5">
                    <tr><th width="20%">Источник</th><th width="50%">Имя файла</th><th width="30%">Тип</th></tr>';
                foreach ($documents as $item) {
                    $source = $item['task_id'] ? "Задача #" . $item['task_id'] : "Проект";
                    $link = BASE_URL . ltrim($item['file_path'], '/');
                    $path_parts = pathinfo($link);
                    $encoded_filename = rawurlencode($path_parts['basename']);
                    $encoded_link = $path_parts['dirname'] . '/' . $encoded_filename;
                    $html .= '<tr><td>' . htmlspecialchars($source) . '</td>'
                        . '<td><a href="' . htmlspecialchars($encoded_link) . '">' . htmlspecialchars($item['file_name']) . '</a></td>'
                        . '<td>' . htmlspecialchars(getRussianDocumentType($item['document_type'])) . '</td></tr>';
                }
                $html .= '</table>';
                $pdf->writeHTML($html, true, false, true, false, '');
            }
            $pdf->Ln(5);
        }

        if ($include_photos) {
            $pdf->SetFont('dejavusans', 'B', 12);
            $pdf->Cell(0, 10, 'Фотографии проекта', 0, 1);
            $pdf->SetFont('dejavusans', '', 10);
            if (empty($photos)) {
                $pdf->Cell(0, 10, 'Данные отсутствуют.', 0, 1);
            } else {
                $html = '<table border="1" cellpadding="5">
                    <tr><th width="30%">Источник</th><th width="70%">Имя файла</th></tr>';
                foreach ($photos as $item) {
                    $source = $item['task_id'] ? "Задача #" . $item['task_id'] : "Проект";
                    $link = BASE_URL . ltrim($item['file_path'], '/');
                    $path_parts = pathinfo($link);
                    $encoded_filename = rawurlencode($path_parts['basename']);
                    $encoded_link = $path_parts['dirname'] . '/' . $encoded_filename;
                    $html .= '<tr><td>' . htmlspecialchars($source) . '</td>'
                        . '<td><a href="' . htmlspecialchars($encoded_link) . '">' . htmlspecialchars($item['file_name']) . '</a></td></tr>';
                }
                $html .= '</table>';
                $pdf->writeHTML($html, true, false, true, false, '');
            }
            $pdf->Ln(5);
        }

        // Add budget diagram at the end
        if ($include_diagrams) {
            $pdf->AddPage(); // New page for diagram
            $pdf->SetFont('dejavusans', 'B', 12);
            $pdf->Cell(0, 10, 'Диаграмма бюджета', 0, 1);
            $pdf->SetFont('dejavusans', '', 12);
            $planned = is_numeric($project['planned_budget']) ? (float)$project['planned_budget'] : 0;
            $actual = is_numeric($project['actual_budget']) ? (float)$project['actual_budget'] : 0;
            $total = $planned + $actual;

            if ($total > 0) {
                $planned_angle = ($planned / $total) * 360;
                $actual_angle = ($actual / $total) * 360;

                $pdf->SetFillColor(24, 162, 235); // Синий для планового бюджета
                $pdf->PieSector(50, 80, 25, 0, $planned_angle, 'FD');
                $pdf->SetFillColor(255, 99, 132); // Розовый для фактического бюджета
                $pdf->PieSector(50, 80, 25, $planned_angle, 360, 'FD');

                // Добавляем легенду
                $pdf->SetXY(100, 50);
                $pdf->SetFillColor(24, 162, 235);
                $pdf->Cell(6, 6, '', 1, 0, 'L', 1); // Прямоугольник синего цвета
                $pdf->SetTextColor(0, 0, 0);
                $pdf->Cell(0, 6, ' Плановый бюджет: ' . formatBudget($planned), 0, 1);

                $pdf->SetXY(100, 60);
                $pdf->SetFillColor(255, 99, 132);
                $pdf->Cell(6, 6, '', 1, 0, 'L', 1); // Прямоугольник розового цвета
                $pdf->SetTextColor(0, 0, 0);
                $pdf->Cell(0, 6, ' Фактический бюджет: ' . formatBudget($actual), 0, 1);
            } else {
                $pdf->Write(0, "Данные для диаграммы отсутствуют.\n");
            }
            $pdf->Ln(10);
        }

        // Add signatures
        $pdf->Ln(50);
        $pdf->Cell(0, 10, 'Прораб(мастер) СУ-246 ОАО «МАПИД» РБ ________________', 0, 1, 'L');
        $pdf->Ln(10);
        $pdf->Cell(0, 10, 'Начальник ОТК ________________', 0, 1, 'L');

        // Output file
        $filename = 'report_' . $project_id . '_' . time() . '.pdf';
        ob_end_clean();
        $pdf->Output($filename, 'D');
        exit;

    } else {
        throw new Exception("Неверный формат отчета");
    }

} catch (\Exception $e) {
    error_log("Ошибка генерации отчета: " . $e->getMessage());
    ob_end_clean();
    die("Ошибка генерации отчета: " . $e->getMessage());
}
?>