<?php

include 'config.php';
error_reporting(0);
ini_set('display_errors', 0);

$id = $_GET['id'] ?? exit;
$channelInfo = getChannelInfo($id);
$dashUrl = $channelInfo['streamData']['initialUrl'] ?? exit;

if (strpos($dashUrl, 'https://bpprod') !== 0) {
    header("Location: $dashUrl");
    exit;
}

if ($beginTimestamp) {
    $dashUrl = str_replace('master', 'manifest', $dashUrl);
    $dashUrl .= "?begin=$begin&end=$end";
}

$manifestContent = fetchMPDManifest($dashUrl, $userAgent) ?? exit;
$baseUrl = dirname($dashUrl);
$widevinePssh = extractPsshFromManifest($manifestContent, $baseUrl, $userAgent, $beginTimestamp);

if (!$widevinePssh) {
    exit("Error: Could not extract PSSH or KID.");
}

$psshSet = $widevinePssh['pssh']; // get pssh
$kid = $widevinePssh['kid']; // get kid
$kid = substr($kid, 0, 8) . "-" . substr($kid, 8, 4) . "-" . substr($kid, 12, 4) . "-" . substr($kid, 16, 4) . "-" . substr($kid, 20);
$pattern = '/<ContentProtection\s+schemeIdUri="(urn:[^"]+)"\s+value="Widevine"\/>/'; // pssh pattern

if (in_array($id, ['244', '599'])) {
    $manifestContent = str_replace( 'minBandwidth="226400" maxBandwidth="3187600" maxWidth="1920" maxHeight="1080"', 'minBandwidth="226400" maxBandwidth="2452400" maxWidth="1280" maxHeight="720"', $manifestContent);
    $manifestContent = preg_replace('/<Representation id="video=3187600" bandwidth="3187600".*?<\/Representation>/s', '', $processedManifest);
}

$manifestContent = str_replace('<BaseURL>dash/</BaseURL>', '<BaseURL>' . "$baseUrl/dash/" . '</BaseURL>', $manifestContent); // add baseUrl
$manifestContent = preg_replace('/\b(init.*?\.dash|media.*?\.m4s)(\?idt=[^"&]*)?("|\b)(\?decryption_key=[^"&]*)?("|\b)(&idt=[^&"]*(&|$))?/', "$1$3$5$6$7", $manifestContent); // remove decryption_key  etc if there
$manifestContent = preg_replace_callback($pattern, function ($matches) use ($psshSet) {
    return '<ContentProtection schemeIdUri="' . $matches[1] . '"> <cenc:pssh>' . $psshSet . '</cenc:pssh></ContentProtection>';
}, $manifestContent); // add pssh to mpd
$manifestContent = preg_replace('/xmlns="urn:mpeg:dash:schema:mpd:2011"/', '$0 xmlns:cenc="urn:mpeg:cenc:2013"', $manifestContent);
$new_content = "<ContentProtection schemeIdUri=\"urn:mpeg:dash:mp4protection:2011\" value=\"cenc\" cenc:default_KID=\"$kid\"/>";  // kid maker
$manifestContent = str_replace('<ContentProtection value="cenc" schemeIdUri="urn:mpeg:dash:mp4protection:2011"/>', $new_content, $manifestContent); // ass kid to mpd


header('Content-Security-Policy: default-src \'self\';');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/dash+xml');
header("Cache-Control: max-age=20, public");
header('Content-Disposition: attachment; filename="'.$genreName.'|'.$id.'.mpd"');
echo $manifestContent;