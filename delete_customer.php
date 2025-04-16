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
        $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: customer_list.php?success=Müşteri başarıyla silindi.');
    } catch (PDOException $e) {
        header('Location: customer_list.php?error=Müşteri silinemedi: ' . $e->getMessage());
    }
} else {
    header('Location: customer_list.php?error=Geçersiz müşteri ID.');
}