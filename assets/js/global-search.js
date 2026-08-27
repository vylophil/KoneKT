// ============================================================
// KONEKT — Network Search (People Only)
// ============================================================
// Used on network.php — searches via /api/search/global_search.php
// Debounced input, keyboard navigation, click-outside-to-close.

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

  //Helpers
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

  //Connection Status Button HTML 
  function connectionActionHtml(person) {
    const status = person.connection_status || 'none';
    const mutual = person.mutual_connections || 0;
    let actionBtn = '';
    let mutualHtml = '';

    switch (status) {
      case 'accepted':
        actionBtn = `
          <div class="konekt-search-item-actions">
            <a href="network.php?user_id=${person.id}" class="btn btn-connected-badge" onclick="event.stopPropagation();">
              <i class="bi bi-chat-dots-fill me-1"></i>Message
            </a>
          </div>`;
        break;
      case 'pending':
        actionBtn = `
          <div class="konekt-search-item-actions">
            <span class="btn btn-pending-badge"><i class="bi bi-clock me-1"></i>Pending</span>
          </div>`;
        break;
      case 'rejected':
      case 'none':
        actionBtn = `
          <div class="konekt-search-item-actions">
            <button class="btn btn-connect search-connect-btn" data-user-id="${person.id}" onclick="event.preventDefault(); event.stopPropagation();">
              <i class="bi bi-person-plus me-1"></i>Connect
            </button>
          </div>`;
        break;
      case 'blocked':
        actionBtn = `<div class="konekt-search-item-actions"><span class="btn btn-pending-badge">Blocked</span></div>`;
        break;
    }

    if (mutual > 0) {
      mutualHtml = `<div class="konekt-search-mutual"><i class="bi bi-people-fill me-1"></i>${mutual} mutual connection${mutual > 1 ? 's' : ''}</div>`;
    }

    return { actionBtn, mutualHtml };
  }

  // Render Results
  function renderResults(data, query) {
    const people = data.people || [];

    if (people.length === 0) {
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
    people.forEach(p => {
      const initials = getInitials(p.first_name, p.last_name).toUpperCase();
      const name = highlightMatch(p.first_name + ' ' + p.last_name, query);
      const sub  = p.headline ? escapeHtml(p.headline) : (p.location ? escapeHtml(p.location) : 'KoneKT User');
      const badge = `<span class="konekt-search-item-badge role-${escapeHtml(p.role)}">${roleBadgeLabel(p.role)}</span>`;
      const { actionBtn, mutualHtml } = connectionActionHtml(p);

      html += `
        <a href="network.php?user_id=${p.id}" class="konekt-search-item" data-type="person" data-id="${p.id}">
          <div class="konekt-search-avatar">${initials}</div>
          <div class="konekt-search-item-info">
            <div class="konekt-search-item-name">${name} ${badge}</div>
            <div class="konekt-search-item-sub">${sub}</div>
            ${mutualHtml}
          </div>
          ${actionBtn}
        </a>`;
    });

    results.innerHTML = html;
    showDropdown();

    // Attach connect button handlers
    results.querySelectorAll('.search-connect-btn').forEach(btn => {
      btn.addEventListener('click', handleConnectClick);
    });
  }

  // Send Connection Request
  async function handleConnectClick(e) {
    const btn = e.currentTarget;
    const userId = parseInt(btn.dataset.userId);
    if (!userId) return;

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Sending...';

    try {
      const res = await fetch('api/networking/send_connection.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ receiver_id: userId })
      });
      const data = await res.json();

      if (data.success) {
        // Replace button with Pending badge
        const actionsDiv = btn.closest('.konekt-search-item-actions');
        if (actionsDiv) {
          actionsDiv.innerHTML = '<span class="btn btn-pending-badge"><i class="bi bi-clock me-1"></i>Pending</span>';
        }
      } else {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-person-plus me-1"></i>Connect';
        // Show error briefly
        const originalText = btn.innerHTML;
        btn.innerHTML = `<i class="bi bi-exclamation-circle me-1"></i>${data.message || 'Failed'}`;
        btn.classList.add('btn-pending-badge');
        btn.classList.remove('btn-connect');
        setTimeout(() => {
          btn.innerHTML = originalText;
          btn.classList.remove('btn-pending-badge');
          btn.classList.add('btn-connect');
        }, 2500);
      }
    } catch (err) {
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-person-plus me-1"></i>Connect';
    }
  }

  //  Fetch Search Results
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
      const res = await fetch(`api/networking/search_people.php?search=${encodeURIComponent(query)}&limit=8`, {
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

  // Input Event (debounced)
  input.addEventListener('input', () => {
    const query = input.value.trim();
    clearTimeout(debounceTimer);

    if (query.length < 2) {
      hideDropdown();
      return;
    }

    debounceTimer = setTimeout(() => doSearch(query), 280);
  });

  // Focus
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

  //  Keyboard Navigation
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
