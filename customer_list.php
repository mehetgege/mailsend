<?php
session_start();
require 'config/db.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// CSV Yükleme
$success_count = 0;
$error_count = 0;
$upload_message = '';
$debug_info = [];

if ($_POST && isset($_POST['upload_csv'])) {
    if (!isset($_FILES['customer_csv']) || $_FILES['customer_csv']['error'] !== UPLOAD_ERR_OK) {
        $upload_message = "Hata: Dosya yüklenemedi. Hata kodu: " . ($_FILES['customer_csv']['error'] ?? 'Bilinmeyen hata');
        $debug_info[] = "Dosya yükleme durumu: " . print_r($_FILES, true);
    } else {
        $file = $_FILES['customer_csv']['tmp_name'];
        $debug_info[] = "Dosya yolu: $file";

        if (!file_exists($file)) {
            $upload_message = "Hata: Dosya bulunamadı.";
            $debug_info[] = "Dosya kontrolü: Dosya mevcut değil.";
        } else {
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false) {
                $upload_message = "Hata: Dosya okunamadı.";
                $debug_info[] = "file() fonksiyonu başarısız.";
            } else {
                array_shift($lines);
                $debug_info[] = "Toplam satır sayısı: " . count($lines);

                foreach ($lines as $index => $line) {
                    $email = trim($line);
                    $debug_info[] = "Satır $index: $email";

                    if (empty($email)) {
                        $error_count++;
                        $debug_info[] = "Hata: Satır $index boş.";
                        continue;
                    }

                    if (!preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email)) {
                        $error_count++;
                        $debug_info[] = "Hata: Satır $index - Geçersiz e-posta ($email)";
                        continue;
                    }

                    try {
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE email = ?");
                        $stmt->execute([$email]);
                        if ($stmt->fetchColumn() > 0) {
                            $error_count++;
                            $debug_info[] = "Hata: Satır $index - E-posta zaten var ($email)";
                            continue;
                        }

                        $stmt = $pdo->prepare("INSERT INTO customers (email) VALUES (?)");
                        $stmt->execute([$email]);
                        $success_count++;
                        $debug_info[] = "Başarılı: Satır $index - $email eklendi";
                    } catch (PDOException $e) {
                        $error_count++;
                        $debug_info[] = "Hata: Satır $index - $email eklenemedi - " . $e->getMessage();
                    }
                }
                $upload_message = "$success_count e-posta başarıyla yüklendi, $error_count e-posta hatalı.";
            }
        }
    }
}

// Manuel Ekleme
if ($_POST && isset($_POST['add_customer'])) {
    $email = trim($_POST['email']);
    if (preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email)) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                $error = "Bu e-posta zaten kayıtlı.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO customers (email) VALUES (?)");
                $stmt->execute([$email]);
                $success_message = "Müşteri başarıyla eklendi.";
            }
        } catch (PDOException $e) {
            $error = "Müşteri eklenemedi: " . $e->getMessage();
        }
    } else {
        $error = "Geçersiz e-posta adresi.";
    }
}

// Toplu Silme
if ($_POST && isset($_POST['bulk_delete'])) {
    $selected_ids = $_POST['selected_customers'] ?? [];
    $delete_all = isset($_POST['delete_all']) && $_POST['delete_all'] == '1';

    if ($delete_all) {
        // Tüm filtrelenmiş kayıtları sil
        $search = $_POST['search'] ?? '';
        $min_mail_count = isset($_POST['min_mail_count']) ? (int)$_POST['min_mail_count'] : 0;
        $where = [];
        $params = [];
        if ($search) {
            $where[] = "c.email LIKE ?";
            $params[] = "%$search%";
        }
        if ($min_mail_count > 0) {
            $where[] = "COUNT(sl.id) >= ?";
            $params[] = $min_mail_count;
        }
        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $pdo->prepare("SELECT c.id
                              FROM customers c
                              LEFT JOIN send_logs sl ON sl.customer_id = c.id
                              $where_sql
                              GROUP BY c.id");
        $stmt->execute($params);
        $selected_ids = array_column($stmt->fetchAll(), 'id');
    }

    if (empty($selected_ids)) {
        $error = "Hiçbir müşteri seçilmedi.";
    } else {
        $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
        try {
            $stmt = $pdo->prepare("DELETE FROM customers WHERE id IN ($placeholders)");
            $stmt->execute($selected_ids);
            $success_message = count($selected_ids) . " müşteri başarıyla silindi.";
        } catch (PDOException $e) {
            $error = "Toplu silme başarısız: " . $e->getMessage();
        }
    }
}

// E-posta Güncelleme
if ($_POST && isset($_POST['edit_customer'])) {
    $customer_id = $_POST['customer_id'];
    $new_email = trim($_POST['new_email']);
    
    if (preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $new_email)) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE email = ? AND id != ?");
            $stmt->execute([$new_email, $customer_id]);
            if ($stmt->fetchColumn() > 0) {
                $error = "Bu e-posta zaten başka bir müşteriye kayıtlı.";
            } else {
                $stmt = $pdo->prepare("UPDATE customers SET email = ? WHERE id = ?");
                $stmt->execute([$new_email, $customer_id]);
                $success_message = "E-posta başarıyla güncellendi.";
            }
        } catch (PDOException $e) {
            $error = "E-posta güncellenemedi: " . $e->getMessage();
        }
    } else {
        $error = "Geçersiz e-posta adresi.";
    }
}

