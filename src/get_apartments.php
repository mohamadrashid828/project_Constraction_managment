<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['floor_id'])) {
    $floor_id = $_POST['floor_id'];
    
    $stmt = $conn->prepare("SELECT id, apartment_number FROM apartments WHERE floor_id = ? ORDER BY apartment_number");
    $stmt->bind_param("i", $floor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo '<option value="">Select Apartment (Optional)</option>';
    while ($apartment = $result->fetch_assoc()) {
        echo '<option value="' . $apartment['id'] . '">' . htmlspecialchars($apartment['apartment_number']) . '</option>';
    }
    
    $stmt->close();
}
?>