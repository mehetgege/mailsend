<?php
session_start();
require 'config/db.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Şablon Ekleme
if ($_POST && isset($_POST['add_template'])) {
    $name = trim($_POST['name']);
    $subject = trim($_POST['subject']);
    $body = $_POST['body'];

    try {
        $stmt = $pdo->prepare("INSERT INTO templates (name, subject, body) VALUES (?, ?, ?)");
        $stmt->execute([$name, $subject, $body]);
        $success_message = "Şablon başarıyla eklendi.";
    } catch (PDOException $e) {
        $error = "Şablon eklenemedi: " . $e->getMessage();
    }
}

// Şablon Güncelleme
if ($_POST && isset($_POST['edit_template'])) {
    $template_id = $_POST['template_id'];
    $name = trim($_POST['name']);
    $subject = trim($_POST['subject']);
    $body = $_POST['body'];

    try {
        $stmt = $pdo->prepare("UPDATE templates SET name = ?, subject = ?, body = ? WHERE id = ?");
        $stmt->execute([$name, $subject, $body, $template_id]);
        $success_message = "Şablon başarıyla güncellendi.";
    } catch (PDOException $e) {
        $error = "Şablon güncellenemedi: " . $e->getMessage();
    }
}

// Toplu Silme
if ($_POST && isset($_POST['bulk_delete'])) {
    $selected_ids = $_POST['selected_templates'] ?? [];
    $delete_all = isset($_POST['delete_all']) && $_POST['delete_all'] == '1';

    if ($delete_all) {
        $stmt = $pdo->query("SELECT id FROM templates");
        $selected_ids = array_column($stmt->fetchAll(), 'id');
    }

    if (empty($selected_ids)) {
        $error = "Hiçbir şablon seçilmedi.";
    } else {
        $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
        try {
            $stmt = $pdo->prepare("DELETE FROM templates WHERE id IN ($placeholders)");
            $stmt->execute($selected_ids);
            $success_message = count($selected_ids) . " şablon başarıyla silindi.";
        } catch (PDOException $e) {
            $error = "Toplu silme başarısız: " . $e->getMessage();
        }
    }
}

// Şablon Listesi
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Güvenlik için sayısal değerleri temizle
$per_page = intval($per_page);
$offset = intval($offset);

$total_stmt = $pdo->query("SELECT COUNT(*) FROM templates");
$total_records = $total_stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);

// Tüm şablon ID'lerini al (toplu silme için)
$all_ids_stmt = $pdo->query("SELECT id FROM templates");
$all_template_ids = array_column($all_ids_stmt->fetchAll(), 'id');

