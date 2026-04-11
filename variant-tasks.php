<?php
require __DIR__ . '/db.php';
require __DIR__ . '/header.php';

// Получаем выбранный предмет
$subjectSlug = $_GET['subject'] ?? 'math';

// Получаем предмет
$stmt = $pdo->prepare("SELECT * FROM subjects WHERE slug = ?");
$stmt->execute([$subjectSlug]);
$subject = $stmt->fetch();

if (!$subject) {
    $subject = ['id' => 1, 'name' => 'Математика', 'slug' => 'math', 'icon' => '📐'];
}

// Получаем задания ИЗ ВАРИАНТОВ
$stmt = $pdo->prepare("
    SELECT DISTINCT t.*, 
           at.name as answer_type_name, 
           at.slug as answer_type_slug
    FROM tasks t
    JOIN variant_tasks vt ON t.id = vt.task_id
    LEFT JOIN answer_types at ON t.answer_type_id = at.id
    WHERE t.subject_id = ?
    ORDER BY t.task_number ASC
");
$stmt->execute([$subject['id']]);
$tasks = $stmt->fetchAll();

// Получаем статусы для пользователя
$taskStatuses = [];
if (isset($_SESSION['user_id'])) {
    $taskIds = array_column($tasks, 'id');
    if (!empty($taskIds)) {
        $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
        $stmt = $pdo->prepare("
            SELECT task_id, is_correct, user_answer 
            FROM user_answers 
            WHERE user_id = ? AND task_id IN ($placeholders)
        ");
        $params = array_merge([$_SESSION['user_id']], $taskIds);
        $stmt->execute($params);
        while ($row = $stmt->fetch()) {
            $taskStatuses[$row['task_id']] = $row;
        }
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
?>

<section class="hero" style="padding: 60px 24px 40px; background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);">
    <div class="hero-content" style="flex-direction: column;">
        <div class="hero-text" style="max-width: 800px; text-align: center;">
            <a href="ege-variants.php" style="color: rgba(255,255,255,0.7); text-decoration: none; font-weight: 600; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px; margin-bottom: 16px;">
                ← Назад к вариантам
            </a>
            
            <div style="display: flex; align-items: center; justify-content: center; gap: 12px; margin-bottom: 12px;">
                <span style="font-size: 1.5rem;"><?= htmlspecialchars($subject['icon']) ?></span>
                <h1 style="font-size: 1.8rem; font-weight: 900; margin: 0; color: #fff;">
                    <?= htmlspecialchars($subject['name']) ?>
                </h1>
            </div>
            
            <p style="color: rgba(255,255,255,0.8); font-size: 1rem; margin: 0;">
                <strong><?= count($tasks) ?></strong> заданий из вариантов ЕГЭ
            </p>
        </div>
    </div>
</section>

<main class="container">
    <div style="max-width: 1200px; margin: 0 auto;">
        
        <!-- Выбор предмета -->
        <div style="background: var(--card-bg); border-radius: var(--radius); padding: 24px; margin-bottom: 32px; box-shadow: var(--shadow); border: 1px solid var(--border);">
            <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 16px; color: var(--text-main);">
                📚 Выбрать предмет
            </h3>
            <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                <a href="?subject=math" 
                   style="padding: 12px 24px; background: <?= $subject['slug'] == 'math' ? 'var(--accent)' : 'var(--light-bg)' ?>; color: <?= $subject['slug'] == 'math' ? '#fff' : 'var(--text-main)' ?>; border-radius: 10px; text-decoration: none; font-weight: 600; transition: all 0.2s;">
                    📐 Математика
                </a>
                <a href="?subject=russian" 
                   style="padding: 12px 24px; background: <?= $subject['slug'] == 'russian' ? 'var(--accent)' : 'var(--light-bg)' ?>; color: <?= $subject['slug'] == 'russian' ? '#fff' : 'var(--text-main)' ?>; border-radius: 10px; text-decoration: none; font-weight: 600; transition: all 0.2s;">
                    📝 Русский язык
                </a>
                <a href="?subject=informatics" 
                   style="padding: 12px 24px; background: <?= $subject['slug'] == 'informatics' ? 'var(--accent)' : 'var(--light-bg)' ?>; color: <?= $subject['slug'] == 'informatics' ? '#fff' : 'var(--text-main)' ?>; border-radius: 10px; text-decoration: none; font-weight: 600; transition: all 0.2s;">
                    💻 Информатика
                </a>
            </div>
        </div>
        
        <!-- Фильтр по номерам заданий -->
        <div style="background: var(--card-bg); border-radius: var(--radius); padding: 24px; margin-bottom: 32px; box-shadow: var(--shadow); border: 1px solid var(--border);">
            <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 16px; color: var(--text-main);">
                📋 Фильтр по номерам
            </h3>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <?php for ($i = 1; $i <= 27; $i++): ?>
                    <button onclick="scrollToTask(<?= $i ?>)" 
                            style="padding: 8px 16px; background: var(--light-bg); color: var(--text-main); border: 1px solid var(--border); border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.9rem; transition: all 0.2s;"
                            onmouseover="this.style.background='var(--border)'; this.style.transform='translateY(-2px)'"
                            onmouseout="this.style.background='var(--light-bg)'; this.style.transform='translateY(0)'">
                        №<?= $i ?>
                    </button>
                <?php endfor; ?>
            </div>
        </div>
        
        <!-- Список заданий -->
        <div style="display: flex; flex-direction: column; gap: 20px;">
            <?php foreach ($tasks as $index => $task): 
                $status = $taskStatuses[$task['id']] ?? null;
                $imgSrc = fixImagePath($task['task_image']);
            ?>
                <div id="task-<?= $task['task_number'] ?>" style="background: var(--card-bg); border-radius: var(--radius); padding: 24px 28px; box-shadow: var(--shadow); border: 1px solid var(--border); scroll-margin-top: 100px;">
                    
                    <!-- Заголовок -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
                        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                            <span style="background: var(--brand); color: #fff; padding: 6px 14px; border-radius: 8px; font-weight: 700; font-size: 0.95rem;">
                                Задание №<?= $task['task_number'] ?>
                            </span>
                            <?php if ($task['is_extended']): ?>
                                <span style="background: rgba(255, 193, 7, 0.15); color: #ff9800; padding: 4px 10px; border-radius: 6px; font-size: 0.8rem; font-weight: 600;">
                                    📝 Развёрнутый
                                </span>
                            <?php else: ?>
                                <span style="background: rgba(76, 175, 80, 0.15); color: #4caf50; padding: 4px 10px; border-radius: 6px; font-size: 0.8rem; font-weight: 600;">
                                    ✅ Краткий
                                </span>
                            <?php endif; ?>
                            <?php if ($status): ?>
                                <?php if ($status['is_correct'] == 1): ?>
                                    <span style="background: rgba(76, 175, 80, 0.15); color: #4caf50; padding: 4px 10px; border-radius: 6px; font-size: 0.8rem; font-weight: 600;">
                                        ✅ Решено
                                    </span>
                                <?php else: ?>
                                    <span style="background: rgba(244, 67, 54, 0.15); color: #f44336; padding: 4px 10px; border-radius: 6px; font-size: 0.8rem; font-weight: 600;">
                                        ❌ Ошибка
                                    </span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <span style="background: var(--light-bg); padding: 6px 12px; border-radius: 8px; font-size: 0.85rem; color: var(--text-gray); font-weight: 600; border: 1px solid var(--border);">
                            💯 <?= $task['points'] ?> балл.
                        </span>
                    </div>
                    
                    <!-- Картинка задания -->
                    <?php if ($task['task_image']): ?>
                        <div style="margin-bottom: 20px; text-align: center; background: #fafafa; padding: 16px; border-radius: 10px; border: 1px solid var(--border);">
                            <img src="<?= htmlspecialchars($imgSrc) ?>" 
                                 alt="Задание №<?= $task['task_number'] ?>"
                                 loading="lazy"
                                 style="max-width: 100%; height: auto; max-height: 500px; border-radius: 8px;">
                        </div>
                    <?php endif; ?>
                    
                    <!-- Кнопки -->
                    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                        <a href="task.php?id=<?= $task['id'] ?>" 
                           style="padding: 10px 20px; background: var(--light-bg); color: var(--text-main); border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 0.9rem; border: 1px solid var(--border);">
                            📖 Просмотреть
                        </a>
                        <?php if (!$task['is_extended']): ?>
                            <a href="task.php?id=<?= $task['id'] ?>" 
                               class="btn-primary" 
                               style="display: inline-block; text-decoration: none; padding: 10px 20px; font-size: 0.9rem; border: none;">
                                ✓ Решить
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php if (empty($tasks)): ?>
                <div style="text-align: center; padding: 80px 20px; background: var(--card-bg); border-radius: var(--radius); box-shadow: var(--shadow);">
                    <div style="font-size: 4rem; margin-bottom: 16px;">📭</div>
                    <h3 style="font-size: 1.3rem; font-weight: 700; margin-bottom: 8px; color: var(--text-main);">Заданий пока нет</h3>
                    <p style="color: var(--text-gray);">
                        <a href="ege-variants.php" style="color: var(--accent); text-decoration: none;">Перейти к вариантам</a>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
// Прокрутка к заданию
function scrollToTask(num) {
    const element = document.getElementById('task-' + num);
    if (element) {
        element.scrollIntoView({ behavior: 'smooth', block: 'start' });
        element.style.boxShadow = '0 0 0 3px rgba(102, 126, 234, 0.3)';
        setTimeout(() => {
            element.style.boxShadow = '';
        }, 2000);
    }
}

// Проверка хэша в URL при загрузке
window.addEventListener('load', () => {
    const hash = window.location.hash;
    if (hash.startsWith('#task-')) {
        const taskNum = hash.replace('#task-', '');
        setTimeout(() => scrollToTask(taskNum), 500);
    }
});
</script>

<?php require __DIR__ . '/footer.php'; ?>