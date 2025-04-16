<?php
session_start();
require 'config/db.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Zamanlanmış Gönderimler
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Güvenlik için sayısal değerleri temizle
$per_page = intval($per_page);
$offset = intval($offset);

$total_stmt = $pdo->query("SELECT COUNT(*) FROM scheduled_emails");
$total_records = $total_stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);

// Zamanlanmış gönderimleri al
$query = "SELECT se.*, t.name as template_name, s.from_email
          FROM scheduled_emails se
          JOIN templates t ON se.template_id = t.id
          JOIN smtp_settings s ON se.smtp_id = s.id
          ORDER BY se.scheduled_at DESC
          LIMIT $per_page OFFSET $offset";
$stmt = $pdo->query($query);
$scheduled_emails = $stmt->fetchAll();
?>
<?php include 'includes/header.php'; ?>
<div class="container mt-4">
    <h2>Zamanlanmış Gönderimler</h2>
    <div class="card">
        <div class="card-header">
            Zamanlanmış Gönderimler (Toplam: <?= $total_records ?>)
        </div>
        <div class="card-body">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Şablon</th>
                        <th>SMTP</th>
                        <th>Müşteri Sayısı</th>
                        <th>Zamanlanmış Tarih</th>
                        <th>Durum</th>
                        <th>Oluşturulma Tarihi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($scheduled_emails)): ?>
                        <tr>
                            <td colspan="6" class="text-center">Zamanlanmış gönderim yok.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($scheduled_emails as $scheduled): ?>
                            <tr>
                                <td><?= htmlspecialchars($scheduled['template_name']) ?></td>
                                <td><?= htmlspecialchars($scheduled['from_email']) ?></td>
                                <td><?= count(json_decode($scheduled['customer_ids'], true)) ?></td>
                                <td><?= $scheduled['scheduled_at'] ?></td>
                                <td>
                                    <?php if ($scheduled['status'] == 'pending'): ?>
                                        <span class="badge bg-warning">Bekliyor</span>
                                    <?php elseif ($scheduled['status'] == 'sent'): ?>
                                        <span class="badge bg-success">Gönderildi</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Başarısız</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $scheduled['created_at'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <!-- Sayfalandırma -->
            <nav aria-label="Sayfalandırma">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page - 1 ?>">Önceki</a>
                        </li>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page + 1 ?>">Sonraki</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>