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
});