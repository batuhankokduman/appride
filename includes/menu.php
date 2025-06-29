<div class="custom-sidebar">
  <div id="custom-sidebar-particles"></div>

  <div class="custom-sidebar-header">
    <button id="sidebar-toggle-btn" aria-label="Toggle sidebar">
      <i class="fa-solid fa-bars"></i>
    </button>
    <a href="/index.php">
      <img src="https://rideandgoo.com/wp-content/uploads/2024/09/RideAndGoo-MainLogo-Mobil.webp" alt="Logo" class="custom-sidebar-logo">
    </a>
  </div>

  <ul class="custom-sidebar-menu">
    <li><a href="/dashboard.php"><i class="fa-solid fa-chart-line" style="color: white;"></i> <span class="menu-text">Dashboard</span></a></li>
    <li><a href="/pending_reservations/pending_reservations.php"><i class="fa-solid fa-clock" style="color: white;"></i> <span class="menu-text">Bekleyen Rezervasyonlar</span></a></li>
    <li><a href="/en_reservations.php"><i class="fa-solid fa-flag" style="color: white;"></i> <span class="menu-text">Rezervasyonlar</span></a></li>
    <li><a href="/calendar/rez-calendar.php"><i class="fa-solid fa-calendar-days" style="color: white;"></i> <span class="menu-text">Rezervasyon Takvimi</span></a></li>
    <li><a href="/cron/cron_import.php"><i class="fa-solid fa-rotate" style="color: white;"></i> <span class="menu-text">Rezervasyon Çek</span></a></li>

    <li class="custom-sidebar-has-submenu">
      <a href="#"><i class="fa-brands fa-whatsapp" style="color: white;"></i> <span class="menu-text">WhatsApp Mesajları</span> <span class="submenu-arrow">▾</span></a>
      <ul class="custom-sidebar-submenu">
        <li><a href="/WAtemplate/template.php"><i class="fa-regular fa-folder-open" style="color: white;"></i> <span class="menu-text">Mesaj Şablonları</span></a></li>
        <li><a href="/WAtemplate/add.php"><i class="fa-solid fa-square-plus" style="color: white;"></i> <span class="menu-text">Yeni Şablon Ekle</span></a></li>
      </ul>
    </li>

    <li><a href="/suppliers.php"><i class="fa-solid fa-boxes-stacked" style="color: white;"></i> <span class="menu-text">Tedarikçiler</span></a></li>
    <li><a href="/costs/supplier_costs_list.php"><i class="fa-solid fa-coins" style="color: white;"></i> <span class="menu-text">Tedarikçi Maliyetleri</span></a></li>

    <li class="custom-sidebar-has-submenu">
      <a href="#"><i class="fa-solid fa-ruler-combined" style="color: white;"></i> <span class="menu-text">Fiyat Kuralları</span> <span class="submenu-arrow">▾</span></a>
      <ul class="custom-sidebar-submenu">
        <li><a href="/price_rules/price_rules.php"><i class="fa-solid fa-list-check" style="color: white;"></i> <span class="menu-text">Kural Listesi</span></a></li>
        <li><a href="/price_rules/price_rules_add.php"><i class="fa-solid fa-circle-plus" style="color: white;"></i> <span class="menu-text">Yeni Kural</span></a></li>
      </ul>
    </li>

    <li class="custom-sidebar-has-submenu">
      <a href="#"><i class="fa-solid fa-car-side" style="color: white;"></i> <span class="menu-text">Araçlar</span> <span class="submenu-arrow">▾</span></a>
      <ul class="custom-sidebar-submenu">
        <li><a href="/vehicles.php"><i class="fa-solid fa-car" style="color: white;"></i> <span class="menu-text">Araçlar</span></a></li>
        <li><a href="/vehicle_add.php"><i class="fa-solid fa-plus" style="color: white;"></i> <span class="menu-text">Araç Ekle</span></a></li>
        <li><a href="/extras.php"><i class="fa-solid fa-sliders" style="color: white;"></i> <span class="menu-text">Extralar</span></a></li>
      </ul>
    </li>

    <li class="custom-sidebar-has-submenu">
      <a href="#"><i class="fa-solid fa-file-invoice-dollar" style="color: white;"></i> <span class="menu-text">Raporlar</span> <span class="submenu-arrow">▾</span></a>
      <ul class="custom-sidebar-submenu">
        <li><a href="/reports/supplier_statements.php"><i class="fa-solid fa-users-between-lines" style="color: white;"></i> <span class="menu-text">Tedarikçi Hesapları</span></a></li>
      </ul>
    </li>

    <li class="custom-sidebar-has-submenu">
      <a href="#"><i class="fa-solid fa-gear" style="color: white;"></i> <span class="menu-text">Ayarlar</span> <span class="submenu-arrow">▾</span></a>
      <ul class="custom-sidebar-submenu">
        <li><a href="/settings/currency_settings.php"><i class="fa-solid fa-euro-sign" style="color: white;"></i> <span class="menu-text">EUR Çevirici</span></a></li>
      </ul>
    </li>
  </ul>
</div>



<script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
<script>
particlesJS("custom-sidebar-particles", {
  "particles": {
    "number": { "value": 50, "density": { "enable": true, "value_area": 800 } },
    "color": { "value": "#60a5fa" },
    "shape": { "type": "circle" },
    "opacity": { "value": 0.4 },
    "size": { "value": 3 },
    "line_linked": { "enable": true, "distance": 120, "color": "#60a5fa", "opacity": 0.2, "width": 1 },
    "move": { "enable": true, "speed": 1.5 }
  },
  "interactivity": {
    "detect_on": "canvas",
    "events": {
      "onhover": { "enable": true, "mode": "repulse" },
      "onclick": { "enable": true, "mode": "push" }
    },
    "modes": {
      "repulse": { "distance": 80 },
      "push": { "particles_nb": 3 }
    }
  },
  "retina_detect": true
});


</script>