// CSV Dışa Aktarma
if (isset($_GET['export_csv'])) {
    $stmt = $pdo->query("SELECT email, created_at FROM customers");
    $customers = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="customers_export_' . date('Y-m-d_H-i-s') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['E-posta', 'Eklenme Tarihi']);
    
    foreach ($customers as $customer) {
        fputcsv($output, [$customer['email'], $customer['created_at']]);
    }
    
    fclose($output);
    exit;
}

// Müşteri Listesi (Filtreleme ve Sayfalandırma)
$search = $_GET['search'] ?? '';
$min_mail_count = isset($_GET['min_mail_count']) ? (int)$_GET['min_mail_count'] : 0;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Güvenlik için sayısal değerleri temizle
$per_page = intval($per_page);
$offset = intval($offset);

$where = [];
$params = [];
if ($search) {
    $where[] = "c.email LIKE ?";
    $params[] = "%$search%";
}
if ($min_mail_count > 0) {
    $where[] = "COUNT(sl.id) >= ?";
    $params[] = $min_mail_count;
}
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Toplam kayıt sayısı
$total_stmt = $pdo->prepare("SELECT COUNT(DISTINCT c.id)
                            FROM customers c
                            LEFT JOIN send_logs sl ON sl.customer_id = c.id
                            $where_sql");
$total_stmt->execute($params);
$total_records = $total_stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);

