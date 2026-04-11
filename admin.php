<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

try {
    require __DIR__ . '/src/includes/db.php';
} catch (Exception $e) {
    die('❌ Ошибка подключения к БД: ' . $e->getMessage());
}

// Проверка на админа (твой ID = 2)
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] !== 2) {
    die('<div style="text-align:center;padding:100px;font-family:sans-serif;">
        <h1>🔒 Доступ запрещён</h1>
        <p>Ваш ID: ' . ($_SESSION['user_id'] ?? 'не авторизован') . '</p>
        <a href="/login.php">Войти</a>
    </div>');
}

$message = '';
$messageType = '';

// ============================================
// ДОБАВЛЕНИЕ РАЗДЕЛА КЭС
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_section'])) {
    try {
        $stmt = $pdo->prepare("INSERT INTO kes (subject_id, code, name, section, parent_id) VALUES (?, ?, ?, ?, NULL)");
        $stmt->execute([$_POST['subject_id'], $_POST['code'], $_POST['name'], $_POST['name']]);
        $message = '✅ Раздел добавлен!';
        $messageType = 'success';
    } catch (PDOException $e) {
        $message = '❌ Ошибка: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// ============================================
// ДОБАВЛЕНИЕ ТЕМЫ КЭС
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_topic'])) {
    try {
        $stmt = $pdo->prepare("INSERT INTO kes (subject_id, code, name, section, parent_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['subject_id'], $_POST['code'], $_POST['name'], $_POST['section_name'], $_POST['parent_id']]);
        $message = '✅ Тема добавлена!';
        $messageType = 'success';
    } catch (PDOException $e) {
        $message = '❌ Ошибка: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// ============================================
// ДОБАВЛЕНИЕ ЗАДАНИЯ (с загрузкой картинок)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_task'])) {
    try {
        $taskImage = '';
        $solutionImage = '';
        
        // Загрузка картинки задания
        if (isset($_FILES['task_image']) && $_FILES['task_image']['error'] === 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $filename = $_FILES['task_image']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $subjectSlug = $_POST['subject_slug'];
                $fipiCode = $_POST['fipi_code'];
                $newFilename = $fipiCode . '.' . $ext;
                $uploadPath = __DIR__ . '/public/uploads/tasks/' . $subjectSlug . '/';
                
                // Создаём папку если нет
                if (!file_exists($uploadPath)) {
                    mkdir($uploadPath, 0755, true);
                }
                
                // Сохраняем файл
                if (move_uploaded_file($_FILES['task_image']['tmp_name'], $uploadPath . $newFilename)) {
                    $taskImage = $subjectSlug . '/' . $newFilename;
                }
            }
        }
        
        // Загрузка картинки решения
        if (isset($_FILES['solution_image']) && $_FILES['solution_image']['error'] === 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $filename = $_FILES['solution_image']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $subjectSlug = $_POST['subject_slug'];
                $fipiCode = $_POST['fipi_code'];
                $newFilename = $fipiCode . '_solution.' . $ext;
                $uploadPath = __DIR__ . '/public/uploads/solutions/' . $subjectSlug . '/';
                
                // Создаём папку если нет
                if (!file_exists($uploadPath)) {
                    mkdir($uploadPath, 0755, true);
                }
                
                // Сохраняем файл
                if (move_uploaded_file($_FILES['solution_image']['tmp_name'], $uploadPath . $newFilename)) {
                    $solutionImage = $subjectSlug . '/' . $newFilename;
                }
            }
        }
        
        // Добавляем задание в БД
        $stmt = $pdo->prepare("
            INSERT INTO tasks (
                subject_id, task_number, fipi_code, task_text, task_image, 
                answer_type_id, is_extended, answer, points, solution_image
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_POST['subject_id'],
            $_POST['task_number'],
            $_POST['fipi_code'],
            $_POST['task_text'],
            $taskImage,
            $_POST['answer_type_id'],
            $_POST['is_extended'],
            $_POST['answer'],
            $_POST['points'],
            $solutionImage
        ]);
        
        $taskId = $pdo->lastInsertId();
        
        // Привязка КЭС (множественный выбор)
        if (!empty($_POST['kes_ids'])) {
            foreach ($_POST['kes_ids'] as $kesId) {
                $pdo->prepare("INSERT INTO task_kes (task_id, kes_id) VALUES (?, ?)")
                    ->execute([$taskId, $kesId]);
            }
        }
        
        $message = '✅ Задание добавлено!' . ($taskImage ? ' Картинка загружена.' : '') . ($solutionImage ? ' Решение загружено.' : '');
        $messageType = 'success';
    } catch (PDOException $e) {
        $message = '❌ Ошибка: ' . $e->getMessage();
        $messageType = 'error'; $messageType = 
    }
}

// ============================================
// УДАЛЕНИЕ ЗАДАНИЯ
// ============================================
if (isset($_GET['delete_task'])) {
    try {
        $taskId = $_GET['delete_task'];
        
        // Получаем пути к картинкам перед удалением
        $stmt = $pdo->prepare("SELECT task_image, solution_image FROM tasks WHERE id = ?");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch();
        
        // Удаляем картинки с сервера
        if ($task['task_image']) {
            $filePath = __DIR__ . '/public/uploads/tasks/' . $task['task_image'];
            if (file_exists($filePath)) unlink($filePath);
        }
        if ($task['solution_image']) {
            $filePath = __DIR__ . '/public/uploads/solutions/' . $task['solution_image'];
            if (file_exists($filePath)) unlink($filePath);
        }
        
        // Удаляем из БД
        $pdo->prepare("DELETE FROM task_kes WHERE task_id = ?")->execute([$taskId]);
        $pdo->prepare("DELETE FROM tasks WHERE id = ?")->execute([$taskId]);
        
        $message = '✅ Задание удалено!';
        $messageType = 'success';
    } catch (PDOException $e) {
        $message = '❌ Ошибка: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Получаем данные
$subjects = $pdo->query("SELECT * FROM subjects ORDER BY id")->fetchAll();
$kes = $pdo->query("SELECT k.*, s.name as subject_name FROM kes k JOIN subjects s ON k.subject_id = s.id ORDER BY s.id, k.code")->fetchAll();

// Получаем все задания с предметами
$tasks = $pdo->query("
    SELECT t.*, s.name as subject_name, s.slug as subject_slug
    FROM tasks t
    JOIN subjects s ON t.subject_id = s.id
    ORDER BY t.subject_id, t.task_number
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель — ProfMat</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fa; }
        .admin-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 24px 40px; display: flex; justify-content: space-between; align-items: center; }
        .admin-header h1 { font-size: 1.8rem; }
        .admin-header a { color: #fff; text-decoration: none; background: rgba(255,255,255,0.2); padding: 10px 20px; border-radius: 8px; font-weight: 600; }
        .admin-container { max-width: 1400px; margin: 40px auto; padding: 0 24px; }
        .admin-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px; margin-bottom: 40px; }
        .admin-card { background: #fff; border-radius: 16px; padding: 24px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .admin-card h2 { margin-top: 0; font-size: 1.3rem; color: #1d1d1f; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 8px; font-size: 0.9rem; color: #6e6e73; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 1rem; font-family: inherit; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #667eea; }
        .form-group small { font-size: 0.8rem; color: #6e6e73; margin-top: 4px; display: block; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; border: none; padding: 12px 24px; border-radius: 10px; font-weight: 600; font-size: 1rem; cursor: pointer; width: 100%; }
        .btn-primary:hover { opacity: 0.9; transform: translateY(-1px); }
        .message { padding: 12px 16px; border-radius: 10px; margin-bottom: 20px; font-weight: 600; }
        .message.success { background: rgba(76, 175, 80, 0.1); color: #4caf50; border: 1px solid rgba(76, 175, 80, 0.3); }
        .message.error { background: rgba(244, 67, 54, 0.1); color: #f44336; border: 1px solid rgba(244, 67, 54, 0.3); }
        .kes-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); margin-top: 24px; }
        .kes-table th { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 16px; text-align: left; }
        .kes-table td { padding: 14px 16px; border-bottom: 1px solid #e2e8f0; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; }
        .badge-math { background: rgba(102, 126, 234, 0.15); color: #667eea; }
        .badge-rus { background: rgba(233, 30, 99, 0.15); color: #e91e63; }
        .badge-inf { background: rgba(0, 188, 212, 0.15); color: #00bcd4; }
        .badge-short { background: rgba(76, 175, 80, 0.15); color: #4caf50; }
        .badge-extended { background: rgba(255, 193, 7, 0.15); color: #ff9800; }
        .kes-checkboxes { max-height: 200px; overflow-y: auto; border: 2px solid #e2e8f0; border-radius: 10px; padding: 12px; }
        .kes-checkboxes label { display: flex; align-items: center; gap: 8px; padding: 6px 0; cursor: pointer; }
        .kes-checkboxes label:hover { background: #f8f9ff; border-radius: 6px; padding-left: 8px; margin-left: -8px; }
        .task-image-preview { max-width: 150px; border-radius: 8px; border: 1px solid #e2e8f0; }
        .btn-danger { background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%); color: #fff; border: none; padding: 8px 16px; border-radius: 8px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-danger:hover { opacity: 0.9; }
    </style>
</head>
<body>
    <div class="admin-header">
        <h1>⚙️ Админ-панель</h1>
        <a href="/index.php">← На сайт</a>
    </div>

    <div class="admin-container">
        
        <?php if ($message): ?>
            <div class="message <?= $messageType ?>"><?= $message ?></div>
        <?php endif; ?>

        <div class="admin-grid">
            
            <!-- Раздел КЭС -->
            <div class="admin-card">
                <h2>📚 Добавить раздел КЭС</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>Предмет</label>
                        <select name="subject_id" required>
                            <?php foreach ($subjects as $subj): ?>
                                <option value="<?= $subj['id'] ?>"><?= htmlspecialchars($subj['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Код раздела</label>
                        <input type="text" name="code" placeholder="1, 2, 3..." required>
                        <small>Для разделов: 1, 2, 3... (без точек)</small>
                    </div>
                    <div class="form-group">
                        <label>Название</label>
                        <input type="text" name="name" placeholder="Числа и вычисления" required>
                    </div>
                    <button type="submit" name="add_section" class="btn-primary">➕ Добавить раздел</button>
                </form>
            </div>

            <!-- Тема КЭС -->
            <div class="admin-card">
                <h2>📖 Добавить тему КЭС</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>Предмет</label>
                        <select name="subject_id" id="subject-select" required onchange="loadSections()">
                            <?php foreach ($subjects as $subj): ?>
                                <option value="<?= $subj['id'] ?>"><?= htmlspecialchars($subj['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Раздел (родитель)</label>
                        <select name="parent_id" id="section-select" required>
                            <option value="">Выберите раздел</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Код темы</label>
                        <input type="text" name="code" placeholder="1.1, 1.2, 2.1..." required>
                        <small>Для тем: 1.1, 1.2, 2.1... (с точкой)</small>
                    </div>
                    <div class="form-group">
                        <label>Название темы</label>
                        <textarea name="name" rows="3" placeholder="Натуральные и целые числа..." required></textarea>
                    </div>
                    <input type="hidden" name="section_name" id="section-name">
                    <button type="submit" name="add_topic" class="btn-primary">➕ Добавить тему</button>
                </form>
            </div>

            <!-- Задание -->
            <div class="admin-card" style="grid-column: 1 / -1;">
                <h2>📝 Добавить задание</h2>
                <form method="POST" enctype="multipart/form-data">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                        
                        <div class="form-group">
                            <label>Предмет</label>
                            <select name="subject_id" id="task-subject" required onchange="loadKESForTask()">
                                <?php foreach ($subjects as $subj): ?>
                                    <option value="<?= $subj['id'] ?>" data-slug="<?= htmlspecialchars($subj['slug']) ?>"><?= htmlspecialchars($subj['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="subject_slug" id="subject-slug">
                        </div>
                        
                        <div class="form-group">
                            <label>Номер задания</label>
                            <input type="number" name="task_number" placeholder="1" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Код ФИПИ</label>
                            <input type="text" name="fipi_code" placeholder="Fe814D" required>
                            <small>Для поиска и именования файлов</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Текст задания (кратко)</label>
                            <input type="text" name="task_text" placeholder="IP-адрес и маска сети">
                        </div>
                        
                        <div class="form-group">
                            <label>Картинка задания</label>
                            <input type="file" name="task_image" accept="image/*">
                            <small>JPG, PNG, GIF, WebP</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Тип ответа</label>
                            <select name="answer_type_id" required>
                                <option value="1">Краткий (число/текст)</option>
                                <option value="2">Развёрнутый</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Развёрнутое?</label>
                            <select name="is_extended" required>
                                <option value="0">Нет</option>
                                <option value="1">Да</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Правильный ответ</label>
                            <input type="text" name="answer" placeholder="9883255254" required>
                            <small>Для автопроверки</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Баллы</label>
                            <input type="number" name="points" value="1" min="1" max="3" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Картинка решения</label>
                            <input type="file" name="solution_image" accept="image/*">
                            <small>JPG, PNG, GIF, WebP</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>📚 Привязка КЭС (можно несколько)</label>
                        <div class="kes-checkboxes" id="kes-checkboxes">
                            <p style="color: #6e6e73; font-size: 0.9rem;">Выберите предмет выше</p>
                        </div>
                    </div>
                    
                    <button type="submit" name="add_task" class="btn-primary">➕ Добавить задание</button>
                </form>
            </div>
        </div>

        <!-- Таблица заданий -->
        <div class="admin-card">
            <h2>📋 Все задания</h2>
            <table class="kes-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Предмет</th>
                        <th>№</th>
                        <th>Код ФИПИ</th>
                        <th>Текст</th>
                        <th>Картинки</th>
                        <th>Ответ</th>
                        <th>КЭС</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tasks as $task): ?>
                        <tr>
                            <td><?= $task['id'] ?></td>
                            <td>
                                <?php if ($task['subject_id'] == 1): ?>
                                    <span class="badge badge-math">Математика</span>
                                <?php elseif ($task['subject_id'] == 2): ?>
                                    <span class="badge badge-rus">Русский</span>
                                <?php else: ?>
                                    <span class="badge badge-inf">Информатика</span>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= $task['task_number'] ?></strong></td>
                            <td><code><?= htmlspecialchars($task['fipi_code']) ?></code></td>
                            <td><?= htmlspecialchars(mb_substr($task['task_text'], 0, 50)) ?></td>
                            <td>
                                <?php if ($task['task_image']): ?>
                                    <a href="/public/uploads/tasks/<?= htmlspecialchars($task['task_image']) ?>" target="_blank">
                                        <img src="/public/uploads/tasks/<?= htmlspecialchars($task['task_image']) ?>" 
                                             class="task-image-preview" alt="Задание">
                                    </a>
                                <?php endif; ?>
                                <?php if ($task['solution_image']): ?>
                                    <a href="/public/uploads/solutions/<?= htmlspecialchars($task['solution_image']) ?>" target="_blank">
                                        <img src="/public/uploads/solutions/<?= htmlspecialchars($task['solution_image']) ?>" 
                                             class="task-image-preview" alt="Решение" style="margin-left: 8px;">
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <code><?= htmlspecialchars($task['answer']) ?></code>
                                <?php if ($task['is_extended']): ?>
                                    <span class="badge badge-extended">Развёрнутое</span>
                                <?php else: ?>
                                    <span class="badge badge-short">Краткое</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $stmt = $pdo->prepare("SELECT k.code FROM kes k JOIN task_kes tk ON k.id = tk.kes_id WHERE tk.task_id = ?");
                                $stmt->execute([$task['id']]);
                                $kesCodes = $stmt->fetchAll(PDO::FETCH_COLUMN);
                                echo implode(', ', $kesCodes);
                                ?>
                            </td>
                            <td>
                                <a href="?delete_task=<?= $task['id'] ?>" 
                                   class="btn-danger" 
                                   onclick="return confirm('Удалить задание <?= htmlspecialchars($task['fipi_code']) ?>?')">
                                    🗑️ Удалить
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Таблица КЭС -->
        <div class="admin-card">
            <h2>📋 Все КЭС</h2>
            <table class="kes-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Предмет</th>
                        <th>Код</th>
                        <th>Название</th>
                        <th>Тип</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($kes as $k): ?>
                        <tr>
                            <td><?= $k['id'] ?></td>
                            <td>
                                <?php if ($k['subject_id'] == 1): ?>
                                    <span class="badge badge-math">Математика</span>
                                <?php elseif ($k['subject_id'] == 2): ?>
                                    <span class="badge badge-rus">Русский</span>
                                <?php else: ?>
                                    <span class="badge badge-inf">Информатика</span>
                                <?php endif; ?>
                            </td>
                            <td><code><?= htmlspecialchars($k['code']) ?></code></td>
                            <td><?= htmlspecialchars(mb_substr($k['name'], 0, 80)) ?></td>
                            <td><?= $k['parent_id'] ? '📖 Тема' : '📚 Раздел' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    // Загрузка разделов для темы КЭС
    function loadSections() {
        const subjectId = document.getElementById('subject-select').value;
        const sectionSelect = document.getElementById('section-select');
        const sectionName = document.getElementById('section-name');
        
        sectionSelect.innerHTML = '<option value="">Загрузка...</option>';
        
        fetch('/admin-get-sections.php?subject_id=' + subjectId)
            .then(r => r.json())
            .then(data => {
                sectionSelect.innerHTML = '<option value="">Выберите раздел</option>';
                data.forEach(s => {
                    sectionSelect.innerHTML += `<option value="${s.id}" data-name="${s.name}">${s.code}. ${s.name}</option>`;
                });
            });
    }
    
    document.getElementById('section-select').addEventListener('change', function() {
        const selected = this.options[this.selectedIndex];
        document.getElementById('section-name').value = selected.dataset.name || '';
    });
    
    // Загрузка КЭС для привязки к заданию
    function loadKESForTask() {
        const subjectSelect = document.getElementById('task-subject');
        const selectedOption = subjectSelect.options[subjectSelect.selectedIndex];
        const subjectId = subjectSelect.value;
        const subjectSlug = selectedOption.dataset.slug;
        
        document.getElementById('subject-slug').value = subjectSlug;
        
        const kesContainer = document.getElementById('kes-checkboxes');
        kesContainer.innerHTML = '<p style="color: #6e6e73;">Загрузка...</p>';
        
        fetch('/admin-get-all-kes.php?subject_id=' + subjectId)
            .then(r => r.json())
            .then(data => {
                if (data.length === 0) {
                    kesContainer.innerHTML = '<p style="color: #6e6e73;">КЭС для этого предмета ещё нет</p>';
                    return;
                }
                
                let html = '';
                data.forEach(k => {
                    const indent = k.parent_id ? 'padding-left: 24px;' : '';
                    const prefix = k.parent_id ? '└─ ' : '📚 ';
                    html += `
                        <label style="${indent}">
                            <input type="checkbox" name="kes_ids[]" value="${k.id}">
                            <span>${prefix}${k.code}. ${k.name}</span>
                        </label>
                    `;
                });
                kesContainer.innerHTML = html;
            });
    }
    
    // Загружаем КЭС при загрузке страницы
    loadSections();
    loadKESForTask();
    </script>
</body>
</html>