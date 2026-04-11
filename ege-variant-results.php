<?php
// ============================================
// ОТЛАДКА - показать ошибки
// ============================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
// ============================================

require __DIR__ . '/db.php';
require __DIR__ . '/header.php';

// session_start() НЕ НУЖЕН - уже есть в header.php!

$variantId = $_GET['id'] ?? null;
$timeSpent = $_GET['time_spent'] ?? 0;

if (!$variantId || !isset($_SESSION['user_id'])) {
    header('Location: ege-variants.php');
    exit;
}

// Получаем вариант
$stmt = $pdo->prepare("SELECT * FROM variants WHERE id = ?");
$stmt->execute([$variantId]);
$variant = $stmt->fetch();

if (!$variant) {
    die('<div style="text-align:center;padding:100px;font-family:sans-serif;">
        <h1>❌ Вариант не найден</h1>
        <a href="/ege-variants.php">← К вариантам</a>
    </div>');
}

// Получаем задания варианта
$stmt = $pdo->prepare("
    SELECT t.*, vt.task_order 
    FROM tasks t
    JOIN variant_tasks vt ON t.id = vt.task_id
    WHERE vt.variant_id = ?
    ORDER BY vt.task_order ASC
");
$stmt->execute([$variantId]);
$tasks = $stmt->fetchAll();

// Считаем результаты
$part1Tasks = [];
$part2Tasks = [];
$part1Correct = 0;
$part1Total = 0;
$part2Score = 0;
$part2Max = 0;
$totalScore = 0;
$totalMax = 0;

foreach ($tasks as $task) {
    $stmt = $pdo->prepare("
        SELECT * FROM user_answers 
        WHERE user_id = ? AND task_id = ? 
        ORDER BY answered_at DESC LIMIT 1
    ");
    $stmt->execute([$_SESSION['user_id'], $task['id']]);
    $answer = $stmt->fetch();
    
    $taskResult = [
        'number' => $task['task_number'],
        'fipi_code' => $task['fipi_code'],
        'points' => $task['points'],
        'earned' => 0,
        'correct' => false,
        'answered' => false,
        'user_answer' => $answer['user_answer'] ?? null
    ];
    
    if ($task['is_extended']) {
        // 2 часть
        $part2Max += $task['points'];
        if ($answer) {
            $taskResult['earned'] = (int)$answer['points_earned'];
            $taskResult['answered'] = true;
            $part2Score += $taskResult['earned'];
        }
        $part2Tasks[] = $taskResult;
    } else {
        // 1 часть
        $part1Total++;
        if ($answer && $answer['is_correct'] == 1) {
            $part1Correct++;
            $taskResult['correct'] = true;
            $taskResult['earned'] = $task['points'];
        }
        if ($answer) {
            $taskResult['answered'] = true;
            $taskResult['user_answer'] = $answer['user_answer'];
        }
        $part1Tasks[] = $taskResult;
    }
    
    $totalMax += $task['points'];
    $totalScore += $taskResult['earned'];
}

$primaryScore = $part1Correct + $part2Score;

// Перевод в тестовый балл (математика профиль 2026)
$testScoreMap = [
    0=>0, 1=>5, 2=>11, 3=>17, 4=>22, 5=>27, 6=>34, 7=>40, 8=>46, 9=>52, 10=>58,
    11=>64, 12=>70, 13=>72, 14=>74, 15=>76, 16=>78, 17=>80, 18=>82, 19=>84, 20=>86,
    21=>88, 22=>90, 23=>92, 24=>94, 25=>95, 26=>96, 27=>97, 28=>98, 29=>99,
    30=>100, 31=>100, 32=>100
];
$testScore = $testScoreMap[$primaryScore] ?? 0;

// Определение оценки
if ($primaryScore <= 4) $grade = 2;
elseif ($primaryScore <= 6) $grade = 3;
elseif ($primaryScore <= 15) $grade = 4;
else $grade = 5;

$gradeColors = [2 => '#ef4444', 3 => '#f59e0b', 4 => '#3b82f6', 5 => '#10b981'];
$gradeTexts = [2 => 'Не сдал', 3 => 'Удовл.', 4 => 'Хорошо', 5 => 'Отлично'];

// ============================================
// ФОРМАТИРУЕМ ВРЕМЯ (БЕЗ sprintf с кириллицей!)
// ============================================
$timeFormatted = '—';
if ($timeSpent > 0) {
    $hours = floor($timeSpent / 3600);
    $minutes = floor(($timeSpent % 3600) / 60);
    $seconds = $timeSpent % 60;
    
    // Показываем только если больше 0
    if ($hours > 0) {
        $timeFormatted = $hours . 'ч ' . $minutes . 'мин ' . $seconds . 'сек';
    } elseif ($minutes > 0) {
        $timeFormatted = $minutes . 'мин ' . $seconds . 'сек';
    } else {
        $timeFormatted = $seconds . ' сек';
    }
}
// ============================================
?>

<!-- Hero секция -->
<section class="hero" style="padding: 80px 24px 60px; background: linear-gradient(135deg, <?= $gradeColors[$grade] ?> 0%, <?= $gradeColors[$grade] ?>dd 100%);">
    <div class="hero-content" style="flex-direction: column; text-align: center;">
        <div style="font-size: 5rem; margin-bottom: 8px; color: #fff;"><?= $testScore ?></div>
        <div style="font-size: 1.3rem; color: rgba(255,255,255,0.9); margin-bottom: 16px;">тестовых баллов</div>
        <div style="display: inline-flex; align-items: center; gap: 12px; padding: 12px 32px; background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); border-radius: 50px;">
            <span style="font-size: 2.5rem; font-weight: 900; color: #fff;"><?= $grade ?></span>
            <div style="text-align: left;">
                <div style="font-weight: 700; color: #fff;"><?= $gradeTexts[$grade] ?></div>
                <div style="font-size: 0.85rem; color: rgba(255,255,255,0.8);"><?= $primaryScore ?> первичных баллов</div>
            </div>
        </div>
    </div>
</section>

<main class="container">
    <div style="max-width: 1100px; margin: 0 auto;">
        
        <!-- Статистика -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 32px;">
            <div style="background: var(--card-bg); border-radius: var(--radius); padding: 24px; text-align: center; box-shadow: var(--shadow); border: 1px solid var(--border);">
                <div style="font-size: 2.5rem; font-weight: 900; color: #10b981; margin-bottom: 4px;"><?= $part1Correct ?></div>
                <div style="color: var(--text-gray); font-size: 0.9rem; font-weight: 600;">Верно (1 часть)</div>
                <div style="font-size: 0.85rem; color: #999; margin-top: 4px;">из <?= $part1Total ?> заданий</div>
            </div>
            <div style="background: var(--card-bg); border-radius: var(--radius); padding: 24px; text-align: center; box-shadow: var(--shadow); border: 1px solid var(--border);">
                <div style="font-size: 2.5rem; font-weight: 900; color: #f59e0b; margin-bottom: 4px;"><?= $part2Score ?></div>
                <div style="color: var(--text-gray); font-size: 0.9rem; font-weight: 600;">Баллов (2 часть)</div>
                <div style="font-size: 0.85rem; color: #999; margin-top: 4px;">из <?= $part2Max ?> возможных</div>
            </div>
            <div style="background: var(--card-bg); border-radius: var(--radius); padding: 24px; text-align: center; box-shadow: var(--shadow); border: 1px solid var(--border);">
                <div style="font-size: 2.5rem; font-weight: 900; color: var(--accent); margin-bottom: 4px;"><?= $primaryScore ?></div>
                <div style="color: var(--text-gray); font-size: 0.9rem; font-weight: 600;">Первичный балл</div>
                <div style="font-size: 0.85rem; color: #999; margin-top: 4px;">из <?= $totalMax ?> возможных</div>
            </div>
            <div style="background: var(--card-bg); border-radius: var(--radius); padding: 24px; text-align: center; box-shadow: var(--shadow); border: 1px solid var(--border);">
                <div style="font-size: 2.5rem; font-weight: 900; color: #6366f1; margin-bottom: 4px; line-height: 1;"><?= $timeFormatted ?></div>
                <div style="color: var(--text-gray); font-size: 0.9rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Затраченное время</div>
                <?php if ($variant['time_limit']): ?>
                <div style="font-size: 0.85rem; color: #999; margin-top: 4px;">
                    из <?= floor($variant['time_limit'] / 60) ?> ч <?= $variant['time_limit'] % 60 ?> мин
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Результаты по заданиям -->
        <div style="background: var(--card-bg); border-radius: var(--radius); padding: 32px; box-shadow: var(--shadow); margin-bottom: 32px; border: 1px solid var(--border);">
            <h2 style="font-size: 1.3rem; margin-bottom: 24px; color: var(--text-main);">📊 Результаты по заданиям</h2>
            
            <!-- 1 часть -->
            <div style="margin-bottom: 32px;">
                <h3 style="font-size: 1.1rem; margin-bottom: 16px; color: var(--text-main);">
                    <span style="background: rgba(76, 175, 80, 0.15); color: #4caf50; padding: 4px 12px; border-radius: 8px; font-size: 0.85rem; font-weight: 600;">✅ Часть 1</span>
                    <span style="color: var(--text-gray); font-size: 0.9rem; font-weight: 400; margin-left: 12px;">Краткий ответ</span>
                </h3>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 10px;">
                    <?php foreach ($part1Tasks as $task): ?>
                        <!-- ЧАСТЬ 1 - ЗАМЕНИ ЭТОТ БЛОК (строка ~180) -->
                        <div style="text-align: center; padding: 14px 10px; background: <?= $task['correct'] ? 'rgba(16, 185, 129, 0.1)' : ($task['answered'] ? 'rgba(239, 68, 68, 0.1)' : 'var(--light-bg)') ?>; border-radius: 12px; border: 2px solid <?= $task['correct'] ? '#10b981' : ($task['answered'] ? '#ef4444' : 'var(--border)') ?>;">
                            <div style="font-weight: 700; font-size: 1.2rem; color: <?= $task['correct'] ? '#10b981' : ($task['answered'] ? '#ef4444' : 'var(--text-gray)') ?>;">
                                <?= $task['number'] ?>
                            </div>
                            <!-- УБРАЛИ VAR1_TASK - теперь показываем баллы -->
                            <div style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px;">
                                <?= $task['earned'] ?> из <?= $task['points'] ?> балл.
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- 2 часть -->
            <div>
                <h3 style="font-size: 1.1rem; margin-bottom: 16px; color: var(--text-main);">
                    <span style="background: rgba(255, 193, 7, 0.15); color: #ff9800; padding: 4px 12px; border-radius: 8px; font-size: 0.85rem; font-weight: 600;">📝 Часть 2</span>
                    <span style="color: var(--text-gray); font-size: 0.9rem; font-weight: 400; margin-left: 12px;">Развёрнутый ответ</span>
                </h3>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 14px;">
                    <?php foreach ($part2Tasks as $task): ?>
                        <!-- ЧАСТЬ 2 - ЗАМЕНИ ЭТОТ БЛОК (строка ~230) -->
                        <div style="padding: 18px; background: <?= $task['earned'] > 0 ? 'rgba(255, 193, 7, 0.1)' : 'var(--light-bg)' ?>; border-radius: 12px; border: 2px solid <?= $task['earned'] > 0 ? '#f59e0b' : 'var(--border)' ?>;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <span style="font-weight: 700; font-size: 1.1rem; color: var(--text-main);">№<?= $task['number'] ?></span>
                                <span style="font-size: 0.8rem; color: var(--text-gray);">из <?= $task['points'] ?></span>
                            </div>
                            <!-- УБРАЛИ VAR1_TASK -->
                            <div style="font-size: 2rem; font-weight: 900; color: <?= $task['earned'] > 0 ? '#f59e0b' : 'var(--text-gray)' ?>;">
                                <?= $task['earned'] ?>
                            </div>
                            <div style="font-size: 0.8rem; color: var(--text-gray);">
                                балл.<?= $task['earned'] == 1 ? '' : 'ов' ?>
                            </div>
                        </div>
                    <?php endforeach; ?> 
                </div>
            </div>
        </div>
        
        <!-- Кнопки -->
        <div style="display: flex; gap: 16px; justify-content: center; flex-wrap: wrap;">
            <a href="ege-variant-solve.php?id=<?= $variantId ?>" class="btn-primary" style="display: inline-block; text-decoration: none; padding: 14px 32px;">
                🔄 Пройти ещё раз
            </a>
            <a href="ege-variants.php" style="display: inline-block; padding: 14px 32px; background: var(--light-bg); color: var(--text-main); border-radius: 12px; text-decoration: none; font-weight: 600;">
                📋 Другие варианты
            </a>
        </div>
    </div>
</main>

<?php require __DIR__ . '/footer.php'; ?>