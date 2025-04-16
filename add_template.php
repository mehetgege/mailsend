<?php
session_start();
require 'config/db.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_POST && isset($_POST['add_template'])) {
    $name = trim($_POST['name']);
    $subject = trim($_POST['subject']);
    $mjml_content = $_POST['mjml_content']; // MJML kodu
    $body = $_POST['body']; // Dönüştürülmüş HTML

    if (empty($name) || empty($subject) || empty($body)) {
        $error = "Lütfen tüm alanları doldurun.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO templates (name, subject, body, mjml_content) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $subject, $body, $mjml_content]);
            $success_message = "Şablon başarıyla eklendi.";
            header('Location: template_list.php');
            exit;
        } catch (PDOException $e) {
            $error = "Şablon eklenemedi: " . $e->getMessage();
        }
    }
}

// Varsayılan MJML şablonu
$default_mjml = <<<EOD
<mjml>
  <mj-body>
    <mj-section>
      <mj-column>
        <mj-text font-size="20px" color="#000000" font-family="Helvetica">
          Merhaba Dünya!
        </mj-text>
        <mj-text>
          Bu bir test e-postasıdır. MJML ile oluşturulmuştur.
        </mj-text>
        <mj-button background-color="#F45E43" href="https://mjml.io">
          Daha Fazla Bilgi
        </mj-button>
      </mj-column>
    </mj-section>
  </mj-body>
</mjml>
EOD;
?>
<?php include 'includes/header.php'; ?>
<div class="container mt-4">
    <h2>Yeni Şablon Ekle</h2>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <div class="card">
        <div class="card-body">
            <form method="post" id="templateForm">
                <div class="mb-3">
                    <label class="form-label">Şablon Adı</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Konu</label>
                    <input type="text" name="subject" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">MJML İçeriği</label>
                    <textarea id="mjml-editor" name="mjml_content"><?= htmlspecialchars($default_mjml) ?></textarea>
                    <input type="hidden" name="body" id="mjml-html">
                </div>
                <button type="submit" name="add_template" class="btn btn-primary">Ekle</button>
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