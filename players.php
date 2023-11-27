<?php

require 'vendor/autoload.php';
include('./db_config.php');
include('./requester.php');

use PhpOffice\PhpSpreadsheet\IOFactory;

session_start();

date_default_timezone_set('US/Eastern');

if (!isset($_SESSION['id'])) {
    header("Location: /index.php");
}

if (isset($_SESSION['isadmin']) && $_SESSION['isadmin'] == TRUE) {
    $guestoradmin = 'Admin';
} else {
    $guestoradmin = 'Guest';
    
    http_response_code(403);
    die('<h2 style="color:red">Forbidden</h2>');
}

if (!isset($_SESSION['user_list'])) {
    $_SESSION['user_list'] = array();
}


function relax() {
    ;
}

function verifyRank($rankParam, $ignParam) {
    $actualRank = "Need Verify";

    if (strlen($rankParam) < 12) {
        if ($_POST['api_key'] == "Optional") {
            // add code to verify if rank is correct. leave as user input for now
            $actualRank = $rankParam;
        } else {
            $api_key = $_SESSION['riotApiKey'];
            $verifiedRank = getSummonerRank($ignParam, $api_key);
            if (strpos($verifiedRank, 'Error') !== True) {
                // error was found
                $actualRank = $rankParam;
            } else {
                // Error, was not found in string, therfore, success
                $actualRank = $rankParam;
                
            }
        }
    }
    return $actualRank;
}

function processRank($rankParam) {
    if (!is_null($rankParam)) {
        return useRegex($rankParam);
    } else {
        return "Need Verify";
    }
}

function useRegex($input) {
    $regex = '/([a-z][1-5]{1})|([A-Za-z0-9]+( [A-Za-z0-9]{1,2}){1})/i';

    if (preg_match($regex, $input, $matches) != 1) {
        $matches[0] = "Need Verify";
    }
    return $matches[0];
}

function isUserInDatabase($conn, $username) {
    $sql = "SELECT name FROM players WHERE name = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();
    return $stmt->num_rows > 0;
}

function addNewUser($conn, $formData) {
    $sql = "INSERT INTO players (time_registered, read_terms, agree_to_terms, discord_name, team_captain, name, `rank`, rank_previous, role_preferred, role_alternative) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        // Handle preparation error
        return "Error preparing SQL statement: " . $conn->error;
    }

    $username = htmlspecialchars(strip_tags($formData['user_name']));
    $api_key = htmlspecialchars(strip_tags($formData['api_key']));
    $rank = getSummonerRank($username, $api_key);

    if (strpos($rank, 'Error') === 0) {
        $rank = "Need Verify";
    } elseif (strpos($rank, 'fetching') === 0) {
        // Handle no games played
        return "No games played";
    }

    $rolePref = htmlspecialchars(strip_tags($formData['role']));

    $readTermsValue = "yes";
    $agreeToTermsValue = "yes";
    $teamCaptainValue = "maybe";
    $rankPrevValue = "N/A";
    $roleAltValue = "Fill";
    $discordName = "N/A";
    $timestamp = date('y-m-d H:i:s', time());

    $stmt->bind_param("ssssssssss", $timestamp, $readTermsValue, $agreeToTermsValue, $discordName, $teamCaptainValue, $username, $rank, $rankPrevValue, $rolePref, $roleAltValue);

    try {
        $stmt->execute();
        $_SESSION['user_list'][] = $username;
    } catch (Exception $e) {
        return "Character Length too long";
    }

    $stmt->close();
    
    return true;
}

function removeSelectedUsers($conn, $selectedUsers) {
    foreach ($selectedUsers as $user) {
        $userToRemove = htmlspecialchars(strip_tags($user));
        $index = array_search($userToRemove, $_SESSION['user_list']);

        if ($index !== false) {
            unset($_SESSION['user_list'][$index]);
            $_SESSION['user_list'] = array_values($_SESSION['user_list']);
        }

        $stmt = $conn->prepare("DELETE FROM players WHERE name = ?");
        $stmt->bind_param("s", $userToRemove);

        if ($stmt->execute()) {
            relax();
        }
        
        $stmt->close();
    }
    
    return true;
}

