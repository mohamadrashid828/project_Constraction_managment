<?php
require_once '../config.php';
require_once 'includes/ajax_options.php';

render_options_endpoint(
    $conn,
    'floor_id',
    "SELECT id, apartment_number FROM apartments WHERE floor_id = ? ORDER BY apartment_number",
    'id',
    'apartment_number',
    'Select Apartment (Optional)'
);
