<?php
session_start();
require 'config/db.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_POST) {
    $key = $_POST['key'];
    $value = $_POST['value'];
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
    $stmt->execute([$key, $value]);
}

$stmt = $pdo->query("SELECT * FROM settings");
$settings = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Ayarlar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container mt-4">
        <h2>Ayarlar</h2>
        <form method="post" class="mb-4">
            <div class="mb-3">
                <label>Ayar Adı</label>
                <input type="text" name="key" class="form-control" required>
            </div>
            <div class="mb-3">
                <label>Değer</label>
                <input type="text" name="value" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary">Ekle</button>
        </form>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Ayar Adı</th>
                    <th>Değer</th>
                    <th>Eklenme Tarihi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($settings as $setting): ?>
                    <tr>
                        <td><?= htmlspecialchars($setting['setting_key']) ?></td>
                        <td><?= htmlspecialchars($setting['setting_value']) ?></td>
                        <td><?= $setting['created_at'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>