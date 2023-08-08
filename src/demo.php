<?php
declare(strict_types=1);

use M3usm\M3uPhpTools\M3uParserStateMachine;
use M3usm\M3uPhpTools\M3uTextStream;

require_once __DIR__ . '/../vendor/autoload.php';

//$filename = '../resources/veryLongAndDirty.m3u';
//$filename = '../resources/withBOM.m3u';
//$filename = '../resources/1.m3u';
//$filename = '../resources/2.m3u';
//$filename = '../resources/3.m3u';
//$filename = '../resources/realFreeTV.m3u';

$filename = '../resources/dirty.m3u';

$text = file_get_contents($filename);

$stream = new M3uTextStream($text);
$parser = new M3uParserStateMachine();

$items = $parser->parsePlaylist($stream);

print_r($items);

echo "items: " . sizeof($items) . "\n";

//foreach ($items as $item) {
//    $title = array_key_exists('title', $item) ? $item['title'] : ' ';
//    $url = array_key_exists('url', $item) ? $item['url'] : ' ';
//    $exttv = array_key_exists('exttv', $item) ? $item['exttv'] : ' ';
//    $duration = array_key_exists('duration', $item) ? $item['duration'] : ' ';
//
//    echo "TITLE: " . $title . "\n";
//    echo "EXTTV: " . $exttv . "\n";
//    echo "URL: " . $url . "\n";
//    echo "Duration: " . $duration . " seconds\n";
//    echo "---\n";
//}

