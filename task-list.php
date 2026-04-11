<?php
require __DIR__ . '/db.php';
require __DIR__ . '/header.php';

// ВАЖНО: Нет дефолтного значения! Если subject не указан — показываем выбор предметов
$subjectSlug = $_GET['subject'] ?? null;

$subject = null;
$tasks = [];
$sections = [];
$topics = [];
$selectedSection = $_GET['section'] ?? null;
$selectedTopic = $_GET['topic'] ?? null;
$answerType = $_GET['type'] ?? 'all';
$taskStatuses = [];

// Если предмет выбран — получаем его и задания
if ($subjectSlug) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM subjects WHERE slug = ? AND is_active = 1");
        $stmt->execute([$subjectSlug]);
        $subject = $stmt->fetch();
        
        if ($subject) {
            // Получаем разделы КЭС
            $stmt = $pdo->prepare("SELECT * FROM kes WHERE subject_id = ? AND parent_id IS NULL ORDER BY code");
            $stmt->execute([$subject['id']]);
            $sections = $stmt->fetchAll();
            
            if ($selectedSection) {
                $stmt = $pdo->prepare("SELECT * FROM kes WHERE parent_id = ? ORDER BY code");
                $stmt->execute([$selectedSection]);
                $topics = $stmt->fetchAll();
            }
            
            // Получаем задания (ВСЕ, включая из вариантов)
            $sql = "
                SELECT DISTINCT t.*,
                       at.name as answer_type_name,
                       at.slug as answer_type_slug
                FROM tasks t
                LEFT JOIN answer_types at ON t.answer_type_id = at.id
                WHERE t.subject_id = ?
            ";
            $params = [$subject['id']];
            
            if ($selectedSection) {
                $sql .= " AND (EXISTS (
                    SELECT 1 FROM task_kes tk 
                    WHERE tk.task_id = t.id 
                    AND (tk.kes_id = ? OR tk.kes_id IN (
                        SELECT id FROM kes WHERE parent_id = ?
                    ))
                ))";
                $params[] = $selectedSection;
                $params[] = $selectedSection;
            }
            
            if ($answerType === 'short') {
                $sql .= " AND t.is_extended = 0";
            } elseif ($answerType === 'extended') {
                $sql .= " AND t.is_extended = 1";
            }
            
            $sql .= " ORDER BY t.task_number ASC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $tasks = $stmt->fetchAll();
            
            // Получаем статусы для пользователя
            if (isset($_SESSION['user_id']) && !empty($tasks)) {
                $taskIds = array_column($tasks, 'id');
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
    } catch (PDOException $e) {
        $tasks = [];
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

<?php if (!$subjectSlug): ?>
<!-- ============================================ -->
<!-- СТРАНИЦА ВЫБОРА ПРЕДМЕТА -->
<!-- ============================================ -->
<section class="hero" style="padding: 80px 24px 60px; background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);">
    <div class="hero-content" style="flex-direction: column; text-align: center;">
        <h1 style="font-size: 2.5rem; font-weight: 900; margin: 0 0 16px; color: #fff;">
            📚 Банк заданий ЕГЭ
        </h1>
        <p style="color: rgba(255,255,255,0.8); font-size: 1.1rem; max-width: 600px;">
            Тысячи заданий для подготовки к ЕГЭ по всем предметам.<br>
            Выбирай предмет и начинай тренироваться!
        </p>
    </div>
</section>

<main class="container">
    <div style="max-width: 1200px; margin: 0 auto;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 32px; margin-top: 40px;">
            
            <!-- Математика -->
            <a href="?subject=math" style="text-decoration: none; background: var(--card-bg); border-radius: var(--radius); padding: 40px 32px; box-shadow: var(--shadow); border: 2px solid var(--border); transition: all 0.3s ease;" onmouseover="this.style.transform='translateY(-8px)'; this.style.boxShadow='0 12px 40px rgba(0,0,0,0.15)'; this.style.borderColor='var(--accent)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='var(--shadow)'; this.style.borderColor='var(--border)'">
                <div style="font-size: 4rem; margin-bottom: 16px;">📐</div>
                <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0 0 12px; color: var(--text-main);">Математика (профиль)</h2>
                <p style="color: var(--text-gray); font-size: 0.95rem; line-height: 1.6; margin-bottom: 20px;">
                    19 заданий, 32 первичных балла<br>
                    Алгебра, геометрия, теория вероятностей
                </p>
                <div style="display: inline-flex; align-items: center; gap: 8px; color: var(--accent); font-weight: 700; font-size: 1rem;">
                    Начать тренировку →
                </div>
            </a>
            
            <!-- Русский язык -->
            <a href="?subject=russian" style="text-decoration: none; background: var(--card-bg); border-radius: var(--radius); padding: 40px 32px; box-shadow: var(--shadow); border: 2px solid var(--border); transition: all 0.3s ease;" onmouseover="this.style.transform='translateY(-8px)'; this.style.boxShadow='0 12px 40px rgba(0,0,0,0.15)'; this.style.borderColor='var(--accent)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='var(--shadow)'; this.style.borderColor='var(--border)'">
                <div style="font-size: 4rem; margin-bottom: 16px;">📝</div>
                <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0 0 12px; color: var(--text-main);">Русский язык</h2>
                <p style="color: var(--text-gray); font-size: 0.95rem; line-height: 1.6; margin-bottom: 20px;">
                    27 заданий, 100 первичных баллов<br>
                    Орфография, пунктуация, сочинение
                </p>
                <div style="display: inline-flex; align-items: center; gap: 8px; color: var(--accent); font-weight: 700; font-size: 1rem;">
                    Начать тренировку →
                </div>
            </a>
            
            <!-- Информатика -->
            <a href="?subject=informatics" style="text-decoration: none; background: var(--card-bg); border-radius: var(--radius); padding: 40px 32px; box-shadow: var(--shadow); border: 2px solid var(--border); transition: all 0.3s ease;" onmouseover="this.style.transform='translateY(-8px)'; this.style.boxShadow='0 12px 40px rgba(0,0,0,0.15)'; this.style.borderColor='var(--accent)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='var(--shadow)'; this.style.borderColor='var(--border)'">
                <div style="font-size: 4rem; margin-bottom: 16px;">💻</div>
                <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0 0 12px; color: var(--text-main);">Информатика</h2>
                <p style="color: var(--text-gray); font-size: 0.95rem; line-height: 1.6; margin-bottom: 20px;">
                    27 заданий, 35 первичных баллов<br>
                    Программирование, алгоритмы, базы данных
                </p>
                <div style="display: inline-flex; align-items: center; gap: 8px; color: var(--accent); font-weight: 700; font-size: 1rem;">
                    Начать тренировку →
                </div>
            </a>
            
        </div>
        
        <!-- Преимущества -->
        <div style="margin-top: 80px; text-align: center;">
            <h2 style="font-size: 1.8rem; font-weight: 800; margin-bottom: 40px; color: var(--text-main);">
                Почему стоит тренироваться здесь?
            </h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 32px;">
                <div style="padding: 24px;">
                    <div style="font-size: 3rem; margin-bottom: 16px;">✅</div>
                    <h3 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 12px; color: var(--text-main);">Автопроверка</h3>
                    <p style="color: var(--text-gray); font-size: 0.95rem;">Мгновенная проверка ответов для заданий с кратким ответом</p>
                </div>
                <div style="padding: 24px;">
                    <div style="font-size: 3rem; margin-bottom: 16px;">📊</div>
                    <h3 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 12px; color: var(--text-main);">Статистика</h3>
                    <p style="color: var(--text-gray); font-size: 0.95rem;">Отслеживай прогресс по каждому предмету и теме</p>
                </div>
                <div style="padding: 24px;">
                    <div style="font-size: 3rem; margin-bottom: 16px;">📚</div>
                    <h3 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 12px; color: var(--text-main);">Все темы</h3>
                    <p style="color: var(--text-gray); font-size: 0.95rem;">Фильтруй задания по темам и уровню сложности</p>
                </div>
                <div style="padding: 24px;">
                    <div style="font-size: 3rem; margin-bottom: 16px;">🎯</div>
                    <h3 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 12px; color: var(--text-main);">Как на ЕГЭ</h3>
                    <p style="color: var(--text-gray); font-size: 0.95rem;">Задания из реальных вариантов ЕГЭ прошлых лет</p>
                </div>
            </div>
        </div>
    </div>
</main>

<?php else: ?>
<!-- ============================================ -->
<!-- СПИСОК ЗАДАНИЙ ВЫБРАННОГО ПРЕДМЕТА -->
<!-- ============================================ -->
<section class="hero" style="padding: 60px 24px 40px; background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);">
    <div class="hero-content" style="flex-direction: column;">
        <div class="hero-text" style="max-width: 900px;">
            <a href="task-list.php" style="color: var(--accent); text-decoration: none; font-weight: 600; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px; margin-bottom: 12px;">
                ← Назад к выбору предмета
            </a>
            <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 8px;">
                <span style="font-size: 1.5rem;"><?= htmlspecialchars($subject['icon']) ?></span>
                <h1 style="font-size: 1.8rem; font-weight: 900; margin: 0; color: #fff;"><?= htmlspecialchars($subject['name']) ?></h1>
            </div>
            <p style="color: rgba(255,255,255,0.7); font-size: 0.95rem; margin: 0;">
                <strong><?= count($tasks) ?></strong> заданий для тренировки
            </p>
        </div>
    </div>
</section>

<main class="container">
    <div style="max-width: 1200px; margin: 0 auto;">
        
        <!-- Фильтры -->
        <div style="background: var(--card-bg); border-radius: var(--radius); margin-bottom: 32px; box-shadow: var(--shadow); border: 1px solid var(--border);">
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px 24px; border-bottom: 1px solid var(--border); cursor: pointer;" onclick="toggleFilters()">
                <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 8px; margin: 0;">
                    🔍 Фильтр заданий
                </h3>
                <button id="filter-toggle-btn" style="background: var(--light-bg); border: 1px solid var(--border); padding: 8px 14px; border-radius: 8px; cursor: pointer; font-size: 0.85rem; font-weight: 600; color: var(--text-gray);">
                    📂 Развернуть фильтры
                </button>
            </div>
            <div id="filter-block" style="display: none; padding: 24px;">
                <form method="GET" action="task-list.php" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; align-items: end;">
                    <input type="hidden" name="subject" value="<?= htmlspecialchars($subject['slug']) ?>">
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 0.85rem; color: var(--text-gray);">
                            📚 Раздел КЭС
                        </label>
                        <select name="section" onchange="this.form.submit()" style="width: 100%; padding: 10px 14px; border: 2px solid var(--border); border-radius: 10px; font-size: 0.9rem; background: #fff;">
                            <option value="">Все разделы</option>
                            <?php foreach ($sections as $section): ?>
                                <option value="<?= $section['id'] ?>" <?= $selectedSection == $section['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($section['code']) ?>. <?= htmlspecialchars(mb_substr($section['name'], 0, 50)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 0.85rem; color: var(--text-gray);">
                            📖 Тема КЭС
                        </label>
                        <select name="topic" onchange="this.form.submit()" style="width: 100%; padding: 10px 14px; border: 2px solid var(--border); border-radius: 10px; font-size: 0.9rem; background: #fff;" <?= empty($topics) ? 'disabled' : '' ?>>
                            <option value="">Все темы</option>
                            <?php foreach ($topics as $topic): ?>
                                <option value="<?= $topic['id'] ?>" <?= $selectedTopic == $topic['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($topic['code']) ?>. <?= htmlspecialchars(mb_substr($topic['name'], 0, 50)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 0.85rem; color: var(--text-gray);">
                            📝 Тип ответа
                        </label>
                        <select name="type" onchange="this.form.submit()" style="width: 100%; padding: 10px 14px; border: 2px solid var(--border); border-radius: 10px; font-size: 0.9rem; background: #fff;">
                            <option value="all" <?= $answerType === 'all' ? 'selected' : '' ?>>Все типы</option>
                            <option value="short" <?= $answerType === 'short' ? 'selected' : '' ?>>Краткий</option>
                            <option value="extended" <?= $answerType === 'extended' ? 'selected' : '' ?>>Развёрнутый</option>
                        </select>
                    </div>
                    <div style="align-self: end;">
                        <a href="task-list.php?subject=<?= htmlspecialchars($subject['slug']) ?>" style="display: inline-flex; align-items: center; gap: 6px; padding: 10px 18px; background: var(--light-bg); color: var(--text-gray); border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 0.85rem;">
                            🔄 Сбросить
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Список заданий -->
        <div style="display: flex; flex-direction: column; gap: 32px;">
            <?php if (empty($tasks)): ?>
                <div style="text-align: center; padding: 80px 20px; background: var(--card-bg); border-radius: var(--radius); box-shadow: var(--shadow); border: 1px solid var(--border);">
                    <div style="font-size: 4rem; margin-bottom: 16px;">📭</div>
                    <h3 style="font-size: 1.3rem; font-weight: 700; margin-bottom: 8px; color: var(--text-main);">Заданий не найдено</h3>
                    <p style="color: var(--text-gray); margin-bottom: 24px;">
                        Для выбранных фильтров заданий не найдено
                    </p>
                    <a href="task-list.php?subject=<?= htmlspecialchars($subject['slug']) ?>" class="btn-primary" style="display: inline-block;">
                        🔄 Сбросить фильтры
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($tasks as $task):
                    $status = $taskStatuses[$task['id']] ?? null;
                    $imgSrc = fixImagePath($task['task_image']);
                ?>
                    <div id="task-<?= $task['id'] ?>" style="background: var(--card-bg); border-radius: var(--radius); padding: 32px; box-shadow: var(--shadow); border: 1px solid var(--border);">
                        <!-- Заголовок -->
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <span style="font-size: 1.5rem; font-weight: 900; color: var(--brand);">#<?= $task['task_number'] ?></span>
                                <?php if ($task['fipi_code']): ?>
                                    <span style="font-size: 0.9rem; color: var(--text-muted); background: var(--light-bg); padding: 4px 10px; border-radius: 6px; font-family: monospace; font-weight: 600;">
                                        <?= htmlspecialchars($task['fipi_code']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if ($status): ?>
                                <?php if ($status['is_correct'] == 1): ?>
                                    <span style="background: rgba(76, 175, 80, 0.15); color: #4caf50; padding: 6px 14px; border-radius: 8px; font-size: 0.85rem; font-weight: 600;">✅ Решено</span>
                                <?php else: ?>
                                    <span style="background: rgba(244, 67, 54, 0.15); color: #f44336; padding: 6px 14px; border-radius: 8px; font-size: 0.85rem; font-weight: 600;">❌ Ошибка</span>
                                <?php endif; ?>
                            <?php elseif ($task['is_extended']): ?>
                                <span style="background: rgba(255, 193, 7, 0.15); color: #ff9800; padding: 6px 14px; border-radius: 8px; font-size: 0.85rem; font-weight: 600;">📝 Развёрнутое</span>
                            <?php else: ?>
                                <span style="background: rgba(76, 175, 80, 0.15); color: #4caf50; padding: 6px 14px; border-radius: 8px; font-size: 0.85rem; font-weight: 600;">✅ Краткий</span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Картинка -->
                        <?php if ($task['task_image']): ?>
                            <div style="margin-bottom: 24px; text-align: center;">
                                <img src="<?= htmlspecialchars($imgSrc) ?>" alt="Задание №<?= $task['task_number'] ?>" style="max-width: 100%; width: 100%; height: auto; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 4px 12px rgba(0,0,0,0.08);" loading="lazy">
                            </div>
                        <?php endif; ?>
                        
                        <!-- Кнопки -->
                        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                            <a href="task.php?id=<?= $task['id'] ?>" style="padding: 10px 20px; background: var(--light-bg); color: var(--text-main); border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 0.9rem; border: 1px solid var(--border);">
                                📖 Просмотреть
                            </a>
                            <?php if (!$task['is_extended']): ?>
                                <a href="task.php?id=<?= $task['id'] ?>" class="btn-primary" style="display: inline-block; text-decoration: none; padding: 10px 20px; font-size: 0.9rem; border: none;">
                                    ✓ Решить
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
function toggleFilters() {
    const block = document.getElementById('filter-block');
    const btn = document.getElementById('filter-toggle-btn');
    if (block.style.display === 'none') {
        block.style.display = 'block';
        btn.textContent = '📁 Свернуть фильтры';
    } else {
        block.style.display = 'none';
        btn.textContent = '📂 Развернуть фильтры';
    }
}
</script>

<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>