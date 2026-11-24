(() => {
  let request;
  let renderedSearch = location.search;
  function update(url, focusOffer = false) {
    const panel = document.querySelector('[data-sp-browser]');
    if (!panel) return;
    if (request) request.abort();
    const controller = new AbortController();
    request = controller;
    panel.setAttribute('aria-busy', 'true');
    const activeUrl = document.activeElement?.href;
    fetch(url, { signal: controller.signal }).then(response => {
      if (!response.ok) throw Error();
      return response.text();
    }).then(html => {
      const next = new DOMParser().parseFromString(html, 'text/html').querySelector('[data-sp-browser]');
      if (!next) throw Error();
      panel.replaceWith(next);
      history.pushState(null, '', url);
      renderedSearch = location.search;
      if (focusOffer) {
        next.querySelector('#offers').focus();
      } else {
        const link = Array.from(next.querySelectorAll('a')).find(a => a.href === activeUrl);
        if (link) link.focus({preventScroll:true});
      }
    }).catch(error => {
      if (error.name !== 'AbortError') location.assign(url);
    }).finally(() => {
      if (request === controller) panel.removeAttribute('aria-busy');
    });
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
    if (location.search !== renderedSearch) location.reload();
  });
})();
