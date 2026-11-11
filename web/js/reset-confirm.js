(function () {
    'use strict';

    function init() {
        var form = document.querySelector('form[data-reset-confirm]');
        if (!form) {
            return;
        }

        form.addEventListener('submit', function (event) {
            var message = form.getAttribute('data-reset-confirm');
            if (message && !window.confirm(message)) {
                event.preventDefault();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
