// ============================================================
// KONEKT — Network Search (People & Companies)
// ============================================================
// Used on network.php — searches via /api/search/global_search.php
// Debounced input, keyboard navigation, click-outside-to-close.
// ============================================================

(function () {
  'use strict';

  const input    = document.getElementById('networkSearchInput');
  const dropdown = document.getElementById('networkSearchDropdown');
  const results  = document.getElementById('networkSearchResults');
  const empty    = document.getElementById('networkSearchEmpty');
  const loading  = document.getElementById('networkSearchLoading');
  const wrap     = document.getElementById('networkSearchWrap');

  if (!input || !dropdown) return;

  let debounceTimer = null;
  let activeIndex   = -1;
  let abortCtrl     = null;

  // ── Helpers ────────────────────────────────────────────────
  function getInitials(first, last) {
    return ((first || '')[0] || '') + ((last || '')[0] || '');
  }

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  function highlightMatch(text, query) {
    if (!text || !query) return escapeHtml(text || '');
    const escaped = escapeHtml(text);
    const regex = new RegExp('(' + query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
    return escaped.replace(regex, '<mark>$1</mark>');
  }

  function roleBadgeLabel(role) {
    if (role === 'employer') return 'Employer';
    if (role === 'job_seeker') return 'Job Seeker';
    return role;
  }

  function showDropdown() { dropdown.style.display = 'block'; }
  function hideDropdown() { dropdown.style.display = 'none'; activeIndex = -1; }

  // ── Render Results ─────────────────────────────────────────
  function renderResults(data, query) {
    const people    = data.people || [];
    const companies = data.companies || [];

    if (people.length === 0 && companies.length === 0) {
      results.innerHTML = '';
      empty.style.display = 'block';
      loading.style.display = 'none';
      showDropdown();
      return;
    }

    empty.style.display = 'none';
    loading.style.display = 'none';
    let html = '';

    // People section
    if (people.length > 0) {
      html += '<div class="konekt-search-category"><i class="bi bi-people me-1"></i> People</div>';
      people.forEach(p => {
        const initials = getInitials(p.first_name, p.last_name).toUpperCase();
        const name = highlightMatch(p.first_name + ' ' + p.last_name, query);
        const sub  = p.headline ? escapeHtml(p.headline) : (p.location ? escapeHtml(p.location) : 'KoneKT User');
        const badge = `<span class="konekt-search-item-badge role-${escapeHtml(p.role)}">${roleBadgeLabel(p.role)}</span>`;

        html += `
          <a href="network.php?user_id=${p.id}" class="konekt-search-item" data-type="person" data-id="${p.id}">
            <div class="konekt-search-avatar">${initials}</div>
            <div class="konekt-search-item-info">
              <div class="konekt-search-item-name">${name}</div>
              <div class="konekt-search-item-sub">${sub}</div>
            </div>
            ${badge}
          </a>`;
      });
    }

    // Companies section
    if (companies.length > 0) {
      html += '<div class="konekt-search-category"><i class="bi bi-building me-1"></i> Companies</div>';
      companies.forEach(c => {
        const initials = (c.name || '').substring(0, 2).toUpperCase();
        const name = highlightMatch(c.name, query);
        const sub  = [c.industry, c.location].filter(Boolean).map(escapeHtml).join(' · ') || 'Company';

        html += `
          <a href="find_jobs.php?keyword=${encodeURIComponent(c.name)}" class="konekt-search-item" data-type="company" data-id="${c.id}">
            <div class="konekt-search-avatar company-avatar">${initials}</div>
            <div class="konekt-search-item-info">
              <div class="konekt-search-item-name">${name}</div>
              <div class="konekt-search-item-sub">${sub}</div>
            </div>
            <span class="konekt-search-item-badge badge-company">Company</span>
          </a>`;
      });
    }

    results.innerHTML = html;
    showDropdown();
  }

  // ── Fetch Search Results ───────────────────────────────────
  async function doSearch(query) {
    if (query.length < 2) {
      hideDropdown();
      return;
    }

    if (abortCtrl) abortCtrl.abort();
    abortCtrl = new AbortController();

    results.innerHTML = '';
    empty.style.display = 'none';
    loading.style.display = 'flex';
    showDropdown();

    try {
      const res = await fetch(`api/search/global_search.php?q=${encodeURIComponent(query)}`, {
        signal: abortCtrl.signal
      });
      const json = await res.json();

      if (json.success) {
        renderResults(json.data, query);
      } else {
        empty.style.display = 'block';
        loading.style.display = 'none';
      }
    } catch (err) {
      if (err.name !== 'AbortError') {
        loading.style.display = 'none';
        empty.style.display = 'block';
      }
    }
  }

  // ── Input Event (debounced) ────────────────────────────────
  input.addEventListener('input', () => {
    const query = input.value.trim();
    clearTimeout(debounceTimer);

    if (query.length < 2) {
      hideDropdown();
      return;
    }

    debounceTimer = setTimeout(() => doSearch(query), 280);
  });

  // ── Focus ──────────────────────────────────────────────────
  input.addEventListener('focus', () => {
    if (input.value.trim().length >= 2) {
      doSearch(input.value.trim());
    }
  });

  // Click outside to close
  document.addEventListener('click', (e) => {
    if (!wrap.contains(e.target)) {
      hideDropdown();
    }
  });

  // ── Keyboard Navigation ────────────────────────────────────
  input.addEventListener('keydown', (e) => {
    const items = dropdown.querySelectorAll('.konekt-search-item');
    if (!items.length && e.key !== 'Escape') return;

    if (e.key === 'ArrowDown') {
      e.preventDefault();
      activeIndex = Math.min(activeIndex + 1, items.length - 1);
      updateActiveItem(items);
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      activeIndex = Math.max(activeIndex - 1, -1);
      updateActiveItem(items);
    } else if (e.key === 'Enter' && activeIndex >= 0 && items[activeIndex]) {
      e.preventDefault();
      items[activeIndex].click();
    } else if (e.key === 'Escape') {
      hideDropdown();
      input.blur();
    }
  });

  function updateActiveItem(items) {
    items.forEach((el, i) => {
      el.classList.toggle('active', i === activeIndex);
      if (i === activeIndex) el.scrollIntoView({ block: 'nearest' });
    });
  }

})();
