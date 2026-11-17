import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';

function nearMe(geolocation, wire = {}) {
    let createNearMe;
    const context = {
        navigator: { geolocation },
        localStorage: { getItem: () => null },
        Alpine: {
            store() {},
            data(name, factory) {
                if (name === 'nearMe') createNearMe = factory;
            },
        },
        document: { addEventListener(event, callback) { callback(); } },
    };

    runInNewContext(readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8'), context);

    return Object.assign(createNearMe(), {
        $wire: { userLat: null, location: '', distance: 'any', ...wire },
    });
}

test('requests browser location on the default guest view and passes coordinates to Livewire', () => {
    let coordinates;
    const component = nearMe({
        getCurrentPosition(success) {
            success({ coords: { latitude: 33.749, longitude: -84.388 } });
        },
    }, { setLocation: (...args) => { coordinates = args; } });

    component.init();

    assert.deepEqual(coordinates, [33.749, -84.388]);
    assert.equal(component.busy, false);
});

test('does not request location when a location is already provided', () => {
    const geolocation = { getCurrentPosition: () => assert.fail('Unexpected location request') };
    nearMe(geolocation, { location: 'Atlanta' }).init();
    nearMe(geolocation, { userLat: 33.749 }).init();
});

test('shows an error and clears the distance filter when permission is denied', () => {
    let distance;
    const component = nearMe({
        getCurrentPosition(success, failure) { failure(); },
    }, { distance: '10', set: (name, value) => { distance = [name, value]; } });

    component.init();

    assert.equal(component.error, 'Allow location access to see nearby businesses.');
    assert.deepEqual(distance, ['distance', 'any']);
    assert.equal(component.busy, false);
});
