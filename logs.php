<?php
session_start();
require 'config/db.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$stmt = $pdo->query("SELECT sl.*, c.email, t.name as template_name 
                     FROM send_logs sl 
                     JOIN customers c ON sl.customer_id = c.id 
                     JOIN templates t ON sl.template_id = t.id");
$logs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Gönderim Geçmişi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container mt-4">
        <h2>Gönderim Geçmişi</h2>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Müşteri</th>
                    <th>Şablon</th>
                    <th>Durum</th>
                    <th>Gönderim Tarihi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= htmlspecialchars($log['email']) ?></td>
                        <td><?= htmlspecialchars($log['template_name']) ?></td>
                        <td><?= htmlspecialchars($log['status']) ?></td>
                        <td><?= $log['sent_at'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>