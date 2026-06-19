(function(){
  const cfg = window.WORKFLOW_CONFIG || {};
  const root = document.documentElement;
  const appShell = document.querySelector('.app');

  const storedTheme = localStorage.getItem('workflow-theme');
  const storedBrand = localStorage.getItem('workflow-brand');
  const storedSidebar = localStorage.getItem('workflow-sidebar');

  const theme = storedTheme || cfg.defaultTheme || 'light';
  const brand = storedBrand || cfg.brand || 'default';
  root.setAttribute('data-theme', theme);
  root.setAttribute('data-brand', brand);

  if(appShell && storedSidebar === 'closed' && window.innerWidth > 960){
    appShell.classList.add('sidebar-closed');
  }

  document.querySelectorAll('[data-site-title]').forEach(el=>el.textContent = cfg.siteTitle || 'Workflow Portal');
  document.querySelectorAll('[data-site-tagline]').forEach(el=>el.textContent = cfg.siteTagline || 'Workflow Portal');
  if(cfg.brandLogo){
    document.querySelectorAll('[data-brand-logo]').forEach(img=>{
      img.src = cfg.brandLogo;
      img.alt = (cfg.siteTitle || 'Site') + ' logo';
    });
  }

  const syncThemeButtons = () => {
    const current = root.getAttribute('data-theme') || 'light';
    document.querySelectorAll('[data-set-theme]').forEach(btn=>{
      btn.classList.toggle('active', btn.dataset.setTheme === current);
      btn.setAttribute('aria-pressed', String(btn.dataset.setTheme === current));
    });
  };

  const setSidebarLabel = () => {
    if(!appShell) return;
    const closed = appShell.classList.contains('sidebar-closed');
    document.querySelectorAll('[data-sidebar-label]').forEach(el=>{
      el.textContent = closed ? 'Open Menu' : 'Close Menu';
    });
    document.querySelectorAll('[data-sidebar-toggle]').forEach(btn=>{
      btn.setAttribute('aria-expanded', String(!closed));
      const label = closed ? 'Open menu' : 'Close menu';
      btn.setAttribute('title', label);
      btn.setAttribute('aria-label', label);
    });
  };

  syncThemeButtons();
  setSidebarLabel();

  document.querySelectorAll('[data-set-theme]').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const next = btn.dataset.setTheme || 'light';
      root.setAttribute('data-theme', next);
      localStorage.setItem('workflow-theme', next);
      syncThemeButtons();
    });
  });
  document.querySelectorAll('[data-theme-toggle]').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', next);
      localStorage.setItem('workflow-theme', next);
      syncThemeButtons();
    });
  });

  document.querySelectorAll('[data-sidebar-toggle]').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      if(!appShell || window.innerWidth <= 960) return;
      appShell.classList.toggle('sidebar-closed');
      localStorage.setItem('workflow-sidebar', appShell.classList.contains('sidebar-closed') ? 'closed' : 'open');
      setSidebarLabel();
    });
  });

  window.addEventListener('resize', ()=>{
    if(!appShell) return;
    if(window.innerWidth <= 960){
      appShell.classList.remove('sidebar-closed');
    } else if(localStorage.getItem('workflow-sidebar') === 'closed'){
      appShell.classList.add('sidebar-closed');
    }
    setSidebarLabel();
  });

  document.querySelectorAll('[data-doc-tab]').forEach(button=>{
    button.addEventListener('click',()=>{
      const wrap=button.closest('[data-doc-tabs-wrapper]')||document;
      wrap.querySelectorAll('[data-doc-tab]').forEach(b=>b.classList.toggle('active',b===button));
      const targetId=button.dataset.docTab;
      wrap.querySelectorAll('.doc-tab').forEach(tab=>tab.classList.toggle('active',tab.id===targetId));
    });
  });
})();

(function(){
  const closeModal = (modal) => {
    if(!modal) return;
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
  };

  const openModal = (modal) => {
    if(!modal) return;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
  };

  document.querySelectorAll('[data-modal-open]').forEach(btn=>{    btn.addEventListener('click', ()=>{
      openModal(document.getElementById(btn.dataset.modalOpen));
    });
  });

  document.querySelectorAll('[data-modal-close]').forEach(btn=>{
    btn.addEventListener('click', ()=> closeModal(btn.closest('.modal-backdrop')));
  });

  document.querySelectorAll('.modal-backdrop').forEach(modal=>{
    modal.addEventListener('click', (event)=>{
      if(event.target === modal) closeModal(modal);
    });
  });

  document.addEventListener('keydown', (event)=>{
    if(event.key === 'Escape') {
      document.querySelectorAll('.modal-backdrop.is-open').forEach(closeModal);
    }
  });
})();

