<?php

declare(strict_types=1);

use kanakanjiconverter\PHPKanaKanjiConverter;

include __DIR__ . '/vendor/autoload.php';

$input = "atarasiipurojekutowokaihatsuchuudesu";
$converter = new PHPKanaKanjiConverter();
$a = $converter->convert($input);
echo $a["best"]["text"], "\n";

foreach ($a["candidates"] as $candidate) {
	echo $candidate["text"], "\n";
}

echo (memory_get_peak_usage() / 1024 / 1024) . " MB\n";
echo (memory_get_usage() / 1024 / 1024) . " MB\n";