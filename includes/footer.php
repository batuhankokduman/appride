<!-- Detay Modal -->
<div id="rez-detail-modal" class="rez-modal">
  <div class="rez-modal-content">
    <span class="rez-close">&times;</span>
    <div id="rez-modal-body">Yükleniyor...</div>
  </div>
</div>

<script src="/assets/main.js?v=<?= time(); ?>"></script>

<?php
// Sayfanın URL'sinde "calendar" geçiyorsa, calendar.js dosyasını dahil et
if (strpos($_SERVER['REQUEST_URI'], 'calendar') !== false): ?>
    <script src="/calendar/calendar.js?v=<?= time(); ?>"></script>
<?php endif; ?>

</body>
</html>
