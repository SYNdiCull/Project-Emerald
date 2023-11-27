<?php

// Include your database connection script
include('db_config.php'); // Change this to the actual name of your database connection script

// Fetch unique player names
$stmt = $conn->prepare("SELECT DISTINCT match_id FROM player_stats");
$stmt->execute();
$result = $stmt->get_result();

// Extract match IDs into an array
$matchIds = array();
while ($row = $result->fetch_assoc()) {
    $matchIds[] = $row['match_id'];
}
// Encode the array as JSON and echo the response
echo json_encode($matchIds);

// Close database connection and statements
$stmt->close();
$conn->close();

?>
