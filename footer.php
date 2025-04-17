 <footer class="bg-light text-center py-3 mt-5">
 <p>© 2025 Mail Gönderim Sistemi. Tüm hakları saklıdır.</p>
 </footer>
 <!-- jQuery -->
 <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
 <!-- Bootstrap 5 JS -->
 <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
 <!-- DataTables JS -->
 <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
 <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
 <!-- Chart.js (Raporlar için) -->
 <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
 <!-- GrapeJS JS -->
 <script src="https://unpkg.com/grapejs@0.21.10/dist/grapejs.min.js"></script>
 <!-- GrapeJS Preset Email JS -->
 <script src="https://unpkg.com/grapejs-preset-newsletter@1.0.3/dist/grapejs-preset-newsletter.min.js"></script>
 <!-- Özel JS -->
 <script>
 // Loading animasyonu
 $(document).ready(function() {
 $('form').on('submit', function() {
 $('#loadingOverlay').show();
 });

 // DataTables Başlatma (Müşteri Listesi, Şablon Listesi, Gönderim Kayıtları gibi sayfalarda kullanılır)
 $('table.table-bordered').DataTable({
 "language": {
 "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/tr.json"
 },
 "pageLength": 10,
 "lengthMenu": [10, 25, 50, 100],
 "order": [[0, "desc"]]
 });
 });
 </script>
</body>
</html>