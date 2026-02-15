<?php

declare(strict_types=1);

use kanakanjiconverter\PHPKanaKanjiConverter;

include __DIR__ . '/vendor/autoload.php';

$input = "kinouhasukiyakiwotabemasita";
$converter = new PHPKanaKanjiConverter();
$a = $converter->convert($input);
echo $a["best"]["text"], "\n";

foreach ($a["candidates"] as $candidate) {
	echo $candidate["text"], "\n";
}

var_dump($a);