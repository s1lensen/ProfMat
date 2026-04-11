<?php
require __DIR__ . '/src/includes/db.php';

// ==========================================
// НАСТРОЙКИ
// ==========================================
$subjectId = 1;          // 1=Математика, 2=Информатика, 3=Русский
$variantNumber = 1;      // Номер варианта
$year = 2026;            // Год
$timeLimit = 235;        // 3ч 55мин = 235 минут
$variantName = 'Вариант №1 (Ященко)';
$basePath = '/public/uploads/variants/math/variant_1/';

// Ключ ответов (заполни свои ответы!)
$answerKey = [
    1 => '76',
    2 => '-12',
    3 => '163.36',  // 52π ≈ 163.36
    4 => '0.36',
    5 => '2',
    6 => '4',
    7 => '15',
    8 => '90',
    9 => '0.625',
    10 => '3',
    11 => '5',
    12 => '18'
];

echo "<h1>🚀 Загрузка варианта...</h1>";
echo "<p>Предмет ID: $subjectId | Вариант: $variantNumber | Путь: $basePath</p>";
echo "<hr>";

try {
    $pdo->beginTransaction();
    
    // 1. Создаём вариант
    $stmt = $pdo->prepare("
        INSERT INTO variants (subject_id, variant_number, year, name, time_limit, is_public) 
        VALUES (?, ?, ?, ?, ?, 1)
    ");
    $stmt->execute([$subjectId, $variantNumber, $year, $variantName, $timeLimit]);
    $variantId = $pdo->lastInsertId();
    
    echo "<p style='color: green;'>✅ Вариант создан (ID: <strong>$variantId</strong>)</p>";
    
    // 2. Сканируем папку с заданиями
    $tasksDir = __DIR__ . $basePath . 'tasks/';
    
    if (!is_dir($tasksDir)) {
        throw new Exception("Папка не найдена: $tasksDir");
    }
    
    $files = scandir($tasksDir);
    $taskFiles = array_diff($files, ['.', '..', '.DS_Store']);
    
    $loadedCount = 0;
    
    foreach ($taskFiles as $file) {
        if (!preg_match('/\.(png|jpg|jpeg|webp)$/i', $file)) continue;
        
        if (preg_match('/^(\d+)-(\d+)\./', $file, $matches)) {
            $part = (int)$matches[1];
            $taskNumInPart = (int)$matches[2];
            $taskNumber = $taskNumInPart;
            
            $taskImage = $basePath . 'tasks/' . $file;
            $solutionImage = null;
            
            $solutionFile = str_replace(['.png', '.jpg', '.jpeg', '.webp'], '', $file) . '_solution.png';
            if (file_exists($tasksDir . '../solutions/' . $solutionFile)) {
                $solutionImage = $basePath . 'solutions/' . $solutionFile;
            }
            
            $isExtended = ($taskNumber > 12);
            $answerTypeId = $isExtended ? 2 : 1;
            $answer = isset($answerKey[$taskNumber]) ? $answerKey[$taskNumber] : '';
            $points = $isExtended ? 2 : 1;
            
            $stmt = $pdo->prepare("
                INSERT INTO tasks (
                    subject_id, task_number, fipi_code, task_text, task_image, 
                    answer_type_id, is_extended, answer, points, solution_image
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $fipiCode = "VAR{$variantNumber}_TASK{$taskNumber}";
            $taskText = "Задание №$taskNumber из варианта $variantNumber";
            
            $stmt->execute([
                $subjectId,
                $taskNumber,
                $fipiCode,
                $taskText,
                $taskImage,
                $answerTypeId,
                $isExtended ? 1 : 0,
                $answer,
                $points,
                $solutionImage
            ]);
            
            $taskId = $pdo->lastInsertId();
            
            $stmt = $pdo->prepare("
                INSERT INTO variant_tasks (variant_id, task_id, task_order) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$variantId, $taskId, $taskNumber]);
            
            $loadedCount++;
            echo "<p>✅ Задание #$taskNumber загружено (ID: $taskId)</p>";
        }
    }
    
    $pdo->commit();
    
    echo "<hr>";
    echo "<h2 style='color: green;'>🎉 ГОТОВО!</h2>";
    echo "<p>Загружено заданий: <strong>$loadedCount</strong></p>";
    echo "<p>ID варианта: <strong>$variantId</strong></p>";
    echo "<p><a href='/ege-variant-view.php?id=$variantId' target='_blank' style='background: #667eea; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 8px; display: inline-block;'>👉 Посмотреть вариант на сайте</a></p>";
    
} catch (PDOException $e) {
    $pdo->rollBack();
    echo "<h2 style='color: red;'>❌ Ошибка базы данных:</h2>";
    echo "<pre>" . $e->getMessage() . "</pre>";
} catch (Exception $e) {
    echo "<h2 style='color: red;'>❌ Ошибка:</h2>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}
?>