<?php
error_reporting(0);

$channels_api = "https://tv.roarzone.net/api/android/channels.php";
$stream_api_base = "https://tv.roarzone.net/api/android/stream.php?channel=";

$ch = curl_init($channels_api);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
$channels = $data['data'] ?? $data;

$multi = curl_multi_init();
$handles = [];

foreach ($channels as $i => $c) {

    if (!isset($c['stream_name'])) continue;

    $url = $stream_api_base . $c['stream_name'];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    curl_multi_add_handle($multi, $ch);

    $handles[$i] = [
        'ch' => $ch,
        'data' => $c
    ];
}

$running = null;
do {
    curl_multi_exec($multi, $running);
    curl_multi_select($multi);
} while ($running > 0);

echo "#EXTM3U\n\n";

foreach ($handles as $h) {

    $res = curl_multi_getcontent($h['ch']);
    curl_multi_remove_handle($multi, $h['ch']);
    curl_close($h['ch']);

    $json = json_decode($res, true);
    if (!isset($json['url'])) continue;

    $c = $h['data'];

    echo "#EXTINF:-1 tvg-id=\"{$c['id']}\" tvg-logo=\"{$c['logo']}\" group-title=\"{$c['group']}\",{$c['name']}\n";
    echo $json['url'] . "\n\n";
}

curl_multi_close($multi);
