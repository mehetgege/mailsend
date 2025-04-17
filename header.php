<!DOCTYPE html>
<html lang=" tr ">
<head>
 <meta charset="UTF-8">
 <meta name="viewport" content="width=device-width, initial-scale=1.0">
 <title>Mail Gönderim Sistemi</title>
 <!-- Bootstrap 5 CSS -->
 <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
 <!-- DataTables CSS -->
 <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
 <!-- FontAwesome (İkonlar için) -->
 <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
 <!-- GrapeJS CSS -->
 <link href="https://unpkg.com/grapejs@0.21.10/dist/css/grapejs.min.css" rel="stylesheet">
 <!-- GrapeJS Preset Email CSS -->
 <link href="https://unpkg.com/grapejs-preset-newsletter@1.0.3/dist/grapejs-preset-newsletter.css" rel="stylesheet">
 <!-- Özel CSS -->
 <style>
 body {
 background-color: #f8f9fa;
 }
 .navbar {
 box-shadow: 0 2px 4px rgba(0,0,0,0.1);
 }
 .card {
 border-radius: 10px;
 box-shadow: 0 2px 8px rgba(0,0,0,0.1);
 }
 .table {
 background-color: #fff;
 }
 .loading-overlay {
 position: fixed;
 top: 0;
 left: 0;
 width: 100%;
 height: 100%;
 background: rgba(0,0,0,0.5);
 display: none;
 justify-content: center;
 align-items: center;
 z-index: 9999;
 }
 .loading-spinner {
 font-size: 2rem;
 color: #fff;
 }
 #gjs {
 border: 1px solid #ddd;
 min-height: 500px;
 }
 </style>
</head>
<body>
 <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
 <div class="container-fluid">
 <a class="navbar-brand" href="index.php">Mail Gönderim Sistemi</a>
 <button class="navbar-toggler" type="button" data-bs-toggle="collapse" 
 data-bs-target="#navbarNav" aria-controls="navbarNav" 
 aria-expanded="false" aria-label="Toggle navigation">
 <span class="navbar-toggler-icon"></span>
 </button>
 <div class="collapse navbar-collapse" id="navbarNav">
 <ul class="navbar-nav me-auto">
 <li class="nav-item">
 <a class="nav-link" href="customer_list.php">Müşteriler</a>
 </li>
 <li class="nav-item">
 <a class="nav-link" href="template_list.php">Şablonlar</a>
 </li>
 <li class="nav-item">
 <a class="nav-link" href="smtp_settings.php">SMTP Ayarları</a>
 </li>
 <li class="nav-item">
 <a class="nav-link" href="send_mail.php">Mail Gönder</a>
 </li>
 <li class="nav-item">
 <a class="nav-link" href="send_logs.php">Gönderim Kayıtları</a>
 </li>
 <li class="nav-item">
 <a class="nav-link" href="scheduled_emails.php">Zamanlanmış Gönderimler</a>
 </li>
 <li class="nav-item">
 <a class="nav-link" href="reports.php">Raporlar</a>
 </li>
 <li class="nav-item">
 <a class="nav-link" href="user_management.php">Kullanıcı Yönetimi</a>
 </li>
 </ul>
 <ul class="navbar-nav">
 <li class="nav-item">
 <a class="nav-link" href="logout.php">Çıkış Yap</a>
 </li>
 </ul>
 </div>
 </div>
 </nav>
 <div class="loading-overlay" id="loadingOverlay">
 <div class="loading-spinner">
 <i class="fas fa-spinner fa-spin"></i>
 </div>
 </div>