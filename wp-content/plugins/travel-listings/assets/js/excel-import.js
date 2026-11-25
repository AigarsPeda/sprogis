(() => {
    const form = document.getElementById('spro-import-form');
    if (!form) return;
    const page = form.closest('.wrap');
    const input = form.elements.workbook;
    const zone = document.getElementById('spro-import-drop');
    const status = document.getElementById('spro-import-status');
    const actions = document.getElementById('spro-import-actions');
    const issue = form.elements.selection_issue;
    const attempted = form.elements.attempted_name;
    let busy = false;
    let lockedControls = [];
    let lockedLinks = [];

    function showSelection(reason = '', name = '') {
        issue.value = reason;
        attempted.value = name;
        input.required = !reason;
        actions.hidden = !input.files.length && !reason;
        status.textContent = reason ? name + ' — ' + ({
            multiple: 'Choose one workbook at a time.',
            package: 'Save this Numbers workbook as a Single File.'
        })[reason] : '';
    }

    input.addEventListener('change', () => showSelection());
    zone.addEventListener('dragover', event => {
        event.preventDefault();
        event.dataTransfer.dropEffect = busy ? 'none' : 'copy';
        if (!busy) zone.style.borderColor = '#2271b1';
    });
    zone.addEventListener('dragleave', () => { zone.style.borderColor = ''; });
    zone.addEventListener('drop', event => {
        event.preventDefault();
        zone.style.borderColor = '';
        if (busy) return;
        const entry = event.dataTransfer.items?.[0]?.webkitGetAsEntry?.();
        const files = event.dataTransfer.files;
        if (entry?.isDirectory || files.length > 1) {
            input.value = '';
            showSelection(entry?.isDirectory ? 'package' : 'multiple', entry?.name || files[0].name);
        } else if (files.length === 1) {
            input.files = files;
            showSelection();
        } else {
            status.textContent = 'Drop a workbook file from your computer, or use Choose File.';
        }
    });

    page.addEventListener('submit', event => {
        if (busy) {
            event.preventDefault();
            return;
        }
        const submitting = event.target;
        if (submitting === form) {
            const file = input.files[0];
            if (!file && !issue.value) {
                event.preventDefault();
                return;
            }
            if (file && file.size > Number(input.dataset.maxBytes)) {
                issue.value = 'size';
                attempted.value = file.name;
            }
            // Send just the error details for an invalid selection.
            if (issue.value) input.value = '';
            status.textContent = 'Importing ' + (attempted.value || file.name) + '…';
        } else {
            status.textContent = 'Retrying selected rows…';
        }
        busy = true;
        // Native form submission collects the file and category choices before
        // this event. Disabling those fields earlier would omit their values.
        submitting.addEventListener('formdata', () => {
            lockedControls = [...page.querySelectorAll('button:enabled, select:enabled, input:enabled:not([type="hidden"])')];
            lockedControls.forEach(control => { control.disabled = true; });
            lockedLinks = [...page.querySelectorAll('a[href], summary')];
            lockedLinks.forEach(link => {
                link.inert = true;
                link.setAttribute('aria-disabled', 'true');
                if (link.classList.contains('button')) link.classList.add('disabled');
            });
        }, {once: true});
    });

    window.addEventListener('pageshow', event => {
        if (!event.persisted) return;
        lockedControls.forEach(control => { control.disabled = false; });
        lockedLinks.forEach(link => {
            link.inert = false;
            link.removeAttribute('aria-disabled');
            if (link.classList.contains('button')) link.classList.remove('disabled');
        });
        busy = false;
        showSelection();
    });
    showSelection();
})();
