<?php
session_start();
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

// Check if the form is submitted to handle selected teams
if (isset($_POST['remove_teams'])) {
    // Get the selected teams from the form
    $selectedTeams = isset($_POST['selected_teams']) ? $_POST['selected_teams'] : [];



    if (!empty($selectedTeams)) {
        // Prepare a placeholder string for the IN clause based on the number of selected teams
        $placeholders = implode(',', array_fill(0, count($selectedTeams), '?'));
        
        // Prepare the SQL statement to delete selected teams from the database
        $sql = "DELETE FROM teams WHERE team_id IN ($placeholders)";

        // Create a prepared statement
        $stmt = mysqli_prepare($conn, $sql);

        // Bind parameters
        $paramType = str_repeat('s', count($selectedTeams)); // Assuming team_id is an integer, adjust if needed
        $params = array(&$stmt, $paramType);
        foreach ($selectedTeams as &$teamId) {
            $teamId = htmlspecialchars(strip_tags($teamId));
            $params[] = &$teamId;
        }

        
        call_user_func_array('mysqli_stmt_bind_param', $params);

        // Execute the statement
        $success = mysqli_stmt_execute($stmt);

        // Check if the deletion was successful
        if (!$success) {
            echo "Error removing selected teams: " . mysqli_error($conn);
        }

        // Close the prepared statement
        mysqli_stmt_close($stmt);
        echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                openTab("teamSetup");
            });
          </script>';
    } else {
        echo "No teams selected for removal.";
    }
}





/*
NOTES: 

use riot CLIENT api to grab names on player join in lobby. possibly use 
async function if that exists in php? or implement JS listener for player join events and add the player to 
player list. IF not possible, add the players to play in other area on dashboard

*/

function getTeamIdByName($conn, $teamName) {
    $tableName = "teams"; 
    $teamName = mysqli_real_escape_string($conn, $teamName);

    $sql = "SELECT id FROM $tableName WHERE name = '$teamName'";
    $result = mysqli_query($conn, $sql);

    if ($result) {
        $row = mysqli_fetch_assoc($result);

        if ($row) {
            return $row['id'];
        }
    }

    return null;
}


function getPlayerIdByName($conn, $playerName) {
    $tableName = "players";
    $playerName = mysqli_real_escape_string($conn, $playerName);

    $sql = "SELECT id FROM $tableName WHERE name = '$playerName'";
    $result = mysqli_query($conn, $sql);

    if ($result) {
        $row = mysqli_fetch_assoc($result);

        if ($row) {
            return $row['id'];
        }
    }

    return null; // Return null if player not found
}


function countPlayers($dbConnection) {
    // Define the table name
    $tableName = "players"; // Replace with your actual table name

    // SQL query to count the number of players in the table
    $sql = "SELECT COUNT(*) as playerCount FROM $tableName";

    // Execute the query
    $result = mysqli_query($dbConnection, $sql);

    if ($result) {
        $row = mysqli_fetch_assoc($result);

        // Check if there is a result
        if ($row) {
            $playerCount = $row['playerCount'];
            return $playerCount;
        }
    }

    return 0;
}



// Function to populate the div with data for a specific role
function populateRoleDiv($conn, $role) {
    // Retrieve data from the database for the specified role
    $sql = "SELECT name, `rank`, role_preferred FROM players WHERE role_preferred = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $role);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {
        echo "<div class='col-md-2 pr-2 role-div'>";
        echo "<div class='table-container'>";
        echo "<table class='table table-hover table-striped' id='table-$role'>";
        echo "<thead>
                <th>Name</th>
                <th>Rank</th>
                <th>Role</th>
            </thead>";
        echo "<tbody>";


        while ($row = mysqli_fetch_assoc($result)) {
            echo "<tr>";
            $user = $row["name"];
            $url = "'https://www.op.gg/summoners/na/".urlencode($user)."'";
            echo "
                <td value='$user' class='draggable' onclick=location.href=$url>$user</td>
            ";
            echo "<td>" . $row["rank"] . "</td>";
            echo "<td>" . $row["role_preferred"] . "</td>";
            echo "</tr>";
        }

        echo "</tbody>";
        echo "</table>";
        echo "</div>";
        echo "</div>";
    } else {
        echo "<div class='col-md-2 pr-2 role-div'>";
        echo "<div class='table-container'><table><thead><th>No data found for $role</th></thead></table></div>";
        echo "</div>";
    }
}

