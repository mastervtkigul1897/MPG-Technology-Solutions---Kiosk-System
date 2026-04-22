(() => {
    if (window.__mpgAjaxActionsWired) return;
    window.__mpgAjaxActionsWired = true;

    const mutatingMethods = new Set(['POST', 'PUT', 'PATCH', 'DELETE']);
    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const effectiveMethod = (form) => {
        const override = form.querySelector('input[name="_method"]')?.value || '';
        const method = override || form.getAttribute('method') || 'GET';
        return String(method).trim().toUpperCase();
    };

    const shouldSkip = (form) => {
        if (!(form instanceof HTMLFormElement)) return true;
        if ((form.dataset.mpgAjax || '').toLowerCase() === 'off') return true;
        if ((form.dataset.ajax || '').toLowerCase() === 'false') return true;
        if (form.hasAttribute('data-mpg-native-submit')) return true;
        if (form.target && form.target !== '_self') return true;
        if (form.id === 'branchSwitcherForm') return true;
        try {
            const action = new URL(form.action || window.location.href, window.location.href);
            if (action.pathname === '/logout') return true;
        } catch {
        }
        return !mutatingMethods.has(effectiveMethod(form));
    };

    const messageFromPayload = (payload, fallback) => {
        if (payload && typeof payload.message === 'string' && payload.message.trim() !== '') {
            return payload.message.trim();
        }
        const errors = payload?.messages?.errors || payload?.errors;
        if (Array.isArray(errors) && errors.length) {
            return errors.map((x) => String(x || '').trim()).filter(Boolean).join('\n');
        }
        const success = payload?.messages?.success;
        if (Array.isArray(success) && success.length) {
            return String(success[0] || fallback);
        }
        return fallback;
    };

    const parseResponse = async (res) => {
        const raw = await res.text();
        const ct = res.headers.get('content-type') || '';
        if (ct.includes('application/json')) {
            try {
                return raw ? JSON.parse(raw) : {};
            } catch {
                return { success: false, message: 'Invalid JSON response.' };
            }
        }
        if (raw.trim().startsWith('{')) {
            try {
                return JSON.parse(raw);
            } catch {
            }
        }
        return {
            success: res.ok,
            message: raw.trim() ? raw.trim().slice(0, 500) : (res.ok ? 'Saved successfully.' : 'Could not complete action.'),
        };
    };

    const setBusy = (form, busy) => {
        form.classList.toggle('mpg-ajax-busy', busy);
        form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((btn) => {
            if (!(btn instanceof HTMLElement)) return;
            if (busy) {
                btn.dataset.mpgAjaxWasDisabled = btn.disabled ? '1' : '0';
                btn.disabled = true;
            } else if (btn.dataset.mpgAjaxWasDisabled !== '1') {
                btn.disabled = false;
            }
        });
    };

    const closeContainingModal = (form) => {
        if ((form.dataset.mpgAjaxCloseModal || '').toLowerCase() === 'false') return;
        const modalEl = form.closest('.modal');
        const Modal = window.bootstrap?.Modal;
        if (modalEl && Modal) {
            Modal.getOrCreateInstance(modalEl).hide();
        }
    };

    const refreshTables = () => {
        if (!window.jQuery?.fn?.DataTable) return;
        window.jQuery('table.dataTable').each(function () {
            if (!window.jQuery.fn.DataTable.isDataTable(this)) return;
            try {
                window.jQuery(this).DataTable().ajax.reload(null, false);
            } catch {
            }
        });
    };

    const applyDomHints = (form, method) => {
        const removeSelector = form.dataset.mpgAjaxRemoveClosest || '';
        const removeTarget = removeSelector ? form.closest(removeSelector) : null;
        if (removeTarget) {
            removeTarget.remove();
            return;
        }
        if (method === 'DELETE') {
            form.closest('tr')?.remove();
        }
    };

    const shouldReloadOnSuccess = (form) => {
        const flag = (form.dataset.mpgAjaxReload || '').toLowerCase();
        if (flag === 'false' || flag === 'off' || flag === 'no') return false;
        return true;
    };

    const notify = (type, message, title) => {
        const text = String(message || '').trim();
        if (!text) return Promise.resolve();
        if (typeof Swal !== 'undefined') {
            return Swal.fire({
                icon: type,
                title,
                text,
                timer: type === 'success' ? 2200 : undefined,
                timerProgressBar: type === 'success',
                showConfirmButton: type !== 'success',
                confirmButtonColor: type === 'success' ? '#198754' : '#dc3545',
            });
        }
        return window.mpgAlert
            ? window.mpgAlert(text, { title, icon: type })
            : Promise.resolve(window.alert(text));
    };

    const submitAjax = async (form, submitter = null) => {
        if (form.dataset.mpgAjaxSubmitting === '1') return;
        form.dataset.mpgAjaxSubmitting = '1';

        const method = effectiveMethod(form);
        let body;
        try {
            body = submitter ? new FormData(form, submitter) : new FormData(form);
        } catch {
            body = new FormData(form);
            if (submitter?.name) {
                body.append(submitter.name, submitter.value || '');
            }
        }
        if (!body.has('_token') && csrfToken()) {
            body.append('_token', csrfToken());
        }

        setBusy(form, true);
        try {
            const htmlMethod = (form.getAttribute('method') || 'POST').toUpperCase();
            const res = await fetch(form.action || window.location.href, {
                method: htmlMethod === 'GET' ? 'POST' : htmlMethod,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body,
                credentials: 'same-origin',
            });
            const payload = await parseResponse(res);
            const ok = res.ok && payload.success !== false && payload.ok !== false;
            const detail = { form, method, payload, response: res };

            if (!ok) {
                form.dispatchEvent(new CustomEvent('mpg:ajax-error', { bubbles: true, detail }));
                await notify('error', messageFromPayload(payload, `Request failed (${res.status}).`), 'Could not save');
                return;
            }

            closeContainingModal(form);
            applyDomHints(form, method);
            refreshTables();
            form.dispatchEvent(new CustomEvent('mpg:ajax-success', { bubbles: true, detail }));
            document.dispatchEvent(new CustomEvent('mpg:ajax-success', { detail }));

            if ((form.dataset.mpgAjaxReset || '').toLowerCase() === 'true') {
                form.reset();
            }
            if ((form.dataset.mpgAjaxSilent || '').toLowerCase() !== 'true') {
                await notify('success', messageFromPayload(payload, 'Saved successfully.'), 'Saved');
            }
            if (shouldReloadOnSuccess(form)) {
                window.location.reload();
            }
        } catch (err) {
            form.dispatchEvent(new CustomEvent('mpg:ajax-error', {
                bubbles: true,
                detail: { form, method, error: err },
            }));
            await notify('error', 'Network error. Please try again.', 'Could not save');
        } finally {
            setBusy(form, false);
            form.dataset.mpgAjaxSubmitting = '0';
        }
    };

    window.mpgSubmitAjaxForm = submitAjax;

    window.jQuery(document).on('submit.mpgAjaxActions', 'form', function (event) {
        if (event.isDefaultPrevented()) return;
        const form = this;
        if (shouldSkip(form)) return;
        event.preventDefault();
        submitAjax(form, event.originalEvent?.submitter || document.activeElement);
    });
})();
