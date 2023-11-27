<?php

function getSummonerRank($summonerName, $apiKey) {
    // API endpoint for getting a summoner's ID
    $summonerUrl = "https://na1.api.riotgames.com/lol/summoner/v4/summoners/by-name/" . rawurlencode(htmlspecialchars(strip_tags($summonerName))) . "?api_key=" . $apiKey;

    $contextOptions = [
        "ssl" => [
            "verify_peer" => false,
            "verify_peer_name" => false,
        ],
    ];

    $context = stream_context_create($contextOptions);

    try {
        // Attempt to fetch the data with the specified context
        $response = file_get_contents($summonerUrl, false, $context);

        if ($response === false) {
            // Handle the case where file_get_contents() fails
            throw new Exception("Unable to get RIOT data");
        }

        // Process the response here

    } catch (Exception $e) {
        // Handle the exception
        echo "Error: " . $e->getMessage();
    }


    if ($response) {
        $summonerData = json_decode($response, true);

        if (isset($summonerData['id'])) {
            // Get the summoner ID
            $summonerId = $summonerData['id'];

            // API endpoint for getting summoner's rank
            $rankUrl = "https://na1.api.riotgames.com/lol/league/v4/entries/by-summoner/$summonerId?api_key=$apiKey";

            // Send a request to get summoner's rank
            $rankData = file_get_contents($rankUrl);

            if ($rankData) {
                $rankInfo = json_decode($rankData, true);

                // Check if the summoner has ranked data
                if (!empty($rankInfo)) {
                    // Find the "RANKED_SOLO_5x5" entry
                    foreach ($rankInfo as $entry) {
                        if ($entry['queueType'] === "RANKED_SOLO_5x5") {
                            $tier = $entry['tier'];
                            $rank = $entry['rank'];
                            return $tier . ' ' . $rank;
                        }
                    }
                    return "Error summoner $summonerName is unranked in RANKED_SOLO_5x5.";
                } else {
                    return "Error summoner $summonerName is unranked.";
                }
            } else {
                return "Error fetching rank data.";
            }
        } else {
            return "Error summoner not found.";
        }
    } else {
        return "Error fetching summoner data.";
    }
}


function getMatchData($matchId, $riotToken) {
    $url = "https://americas.api.riotgames.com/lol/match/v5/matches/$matchId";
    $headers = [
        "Accept: Application/json",
        "X-Riot-Token: $riotToken",
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $output = curl_exec($ch);

    if ($output === false) {
        // cURL request failed
        return ["error" => "cURL request failed"];
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        // The API request returned an error response
        return ["error" => "API request error (HTTP code: $httpCode)"];
    }

    $decodedOutput = json_decode($output, true, JSON_PRETTY_PRINT);

    if ($decodedOutput === null) {
        // JSON decoding failed
        return ["error" => "Invalid JSON response"];
    }

    return $decodedOutput;
}

// not in use
function getPlayerNamesFromMatch($matchId, $riotToken, $conn) {
    $matchData = getMatchData($matchId, $riotToken);
    if (isset($matchData['metadata']['participants'])) {
        $puuids = $matchData['metadata']['participants'];
        $playerNames = array();

        // Get existing PUUIDs and player names from the database
        $existingData = getExistingPuuidsFromDatabase($conn);

        foreach ($puuids as $puuid) {
            if (array_key_exists($puuid, $existingData)) {
                // PUUID exists in the database
                $playerNames[] = $existingData[$puuid];
            } else {
                // PUUID not in the database, retrieve the name from Riot API
                $playerData = getPlayerDataFromPuuid($puuid, $riotToken);
                if (isset($playerData['name'])) {
                    $playerName = $playerData['name'];
                    $playerNames[] = $playerName;

                    // Insert the PUUID and player name into the database
                    storePlayerDataInDatabase($conn, $puuid, $playerName);
                }
            }
        }

        return $playerNames;
    } else {
        return [];
    }
}




// Function to get PUUID from the database
function getExistingPuuidsFromDatabase($conn) {
    $existingPuuids = array();

    // Replace 'your_table_name' with the actual table name where PUUIDs are stored
    $tableName = 'players';

    $sql = "SELECT puuid FROM $tableName";

    $result = mysqli_query($conn, $sql);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $existingPuuids[] = $row['puuid'];
        }
    }

    return $existingPuuids;
}



