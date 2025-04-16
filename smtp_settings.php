<?php
session_start();
require 'config/db.php';

// Kullanıcı oturum kontrolü
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Kullanıcı rol kontrolü (sadece admin erişebilir)
if ($_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

// PHPMailer için gerekli sınıfları dosyanın başında tanımla
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// SMTP Ayar Ekleme
if ($_POST && isset($_POST['add_smtp'])) {
    $host = trim($_POST['host']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $port = (int)$_POST['port'];
    $encryption = $_POST['encryption'];
    $from_email = trim($_POST['from_email']);
    $from_name = trim($_POST['from_name']);
    $is_default = isset($_POST['is_default']) ? 1 : 0;

    try {
        if ($is_default) {
            $pdo->query("UPDATE smtp_settings SET is_default = 0");
        }
        $stmt = $pdo->prepare("INSERT INTO smtp_settings (host, username, password, port, encryption, from_email, from_name, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$host, $username, $password, $port, $encryption, $from_email, $from_name, $is_default]);
        $success_message = "SMTP ayarı başarıyla eklendi.";
    } catch (PDOException $e) {
        $error = "SMTP ayarı eklenemedi: " . $e->getMessage();
    }
}

// SMTP Ayar Güncelleme
if ($_POST && isset($_POST['edit_smtp'])) {
    $smtp_id = $_POST['smtp_id'];
    $host = trim($_POST['host']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $port = (int)$_POST['port'];
    $encryption = $_POST['encryption'];
    $from_email = trim($_POST['from_email']);
    $from_name = trim($_POST['from_name']);
    $is_default = isset($_POST['is_default']) ? 1 : 0;

    try {
        if ($is_default) {
            $pdo->query("UPDATE smtp_settings SET is_default = 0");
        }
        $stmt = $pdo->prepare("UPDATE smtp_settings SET host = ?, username = ?, password = ?, port = ?, encryption = ?, from_email = ?, from_name = ?, is_default = ? WHERE id = ?");
        $stmt->execute([$host, $username, $password, $port, $encryption, $from_email, $from_name, $is_default, $smtp_id]);
        $success_message = "SMTP ayarı başarıyla güncellendi.";
    } catch (PDOException $e) {
        $error = "SMTP ayarı güncellenemedi: " . $e->getMessage();
    }
}

// SMTP Test Etme
if ($_POST && isset($_POST['test_smtp'])) {
    $smtp_id = $_POST['smtp_id'];
    $test_email = trim($_POST['test_email']);

    if (empty($test_email) || !filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        $error = "Lütfen geçerli bir e-posta adresi girin.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM smtp_settings WHERE id = ?");
        $stmt->execute([$smtp_id]);
        $smtp = $stmt->fetch();

        if ($smtp) {
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
                $mail->addAddress($test_email);
                $mail->Subject = 'SMTP Test Maili';
                $mail->Body = 'Bu bir test e-postasıdır. SMTP ayarlarınız doğru çalışıyor.';
                $mail->send();
                $success_message = "Test e-postası başarıyla gönderildi.";
            } catch (Exception $e) {
                $error = "Test e-postası gönderilemedi: " . $mail->ErrorInfo;
            }
        } else {
            $error = "Geçersiz SMTP ayarı.";
        }
    }
}

// SMTP Ayarları Listesi
$smtp_settings = $pdo->query("SELECT * FROM smtp_settings")->fetchAll();
?>
<?php include 'includes/header.php'; ?>
<div class="container mt-4">
    <h2>SMTP Ayarları</h2>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>
    <!-- Yeni SMTP Ayar Ekleme -->
    <div class="card mb-4">
        <div class="card-header">Yeni SMTP Ayar Ekle</div>
        <div class="card-body">
            <form method="post">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">SMTP Sunucusu</label>
                        <input type="text" name="host" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Kullanıcı Adı</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Parola</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Port</label>
                        <input type="number" name="port" class="form-control" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Şifreleme</label>
                        <select name="encryption" class="form-control">
                            <option value="none">Yok</option>
                            <option value="tls">TLS</option>
                            <option value="ssl">SSL</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Gönderen E-posta</label>
                        <input type="email" name="from_email" class="form-control" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Gönderen Adı</label>
                        <input type="text" name="from_name" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Varsayılan Ayar</label>
                        <div class="form-check">
                            <input type="checkbox" name="is_default" class="form-check-input" value="1">
                            <label class="form-check-label">Bu ayarı varsayılan yap</label>
                        </div>
                    </div>
                </div>
                <button type="submit" name="add_smtp" class="btn btn-primary">Ekle</button>
            </form>
        </div>
    </div>
    <!-- SMTP Ayarları Listesi -->
    <div class="card">
        <div class="card-header">
            SMTP Ayarları Listesi (Toplam: <?= count($smtp_settings) ?>)
        </div>
        <div class="card-body">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Gönderen E-posta</th>
                        <th>Gönderen Adı</th>
                        <th>Sunucu</th>
                        <th>Port</th>
                        <th>Varsayılan</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($smtp_settings)): ?>
                        <tr>
                            <td colspan="6" class="text-center">Kayıtlı SMTP ayarı yok.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($smtp_settings as $smtp): ?>
                            <tr>
                                <td><?= htmlspecialchars($smtp['from_email']) ?></td>
                                <td><?= htmlspecialchars($smtp['from_name']) ?></td>
                                <td><?= htmlspecialchars($smtp['host']) ?></td>
                                <td><?= $smtp['port'] ?></td>
                                <td>
                                    <?php if ($smtp['is_default']): ?>
                                        <span class="badge bg-success">Varsayılan</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-primary me-1" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editModal-<?= $smtp['id'] ?>">Düzenle</button>
                                    <button type="button" class="btn btn-sm btn-info me-1" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#testModal-<?= $smtp['id'] ?>">Test Et</button>
                                    <a href="delete_smtp.php?id=<?= $smtp['id'] ?>" class="btn btn-sm btn-danger" 
                                       onclick="return confirm('Bu SMTP ayarını silmek istediğinize emin misiniz?')">Sil</a>
                                </td>
                            </tr>
                            <!-- Düzenle Modal -->
                            <div class="modal fade" id="editModal-<?= $smtp['id'] ?>" tabindex="-1" 
                                 aria-labelledby="editModalLabel-<?= $smtp['id'] ?>" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="editModalLabel-<?= $smtp['id'] ?>">SMTP Ayarını Düzenle</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" 
                                                    aria-label="Close"></button>
                                        </div>
                                        <form method="post">
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label">SMTP Sunucusu</label>
                                                    <input type="text" name="host" class="form-control" 
                                                           value="<?= htmlspecialchars($smtp['host']) ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Kullanıcı Adı</label>
                                                    <input type="text" name="username" class="form-control" 
                                                           value="<?= htmlspecialchars($smtp['username']) ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Parola</label>
                                                    <input type="password" name="password" class="form-control" 
                                                           value="<?= htmlspecialchars($smtp['password']) ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Port</label>
                                                    <input type="number" name="port" class="form-control" 
                                                           value="<?= $smtp['port'] ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Şifreleme</label>
                                                    <select name="encryption" class="form-control">
                                                        <option value="none" <?= $smtp['encryption'] == 'none' ? 'selected' : '' ?>>Yok</option>
                                                        <option value="tls" <?= $smtp['encryption'] == 'tls' ? 'selected' : '' ?>>TLS</option>
                                                        <option value="ssl" <?= $smtp['encryption'] == 'ssl' ? 'selected' : '' ?>>SSL</option>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Gönderen E-posta</label>
                                                    <input type="email" name="from_email" class="form-control" 
                                                           value="<?= htmlspecialchars($smtp['from_email']) ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Gönderen Adı</label>
                                                    <input type="text" name="from_name" class="form-control" 
                                                           value="<?= htmlspecialchars($smtp['from_name']) ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Varsayılan Ayar</label>
                                                    <div class="form-check">
                                                        <input type="checkbox" name="is_default" class="form-check-input" 
                                                               value="1" <?= $smtp['is_default'] ? 'checked' : '' ?>>
                                                        <label class="form-check-label">Bu ayarı varsayılan yap</label>
                                                    </div>
                                                </div>
                                                <input type="hidden" name="smtp_id" value="<?= $smtp['id'] ?>">
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" 
                                                        data-bs-dismiss="modal">Kapat</button>
                                                <button type="submit" name="edit_smtp" class="btn btn-primary">Kaydet</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <!-- Test Modal -->
                            <div class="modal fade" id="testModal-<?= $smtp['id'] ?>" tabindex="-1" 
                                 aria-labelledby="testModalLabel-<?= $smtp['id'] ?>" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="testModalLabel-<?= $smtp['id'] ?>">SMTP Testi</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" 
                                                    aria-label="Close"></button>
                                        </div>
                                        <form method="post">
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label">Test E-posta Adresi</label>
                                                    <input type="email" name="test_email" class="form-control" required>
                                                </div>
                                                <input type="hidden" name="smtp_id" value="<?= $smtp['id'] ?>">
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" 
                                                        data-bs-dismiss="modal">Kapat</button>
                                                <button type="submit" name="test_smtp" class="btn btn-primary">Test Gönder</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>