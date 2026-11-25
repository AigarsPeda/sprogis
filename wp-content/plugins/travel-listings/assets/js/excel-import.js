(() => {
    const form = document.getElementById('spro-import-form');
    if (!form) return;
    const input = form.elements.workbook;
    const zone = document.getElementById('spro-import-drop');
    const status = document.getElementById('spro-import-status');
    let uploading = false;
    document.getElementById('spro-import-submit').hidden = true;

    function upload() {
        if (input.files[0] && input.files[0].size > Number(input.dataset.maxBytes)) {
            reportSelection('size', input.files[0].name);
            return;
        }
        if (!uploading && input.files.length) form.requestSubmit();
    }

    function reportSelection(reason, name) {
        if (uploading) return;
        uploading = true;
        form.elements.selection_issue.value = reason;
        form.elements.attempted_name.value = name;
        input.disabled = true;
        form.submit();
    }

    input.addEventListener('change', upload);
    zone.addEventListener('dragover', event => {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'copy';
        zone.style.borderColor = '#2271b1';
    });
    zone.addEventListener('dragleave', () => { zone.style.borderColor = ''; });
    zone.addEventListener('drop', event => {
        event.preventDefault();
        zone.style.borderColor = '';
        if (uploading) return;
        if (event.dataTransfer.files.length !== 1) {
            if (event.dataTransfer.files.length > 1) {
                reportSelection('multiple', event.dataTransfer.files[0].name);
            } else {
                status.textContent = 'Drop a workbook file from your computer, or use Choose File.';
            }
            return;
        }
        input.files = event.dataTransfer.files;
        upload();
    });
    form.addEventListener('submit', event => {
        if (uploading) {
            event.preventDefault();
            return;
        }
        uploading = true;
        status.textContent = 'Importing ' + input.files[0].name + '…';
    });
})();
