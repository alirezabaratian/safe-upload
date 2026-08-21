(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('safeupload-form');
        if (!form) {
            return;
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            var status = document.getElementById('safeupload-save-status');
            var requesttoken = document.getElementById('safeupload-requesttoken').value;
            var formData = new FormData(form);
            var params = new URLSearchParams();
            formData.forEach(function (value, key) {
                params.append(key, value);
            });

            status.textContent = '';

            fetch(form.dataset.saveUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'requesttoken': requesttoken,
                },
                body: params.toString(),
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Save failed');
                    }
                    return response.json();
                })
                .then(function () {
                    status.textContent = 'Saved.';
                })
                .catch(function () {
                    status.textContent = 'Could not save settings.';
                });
        });
    });
})();
