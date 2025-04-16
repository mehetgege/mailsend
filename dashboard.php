<?php
session_start();
require 'config/db.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Ana Sayfa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container mt-4">
        <h2>Hoş Geldiniz!</h2>
        <p>Mail gönderim sistemine hoş geldiniz. Menüden istediğiniz işlemi seçebilirsiniz.</p>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>