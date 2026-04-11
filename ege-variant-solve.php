<?php
require __DIR__ . '/db.php';
require __DIR__ . '/header.php';

// session_start() НЕ НУЖЕН - уже есть в header.php!

$variantId = $_GET['id'] ?? null;

if (!$variantId) {
    die('<div style="text-align:center;padding:100px;font-family:sans-serif;">
        <h1>❌ Ошибка</h1>
        <p>Не указан номер варианта</p>
        <a href="/ege-variants.php">← К вариантам</a>
    </div>');
}

if (!isset($_SESSION['user_id'])) {
    die('<div style="text-align:center;padding:100px;font-family:sans-serif;">
        <h1>🔒 Вход required</h1>
        <p>Войдите в аккаунт для решения варианта</p>
        <a href="/login.php" style="display:inline-block;padding:12px 24px;background:#667eea;color:#fff;text-decoration:none;border-radius:8px;">Войти</a>
    </div>');
}

// ============================================
// ЗАПУСКАЕМ ТАЙМЕР ДЛЯ ЭТОГО ВАРИАНТА
// ============================================
$sessionKey = 'variant_start_time_' . $variantId;
if (!isset($_SESSION[$sessionKey])) {
    $_SESSION[$sessionKey] = time();
}
// ============================================

$stmt = $pdo->prepare("SELECT * FROM variants WHERE id = ? AND is_public = 1");
$stmt->execute([$variantId]);
$variant = $stmt->fetch();

if (!$variant) {
    die('<div style="text-align:center;padding:100px;font-family:sans-serif;">
        <h1>❌ Вариант не найден</h1>
        <a href="/ege-variants.php">← К вариантам</a>
    </div>');
}

$stmt = $pdo->prepare("
    SELECT t.*, vt.task_order
    FROM tasks t
    JOIN variant_tasks vt ON t.id = vt.task_id
    WHERE vt.variant_id = ?
    ORDER BY vt.task_order ASC
");
$stmt->execute([$variantId]);
$tasks = $stmt->fetchAll();

// Обработка завершения варианта
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finish_variant'])) {
    // Сохраняем баллы за 2 часть
    if (!empty($_POST['self_score'])) {
        foreach ($_POST['self_score'] as $taskId => $score) {
            $stmt = $pdo->prepare("
                INSERT INTO user_answers (user_id, task_id, user_answer, is_correct, points_earned, needs_review)
                VALUES (?, ?, 'self-assessment', 1, ?, 1)
                ON DUPLICATE KEY UPDATE points_earned = ?, answered_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$_SESSION['user_id'], $taskId, $score, $score]);
        }
    }
    
    // ============================================
    // ВЫЧИСЛЯЕМ ЗАТРАЧЕННОЕ ВРЕМЯ
    // ============================================
    $timeSpent = 0;
    if (isset($_SESSION[$sessionKey])) {
        $timeSpent = time() - $_SESSION[$sessionKey];
        unset($_SESSION[$sessionKey]); // Сбрасываем таймер
    }
    
    // Считаем первичный балл
    $primaryScore = 0;
    $totalMax = 0;
    foreach ($tasks as $task) {
        $totalMax += $task['points'];
        $stmt = $pdo->prepare("SELECT points_earned FROM user_answers WHERE user_id = ? AND task_id = ?");
        $stmt->execute([$_SESSION['user_id'], $task['id']]);
        $answer = $stmt->fetch();
        if ($answer) {
            $primaryScore += (int)$answer['points_earned'];
        }
    }
    
    // Сохраняем результат в БД
    $stmt = $pdo->prepare("
        INSERT INTO user_variant_results (user_id, variant_id, score, max_score, completed_at, time_spent)
        VALUES (?, ?, ?, ?, NOW(), ?)
        ON DUPLICATE KEY UPDATE 
            score = VALUES(score),
            max_score = VALUES(max_score),
            completed_at = VALUES(completed_at),
            time_spent = VALUES(time_spent)
    ");
    $stmt->execute([$_SESSION['user_id'], $variantId, $primaryScore, $totalMax, $timeSpent]);
    
    // Передаём время на страницу результатов
    header("Location: ege-variant-results.php?id=$variantId&time_spent=$timeSpent");
    exit;
}

// Получаем сохранённые ответы
$savedAnswers = [];
$stmt = $pdo->prepare("SELECT task_id, points_earned, is_correct, user_answer FROM user_answers WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
while ($row = $stmt->fetch()) {
    $savedAnswers[$row['task_id']] = $row;
}

// Функция для исправления пути к картинкам
function fixImagePath($path) {
    if (empty($path)) return '';
    if (strpos($path, '/public/') === 0) {
        return $path;
    }
    if (strpos($path, '/uploads/') === 0) {
        return '/public' . $path;
    }
    if (strpos($path, '/') !== 0) {
        return '/' . $path;
    }
    return $path;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Решение варианта №<?= $variant['variant_number'] ?> — ProfMat</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fa; padding-bottom: 100px; }
        
        .solve-header { position: sticky; top: 0; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 16px 24px; z-index: 100; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .solve-header-content { max-width: 1400px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; }
        .timer { font-size: 1.5rem; font-weight: 900; background: rgba(255,255,255,0.2); padding: 8px 16px; border-radius: 8px; font-variant-numeric: tabular-nums; }
        
        .solve-container { max-width: 1000px; margin: 40px auto; padding: 0 24px; }
        
        .task-card { background: #fff; border-radius: 16px; padding: 24px 28px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); margin-bottom: 24px; border: 1px solid #e2e8f0; }
        .task-card.part2 { background: linear-gradient(135deg, rgba(255, 193, 7, 0.03) 0%, rgba(255, 152, 0, 0.03) 100%); border: 2px solid rgba(255, 193, 7, 0.3); }
        
        .task-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .task-title { font-size: 1.15rem; font-weight: 700; color: #1d1d1f; }
        .task-badge { padding: 6px 12px; border-radius: 8px; font-size: 0.85rem; font-weight: 600; }
        .badge-part1 { background: rgba(76, 175, 80, 0.15); color: #4caf50; }
        .badge-part2 { background: rgba(255, 193, 7, 0.15); color: #ff9800; }
        
        .task-image-container { margin-bottom: 20px; text-align: center; background: #fafafa; padding: 12px; border-radius: 10px; border: 1px solid #e2e8f0; }
        .task-image { max-width: 100%; height: auto; max-height: 600px; border-radius: 6px; }
        
        .answer-form { margin-top: 20px; padding-top: 20px; border-top: 2px solid #f0f0f5; display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap; }
        .answer-input-group { flex: 1; min-width: 300px; }
        .answer-input-group label { display: block; font-weight: 600; margin-bottom: 8px; color: #6e6e73; font-size: 0.9rem; }
        .answer-input { width: 100%; padding: 14px 18px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 1rem; }
        .answer-input:focus { outline: none; border-color: #667eea; }
        .answer-input.success { border-color: #4caf50; background-color: #e8f5e9; }
        
        .btn-save { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; border: none; padding: 14px 28px; border-radius: 12px; font-weight: 600; cursor: pointer; }
        .btn-save:hover { opacity: 0.9; }
        .btn-save:disabled { opacity: 0.6; cursor: not-allowed; }
        
        .criteria-section { margin-top: 20px; padding: 20px; background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; }
        .criteria-image, .solution-image { max-width: 100%; height: auto; border-radius: 8px; margin: 16px 0; }
        
        .self-score-group { margin-top: 20px; padding-top: 20px; border-top: 2px solid #f0f0f5; }
        .self-score-group label { display: block; font-weight: 600; margin-bottom: 12px; color: #6e6e73; }
        .self-score-select { padding: 12px 20px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 1rem; font-weight: 600; cursor: pointer; }
        .self-score-select:focus { outline: none; border-color: #ff9800; }
        
        .btn-toggle { background: rgba(102, 126, 234, 0.1); color: #667eea; border: none; padding: 10px 16px; border-radius: 8px; font-weight: 600; cursor: pointer; margin-right: 8px; margin-bottom: 8px; }
        .btn-toggle:hover { background: rgba(102, 126, 234, 0.2); }
        
        /* ИСПРАВЛЕННАЯ КНОПКА ЗАВЕРШЕНИЯ */
        .btn-finish { 
            background: linear-gradient(135deg, #10b981 0%, #059669 100%); 
            color: #fff; 
            border: none; 
            padding: 20px 48px; 
            border-radius: 16px; 
            font-weight: 700; 
            font-size: 1.1rem; 
            cursor: pointer; 
            display: block; 
            margin: 40px auto 0; 
            box-shadow: 0 8px 24px rgba(16, 185, 129, 0.3); 
            width: 100%; 
            max-width: 500px;
            transition: all 0.3s ease;
        }

        .btn-finish:hover { 
            opacity: 1; 
            transform: translateY(-4px); 
            box-shadow: 0 12px 32px rgba(16, 185, 129, 0.4);
        }

        .btn-finish:active {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.3);
        }
        
        .result-message { margin-top: 12px; font-weight: 600; padding: 12px; border-radius: 8px; }
        .result-success { background: rgba(76, 175, 80, 0.1); color: #4caf50; }
        .result-error { background: rgba(244, 67, 54, 0.1); color: #f44336; }
    </style>
</head>
<body>
    <div class="solve-header">
        <div class="solve-header-content">
            <div>
                <strong style="font-size: 1.1rem;">Вариант №<?= $variant['variant_number'] ?></strong>
                <div style="font-size: 0.85rem; opacity: 0.9;"><?= htmlspecialchars($variant['subject_name']) ?></div>
            </div>
            <div class="timer" id="timer">
                <?= $variant['time_limit'] ? floor($variant['time_limit'] / 60) . ':' . str_pad($variant['time_limit'] % 60, 2, '0', STR_PAD_LEFT) : '∞' ?>
            </div>
            <a href="ege-variant-view.php?id=<?= $variant['id'] ?>" style="color: #fff; text-decoration: none; font-weight: 600;">✕ Завершить</a>
        </div>
    </div>

    <div class="solve-container">
        <form method="POST" id="variant-form">
            <?php foreach ($tasks as $index => $task):
                $isPart2 = $task['is_extended'];
                $taskImgSrc = fixImagePath($task['task_image']);
                $solutionImgSrc = fixImagePath($task['solution_image']);
                $criteriaImgSrc = fixImagePath($task['criteria_image']);
            ?>
                <div id="task-<?= $index + 1 ?>" class="task-card <?= $isPart2 ? 'part2' : '' ?>">
                    
                    <div class="task-header">
                        <h2 class="task-title">Задание №<?= $index + 1 ?></h2>
                        <span class="task-badge <?= $isPart2 ? 'badge-part2' : 'badge-part1' ?>">
                            <?= $isPart2 ? '📝 Часть 2' : '✅ Часть 1' ?> • Макс. <?= $task['points'] ?> балл.<?= $task['points'] == 1 ? '' : 'лов' ?>
                        </span>
                    </div>
                    
                    <div class="task-image-container">
                        <img src="<?= htmlspecialchars($taskImgSrc) ?>" alt="Задание <?= $index + 1 ?>" class="task-image" loading="lazy">
                    </div>
                    
                    <?php if (!$isPart2): ?>
                        <div class="answer-form">
                            <div class="answer-input-group">
                                <label>Ваш ответ:</label>
                                <input type="text" name="user_answer[<?= $task['id'] ?>]"
                                       class="answer-input"
                                       placeholder="<?= $task['answer_type_slug'] === 'number' ? 'Например: 72 или 72,5' : 'Введите ответ' ?>"
                                       value="<?= htmlspecialchars($savedAnswers[$task['id']]['user_answer'] ?? '') ?>"
                                       <?= $task['answer_type_slug'] === 'number' ? 'pattern="[0-9,.]*"' : '' ?>>
                            </div>
                            <button type="button" class="btn-save" onclick="checkAnswer(<?= $task['id'] ?>, this)">
                                ✓ Сохранить
                            </button>
                        </div>
                        <div id="result-<?= $task['id'] ?>" class="result-message" style="display:none;"></div>
                    <?php else: ?>
                        <div class="criteria-section">
                            <?php if ($task['criteria_image']): ?>
                                <button type="button" class="btn-toggle" onclick="document.getElementById('criteria-<?= $task['id'] ?>').style.display = document.getElementById('criteria-<?= $task['id'] ?>').style.display === 'none' ? 'block' : 'none'">
                                    📊 Критерии проверки
                                </button>
                                <div id="criteria-<?= $task['id'] ?>" style="display: none;">
                                    <img src="<?= htmlspecialchars($criteriaImgSrc) ?>" alt="Критерии" class="criteria-image">
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($task['solution_image']): ?>
                                <button type="button" class="btn-toggle" onclick="document.getElementById('solution-<?= $task['id'] ?>').style.display = document.getElementById('solution-<?= $task['id'] ?>').style.display === 'none' ? 'block' : 'none'">
                                    📖 Правильное решение
                                </button>
                                <div id="solution-<?= $task['id'] ?>" style="display: none;">
                                    <img src="<?= htmlspecialchars($solutionImgSrc) ?>" alt="Решение" class="solution-image">
                                </div>
                            <?php endif; ?>
                            
                            <div class="self-score-group">
                                <label>Проверь решение и выстави балл:</label>
                                <select name="self_score[<?= $task['id'] ?>]" class="self-score-select">
                                    <?php for ($i = $task['points']; $i >= 0; $i--): ?>
                                        <option value="<?= $i ?>" <?= (isset($savedAnswers[$task['id']]['points_earned']) && $savedAnswers[$task['id']]['points_earned'] == $i) ? 'selected' : '' ?>>
                                            <?= $i ?> балл.<?= $i == 1 ? '' : 'лов' ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                                <small style="display: block; margin-top: 8px; color: #999;">
                                    Максимум за это задание: <?= $task['points'] ?> балл.<?= $task['points'] == 1 ? '' : 'лов' ?>
                                </small>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            
            <!-- ИСПРАВЛЕННАЯ КНОПКА ЗАВЕРШЕНИЯ -->
            <button type="submit" name="finish_variant" class="btn-finish">
                <span style="display: flex; align-items: center; justify-content: center; gap: 12px;">
                    <span style="font-size: 1.3rem;">✅</span>
                    <span style="text-align: left;">
                        <div style="font-weight: 700; font-size: 1.1rem; line-height: 1.2;">Завершить вариант</div>
                        <div style="font-size: 0.85rem; opacity: 0.9; font-weight: 400; line-height: 1.2;">и показать результаты</div>
                    </span>
                </span>
            </button>
        </form>
    </div>

    <script>
    <?php if ($variant['time_limit']): ?>
    let timeLeft = <?= $variant['time_limit'] * 60 ?>;
    const timerEl = document.getElementById('timer');
    const timer = setInterval(() => {
        timeLeft--;
        if (timeLeft < 0) { clearInterval(timer); finishVariant(); return; }
        const h = Math.floor(timeLeft / 3600);
        const m = Math.floor((timeLeft % 3600) / 60);
        const s = timeLeft % 60;
        timerEl.textContent = `${h}:${m.toString().padStart(2,'0')}:${s.toString().padStart(2,'0')}`;
    }, 1000);
    <?php endif; ?>
    
    function checkAnswer(taskId, btn) {
        const input = btn.previousElementSibling.querySelector('input');
        const resultDiv = document.getElementById('result-' + taskId);
        const formData = new FormData();
        formData.append('task_id', taskId);
        formData.append('user_answer', input.value);
        
        btn.disabled = true;
        btn.textContent = '⏳...';
        
        fetch('/task-check.php', { method: 'POST', body: formData })
        .then(r => r.text())
        .then(() => {
            btn.disabled = false;
            btn.textContent = '✓ Проверено';
            input.classList.add('success');
            resultDiv.style.display = 'block';
            resultDiv.className = 'result-message result-success';
            resultDiv.textContent = '✅ Ответ сохранён!';
            setTimeout(() => { resultDiv.style.display = 'none'; }, 3000);
        })
        .catch(() => {
            btn.disabled = false;
            btn.textContent = '❌ Ошибка';
        });
    }
    
    function finishVariant() {
        if (confirm('Завершить вариант и перейти к результатам?')) {
            document.getElementById('variant-form').submit();
        }
    }
    </script>
</body>
</html>