function addTeamToDatabase($conn, $teamName) {
    // Check if the team name is not empty
    if (!empty($teamName)) {
        // Prepare the SQL statement to insert the team into the teams table
        $sql = "INSERT INTO teams (team_id) VALUES (?)";

        // Prepare the statement
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            die("Error preparing statement: " . $conn->error);
        }

        // Bind parameters and execute the statement
        $stmt->bind_param("s", $teamName);
        $stmt->execute();

        // Close the statement
        $stmt->close();
        echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                openTab("teamSetup");
            });
          </script>';

        // Optionally, you can redirect the user to another page after adding the team
    } else {
        // Handle the case where the team name is empty
        echo "Team name cannot be empty!";
    }
}

function calculateGameInfo($conn) {
    // Define the table name where player data is stored
    $tableName = "players"; // Replace with your actual table name

    // SQL query to retrieve player names, timestamps, and roles, sorted by timestamp in ascending order
    $sql = "SELECT name, time_registered, role_preferred FROM $tableName ORDER BY time_registered ASC";

    // Execute the query
    $result = mysqli_query($conn, $sql);

    if ($result) {
        $players = array();
        $teams = array();
        $remainingPlayers = array();
        $lastTimestamp = null;

        // Initialize role counts
        $roleCounts = [
            'adc' => 0,
            'top' => 0,
            'mid' => 0,
            'jungle' => 0,
            'support' => 0,
        ];

        // Fetch and organize the data
        while ($row = mysqli_fetch_assoc($result)) {
            $players[] = $row; // Store player data
            $lastTimestamp = $row['time_registered']; // Update the last timestamp

            // Increment role counts
            $role = strtolower($row['role_preferred']);
            if (array_key_exists($role, $roleCounts)) {
                $roleCounts[$role]++;
            }

            // Form teams of 5 with the earliest timestamps
            if (count($players) >= 5) {
                $team = array_slice($players, 0, 5); // Get the first 5 players
                $teams[] = $team; // Store the team
                $players = array_slice($players, 5); // Remove the team members from the list
            }
        }

        // Remaining players with the latest timestamps
        $remainingPlayers = $players;

        // Check if there are any remaining players
        $hasRemainingPlayers = !empty($remainingPlayers);

        // Count of extra players
        $extraPlayersCount = count($remainingPlayers);

        // Count of possible teams
        $possibleTeamsCount = count($teams);

        // Return teams, remaining players, last timestamp, role counts, boolean value, extra players count, and possible teams count
        return array(
            'teams' => $teams,
            'remainingPlayers' => $remainingPlayers,
            'lastTimestamp' => $lastTimestamp,
            'roleCounts' => $roleCounts,
            'hasRemainingPlayers' => $hasRemainingPlayers,
            'extraPlayersCount' => $extraPlayersCount,
            'possibleTeamsCount' => $possibleTeamsCount,
        );
    }

    // If an error occurs or no player data is found, return an empty result
    return array(
        'teams' => array(),
        'remainingPlayers' => array(),
        'lastTimestamp' => null,
        'roleCounts' => [],
        'hasRemainingPlayers' => false,
        'extraPlayersCount' => 0,
        'possibleTeamsCount' => 0,
    );
}

if (isset($_POST['add_team'])) {
    // Get the team name from the form
    $teamName =  htmlspecialchars(strip_tags($_POST['team_name']));

    // Call the function to add the team to the database
    addTeamToDatabase($conn, $teamName);
}


?>

<script>
    
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


