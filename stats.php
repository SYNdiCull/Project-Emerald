<?php
session_start();
include('./requester.php');
include('./db_config.php');

error_reporting(E_ALL);
ini_set('display_errors', 1);

if(!isset($_SESSION['id'])) {
    header("Location: /index.php");
}

if($_SESSION['isadmin'] == TRUE) {
    $guestoradmin = 'Admin';
} else {
    $guestoradmin = 'Guest';
}    


if (isset($_POST['confirmStats'])) {
    // Retrieve data from the form submission
    $matchId = $_POST['matchId'];
    

    $playerName = $_POST['playerName'];
    $kills = $_POST['kills'];
    $deaths = $_POST['deaths'];
    $assists = $_POST['assists'];
    $kd = $_POST['kd'];
    $kda = $_POST['kda'];
    $cs = $_POST['cs'];
    $ff = $_POST['ff'];
    $csm = $_POST['csm'];
    $dmg = $_POST['dmg'];
    $dmm = $_POST['dmm'];
    $vs = $_POST['vs'];
    $kp = $_POST['kp'];

    // Perform the insertion into the player_stats table
    // Use prepared statements to prevent SQL injection

    // Check if the match_id exists in match_stats before inserting into player_stats
    $checkMatchStmt = $conn->prepare("SELECT match_id FROM match_stats WHERE match_id = ?");
    $checkMatchStmt->bind_param("s", $matchId);
    
    if ($ff != 1) {
        // Check if the match_id exists in match_stats before inserting into player_stats
        if ($checkMatchStmt->execute() && $checkMatchStmt->fetch()) {


            echo 'Match ID has already been submitted, please use a different ID.';
            $checkMatchStmt->close();  
    
        } else {
            // The match_id doesn't exist in match_stats
            // Insert match_id into match_stats
            $checkMatchStmt->close();  // Close the result set
            $matchTime = $_SESSION['playerKDA'][0]['MATCH_TIME'];
            $insertMatchStmt = $conn->prepare("INSERT INTO match_stats (match_id, match_duration) VALUES (?, ?)");
            $insertMatchStmt->bind_param("si", $matchId, $matchTime);
    
            if ($insertMatchStmt->execute()) {
                echo "Match ID added to match_stats table. ";
    
                // Now proceed with inserting into player_stats
                foreach ($_SESSION['playerKDA'] as $playerStats) {
                    // Fetch team_id from players table based on player name
                    $fetchTeamIdStmt = $conn->prepare("SELECT team_id FROM players WHERE `name` = ?");
                    $fetchTeamIdStmt->bind_param("s", $playerStats['PlayerName']);
                    $fetchTeamIdStmt->execute();
                    $fetchTeamIdStmt->bind_result($teamId);
    
                    if ($fetchTeamIdStmt->fetch()) {
                        $fetchTeamIdStmt->close();  // Close after fetching
    
                        // Prepare a new statement for player_stats
                        $stmt = $conn->prepare("INSERT INTO player_stats (`name`, kills, deaths, assists, kd, kad, cs, csm, dmg, dmm, vision_score, kp, match_id, team_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("sdddddddddddss", $playerStats['PlayerName'], $playerStats['Kills'], $playerStats['Deaths'], $playerStats['Assists'], $playerStats['K/D'], $playerStats['K/D/A'], $playerStats['CS'], $playerStats['CSM'], $playerStats['DMG'], $playerStats['DMM'], $playerStats['VS'], $playerStats['KP'], $matchId, $teamId);
    
                        if ($stmt->execute()) {
                            echo "Player stats for " . htmlspecialchars(strip_tags($playerStats['PlayerName'])) . " confirmed and added to the player_stats table.<br>";
                        } else {
                            echo "Error adding player stats for " . htmlspecialchars(strip_tags($playerStats['PlayerName'])) . ": " . $stmt->error . "<br>";
                        }
    
                        $stmt->close();  // Close and free resources
                    } else {
                        echo "Error fetching team_id for player " . $playerStats['PlayerName'] . ": " . $fetchTeamIdStmt->error . "<br>";
                    }
                }
            } else {
                echo "Error adding match ID to match_stats table: " . $insertMatchStmt->error . "<br>";
            }
    
            $insertMatchStmt->close();
        }
    } else {
        echo "Game ended in forfeit. Not valid";
    }
}    

// Function to get all team IDs from the teams table
function getTeamIds($conn) {
    $teamIds = array();

    $stmt = $conn->prepare("SELECT team_id FROM teams");
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $teamIds[] = $row['team_id'];
    }

    $stmt->close();

    return $teamIds;
}

