<?php
require __DIR__ . '/db.php';
require __DIR__ . '/header.php';

$variantId = $_GET['id'] ?? null;
if ($variantId) {
    $sessionKey = 'variant_start_time_' . $variantId;
    if (isset($_SESSION[$sessionKey])) {
        unset($_SESSION[$sessionKey]);
    }
}

if (!$variantId) {
    header('Location: ege-variants.php');
    exit;
}

// Получаем информацию о варианте
$stmt = $pdo->prepare("
    SELECT v.*, s.name as subject_name, s.slug as subject_slug, s.icon
    FROM variants v
    JOIN subjects s ON v.subject_id = s.id
    WHERE v.id = ? AND v.is_public = 1
");
$stmt->execute([$variantId]);
$variant = $stmt->fetch();

if (!$variant) {
    header('Location: ege-variants.php');
    exit;
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

// Считаем общую сумму баллов
$totalPoints = array_sum(array_column($tasks, 'points'));
?>

<!-- Шапка варианта -->
<section class="hero" style="padding: 60px 24px 40px; background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);">
    <div class="hero-content" style="flex-direction: column;">
        <div class="hero-text" style="max-width: 900px; text-align: center; margin: 0 auto;">
            <a href="ege-variants.php" style="color: rgba(255,255,255,0.7); text-decoration: none; font-weight: 600; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px; margin-bottom: 16px; transition: color 0.2s;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='rgba(255,255,255,0.7)'">
                ← Назад к списку вариантов
            </a>
            
            <div style="display: flex; align-items: center; justify-content: center; gap: 12px; flex-wrap: wrap; margin-bottom: 12px;">
                <span style="font-size: 1.5rem;"><?= htmlspecialchars($variant['icon']) ?></span>
                <h1 style="font-size: 2rem; font-weight: 900; margin: 0; color: #fff;">
                    Вариант №<?= $variant['variant_number'] ?>
                </h1>
                <span style="background: rgba(255,255,255,0.15); padding: 4px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; color: #fff; backdrop-filter: blur(5px);">
                    <?= $variant['year'] ?>
                </span>
            </div>
            
            <p style="color: rgba(255,255,255,0.8); font-size: 1rem; margin: 0; display: flex; align-items: center; justify-content: center; gap: 12px; flex-wrap: wrap;">
                <span><?= htmlspecialchars($variant['subject_name']) ?></span>
                <span style="opacity: 0.5;">•</span>
                <span>📝 <?= count($tasks) ?> заданий</span>
                <span style="opacity: 0.5;">•</span>
                <span>💯 <?= $totalPoints ?> баллов</span>
                <?php if ($variant['time_limit']): ?>
                    <span style="opacity: 0.5;">•</span>
                    <span>⏱️ <?= floor($variant['time_limit'] / 60) ?> ч <?= $variant['time_limit'] % 60 ?> мин</span>
                <?php endif; ?>
            </p>
        </div>
    </div>
</section>

<main class="container">
    <div style="max-width: 900px; margin: 0 auto;">
        
        <!-- Карточка статистики -->
        <div style="background: var(--card-bg); border-radius: var(--radius); padding: 32px; margin-bottom: 32px; box-shadow: var(--shadow); border: 1px solid var(--border);">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 32px; text-align: center;">
                <div>
                    <div style="font-size: 2.5rem; font-weight: 900; color: var(--brand); line-height: 1; margin-bottom: 8px;"><?= count($tasks) ?></div>
                    <div style="color: var(--text-gray); font-size: 0.9rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Заданий</div>
                </div>
                <div style="border-left: 1px solid var(--border); border-right: 1px solid var(--border);">
                    <div style="font-size: 2.5rem; font-weight: 900; color: var(--accent); line-height: 1; margin-bottom: 8px;"><?= $totalPoints ?></div>
                    <div style="color: var(--text-gray); font-size: 0.9rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Баллов</div>
                </div>
                <?php if ($variant['time_limit']): ?>
                <div>
                    <div style="font-size: 2.5rem; font-weight: 900; color: #ff9800; line-height: 1; margin-bottom: 8px;">
                        <?= floor($variant['time_limit'] / 60) ?>:<span style="font-size: 0.6em; vertical-align: top;"><?= str_pad($variant['time_limit'] % 60, 2, '0', STR_PAD_LEFT) ?></span>
                    </div>
                    <div style="color: var(--text-gray); font-size: 0.9rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Время</div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Список заданий -->
        <div style="display: flex; flex-direction: column; gap: 20px;">
            <?php foreach ($tasks as $index => $task): ?>
                <div style="background: var(--card-bg); border-radius: var(--radius); padding: 24px 28px; box-shadow: var(--shadow); border: 1px solid var(--border); transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 24px rgba(0,0,0,0.08)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='var(--shadow)'">
                    
                    <!-- Заголовок -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                        <h3 style="font-size: 1.15rem; font-weight: 800; margin: 0; color: var(--text-main);">
                            Задание №<?= $index + 1 ?>
                        </h3>
                        <span style="background: var(--light-bg); padding: 6px 12px; border-radius: 8px; font-size: 0.85rem; color: var(--text-gray); font-weight: 600; border: 1px solid var(--border);">
                            💯 <?= $task['points'] ?> балл.
                        </span>
                    </div>
                    
                    <!-- Картинка задания (минимальные отступы) -->
                    <?php if ($task['task_image']): ?>
                        <div style="margin-bottom: 20px; text-align: center; background: #fafafa; padding: 12px; border-radius: 10px; border: 1px solid var(--border);">
                            <?php 
                            $imgSrc = $task['task_image'];
                            if (strpos($imgSrc, '/') !== 0) {
                                $imgSrc = '/' . $imgSrc;
                            }
                            ?>
                            <img src="<?= htmlspecialchars($imgSrc) ?>" 
                                 alt="Задание <?= $index + 1 ?>"
                                 loading="lazy"
                                 style="max-width: 100%; height: auto; max-height: 600px; border-radius: 6px;">
                        </div>
                    <?php endif; ?>

                    <div style="display: flex; gap: 12px; flex-wrap: wrap; justify-content: flex-end; margin-top: 16px;">
                        <a href="task.php?id=<?= $task['id'] ?>"
                        style="padding: 10px 20px; background: var(--light-bg); color: var(--text-main); border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 0.9rem; border: 1px solid var(--border); transition: all 0.2s;"
                        onmouseover="this.style.background='var(--border)'; this.style.transform='translateY(-1px)'"
                        onmouseout="this.style.background='var(--light-bg)'; this.style.transform='translateY(0)'">
                            📖 Просмотреть
                        </a>
                        
                        <?php if (!$task['is_extended']): ?>
                            <a href="ege-variant-solve.php?id=<?= $variant['id'] ?>#task-<?= $index + 1 ?>"
                            class="btn-primary"
                            style="display: inline-block; text-decoration: none; padding: 10px 20px; font-size: 0.9rem; border: none;">
                                ✓ Сохранить
                            </a>
                        <?php else: ?>
                            <!-- ИСПРАВЛЕННЫЙ БЕЙДЖИК ДЛЯ 2 ЧАСТИ -->
                            <span style="display: inline-flex; align-items: center; gap: 6px; padding: 10px 18px; background: linear-gradient(135deg, rgba(255, 193, 7, 0.2) 0%, rgba(255, 152, 0, 0.2) 100%); color: #f57c00; border-radius: 10px; font-weight: 700; font-size: 0.9rem; border: 2px solid rgba(255, 193, 7, 0.4); box-shadow: 0 2px 8px rgba(255, 152, 0, 0.15);">
                                📝 Развёрнутый
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Финальная кнопка -->
        <div style="margin-top: 40px; text-align: center; padding: 32px; background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%); border-radius: var(--radius); border: 1px solid var(--border);">
            <h2 style="font-size: 1.4rem; margin-bottom: 10px; color: var(--text-main);">Готовы начать?</h2>
            <p style="color: var(--text-gray); margin-bottom: 20px; max-width: 500px; margin-left: auto; margin-right: auto;">
                Запустите таймер и решайте вариант в условиях экзамена
            </p>
            <a href="ege-variant-solve.php?id=<?= $variant['id'] ?>" 
               class="btn-primary" 
               style="display: inline-block; text-decoration: none; padding: 14px 40px; font-size: 1.05rem; box-shadow: 0 6px 16px rgba(102, 126, 234, 0.3);">
                🚀 Начать решение
            </a>
        </div>
    </div>
</main>

<?php require __DIR__ . '/footer.php'; ?>