(() => {
    const button = document.getElementById('current-location');
    const status = document.getElementById('location-status');
    let requestId = 0;

    function finish(message) {
        button.disabled = false;
        button.removeAttribute('aria-busy');
        status.textContent = message;
    }

    // A manually selected pin takes precedence over an unfinished location request.
    map.on('click', () => {
        requestId += 1;
        finish('Pin selected. You can tap the map again to adjust it.');
    });

    button.addEventListener('click', () => {
        if (!window.isSecureContext) {
            finish('Location access needs HTTPS or localhost. You can still tap the map to select the location.');
            return;
        }
        if (!navigator.geolocation) {
            finish('Your browser does not support location access. Tap the map to select the location.');
            return;
        }

        const activeRequest = ++requestId;
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        status.textContent = 'Finding your location… Allow location access if prompted.';

        navigator.geolocation.getCurrentPosition(position => {
            if (activeRequest !== requestId) return;
            const { latitude, longitude, accuracy } = position.coords;
            placeLocationPin(latitude, longitude);
            map.setView([latitude, longitude], 18);
            const accuracyText = Number.isFinite(accuracy) ? ` Estimated accuracy: ${Math.ceil(accuracy)} metres.` : '';
            finish(`Your current location is pinned.${accuracyText} Check the pin and tap the map to adjust it if needed.`);
        }, error => {
            if (activeRequest !== requestId) return;
            const messages = {
                1: 'Location access was denied. Allow it in your browser settings and try again, or tap the map.',
                2: 'Your location could not be determined. Check your device location settings and try again, or tap the map.',
                3: 'Finding your location took too long. Try again or tap the map to select the location.'
            };
            finish(messages[error.code] || 'Unable to get your location. Try again or tap the map.');
        }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
    });
})();
