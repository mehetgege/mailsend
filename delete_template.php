<?php
session_start();
require 'config/db.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM templates WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: template_list.php?success=Şablon başarıyla silindi.');
    } catch (PDOException $e) {
        header('Location: template_list.php?error=Şablon silinemedi: ' . $e->getMessage());
    }
} else {
    header('Location: template_list.php?error=Geçersiz şablon ID.');
}