<?php
require_once '../config.php';
require_once 'includes/ajax_options.php';

render_options_endpoint(
    $conn,
    'building_id',
    "SELECT id, floor_name FROM floors WHERE building_id = ? ORDER BY floor_name",
    'id',
    'floor_name',
    'Select Floor'
);
