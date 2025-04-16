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
    $mjml_content = $_POST['mjml_content'];
    $body = $_POST['body'];

    if (empty($name) || empty($subject) || empty($body)) {
        $error = "Lütfen tüm alanları doldurun.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE templates SET name = ?, subject = ?, body = ?, mjml_content = ? WHERE id = ?");
            $stmt->execute([$name, $subject, $body, $mjml_content, $template_id]);
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
                    <label class="form-label">MJML İçeriği</label>
                    <textarea id="mjml-editor" name="mjml_content"><?= htmlspecialchars($template['mjml_content'] ?? '') ?></textarea>
                    <input type="hidden" name="body" id="mjml-html">
                </div>
                <button type="submit" name="edit_template" class="btn btn-primary">Kaydet</button>
            </form>
        </div>
    </div>
</div>
<script>
// CodeMirror ile MJML editörünü başlat
window.onload = function() {
    const mjmlEditor = CodeMirror.fromTextArea(document.getElementById('mjml-editor'), {
        mode: 'xml',
        theme: 'dracula',
        lineNumbers: true,
        indentUnit: 2,
        tabSize: 2,
        lineWrapping: true
    });

    // Form gönderilmeden önce MJML kodunu HTML’e dönüştür
    document.getElementById('templateForm').addEventListener('submit', function(e) {
        e.preventDefault(); // Formun hemen gönderilmesini engelle

        const mjmlContent = mjmlEditor.getValue();
        try {
            // MJML kodunu HTML’e dönüştür
            const result = mjml2html(mjmlContent, {
                beautify: true,
                minify: false
            });

            if (result.errors.length > 0) {
                alert('MJML kodunda hatalar var: ' + JSON.stringify(result.errors));
                return;
            }

            // Dönüştürülen HTML’i gizli input’a aktar
            document.getElementById('mjml-html').value = result.html;
            console.log('Dönüştürülen HTML:', result.html);

            // Formu gönder
            this.submit();
        } catch (error) {
            console.error('MJML dönüştürme hatası:', error);
            alert('MJML kodunu HTML’e dönüştürürken bir hata oluştu: ' + error.message);
        }
    });
};
</script>
<?php include 'includes/footer.php'; ?>