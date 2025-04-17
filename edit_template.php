<?php
session_start();
require 'config/db.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$template_id = $_GET['id'] ?? null;
if (!$template_id) {
    header('Location: template_list.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM templates WHERE id = ?");
$stmt->execute([$template_id]);
$template = $stmt->fetch();

if (!$template) {
    header('Location: template_list.php');
    exit;
}

if ($_POST && isset($_POST['edit_template'])) {
    $name = trim($_POST['name']);
    $subject = trim($_POST['subject']);
    $body = $_POST['body'];

    if (empty($name) || empty($subject) || empty($body)) {
        $error = "Lütfen tüm alanları doldurun.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE templates SET name = ?, subject = ?, body = ? WHERE id = ?");
            $stmt->execute([$name, $subject, $body, $template_id]);
            $success_message = "Şablon başarıyla güncellendi.";
            header('Location: template_list.php');
            exit;
        } catch (PDOException $e) {
            $error = "Şablon güncellenemedi: " . $e->getMessage();
        }
    }
}
?>
<?php include 'includes/header.php'; ?>
<div class="container mt-4">
    <h2>Şablonu Düzenle</h2>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <div class="card">
        <div class="card-body">
            <form method="post" id="templateForm">
                <div class="mb-3">
                    <label class="form-label">Şablon Adı</label>
                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($template['name']) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Konu</label>
                    <input type="text" name="subject" class="form-control" value="<?= htmlspecialchars($template['subject']) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">İçerik</label>
                    <div id="gjs"><?= $template['body'] ?></div>
                    <input type="hidden" name="body" id="gjs-html">
                </div>
                <button type="submit" name="edit_template" class="btn btn-primary">Kaydet</button>
            </form>
        </div>
    </div>
</div>
<script>
// DOM tamamen yüklendiğinde GrapeJS’yi başlat
window.onload = function() {
    try {
        if (typeof grapesjs === 'undefined') {
            console.error('GrapeJS yüklenmedi! Lütfen footer.php veya header.php dosyalarını kontrol edin.');
            return;
        }

        const editor = grapesjs.init({
            container: '#gjs',
            plugins: ['gjs-preset-newsletter'],
            pluginsOpts: {
                'gjs-preset-newsletter': {
                    modalTitleImport: 'HTML Şablonunu İçe Aktar',
                    modalTitleExport: 'HTML Şablonunu Dışa Aktar',
                }
            },
            storageManager: false,
            fromElement: true,
            height: '500px',
            width: '100%',
            canvas: {
                styles: [],
                scripts: []
            }
        });

        console.log('GrapeJS başarıyla başlatıldı:', editor);

        // Form gönderilmeden önce HTML içeriğini gizli input’a aktar
        document.getElementById('templateForm').addEventListener('submit', function(e) {
            const html = editor.getHtml();
            const css = editor.getCss();
            const fullHtml = `
                <!DOCTYPE html>
                <html>
                <head>
                    <style>${css}</style>
                </head>
                <body>${html}</body>
                </html>
            `;
            document.getElementById('gjs-html').value = fullHtml;
            console.log('Gönderilen HTML:', fullHtml);
        });

        // Editör yüklendiğinde bir başlangıç mesajı göster
        editor.on('load', function() {
            console.log('Editör tamamen yüklendi!');
        });
    } catch (error) {
        console.error('GrapeJS başlatılırken bir hata oluştu:', error);
    }
};
</script>
<?php include 'includes/footer.php'; ?>