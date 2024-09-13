<?php

// Include the config.php to access variables and functions
include 'config.php';

// Get the channel ID from the URL parameter (e.g., ?id=1003)
$id = $_GET['id'] ?? exit("Error: No channel ID provided.");

// Fetch channel information based on the ID from your config function
$channelInfo = getChannelInfo($id);

// Extract stream URL from the channel data
$dashUrl = $channelInfo['streamData']['initialUrl'] ?? exit("Error: Stream URL not found.");

// Fetch the MPD manifest using the extracted stream URL
$manifestContent = fetchMPDManifest($dashUrl, $userAgent) ?? exit("Error: Could not fetch MPD manifest.");

// Extract PSSH and KID from the MPD manifest
$widevinePssh = extractPsshFromManifest($manifestContent, dirname($dashUrl), $userAgent, $beginTimestamp);

if (!$widevinePssh) {
    exit("Error: Could not extract PSSH or KID.");
}

// Extract PSSH and KID
$psshSet = $widevinePssh['pssh']; 
$kid = $widevinePssh['kid']; 

// Format the KID into UUID format
$kidFormatted = substr($kid, 0, 8) . '-' . substr($kid, 8, 4) . '-' . substr($kid, 12, 4) . '-' . substr($kid, 16, 4) . '-' . substr($kid, 20);

// Return the PSSH and KID as a JSON response
header('Content-Type: application/json');
echo json_encode([
    'pssh' => $psshSet,
    'kid' => $kidFormatted,
]);

?>
