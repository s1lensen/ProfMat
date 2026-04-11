<?php
require __DIR__ . '/src/includes/db.php';
session_start();

// Проверка админа
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] !== 2) {
    die('🔒 Доступ запрещён');
}

$message = '';
$messageType = '';

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_import'])) {
    $subjectId = (int)$_POST['subject_id'];
    $variantNumber = (int)$_POST['variant_number'];
    $year = (int)$_POST['year'];
    $timeLimit = (int)$_POST['time_limit'];
    $variantName = trim($_POST['variant_name']);
    $basePath = trim($_POST['base_path']);
    
    // Парсим ответы из строки "1:76, 2:-12, 3:52π" в массив
    $answerKey = [];
    if (!empty($_POST['answer_key'])) {
        $pairs = explode(',', $_POST['answer_key']);
        foreach ($pairs as $pair) {
            $parts = explode(':', trim($pair));
            if (count($parts) == 2) {
                $num = (int)trim($parts[0]);
                $ans = trim($parts[1]);
                $answerKey[$num] = $ans;
            }
        }
    }

    try {
        $pdo->beginTransaction();
        
        // 1. Создаём вариант
        $stmt = $pdo->prepare("
            INSERT INTO variants (subject_id, variant_number, year, name, time_limit, is_public) 
            VALUES (?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([$subjectId, $variantNumber, $year, $variantName ?: "Вариант №$variantNumber", $timeLimit]);
        $variantId = $pdo->lastInsertId();
        
        // 2. Сканируем папку
        $fullPath = __DIR__ . $basePath . 'tasks/';
        if (!is_dir($fullPath)) {
            throw new Exception("Папка не найдена: $fullPath");
        }
        
        $files = scandir($fullPath);
        $taskFiles = array_diff($files, ['.', '..', '.DS_Store']);
        $loadedCount = 0;
        
        foreach ($taskFiles as $file) {
            if (!preg_match('/\.(png|jpg|jpeg|webp)$/i', $file)) continue;
            
            // Поддерживаем форматы: 1-1.png, task_1.png, var1_task1.png
            if (preg_match('/(?:^|[_-])(\d+)[_-](\d+)/', $file, $matches) || preg_match('/task[_-]?(\d+)/i', $file, $matches)) {
                $taskNumber = isset($matches[2]) ? (int)$matches[2] : (int)$matches[1];
                
                $taskImage = $basePath . 'tasks/' . $file;
                $solutionImage = null;
                
                // Ищем решение
                $solutionFile = str_replace(['.png', '.jpg', '.jpeg', '.webp'], '', $file) . '_solution.png';
                if (file_exists($fullPath . '../solutions/' . $solutionFile)) {
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
                $taskText = "Задание №$taskNumber";
                
                $stmt->execute([
                    $subjectId, $taskNumber, $fipiCode, $taskText, $taskImage,
                    $answerTypeId, $isExtended ? 1 : 0, $answer, $points, $solutionImage
                ]);
                
                $taskId = $pdo->lastInsertId();
                
                $stmt = $pdo->prepare("INSERT INTO variant_tasks (variant_id, task_id, task_order) VALUES (?, ?, ?)");
                $stmt->execute([$variantId, $taskId, $taskNumber]);
                
                $loadedCount++;
            }
        }
        
        $pdo->commit();
        $message = "✅ Вариант успешно загружен! ID: $variantId. Заданий: $loadedCount";
        $messageType = 'success';
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = '❌ Ошибка: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Получаем предметы
$subjects = $pdo->query("SELECT * FROM subjects ORDER BY id")->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Импорт варианта</title>
    <style>
        body { font-family: sans-serif; background: #f5f7fa; padding: 40px; }
        .container { max-width: 800px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        h1 { margin-top: 0; color: #1d1d1f; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: 600; margin-bottom: 8px; color: #6e6e73; }
        input, select, textarea { width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 1rem; box-sizing: border-box; }
        input:focus, select:focus, textarea:focus { outline: none; border-color: #667eea; }
        small { color: #999; font-size: 0.85rem; margin-top: 4px; display: block; }
        button { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; border: none; padding: 15px 30px; border-radius: 10px; font-size: 1.1rem; font-weight: 600; cursor: pointer; width: 100%; }
        button:hover { opacity: 0.9; }
        .alert { padding: 15px; border-radius: 10px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>

<div class="container">
    <h1>📥 Загрузка варианта ЕГЭ</h1>
    
    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>"><?= $message ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Предмет</label>
            <select name="subject_id" required>
                <?php foreach ($subjects as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= $s['icon'] ?> <?= $s['name'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="form-group">
                <label>Номер варианта</label>
                <input type="number" name="variant_number" placeholder="1" required>
            </div>
            <div class="form-group">
                <label>Год</label>
                <input type="number" name="year" value="2026" required>
            </div>
        </div>

        <div class="form-group">
            <label>Название (необязательно)</label>
            <input type="text" name="variant_name" placeholder="Например: Вариант от Ященко">
        </div>

        <div class="form-group">
            <label>Время (минуты)</label>
            <input type="number" name="time_limit" value="235" placeholder="235">
        </div>

        <div class="form-group">
            <label>Путь к папке на сервере</label>
            <input type="text" name="base_path" value="/public/uploads/variants/math/variant_1/" required>
            <small>Относительно корня сайта. В конце должен быть слэш <code>/</code></small>
        </div>

        <div class="form-group">
            <label>Ключ ответов (формат: номер:ответ, номер:ответ)</label>
            <textarea name="answer_key" rows="4" placeholder="1:76, 2:-12, 3:163.36, 4:0.36" required></textarea>
            <small>Заполняй только для заданий с кратким ответом (обычно 1-12). Разделяй запятой.</small>
        </div>

        <button type="submit" name="start_import">🚀 Загрузить вариант</button>
    </form>
</div>

</body>
</html>