<?php
session_start();
require 'config/db.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$smtp_id = $_GET['id'] ?? null;
if (!$smtp_id) {
    header('Location: smtp_settings.php');
    exit;
}

// SMTP hesabını getir
$stmt = $pdo->prepare("SELECT * FROM smtp_accounts WHERE id = ?");
$stmt->execute([$smtp_id]);
$account = $stmt->fetch();

if (!$account) {
    header('Location: smtp_settings.php');
    exit;
}

// SMTP güncelleme
if ($_POST && isset($_POST['update_smtp'])) {
    $email = $_POST['email'];
    $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : $account['password'];
    $smtp_host = $_POST['smtp_host'];
    $smtp_port = $_POST['smtp_port'];
    $smtp_secure = $_POST['smtp_secure'];
    $daily_limit = (int)$_POST['daily_limit'];
    $status = $_POST['status'];

    $stmt = $pdo->prepare("UPDATE smtp_accounts SET email = ?, password = ?, smtp_host = ?, smtp_port = ?, smtp_secure = ?, daily_limit = ?, status = ? WHERE id = ?");
    $stmt->execute([$email, $password, $smtp_host, $smtp_port, $smtp_secure, $daily_limit, $status, $smtp_id]);
    header('Location: smtp_settings.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>SMTP Düzenle</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container mt-4">
        <h2>SMTP Hesabı Düzenle</h2>
        <form method="post">
            <div class="mb-3">
                <label>E-posta</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($account['email']) ?>" required>
            </div>
            <div class="mb-3">
                <label>Şifre (Değiştirmek istemiyorsanız boş bırakın)</label>
                <input type="password" name="password" class="form-control">
            </div>
            <div class="mb-3">
                <label>SMTP Host</label>
                <input type="text" name="smtp_host" class="form-control" value="<?= htmlspecialchars($account['smtp_host']) ?>" required>
            </div>
            <div class="mb-3">
                <label>SMTP Port</label>
                <input type="number" name="smtp_port" class="form-control" value="<?= $account['smtp_port'] ?>" required>
            </div>
            <div class="mb-3">
                <label>SMTP Güvenlik</label>
                <select name="smtp_secure" class="form-control">
                    <option value="tls" <?= $account['smtp_secure'] == 'tls' ? 'selected' : '' ?>>TLS</option>
                    <option value="ssl" <?= $account['smtp_secure'] == 'ssl' ? 'selected' : '' ?>>SSL</option>
                </select>
            </div>
            <div class="mb-3">
                <label>Günlük Limit</label>
                <input type="number" name="daily_limit" class="form-control" value="<?= $account['daily_limit'] ?>" required>
            </div>
            <div class="mb-3">
                <label>Durum</label>
                <select name="status" class="form-control">
                    <option value="active" <?= $account['status'] == 'active' ? 'selected' : '' ?>>Aktif</option>
                    <option value="inactive" <?= $account['status'] == 'inactive' ? 'selected' : '' ?>>Pasif</option>
                </select>
            </div>
            <button type="submit" name="update_smtp" class="btn btn-primary">Güncelle</button>
            <a href="smtp_settings.php" class="btn btn-secondary">İptal</a>
        </form>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>