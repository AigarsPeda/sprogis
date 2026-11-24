(() => {
  document.addEventListener('click', event => {
    if (event.target.matches('[data-add-field]')) {
      const row = document.createElement('p');
      row.innerHTML = '<input type="text" name="spro_fields[]" aria-label="Filter name"> <button type="button" class="button-link-delete" data-remove-field>Remove</button>';
      event.target.previousElementSibling.append(row);
      row.querySelector('input').focus();
    } else if (event.target.matches('[data-remove-field]')) {
      event.target.closest('p').remove();
    }
  });
  function showSelectedFields() {
    const panel = document.getElementById('spro-search-values');
    if (!panel) return;
    const categories = document.getElementById('listing_categorychecklist');
    if (!categories) return;
    const selected = new Set(Array.from(categories.querySelectorAll('input:checked'), box => box.value));
    panel.querySelectorAll('[data-filter-term]').forEach(group => {
      group.hidden = !selected.has(group.dataset.filterTerm);
    });
    panel.hidden = !panel.querySelector('[data-filter-term]:not([hidden])');
  }
  document.addEventListener('change', event => {
    if (event.target.closest('#listing_categorydiv')) showSelectedFields();
  });
  showSelectedFields();
})();
