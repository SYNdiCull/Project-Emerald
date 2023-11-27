<?php
include('./db_config.php');

// Function to get player stats for a specific match
function getPlayerStatsForMatch($conn, $matchId) {
    $playerStats = array();

    $stmt = $conn->prepare("SELECT * FROM player_stats WHERE match_id = ?");
    $stmt->bind_param("s", $matchId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $playerStats[] = $row;
    }

    $stmt->close();

    return $playerStats;
}

// Get match ID from the AJAX request
$matchId = $_POST['matchId'];

// Fetch player stats for the specified match
$playerStats = getPlayerStatsForMatch($conn, $matchId);

// Return player stats in JSON format
echo json_encode($playerStats);
?>
