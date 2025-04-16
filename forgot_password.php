<?php
session_start();
require 'config/db.php';

if ($_SESSION['user_id']) {
    header('Location: index.php');
    exit;
}

require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error = '';
$success_message = '';
if ($_POST && isset($_POST['forgot_password'])) {
    $email = trim($_POST['email']);

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Lütfen geçerli bir e-posta adresi girin.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?");
            $stmt->execute([$token, $expiry, $user['id']]);

            $reset_link = "http://localhost/mail-sender/reset_password.php?token=$token";
            $mail = new PHPMailer(true);
            try {
                $smtp = $pdo->query("SELECT * FROM smtp_settings WHERE is_default = 1")->fetch();
                if (!$smtp) {
                    throw new Exception("Varsayılan SMTP ayarı bulunamadı.");
                }

                $mail->isSMTP();
                $mail->Host = $smtp['host'];
                $mail->SMTPAuth = true;
                $mail->Username = $smtp['username'];
                $mail->Password = $smtp['password'];
                $mail->SMTPSecure = $smtp['encryption'] === 'none' ? '' : $smtp['encryption'];
                $mail->Port = $smtp['port'];
                $mail->setFrom($smtp['from_email'], $smtp['from_name']);
                $mail->addAddress($email);
                $mail->Subject = 'Parola Sıfırlama Talebi';
                $mail->isHTML(true);
                $mail->Body = "Merhaba {$user['username']},<br><br>Parolanızı sıfırlamak için aşağıdaki bağlantıya tıklayın:<br><a href='$reset_link'>Parolayı Sıfırla</a><br><br>Bu bağlantı 1 saat içinde geçerliliğini yitirecektir.";
                $mail->AltBody = "Merhaba {$user['username']},\n\nParolanızı sıfırlamak için şu bağlantıyı kullanın:\n$reset_link\n\nBu bağlantı 1 saat içinde geçerliliğini yitirecektir.";
                $mail->send();

                $success_message = "Parola sıfırlama bağlantısı e-posta adresinize gönderildi.";
            } catch (Exception $e) {
                $error = "E-posta gönderilemedi: " . $mail->ErrorInfo;
            }
        } else {
            $error = "Bu e-posta adresiyle kayıtlı bir kullanıcı bulunamadı.";
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
        .forgot-password-container {
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
    <div class="forgot-password-container">
        <h2 class="text-center mb-4">Parola Sıfırlama</h2>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="mb-3">
                <label class="form-label">E-posta Adresi</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <button type="submit" name="forgot_password" class="btn btn-primary w-100">Sıfırlama Bağlantısı Gönder</button>
        </form>
        <div class="mt-3 text-center">
            <a href="login.php">Giriş Yap</a>
        </div>
    </div>
</body>
</html>