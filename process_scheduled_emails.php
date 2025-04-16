<?php
require 'config/db.php';
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Şu anki zamanı al
$current_time = date('Y-m-d H:i:s');

// Zamanlanmış ve gönderilmemiş mailleri al
$stmt = $pdo->prepare("SELECT * FROM scheduled_emails WHERE scheduled_at <= ? AND status = 'pending'");
$stmt->execute([$current_time]);
$scheduled_emails = $stmt->fetchAll();

foreach ($scheduled_emails as $scheduled) {
    $customer_ids = json_decode($scheduled['customer_ids'], true);
    $template_id = $scheduled['template_id'];
    $smtp_id = $scheduled['smtp_id'];
    $scheduled_id = $scheduled['id'];

    // Şablonu al
    $stmt = $pdo->prepare("SELECT * FROM templates WHERE id = ?");
    $stmt->execute([$template_id]);
    $template = $stmt->fetch();

    // SMTP ayarını al
    $stmt = $pdo->prepare("SELECT * FROM smtp_settings WHERE id = ?");
    $stmt->execute([$smtp_id]);
    $smtp = $stmt->fetch();

    if (!$template || !$smtp || empty($customer_ids)) {
        $stmt = $pdo->prepare("UPDATE scheduled_emails SET status = 'failed' WHERE id = ?");
        $stmt->execute([$scheduled_id]);
        continue;
    }

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

    // Durumu güncelle
    $status = ($error_count == 0 && $success_count > 0) ? 'sent' : 'failed';
    $stmt = $pdo->prepare("UPDATE scheduled_emails SET status = ? WHERE id = ?");
    $stmt->execute([$status, $scheduled_id]);
}

echo "Zamanlanmış mailler kontrol edildi ve gönderildi.";