// Function to get players in a specific team from player_stats
function getPlayersInTeamStats($conn, $teamId) {
    $totals = array(
        'kills' => 0,
        'deaths' => 0,
        'assists' => 0,
        'kd' => 0,
        'kad' => 0,
        'cs' => 0,
        'csm' => 0,
        'dmg' => 0,
        'dmm' => 0,
        'vision_score' => 0,
        'kp' => 0
    );

    $stmt = $conn->prepare("SELECT * FROM player_stats WHERE team_id = ?");
    $stmt->bind_param("s", $teamId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        // Add each stat to the corresponding total
        $totals['kills'] += $row['kills'];
        $totals['deaths'] += $row['deaths'];
        $totals['assists'] += $row['assists'];
        $totals['kd'] += $row['kd'];
        $totals['kad'] += $row['kad'];
        $totals['cs'] += $row['cs'];
        $totals['csm'] += $row['csm'];
        $totals['dmg'] += $row['dmg'];
        $totals['dmm'] += $row['dmm'];
        $totals['vision_score'] += $row['vision_score'];
        $totals['kp'] += $row['kp'];
    }
    $name = $result['name'];
    $totals['name'] = $name;

    $stmt->close();

    return $totals;
}


// Function to display player stats in a table
function displayPlayerStatsTable($playerStats) {
    echo '<table class="table table-bordered" style="padding-left:10px;">';
    echo '<tr><th>Name</th><th>Kills</th><th>Deaths</th><th>Assists</th><th>K/D</th><th>K/D/A</th><th>CS</th><th>CSM</th><th>DMG</th><th>DMM</th><th>Vision Score</th><th>KP</th></tr>';
    
    // Display a single row for the player's stats
    echo '<tr>';
    echo '<td>' . $playerStats['name'] . '</td>';
    echo '<td>' . $playerStats['kills'] . '</td>';
    echo '<td>' . $playerStats['deaths'] . '</td>';
    echo '<td>' . $playerStats['assists'] . '</td>';
    echo '<td>' . $playerStats['kd'] . '</td>';
    echo '<td>' . $playerStats['kad'] . '</td>';
    echo '<td>' . $playerStats['cs'] . '</td>';
    echo '<td>' . $playerStats['csm'] . '</td>';
    echo '<td>' . $playerStats['dmg'] . '</td>';
    echo '<td>' . $playerStats['dmm'] . '</td>';
    echo '<td>' . $playerStats['vision_score'] . '</td>';
    echo '<td>' . $playerStats['kp'] . '</td>';
    echo '</tr>';

    echo '</table>';
}
?>
    
