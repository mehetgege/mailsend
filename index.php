<?php
session_start();
require 'config/db.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// İstatistikleri al
$total_customers = $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
$total_templates = $pdo->query("SELECT COUNT(*) FROM templates")->fetchColumn();
$total_smtp = $pdo->query("SELECT COUNT(*) FROM smtp_settings")->fetchColumn();
$total_sent = $pdo->query("SELECT COUNT(*) FROM send_logs WHERE status = 'Gönderildi'")->fetchColumn();
?>
<?php include 'includes/header.php'; ?>
<div class="container mt-4">
    <h2>Dashboard</h2>
    <div class="row">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Toplam Müşteri</h5>
                    <p class="card-text"><?= $total_customers ?></p>
                    <a href="customer_list.php" class="btn btn-primary">Detaylar</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Toplam Şablon</h5>
                    <p class="card-text"><?= $total_templates ?></p>
                    <a href="template_list.php" class="btn btn-primary">Detaylar</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">SMTP Ayarları</h5>
                    <p class="card-text"><?= $total_smtp ?></p>
                    <a href="smtp_settings.php" class="btn btn-primary">Detaylar</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Gönderilen Mailler</h5>
                    <p class="card-text"><?= $total_sent ?></p>
                    <a href="send_logs.php" class="btn btn-primary">Detaylar</a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>