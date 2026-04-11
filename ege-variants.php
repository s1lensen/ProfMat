<?php
require __DIR__ . '/db.php';
require __DIR__ . '/header.php';

// Получаем предметы
$stmt = $pdo->query("SELECT * FROM subjects WHERE is_active = 1 ORDER BY id");
$subjects = $stmt->fetchAll();

// Получаем выбранный предмет
$selectedSubject = $_GET['subject'] ?? null;

// Получаем варианты
$sql = "
    SELECT 
        v.*,
        s.name as subject_name,
        s.slug as subject_slug,
        s.icon,
        COUNT(vt.task_id) as task_count,
        SUM(t.points) as total_points
    FROM variants v
    JOIN subjects s ON v.subject_id = s.id
    LEFT JOIN variant_tasks vt ON v.id = vt.variant_id
    LEFT JOIN tasks t ON vt.task_id = t.id
    WHERE v.is_public = 1
";

$params = [];

if ($selectedSubject) {
    $sql .= " AND v.subject_id = ?";
    $params[] = $selectedSubject;
}

$sql .= " GROUP BY v.id ORDER BY v.subject_id, v.variant_number DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$variants = $stmt->fetchAll();
?>

<section class="hero" style="padding: 80px 24px 60px;">
    <div class="hero-content" style="flex-direction: column; text-align: center;">
        <div class="hero-text" style="max-width: 700px;">
            <span class="badge">📝 ЕГЭ 2026</span>
            <h1>Тренировочные варианты</h1>
            <p>Полноценные варианты ЕГЭ по всем предметам. Решай как на настоящем экзамене и отслеживай прогресс.</p>
        </div>
    </div>
</section>


<div style="max-width: 1200px; margin: 0 auto; padding: 0 24px;">
    <div style="text-align: right; margin-bottom: 24px;">
        <a href="variant-tasks.php?subject=math" 
           style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: var(--light-bg); color: var(--text-main); border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 0.9rem; border: 1px solid var(--border); transition: all 0.2s;"
           onmouseover="this.style.background='var(--border)'; this.style.transform='translateY(-2px)'"
           onmouseout="this.style.background='var(--light-bg)'; this.style.transform='translateY(0)'">
            📖 Задачи из вариантов
        </a>
    </div>
</div>

<main class="container">
    <!-- Фильтр по предметам -->
    <div style="background: var(--card-bg); border-radius: var(--radius); padding: 24px; margin-bottom: 32px; box-shadow: var(--shadow);">
        <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 16px; color: var(--text-main);">
            📚 Выбрать предмет
        </h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
            <a href="ege-variants.php" 
               style="padding: 16px 20px; background: <?= !$selectedSubject ? 'var(--accent)' : 'var(--light-bg)' ?>; color: <?= !$selectedSubject ? '#fff' : 'var(--text-main)' ?>; border-radius: 12px; text-decoration: none; font-weight: 600; text-align: center; transition: all 0.2s;"
               onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='var(--shadow)'"
               onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                📋 Все предметы
            </a>
            <?php foreach ($subjects as $subj): ?>
                <a href="ege-variants.php?subject=<?= $subj['id'] ?>" 
                   style="padding: 16px 20px; background: <?= $selectedSubject == $subj['id'] ? 'var(--accent)' : 'var(--light-bg)' ?>; color: <?= $selectedSubject == $subj['id'] ? '#fff' : 'var(--text-main)' ?>; border-radius: 12px; text-decoration: none; font-weight: 600; text-align: center; transition: all 0.2s;"
                   onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='var(--shadow)'"
                   onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                    <?= htmlspecialchars($subj['icon']) ?> <?= htmlspecialchars($subj['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Список вариантов -->
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 24px;">
        <?php foreach ($variants as $variant): ?>
            <a href="ege-variant-view.php?id=<?= $variant['id'] ?>" 
               style="background: var(--card-bg); border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow); border: 1px solid var(--border); text-decoration: none; color: var(--text-main); transition: all 0.2s;"
               onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 8px 30px rgba(0,0,0,0.12)'"
               onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='var(--shadow)'">
                
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <span style="font-size: 2rem;"><?= htmlspecialchars($variant['icon']) ?></span>
                        <div>
                            <h3 style="font-size: 1.1rem; font-weight: 700; margin: 0; color: var(--text-main);">
                                Вариант №<?= $variant['variant_number'] ?>
                            </h3>
                            <p style="font-size: 0.85rem; color: var(--text-muted); margin: 4px 0 0 0;">
                                <?= htmlspecialchars($variant['subject_name']) ?> • <?= $variant['year'] ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div style="display: flex; gap: 12px; margin-bottom: 16px; flex-wrap: wrap;">
                    <span style="background: var(--light-bg); padding: 6px 12px; border-radius: 8px; font-size: 0.85rem; color: var(--text-gray);">
                        📝 <?= $variant['task_count'] ?> заданий
                    </span>
                    <span style="background: var(--light-bg); padding: 6px 12px; border-radius: 8px; font-size: 0.85rem; color: var(--text-gray);">
                        💯 <?= $variant['total_points'] ?> баллов
                    </span>
                    <?php if ($variant['time_limit']): ?>
                        <span style="background: rgba(255, 193, 7, 0.15); padding: 6px 12px; border-radius: 8px; font-size: 0.85rem; color: #ff9800;">
                            ⏱️ <?= floor($variant['time_limit'] / 60) ?> ч <?= $variant['time_limit'] % 60 ?> мин
                        </span>
                    <?php endif; ?>
                </div>
                
                <?php if ($variant['description']): ?>
                    <p style="font-size: 0.9rem; color: var(--text-gray); margin: 0 0 16px 0; line-height: 1.6;">
                        <?= htmlspecialchars(mb_substr($variant['description'], 0, 100)) ?><?= mb_strlen($variant['description']) > 100 ? '...' : '' ?>
                    </p>
                <?php endif; ?>
                
                <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 16px; border-top: 1px solid var(--border);">
                    <span style="font-size: 0.85rem; color: var(--text-muted);">
                        ⏳ Не решено
                    </span>
                    <span style="color: var(--accent); font-weight: 600; font-size: 0.9rem;">
                        Начать →
                    </span>
                </div>
            </a>
        <?php endforeach; ?>
        
        <?php if (empty($variants)): ?>
            <div style="grid-column: 1/-1; text-align: center; padding: 80px 20px; background: var(--card-bg); border-radius: var(--radius); box-shadow: var(--shadow);">
                <div style="font-size: 4rem; margin-bottom: 16px;">📭</div>
                <h3 style="font-size: 1.3rem; font-weight: 700; margin-bottom: 8px; color: var(--text-main);">Вариантов пока нет</h3>
                <p style="color: var(--text-gray);">
                    <?php if ($selectedSubject): ?>
                        <a href="ege-variants.php" style="color: var(--accent); text-decoration: none;">Показать все предметы</a>
                    <?php else: ?>
                        Загляни позже!
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php require __DIR__ . '/footer.php'; ?>