function processFileUpload($conn, $formData, $file) {
    $file = $_FILES['user_file']['tmp_name'];

    // Load the spreadsheet using PhpSpreadsheet
    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');    
    $reader->setReadDataOnly(true); // Set this property
    $spreadsheet = $reader->load($file);

    $isheader = 0;
    
    $chunkSize = 20; // Define your desired chunk size
    $rowCounter = 0;

    // Initialize the $worksheet outside the loop
    $worksheet = $spreadsheet->getActiveSheet();

    try {
        // Get the uploaded file

        // Prepare the SQL statement outside the loop
        $insertQuery = "INSERT INTO players (time_registered, read_terms, agree_to_terms, discord_name, team_captain, name, `rank`, rank_previous, role_preferred, role_alternative) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $insertStmt = $conn->prepare($insertQuery);
        $insertStmt->bind_param("ssssssssss", $timestamp, $readTerms, $agreeToTerms, $discordName, $teamCaptain, $ign, $rank, $rankPrev, $rolePref, $roleAlt);

        $insertedUsers = [];
        // Iterate through the rows
        foreach ($worksheet->getRowIterator() as $row) {
            if ($isheader > 0) {
                            
                
                // Get the values for each column
                $timestampColumn = $worksheet->getCellByColumnAndRow(1, $row->getRowIndex())->getValue();
                $readTerms = $worksheet->getCellByColumnAndRow(2, $row->getRowIndex())->getValue();
                $agreeToTerms = $worksheet->getCellByColumnAndRow(3, $row->getRowIndex())->getValue();
                $discordName = $worksheet->getCellByColumnAndRow(4, $row->getRowIndex())->getValue();
                $teamCaptain = $worksheet->getCellByColumnAndRow(5, $row->getRowIndex())->getValue();
                $ign = $worksheet->getCellByColumnAndRow(6, $row->getRowIndex())->getValue();
                $rank = $worksheet->getCellByColumnAndRow(7, $row->getRowIndex())->getValue();
                $rankPrev = $worksheet->getCellByColumnAndRow(8, $row->getRowIndex())->getValue();
                $rolePref = $worksheet->getCellByColumnAndRow(9, $row->getRowIndex())->getValue();
                $roleAlt = $worksheet->getCellByColumnAndRow(10, $row->getRowIndex())->getValue();
    //echo $rank;
                // Check for null values before using htmlspecialchars
                $readTerms = ($readTerms !== null) ? htmlspecialchars($readTerms) : null;
                $agreeToTerms = ($agreeToTerms !== null) ? htmlspecialchars($agreeToTerms) : null;
                $discordName = ($discordName !== null) ? htmlspecialchars($discordName) : null;
                $teamCaptain = ($teamCaptain !== null) ? htmlspecialchars($teamCaptain) : null;
                $ign = ($ign !== null) ? htmlspecialchars($ign) : null;
                $rank = ($rank !== null) ? htmlspecialchars($rank) : null;
                $rankPrev = ($rankPrev !== null) ? htmlspecialchars($rankPrev) : null;
                $rolePref = ($rolePref !== null) ? htmlspecialchars($rolePref) : null;
                $roleAlt = ($roleAlt !== null) ? htmlspecialchars($roleAlt) : null;
                $timestampColumn = ($timestampColumn !== null) ? htmlspecialchars($timestampColumn) : null;
                
                $rank = processRank($rank);
                $rankPrev = processRank($rankPrev);

                // Process the timestamp
                if ($timestampColumn !== null) {
                    $unixTimestamp = ($timestampColumn - 25569) * 86400;
                    $dateTime = DateTime::createFromFormat('U.u', $unixTimestamp);
                    if ($dateTime !== false) {
                        // Format the DateTime as a human-readable date and time
                        $timestamp = $dateTime->format('y-m-d H:i:s');
                    } else {
                        relax();
                    }
                } else {
                    $timestamp = null; // Or set to an appropriate default value
                }
                if (isUserInDatabase($conn, $ign)) {
                    // User already exists, skip this user
                    continue;
                } elseif (!empty($ign) && !empty($rank) && !empty($rolePref) && !in_array($ign, $insertedUsers)) {
                    if ($insertStmt->execute()) {
                        $insertedUsers[] = $ign;
                         // Clear PHPExcel objects and unset variables every chunkSize rows
                        $rowCounter++;
                        if ($rowCounter % $chunkSize === 0) {
                            // Close the insert statement
                            $insertStmt->close();

                            // Clear PHPExcel objects
                            $spreadsheet->disconnectWorksheets();
                            unset($spreadsheet);

                            // Reconnect the worksheet to release memory
                            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
                            $worksheet = $spreadsheet->getActiveSheet();

                            // Prepare a new insert statement
                            $insertStmt = $conn->prepare($insertQuery);
                            $insertStmt->bind_param("ssssssssss", $timestamp, $readTerms, $agreeToTerms, $discordName, $teamCaptain, $ign, $rank, $rankPrev, $rolePref, $roleAlt);
                        }
                    } else {
                        error_log("Error executing SQL statement: " . $insertStmt->error);
                    }
                }

               
            } else {
                $isheader = 1;
            }
        }

        // Close the insert statement one final time
        $insertStmt->close();

        // Clear PHPExcel objects and unset variables
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        unset($worksheet);
        unset($insertStmt);

        return true;
    } catch (Exception $e) {
        // Log the exception to a file or database
        echo "An error occurred while processing the file. Please try again later.";
    }
}



