<?php
// Google Maps API Configuration
// Get your API key from: https://console.cloud.google.com/google/maps-apis/
define('GOOGLE_MAPS_API_KEY', getenv('GOOGLE_MAPS_API_KEY') ?: 'YOUR_GOOGLE_MAPS_API_KEY_HERE');

// Barangay Mudiang Coordinates
define('MUDIANG_LAT', 7.1907);
define('MUDIANG_LNG', 125.4553);

// Barangay Mudiang Bounds
define('MUDIANG_BOUNDS', [
    'south' => 7.1870,
    'west' => 125.4510,
    'north' => 7.1945,
    'east' => 125.4595
]);
?>
