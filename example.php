<?php

declare(strict_types=1);

use kanakanjiconverter\PHPKanaKanjiConverter;
use kanakanjiconverter\UserDictionary;

include __DIR__ . '/vendor/autoload.php';

$basemem = memory_get_usage();
$basemem1 = memory_get_peak_usage();
$converter = new PHPKanaKanjiConverter();

$dict = new UserDictionary();
$dict->addAll([
	// 「eremenntox」→ toHiragana → 「えれめんとx」だが x は変換されないため
	// removeIllegalFlag=true にするか、reading を「えれめんと」にして別途処理する
	['reading' => 'eremenntox', 'surface' => 'ElementX',  'mode' => UserDictionary::MODE_REPLACE,'word_cost' => -5000, 'pos' => "名詞"],
	// 「sod」→ 「そd」 → d が残るため reading は「そ」にするか入力を「sodo」等にする
	[
		'reading'   => 'anni',
		'surface'   => 'Annihilation',
		'mode'      => UserDictionary::MODE_SERVER,
		'word_cost' => 2000,
		'pos'       => '名詞',
		'subpos'    => '一般',
		'pos_label' => '名詞-一般',
		'left_id'   => 1852,
		'right_id'  => 1852,
	],
	//['reading' => 'sod',       'surface' => 'SOD SERVER',  'mode' => UserDictionary::MODE_REPLACE,'word_cost' => -5000, 'pos' => "名詞"],
]);

$converter->registerUserDict('server', $dict);


$time = microtime(true);

//English is not supported
foreach(["きょうはいいてんきです", "まいにちうんどうすることをすすめます", "して、よりよりまちづくりをしましょう", "かわのせせらぎ", "しゃしんをとるときは", "りかいしてからいんをおしてください", "さよならいったあと", "いままでのけいけんをいかして"] as $input){
	$result = $converter->convert($input);


	var_dump($result["best"]["tokens"]);

	//var_dump($result["kana"]);

	if(!$converter->isValid($result)){
		echo "kana: ".$result["kana"], "\n";
		continue;
	}

	echo "no-maru:". $result["best"]["text"], "\n";
	//var_dump($result["best"]["tokens"]);
}
$time = microtime(true) - $time;

echo "Time: {$time} sec\n";

echo ((memory_get_peak_usage() - $basemem) / 1024 / 1024) . " MB\n";
echo ((memory_get_usage() - $basemem1) / 1024 / 1024) . " MB\n";