<!DOCTYPE html>
<html lang="en">
    <head>
        <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
        <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="X-UA-Compatible" content="ie=edge">
        <title>Dashboard</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
        <link rel="stylesheet" href="styles/tournament.css">
        </head>
    <body style="overflow-x: hidden;">
        <nav class="navbar bg-dark navbar-dark">
            <div class="container-fluid" >
                <a class="navbar-brand" href="/dashboard.php">Dashboard</a>

                <div class="tab active" onclick="openTab('signupView')">Signup</div>
                <div class="tab" onclick="openTab('draftOrganization')">Draft</div>
                <div class="tab" onclick="openTab('teamSetup')">Available Teams</div>
                


                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#collapsibleNavbar">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="collapsibleNavbar">
                    <ul class="navbar-nav">
                        <li class="nav-item">
                            <a class="nav-link" style="text-align:right" href="stats.php">Stats</a>
                            <a class="nav-link" style="text-align:right" href="players.php">Players</a>
                            <a class="nav-link" style="text-align:right" href="logout.php">Logout</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>



        <style>
        .box {
            background-color: #f8f9fa;
            padding: 20px;
            margin: auto;
            border: 1px solid #d6d8db;
            border-radius: 5px;
        }
        .container h5 {
            text-align: center;
        }
        .form-label {
            font-weight: bold;
        }
        .form-group {
            margin-bottom: 10px;
        }
        .file-upload {
            margin-top: 20px;
        }
        .box-container {
            display: flex;
            justify-content: space-between;
        }
        </style>

        <div id="tabs">
            <div id="teamSetupContent" class="tabContent" style="display: none;">

                <div class="box col-md-3" id="users">
                    <form method="post">
                        <div class="form-group">
                            <label class="text-warning">WARNING: type team names EXACTLY as they should be.</label><br>
                        </div>
                        <div class="form-group">
                            <label for="team_name" class="form-label">Enter Team Name:</label>
                            <input type="text" id="team_name" name="team_name" class="form-control" required>
                        </div>
                        <button type="submit" name="add_team" class="btn btn-primary">Add Team</button>
                    </form>
                    
                    <form method="post">
                    <label for="selected_teams" class="form-label">Select Teams:</label>
                    <select style="height: 300px" class="form-select" id="selected_teams" name="selected_teams[]" multiple>
                        <?php
                        // Fetch teams from the database and display them in the form
                        $result = $conn->query("SELECT team_id FROM teams");

                        if ($result) {
                            while ($row = $result->fetch_assoc()) {
                                $teamId = $row['team_id'];
                                echo "<option value='$teamId'>$teamId</option>";
                            }
                        } else {
                            echo "Error querying the database: " . $conn->error;
                        }
                        ?>
                    </select>

                    <button type="submit" name="remove_teams" class="btn btn-danger mt-3">Remove Selected Teams</button>
                </form>

                </div>

            </div>
        
    
            <div id="draftOrganizationContent" class="tabContent" style="display: none;">
                <h2 style="margin: auto;text-align:center">Assign Teams</h4>
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-md-2" >
                            <div class="team-container" id="Tigers">
                            <h4>Tigers</h4>
                                <table class="team-table table table-striped" data-roles="top,jungle,mid,adc,support" id="team-Tigers">
                                    <tr>
                                        <th>Player</th>
                                        <th>Role</th>
                                    </tr>
                                    <tr>
                                        
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <div class="col-md-2">
                            <div class="team-container" id="Sharks">
                                <h4>Sharks</h4>
                                <table class="team-table table table-striped" data-roles="top,jungle,mid,adc,support" id="team-Sharks">
                                    <tr>
                                        <th>Player</th>
                                        <th>Role</th>
                                    </tr>
                                    <tr>
                                        
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <div class="col-md-2">
                            <div class="team-container" id="Boas">
                                <h4>Boas</h4>
                                <table class="team-table table table-striped" data-roles="top,jungle,mid,adc,support" id="team-Boas">
                                    <tr>
                                        <th>Player</th>
                                        <th>Role</th>
                                    </tr>
                                    <tr>
                                        
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <div class="col-md-2">
                            <div class="team-container" id="Cougars">
                                <h4>Cougars</h4>
                                <table class="team-table table table-striped" data-roles="top,jungle,mid,adc,support" id="team-Cougars">
                                    <tr>
                                        <th>Player</th>
                                        <th>Role</th>
                                    </tr>
                                    <tr>
                                        
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <div class="col-md-2">
                            <div class="team-container" id="BlackWidows">
                                <h4>Black Widows</h4>
                                <table class="team-table table table-striped" data-roles="top,jungle,mid,adc,support" id="team-BlackWidows">
                                    <tr>
                                        <th>Player</th>
                                        <th>Role</th>
                                    </tr>
                                    <tr>
                                        
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <div class="col-md-2">
                            <div class="team-container" id="Crocs">
                                <h4>Crocs</h4>
                                <table class="team-table table table-striped" data-roles="top,jungle,mid,adc,support" id="team-Crocs">
                                    <tr>
                                        <th>Player</th>
                                        <th>Role</th>
                                    </tr>
                                    <tr>
                                        
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-2">
                            <div class="team-container" id="Whales">
                                <h4>Whales</h4>
                                <table class="team-table table table-striped" data-roles="top,jungle,mid,adc,support" id="team-Whales">
                                    <tr>
                                        <th>Player</th>
                                        <th>Role</th>
                                    </tr>
                                    <tr>
                                        
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <div class="col-md-2">
                            <div class="team-container" id="Warthogs">
                                <h4>Warthogs</h4>
                                <table class="team-table table table-striped" data-roles="top,jungle,mid,adc,support" id="team-Warthogs">
                                    <tr>
                                        <th>Player</th>
                                        <th>Role</th>
                                    </tr>
                                    <tr>
                                        
                                    </tr>
                                </table>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="team-container" id="Hyenas">
                                <h4>Hyenas</h4>
                                <table class="team-table table table-striped" data-roles="top,jungle,mid,adc,support" id="team-Hyenas">
                                    <tr>
                                        <th>Player</th>
                                        <th>Role</th>
                                    </tr>
                                    <tr>
                                        
                                    </tr>
                                </table>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="team-container" id="Dragons">
                                <h4>Dragons</h4>
                                <table class="team-table table table-striped" data-roles="top,jungle,mid,adc,support" id="team-Dragons">
                                    <tr>
                                        <th>Player</th>
                                        <th>Role</th>
                                    </tr>
                                    <tr>
                                        
                                    </tr>
                                </table>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="team-container" id="Bulls">
                                <h4>Bulls</h4>
                                <table class="team-table table table-striped" data-roles="top,jungle,mid,adc,support" id="team-Bulls">
                                    <tr>
                                        <th>Player</th>
                                        <th>Role</th>
                                    </tr>
                                    <tr>
                                        
                                    </tr>
                                </table>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="team-container" id="Elks">
                                <h4>Elks</h4>
                                <table class="team-table table table-striped" data-roles="top,jungle,mid,adc,support" id="team-Elks">
                                    <tr>
                                        <th>Player</th>
                                        <th>Role</th>
                                    </tr>
                                    <tr>
                                        
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                
                </div>
                <div class="container-fluid justify-content-between align-items-end" id="signupViewContentTables" style="display: flex">
                    <?php
                        $roles = array("top", "jungle", "mid", "adc", "support");
                        foreach ($roles as $role) {
                            populateRoleDiv($conn, $role); // Call the function to populate the div for each role
                        }
                    ?>
                </div> 
                </div> 
            </div>
            


            <div class="tabContent" id="signupViewContent" style="display: block">
                <div class="col-md-7" style="margin: auto; margin-top: 40px;">
                    <table class="table table-striped ">
                        <tr>
                            <th>Player Count</th>
                            <th>Possible Teams</th>
                            <th>Extra Players</th>
                            <th>Top Players</th>
                            <th>Jungle Players</th>
                            <th>Mid Players</th>
                            <th>Adc Players</th>
                            <th>Support Players</th>
                        </tr>
                        <tr>
                            <td><?php echo countPlayers($conn); ?></td>
                            <td><?php $data = calculateGameInfo($conn); echo $data['possibleTeamsCount'];?></td>
                            <td>
                                <?php 
                                $data = calculateGameInfo($conn);
                                if ($data['hasRemainingPlayers']) {
                                    if ($data['extraPlayersCount'] > 1) {
                                        echo $data['extraPlayersCount'] . " extra players";
                                    } else {
                                        echo $data['extraPlayersCount'] . " extra player";
                                    }
                                } else {
                                    echo 'No extra players';
                                }
                            
                                ?>
                            </td>
                            <td><?php $data = calculateGameInfo($conn); echo $data['roleCounts']['top'];?></td>
                            <td><?php $data = calculateGameInfo($conn); echo $data['roleCounts']['jungle'];?></td>
                            <td><?php $data = calculateGameInfo($conn); echo $data['roleCounts']['mid'];?></td>
                            <td><?php $data = calculateGameInfo($conn); echo $data['roleCounts']['adc'];?></td>
                            <td><?php $data = calculateGameInfo($conn); echo $data['roleCounts']['support'];?></td>
                        </tr>
                    </table>
                </div>
                
                <div class="container-fluid justify-content-between align-items-end" id="signupViewContentTables" style="display: flex">
                    <?php
                        $roles = array("top", "jungle", "mid", "adc", "support");
                        foreach ($roles as $role) {
                            populateRoleDiv($conn, $role); // Call the function to populate the div for each role
                        }
                    ?>
                </div>                
            </div>
        </div>


    </body>
    <script>
        // Function to open a tab and display its content
        

 // start drag/drop feature

        // Updated JavaScript
        document.addEventListener('DOMContentLoaded', function () {
            const players = document.querySelectorAll('.role-div td');
            const teamContainers = document.querySelectorAll('.team-container');

            players.forEach(player => {
                player.setAttribute('draggable', 'true');
                player.addEventListener('dragstart', handleDragStart);
            });

            teamContainers.forEach(teamContainer => {
                teamContainer.addEventListener('dragover', handleDragOver);
                teamContainer.addEventListener('drop', handleDrop);
            });
        });

        function handleDragStart(event) {
            const playerName = event.target.textContent;
            const playerRole = event.target.parentElement.lastElementChild.textContent;
            const draggedData = JSON.stringify({ playerName, playerRole });

            event.dataTransfer.setData('text/plain', draggedData);
        }

        function handleDragOver(event) {
            event.preventDefault();
        }


        function handleDrop(event) {
            event.preventDefault();
            const teamTable = event.currentTarget.querySelector('.team-table');
            const teamName = teamTable.id.split('-')[1];
            const rolesNeeded = teamTable.dataset.roles.split(',');

            const draggedData = event.dataTransfer.getData('text/plain');
            const { playerName, playerRole } = JSON.parse(draggedData);

            // Check if the role is needed (case-insensitive)
            if (!rolesNeeded.some(role => role.toLowerCase().trim() === playerRole.toLowerCase().trim())) {
                alert('Invalid role for this team. Role: ' + playerRole + ', Needed Roles: ' + rolesNeeded.join(', '));
                return;  // Add this line to prevent further execution if the role is invalid
            }

            // Check if the role is already in the team
            if (isRoleInTeam(teamTable, playerRole)) {
                alert('Role is already in the team.');
                removePlayerFromTeam(teamTable, playerName);
                return;
            }

            // Insert the player and role into the team table
            const newRow = teamTable.insertRow();
            const cellName = newRow.insertCell();
            const cellRole = newRow.insertCell();

            cellName.textContent = playerName;
            cellRole.textContent = playerRole;

            console.log('Player added to team:', playerName, 'Role:', playerRole);

            // Update the player's team_id in the players table
            addPlayerTeam(playerName, teamName);

            // Update remaining roles
            updateRemainingRoles(teamTable, rolesNeeded);
        }

        function removePlayerFromTeam(teamTable, playerName) {
            const playerRows = Array.from(teamTable.querySelectorAll('tr'));
            
            for (const row of playerRows) {
                const nameCell = row.querySelector('td:first-child');
                if (nameCell && nameCell.textContent.trim() === playerName) {
                    row.remove();
                    
                    // Set team_id to null in the players table
                    removePlayerTeam(playerName);
                    
                    console.log('Player removed from team:', playerName);

                    break;
                }
            }
        }

        function addPlayerTeam(playerName, teamName) {
            // Make an AJAX request to update the player's team_id
            const xhr = new XMLHttpRequest();
            const url = '/addPlayerTeam.php'; // Adjust the path if needed

            xhr.open('POST', url, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    console.log(response);

                    if (response.success) {
                        console.log('Player team updated successfully!');
                    } else {
                        console.error('Error updating player team:', response.error);
                    }
                }
            };

            const data = 'playerName=' + encodeURIComponent(playerName) + '&teamName=' + encodeURIComponent(teamName);
            xhr.send(data);
            
        }

        function removePlayerTeam(playerName) {
            // Make an AJAX request to update the player's team_id
            const xhr = new XMLHttpRequest();
            const url = '/removePlayerTeam.php'; // Adjust the path if needed

            xhr.open('POST', url, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    console.log(response);

                    if (response.success) {
                        console.log('Player team updated successfully!');
                    } else {
                        console.error('Error updating player team:', response.error);
                    }
                }
            };

            const data = 'playerName=' + encodeURIComponent(playerName);
            xhr.send(data);
        
        }


        function isRoleInTeam(teamTable, playerRole) {
            const roleCells = Array.from(teamTable.querySelectorAll('td:last-child'));
            const matchingRoles = roleCells
                .map(cell => cell.textContent.trim().toLowerCase())
                .filter(role => role === playerRole.toLowerCase().trim());

            return matchingRoles.length > 0;
        }



        function isPlayerInAnyTeam(playerName) {
            const teamTables = document.querySelectorAll('.team-table');
            for (const teamTable of teamTables) {
                if (isPlayerInTeam(teamTable, playerName)) {
                    return true;
                }
            }
            return false;
        }
        
        function isPlayerInTeam(teamTable, playerName) {
            const playerNames = Array.from(teamTable.querySelectorAll('td:first-child')).map(cell => cell.textContent.trim());
            return playerNames.includes(playerName);
        }

        function updateRemainingRoles(teamTable, rolesAllowed) {
            const playerRoles = Array.from(teamTable.querySelectorAll('td:last-child')).map(cell => cell.textContent.trim().toLowerCase());
            const remainingRoles = rolesAllowed.filter(role => !playerRoles.includes(role));

            // Display remaining roles
            alert('Remaining Roles: ' + remainingRoles.join(', '));

        
        }

    </script>

</html>