if (isset($_POST['add_user'])) {
    $result = addNewUser($conn, $_POST);
    if (is_string($result)) {
        echo "<span style='color:red'>$result</span>";
    }
}

if (isset($_POST['remove_selected'])) {
    if (isset($_POST['selected_users'])) {
        removeSelectedUsers($conn, $_POST['selected_users']);
    }
}

if (isset($_FILES['user_file']) && $_FILES['user_file']['error'] === UPLOAD_ERR_OK) {
    $result = processFileUpload($conn, $_POST, $_FILES['user_file']['tmp_name']);
    if (is_string($result)) {
        echo $result;
    }
}







?>

<!DOCTYPE html>
<html>
<head>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie-edge">
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="./style.css">
    <style>
        /* Add custom styles here  */
        body {
            background-image: url('img/dash_plants_dark.jpg'); 
            background-size: cover;
            background-repeat: no-repeat;
            background-attachment: fixed;
        }
        .container {
            margin-top: 20px;
        }
        .box {
            background-color: #f8f9fa;
            padding: 20px;
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
        /* changes to the title next color and size */
    </style>
    
</head>
<body style="overflow-x: hidden;"> 
    <nav class="navbar bg-dark navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/dashboard.php">Dashboard</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#collapsibleNavbar">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="collapsibleNavbar">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" style="text-align:right" href="tournaments.php">Tournaments</a>
                        <a class="nav-link" style="text-align:right" href="stats.php">Stats</a>
                        <a class="nav-link" style="text-align:right" href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container"> 
    <h5 style="color: white; font-size: 32px;">Add and Remove Users</h5> 
        <div class="box-container">
            <div class="box" id="users">
                <form method="post">
                    <div class="form-group">
                        <label for="api_key" class="form-label">Enter RIOT API key to verify user ranks (or verify rank manually):</label>
                        <input type="text" id="api_key" name="api_key" class="form-control" value="Optional">
                    </div>
                    <div class="form-group">
                        <label for="user_name" class="form-label">Enter User Name:</label>
                        <input type="text" id="user_name" name="user_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="role" class="form-label">Enter Preferred Role:</label>
                        <input type="text" id="role" name="role" class="form-control" required>
                    </div>
                    <button type="submit" name="add_user" class="btn btn-primary">Add User</button>
                </form>
            </div>

            <div class="box" id="usernames">
                <form method="post" enctype="multipart/form-data">
                    <label>User List:</label>
                    <select class="form-select" style="height:200px;" name="selected_users[]" multiple>
                        <?php
                            $result = $conn->query("SELECT name FROM players");

                            if ($result) {
                                $usernames = array();
                        
                                // Fetch usernames and add them to the session array
                                while ($row = $result->fetch_assoc()) {
                                    $usernames[] = $row['name'];
                                }
                        
                                $_SESSION['user_list'] = $usernames;
                            } else {
                                echo "Error querying the database: " . $conn->error;
                            }
                            // Close the database connection
                            $conn->close();
                            // Display the added users from the session array
                            foreach ($_SESSION['user_list'] as $user) {
                                echo "<option value='$user'>$user</option>";
                            }
                        ?>
                    </select>

                    <!-- TEST -->
                    <label for="user_file" class="form-label">Upload Spreadsheet:</label>
                    <input type="file" id="user_file" name="user_file" accept=".csv, .xls, .xlsx">
                    <button type="submit" name="upload_file" class="btn btn-success mt-3">Submit Spreadsheet</button>
                    <input type="hidden" id="api_key_copy" name="api_key" class="form-control" value="Optional">                    
                    <button type="submit" name="remove_selected" class="btn btn-danger mt-3">Remove Selected Users</button>
                    <!-- TEST -->
                </form>

            </div>
            <script>
                // JavaScript to copy the api_key value from the first form to the hidden input in the second form
                document.getElementById('api_key').addEventListener('input', function() {
                    document.getElementById('api_key_copy').value = this.value;
                });
            </script>
        </div>
    </div>
</body>
</html>






