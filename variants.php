<?php
require __DIR__ . '/db.php';
require __DIR__ . '/header.php';

try {
    $stmt = $pdo->query("
        SELECT 
            s.*,
            COUNT(t.id) as total_tasks,
            SUM(CASE WHEN t.is_extended = 0 THEN 1 ELSE 0 END) as short_tasks,
            SUM(CASE WHEN t.is_extended = 1 THEN 1 ELSE 0 END) as extended_tasks
        FROM subjects s
        LEFT JOIN tasks t ON s.id = t.subject_id
        WHERE s.is_active = 1
        GROUP BY s.id
        ORDER BY s.id
    ");
    $subjects = $stmt->fetchAll();
} catch (PDOException $e) {
    $subjects = [];
}
?>

<section class="hero" style="padding: 80px 24px 60px;">
    <div class="hero-content" style="flex-direction: column; text-align: center;">
        <div class="hero-text" style="max-width: 700px;">
            <span class="badge">📚 Тренировка по предметам</span>
            <h1>Выберите предмет для подготовки</h1>
            <p>Тысячи заданий с автоматической проверкой ответов. Начни с любого предмета и отслеживай свой прогресс.</p>
        </div>
    </div>
</section>

<main class="container">
    <div class="section-title">
        <h2>Доступные предметы</h2>
    </div>
    
    <div class="subjects-grid">
        <?php if (!empty($subjects)): ?>
            <?php foreach ($subjects as $subject): ?>
                <a href="task-list.php?subject=<?= htmlspecialchars($subject['slug']) ?>" class="subject-card-large" style="text-decoration: none;">
                    <div class="card-info">
                        <span class="card-tag" style="font-size: 1.5rem; margin-bottom: 12px;">
                            <?= htmlspecialchars($subject['icon']) ?>
                        </span>
                        <h3 style="text-decoration: none; color: var(--text-main); margin-bottom: 8px;">
                            <?= htmlspecialchars($subject['name']) ?>
                        </h3>
                        <p style="color: var(--text-gray);">
                            <strong><?= $subject['total_tasks'] ?? 0 ?></strong> заданий всего
                            <?php if (($subject['short_tasks'] ?? 0) > 0): ?>
                                <br>
                                <span style="color: #4caf50; font-size: 0.85rem;">
                                    ✅ <?= $subject['short_tasks'] ?> с автопроверкой
                                </span>
                            <?php endif; ?>
                            <?php if (($subject['extended_tasks'] ?? 0) > 0): ?>
                                <br>
                                <span style="color: #ff9800; font-size: 0.85rem;">
                                    📝 <?= $subject['extended_tasks'] ?> развёрнутых
                                </span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="card-footer">
                        <span class="go-link">Начать тренировку →</span>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="grid-column: 1/-1; text-align: center; padding: 60px 20px;">
                <p style="font-size: 1.1rem; color: var(--text-gray);">😕 Предметы пока не добавлены</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php require __DIR__ . '/footer.php'; ?>