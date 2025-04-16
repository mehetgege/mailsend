<?php
session_start();
require 'config/db.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require 'vendor/autoload.php'; // PHPMailer için
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Müşteriler ve Şablonlar
$customers = $pdo->query("SELECT * FROM customers")->fetchAll();
$templates = $pdo->query("SELECT * FROM templates")->fetchAll();
$smtp_settings = $pdo->query("SELECT * FROM smtp_settings")->fetchAll();

// Mail Gönderme veya Zamanlama
if ($_POST && (isset($_POST['send_mail']) || isset($_POST['schedule_mail']))) {
    $customer_ids = $_POST['customers'] ?? [];
    $template_id = $_POST['template'];
    $smtp_id = $_POST['smtp'];
    $send_now = isset($_POST['send_mail']);

    if (empty($customer_ids) || !$template_id || !$smtp_id) {
        $error = "Lütfen tüm alanları doldurun.";
    } else {
        if ($send_now) {
            // Hemen Gönder
            $stmt = $pdo->prepare("SELECT * FROM templates WHERE id = ?");
            $stmt->execute([$template_id]);
            $template = $stmt->fetch();

            $stmt = $pdo->prepare("SELECT * FROM smtp_settings WHERE id = ?");
            $stmt->execute([$smtp_id]);
            $smtp = $stmt->fetch();

            if (!$template || !$smtp) {
                $error = "Geçersiz şablon veya SMTP ayarı.";
            } else {
                $success_count = 0;
                $error_count = 0;

                foreach ($customer_ids as $customer_id) {
                    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
                    $stmt->execute([$customer_id]);
                    $customer = $stmt->fetch();

                    if ($customer) {
                        $mail = new PHPMailer(true);
                        try {
                            $mail->isSMTP();
                            $mail->Host = $smtp['host'];
                            $mail->SMTPAuth = true;
                            $mail->Username = $smtp['username'];
                            $mail->Password = $smtp['password'];
                            $mail->SMTPSecure = $smtp['encryption'] === 'none' ? '' : $smtp['encryption'];
                            $mail->Port = $smtp['port'];
                            $mail->setFrom($smtp['from_email'], $smtp['from_name']);
                            $mail->addAddress($customer['email']);
                            $mail->Subject = $template['subject'];
                            $mail->isHTML(true);
                            $mail->Body = $template['body'];
                            $mail->AltBody = strip_tags($template['body']);
                            $mail->send();

                            $stmt = $pdo->prepare("INSERT INTO send_logs (customer_id, template_id, status) VALUES (?, ?, ?)");
                            $stmt->execute([$customer_id, $template_id, 'Gönderildi']);
                            $success_count++;
                        } catch (Exception $e) {
                            $stmt = $pdo->prepare("INSERT INTO send_logs (customer_id, template_id, status) VALUES (?, ?, ?)");
                            $stmt->execute([$customer_id, $template_id, 'Hata: ' . $mail->ErrorInfo]);
                            $error_count++;
                        }
                    }
                }

                $success_message = "$success_count mail başarıyla gönderildi, $error_count mail gönderilemedi.";
            }
        } else {
            // Zamanla
            $scheduled_at = $_POST['scheduled_at'];
            if (empty($scheduled_at)) {
                $error = "Lütfen bir gönderim tarihi seçin.";
            } else {
                $customer_ids_json = json_encode($customer_ids);
                $stmt = $pdo->prepare("INSERT INTO scheduled_emails (customer_ids, template_id, smtp_id, scheduled_at) VALUES (?, ?, ?, ?)");
                $stmt->execute([$customer_ids_json, $template_id, $smtp_id, $scheduled_at]);
                $success_message = "Mail gönderimi başarıyla zamanlandı.";
            }
        }
    }
}
?>
<?php include 'includes/header.php'; ?>
<div class="container mt-4">
    <h2>Mail Gönder</h2>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>
    <div class="card">
        <div class="card-header">Mail Gönderim Formu</div>
        <div class="card-body">
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Müşteriler</label>
                    <select name="customers[]" class="form-control" multiple required style="height: 200px;">
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?= $customer['id'] ?>"><?= htmlspecialchars($customer['email']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted">Birden fazla müşteri seçmek için Ctrl tuşuna basılı tutun.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Şablon</label>
                    <select name="template" class="form-control" required>
                        <option value="">Şablon Seçin</option>
                        <?php foreach ($templates as $template): ?>
                            <option value="<?= $template['id'] ?>"><?= htmlspecialchars($template['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">SMTP Ayarı</label>
                    <select name="smtp" class="form-control" required>
                        <option value="">SMTP Ayarı Seçin</option>
                        <?php foreach ($smtp_settings as $smtp): ?>
                            <option value="<?= $smtp['id'] ?>">
                                <?= htmlspecialchars($smtp['from_email']) ?> 
                                <?= $smtp['is_default'] ? '(Varsayılan)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Gönderim Türü</label>
                    <div class="form-check">
                        <input type="radio" name="send_type" value="now" class="form-check-input" checked 
                               onclick="document.getElementById('scheduledAtDiv').style.display='none';">
                        <label class="form-check-label">Hemen Gönder</label>
                    </div>
                    <div class="form-check">
                        <input type="radio" name="send_type" value="schedule" class="form-check-input"
                               onclick="document.getElementById('scheduledAtDiv').style.display='block';">
                        <label class="form-check-label">Zamanla</label>
                    </div>
                </div>
                <div class="mb-3" id="scheduledAtDiv" style="display: none;">
                    <label class="form-label">Gönderim Tarihi ve Saati</label>
                    <input type="datetime-local" name="scheduled_at" class="form-control">
                </div>
                <button type="submit" name="send_mail" class="btn btn-primary" id="sendButton">Mail Gönder</button>
                <button type="submit" name="schedule_mail" class="btn btn-info" id="scheduleButton" style="display: none;">Zamanla</button>
            </form>
        </div>
    </div>
</div>
<script>
document.querySelectorAll('input[name="send_type"]').forEach(radio => {
    radio.addEventListener('change', function() {
        if (this.value === 'now') {
            document.getElementById('sendButton').style.display = 'inline-block';
            document.getElementById('scheduleButton').style.display = 'none';
        } else {
            document.getElementById('sendButton').style.display = 'none';
            document.getElementById('scheduleButton').style.display = 'inline-block';
        }
    });
});
</script>
<?php include 'includes/footer.php'; ?>