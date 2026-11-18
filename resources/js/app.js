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
});