(function(){
  const toast = document.getElementById('flash-toast');
  if(!toast) return;
  // Remove from DOM after fade-out animation finishes (3s delay + 0.4s fade = 3.4s)
  setTimeout(()=>toast.remove(), 3400);
})();

// Clickable table rows
(function(){
  document.querySelectorAll('tr.row-clickable[data-href]').forEach(row => {
    row.addEventListener('click', function(e) {
      // Don't navigate if user clicked a link, button, select, or input inside the row
      if (e.target.closest('a,button,select,input,label')) return;
      window.location.href = row.dataset.href;
    });
  });
})();

// Fixed mirror scrollbar for tables
// Shows a synced scrollbar fixed to the bottom of the viewport
// whenever a table-wrap is wider than its container and on screen.
(function(){
  const tableWraps = Array.from(document.querySelectorAll('.table-wrap'));
  if (!tableWraps.length) return;

  // Create the fixed mirror bar container
  const mirror = document.createElement('div');
  mirror.id = 'table-scroll-mirror';
  Object.assign(mirror.style, {
    position:   'fixed',
    bottom:     '0',
    left:       '0',
    right:      '0',
    height:     '14px',
    overflowX:  'scroll',
    overflowY:  'hidden',
    zIndex:     '500',
    display:    'none',
    background: 'var(--surface-overlay, rgba(255,255,255,0.92))',
    borderTop:  '1px solid var(--border-subtle, #e2e8f0)',
    backdropFilter: 'blur(4px)',
  });

  // Inner spacer — width is set dynamically to match the table
  const spacer = document.createElement('div');
  spacer.style.height = '1px';
  mirror.appendChild(spacer);
  document.body.appendChild(mirror);

  let activeWrap = null;
  let syncingFromMirror = false;
  let syncingFromWrap   = false;

  // Sync: mirror → table
  mirror.addEventListener('scroll', () => {
    if (!activeWrap || syncingFromWrap) return;
    syncingFromMirror = true;
    activeWrap.scrollLeft = mirror.scrollLeft;
    syncingFromMirror = false;
  });

  function attachWrap(wrap) {
    if (!wrap._mirrorListener) {
      wrap._mirrorListener = () => {
        if (wrap !== activeWrap || syncingFromMirror) return;
        syncingFromWrap = true;
        mirror.scrollLeft = wrap.scrollLeft;
        syncingFromWrap = false;
      };
      wrap.addEventListener('scroll', wrap._mirrorListener);
    }
  }

  tableWraps.forEach(attachWrap);

  function update() {
    const viewportBottom = window.innerHeight;
    let found = null;

    for (const wrap of tableWraps) {
      const rect = wrap.getBoundingClientRect();
      const tableWidth = wrap.scrollWidth;
      const wrapWidth  = wrap.clientWidth;
      const isScrollable = tableWidth > wrapWidth + 2;
      const nativeBarY   = rect.bottom; // where the native scrollbar is

      // Show mirror if: table is scrollable AND visible on screen
      // AND native scrollbar is below the viewport bottom
      if (isScrollable && rect.top < viewportBottom && rect.bottom > 0) {
        // Prefer the one whose native bar is furthest off screen
        if (!found || nativeBarY > viewportBottom) {
          found = wrap;
        }
      }
    }

    if (found) {
      const rect      = found.getBoundingClientRect();
      const nativeBar = rect.bottom;
      const tableWidth = found.scrollWidth;
      const wrapWidth  = found.clientWidth;

      // Only show mirror when native bar is out of viewport
      if (nativeBar > window.innerHeight) {
        spacer.style.width  = tableWidth + 'px';
        mirror.style.left   = rect.left + 'px';
        mirror.style.right  = (window.innerWidth - rect.right) + 'px';
        mirror.style.display = 'block';

        if (activeWrap !== found) {
          activeWrap = found;
          mirror.scrollLeft = found.scrollLeft;
        }
      } else {
        mirror.style.display = 'none';
        activeWrap = null;
      }
    } else {
      mirror.style.display = 'none';
      activeWrap = null;
    }
  }

  window.addEventListener('scroll',  update, { passive: true });
  window.addEventListener('resize',  update, { passive: true });
  update();
})();