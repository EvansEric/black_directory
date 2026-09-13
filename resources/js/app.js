document.addEventListener('alpine:init', () => {
    Alpine.store('favorites', {
        ids: JSON.parse(localStorage.getItem('roots_favorites') || '[]'),
        showOnly: false,

        has(id) {
            return this.ids.includes(id);
        },

        toggle(id) {
            this.ids = this.has(id)
                ? this.ids.filter((existing) => existing !== id)
                : [...this.ids, id];

            localStorage.setItem('roots_favorites', JSON.stringify(this.ids));
        },

        reset() {
            this.showOnly = false;
        },
    });

    Alpine.data('directorySearch', () => ({
        locating: false,

        init() {
            this.askLocation(false);
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