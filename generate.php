<?php
error_reporting(E_ALL);

// ================= API =================
$channels_api = "https://tv.roarzone.net/api/android/channels.php";
$stream_api_base = "https://tv.roarzone.net/api/android/stream.php?channel=";

// ================= HEADERS =================
$headers = [
    "User-Agent: okhttp/4.9.0",
    "Accept: application/json",
    "Connection: Keep-Alive",
    "Accept-Encoding: gzip"
];

// ================= CURL FUNCTION =================
function createCurl($url, $headers)
{
    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_ENCODING => ""
    ]);

    return $ch;
}

// ================= FETCH CHANNELS =================
$ch = createCurl($channels_api, $headers);

$response = curl_exec($ch);

echo "========== RAW RESPONSE ==========\n";
echo $response . "\n\n";

if (!$response) {

    $output = "#EXTM3U\n";
    $output .= "# CHANNEL API ERROR\n";
    $output .= "# " . curl_error($ch) . "\n";

    file_put_contents("playlist.m3u", $output);

    exit;
}

curl_close($ch);

// ================= JSON =================
$data = json_decode($response, true);

if (!$data) {

    file_put_contents(
        "playlist.m3u",
        "#EXTM3U\n# INVALID CHANNEL JSON\n"
    );

    exit;
}

$channels = $data['data'] ?? $data;

if (!is_array($channels)) {

    file_put_contents(
        "playlist.m3u",
        "#EXTM3U\n# INVALID CHANNEL DATA\n"
    );

    exit;
}

// ================= MULTI CURL =================
$multi = curl_multi_init();
$handles = [];

foreach ($channels as $i => $c) {

    if (empty($c['stream_name'])) {
        continue;
    }

    $url = $stream_api_base . $c['stream_name'];

    $mh = createCurl($url, $headers);

    curl_multi_add_handle($multi, $mh);

    $handles[] = [
        'handle' => $mh,
        'channel' => $c
    ];
}

// ================= EXECUTE =================
$running = null;

do {
    curl_multi_exec($multi, $running);
    curl_multi_select($multi);
} while ($running > 0);

// ================= BUILD PLAYLIST =================
$output = "#EXTM3U\n\n";

$total = 0;

foreach ($handles as $h) {

    $res = curl_multi_getcontent($h['handle']);

    curl_multi_remove_handle($multi, $h['handle']);

    if (!$res) {
        continue;
    }

    $json = json_decode($res, true);

    if (!isset($json['url'])) {
        continue;
    }

    $c = $h['channel'];

    $id = $c['id'] ?? '';
    $name = $c['name'] ?? 'Unknown';
    $logo = $c['logo'] ?? '';
    $group = $c['group'] ?? 'General';

    $stream = $json['url'];

    $output .= "#EXTINF:-1 tvg-id=\"$id\" tvg-logo=\"$logo\" group-title=\"$group\",$name\n";
    $output .= "$stream\n\n";

    $total++;
}

curl_multi_close($multi);

// ================= EMPTY CHECK =================
if ($total == 0) {
    $output .= "# NO STREAM FOUND\n";
}

// ================= SAVE =================
file_put_contents("playlist.m3u", $output);

// ================= DEBUG =================
echo "========== DEBUG ==========\n";
echo "TOTAL CHANNELS: " . count($channels) . "\n";
echo "SUCCESS STREAMS: " . $total . "\n";

if (file_exists("playlist.m3u")) {
    echo "playlist.m3u CREATED\n";
} else {
    echo "FAILED TO CREATE playlist.m3u\n";
}
?>
