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
 $body = $_POST['body'];

 if (empty($name) || empty($subject) || empty($body)) {
 $error = "Lütfen tüm alanları doldurun.";
 } else {
 try {
 $stmt = $pdo->prepare("INSERT INTO templates (name, subject, body) VALUES (?, ?, ?)");
 $stmt->execute([$name, $subject, $body]);
 $success_message = "Şablon başarıyla eklendi.";
 header('Location: template_list.php');
 exit;
 } catch (PDOException $e) {
 $error = "Şablon eklenemedi: " . $e->getMessage();
 }
 }
}
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
 <label class="form-label">İçerik</label>
 <div id="gjs"></div>
 <input type="hidden" name="body" id="gjs-html">
 </div>
 <button type="submit" name="add_template" class="btn btn-primary">Ekle</button>
 </form>
 </div>
 </div>
</div>
<script>
// DOM tamamen yüklendiğinde GrapeJS’yi başlat
document.addEventListener('DOMContentLoaded', function() {
 if (typeof grapesjs === 'undefined') {
 console.error('GrapeJS yüklenmedi! Lütfen footer.php veya header.php dosyalarını kontrol edin.');
 return;
 }

 const editor = grapesjs.init({
 container: '#gjs',
 plugins: ['gjs-preset-newsletter'],
 pluginsOpts: {
 'gjs-preset-newsletter': {}
 },
 storageManager: false,
 fromElement: true,
 height: '500px',
 width: '100%',
 });

 // Form gönderilmeden önce HTML içeriğini gizli input’a aktar
 document.getElementById('templateForm').addEventListener('submit', function() {
 const html = editor.getHtml();
 const css = editor.getCss();
 document.getElementById('gjs-html').value = `
 <!DOCTYPE html>
 <html>
 <head>
 <style>${css}</style>
 </head>
 <body>${html}</body>
 </html>
 `;
 });
});
</script>
<?php include 'includes/footer.php'; ?>