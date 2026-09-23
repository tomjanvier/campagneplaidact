(function () {
  'use strict';

  /**
   * Administration PLAID·ACT — onglets et améliorations mineures.
   * Progressif : sans JS, toutes les sections restent lisibles (fallback empilé).
   */

  function initTabs(root) {
    var tabs = root.querySelectorAll('.plaidact-admin-tabs .nav-tab[data-tab]');
    var panels = root.querySelectorAll('.plaidact-admin-tab-panel[data-panel]');
    if (!tabs.length || !panels.length) return;

    var storageKey = 'plaidact_admin_active_tab';

    function activate(tabName, focus) {
      tabs.forEach(function (t) {
        var isActive = t.getAttribute('data-tab') === tabName;
        t.classList.toggle('nav-tab-active', isActive);
        t.setAttribute('aria-selected', isActive ? 'true' : 'false');
        t.tabIndex = isActive ? 0 : -1;
      });
      panels.forEach(function (p) {
        var isActive = p.getAttribute('data-panel') === tabName;
        p.classList.toggle('is-active', isActive);
        p.hidden = !isActive;
        p.setAttribute('aria-hidden', isActive ? 'false' : 'true');
      });
      try { localStorage.setItem(storageKey, tabName); } catch (e) {}
      // Met à jour l'URL sans recharger (hash pour partage).
      if (history.replaceState) {
        var url = new URL(window.location);
        url.searchParams.set('plaidact_tab', tabName);
        history.replaceState(null, '', url);
      }
      if (focus) {
        var activePanel = root.querySelector('.plaidact-admin-tab-panel.is-active');
        if (activePanel) activePanel.focus({ preventScroll: true });
      }
    }

    tabs.forEach(function (tab) {
      tab.setAttribute('role', 'tab');
      tab.addEventListener('click', function (e) {
        e.preventDefault();
        activate(tab.getAttribute('data-tab'), false);
      });
      tab.addEventListener('keydown', function (e) {
        var idx = Array.prototype.indexOf.call(tabs, tab);
        if (e.key === 'ArrowRight') {
          e.preventDefault();
          var next = tabs[(idx + 1) % tabs.length];
          next.focus(); activate(next.getAttribute('data-tab'), false);
        } else if (e.key === 'ArrowLeft') {
          e.preventDefault();
          var prev = tabs[(idx - 1 + tabs.length) % tabs.length];
          prev.focus(); activate(prev.getAttribute('data-tab'), false);
        } else if (e.key === 'Home') {
          e.preventDefault(); tabs[0].focus(); activate(tabs[0].getAttribute('data-tab'), false);
        } else if (e.key === 'End') {
          e.preventDefault(); var last = tabs[tabs.length - 1]; last.focus(); activate(last.getAttribute('data-tab'), false);
        }
      });
    });

    // Rôle tablist
    var tablist = root.querySelector('.plaidact-admin-tabs');
    if (tablist) tablist.setAttribute('role', 'tablist');
    panels.forEach(function (p) { p.setAttribute('role', 'tabpanel'); p.tabIndex = -1; });

    // Tab initiale : URL > localStorage > première
    var initial = null;
    try {
      var urlTab = new URL(window.location).searchParams.get('plaidact_tab');
      if (urlTab && root.querySelector('.plaidact-admin-tabs .nav-tab[data-tab="' + urlTab + '"]')) initial = urlTab;
    } catch (e) {}
    if (!initial) {
      try { var stored = localStorage.getItem(storageKey); if (stored && root.querySelector('[data-tab="' + stored + '"]')) initial = stored; } catch (e) {}
    }
    if (!initial) initial = tabs[0].getAttribute('data-tab');
    activate(initial, false);
  }

  function init() {
    document.querySelectorAll('.plaidact-admin-wrap').forEach(initTabs);

    // Améliore les notices WP en les déplaçant dans le header admin si besoin
    // (non bloquant, purement visuel)
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
