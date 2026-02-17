<?php

declare(strict_types=1);

use kanakanjiconverter\PHPKanaKanjiConverter;

include __DIR__ . '/vendor/autoload.php';

$time = microtime(true);
$basemem = memory_get_usage();
$basemem1 = memory_get_peak_usage();
$converter = new PHPKanaKanjiConverter();
$input = "kilyouhaharedesu";
$result = $converter->convert($input);
//var_dump($result["kana"]);

if(!$converter->isValid($result)){
	echo "kana: " . $result["kana"], "\n";
	return;
}

echo "no-maru:" . $result["best"]["text"], "\n";

foreach($result["candidates"] as $item){
	var_dump($item["text"]);
}

$time = microtime(true) - $time;

echo "Time: {$time} sec\n";

echo ((memory_get_peak_usage() - $basemem) / 1024 / 1024) . " MB\n";
echo ((memory_get_usage() - $basemem1) / 1024 / 1024) . " MB\n";