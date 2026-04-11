<?php
require __DIR__ . '/src/includes/db.php';
header('Content-Type: application/json');

$subjectId = $_GET['subject_id'] ?? 0;

$stmt = $pdo->prepare("SELECT id, code, name FROM kes WHERE subject_id = ? AND parent_id IS NULL ORDER BY code");
$stmt->execute([$subjectId]);
$sections = $stmt->fetchAll();

echo json_encode($sections);