<?php

declare(strict_types=1);

use kanakanjiconverter\PHPKanaKanjiConverter;
use kanakanjiconverter\UserDictionary;

include __DIR__ . '/vendor/autoload.php';

$basemem = memory_get_usage();
$basemem1 = memory_get_peak_usage();
$converter = new PHPKanaKanjiConverter();

// --- ユーザー辞書の構築 ---
$dict = new UserDictionary();
$dict->addAll([
	// 漢字変換フェーズでマージ（コスト優先で「誰」が選ばれやすくなる）
	['reading' => 'だあれ', 'surface' => 'だあれ', 'mode' => UserDictionary::MODE_REPLACE, 'word_cost' => -6000],

	// サーバー側辞書エントリ
	['reading' => 'せrゔぇr', 'surface' => 'サーバー', 'mode' => UserDictionary::MODE_REPLACE, 'word_cost' => -5000],
]);

$converter->registerUserDict('main', $dict);

$time = microtime(true);

foreach(["server"] as $input){
	$result = $converter->convert($input);

	var_dump($result["best"]["text"]);

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
$time = microtime(true) - $time;

echo "Time: {$time} sec\n";

echo ((memory_get_peak_usage() - $basemem) / 1024 / 1024) . " MB\n";
echo ((memory_get_usage() - $basemem1) / 1024 / 1024) . " MB\n";
