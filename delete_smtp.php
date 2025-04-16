<?php
session_start();
require 'config/db.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$smtp_id = $_GET['id'] ?? null;
if ($smtp_id) {
    $stmt = $pdo->prepare("DELETE FROM smtp_accounts WHERE id = ?");
    $stmt->execute([$smtp_id]);
}

header('Location: smtp_settings.php');
exit;
?>