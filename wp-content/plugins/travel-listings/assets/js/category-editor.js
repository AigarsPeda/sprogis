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
    document.querySelectorAll('[data-filter-term]').forEach(group => {
      const boxes = document.querySelectorAll('input[name="tax_input[listing_category][]"][value="' + group.dataset.filterTerm + '"]');
      group.hidden = boxes.length > 0 && !Array.from(boxes).some(box => box.checked);
    });
  }
  document.addEventListener('change', showSelectedFields);
  showSelectedFields();
})();
