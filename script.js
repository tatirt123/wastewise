 (function () {
    'use strict';

    const toastEl = document.getElementById('toast');
    let toastTimer = null;

    function showToast(msg, icon) {
      toastEl.innerHTML = '<i class="fa-solid ' + (icon || 'fa-circle-check') + '"></i> ' + msg;
      toastEl.classList.add('show');
      clearTimeout(toastTimer);
      toastTimer = setTimeout(function () { toastEl.classList.remove('show'); }, 2600);
    }

    function openOverlay(id) {
      const el = document.getElementById(id);
      if (!el) return;
      el.classList.add('open');
      document.body.style.overflow = 'hidden';
    }

    function closeOverlay(id) {
      const el = document.getElementById(id);
      if (!el) return;
      el.classList.remove('open');
      if (!document.querySelector('.simple-overlay.open, .guide-modal-overlay.open, .climate-modal-overlay.open')) {
        document.body.style.overflow = '';
      }
    }

    document.querySelectorAll('[data-close]').forEach(function (btn) {
      btn.addEventListener('click', function () { closeOverlay(btn.dataset.close); });
    });

    document.querySelectorAll('.simple-overlay').forEach(function (ov) {
      ov.addEventListener('click', function (e) {
        if (e.target === ov) closeOverlay(ov.id);
      });
    });

    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape') return;
      document.querySelectorAll('.simple-overlay.open').forEach(function (o) { closeOverlay(o.id); });
      if (guideModal.classList.contains('open')) closeGuide();
      if (climateModal.classList.contains('open')) closeClimateModal();
    });


    document.querySelectorAll('a[href^="#"]').forEach(function (link) {
      link.addEventListener('click', function (e) {
        const id = this.getAttribute('href');
        if (!id || id === '#') { 
          e.preventDefault(); 
          return; 
        }
        const target = document.querySelector(id);
        if (!target) { 
          e.preventDefault();
          return; 
        }

        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });

        if (this.classList.contains('nav-item')) {
          document.querySelectorAll('.nav-item').forEach(function (n) { n.classList.remove('active'); });
          this.classList.add('active');
        }
      });
    });

    const wiseSearchNav = document.querySelector('a[href="#search-result"].nav-item');
    if (wiseSearchNav) {
      wiseSearchNav.addEventListener('click', function () {
        setTimeout(function () {
          const input = document.getElementById('wiseSearchInput');
          if (input) input.focus({ preventScroll: true });
        }, 500);
      });
    }

    const CATEGORY_STYLE = {
      biodegradable: { color: '#208e3a', dark: '#146527', label: 'Biodegradable' },
      recyclable:    { color: '#e8b32a', dark: '#c2931a', label: 'Recyclable' },
      residual:      { color: '#1b659c', dark: '#154d77', label: 'Residual' },
      hazardous:     { color: '#d64a3b', dark: '#a83428', label: 'Hazardous' }
    };

    const BOTTLE_SVG =
      '<svg class="bottle-img" viewBox="0 0 100 200" xmlns="http://www.w3.org/2000/svg">' +
        '<path d="M 35 15 L 65 15 L 65 30 L 75 45 L 75 180 C 75 190, 65 195, 50 195 C 35 195, 25 190, 25 180 L 25 45 L 35 30 Z" fill="#cbe3f0" stroke="#7bb1d4" stroke-width="3"/>' +
        '<rect x="33" y="5" width="34" height="12" rx="2" fill="#3190cc" />' +
        '<path d="M 26 80 Q 50 90 74 80 M 26 110 Q 50 120 74 110 M 26 140 Q 50 150 74 140" stroke="#a0ccf0" stroke-width="2" fill="none"/>' +
      '</svg>';

    function binSVG(catKey) {
      const s = CATEGORY_STYLE[catKey];
      return '<svg class="bin-img" viewBox="0 0 140 140" xmlns="http://www.w3.org/2000/svg">' +
        '<path d="M 15 35 L 125 35 L 115 125 C 113 132, 105 135, 95 135 L 45 135 C 35 135, 27 132, 25 125 Z" fill="' + s.color + '" />' +
        '<path d="M 10 25 L 130 25 L 125 35 L 15 35 Z" fill="' + s.dark + '" />' +
        '<path d="M 50 70 L 60 55 L 70 70 M 60 55 L 60 85 M 75 75 L 85 85 L 75 95 M 85 85 L 55 85" stroke="#ffffff" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" fill="none"/>' +
        '<text x="70" y="115" font-size="11" text-anchor="middle" fill="#ffffff" font-weight="bold">' + s.label + '</text>' +
      '</svg>';
    }

    const wasteDB = {
      plasticBottle: {
        name: 'Plastic Bottle', category: 'Recyclable', catKey: 'recyclable',
        visual: { type: 'svg' },
        where: 'Rinse and dispose in the <strong>Yellow Bin</strong> for recyclables.',
        steps: ['Remove the cap and label.', 'Rinse to remove any residue.', 'Flatten the bottle to save space.', 'Dispose in the yellow bin.'],
        keywords: ['plastic', 'bottle', 'pet', 'pet bottle', 'water bottle', 'soda bottle', 'plastic bottle']
      },
      glassBottle: {
        name: 'Glass Bottle', category: 'Recyclable', catKey: 'recyclable',
        visual: { type: 'icon', icon: 'fa-wine-bottle' },
        where: 'Rinse and place in the <strong>Yellow Bin</strong>. Handle with care.',
        steps: ['Empty and rinse the bottle.', 'Remove metal caps or corks.', 'Do not break the glass.', 'Place in the yellow bin.'],
        keywords: ['glass', 'glass bottle', 'jar', 'bottle']
      },
      cardboard: {
        name: 'Cardboard', category: 'Recyclable', catKey: 'recyclable',
        visual: { type: 'icon', icon: 'fa-box' },
        where: 'Flatten and place in the <strong>Yellow Bin</strong>. Keep it dry.',
        steps: ['Remove tape and plastic wrap.', 'Flatten the box.', 'Keep it clean and dry.', 'Place in the yellow bin.'],
        keywords: ['cardboard', 'box', 'carton', 'paper', 'newspaper']
      },
      bananaPeel: {
        name: 'Banana Peel', category: 'Biodegradable', catKey: 'biodegradable',
        visual: { type: 'icon', icon: 'fa-apple-whole' },
        where: 'Place in the <strong>Green Bin</strong> for biodegradable waste, or compost it.',
        steps: ['Remove any plastic or sticker.', 'Do not mix with plastics.', 'Place in the green bin.', 'Compost if you have a pit.'],
        keywords: ['banana', 'banana peel', 'peel', 'fruit', 'food scrap', 'vegetable']
      },
      leaves: {
        name: 'Dry Leaves', category: 'Biodegradable', catKey: 'biodegradable',
        visual: { type: 'icon', icon: 'fa-tree' },
        where: 'Place in the <strong>Green Bin</strong> or add to a compost pile.',
        steps: ['Sweep them into a pile.', 'Remove plastic wrappers or trash.', 'Place in the green bin.', 'Or compost for garden soil.'],
        keywords: ['leaves', 'leaf', 'dry leaves', 'grass', 'twigs', 'yard waste']
      },
      diaper: {
        name: 'Used Diaper', category: 'Residual', catKey: 'residual',
        visual: { type: 'icon', icon: 'fa-baby' },
        where: 'Wrap securely and place in the <strong>Blue Bin</strong> for residual waste.',
        steps: ['Remove solid waste into the toilet.', 'Wrap the diaper tightly.', 'Seal it in a small bag.', 'Place in the blue bin.'],
        keywords: ['diaper', 'nappy', 'used diaper', 'sanitary', 'pad']
      },
      tissue: {
        name: 'Used Tissue', category: 'Residual', catKey: 'residual',
        visual: { type: 'icon', icon: 'fa-toilet-paper' },
        where: 'Place in the <strong>Blue Bin</strong> for residual waste.',
        steps: ['Do not put it in the recycling bin.', 'Do not flush large amounts.', 'Place in the blue bin.'],
        keywords: ['tissue', 'used tissue', 'napkin', 'paper towel', 'candy wrapper', 'wrapper']
      },
      battery: {
        name: 'Used Battery', category: 'Hazardous', catKey: 'hazardous',
        visual: { type: 'icon', icon: 'fa-battery-full' },
        where: 'Take to a <strong>Hazardous Waste Drop-off</strong> — never in regular trash.',
        steps: ['Tape the terminals to prevent sparks.', 'Never throw batteries in regular bins.', 'Store in a dry container.', 'Drop off at a hazardous waste point.'],
        keywords: ['battery', 'batteries', 'used battery', 'aa', 'aaa', 'lithium']
      },
      bulb: {
        name: 'Used Light Bulb', category: 'Hazardous', catKey: 'hazardous',
        visual: { type: 'icon', icon: 'fa-lightbulb' },
        where: 'Take to a <strong>Hazardous Waste Drop-off</strong>. Do not break the bulb.',
        steps: ['Wrap the bulb in paper or cloth.', 'Do not throw it in regular trash.', 'Place in a rigid container.', 'Drop off at a hazardous waste point.'],
        keywords: ['bulb', 'light bulb', 'lamp', 'fluorescent', 'cfl']
      }
    };

    const CATEGORY_DEFAULT = {
      biodegradable: 'bananaPeel',
      recyclable: 'plasticBottle',
      residual: 'diaper',
      hazardous: 'battery'
    };

    const itemVisualEl   = document.getElementById('itemVisual');
    const itemNameEl     = document.getElementById('itemName');
    const itemCategoryEl = document.getElementById('itemCategory');
    const itemWhereEl    = document.getElementById('itemWhere');
    const stepsListEl    = document.getElementById('stepsList');
    const binVisualEl    = document.getElementById('binVisual');
    const dotsEl         = document.getElementById('categoryDots');

    function renderItem(key) {
      const item = wasteDB[key];
      if (!item) return;

      const style = CATEGORY_STYLE[item.catKey];

      if (item.visual.type === 'svg') {
        itemVisualEl.innerHTML = BOTTLE_SVG;
      } else {
        itemVisualEl.innerHTML = '<i class="fa-solid ' + item.visual.icon + '" style="color:' + style.color + '"></i>';
      }

      itemNameEl.textContent = item.name;
      itemCategoryEl.textContent = item.category;
      itemCategoryEl.style.color = style.color;
      itemWhereEl.innerHTML = item.where;

      stepsListEl.innerHTML = item.steps
        .map(function (s) { return '<li><span class="check">✓</span> ' + s + '</li>'; })
        .join('');

      binVisualEl.innerHTML = binSVG(item.catKey);

      dotsEl.querySelectorAll('.dot').forEach(function (d) {
        d.classList.toggle('active', d.dataset.cat === item.catKey);
      });
    }

    dotsEl.querySelectorAll('.dot').forEach(function (dot) {
      dot.addEventListener('click', function () {
        const cat = dot.dataset.cat;
        renderItem(CATEGORY_DEFAULT[cat]);
        showToast('Showing ' + CATEGORY_STYLE[cat].label + ' example', 'fa-circle-info');
      });
    });

    function searchWaste(rawQuery) {
      const q = (rawQuery || '').trim().toLowerCase();
      if (!q) {
        showToast('Type an item to search — e.g. "battery"', 'fa-circle-info');
        return;
      }

      let foundKey = null;
      Object.keys(wasteDB).some(function (key) {
        const item = wasteDB[key];
        const name = item.name.toLowerCase();

        if (name === q || name.indexOf(q) !== -1 || q.indexOf(name) !== -1) {
          foundKey = key; return true;
        }
        const hit = item.keywords.some(function (k) {
          return k === q || (k.length > 2 && q.indexOf(k) !== -1) || (q.length > 2 && k.indexOf(q) !== -1);
        });
        if (hit) { foundKey = key; return true; }
        return false;
      });

      if (foundKey) {
        renderItem(foundKey);
        showToast('Found: ' + wasteDB[foundKey].name, 'fa-magnifying-glass');
      } else {
        showToast('No match — try "bottle", "battery", "peel"…', 'fa-circle-question');
      }
    }

    document.getElementById('wiseSearchBtn').addEventListener('click', function () {
      searchWaste(document.getElementById('wiseSearchInput').value);
    });

    document.getElementById('wiseSearchInput').addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        searchWaste(this.value);
      }
    });

    renderItem('plasticBottle');

    const recycleGuides = {
      biodegradable: {
        title: 'Biodegradable', icon: 'fa-leaf', color: '#208e3a', bg: '#dff0e6',
        description: 'Waste that comes from plants or animals and can naturally decompose.',
        examples: [
          { name: 'Dry Leaves', icon: 'fa-tree' },
          { name: 'Banana Peel', icon: 'fa-apple-whole' },
          { name: 'Apple Core', icon: 'fa-apple-alt' }
        ],
        images: [
          'https://images.unsplash.com/photo-1540420773420-3366772f4999?w=500',
          'https://images.unsplash.com/photo-1606787366850-de63301c7bfc?w=500',
          'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=500',
          'https://images.unsplash.com/photo-1518843875459-f738682238a6?w=500',
          'https://images.unsplash.com/photo-1498837167922-ddd27525d352?w=500',
          'https://images.unsplash.com/photo-1550989460-0adf9ea622e2?w=500'
        ],
        segregation: [
          'Place in a trash bin properly.',
          'Remove the non-biodegradable materials (e.g. plastic).',
          'Compost if possible to reduce landfill waste.'
        ],
        mistakes: [
          'Mixing with plastics.',
          'Putting biodegradable waste in the wrong bin.'
        ]
      }
    };

    const guideModal       = document.getElementById('guideModal');
    const guideSidebar     = document.getElementById('guideSidebar');
    const guideMainContent = document.getElementById('guideMainContent');
    const guideClose       = document.getElementById('guideClose');

    function renderSidebar(activeKey) {
      guideSidebar.innerHTML = Object.keys(recycleGuides).map(function (key) {
        const g = recycleGuides[key];
        const isActive = key === activeKey ? 'active' : '';
        return '<button class="guide-sidebar-btn ' + isActive + '" data-guide="' + key + '">' +
                 '<i class="fa-solid ' + g.icon + '"></i> ' + g.title +
               '</button>';
      }).join('');

      guideSidebar.querySelectorAll('.guide-sidebar-btn').forEach(function (btn) {
        btn.addEventListener('click', function () { openGuide(btn.dataset.guide); });
      });
    }

    function renderMainContent(key) {
      const g = recycleGuides[key];
      if (!g) return;

      const gridColorClass = {
        biodegradable: 'green',
        recyclable:    'yellow',
        residual:      'blue',
        hazardous:     'red'
      }[key] || 'green';

      guideMainContent.innerHTML =
        '<div style="display: flex; gap: 2rem; align-items: flex-start;">' +
          '<div style="flex-shrink: 0; width: 200px;">' +
            '<h2 style="font-size: 1.8rem; font-weight: 800; color: #1a402b; margin-bottom: 0.5rem; text-transform: uppercase;">' + g.title + '</h2>' +
            '<p class="guide-subtitle" style="font-size: 0.95rem; color: #4a6f5a; line-height: 1.5;">' + g.description + '</p>' +
          '</div>' +
          '<div style="flex: 1;">' +
            (g.images && g.images.length ?
              '<div class="category-image-grid ' + gridColorClass + '" style="grid-template-columns: 1fr; gap: 0;">' +
                '<div class="grid-card" style="aspect-ratio: 16/9; border-radius: 1rem; border-width: 4px;">' +
                  '<img src="' + g.images[0] + '" alt="' + g.title + ' example" loading="lazy">' +
                '</div>' +
              '</div>'
              : '') +
          '</div>' +
        '</div>';
    }

    function openGuide(key) {
      if (!recycleGuides[key]) key = 'biodegradable';
      renderSidebar(key);
      renderMainContent(key);
      guideModal.classList.add('open');
      document.body.style.overflow = 'hidden';
    }

    function closeGuide() {
      guideModal.classList.remove('open');
      if (!document.querySelector('.simple-overlay.open, .climate-modal-overlay.open')) {
        document.body.style.overflow = '';
      }
    }

    document.querySelectorAll('[data-guide]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        openGuide(btn.dataset.guide);
      });
    });

    guideClose.addEventListener('click', closeGuide);
    guideModal.addEventListener('click', function (e) {
      if (e.target === guideModal) closeGuide();
    });

    const climateModal   = document.getElementById('climateModal');
    const climateClose   = document.getElementById('climateClose');
    const openClimateBtn = document.getElementById('openClimateBtn');
    const climateBackHome = document.getElementById('climateBackHome');

    function openClimateModal(e) {
      if (e) e.preventDefault();
      climateModal.classList.add('open');
      document.body.style.overflow = 'hidden';
    }

    function closeClimateModal() {
      climateModal.classList.remove('open');
      if (!document.querySelector('.simple-overlay.open, .guide-modal-overlay.open')) {
        document.body.style.overflow = '';
      }
    }

    openClimateBtn.addEventListener('click', openClimateModal);
    climateClose.addEventListener('click', closeClimateModal);

    climateBackHome.addEventListener('click', function (e) {
      e.preventDefault();
      closeClimateModal();
      document.getElementById('home').scrollIntoView({ behavior: 'smooth' });
    });

    climateModal.addEventListener('click', function (e) {
      if (e.target === climateModal) closeClimateModal();
    });

    document.getElementById('openSchedulePreviewBtn').addEventListener('click', function () {
      openOverlay('schedulePreviewModal');
    });

    document.getElementById('schedulePreviewGoBtn').addEventListener('click', function () {
      closeOverlay('schedulePreviewModal');
      setTimeout(function () {
        document.getElementById('schedule').scrollIntoView({ behavior: 'smooth', block: 'start' });
        document.querySelectorAll('.nav-item').forEach(function (n) { n.classList.remove('active'); });
        const navSchedule = document.querySelector('a[href="#schedule"].nav-item');
        if (navSchedule) navSchedule.classList.add('active');
      }, 200);
    });

    (function () {
      const toggle = document.getElementById('spReminderToggle');
      const icon   = document.getElementById('spReminderIcon');
      if (!toggle || !icon) return;

      let on = false;
      toggle.addEventListener('click', function () {
        on = !on;
        if (on) {
          icon.className = 'fa-solid fa-bell on';
          showToast('Reminders turned ON', 'fa-bell');
        } else {
          icon.className = 'fa-regular fa-bell-slash';
          icon.style.color = '';
          showToast('Reminders turned OFF', 'fa-bell-slash');
        }
      });
    })();

    const viewAllUpcomingBtn = document.getElementById('viewAllUpcomingBtn');
    if (viewAllUpcomingBtn) {
      viewAllUpcomingBtn.addEventListener('click', function (e) {
        e.preventDefault();
        openOverlay('viewAllImagesModal');
      });
    }

    document.querySelectorAll('.reminder-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (this.classList.contains('done')) {
          showToast('Reminder already set', 'fa-circle-info');
          return;
        }
        this.classList.add('done');
        this.textContent = 'Reminder Set';
        showToast('Reminder set for this collection', 'fa-bell');
      });
    });

    document.getElementById('openReportBtn').addEventListener('click', function () {
      openOverlay('reportModal');
    });

    document.getElementById('reportForm').addEventListener('submit', function (e) {
      e.preventDefault();
      const location = document.getElementById('reportLocation') ? document.getElementById('reportLocation').value.trim() : '';

      const countEl = document.getElementById('reportCount');
      countEl.textContent = (parseInt(countEl.textContent.replace(/,/g, ''), 10) + 1).toLocaleString();

      const list = document.getElementById('activityList');
      const row = document.createElement('div');
      row.className = 'activity-item';
      row.innerHTML =
        '<div class="activity-item-left">' +
          '<i class="fa-solid fa-check-circle"></i>' +
          '<span>Reported illegal dumping' + (location ? ' at ' + location : '') + '</span>' +
        '</div>' +
        '<div class="activity-date"><i class="fa-regular fa-calendar"></i> Just now</div>';
      list.prepend(row);

      this.reset();
      closeOverlay('reportModal');
      showToast('Report submitted. Thank you!', 'fa-paper-plane');
    });

    const recyclingCenters = [
      { name: 'Subabasbas Materials Recovery Facility', addr: 'Barangay Subabasbas, Lapu-Lapu City', dist: '0.8 km', tags: ['Plastic', 'Glass', 'Paper'] },
      { name: 'Basak Eco Drop-off Point', addr: 'Basak Road, Lapu-Lapu City', dist: '2.4 km', tags: ['Paper', 'Metal', 'Cardboard'] },
      { name: 'Marigondon Junk Shop & Recycler', addr: 'Marigondon Crossing, Lapu-Lapu City', dist: '3.6 km', tags: ['Metal', 'E-waste'] },
      { name: 'Lapu-Lapu City Hazardous Waste Depot', addr: 'City Hall Compound, Lapu-Lapu City', dist: '5.1 km', tags: ['Battery', 'Chemical', 'Bulb'] }
    ];

    const centersList = document.getElementById('centersList');
    const centersLocation = document.getElementById('centersLocation');

    function renderCenters() {
      centersList.innerHTML = recyclingCenters.map(function (c) {
        const mapsUrl = 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(c.name + ' ' + c.addr);
        return '<div class="center-item">' +
                 '<div class="center-icon"><i class="fa-solid fa-recycle"></i></div>' +
                 '<div class="center-info">' +
                   '<h5>' + c.name + '</h5>' +
                   '<p>' + c.addr + ' · ' + c.dist + ' away</p>' +
                   '<div class="tags">' + c.tags.map(function (t) { return '<span>' + t + '</span>'; }).join('') + '</div>' +
                 '</div>' +
                 '<a class="center-dir-btn" href="' + mapsUrl + '" target="_blank" rel="noopener">' +
                   '<i class="fa-solid fa-diamond-turn-right"></i> Directions' +
                 '</a>' +
               '</div>';
      }).join('');
    }

    renderCenters();

    document.getElementById('findCenterBtn').addEventListener('click', function () {
      centersLocation.textContent =
        document.getElementById('brgySelect').value + ', ' + document.getElementById('citySelect').value;
      openOverlay('centersModal');
    });

    const readAnnouncementBtn = document.getElementById('readAnnouncementBtn');
    if (readAnnouncementBtn) {
      readAnnouncementBtn.addEventListener('click', function () {
        openOverlay('announceModal');
      });
    }

    const joinBtn = document.getElementById('joinEventBtn');
    let joined = false;
    if (joinBtn) {
      joinBtn.addEventListener('click', function () {
        joined = !joined;

        if (joined) {
          joinBtn.classList.add('done');
          joinBtn.innerHTML = 'Joined <i class="fa-solid fa-check"></i>';
          showToast('You joined the Coastal Clean-up Drive!', 'fa-hands-holding-circle');

          const list = document.getElementById('activityList');
          const row = document.createElement('div');
          row.className = 'activity-item';
          row.innerHTML =
            '<div class="activity-item-left">' +
              '<i class="fa-solid fa-check-circle"></i>' +
              '<span>Joined Clean-Up Drive</span>' +
            '</div>' +
            '<div class="activity-date"><i class="fa-regular fa-calendar"></i> Just now</div>';
          list.prepend(row);
        } else {
          joinBtn.classList.remove('done');
          joinBtn.innerHTML = 'Join Event <i class="fa-solid fa-check"></i>';
          showToast('You left the event.', 'fa-circle-info');
        }
      });
    }

    document.querySelectorAll('.join-activity-btn').forEach(function(btn) {
      btn.addEventListener('click', function() {
        const activityName = this.dataset.activity;
        if (this.classList.contains('done')) {
          showToast('You are already registered for ' + activityName, 'fa-circle-info');
          return;
        }
        
        this.classList.add('done');
        this.textContent = 'Joined';
        showToast('You joined the ' + activityName + '!', 'fa-hands-holding-circle');
        
        const list = document.getElementById('activityList');
        const row = document.createElement('div');
        row.className = 'activity-item';
        row.innerHTML =
          '<div class="activity-item-left">' +
            '<i class="fa-solid fa-check-circle"></i>' +
            '<span>Joined ' + activityName + '</span>' +
          '</div>' +
          '<div class="activity-date"><i class="fa-regular fa-calendar"></i> Just now</div>';
        list.prepend(row);
      });
    });

    const profileNameEl  = document.getElementById('profileName');
    const profileEmailEl = document.getElementById('profileEmail');
    const profileAvatarEl = document.getElementById('profileAvatar');
    const navAvatarEl = document.getElementById('navAvatar');

    const defaultAvatarSVG = `
      <svg viewBox="0 0 100 100" width="72" height="72" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Profile avatar">
        <circle cx="50" cy="50" r="50" fill="#dff0e6"/>
        <circle cx="50" cy="38" r="16" fill="#2d7a4a"/>
        <path d="M50 62c-15 0-27 11-29 25 8 6 18 9 29 9s21-3 29-9c-2-14-14-25-29-25z" fill="#2d7a4a"/>
      </svg>`;

    document.getElementById('editProfileBtn').addEventListener('click', function () {
      document.getElementById('editName').value = profileNameEl.textContent;
      document.getElementById('editEmail').value = profileEmailEl.textContent;
      document.getElementById('editAvatar').value = '';
      openOverlay('editProfileModal');
    });

    document.getElementById('editProfileForm').addEventListener('submit', function (e) {
      e.preventDefault();
      
      profileNameEl.textContent = document.getElementById('editName').value.trim();
      profileEmailEl.textContent = document.getElementById('editEmail').value.trim();
      
      const fileInput = document.getElementById('editAvatar');
      if (fileInput.files && fileInput.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
          const imgHTML = '<img src="' + e.target.result + '" alt="Profile Avatar" style="width:100%; height:100%; object-fit:cover; border-radius:50%;">';
          profileAvatarEl.innerHTML = imgHTML;
          navAvatarEl.src = e.target.result;
        };
        reader.readAsDataURL(fileInput.files[0]);
      } else {
        profileAvatarEl.innerHTML = defaultAvatarSVG;
        navAvatarEl.src = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='50' fill='%232d7a4a'/%3E%3Cpath d='M50 20C30 35 28 60 50 84 72 60 70 35 50 20Z' fill='%23eaf7ee'/%3E%3Cpath d='M50 26V80M50 46L36 56M50 46L64 56M50 62L38 70M50 62L62 70' stroke='%232d7a4a' stroke-width='3' stroke-linecap='round' fill='none'/%3E%3C/svg%3E";
      }

      closeOverlay('editProfileModal');
      showToast('Profile updated successfully', 'fa-floppy-disk');
    });

    const INFO = {
      privacy: {
        title: 'Privacy Policy',
        body: '<p class="simple-sub">WasteWise collects only the information needed to run collection schedules and community reports.</p>' +
              '<ul class="guide-list">' +
                '<li><i class="fa-solid fa-circle-check"></i> <span>We never sell your personal data.</span></li>' +
                '<li><i class="fa-solid fa-circle-check"></i> <span>Location data is used only to match you with nearby collection routes and recycling centers.</span></li>' +
                '<li><i class="fa-solid fa-circle-check"></i> <span>Reports you submit are shared only with your barangay waste office.</span></li>' +
              '</ul>'
      },
      terms: {
        title: 'Terms of Service',
        body: '<p class="simple-sub">By using WasteWise you agree to keep the community accurate and respectful.</p>' +
              '<ul class="guide-list">' +
                '<li><i class="fa-solid fa-circle-check"></i> <span>Submit only truthful waste reports.</span></li>' +
                '<li><i class="fa-solid fa-circle-check"></i> <span>Do not misuse the reporting feature to target individuals.</span></li>' +
                '<li><i class="fa-solid fa-circle-check"></i> <span>Eco Points have no cash value and may be reset each season.</span></li>' +
              '</ul>'
      },
      help: {
        title: 'Help Center',
        body: '<p class="simple-sub">Quick answers to the most common questions.</p>' +
              '<ul class="guide-list">' +
                '<li><i class="fa-solid fa-circle-check"></i> <span><strong>How do I know which bin to use?</strong> Use Wise Search — type the item and we will tell you.</span></li>' +
                '<li><i class="fa-solid fa-circle-check"></i> <span><strong>When is pickup in my area?</strong> Open the Schedule tab, pick your city and barangay, then press View Schedule.</span></li>' +
                '<li><i class="fa-solid fa-circle-check"></i> <span><strong>Where do I bring batteries?</strong> Search "battery" and tap Find Recycling Centers Near You.</span></li>' +
              '</ul>'
      }
    };

    document.querySelectorAll('[data-info]').forEach(function (link) {
      link.addEventListener('click', function (e) {
        e.preventDefault();
        const info = INFO[link.dataset.info];
        if (!info) return;
        document.getElementById('infoTitle').textContent = info.title;
        document.getElementById('infoBody').innerHTML = info.body;
        openOverlay('infoModal');
      });
    });

    const MONTH_NAMES = ['January','February','March','April','May','June',
                         'July','August','September','October','November','December'];
    const DAY_HEADERS = ['SUN','MON','TUE','WED','THU','FRI','SAT'];

    const COLLECTION_TYPES = {
      0: { key: 'biodegradable', name: 'Biodegradable', color: '#208e3a', icon: 'fa-leaf' },
      1: { key: 'biodegradable', name: 'Biodegradable', color: '#208e3a', icon: 'fa-leaf' },
      2: { key: 'recyclable',    name: 'Recyclable',    color: '#e8b32a', icon: 'fa-recycle' },
      3: { key: 'residual',      name: 'Residual',      color: '#1b659c', icon: 'fa-trash-can' },
      4: { key: 'recyclable',    name: 'Recyclable',    color: '#e8b32a', icon: 'fa-recycle' },
      5: { key: 'hazardous',     name: 'Hazardous',     color: '#d64a3b', icon: 'fa-triangle-exclamation' },
      6: { key: 'residual',      name: 'Residual',      color: '#1b659c', icon: 'fa-trash-can' }
    };

    const today = new Date();
    let calYear  = today.getFullYear();
    let calMonth = today.getMonth();

    const calendarGrid = document.getElementById('calendarGrid');
    const calMonthLabel = document.getElementById('calMonthLabel');

    function renderCalendar() {
      calMonthLabel.textContent = MONTH_NAMES[calMonth] + ' ' + calYear;

      const firstWeekday = new Date(calYear, calMonth, 1).getDay();
      const daysInMonth  = new Date(calYear, calMonth + 1, 0).getDate();

      let html = DAY_HEADERS.map(function (d) {
        return '<div class="cal-day-header">' + d + '</div>';
      }).join('');

      for (let i = 0; i < firstWeekday; i++) {
        html += '<div class="cal-date" style="cursor:default;"></div>';
      }

      for (let day = 1; day <= daysInMonth; day++) {
        const weekday = (firstWeekday + day - 1) % 7;
        const type = COLLECTION_TYPES[weekday];

        let ind = 'ind-green';
        if (type.key === 'recyclable') ind = 'ind-yellow';
        else if (type.key === 'residual') ind = 'ind-blue';
        else if (type.key === 'hazardous') ind = 'ind-red';

        const isToday = (day === today.getDate() &&
                         calMonth === today.getMonth() &&
                         calYear === today.getFullYear());

        html += '<div class="cal-date has-indicator ' + ind + (isToday ? ' today' : '') + '" ' +
                     'data-day="' + day + '" data-wd="' + weekday + '">' + day + '</div>';
      }

      calendarGrid.innerHTML = html;

      calendarGrid.querySelectorAll('.cal-date[data-day]').forEach(function (cell) {
        cell.addEventListener('click', function () {
          calendarGrid.querySelectorAll('.cal-date').forEach(function (c) { c.classList.remove('selected'); });
          cell.classList.add('selected');

          const type = COLLECTION_TYPES[Number(cell.dataset.wd)];
          showToast(MONTH_NAMES[calMonth] + ' ' + cell.dataset.day + ' · ' + type.name + ' collection',
                    type.icon);
        });
      });
    }

    document.getElementById('prevMonth').addEventListener('click', function () {
      calMonth--;
      if (calMonth < 0) { calMonth = 11; calYear--; }
      renderCalendar();
    });

    document.getElementById('nextMonth').addEventListener('click', function () {
      calMonth++;
      if (calMonth > 11) { calMonth = 0; calYear++; }
      renderCalendar();
    });

    renderCalendar();

    function renderUpcoming() {
      const container = document.getElementById('upcomingList');
      const out = [];
      const d = new Date(today.getFullYear(), today.getMonth(), today.getDate() + 1);

      while (out.length < 3) {
        const type = COLLECTION_TYPES[d.getDay()];
        out.push({
          date: new Date(d),
          type: type
        });
        d.setDate(d.getDate() + 1);
      }

      container.innerHTML = out.map(function (o) {
        const label = MONTH_NAMES[o.date.getMonth()].slice(0, 3) + ' ' + o.date.getDate() + ', ' + o.date.getFullYear() +
                      ' • ' + DAY_HEADERS[o.date.getDay()].charAt(0) + DAY_HEADERS[o.date.getDay()].slice(1).toLowerCase();
        return '<div class="upcoming-card">' +
                 '<div class="upcoming-icon" style="background:' + o.type.color + ';">' +
                   '<i class="fa-solid ' + o.type.icon + '"></i>' +
                 '</div>' +
                 '<div class="upcoming-info">' +
                   '<h5>' + label + '</h5>' +
                   '<p><strong>' + o.type.name + '</strong><br>7:00 AM - 10:00 AM</p>' +
                 '</div>' +
               '</div>';
      }).join('');
    }

    renderUpcoming();

    const citySelect = document.getElementById('citySelect');
    const brgySelect = document.getElementById('brgySelect');

    document.getElementById('viewScheduleBtn').addEventListener('click', function () {
      const city = citySelect.value;
      const brgy = brgySelect.value;

      document.getElementById('calendarSub').textContent = brgy + ', ' + city;

      document.querySelectorAll('.legend-item').forEach(function (li) {
        li.classList.add('highlight');
      });

      showToast('Showing schedule for ' + brgy + ', ' + city, 'fa-calendar-check');
    });

    let reminderOn = false;
    const reminderToggle = document.getElementById('reminderToggle');
    const reminderIcon   = document.getElementById('reminderIcon');

    reminderToggle.addEventListener('click', function () {
      reminderOn = !reminderOn;

      if (reminderOn) {
        reminderIcon.className = 'fa-solid fa-bell';
        reminderIcon.style.color = '#2d7a4a';
        showToast('Reminders turned ON', 'fa-bell');
      } else {
        reminderIcon.className = 'fa-regular fa-bell-slash';
        reminderIcon.style.color = '#888';
        showToast('Reminders turned OFF', 'fa-bell-slash');
      }
    });

    document.querySelectorAll('.browse-card').forEach(function (card) {
      card.addEventListener('click', function () {
        const guide = card.dataset.guide;
        if (guide) openGuide(guide);
      });
    });

  })();
