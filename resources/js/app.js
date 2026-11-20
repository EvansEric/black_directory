class ToastrManager {
    constructor() {
        this.region = document.createElement('div');
        this.region.className = 'toastr-region';
        this.region.setAttribute('aria-live', 'polite');
        this.region.setAttribute('aria-atomic', 'false');
        document.body.append(this.region);
    }

    show(message, type = 'success', duration = 5000) {
        const toast = this.createToast(message, type);
        const closeButton = this.createButton('Dismiss', 'toastr-close', () => this.dismiss(toast));
        closeButton.setAttribute('aria-label', 'Dismiss notification');

        toast.append(closeButton);
        this.region.append(toast);
        requestAnimationFrame(() => toast.classList.add('is-visible'));

        window.setTimeout(() => this.dismiss(toast), duration);
    }

    confirm(message, { type = 'warning', confirmLabel = 'Confirm', title = 'Please confirm' } = {}) {
        return new Promise((resolve) => {
            const confirmationRegion = document.createElement('div');
            confirmationRegion.className = 'toastr-confirmation-region';
            confirmationRegion.setAttribute('aria-live', 'assertive');
            document.body.append(confirmationRegion);

            const toast = this.createToast(message, type, title);
            const previousFocus = document.activeElement;
            toast.setAttribute('role', 'alertdialog');
            toast.setAttribute('aria-modal', 'true');
            const actions = document.createElement('div');
            actions.className = 'toastr-actions';

            const finish = (confirmed) => {
                document.removeEventListener('keydown', handleKeydown);
                this.dismiss(toast, (result) => {
                    confirmationRegion.remove();
                    resolve(result);
                }, confirmed);
                previousFocus instanceof HTMLElement && previousFocus.focus();
            };

            const handleKeydown = (event) => {
                if (event.key === 'Escape') {
                    finish(false);
                }
            };

            const cancelButton = this.createButton('Cancel', 'toastr-button toastr-button--secondary', () => finish(false));
            const confirmButton = this.createButton(confirmLabel, `toastr-button toastr-button--${type}`, () => finish(true));

            actions.append(cancelButton, confirmButton);
            toast.append(actions);
            confirmationRegion.append(toast);
            document.addEventListener('keydown', handleKeydown);
            requestAnimationFrame(() => {
                toast.classList.add('is-visible');
                cancelButton.focus();
            });
        });
    }

    createToast(message, type, title = null) {
        const toast = document.createElement('div');
        toast.className = `toastr toastr--${type}`;
        toast.setAttribute('role', type === 'error' ? 'alert' : 'status');

        const icon = document.createElement('i');
        icon.className = this.iconFor(type);
        icon.setAttribute('aria-hidden', 'true');

        const content = document.createElement('div');
        content.className = 'toastr-content';

        if (title) {
            const heading = document.createElement('p');
            heading.className = 'toastr-title';
            heading.textContent = title;
            content.append(heading);
        }

        const body = document.createElement('p');
        body.className = 'toastr-message';
        body.textContent = message;
        content.append(body);
        toast.append(icon, content);

        return toast;
    }

    createButton(label, className, handler) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = className;
        button.textContent = label;
        button.addEventListener('click', handler);

        return button;
    }

    dismiss(toast, resolve = null, value = undefined) {
        if (!toast.isConnected || toast.dataset.dismissing === 'true') {
            return;
        }

        toast.dataset.dismissing = 'true';
        toast.classList.remove('is-visible');
        window.setTimeout(() => {
            toast.remove();
            resolve?.(value);
        }, 180);
    }

    iconFor(type) {
        return {
            danger: 'fa-solid fa-triangle-exclamation toastr-icon',
            error: 'fa-solid fa-circle-exclamation toastr-icon',
            warning: 'fa-solid fa-circle-question toastr-icon',
            success: 'fa-solid fa-circle-check toastr-icon',
            info: 'fa-solid fa-circle-info toastr-icon',
        }[type] ?? 'fa-solid fa-circle-info toastr-icon';
    }
}

const toastr = new ToastrManager();
const confirmedTriggers = new WeakSet();

window.toastr = toastr;

