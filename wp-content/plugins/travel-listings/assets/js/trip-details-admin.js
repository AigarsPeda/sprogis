/* Edit-screen controls for the trip page photo, included items and key dates. */
(function () {
    var counter = Date.now();

    document.querySelectorAll('[data-spro-repeater]').forEach(function (box) {
        var body = box.querySelector('tbody');
        var template = box.querySelector('template');
        box.querySelector('[data-spro-add]').addEventListener('click', function () {
            counter += 1;
            body.insertAdjacentHTML('beforeend', template.innerHTML.replace(/__i__/g, 'n' + counter));
            var first = body.lastElementChild.querySelector('input');
            if (first) {
                first.focus();
            }
        });
        body.addEventListener('click', function (event) {
            var button = event.target.closest('[data-spro-remove]');
            if (button) {
                button.closest('tr').remove();
            }
        });
    });

    var related = document.querySelector('[data-spro-related]');
    if (related) {
        var sync = function () {
            var picked = related.querySelector('input[name="spro_related_mode"]:checked');
            related.querySelectorAll('[data-spro-related-show]').forEach(function (part) {
                part.hidden = !picked || part.getAttribute('data-spro-related-show') !== picked.value;
            });
        };
        related.addEventListener('change', sync);
        sync();
        var search = related.querySelector('[data-spro-related-search]');
        search.addEventListener('input', function () {
            var term = search.value.trim().toLowerCase();
            related.querySelectorAll('[data-spro-related-item]').forEach(function (item) {
                item.hidden = term !== '' && item.getAttribute('data-spro-related-item').indexOf(term) === -1;
            });
        });
        // Enter in the search box would submit the post form.
        search.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
            }
        });
    }

    var photo = document.querySelector('[data-spro-photo]');
    if (!photo || !window.wp || !wp.media) {
        return;
    }
    var input = photo.querySelector('input');
    var preview = photo.querySelector('[data-spro-photo-preview]');
    var choose = photo.querySelector('[data-spro-photo-choose]');
    var remove = photo.querySelector('[data-spro-photo-remove]');
    var frame;
    choose.addEventListener('click', function () {
        if (!frame) {
            frame = wp.media({ title: 'Trip page photo', button: { text: 'Use this photo' }, library: { type: 'image' }, multiple: false });
            frame.on('select', function () {
                var picked = frame.state().get('selection').first().toJSON();
                var size = picked.sizes && (picked.sizes.medium || picked.sizes.full);
                input.value = picked.id;
                preview.innerHTML = '';
                var img = document.createElement('img');
                img.src = size ? size.url : picked.url;
                img.alt = '';
                preview.appendChild(img);
                choose.textContent = 'Replace photo';
                remove.hidden = false;
            });
        }
        frame.open();
    });
    remove.addEventListener('click', function () {
        input.value = '';
        preview.innerHTML = '';
        choose.textContent = 'Choose photo';
        remove.hidden = true;
    });
})();
