<?php

declare(strict_types=1);

use kanakanjiconverter\PHPKanaKanjiConverter;
use kanakanjiconverter\UserDictionary;

include __DIR__ . '/vendor/autoload.php';

$basemem = memory_get_usage();
$basemem1 = memory_get_peak_usage();
$converter = new PHPKanaKanjiConverter();

// --- ユーザー辞書の構築 ---
// --- ユーザー辞書の構築 ---
$dict = new UserDictionary();
$dict->addAll([
	['reading' => 'daare',     'surface' => 'だあれ',    'mode' => UserDictionary::MODE_SERVER, 'word_cost' => -6000, 'pos' => "名詞"],
	['reading' => 'ime',       'surface' => 'IME',       'mode' => UserDictionary::MODE_SERVER, 'word_cost' => -5000, 'pos' => "名詞"],
	// 「eremenntox」→ toHiragana → 「えれめんとx」だが x は変換されないため
	// removeIllegalFlag=true にするか、reading を「えれめんと」にして別途処理する
	['reading' => 'eremenntox', 'surface' => 'ElementX',  'mode' => UserDictionary::MODE_SERVER, 'word_cost' => -5000, 'pos' => "名詞"],
	// 「sod」→ 「そd」 → d が残るため reading は「そ」にするか入力を「sodo」等にする
	['reading' => 'sod',       'surface' => 'SOD SERVER', 'mode' => UserDictionary::MODE_SERVER, 'word_cost' => -5000, 'pos' => "名詞"],
	['reading' => 'test',       'surface' => 'てすと', 'mode' => UserDictionary::MODE_SERVER, 'word_cost' => -5000, 'pos' => "名詞"],
]);

$converter->registerUserDict('main', $dict);

$time = microtime(true);

foreach(["sod", "eremenntox", "test", "aisusuki"] as $input){
	$result = $converter->convert($input);

	//var_dump($result);

	//var_dump($result["best"]["text"]);
	//var_dump($result["kana"]);

	if(!$converter->isValid($result)){
		echo "kana: ".$result["kana"], "\n";
		continue;
	}

	echo "no-maru:". $result["best"]["text"], "\n";
}
$time = microtime(true) - $time;

echo "Time: {$time} sec\n";

echo ((memory_get_peak_usage() - $basemem) / 1024 / 1024) . " MB\n";
echo ((memory_get_usage() - $basemem1) / 1024 / 1024) . " MB\n";
