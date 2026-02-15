<?php

declare(strict_types=1);

use kanakanjiconverter\PHPKanaKanjiConverter;

include __DIR__ . '/vendor/autoload.php';

$basemem = memory_get_usage();
$basemem1 = memory_get_peak_usage();

foreach(["server", "konn", "sinnkannsenn", "converterですか"] as $input){
	$converter = new PHPKanaKanjiConverter();
	$result = $converter->convert($input);

	if(preg_match('/[A-Za-z]/u', $result["kana"])){
		echo $result["original"], "\n";
		continue;
	}

	if(!$converter->isValid($result)){
		echo $result["kana"], "\n";
		continue;
	}

	echo $result["best"]["text"], "\n";
}
echo ((memory_get_peak_usage() - $basemem) / 1024 / 1024) . " MB\n";
echo ((memory_get_usage() - $basemem1) / 1024 / 1024) . " MB\n";
