<?php
include('./db_config.php');

// Function to get all unique match IDs from the player_stats table
function getMatchIds($conn) {
    $matchIds = array();

    $stmt = $conn->prepare("SELECT DISTINCT match_id FROM player_stats");
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $matchIds[] = $row['match_id'];
    }

    $stmt->close();

    return $matchIds;
}

// Fetch and return match IDs
$matchIds = getMatchIds($conn);
echo json_encode($matchIds);
?>