// Function to get player name from the database
function getPlayerNameFromDatabaseByPuuid($conn, $puuid) {
    // Assuming you have a table named "players" with columns "puuid" and "name" in your database

    $query = "SELECT name FROM players WHERE puuid = ?";
    
    // Prepare the query
    $stmt = $conn->prepare($query);
    
    if ($stmt) {
        // Bind the parameter
        $stmt->bind_param("s", $puuid);

        // Execute the query
        $stmt->execute();

        // Assign the player name variable
        $playerName = null;

        // Bind the result variable
        $stmt->bind_result($playerName);

        // Fetch the result
        $stmt->fetch();

        // Close the statement
        $stmt->close();

        if (!empty($playerName)) {
            return $playerName;
        }
    }

    return false; // Return false if the player name is not found in the database
}



// return example for below function:
/* 

{
   "accountId" : "ArxniEVUSK39aNLwiYG7SeSsCDkpKwD1N0c5QEDjhLze_dHxL9qR6FxB",
   "id" : "bj0sUF7F5hafFgUxF00j9UfgVxqRE-GH8dR9_K-ZzgjXurKM",
   "name" : "iEnders",
   "profileIconId" : 6334,
   "puuid" : "MOhvcokrRxJSRpGM-y3AVBQ0glnSrXHW-Y_fE6zHGXBPiY1SP6dKjIFQK0RFLXVgkdPdmxyHcsy-Yg",
   "revisionDate" : 1699288659424,
   "summonerLevel" : 692
}

*/
function getPlayerDataFromPuuid($puuid, $riotToken) {
    $url = "https://na1.api.riotgames.com/lol/summoner/v4/summoners/by-puuid/$puuid";
    $headers = [
        "Accept: Application/json",
        "X-Riot-Token: $riotToken",
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $output = curl_exec($ch);

    if ($output === false) {
        // cURL request failed
        return ["error" => "cURL request failed"];
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        // The API request returned an error response
        return ["error" => "API request error (HTTP code: $httpCode)"];
    }

    $decodedOutput = json_decode($output, true);
    if ($decodedOutput === null) {
        // JSON decoding failed
        return ["error" => "Invalid JSON response"];
    }

    return $decodedOutput;
}

// check if a player name exists in database,
// if it does then set the puuid to the one found for the name

function storePlayerDataInDatabase($conn, $puuid, $playerName) {
    // You should replace 'your_table_name' with the actual table name in your database.
    $tableName = 'players';

    // Check if the name already exists in the database.
    $sql = "SELECT * FROM $tableName WHERE name = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $playerName);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $updateSql = "UPDATE $tableName SET puuid = ? WHERE name = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("ss", $puuid, $playerName);
        $updateStmt->execute();
    }
}

function updatePuuidForExistingName($conn, $playerName, $puuid) {
    // Assuming you have a table named "players" with columns "puuid" and "name" in your database

    // Define the SQL query to update the PUUID for the existing player name
    $sql = "UPDATE players SET puuid = ? WHERE name = ?";

    // Prepare the query
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        // Bind the parameters (PUUID and player name)
        $stmt->bind_param("ss", $puuid, $playerName);

        // Execute the update query
        $stmt->execute();

        // Close the statement
        $stmt->close();
    }
}


function getExistingNamesFromDatabase($conn) {
    $existingNames = array();

    // Assuming you have a table named "players" with a "name" column in your database

    // Define the SQL query to retrieve existing player names
    $sql = "SELECT name FROM players";

    // Execute the query
    $result = mysqli_query($conn, $sql);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $existingNames[] = $row['name'];
        }
    }

    return $existingNames;
}



// returns all player names, kills, deaths, assists from match

