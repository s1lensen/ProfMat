<?php
require __DIR__ . '/db.php';
require __DIR__ . '/header.php';

$taskId = $_GET['id'] ?? null;

if (!$taskId) {
    header('Location: task-list.php?subject=math');
    exit;
}

// Получаем задание
$stmt = $pdo->prepare("
    SELECT t.*, s.name as subject_name, s.slug as subject_slug, s.icon,
           at.name as answer_type_name, at.slug as answer_type_slug
    FROM tasks t
    JOIN subjects s ON t.subject_id = s.id
    LEFT JOIN answer_types at ON t.answer_type_id = at.id
    WHERE t.id = ?
");
$stmt->execute([$taskId]);
$task = $stmt->fetch();

if (!$task) {
    header('Location: task-list.php?subject=math');
    exit;
}

// Получаем предыдущее и следующее задание
$stmt = $pdo->prepare("
    SELECT id, task_number 
    FROM tasks 
    WHERE subject_id = ? AND task_number < ? 
    ORDER BY task_number DESC LIMIT 1
");
$stmt->execute([$task['subject_id'], $task['task_number']]);
$prevTask = $stmt->fetch();

$stmt = $pdo->prepare("
    SELECT id, task_number 
    FROM tasks 
    WHERE subject_id = ? AND task_number > ? 
    ORDER BY task_number ASC LIMIT 1
");
$stmt->execute([$task['subject_id'], $task['task_number']]);
$nextTask = $stmt->fetch();

// Получаем статус выполнения
$userAnswer = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM user_answers WHERE user_id = ? AND task_id = ?");
    $stmt->execute([$_SESSION['user_id'], $taskId]);
    $userAnswer = $stmt->fetch();
}

// Обработка проверки ответа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_answer'])) {
    if (isset($_SESSION['user_id'])) {
        $userAnswerText = trim($_POST['user_answer']);
        
        // Нормализация ответа
        $normalizedUser = str_replace(',', '.', $userAnswerText);
        $normalizedUser = str_replace(' ', '', $normalizedUser);
        $normalizedCorrect = str_replace(',', '.', $task['answer']);
        $normalizedCorrect = str_replace(' ', '', $normalizedCorrect);
        
        $isCorrect = false;
        if ($task['answer_type_slug'] === 'number' || $task['answer_type_slug'] === 'digits') {
            $isCorrect = (floatval($normalizedUser) == floatval($normalizedCorrect));
        } else {
            $isCorrect = (mb_strtolower($normalizedUser) == mb_strtolower($normalizedCorrect));
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO user_answers (user_id, task_id, user_answer, is_correct, points_earned, needs_review)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                user_answer = VALUES(user_answer),
                is_correct = VALUES(is_correct),
                points_earned = VALUES(points_earned),
                answered_at = CURRENT_TIMESTAMP
        ");
        
        $pointsEarned = $isCorrect ? $task['points'] : 0;
        $needsReview = $task['is_extended'] ? 1 : 0;
        
        $stmt->execute([
            $_SESSION['user_id'],
            $taskId,
            $userAnswerText,
            $isCorrect ? 1 : 0,
            $pointsEarned,
            $needsReview
        ]);
        
        $userAnswer = [
            'user_answer' => $userAnswerText,
            'is_correct' => $isCorrect ? 1 : 0,
            'points_earned' => $pointsEarned
        ];
        
        $checkResult = [
            'correct' => $isCorrect,
            'message' => $isCorrect ? '✅ Верно!' : '❌ Неверно',
            'correct_answer' => $isCorrect ? '' : $task['answer']
        ];
    }
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

$taskImgSrc = fixImagePath($task['task_image']);
$solutionImgSrc = fixImagePath($task['solution_image']);
?>

<section class="hero" style="padding: 60px 24px 40px; background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);">
    <div class="hero-content" style="flex-direction: column;">
        <div class="hero-text" style="max-width: 800px; text-align: center;">
            <a href="task-list.php?subject=<?= htmlspecialchars($task['subject_slug']) ?>" 
               style="color: rgba(255,255,255,0.7); text-decoration: none; font-weight: 600; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px; margin-bottom: 16px; transition: color 0.2s;"
               onmouseover="this.style.color='#fff'" onmouseout="this.style.color='rgba(255,255,255,0.7)'">
                ← Назад к списку заданий
            </a>
            
            <div style="display: flex; align-items: center; justify-content: center; gap: 12px; flex-wrap: wrap; margin-bottom: 12px;">
                <span style="font-size: 1.3rem;"><?= htmlspecialchars($task['icon']) ?></span>
                <h1 style="font-size: 1.8rem; font-weight: 900; margin: 0; color: #fff;">
                    <?= htmlspecialchars($task['subject_name']) ?>
                </h1>
            </div>
            
            <div style="display: flex; align-items: center; justify-content: center; gap: 8px; flex-wrap: wrap;">
                <span style="background: rgba(255,255,255,0.15); padding: 4px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; color: #fff;">
                    Задание №<?= $task['task_number'] ?>
                </span>
                <?php if ($task['fipi_code']): ?>
                    <span style="background: rgba(255,255,255,0.1); padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; color: rgba(255,255,255,0.8); font-family: monospace;">
                        <?= htmlspecialchars($task['fipi_code']) ?>
                    </span>
                <?php endif; ?>
                <?php if ($task['is_extended']): ?>
                    <span style="background: rgba(255, 193, 7, 0.3); padding: 4px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; color: #fff;">
                        📝 Развёрнутый ответ
                    </span>
                <?php else: ?>
                    <span style="background: rgba(76, 175, 80, 0.3); padding: 4px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; color: #fff;">
                        ✅ Краткий ответ
                    </span>
                <?php endif; ?>
                <?php if ($userAnswer): ?>
                    <span style="background: <?= $userAnswer['is_correct'] == 1 ? 'rgba(76, 175, 80, 0.3)' : 'rgba(244, 67, 54, 0.3)' ?>; padding: 4px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; color: #fff;">
                        <?= $userAnswer['is_correct'] == 1 ? '✅ Решено верно' : '❌ Ошибка' ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<main class="container">
    <div style="max-width: 800px; margin: 0 auto;">
        
        <!-- Карточка задания -->
        <div style="background: var(--card-bg); border-radius: var(--radius); padding: 32px; box-shadow: var(--shadow); border: 1px solid var(--border); margin-bottom: 24px;">
            <h2 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 20px; color: var(--text-main);">
                Задание №<?= $task['task_number'] ?>
            </h2>
            
            <!-- Картинка задания -->
            <?php if ($task['task_image']): ?>
                <div style="margin-bottom: 24px; text-align: center; background: #fafafa; padding: 20px; border-radius: 12px; border: 1px solid var(--border);">
                    <img src="<?= htmlspecialchars($taskImgSrc) ?>" 
                         alt="Задание №<?= $task['task_number'] ?>"
                         style="max-width: 100%; height: auto; max-height: 600px; border-radius: 8px;">
                </div>
            <?php endif; ?>
            
            <!-- Форма ответа для кратких заданий -->
            <?php if (!$task['is_extended']): ?>
                <form method="POST" style="margin-top: 24px; padding-top: 24px; border-top: 2px solid var(--border);">
                    <div style="margin-bottom: 16px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: var(--text-main);">
                            Ваш ответ:
                        </label>
                        <input type="text" name="user_answer" 
                               value="<?= htmlspecialchars($userAnswer['user_answer'] ?? '') ?>"
                               placeholder="<?= $task['answer_type_slug'] === 'number' ? 'Например: 72 или 72,5' : 'Введите ответ' ?>"
                               style="width: 100%; padding: 14px 18px; border: 2px solid var(--border); border-radius: 12px; font-size: 1rem; font-family: inherit;"
                               <?= $task['answer_type_slug'] === 'number' ? 'pattern="[0-9,.]*"' : '' ?>
                               required>
                    </div>
                    
                    <button type="submit" name="check_answer" 
                            style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; border: none; padding: 14px 32px; border-radius: 12px; font-weight: 600; font-size: 1rem; cursor: pointer; transition: all 0.2s;"
                            onmouseover="this.style.opacity='0.9'; this.style.transform='translateY(-2px)'"
                            onmouseout="this.style.opacity='1'; this.style.transform='translateY(0)'">
                        ✓ Проверить ответ
                    </button>
                    
                    <?php if (isset($checkResult)): ?>
                        <div style="margin-top: 16px; padding: 16px; border-radius: 12px; font-weight: 600; 
                                    background: <?= $checkResult['correct'] ? 'rgba(76, 175, 80, 0.1)' : 'rgba(244, 67, 54, 0.1)' ?>; 
                                    color: <?= $checkResult['correct'] ? '#4caf50' : '#f44336' ?>; 
                                    border: 1px solid <?= $checkResult['correct'] ? 'rgba(76, 175, 80, 0.3)' : 'rgba(244, 67, 54, 0.3)' ?>;">
                            <?= $checkResult['message'] ?>
                            <?php if (!$checkResult['correct'] && $checkResult['correct_answer']): ?>
                                <div style="margin-top: 8px; font-size: 0.9rem; font-weight: 400;">
                                    Правильный ответ: <strong><?= htmlspecialchars($checkResult['correct_answer']) ?></strong>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </form>
            <?php else: ?>
                <!-- Для развёрнутых заданий -->
                <div style="margin-top: 24px; padding: 20px; background: rgba(255, 193, 7, 0.08); border: 1px solid rgba(255, 193, 7, 0.2); border-radius: 12px;">
                    <p style="color: #ff9800; font-weight: 600; margin: 0;">
                        📝 Это задание с развёрнутым ответом. Проверка выполняется преподавателем.
                    </p>
                </div>
            <?php endif; ?>
            
            <!-- Решение задания -->
            <?php if ($task['solution_image']): ?>
                <div style="margin-top: 32px; padding-top: 24px; border-top: 2px solid var(--border);">
                    <button onclick="document.getElementById('solution-block').style.display = document.getElementById('solution-block').style.display === 'none' ? 'block' : 'none'"
                            style="background: rgba(102, 126, 234, 0.1); color: var(--accent); border: none; padding: 12px 24px; border-radius: 10px; font-weight: 600; cursor: pointer; font-size: 0.95rem; transition: all 0.2s;"
                            onmouseover="this.style.background='rgba(102, 126, 234, 0.2)'"
                            onmouseout="this.style.background='rgba(102, 126, 234, 0.1)'">
                        📖 Показать решение
                    </button>
                    
                    <div id="solution-block" style="display: none; margin-top: 20px; text-align: center;">
                        <img src="<?= htmlspecialchars($solutionImgSrc) ?>" 
                             alt="Решение задания №<?= $task['task_number'] ?>"
                             style="max-width: 100%; height: auto; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 4px 12px rgba(0,0,0,0.08);">
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Навигация -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <?php if ($prevTask): ?>
                <a href="task.php?id=<?= $prevTask['id'] ?>" 
                   style="padding: 20px; background: var(--card-bg); border-radius: var(--radius); border: 1px solid var(--border); text-decoration: none; color: var(--text-main); transition: all 0.2s; display: flex; align-items: center; gap: 12px;"
                   onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='var(--shadow)'"
                   onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                    <span style="font-size: 1.5rem;">←</span>
                    <div>
                        <div style="font-size: 0.85rem; color: var(--text-gray);">Предыдущее</div>
                        <div style="font-weight: 700;">Задание №<?= $prevTask['task_number'] ?></div>
                    </div>
                </a>
            <?php else: ?>
                <div style="padding: 20px; background: var(--light-bg); border-radius: var(--radius); border: 1px solid var(--border); opacity: 0.5; display: flex; align-items: center; gap: 12px;">
                    <span style="font-size: 1.5rem;">←</span>
                    <div>
                        <div style="font-size: 0.85rem; color: var(--text-gray);">Предыдущее</div>
                        <div style="font-weight: 700;">Нет задания</div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($nextTask): ?>
                <a href="task.php?id=<?= $nextTask['id'] ?>" 
                   style="padding: 20px; background: var(--card-bg); border-radius: var(--radius); border: 1px solid var(--border); text-decoration: none; color: var(--text-main); transition: all 0.2s; display: flex; align-items: center; gap: 12px; justify-content: flex-end; text-align: right;"
                   onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='var(--shadow)'"
                   onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                    <div>
                        <div style="font-size: 0.85rem; color: var(--text-gray);">Следующее</div>
                        <div style="font-weight: 700;">Задание №<?= $nextTask['task_number'] ?></div>
                    </div>
                    <span style="font-size: 1.5rem;">→</span>
                </a>
            <?php else: ?>
                <div style="padding: 20px; background: var(--light-bg); border-radius: var(--radius); border: 1px solid var(--border); opacity: 0.5; display: flex; align-items: center; gap: 12px; justify-content: flex-end; text-align: right;">
                    <div>
                        <div style="font-size: 0.85rem; color: var(--text-gray);">Следующее</div>
                        <div style="font-weight: 700;">Нет задания</div>
                    </div>
                    <span style="font-size: 1.5rem;">→</span>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require __DIR__ . '/footer.php'; ?>