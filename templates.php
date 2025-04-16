<?php
session_start();
require 'config/db.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Yeni şablon ekleme
if ($_POST && isset($_POST['add_template'])) {
    $name = $_POST['name'];
    $subject = $_POST['subject'];
    $body = $_POST['body'];
    $stmt = $pdo->prepare("INSERT INTO templates (name, subject, body) VALUES (?, ?, ?)");
    $stmt->execute([$name, $subject, $body]);
}

// Şablon listesi
$stmt = $pdo->query("SELECT * FROM templates");
$templates = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Mail Şablonları</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container mt-4">
        <h2>Mail Şablonları</h2>
        <form method="post" class="mb-4">
            <div class="mb-3">
                <label>Şablon Adı</label>
                <input type="text" name="name" class="form-control" required>
            </div>
            <div class="mb-3">
                <label>Konu</label>
                <input type="text" name="subject" class="form-control" required>
            </div>
            <div class="mb-3">
                <label>İçerik</label>
                <textarea name="body" class="form-control" rows="5" required></textarea>
            </div>
            <button type="submit" name="add_template" class="btn btn-primary">Ekle</button>
        </form>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Şablon Adı</th>
                    <th>Konu</th>
                    <th>İçerik</th>
                    <th>Eklenme Tarihi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($templates as $template): ?>
                    <tr>
                        <td><?= htmlspecialchars($template['name']) ?></td>
                        <td><?= htmlspecialchars($template['subject']) ?></td>
                        <td><?= htmlspecialchars($template['body']) ?></td>
                        <td><?= $template['created_at'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>