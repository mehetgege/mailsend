<?php
session_start();
require 'config/db.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Filtreleme
$status = $_GET['status'] ?? '';
$customer_id = $_GET['customer_id'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Güvenlik için sayısal değerleri temizle
$per_page = intval($per_page);
$offset = intval($offset);

$where = [];
$params = [];
if ($status) {
    $where[] = "sl.status LIKE ?";
    $params[] = "%$status%";
}
if ($customer_id) {
    $where[] = "sl.customer_id = ?";
    $params[] = $customer_id;
}
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Toplam kayıt sayısı
$total_stmt = $pdo->prepare("SELECT COUNT(*) 
                            FROM send_logs sl
                            JOIN customers c ON sl.customer_id = c.id
                            JOIN templates t ON sl.template_id = t.id
                            $where_sql");
$total_stmt->execute($params);
$total_records = $total_stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);

// Gönderim Kayıtları
$query = "SELECT sl.*, c.email, t.name as template_name
          FROM send_logs sl
          JOIN customers c ON sl.customer_id = c.id
          JOIN templates t ON sl.template_id = t.id
          $where_sql
          ORDER BY sl.created_at DESC
          LIMIT $per_page OFFSET $offset";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Müşteriler (Filtreleme için)
$customers = $pdo->query("SELECT * FROM customers")->fetchAll();
?>
<?php include 'includes/header.php'; ?>
<div class="container mt-4">
    <h2>Gönderim Kayıtları</h2>
    <!-- Filtreleme -->
    <div class="card mb-4">
        <div class="card-header">Filtreleme</div>
        <div class="card-body">
            <form method="get" class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Durum</label>
                    <input type="text" name="status" class="form-control" 
                           value="<?= htmlspecialchars($status) ?>" placeholder="Durum ara...">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Müşteri</label>
                    <select name="customer_id" class="form-control">
                        <option value="">Tümü</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?= $customer['id'] ?>" 
                                    <?= $customer_id == $customer['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($customer['email']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">Filtrele</button>
                </div>
            </form>
        </div>
    </div>
    <!-- Gönderim Kayıtları Listesi -->
    <div class="card">
        <div class="card-header">
            Gönderim Kayıtları (Toplam: <?= $total_records ?>)
        </div>
        <div class="card-body">
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
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="4" class="text-center">Gönderim kaydı yok.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= htmlspecialchars($log['email']) ?></td>
                                <td><?= htmlspecialchars($log['template_name']) ?></td>
                                <td>
                                    <?php if (str_starts_with($log['status'], 'Gönderildi')): ?>
                                        <span class="badge bg-success"><?= htmlspecialchars($log['status']) ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-danger"><?= htmlspecialchars($log['status']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $log['created_at'] ?></td>
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
                            <a class="page-link" href="?page=<?= $page - 1 ?>&status=<?= urlencode($status) ?>&customer_id=<?= $customer_id ?>">Önceki</a>
                        </li>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&status=<?= urlencode($status) ?>&customer_id=<?= $customer_id ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page + 1 ?>&status=<?= urlencode($status) ?>&customer_id=<?= $customer_id ?>">Sonraki</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>