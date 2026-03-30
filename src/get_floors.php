<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['building_id'])) {
    $building_id = $_POST['building_id'];
    
    $stmt = $conn->prepare("SELECT id, floor_name FROM floors WHERE building_id = ? ORDER BY floor_name");
    $stmt->bind_param("i", $building_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo '<option value="">Select Floor</option>';
    while ($floor = $result->fetch_assoc()) {
        echo '<option value="' . $floor['id'] . '">' . htmlspecialchars($floor['floor_name']) . '</option>';
    }
    
    $stmt->close();
}
?>