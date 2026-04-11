<?php
require __DIR__ . '/db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['task_id'])) {
    header('Location: /ege-variants.php');
    exit;
}

$taskId = $_POST['task_id'];
$userAnswer = trim($_POST['user_answer']);
$variantId = $_POST['variant_id'] ?? null;
$taskNumber = $_POST['task_number'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
$stmt->execute([$taskId]);
$task = $stmt->fetch();

if (!$task) {
    header('Location: /ege-variants.php');
    exit;
}

// Нормализация ответа
$normalizedUser = str_replace(',', '.', $userAnswer);
$normalizedUser = str_replace(' ', '', $normalizedUser);
$normalizedCorrect = str_replace(',', '.', $task['answer']);
$normalizedCorrect = str_replace(' ', '', $normalizedCorrect);

$isCorrect = false;
if ($task['answer_type_slug'] === 'number' || $task['answer_type_slug'] === 'digits') {
    $isCorrect = (floatval($normalizedUser) == floatval($normalizedCorrect));
} else {
    $isCorrect = (mb_strtolower($normalizedUser) == mb_strtolower($normalizedCorrect));
}
            
// Сохраняем ответ
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("
        INSERT INTO user_answers (user_id, task_id, user_answer, is_correct, needs_review, points_earned)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            user_answer = VALUES(user_answer),
            is_correct = VALUES(is_correct),
            points_earned = VALUES(points_earned),
            answered_at = CURRENT_TIMESTAMP
    ");
    
    $needsReview = $task['is_extended'] ? 1 : 0;
    $pointsEarned = $isCorrect ? $task['points'] : 0;
    
    $stmt->execute([
        $_SESSION['user_id'],
        $taskId,
        $userAnswer,
        $isCorrect ? 1 : 0,
        $needsReview,
        $pointsEarned
    ]);
}

// Редирект обратно на вариант
if ($variantId) {
    header("Location: /ege-variant-solve.php?id=$variantId#task-$taskNumber");
} else {
    header("Location: /task.php?id=$taskId");
}
exit;