<!DOCTYPE html>
<html lang="en">
    <head>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="X-UA-Compatible" content="ie=edge">
        <title>Dashboard</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
        <link rel="stylesheet" href="styles/stats.css">
    </head>
    <body style="overflow-x: hidden;">
    <nav class="navbar bg-dark navbar-dark">
            <div class="container-fluid" >
                <a class="navbar-brand" href="/dashboard.php">Dashboard</a>

                <?php if ($guestoradmin == "Admin") {echo '<div class="tab active" onclick=openTab("statGen")>Generate Stats</div>';} ?>
                <?php if ($guestoradmin == "Guest") {echo '<div class="tab active" onclick=openTab("teamStats")>Team Stats</div>';} else {echo '<div class="tab" onclick=openTab("teamStats")>Team Stats</div>';}?>
                <div class="tab" onclick="openTab('generalStatsView')">Overall Player Stats</div>
                <?php if ($guestoradmin == "Admin") {echo '<div class="tab" onclick=openTab("teamManagement")>Team Management</div>';} ?>
                <!-- <div class="tab" onclick="openTab('teamOrganization')">Team Organization</div>
                <div class="tab" onclick="openTab('teamOrganization')">Team Organization</div> -->


                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#collapsibleNavbar">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="collapsibleNavbar">
                    <ul class="navbar-nav">
                        <li class="nav-item">
                            <a class="nav-link" style="text-align:right" href="tournaments.php">Tournaments</a>
                            <a class="nav-link" style="text-align:right" href="players.php">Players</a>
                            <a class="nav-link" style="text-align:right" href="logout.php">Logout</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    


        
    <div id="tabs">

        <div class="tabContent" id="statGenContent" style=<?php if ($guestoradmin == "Admin") {echo '"display: block;"';} else {echo '"display: none;"';} ?>>
            <form method="post">
                <label for="matchId">Match ID:</label>
                <input type="text" id="matchId" name="matchId" required>
                <br>
                <button name="submit" type="submit">Get Match Data</button>
            </form>

            
            <?php 
            $riotToken = $_SESSION['riotApiKey'];
            // get vds stats here

            if (isset($_POST['submit'])) {
                $matchId = htmlspecialchars($_POST['matchId']);
                
                $results = getMatchData($matchId, $riotToken);
            
                if (isset($results['error'])) {
                    echo "Error: " . $results['error'];
                } else {
                    echo "<pre>"; // Use <pre> tag for a more readable output
                    // print_r($results); // Use print_r to display the array contents
                    $playerName = getPlayerNamesFromMatch($matchId, $riotToken, $conn);
                    $_SESSION['playerKDA'] = getPlayerMatchStats($matchId, $riotToken, $conn);
                    echo "Retrieved data for: \n";
                    foreach ($_SESSION['playerKDA'] as $playerStats) {
                        echo 'Player: ' . $playerStats['PlayerName'] . "\n";
                        
                    }

                    echo "</pre>";
                }
            }
            
            ?>
            <?php 
                if (isset($_POST['submit'])) {
                    echo '<span>Game Length:</span>';

                    $minutes = floor($_SESSION['playerKDA'][0]['MATCH_TIME'] / 60);
                    $remainingSeconds = $_SESSION['playerKDA'][0]['MATCH_TIME'] % 60;
                    
                    echo '<span>' . $minutes . ":" . $remainingSeconds . "</span>"; 
                }
            ?>
            <table class="table table-hover table-striped player-table">
                <tr>
                    <th>Player Name</th>
                    <th>Kills</th>
                    <th>Deaths</th>
                    <th>Assists</th>
                    <th>K/D</th>
                    <th>(K+A)/D</th>
                    <th>CS</th>
                    <th>CS/M</th>
                    <th>Damage</th>
                    <th>DPM</th>
                    <th>Vision Score</th>
                    <th>K/P</th>
                    
                </tr>
                <?php
                    if (isset($_POST['submit'])) {
                        foreach ($_SESSION['playerKDA'] as $matchStats) {
                            
                            echo '<tr>';
                            echo '<td>' . $matchStats['PlayerName'] . "</td>";
                            echo '<td>' . $matchStats['Kills'] . "</td>";
                            echo '<td>' . $matchStats['Deaths'] . "</td>";
                            echo '<td>' . $matchStats['Assists'] . "</td>";
                            echo '<td>' . $matchStats['K/D'] . "</td>";
                            echo '<td>' . $matchStats['K/D/A'] . "</td>";
                            echo '<td>' . $matchStats['CS'] . "</td>";
                            echo '<td>' . $matchStats['CSM'] . "</td>";
                            echo '<td>' . $matchStats['DMG'] . "</td>";
                            echo '<td>' . $matchStats['DMM'] . "</td>";
                            echo '<td>' . $matchStats['VS'] . "</td>";
                            echo '<td>' . $matchStats['KP'] . "%</td>";
                        
                            echo '</tr>';
                    
                            
                            echo '</tr>';
                        }

                    }
                ?>
                
            </table>
            <?php 
            if (isset($_POST['submit'])) {
                echo '
                    <form method="post">
                        <input type="hidden" name="matchId" value="' . htmlspecialchars(strip_tags($_POST['matchId'])) . '">
                        <input type="hidden" name="playerName" value="' . htmlspecialchars(strip_tags($playerStats['PlayerName'])) . '">
                        <input type="hidden" name="kills" value="' . htmlspecialchars(strip_tags($playerStats['Kills'])) . '">
                        <input type="hidden" name="deaths" value="' . htmlspecialchars(strip_tags($playerStats['Deaths'])) . '">
                        <input type="hidden" name="assists" value="' . htmlspecialchars(strip_tags($playerStats['Assists'])) . '">
                        <input type="hidden" name="kd" value="' . htmlspecialchars(strip_tags($playerStats['K/D'])) . '">
                        <input type="hidden" name="kda" value="' . htmlspecialchars(strip_tags($playerStats['K/D/A'])) . '">
                        <input type="hidden" name="ff" value="' . htmlspecialchars(strip_tags($playerStats['FF'])) . '">
                        <input type="hidden" name="cs" value="' . htmlspecialchars(strip_tags($playerStats['CS'])) . '">
                        <input type="hidden" name="csm" value="' . htmlspecialchars(strip_tags($playerStats['CSM'])) . '">
                        <input type="hidden" name="dmg" value="' . htmlspecialchars(strip_tags($playerStats['DMG'])) . '">
                        <input type="hidden" name="dmm" value="' . htmlspecialchars(strip_tags($playerStats['DMM'])) . '">
                        <input type="hidden" name="vs" value="' . htmlspecialchars(strip_tags($playerStats['VS'])) . '">
                        <input type="hidden" name="kp" value="' . htmlspecialchars(strip_tags($playerStats['KP'])) . '">
                        <button type="submit" name="confirmStats" class="btn btn-success">Confirm Selected Player Stats</button>

                    </form>';

            
               
            
            }
           
            
            ?>
        </div>


        <div class="tabContent" id="teamStatsContent" style=<?php if ($guestoradmin == "Guest") {echo '"display: block;"';} else {echo '"display: none;"';} ?>>
            <!-- Content for the Setup View tab -->
            <h2 style="margin: auto">Team Stats</h2>
                <?php
                    $teamIds = getTeamIds($conn);

                    foreach ($teamIds as $teamId) {
                        $playersInTeamStats = getPlayersInTeamStats($conn, $teamId);
                        if (!empty($playersInTeamStats)) {
                            echo '<h3>Team: ' . $teamId . '</h3>';
                            displayPlayerStatsTable($playersInTeamStats);
                        }
                    }
                ?>

                
            </table>
            
        </div>


        <div class="tabContent col-md-10" id="generalStatsViewContent" style="display: none">
            EMPTY FOR NOW
        </div>

        <div class="tabContent" id="teamManagementContent" style="display: none;">
            <h1>Manage Team Stats</h1>
    
        </div>
    </div>



        
    <script>
        // Function to open a tab and display its content
        function openTab(tabName) {
            var i;
            var tabs = document.getElementsByClassName("tab");
            var tabContent = document.getElementsByClassName("tabContent");

            // Hide all tab content
            for (i = 0; i < tabContent.length; i++) {
                tabContent[i].style.display = "none";
            }

            // Remove the 'active' class from all tabs
            for (i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove("active");
            }

            // Display the selected tab content and mark the tab as active
            var tabContentElement = document.getElementById(tabName + "Content");
            tabContentElement.style.display = "block";
            event.currentTarget.classList.add("active");

            // Log the value of tabContentElement to the console
        }
    </script>
    </body>
</html>
