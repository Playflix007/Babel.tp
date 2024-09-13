<?php

$genreName = "Babel-IN"; // for m3u category
$userAgent = 'Mozilla/5.0';

$serverAddress = $_SERVER['HTTP_HOST'] ?? 'default.server.address';
$serverPort = $_SERVER['SERVER_PORT'] ?? '80';
$serverScheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$dirPath = dirname($requestUri);
$portPart = ($serverPort != '80' && $serverPort != '443') ? ":$serverPort" : '';

$beginTimestamp = isset($_GET['utc']) ? intval($_GET['utc']) : null;
$endTimestamp = isset($_GET['lutc']) ? intval($_GET['lutc']) : null;
$begin = $beginTimestamp ? date('Ymd\THis', $beginTimestamp) : 'unknown';
$end = $endTimestamp ? date('Ymd\THis', $endTimestamp) : 'unknown';

function fetchMPDManifest(string $url, string $userAgent): ?string {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => 'User-Agent: ' . $userAgent,
        ]
    ]);
    $content = @file_get_contents($url, false, $context);
    return $content !== false ? $content : null;
}

function extractKid($hexContent) {
    $psshMarker = "70737368";
    $psshOffset = strpos($hexContent, $psshMarker);
    
    if ($psshOffset !== false) {
        $headerSizeHex = substr($hexContent, $psshOffset - 8, 8);
        $headerSize = hexdec($headerSizeHex);
        $psshHex = substr($hexContent, $psshOffset - 8, $headerSize * 2);
        $kidHex = substr($psshHex, 68, 32);
        $newPsshHex = "000000327073736800000000edef8ba979d64acea3c827dcd51d21ed000000121210" . $kidHex;
        $pssh = base64_encode(hex2bin($newPsshHex));
        
        return ['pssh' => $pssh, 'kid' => $kidHex];
    }
    
    return null;
}

function extractPsshFromManifest(string $content, string $baseUrl, string $userAgent, ?int $beginTimestamp): ?array {
    if (($xml = @simplexml_load_string($content)) === false) return null;
    foreach ($xml->Period->AdaptationSet as $set) {
        if ((string)$set['contentType'] === 'audio') {
            foreach ($set->Representation as $rep) {
                $template = $rep->SegmentTemplate ?? null;
                if ($template) {
                    $startNumber = $beginTimestamp ? (int)($template['startNumber'] ?? 0) : (int)($template['startNumber'] ?? 0) + (int)($template->SegmentTimeline->S['r'] ?? 0);
                    $media = str_replace(['$RepresentationID$', '$Number$'], [(string)$rep['id'], $startNumber], $template['media']);
                    $url = "$baseUrl/dash/$media";
                    $context = stream_context_create([
                        'http' => ['method' => 'GET', 'header' => 'User-Agent: ' . $userAgent],
                    ]);
                    if (($content = @file_get_contents($url, false, $context)) !== false) {
                        $hexContent = bin2hex($content);
                        return extractKid($hexContent);
                    }
                }
            }
        }
    }
    return null;
}

function getChannelInfo(string $id): array {
    $json = @file_get_contents('https://raw.githubusercontent.com/Babel-In/TP-IN/main/jup.json');
    $channels = $json !== false ? json_decode($json, true) : null;
    if ($channels === null) {
        exit;
    }
    foreach ($channels as $channel) {
        if ($channel['id'] == $id) return $channel;
    }
    exit;
}

function getAllChannelInfo(): array {
    $json = @file_get_contents('https://raw.githubusercontent.com/Babel-In/TP-IN/main/jup.json');
    if ($json === false) {
        header("HTTP/1.1 500 Internal Server Error");
        exit;
    }
    $channels = json_decode($json, true);
    if ($channels === null) {
        header("HTTP/1.1 500 Internal Server Error");
        exit;
    }
    return $channels;
}

function getServerPublicIP() {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://ifconfig.me");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $publicIP = curl_exec($ch);
    curl_close($ch);

    return $publicIP;
}