<?php
session_start();
require 'config/db.php';

if ($_SESSION['user_id']) {
    header('Location: index.php');
    exit;
}

$error = '';
$success_message = '';
$token = $_GET['token'] ?? '';

if (empty($token)) {
    $error = "Geçersiz sıfırlama bağlantısı.";
} else {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE reset_token = ? AND reset_token_expiry > ?");
    $stmt->execute([$token, date('Y-m-d H:i:s')]);
    $user = $stmt->fetch();

    if (!$user) {
        $error = "Geçersiz veya süresi dolmuş sıfırlama bağlantısı.";
    } elseif ($_POST && isset($_POST['reset_password'])) {
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        if (empty($password) || empty($confirm_password)) {
            $error = "Lütfen tüm alanları doldurun.";
        } elseif ($password !== $confirm_password) {
            $error = "Parolalar eşleşmiyor.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?");
            $stmt->execute([$hashed_password, $user['id']]);
            $success_message = "Parolanız başarıyla sıfırlandı. Giriş yapabilirsiniz.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parola Sıfırlama</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .reset-password-container {
            max-width: 400px;
            width: 100%;
            padding: 20px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="reset-password-container">
        <h2 class="text-center mb-4">Parola Sıfırlama</h2>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if (!$error && !$success_message): ?>
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Yeni Parola</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Parolayı Onayla</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
                <button type="submit" name="reset_password" class="btn btn-primary w-100">Parolayı Sıfırla</button>
            </form>
        <?php endif; ?>
        <div class="mt-3 text-center">
            <a href="login.php">Giriş Yap</a>
        </div>
    </div>
</body>
</html>