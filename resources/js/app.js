document.addEventListener('alpine:init', () => {
    Alpine.store('theme', {
        dark: localStorage.getItem('noir_theme') === 'dark',

        toggle() {
            this.dark = !this.dark;
            localStorage.setItem('noir_theme', this.dark ? 'dark' : 'light');
        },
    });

    Alpine.data('nearMe', () => ({
        busy: false,
        error: '',

        init() {
            // Default view is businesses within 5 miles, so ask for the location up front
            // unless the visitor already blocked it or chose "Any distance".
            const wanted = () => this.$wire.distance !== 'any' && this.$wire.userLat === null;

            if (!navigator.permissions) {
                if (wanted()) this.locate();
                return;
            }

            navigator.permissions.query({ name: 'geolocation' }).then((status) => {
                if (status.state !== 'denied' && wanted()) {
                    this.locate();
                }
            }).catch(() => {});
        },

        locate() {
            if (!navigator.geolocation) {
                this.error = 'Location is not supported by this browser.';
                return;
            }

            this.busy = true;
            this.error = '';

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    this.busy = false;
                    this.$wire.setLocation(position.coords.latitude, position.coords.longitude);
                },
                () => {
                    this.busy = false;
                    this.error = 'Allow location access to see nearby businesses.';
                    this.$wire.set('distance', 'any');
                },
                { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 },
            );
        },
    }));

    Alpine.data('directorySearch', () => ({
        locating: false,

        init() {
            // Do not automatically trigger geolocation on component init to prevent continuous Livewire re-render loops
        },

        askLocation(force = false) {
            if (!navigator.geolocation) {
                return;
            }

            this.locating = true;

            navigator.geolocation.getCurrentPosition(
                async (position) => {
                    try {
                        const lat = position.coords.latitude;
                        const lon = position.coords.longitude;

                        let locationName = null;

                        try {
                            const res = await fetch(`https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=${lat}&longitude=${lon}&localityLanguage=en`);
                            if (res.ok) {
                                const data = await res.json();
                                const city = data.city || data.locality || '';
                                const state = data.principalSubdivisionCode ? data.principalSubdivisionCode.replace(/^[A-Z]{2}-/, '') : (data.principalSubdivision || '');
                                if (city && state) {
                                    locationName = `${city}, ${state}`;
                                } else if (city) {
                                    locationName = city;
                                } else if (data.postcode) {
                                    locationName = data.postcode;
                                }
                            }
                        } catch (e) {}

                        if (!locationName) {
                            try {
                                const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lon}`);
                                if (res.ok) {
                                    const data = await res.json();
                                    const addr = data.address || {};
                                    const city = addr.city || addr.town || addr.village || addr.suburb || addr.county || '';
                                    const state = addr.state || '';
                                    if (city && state) {
                                        locationName = `${city}, ${state}`;
                                    } else if (city) {
                                        locationName = city;
                                    } else if (addr.postcode) {
                                        locationName = addr.postcode;
                                    }
                                }
                            } catch (e) {}
                        }

                        if (locationName && this.$wire) {
                            if (force || !this.$wire.location) {
                                this.$wire.set('location', locationName);
                            }
                        }
                    } catch (err) {
                        console.error('Error determining location:', err);
                    } finally {
                        this.locating = false;
                    }
                },
                (error) => {
                    this.locating = false;
                },
                {
                    enableHighAccuracy: false,
                    timeout: 10000,
                    maximumAge: 300000,
                }
            );
        },
    }));
});