{{--
    Replaces the browser's own "localhost says ... OK / Cancel" box with a modal. It takes over every form or
    button that asks with onsubmit="return confirm('...')" or onclick="return confirm('...')", so those pages do
    not need to change. Put it once in each layout.
--}}
<div id="confirm-modal" class="fixed inset-0 z-[110] hidden items-center justify-center p-4"
     role="alertdialog" aria-modal="true" aria-labelledby="confirm-modal-title" aria-describedby="confirm-modal-message">
    <div data-confirm-backdrop class="absolute inset-0 bg-gray-900/60"></div>

    <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
        <div class="flex items-start gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-600">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z"/></svg>
            </span>
            <div class="min-w-0 flex-1">
                <h2 id="confirm-modal-title" class="text-base font-semibold text-gray-900">{{ __('Please confirm') }}</h2>
                <p id="confirm-modal-message" data-fallback="{{ __('Are you sure?') }}" class="mt-2 text-sm text-gray-700"></p>
            </div>
        </div>

        <div class="mt-6 flex justify-end gap-3">
            <button type="button" data-confirm-cancel
                    class="rounded-lg border border-gray-300 bg-white px-5 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-800 focus:ring-offset-2">
                {{ __('Cancel') }}
            </button>
            <button type="button" data-confirm-ok
                    class="rounded-lg bg-gray-800 px-5 py-2 text-sm font-semibold text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-800 focus:ring-offset-2">
                {{ __('Confirm') }}
            </button>
        </div>
    </div>
</div>

<script>
    (function () {
        if (window.__confirmModalReady) { return; }
        window.__confirmModalReady = true;

        var modal = document.getElementById('confirm-modal');
        var text = document.getElementById('confirm-modal-message');
        var okButton = modal.querySelector('[data-confirm-ok]');
        var cancelButton = modal.querySelector('[data-confirm-cancel]');
        var proceed = null;
        var opener = null;

        function isOpen() { return ! modal.classList.contains('hidden'); }

        function open(message, onConfirm) {
            text.textContent = message || text.getAttribute('data-fallback');
            proceed = onConfirm;
            opener = document.activeElement;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            cancelButton.focus();
        }

        function close(confirmed) {
            var action = confirmed ? proceed : null;
            proceed = null;
            modal.classList.add('hidden');
            modal.classList.remove('flex');

            if (! confirmed && opener && opener.focus) { opener.focus(); }
            if (action) { action(); }
        }

        okButton.addEventListener('click', function () { close(true); });
        cancelButton.addEventListener('click', function () { close(false); });
        modal.querySelector('[data-confirm-backdrop]').addEventListener('click', function () { close(false); });

        document.addEventListener('keydown', function (event) {
            if (! isOpen()) { return; }

            if (event.key === 'Escape') {
                event.preventDefault();
                close(false);
            } else if (event.key === 'Tab') {
                event.preventDefault();
                var buttons = [cancelButton, okButton];
                var next = buttons.indexOf(document.activeElement) + (event.shiftKey ? -1 : 1);
                buttons[(next + 2) % 2].focus();
            }
        });

        // Read the question out of the page's own confirm('...') code, whatever the quoting is.
        function messageOf(code) {
            var message = '';
            var original = window.confirm;
            window.confirm = function (question) { message = String(question); return true; };

            try { (new Function(code))(); } catch (error) { /* keep the fallback text */ } finally { window.confirm = original; }

            return message;
        }

        // Forms: onsubmit="return confirm('...')". Runs before the form's own handler, so the browser box never shows.
        document.addEventListener('submit', function (event) {
            var form = event.target;
            var code = form.getAttribute && form.getAttribute('onsubmit');

            if (! code || code.indexOf('confirm(') === -1) { return; }

            event.preventDefault();
            event.stopPropagation();

            if (! form.checkValidity()) { form.reportValidity(); return; }

            var submitter = event.submitter;

            open(messageOf(code), function () {
                form.removeAttribute('onsubmit');
                if (submitter) { form.requestSubmit(submitter); } else { form.requestSubmit(); }
            });
        }, true);

        // Buttons: onclick="return confirm('...')".
        document.addEventListener('click', function (event) {
            var button = event.target.closest ? event.target.closest('[onclick*="confirm("]') : null;

            if (! button) { return; }

            event.preventDefault();
            event.stopPropagation();

            open(messageOf(button.getAttribute('onclick')), function () {
                button.removeAttribute('onclick');
                if (button.form) { button.form.requestSubmit(button); } else { button.click(); }
            });
        }, true);
    })();
</script>