// Tüm müşteri ID'lerini al (toplu silme için)
$all_ids_stmt = $pdo->prepare("SELECT c.id
                              FROM customers c
                              LEFT JOIN send_logs sl ON sl.customer_id = c.id
                              $where_sql
                              GROUP BY c.id");
$all_ids_stmt->execute($params);
$all_customer_ids = array_column($all_ids_stmt->fetchAll(), 'id');

// Müşteri listesi
$query = "SELECT c.*, COUNT(sl.id) as mail_count
          FROM customers c
          LEFT JOIN send_logs sl ON sl.customer_id = c.id
          $where_sql
          GROUP BY c.id
          LIMIT $per_page OFFSET $offset";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$customers = $stmt->fetchAll();
$total_customers = $total_records;
?>
<?php include 'includes/header.php'; ?>
<div class="container mt-4">
    <h2>Müşteri Listesi</h2>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>
    <?php if (!empty($upload_message)): ?>
        <div class="alert alert-info"><?= htmlspecialchars($upload_message) ?></div>
    <?php endif; ?>
    <?php if (!empty($debug_info)): ?>
        <div class="alert alert-warning">
            <h5>Hata Ayıklama Bilgileri:</h5>
            <ul>
                <?php foreach ($debug_info as $info): ?>
                    <li><?= htmlspecialchars($info) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <!-- CSV Yükleme ve Manuel Ekleme -->
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">CSV Yükle</div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">CSV Dosyası (sadece email formatında, her satırda bir e-posta)</label>
                            <input type="file" name="customer_csv" class="form-control" accept=".csv" required>
                        </div>
                        <button type="submit" name="upload_csv" class="btn btn-primary">Yükle</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">Manuel Ekle</div>
                <div class="card-body">
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">E-posta</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <button type="submit" name="add_customer" class="btn btn-primary">Ekle</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- Filtreleme ve Dışa Aktarma -->
    <div class="card mb-4">
        <div class="card-header">Filtreleme</div>
        <div class="card-body">
            <form method="get" class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">E-posta Ara</label>
                    <input type="text" name="search" class="form-control" 
                           value="<?= htmlspecialchars($search) ?>" placeholder="E-posta ara...">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Minimum Gönderilen Mail Sayısı</label>
                    <input type="number" name="min_mail_count" class="form-control" 
                           value="<?= $min_mail_count ?>" min="0">
                </div>
                <div class="col-md-4 mb-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">Filtrele</button>
                    <a href="customer_list.php?export_csv=1" class="btn btn-success">CSV Dışa Aktar</a>
                </div>
            </form>
        </div>
    </div>
    <!-- Müşteri Listesi -->
    <div class="card">
        <div class="card-header">
            Müşteri Listesi (Toplam: <?= $total_customers ?>)
        </div>
        <div class="card-body">
            <form method="post" id="bulkDeleteForm">
                <div class="mb-3">
                    <button type="submit" name="bulk_delete" class="btn btn-danger" 
                            onclick="return confirm('Seçilen müşterileri silmek istediğinize emin misiniz?')">Toplu Sil</button>
                    <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                    <input type="hidden" name="min_mail_count" value="<?= $min_mail_count ?>">
                    <input type="hidden" name="delete_all" id="deleteAllInput" value="0">
                </div>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>
                                <input type="checkbox" id="selectAll"> Tümünü Seç
                                <div id="selectAllMessage" style="display: none; color: blue; font-size: 0.9em;">
                                    Tüm kayıtlar seçildi (filtrelenmiş toplam: <?= $total_customers ?>)
                                </div>
                            </th>
                            <th>E-posta</th>
                            <th>Gönderilen Mail Sayısı</th>
                            <th>Eklenme Tarihi</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($customers)): ?>
                            <tr>
                                <td colspan="5" class="text-center">Kayıtlı müşteri yok.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($customers as $customer): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="selected_customers[]" 
                                               class="customer-checkbox" value="<?= $customer['id'] ?>">
                                    </td>
                                    <td><?= htmlspecialchars($customer['email']) ?></td>
                                    <td>
                                        <?= $customer['mail_count'] ?>
                                        <?php if ($customer['mail_count'] > 0): ?>
                                            <span class="badge bg-success ms-2">📧</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $customer['created_at'] ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary me-1" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editModal-<?= $customer['id'] ?>">Düzenle</button>
                                        <a href="delete_customer.php?id=<?= $customer['id'] ?>" class="btn btn-sm btn-danger" 
                                           onclick="return confirm('Silmek istediğinize emin misiniz?')">Sil</a>
                                    </td>
                                </tr>
                                <!-- Düzenle Modal -->
                                <div class="modal fade" id="editModal-<?= $customer['id'] ?>" tabindex="-1" 
                                     aria-labelledby="editModalLabel-<?= $customer['id'] ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="editModalLabel-<?= $customer['id'] ?>">E-posta Düzenle</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" 
                                                        aria-label="Close"></button>
                                            </div>
                                            <form method="post">
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label">E-posta Adresi</label>
                                                        <input type="email" name="new_email" class="form-control" 
                                                               value="<?= htmlspecialchars($customer['email']) ?>" required>
                                                        <input type="hidden" name="customer_id" 
                                                               value="<?= $customer['id'] ?>">
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" 
                                                            data-bs-dismiss="modal">Kapat</button>
                                                    <button type="submit" name="edit_customer" 
                                                            class="btn btn-primary">Kaydet</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </form>
            <!-- Sayfalandırma -->
            <nav aria-label="Sayfalandırma">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&min_mail_count=<?= $min_mail_count ?>">Önceki</a>
                        </li>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&min_mail_count=<?= $min_mail_count ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&min_mail_count=<?= $min_mail_count ?>">Sonraki</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </div>
</div>
<script>
let selectAllCheckbox = document.getElementById('selectAll');
let customerCheckboxes = document.querySelectorAll('.customer-checkbox');
let deleteAllInput = document.getElementById('deleteAllInput');
let selectAllMessage = document.getElementById('selectAllMessage');

selectAllCheckbox.addEventListener('change', function() {
    customerCheckboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });

    if (this.checked) {
        if (confirm('Tüm filtrelenmiş kayıtları seçmek istiyor musunuz? (Toplam: <?= $total_customers ?>)')) {
            deleteAllInput.value = '1';
            selectAllMessage.style.display = 'block';
        } else {
            deleteAllInput.value = '0';
            selectAllMessage.style.display = 'none';
            customerCheckboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            this.checked = false;
        }
    } else {
        deleteAllInput.value = '0';
        selectAllMessage.style.display = 'none';
    }
});

customerCheckboxes.forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        if (!this.checked) {
            selectAllCheckbox.checked = false;
            deleteAllInput.value = '0';
            selectAllMessage.style.display = 'none';
        }
    });
});
</script>
<?php include 'includes/footer.php'; ?>