function getPlayerMatchStats($matchId, $riotToken, $conn) {
    // Get match data using your function to fetch data from the Riot API
    $matchData = getMatchData($matchId, $riotToken);
    // Check if the 'participants' key exists in the match data
    if (isset($matchData['info']['participants'])) {
        $participants = $matchData['info']['participants'];

        // Initialize arrays to store player statistics
        $matchStats = array();

        // Check the database for existing PUUIDs and names
        $existingPuuids = getExistingPuuidsFromDatabase($conn);
        $existingNames = getExistingNamesFromDatabase($conn);
        // print_r($matchData);
        foreach ($participants as $participant) {
            $puuid = $participant['puuid'];
            $playerName = $participant['summonerName'];
            $gameTime = $matchData['info']['gameDuration'];
            // Check if the PUUID is in the database
            if (in_array($puuid, $existingPuuids)) {
                // Player exists by PUUID in the database; you can retrieve additional data as needed

                $kills = $participant['kills'];
                $deaths = $participant['deaths'];
                $assists = $participant['assists'];
                if ($participant['deaths'] == 0) {
                    $kad = ($participant['kills'] + $participant['assists']);
                    $kd = $participant['kills'];
                } else {
                    $kd = $participant['kills'] / $participant['deaths'];
                    $kad = ($participant['kills'] + $participant['assists']) / $participant['deaths'];
                }                
                $cs = $participant['totalMinionsKilled'];
                $csm = $participant['totalMinionsKilled'] / ($gameTime / 60);
                $kp = isset($participant['challenges']['killParticipation']) ? $participant['challenges']['killParticipation'] : 0;
                $vs = $participant['visionScore'];
                $kd = round($kd, 2);

                $kad = round($kad, 2);
                $csm = round($csm, 2);
                $ff = $participant['gameEndedInEarlySurrender'];
                $dmg = $participant['totalDamageDealtToChampions'];
                $dmm = round($participant['challenges']['damagePerMinute'], 2);
                $kp = round($kp, 4) * 100;
                $matchStats[] = array(
                    'PlayerName' => $playerName,
                    'Kills' => $kills,
                    'Deaths' => $deaths,
                    'Assists' => $assists,
                    'K/D' => $kd,
                    'K/D/A' => $kad,
                    'CS' => $cs,
                    'CSM' => $csm,
                    'FF' => $ff,
                    'MATCH_TIME' => $gameTime,
                    'DMG' => $dmg,
                    'DMM' => $dmm,
                    'VS' => $vs,
                    'KP' => $kp,
                );
            } else {
                // PUUID is not in the database, check if the player name exists
                if (in_array($playerName, $existingNames)) {
                    // Player name exists in the database, update the row with the PUUID
                    updatePuuidForExistingName($conn, $playerName, $puuid);
                }
            }
        }

        // Return the array of player statistics
        print_r($matchStats);
        return $matchStats;
    } else {
        return [];
    }
}

function getPlayerStatsTotals($conn, $playerName) {
    $totals = array(
        'total_kills' => 0,
        'total_deaths' => 0,
        'total_assists' => 0,
        'total_kd' => 0,
        'total_kad' => 0,
        'total_cs' => 0,
        'total_csm' => 0,
        'total_dmg' => 0,
        'total_dmm' => 0,
        'total_vision_score' => 0,
        'total_kp' => 0,
        'games' => 0,
        'total_average_kills' => 0,
        'total_average_deaths' => 0,
        'total_average_assists' => 0
    );

    // Fetch player stats for all matches
    $stmt = $conn->prepare("SELECT * FROM player_stats WHERE `name` = ?");
    $stmt->bind_param("s", $playerName);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        // Add each stat to the corresponding total
        $totals['total_kills'] += $row['kills'];
        $totals['total_deaths'] += $row['deaths'];
        $totals['total_assists'] += $row['assists'];
        $totals['total_kd'] += $row['kd'];
        $totals['total_kad'] += $row['kad'];
        $totals['total_cs'] += $row['cs'];
        $totals['total_csm'] += $row['csm'];
        $totals['total_dmg'] += $row['dmg'];
        $totals['total_dmm'] += $row['dmm'];
        $totals['total_vision_score'] += $row['vision_score'];
        $totals['total_kp'] += $row['kp'];
        $totals['games']++;
    }

    $stmt->close();

    // Calculate average values
    $totals['total_average_kills'] = $totals['total_kills'] / $totals['games'];
    $totals['total_average_deaths'] = $totals['total_deaths'] / $totals['games'];
    $totals['total_average_assists'] = $totals['total_assists'] / $totals['games'];

    return $totals;
}


// need to know current rank, last season peak rank, account level, games played this season/split


// data paths
/*
$data = $matchData['info']['participants']
foreach ($data as $participant) {
    $participant['kills']
    $participant['deaths']
    $participant['assists']
    needs calc
    needs calc
    needs calc
    $kd = $participant['kills'] / $participant['deaths']
    $kad = $participant['kills'] + $participant['assists']) / $participant['deaths']
    needs calc
    $cs = $participant['totalMinionsKilled']
    $csm = $participant['totalMinionsKilled'] / ($participant['gameDuration'] / 60);
    $participant['totalDamageDealt'] add to db total, the divide by games
    $participant['damagePerMinute']  add to db total, the divide by games
    $participant['visionScore'] add to db total
    $participant['visionScorePerMinute']
    $participant['killParticipation']
 

}



*/