document.addEventListener('click', async (event) => {
    const trigger = event.target instanceof Element
        ? event.target.closest('[data-toastr-confirm]')
        : null;

    if (!trigger || confirmedTriggers.has(trigger)) {
        return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();

    const confirmed = await toastr.confirm(trigger.dataset.toastrConfirm, {
        type: trigger.dataset.toastrType ?? 'warning',
        confirmLabel: trigger.dataset.toastrConfirmLabel ?? 'Confirm',
        title: trigger.dataset.toastrTitle ?? 'Please confirm',
    });

    if (!confirmed || !trigger.isConnected) {
        return;
    }

    confirmedTriggers.add(trigger);
    trigger.click();
    queueMicrotask(() => confirmedTriggers.delete(trigger));
}, true);

document.addEventListener('alpine:init', () => {
    Alpine.store('theme', {
        dark: localStorage.getItem('noir_theme') === 'dark',

        toggle() {
            this.dark = !this.dark;
            localStorage.setItem('noir_theme', this.dark ? 'dark' : 'light');
        },
    });


    Alpine.data('nearMe', (options = {}) => ({
        busy: false,
        error: '',
        automatic: options.autoPrompt ?? true,
        attempted: false,

        init() {
            if (this.automatic && !this.attempted && this.$wire.userLat === null && !this.$wire.location.trim()) {
                this.locate();
            }
        },

        locate() {
            if (!navigator.geolocation) {
                this.error = 'Location is not supported by this browser.';
                return;
            }

            if (this.busy) {
                return;
            }

            this.attempted = true;
            this.busy = true;
            this.error = '';

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    this.busy = false;
                    this.$wire.setLocation(position.coords.latitude, position.coords.longitude);
                },
                (error) => {
                    this.busy = false;
                    this.error = error.code === 1
                        ? 'Allow location access to see nearby businesses.'
                        : 'We could not determine your location. Try again or search by city or ZIP code.';
                    this.$wire.set('distance', 'any');
                },
                { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 },
            );
        },
    }));

    Alpine.data('addressAutocomplete', () => ({
        suggestions: [],
        open: false,
        loading: false,
        error: '',
        activeIndex: -1,
        sessionToken: crypto.randomUUID(),
        timer: null,
        requestId: 0,

        search(value) {
            this.$wire.clearAddressCoordinates();
            clearTimeout(this.timer);
            const input = value.trim();

            if (input.length < 2) {
                this.suggestions = [];
                this.open = false;
                return;
            }

            this.timer = setTimeout(() => this.loadSuggestions(input), 300);
        },

        async loadSuggestions(input) {
            const requestId = ++this.requestId;
            this.loading = true;
            this.error = '';
            this.open = true;
            this.activeIndex = -1;

            try {
                const query = new URLSearchParams({
                    input,
                    session_token: this.sessionToken,
                });
                const response = await fetch(`${this.$root.dataset.autocompleteUrl}?${query}`, {
                    headers: { Accept: 'application/json' },
                });
                const payload = await response.json();

                if (!response.ok) {
                    throw new Error(payload.message || 'Address autocomplete is unavailable.');
                }

                if (requestId === this.requestId) {
                    this.suggestions = payload.suggestions ?? [];
                }
            } catch (error) {
                if (requestId === this.requestId) {
                    this.suggestions = [];
                    this.error = error.message;
                }
            } finally {
                if (requestId === this.requestId) {
                    this.loading = false;
                }
            }
        },

        async select(suggestion) {
            this.loading = true;
            this.error = '';
            const sessionToken = this.sessionToken;

            try {
                const url = this.$root.dataset.detailsUrl.replace('__PLACE__', encodeURIComponent(suggestion.place_id));
                const response = await fetch(`${url}?${new URLSearchParams({ session_token: sessionToken })}`, {
                    headers: { Accept: 'application/json' },
                });
                const place = await response.json();

                if (!response.ok) {
                    throw new Error(place.message || 'We could not load that address.');
                }

                this.$wire.setAddressFromAutocomplete(
                    place.address,
                    place.city,
                    place.state,
                    place.zip,
                    place.latitude,
                    place.longitude,
                );
                this.$refs.address.value = place.address;
                this.sessionToken = crypto.randomUUID();
                this.close();
            } catch (error) {
                this.open = true;
                this.error = error.message;
            } finally {
                this.loading = false;
            }
        },

        move(offset) {
            if (!this.open || this.suggestions.length === 0) {
                return;
            }

            this.activeIndex = (this.activeIndex + offset + this.suggestions.length) % this.suggestions.length;
        },

        chooseActive() {
            if (this.activeIndex >= 0) {
                this.select(this.suggestions[this.activeIndex]);
            }
        },

        close() {
            this.open = false;
            this.activeIndex = -1;
        },

        closeAfterDelay() {
            setTimeout(() => this.close(), 100);
        },
    }));
});