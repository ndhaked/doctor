/**
 * Sends the website forms (<form data-enquiry-form>) in the background, so the page never reloads.
 *
 * - Validation errors from the server are shown next to each field (or as a list when the form has data-errors="summary").
 * - A success or failure message appears inside the form (in an element with data-form-status, created if missing).
 * - Forms with data-close-after="N" close their popup N ms after a successful send.
 */

const CLASSES = {
    success: 'mt-3 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800',
    error: 'mt-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700',
    info: 'mt-3 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700',
};

function statusBox(form) {
    let box = form.querySelector('[data-form-status]');

    if (!box) {
        box = document.createElement('div');
        box.setAttribute('data-form-status', '');
        box.setAttribute('role', 'status');
        box.setAttribute('aria-live', 'polite');
        form.appendChild(box);
    }

    return box;
}

function setStatus(form, type, content) {
    const box = statusBox(form);
    box.className = CLASSES[type];
    box.replaceChildren();

    if (Array.isArray(content)) {
        const list = document.createElement('ul');
        list.className = 'list-disc pl-4 space-y-0.5';
        content.forEach((text) => {
            const item = document.createElement('li');
            item.textContent = text;
            list.appendChild(item);
        });
        box.appendChild(list);
    } else {
        box.textContent = content;
    }
}

function clearFeedback(form) {
    form.querySelectorAll('.enquiry-error').forEach((el) => el.remove());
    form.querySelectorAll('[aria-invalid]').forEach((el) => el.removeAttribute('aria-invalid'));

    const box = form.querySelector('[data-form-status]');
    if (box) {
        box.className = 'hidden';
        box.replaceChildren();
    }
}

function showFieldErrors(form, errors) {
    const messages = [];
    const summary = form.dataset.errors === 'summary';

    Object.entries(errors).forEach(([field, list]) => {
        const message = list[0];
        const input = form.querySelector(`[name="${field}"]`);

        if (input) {
            input.setAttribute('aria-invalid', 'true');
        }

        if (summary || !input) {
            messages.push(message);
            return;
        }

        const note = document.createElement('p');
        note.className = 'enquiry-error mt-1 text-xs text-red-600';
        note.setAttribute('role', 'alert');
        note.textContent = message;

        (input.closest('label') || input.parentElement).insertAdjacentElement('afterend', note);
    });

    if (messages.length) {
        setStatus(form, 'error', messages);
    }

    const first = form.querySelector('[aria-invalid]');
    if (first) {
        first.focus({ preventScroll: false });
    }
}

async function send(form, event) {
    event.preventDefault();

    if (form.dataset.sending === '1') {
        return;
    }

    const button = form.querySelector('[type="submit"]');
    const label = button ? button.textContent : '';

    clearFeedback(form);
    form.dataset.sending = '1';

    if (button) {
        button.disabled = true;
        button.classList.add('opacity-60', 'cursor-wait');
        button.textContent = 'Sending…';
    }

    try {
        const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

        const response = await fetch(form.action, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token,
            },
            body: new FormData(form),
            credentials: 'same-origin',
        });

        const data = await response.json().catch(() => ({}));

        if (response.status === 201) {
            form.reset();
            setStatus(form, 'success', data.message || 'Thank you! We will contact you shortly.');

            const closeAfter = parseInt(form.dataset.closeAfter || '0', 10);
            if (closeAfter > 0) {
                setTimeout(() => {
                    form.closest('[role="dialog"]')?.querySelector('[data-close-appointment]')?.click();
                    clearFeedback(form);
                }, closeAfter);
            }
        } else if (response.status === 422 && data.errors) {
            showFieldErrors(form, data.errors);
        } else if (response.status === 429) {
            setStatus(form, 'error', 'You are sending requests too quickly. Please wait a minute and try again.');
        } else if (response.status === 419) {
            setStatus(form, 'error', 'This page has been open for a long time. Please refresh the page and try again.');
        } else {
            setStatus(form, 'error', 'Sorry, something went wrong. Please try again or call us.');
        }
    } catch (error) {
        setStatus(form, 'error', 'Could not reach the server. Please check your connection and try again.');
    } finally {
        delete form.dataset.sending;

        if (button) {
            button.disabled = false;
            button.classList.remove('opacity-60', 'cursor-wait');
            button.textContent = label;
        }
    }
}

export function initEnquiryForms(root = document) {
    root.querySelectorAll('form[data-enquiry-form]').forEach((form) => {
        if (form.dataset.enquiryBound) {
            return;
        }

        form.dataset.enquiryBound = '1';
        form.setAttribute('novalidate', '');
        form.addEventListener('submit', (event) => send(form, event));
    });
}

document.addEventListener('DOMContentLoaded', () => initEnquiryForms());
