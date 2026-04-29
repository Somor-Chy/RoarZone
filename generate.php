<?php
error_reporting(0);

// ================= CONFIG =================
$proxy = "64.227.131.240:1080";

// ================= API =================
$channels_api = "https://tv.roarzone.net/api/android/channels.php";
$stream_api_base = "https://tv.roarzone.net/api/android/stream.php?channel=";

// ================= COMMON HEADERS =================
$headers = [
    "User-Agent: okhttp/4.9.0",
    "Accept: application/json",
    "Connection: Keep-Alive",
    "Accept-Encoding: gzip"
];

// ================= FETCH CHANNELS =================
$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $channels_api);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_ENCODING, "");

// SOCKS5 PROXY
curl_setopt($ch, CURLOPT_PROXY, $proxy);
curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);

$response = curl_exec($ch);

curl_close($ch);

$data = json_decode($response, true);
$channels = $data['data'] ?? $data;

if (!is_array($channels)) {
    file_put_contents("playlist.m3u", "#EXTM3U\n");
    exit;
}

// ================= MULTI CURL =================
$multi = curl_multi_init();
$handles = [];

foreach ($channels as $i => $c) {

    if (empty($c['stream_name'])) continue;

    $url = $stream_api_base . $c['stream_name'];

    $mh = curl_init();

    curl_setopt($mh, CURLOPT_URL, $url);
    curl_setopt($mh, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($mh, CURLOPT_TIMEOUT, 20);
    curl_setopt($mh, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($mh, CURLOPT_ENCODING, "");

    // SOCKS5
    curl_setopt($mh, CURLOPT_PROXY, $proxy);
    curl_setopt($mh, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);

    curl_multi_add_handle($multi, $mh);

    $handles[$i] = [
        'ch' => $mh,
        'data' => $c
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

foreach ($handles as $h) {

    $res = curl_multi_getcontent($h['ch']);

    curl_multi_remove_handle($multi, $h['ch']);
    curl_close($h['ch']);

    if (!$res) continue;

    $json = json_decode($res, true);

    if (!isset($json['url'])) continue;

    $c = $h['data'];

    $id = $c['id'] ?? '';
    $name = $c['name'] ?? 'Unknown';
    $logo = $c['logo'] ?? '';
    $group = $c['group'] ?? 'General';

    $stream = $json['url'];

    $output .= "#EXTINF:-1 tvg-id=\"$id\" tvg-logo=\"$logo\" group-title=\"$group\",$name\n";
    $output .= "$stream\n\n";
}

curl_multi_close($multi);

// ================= SAVE =================
file_put_contents("playlist.m3u", $output);

echo "DONE\n";
?>
