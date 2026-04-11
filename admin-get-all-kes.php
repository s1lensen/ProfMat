<?php
require __DIR__ . '/src/includes/db.php';
header('Content-Type: application/json');

$subjectId = $_GET['subject_id'] ?? 0;

$stmt = $pdo->prepare("SELECT id, code, name, parent_id FROM kes WHERE subject_id = ? ORDER BY parent_id, code");
$stmt->execute([$subjectId]);
$kes = $stmt->fetchAll();

echo json_encode($kes);