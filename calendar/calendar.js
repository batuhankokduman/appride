document.addEventListener('DOMContentLoaded', function () {
    const monthNames = ["Ocak", "Şubat", "Mart", "Nisan", "Mayıs", "Haziran", "Temmuz", "Ağustos", "Eylül", "Ekim", "Kasım", "Aralık"];
    const dayNamesAbbr = ["Paz", "Pzt", "Sal", "Çar", "Per", "Cum", "Cmt"];

    let currentDate = new Date(); // Takvim bugünün tarihiyle başlasın
    let currentView = 'month'; // Varsayılan görünüm 'month'
    // PHP'den gelen initialCalendarEvents'i kullan. Eğer tanımsızsa boş dizi ata.
    // Bu satır, PHP'de oluşturduğunuz extras_processed alanını içeren events dizisini alır.
    let events = typeof initialCalendarEvents !== 'undefined' ? initialCalendarEvents : [];

    // Takvim Ana Elemanları
    const calendarDaysEl = document.getElementById('calendarDays');
    const currentMonthYearEl = document.getElementById('currentMonthYear');
    const prevMonthBtn = document.getElementById('prevMonth');
    const nextMonthBtn = document.getElementById('nextMonth');
    const todayBtn = document.getElementById('todayButton');
    const monthViewBtn = document.getElementById('monthViewButton');
    const weekViewBtn = document.getElementById('weekViewButton');
    const dayViewBtn = document.getElementById('dayViewButton');
    const searchInput = document.getElementById('searchInput');
    const searchButton = document.getElementById('searchButton');
    const calendarGridContainerEl = document.querySelector('.calendar-grid-container');
    const calendarListViewEl = document.getElementById('calendarListView');

    // Modal Ana Elemanları
    const modal = document.getElementById('eventModal');
    const closeButton = document.querySelector('.modal .close-button');
    
    // Yeni Detaylı Modal Kart Elemanları (ID'ler HTML ile eşleşmeli)
    const modalCardTitle = document.getElementById('modalCardTitle');
    const modalCardReservationId = document.getElementById('modalCardReservationId');
    const modalCardCustomerName = document.getElementById('modalCardCustomerName');
    const modalCardCustomerPhone = document.getElementById('modalCardCustomerPhone');
    const modalCardCustomerEmail = document.getElementById('modalCardCustomerEmail');
    const modalCardStatus = document.getElementById('modalCardStatus');
    const modalCardCreatedAt = document.getElementById('modalCardCreatedAt');
    const modalCardSelectedDateTime = document.getElementById('modalCardSelectedDateTime');
    const modalCardPickupAddress = document.getElementById('modalCardPickupAddress');
    const modalCardDropoffAddress = document.getElementById('modalCardDropoffAddress');
    const modalCardPassengers = document.getElementById('modalCardPassengers');
    const modalCardStopoversContainer = document.getElementById('modalCardStopoversContainer');
    const modalCardStopoversList = document.getElementById('modalCardStopoversList');
    const modalCardFlightInfoContainer = document.getElementById('modalCardFlightInfoContainer');
    const modalCardFlightInfoList = document.getElementById('modalCardFlightInfoList');
    const modalCardExtrasContainer = document.getElementById('modalCardExtrasContainer');
    const modalCardExtrasList = document.getElementById('modalCardExtrasList'); // Bu ID'nin HTML'de olduğundan emin olun
    const modalCardGrossPrice = document.getElementById('modalCardGrossPrice');
    const modalCardPaidAmount = document.getElementById('modalCardPaidAmount');
    const modalCardRemainingAmount = document.getElementById('modalCardRemainingAmount');
    const modalCardCommentContainer = document.getElementById('modalCardCommentContainer');
    const modalCardComment = document.getElementById('modalCardComment');
    const modalViewFullTicketLink = document.getElementById('modalViewFullTicketLink');
    const modalEditLink = document.getElementById('modalEditLink');

    // Alınış Zamanı Değiştirme Formu Elemanları
    const modalPickupDateTimeDisplayEl = document.getElementById('modalPickupDateTimeDisplay');
    const btnTogglePickupForm = document.getElementById('btnTogglePickupForm');
    const pickupFormContainer = document.getElementById('pickupFormContainer');
    const modalPickupDateInput = document.getElementById('modalPickupDateInput');
    const modalPickupTimeInput = document.getElementById('modalPickupTimeInput');
    const btnSavePickupDateTime = document.getElementById('btnSavePickupDateTime');
    const btnCancelPickupEdit = document.getElementById('btnCancelPickupEdit');

    function formatTime(timeStr) { // HH:MM:SS veya HH:MM -> HH:MM
        if (timeStr && typeof timeStr === 'string' && timeStr.length >= 5) {
            return timeStr.substring(0, 5);
        }
        return "";
    }
    
    function formatDate(dateStr) { // YYYY-MM-DD -> DD.MM.YYYY
        if (!dateStr) return 'N/A';
        try {
            const parts = dateStr.split('-');
            if (parts.length === 3) {
                const dateObj = new Date(parts[0], parts[1] - 1, parts[2]);
                return dateObj.toLocaleDateString('tr-TR', { day: '2-digit', month: '2-digit', year: 'numeric' });
            }
            return dateStr;
        } catch (e) {
            console.warn("Tarih formatlama hatası:", dateStr, e);
            return dateStr;
        }
    }

    function showEventModal(event) {
        if (!modal) { console.error("Modal DOM elemanı bulunamadı!"); return; }

        modalCardTitle.textContent = (event.customer_name && event.rule_name) ? `${event.customer_name} / ${event.rule_name}` : (event.customer_name || event.rule_name || 'Rezervasyon Detayı');
        if (modalCardReservationId) modalCardReservationId.textContent = `ID: ${event.reservation_id_text || 'N/A'}`;

        if (modalCardCustomerName) modalCardCustomerName.textContent = event.customer_name || 'N/A';
        if (modalCardCustomerPhone) modalCardCustomerPhone.textContent = event.customer_phone || 'N/A';
        if (modalCardCustomerEmail) modalCardCustomerEmail.textContent = event.customer_email || 'N/A';
        
        if (modalCardStatus) {
            const statusClass = (event.status || 'default').toLowerCase().replace(/ /g, '_');
            modalCardStatus.innerHTML = `<span class="status-${statusClass}">${event.status || 'Belirtilmemiş'}</span>`;
        }
        if (modalCardCreatedAt) {
            modalCardCreatedAt.textContent = event.reservation_created_at ? formatDate(event.reservation_created_at.substring(0,10)) + " " + formatTime(event.reservation_created_at.substring(11)) : 'N/A';
        }
        
        let selectedDateTimeDisplay = 'N/A';
        if (event.schedule_selected_date_raw) {
            selectedDateTimeDisplay = formatDate(event.schedule_selected_date_raw);
            if (event.schedule_selected_time_raw) {
                selectedDateTimeDisplay += ` - ${formatTime(event.schedule_selected_time_raw)}`;
            }
        }
        if (modalCardSelectedDateTime) modalCardSelectedDateTime.textContent = selectedDateTimeDisplay;

        if (modalPickupDateTimeDisplayEl) {
            if (event.schedule_pickup_date_raw && event.schedule_pickup_time_raw) {
                let displayText = formatDate(event.schedule_pickup_date_raw);
                displayText += ` - ${formatTime(event.schedule_pickup_time_raw)}`;
                modalPickupDateTimeDisplayEl.innerHTML = `<span style="color: #198754; font-weight: 500;">${displayText}</span>`;
            } else {
                modalPickupDateTimeDisplayEl.innerHTML = `<span style="color: #dc3545; font-style: italic;">Alınış tarihi/saati henüz ayarlanmamış.</span>`;
            }
        }

        let formInputDate = event.schedule_pickup_date_raw;
        let formInputTime = event.schedule_pickup_time_raw;
        if (!formInputDate) {
            formInputDate = event.schedule_selected_date_raw;
            if (!formInputTime) { 
                 formInputTime = event.schedule_selected_time_raw;
            }
        }
        if (modalPickupDateInput) modalPickupDateInput.value = formInputDate || '';
        if (modalPickupTimeInput) modalPickupTimeInput.value = formatTime(formInputTime);
        if (btnSavePickupDateTime) btnSavePickupDateTime.dataset.reservationId = event.reservation_id_text;
        if (pickupFormContainer) pickupFormContainer.style.display = 'none'; 
        if (btnTogglePickupForm) btnTogglePickupForm.textContent = 'Alınış Zamanını Değiştir';

        if (modalCardPickupAddress) modalCardPickupAddress.textContent = event.pickup_address || 'N/A';
        if (modalCardDropoffAddress) modalCardDropoffAddress.textContent = event.dropoff_address || 'N/A';
        if (modalCardPassengers) modalCardPassengers.textContent = `${event.passengers_adults || 0} Yetişkin, ${event.passengers_children || 0} Çocuk`;

        try {
            const stopovers = JSON.parse(event.stopovers_json || '[]');
            modalCardStopoversList.innerHTML = '';
            if (stopovers.length > 0) {
                stopovers.forEach(stop => { const li = document.createElement('li'); li.textContent = `${stop.address || 'Adres Yok'}${stop.duration ? ` (Bekleme: ${stop.duration} dk)` : ''}`; modalCardStopoversList.appendChild(li); });
                modalCardStopoversContainer.style.display = 'block';
            } else { modalCardStopoversContainer.style.display = 'none'; }
        } catch (e) { console.error("Durak JSON parse hatası:", e); if (modalCardStopoversContainer) modalCardStopoversContainer.style.display = 'none'; }
        
        try {
            const flightInfo = JSON.parse(event.flight_info_json || '[]');
            modalCardFlightInfoList.innerHTML = '';
            if (flightInfo.length > 0) {
                flightInfo.forEach(item => { const p = document.createElement('p'); p.innerHTML = `<strong>${item.label || 'Bilgi'}:</strong> ${item.value || 'N/A'}`; modalCardFlightInfoList.appendChild(p); });
                modalCardFlightInfoContainer.style.display = 'block';
            } else { modalCardFlightInfoContainer.style.display = 'none'; }
        } catch (e) { console.error("Uçuş JSON parse hatası:", e); if (modalCardFlightInfoContainer) modalCardFlightInfoContainer.style.display = 'none'; }

        // --- GÜNCELLENMİŞ: Ekstra Hizmetler Bölümü ---
        if (modalCardExtrasContainer && modalCardExtrasList) {
            modalCardExtrasList.innerHTML = ''; // Önce listeyi temizle
            // event.extras_processed PHP tarafından hazırlanan işlenmiş diziyi kullanıyoruz
            if (event.extras_processed && event.extras_processed.length > 0) {
                event.extras_processed.forEach(extra => {
                    const li = document.createElement('li');
                    // 'name' alanı artık PHP'den geliyor.
                    li.textContent = `${extra.name}, Adet: ${extra.quantity}${extra.note ? ' (Not: ' + extra.note + ')' : ''}`;
                    modalCardExtrasList.appendChild(li);
                });
                modalCardExtrasContainer.style.display = 'block';
            } else {
                modalCardExtrasContainer.style.display = 'none';
            }
        }
        // --- Ekstra Hizmetler Bölümü SONU ---

        const currency = event.currency || '';
        if (modalCardGrossPrice) modalCardGrossPrice.textContent = `${parseFloat(event.gross_price || 0).toFixed(2)} ${currency}`;
        if (modalCardPaidAmount) modalCardPaidAmount.textContent = `${parseFloat(event.paid_amount || 0).toFixed(2)} ${currency}`;
        if (modalCardRemainingAmount) modalCardRemainingAmount.textContent = `${parseFloat(event.remaining_amount || 0).toFixed(2)} ${currency}`;

        if (modalCardCommentContainer && modalCardComment) {
            if (event.description) {
                modalCardComment.textContent = event.description;
                modalCardCommentContainer.style.display = 'block';
            } else { modalCardCommentContainer.style.display = 'none'; }
        }

        if (modalViewFullTicketLink) {
            if (event.reservation_id_text && event.access_token) {
                // REZ.PHP DOSYANIZIN DOĞRU YOLUNU KONTROL EDİN (örn: /rez.php veya baska bir yol)
                modalViewFullTicketLink.href = `/rez.php?id=${event.reservation_id_text}&t=${event.access_token}`; 
                modalViewFullTicketLink.style.display = 'inline-block';
            } else { modalViewFullTicketLink.style.display = 'none'; }
        }
        if (modalEditLink) {
            if (event.id) { // event.id, veritabanındaki primary key olmalı
                // ADMİN DÜZENLEME SAYFANIZIN YOLUNU KONTROL EDİN
                modalEditLink.href = `/reservation_detail.php?id=${event.reservation_id_text}`; 
                modalEditLink.style.display = 'inline-block';
            } else { modalEditLink.style.display = 'none'; }
        }

        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
    
    function renderCalendar() { if (!currentMonthYearEl || !calendarDaysEl || !calendarListViewEl) { console.error("Takvim DOM elemanları bulunamadı!"); return; } calendarDaysEl.innerHTML = ''; calendarListViewEl.innerHTML = ''; currentMonthYearEl.textContent = `${monthNames[currentDate.getMonth()]} ${currentDate.getFullYear()}`; if (currentView === 'month') { calendarGridContainerEl.style.display = 'block'; calendarListViewEl.style.display = 'none'; renderMonthView(); } else if (currentView === 'week') { calendarGridContainerEl.style.display = 'none'; calendarListViewEl.style.display = 'block'; renderWeekView(); } else if (currentView === 'day') { calendarGridContainerEl.style.display = 'none'; calendarListViewEl.style.display = 'block'; renderDayView(); } updateActiveViewButton(); }
    function renderMonthView() { const year = currentDate.getFullYear(); const month = currentDate.getMonth(); const firstDayOfMonthRaw = new Date(year, month, 1).getDay(); const daysInMonth = new Date(year, month + 1, 0).getDate(); const firstDayOfMonth = (firstDayOfMonthRaw === 0) ? 6 : firstDayOfMonthRaw - 1; const MAX_EVENTS_VISIBLE_IN_CELL = 2; const daysInPrevMonth = new Date(year, month, 0).getDate(); for (let i = firstDayOfMonth - 1; i >= 0; i--) { const dayCell = document.createElement('div'); dayCell.classList.add('day-cell', 'other-month'); dayCell.innerHTML = `<div class="day-number">${daysInPrevMonth - i}</div><div class="events-list"></div>`; calendarDaysEl.appendChild(dayCell); } for (let day = 1; day <= daysInMonth; day++) { const dayCell = document.createElement('div'); dayCell.classList.add('day-cell'); const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`; const dayNumberDiv = document.createElement('div'); dayNumberDiv.classList.add('day-number'); dayNumberDiv.textContent = day; dayCell.appendChild(dayNumberDiv); const eventsListDiv = document.createElement('div'); eventsListDiv.classList.add('events-list'); const todaysEvents = getEventsForDate(dateStr, searchInput.value).sort((a,b) => (a.time || "").localeCompare(b.time || "")); const eventsToShow = todaysEvents.slice(0, MAX_EVENTS_VISIBLE_IN_CELL); const remainingEventsCount = todaysEvents.length - eventsToShow.length; eventsToShow.forEach(event => { const eventEl = document.createElement('div'); eventEl.classList.add('event'); const eventTimeDisplay = formatTime(event.time); eventEl.textContent = eventTimeDisplay ? `${eventTimeDisplay} ${event.title}` : event.title; eventEl.title = eventTimeDisplay ? `${eventTimeDisplay} ${event.title}` : event.title; eventEl.addEventListener('click', (e) => { e.stopPropagation(); showEventModal(event); }); eventsListDiv.appendChild(eventEl); }); if (remainingEventsCount > 0) { const moreEventsEl = document.createElement('div'); moreEventsEl.classList.add('more-events-indicator'); moreEventsEl.textContent = `+${remainingEventsCount} daha fazla...`; moreEventsEl.title = `${remainingEventsCount} tane daha etkinlik var`; moreEventsEl.addEventListener('click', (e) => { e.stopPropagation(); currentDate = new Date(year, month, day); currentView = 'day'; renderCalendar(); }); eventsListDiv.appendChild(moreEventsEl); } dayCell.appendChild(eventsListDiv); if (day === new Date().getDate() && month === new Date().getMonth() && year === new Date().getFullYear()) { dayCell.classList.add('today'); } calendarDaysEl.appendChild(dayCell); } const totalCells = firstDayOfMonth + daysInMonth; const nextMonthDays = (totalCells % 7 === 0) ? 0 : 7 - (totalCells % 7); for (let i = 1; i <= nextMonthDays; i++) { const dayCell = document.createElement('div'); dayCell.classList.add('day-cell', 'other-month'); dayCell.innerHTML = `<div class="day-number">${i}</div><div class="events-list"></div>`; calendarDaysEl.appendChild(dayCell); } }
    function renderWeekView() { const year = currentDate.getFullYear(); const month = currentDate.getMonth(); const day = currentDate.getDate(); let currentDayOfWeek = currentDate.getDay(); if (currentDayOfWeek === 0) currentDayOfWeek = 7; const startDate = new Date(year, month, day - currentDayOfWeek + 1); for (let i = 0; i < 7; i++) { const loopDate = new Date(startDate); loopDate.setDate(startDate.getDate() + i); const dateStr = `${loopDate.getFullYear()}-${String(loopDate.getMonth() + 1).padStart(2, '0')}-${String(loopDate.getDate()).padStart(2, '0')}`; const todaysEvents = getEventsForDate(dateStr, searchInput.value).sort((a,b) => (a.time || "").localeCompare(b.time || "")); const dayGroup = document.createElement('div'); dayGroup.classList.add('list-day-group'); const dayHeader = document.createElement('div'); dayHeader.classList.add('list-day-header'); const dayIndexForName = loopDate.getDay(); dayHeader.textContent = `${dayNamesAbbr[dayIndexForName === 0 ? 6 : dayIndexForName -1]}, ${loopDate.getDate()} ${monthNames[loopDate.getMonth()]} ${loopDate.getFullYear()}`; if (loopDate.toDateString() === new Date().toDateString()) { dayHeader.innerHTML += ' <span style="color:var(--primary-color); font-weight:normal;">(Bugün)</span>'; } dayGroup.appendChild(dayHeader); if (todaysEvents.length > 0) { todaysEvents.forEach(event => { const eventItem = document.createElement('div'); eventItem.classList.add('list-event-item'); eventItem.innerHTML = `${event.time ? `<span class="event-time">${formatTime(event.time)}</span>` : '<span class="event-time">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>'} <span class="event-title">${event.title}</span>`; eventItem.addEventListener('click', () => showEventModal(event)); dayGroup.appendChild(eventItem); }); } else { const noEventItem = document.createElement('div'); noEventItem.classList.add('list-event-item'); noEventItem.textContent = "Rezervasyon bulunmamaktadır."; noEventItem.style.fontStyle = "italic"; noEventItem.style.color = "#888"; dayGroup.appendChild(noEventItem); } calendarListViewEl.appendChild(dayGroup); } }
    function renderDayView() { const year = currentDate.getFullYear(); const month = currentDate.getMonth(); const day = currentDate.getDate(); const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`; const dayGroup = document.createElement('div'); dayGroup.classList.add('list-day-group'); const dayHeader = document.createElement('div'); dayHeader.classList.add('list-day-header'); const dayIndexForName = currentDate.getDay(); dayHeader.textContent = `${dayNamesAbbr[dayIndexForName === 0 ? 6 : dayIndexForName -1]}, ${day} ${monthNames[month]} ${year}`; if (currentDate.toDateString() === new Date().toDateString()) { dayHeader.innerHTML += ' <span style="color:var(--primary-color); font-weight:normal;">(Bugün)</span>'; } dayGroup.appendChild(dayHeader); const todaysEvents = getEventsForDate(dateStr, searchInput.value).sort((a,b) => (a.time || "").localeCompare(b.time || "")); if (todaysEvents.length > 0) { todaysEvents.forEach(event => { const eventItem = document.createElement('div'); eventItem.classList.add('list-event-item'); eventItem.innerHTML = `${event.time ? `<span class="event-time">${formatTime(event.time)}</span>` : '<span class="event-time">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>'} <span class="event-title">${event.title}</span>`; eventItem.addEventListener('click', () => showEventModal(event)); dayGroup.appendChild(eventItem); }); } else { const noEventItem = document.createElement('div'); noEventItem.classList.add('list-event-item'); noEventItem.textContent = "Bugün için rezervasyon bulunmamaktadır."; noEventItem.style.fontStyle = "italic"; noEventItem.style.color = "#888"; dayGroup.appendChild(noEventItem); } calendarListViewEl.appendChild(dayGroup); }
    function getEventsForDate(dateStr, searchTerm = "") { const searchLower = searchTerm.toLowerCase().trim(); return events.filter(event => { const eventDate = event.date; const matchesDate = eventDate === dateStr; if (!matchesDate) return false; if (searchLower) { return (event.title && event.title.toLowerCase().includes(searchLower)) || (event.customer_name && event.customer_name.toLowerCase().includes(searchLower)) || (event.rule_name && event.rule_name.toLowerCase().includes(searchLower)) || (event.reservation_id_text && String(event.reservation_id_text).toLowerCase().includes(searchLower)); } return true; }); }
    function closeEventModal() { if (!modal) return; modal.style.display = 'none'; document.body.style.overflow = ''; }
    function updateActiveViewButton() { [monthViewBtn, weekViewBtn, dayViewBtn].forEach(btn => btn.classList.remove('active')); if (currentView === 'month' && monthViewBtn) monthViewBtn.classList.add('active'); else if (currentView === 'week' && weekViewBtn) weekViewBtn.classList.add('active'); else if (currentView === 'day' && dayViewBtn) dayViewBtn.classList.add('active'); }
    
    if(prevMonthBtn) prevMonthBtn.addEventListener('click', () => { if (currentView === 'month') currentDate.setMonth(currentDate.getMonth() - 1); else if (currentView === 'week') currentDate.setDate(currentDate.getDate() - 7); else if (currentView === 'day') currentDate.setDate(currentDate.getDate() - 1); renderCalendar(); });
    if(nextMonthBtn) nextMonthBtn.addEventListener('click', () => { if (currentView === 'month') currentDate.setMonth(currentDate.getMonth() + 1); else if (currentView === 'week') currentDate.setDate(currentDate.getDate() + 7); else if (currentView === 'day') currentDate.setDate(currentDate.getDate() + 1); renderCalendar(); });
    if(todayBtn) todayBtn.addEventListener('click', () => { currentDate = new Date(); renderCalendar(); });
    if(monthViewBtn) monthViewBtn.addEventListener('click', () => { currentView = 'month'; renderCalendar(); });
    if(weekViewBtn) weekViewBtn.addEventListener('click', () => { currentView = 'week'; renderCalendar(); });
    if(dayViewBtn) dayViewBtn.addEventListener('click', () => { currentView = 'day'; renderCalendar(); });
    if(searchButton) searchButton.addEventListener('click', renderCalendar);
    if(searchInput) searchInput.addEventListener('keyup', (e) => { if (e.key === 'Enter') renderCalendar(); });
    if(closeButton) closeButton.addEventListener('click', closeEventModal);
    if(modal) modal.addEventListener('click', (event) => { if (event.target === modal) closeEventModal(); });
    window.addEventListener('keydown', (event) => { if (event.key === 'Escape' && modal && modal.style.display === 'block') closeEventModal(); });
    
    if (btnTogglePickupForm) { btnTogglePickupForm.addEventListener('click', function() { if (pickupFormContainer) { const isHidden = pickupFormContainer.style.display === 'none'; pickupFormContainer.style.display = isHidden ? 'block' : 'none'; this.textContent = isHidden ? 'Formu Kapat' : 'Alınış Zamanını Değiştir'; } }); }
    if (btnCancelPickupEdit) { btnCancelPickupEdit.addEventListener('click', function() { if (pickupFormContainer) { pickupFormContainer.style.display = 'none'; if (btnTogglePickupForm) btnTogglePickupForm.textContent = 'Alınış Zamanını Değiştir'; } }); }
    if (btnSavePickupDateTime) { btnSavePickupDateTime.addEventListener('click', function() { const reservationId = this.dataset.reservationId; const newDate = modalPickupDateInput.value; const newTime = modalPickupTimeInput.value; if (!reservationId) { alert('Rezervasyon ID bulunamadı.'); return; } if (!newDate || !newTime) { alert("Lütfen alınış için tarih ve saat girin."); return; } this.disabled = true; this.textContent = 'Kaydediliyor...'; const formData = new FormData(); formData.append('reservation_id', reservationId); formData.append('date', newDate); formData.append('time', newTime); fetch('../functions/update_pickup_datetime.php', { method: 'POST', body: formData }) .then(response => { if (!response.ok) { return response.text().then(text => { throw new Error(text || 'Sunucu hatası') }); } return response.json(); }) .then(data => { alert(data.message || 'İşlem sonucu bilinmiyor.'); if (data.success) { location.reload(); } }) .catch(error => { console.error('Güncelleme Hatası:', error); alert('Alınış zamanı güncellenirken bir hata oluştu: ' + error.message); }) .finally(() => { this.disabled = false; this.textContent = 'Yeni Zamanı Kaydet'; }); }); }

    renderCalendar();
});