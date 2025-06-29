
document.addEventListener('DOMContentLoaded', function () {
  const sidebar = document.querySelector('.custom-sidebar');
  const mobileToggle = document.getElementById('mobile-menu-toggle');

  if (mobileToggle && sidebar) {
    mobileToggle.addEventListener('click', () => {
      const isOpen = sidebar.style.transform === 'translateX(0%)';

      if (isOpen) {
        sidebar.style.transform = 'translateX(-100%)';
      } else {
        sidebar.style.transform = 'translateX(0%)';
      }
    });
  }
});


document.addEventListener('DOMContentLoaded', function () {
  // --- HTML Elementlerini Seçme ---
  const sidebar = document.querySelector('.custom-sidebar');
  const toggleBtn = document.getElementById('sidebar-toggle-btn');
  const pageContent = document.querySelector('.content');
  const menuItemsWithSubmenu = document.querySelectorAll('.custom-sidebar-has-submenu');
  let sidebarOverlay = document.querySelector('.sidebar-overlay');

  // Gerekli ana elementler bulunamazsa, konsola hata yaz ve script'i durdur.
  if (!sidebar) {
    console.error("Hata: '.custom-sidebar' elementi bulunamadı.");
    return;
  }
  if (!toggleBtn) {
    console.error("Hata: '#sidebar-toggle-btn' elementi bulunamadı.");
    // Eğer toggle butonu yoksa, sidebar'ı yönetmek mümkün olmayacağı için
    // bazı işlevler çalışmayabilir veya script'in devam etmesi anlamsız olabilir.
    // Şimdilik devam etmesine izin veriyoruz, belki başka işlevler vardır.
  }
  if (!pageContent) {
    console.error("Hata: '.content' elementi bulunamadı.");
    // pageContent olmadan layout güncellemeleri yapılamaz.
  }

  // Eğer HTML'de overlay elementi yoksa, dinamik olarak oluştur ve body'e ekle.
  if (!sidebarOverlay) {
    sidebarOverlay = document.createElement('div');
    sidebarOverlay.className = 'sidebar-overlay';
    document.body.appendChild(sidebarOverlay);
  }

  // --- Yardımcı Fonksiyonlar ---
  const isDesktopView = () => window.innerWidth > 768;

  // --- Ana Fonksiyon: Arayüz Durumunu Güncelleme ---
  function updateUIState() {
    const desktopView = isDesktopView();
    const sidebarIsCollapsedForDesktop = sidebar.classList.contains('collapsed');
    const sidebarIsActiveForMobile = sidebar.classList.contains('mobile-menu-active');

    // 1. Ana İçerik (.content) Alanının Kaydırılması (Sadece Masaüstü)
    if (pageContent) {
      if (desktopView) {
        pageContent.classList.toggle('sidebar-is-collapsed', sidebarIsCollapsedForDesktop);
      } else {
        pageContent.classList.remove('sidebar-is-collapsed'); // Mobilde bu sınıfı her zaman kaldır
      }
    }

    // 2. Toggle Butonunun İkonu ve ARIA Durumu
    if (toggleBtn) {
      if (desktopView) {
        toggleBtn.innerHTML = sidebarIsCollapsedForDesktop ? '<i class="fa-solid fa-angles-right"></i>' : '<i class="fa-solid fa-bars"></i>';
        toggleBtn.setAttribute('aria-expanded', !sidebarIsCollapsedForDesktop);
      } else { // Mobil Görünüm
        toggleBtn.innerHTML = sidebarIsActiveForMobile ? '<i class="fa-solid fa-times"></i>' : '<i class="fa-solid fa-bars"></i>';
        toggleBtn.setAttribute('aria-expanded', sidebarIsActiveForMobile);
      }
    }

    // 3. Mobil Menü Overlay'i
    if (sidebarOverlay) {
      sidebarOverlay.classList.toggle('active', !desktopView && sidebarIsActiveForMobile);
    }

    // 4. Ekran Boyutu Değiştiğinde Tutarlılık Sağlama
    if (desktopView && sidebarIsActiveForMobile) {
      // Eğer masaüstüne geçildiğinde mobil menü açıksa, mobil menüyü kapat.
      sidebar.classList.remove('mobile-menu-active');
      if (sidebarOverlay) sidebarOverlay.classList.remove('active');
    } else if (!desktopView && sidebarIsCollapsedForDesktop) {
      // Eğer mobile geçildiğinde masaüstü menü daraltılmışsa, bu daraltmayı kaldır (mobil kendi mantığıyla çalışsın).
      // Bu satır, CSS'iniz mobilde .collapsed sınıfını nasıl ele aldığına bağlı olarak gerekmeyebilir.
      // sidebar.classList.remove('collapsed');
    }
  }

  // --- Event Listener'lar ---

  // 1. Sidebar Daraltma/Genişletme ve Mobil Menü Açma/Kapama Butonu
  if (toggleBtn) {
    toggleBtn.addEventListener('click', function () {
      if (isDesktopView()) { // MASAÜSTÜ
        sidebar.classList.toggle('collapsed');
        // Masaüstünde daraltılıyorsa ve açık alt menüler varsa, bunları kapat
        if (sidebar.classList.contains('collapsed')) {
          menuItemsWithSubmenu.forEach(item => {
            item.classList.remove('submenu-open');
          });
        }
      } else { // MOBİL
        sidebar.classList.toggle('mobile-menu-active');
        // Mobilde menü açıldığında body scroll'unu engellemek için (opsiyonel)
        // document.body.style.overflow = sidebar.classList.contains('mobile-menu-active') ? 'hidden' : '';
      }
      updateUIState(); // Her tıklamadan sonra arayüz durumunu güncelle
    });
  }

  // 2. Mobil Menü Overlay Tıklandığında Menüyü Kapat
  if (sidebarOverlay) {
    sidebarOverlay.addEventListener('click', function () {
      if (!isDesktopView() && sidebar.classList.contains('mobile-menu-active')) {
        sidebar.classList.remove('mobile-menu-active');
        updateUIState(); // Arayüz durumunu güncelle
      }
    });
  }

  // 3. Alt Menülerin Açılıp Kapanma Mantığı
  menuItemsWithSubmenu.forEach(item => {
    const link = item.querySelector('a'); // Alt menüyü tetikleyen <a> etiketi
    const submenuElement = item.querySelector('.custom-sidebar-submenu'); // Gerçek alt menü <ul>'si

    if (link && submenuElement) {
      link.addEventListener('click', function (event) {
        // Sadece href="#" veya boş href ise (gerçek bir sayfaya gitmiyorsa) alt menüyü yönet
        const href = link.getAttribute('href');
        if (href === '#' || !href || href.trim() === '') {
          event.preventDefault(); // Varsayılan davranışı engelle

          let canToggleSubmenu = false;
          if (isDesktopView()) { // Masaüstü
            if (!sidebar.classList.contains('collapsed')) { // Sidebar daraltılmamışsa
              canToggleSubmenu = true;
            }
          } else { // Mobil
            if (sidebar.classList.contains('mobile-menu-active')) { // Mobil menü aktifse
              canToggleSubmenu = true;
            }
          }

          if (canToggleSubmenu) {
            const isOpen = item.classList.contains('submenu-open');
            // İSTEĞE BAĞLI: Diğer tüm açık alt menüleri kapat
            if (!isOpen) { // Sadece yeni bir alt menü açılıyorsa (yani şu an kapalıysa)
              menuItemsWithSubmenu.forEach(otherItem => {
                if (otherItem !== item) {
                  otherItem.classList.remove('submenu-open');
                }
              });
            }
            // Tıklanan alt menünün (<li> elementinin) 'submenu-open' sınıfını değiştir (aç/kapa)
            item.classList.toggle('submenu-open');
          }
        }
        // Eğer linkin geçerli bir URL'i varsa, normal şekilde o sayfaya gitmesine izin verilir.
      });
    }
  });

  // --- İlk Yükleme ve Pencere Boyutu Değişiklikleri ---
  window.addEventListener('resize', updateUIState); // Pencere boyutu değiştiğinde durumu güncelle
  updateUIState(); // Sayfa ilk yüklendiğinde de durumu doğru ayarla
});