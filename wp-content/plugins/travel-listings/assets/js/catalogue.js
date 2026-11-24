(() => {
  let request;
  let renderedSearch = location.search;
  function pointerCenter(tab) {
    const rect = tab.getBoundingClientRect();
    const shift = parseFloat(getComputedStyle(tab, '::after').translate) || 0;
    return rect.left + rect.width / 2 + shift;
  }

  async function update(url, focusOffer = false, restoreHistory = false) {
    const panel = document.querySelector('[data-sp-browser]');
    if (!panel) return;
    request?.abort();
    const controller = new AbortController();
    request = controller;
    panel.setAttribute('aria-busy', 'true');
    const status = panel.querySelector('[data-sp-status]');
    status.className = 'screen-reader-text';
    status.textContent = '';
    try {
      const response = await fetch(url, {
        signal: controller.signal,
        headers: {'X-Spro-Catalogue': '1'}
      });
      if (!response.ok) throw Error('Filter request failed');
      const html = await response.text();
      if (controller.signal.aborted) return;
      const template = document.createElement('template');
      template.innerHTML = html;
      const next = template.content.querySelector('[data-sp-browser]');
      const offers = next?.querySelector('#offers');
      const search = panel.querySelector('#trip-search');
      const nextSearch = next?.querySelector('#trip-search');
      if (!offers || Boolean(search) !== Boolean(nextSearch)) throw Error('Incomplete results');

      const activeUrl = panel.contains(document.activeElement) ? document.activeElement.href : null;
      const previousTab = search?.querySelector('.sp-tab[aria-current]');
      const selectedTab = nextSearch?.querySelector('.sp-tab[aria-current]');
      const slide = previousTab && selectedTab && previousTab.getAttribute('href') !== selectedTab.getAttribute('href')
        && matchMedia('(min-width: 601px) and (prefers-reduced-motion: no-preference)').matches;
      const from = slide ? pointerCenter(previousTab) : 0;
      if (search) search.replaceChildren(...nextSearch.children);
      if (slide) {
        selectedTab.style.setProperty('--pointer-from', (from - pointerCenter(selectedTab)) + 'px');
        selectedTab.classList.add('sp-pointer-slide');
      }
      offers.classList.add('sp-results-enter');
      panel.querySelector('#offers').replaceWith(offers);
      if (!restoreHistory && location.href !== url) history.pushState(null, '', url);
      renderedSearch = location.search;
      panel.removeAttribute('aria-busy');
      status.textContent = offers.getAttribute('aria-label');
      if (focusOffer) {
        offers.focus();
      } else if (activeUrl) {
        const link = Array.from(panel.querySelectorAll('a')).find(a => a.href === activeUrl && a.getClientRects().length);
        link?.focus({preventScroll: true});
      }
    } catch (error) {
      if (controller.signal.aborted) return;
      const retry = document.createElement('a');
      retry.href = url;
      retry.setAttribute('data-sp-link', '');
      retry.textContent = status.dataset.retry;
      status.className = 'sp-filter-error';
      status.replaceChildren(status.dataset.error + ' ', retry);
    } finally {
      if (request === controller) panel.removeAttribute('aria-busy');
    }
  }
  const disclosures = '.sp-lang[open], .sp-sort[open], .sp-dates[open], .sp-more[open]';
  document.addEventListener('toggle', e => {
    if (e.target.matches?.('.sp-more[open]')) {
      const input = e.target.querySelector('[data-category-search]');
      if (input) input.hidden = false;
    }
  }, true);
  document.addEventListener('click', e => {
    document.querySelectorAll(disclosures).forEach(detail => {
      if (!detail.contains(e.target)) detail.open = false;
    });
    const link = e.target.closest('a[data-sp-link]');
    if (!link || e.defaultPrevented || e.button || e.metaKey || e.ctrlKey || e.altKey || e.shiftKey) return;
    e.preventDefault();
    update(link.href, link.hash === '#offers');
  });
  document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    const open = document.querySelector(disclosures);
    if (open) {
      open.open = false;
      open.querySelector('summary').focus();
    }
  });
  document.addEventListener('submit', e => {
    if (!e.target.matches('[data-sp-search]')) return;
    e.preventDefault();
    const url = new URL(e.target.action);
    for (const [key, value] of new FormData(e.target)) {
      if (value) url.searchParams.set(key, value);
    }
    update(url.href, true);
  });
  document.addEventListener('input', e => {
    if (e.target.matches('[data-category-search]')) {
      const panel = e.target.closest('.sp-more-panel');
      const query = e.target.value.trim().toLocaleLowerCase();
      let matches = 0;
      for (const item of panel.querySelectorAll('li')) {
        item.hidden = !item.textContent.toLocaleLowerCase().includes(query);
        if (!item.hidden) matches++;
      }
      panel.querySelector('[data-category-empty]').hidden = matches > 0;
      return;
    }
    const picker = e.target.closest('.sp-dates');
    if (!picker) return;
    const summary = picker.querySelector('[data-date-summary]');
    const values = Array.from(picker.querySelectorAll('input'), input => input.value).filter(Boolean);
    summary.textContent = values.join(' – ') || summary.dataset.placeholder;
    const start = picker.querySelector('[name=trip_date_from]');
    const end = picker.querySelector('[name=trip_date_to]');
    if (start && end) {
      start.max = end.value;
      end.min = start.value;
    }
  });
  window.addEventListener('popstate', () => {
    request?.abort();
    if (location.search !== renderedSearch) update(location.href, false, true);
  });
})();