// Şablon listesi
$query = "SELECT * FROM templates LIMIT $per_page OFFSET $offset";
$stmt = $pdo->query($query);
$templates = $stmt->fetchAll();
$total_templates = $total_records;
?>
<?php include 'includes/header.php'; ?>
<div class="container mt-4">
    <h2>Şablon Listesi</h2>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>
    <!-- Yeni Şablon Ekleme -->
    <div class="card mb-4">
        <div class="card-header">Yeni Şablon Ekle</div>
        <div class="card-body">
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Şablon Adı</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Konu</label>
                    <input type="text" name="subject" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">İçerik</label>
                    <textarea name="body" id="editor1" class="form-control" rows="5" required></textarea>
                    <script>
                        CKEDITOR.replace('editor1');
                    </script>
                </div>
                <button type="submit" name="add_template" class="btn btn-primary">Ekle</button>
            </form>
        </div>
    </div>
    <!-- Şablon Listesi -->
    <div class="card">
        <div class="card-header">
            Şablon Listesi (Toplam: <?= $total_templates ?>)
        </div>
        <div class="card-body">
            <form method="post" id="bulkDeleteForm">
                <div class="mb-3">
                    <button type="submit" name="bulk_delete" class="btn btn-danger" 
                            onclick="return confirm('Seçilen şablonları silmek istediğinize emin misiniz?')">Toplu Sil</button>
                    <input type="hidden" name="delete_all" id="deleteAllInput" value="0">
                </div>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>
                                <input type="checkbox" id="selectAll"> Tümünü Seç
                                <div id="selectAllMessage" style="display: none; color: blue; font-size: 0.9em;">
                                    Tüm kayıtlar seçildi (toplam: <?= $total_templates ?>)
                                </div>
                            </th>
                            <th>Şablon Adı</th>
                            <th>Konu</th>
                            <th>Oluşturulma Tarihi</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($templates)): ?>
                            <tr>
                                <td colspan="5" class="text-center">Kayıtlı şablon yok.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($templates as $template): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="selected_templates[]" 
                                               class="template-checkbox" value="<?= $template['id'] ?>">
                                    </td>
                                    <td><?= htmlspecialchars($template['name']) ?></td>
                                    <td><?= htmlspecialchars($template['subject']) ?></td>
                                    <td><?= $template['created_at'] ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary me-1" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editModal-<?= $template['id'] ?>">Düzenle</button>
                                        <button type="button" class="btn btn-sm btn-info me-1" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#previewModal-<?= $template['id'] ?>">Önizleme</button>
                                        <a href="delete_template.php?id=<?= $template['id'] ?>" class="btn btn-sm btn-danger" 
                                           onclick="return confirm('Bu şablonu silmek istediğinize emin misiniz?')">Sil</a>
                                    </td>
                                </tr>
                                <!-- Düzenle Modal -->
                                <div class="modal fade" id="editModal-<?= $template['id'] ?>" tabindex="-1" 
                                     aria-labelledby="editModalLabel-<?= $template['id'] ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="editModalLabel-<?= $template['id'] ?>">Şablon Düzenle</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" 
                                                        aria-label="Close"></button>
                                            </div>
                                            <form method="post">
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label">Şablon Adı</label>
                                                        <input type="text" name="name" class="form-control" 
                                                               value="<?= htmlspecialchars($template['name']) ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Konu</label>
                                                        <input type="text" name="subject" class="form-control" 
                                                               value="<?= htmlspecialchars($template['subject']) ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">İçerik</label>
                                                        <textarea name="body" id="editor-<?= $template['id'] ?>" 
                                                                  class="form-control" rows="5" required><?= htmlspecialchars($template['body']) ?></textarea>
                                                        <script>
                                                            CKEDITOR.replace('editor-<?= $template['id'] ?>');
                                                        </script>
                                                    </div>
                                                    <input type="hidden" name="template_id" value="<?= $template['id'] ?>">
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" 
                                                            data-bs-dismiss="modal">Kapat</button>
                                                    <button type="submit" name="edit_template" 
                                                            class="btn btn-primary">Kaydet</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <!-- Önizleme Modal -->
                                <div class="modal fade" id="previewModal-<?= $template['id'] ?>" tabindex="-1" 
                                     aria-labelledby="previewModalLabel-<?= $template['id'] ?>" aria-hidden="true">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="previewModalLabel-<?= $template['id'] ?>">Şablon Önizleme</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" 
                                                        aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <h6>Konu: <?= htmlspecialchars($template['subject']) ?></h6>
                                                <hr>
                                                <div><?= $template['body'] ?></div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" 
                                                        data-bs-dismiss="modal">Kapat</button>
                                            </div>
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
<script>
let selectAllCheckbox = document.getElementById('selectAll');
let templateCheckboxes = document.querySelectorAll('.template-checkbox');
let deleteAllInput = document.getElementById('deleteAllInput');
let selectAllMessage = document.getElementById('selectAllMessage');

selectAllCheckbox.addEventListener('change', function() {
    templateCheckboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });

    if (this.checked) {
        if (confirm('Tüm şablonları seçmek istiyor musunuz? (Toplam: <?= $total_templates ?>)')) {
            deleteAllInput.value = '1';
            selectAllMessage.style.display = 'block';
        } else {
            deleteAllInput.value = '0';
            selectAllMessage.style.display = 'none';
            templateCheckboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            this.checked = false;
        }
    } else {
        deleteAllInput.value = '0';
        selectAllMessage.style.display = 'none';
    }
});

templateCheckboxes.forEach(checkbox => {
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