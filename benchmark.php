<?php

declare(strict_types=1);

use kanakanjiconverter\PHPKanaKanjiConverter;
use kanakanjiconverter\ConvertibleRomaji;

require __DIR__ . '/vendor/autoload.php';

/**
 * 秒 → ミリ秒
 */
function ms(float $seconds): float {
	return $seconds * 1000;
}

/**
 * AI生成・長めローマ字テストデータ
 */
$testWords = [
	"kilyounotennkihaharedesugoshiyasuidesu",
	"watashihanihongonobenkyouwoshiteimasu",
	"ashitahatomodachitotoukyouniikimasu",
	"korehakanawokanjinihenkansurutesutodesu",
	"konosukuriputohabenchima-kudesu",
	"nihonnojuuyounabunkawoookumanabimasu",
	"atarasiipurojekutowokaihatsuchuudesu",
	"saikinnopasokonhaseinougaiidesu",
	"kanakanjikonnba-ta-notesutowoshiteimasu",
	"nagairo-majibunnwokousokudekenshoushimasu",
	"kinounopurozilylekutowosuisinnsuru",
	"kinounoyorugohannha,sukiyakidesu",
	"asitanoasa",
	"kinounokaigiwokaisaisita",
	"imanozidai",
	"mainitiwotaisetuni",
	"kikaiha",
	"taiki",
	"mottokireini",
	"hanasigaaru",
	"sannninndeiku",
	"toukilyuu",
	"kinougasugureteiru",
	"atarasiikikaku",
	"ookinakabu",
	"terebiwomiru",
	"kannsilyasareteiru",
	"kinoutabeta",
	"suuzinotesuto1234567890tesuto"
];

/* =========================
 * 1. プリロード時間測定
 * ========================= */
$startPreload = microtime(true);
$converter = new PHPKanaKanjiConverter();
$endPreload = microtime(true);

$preloadTime = $endPreload - $startPreload;

/* =========================
 * 2. 変換時間測定
 * ========================= */
$totalConvertTime = 0.0;

$kana = new ConvertibleRomaji();

foreach ($testWords as $input) {
	$start = microtime(true);
	$a = $converter->convert($input);

	$end = microtime(true);

	$elapsed = $end - $start;
	$totalConvertTime += $elapsed;

	echo "Input: {$input}\n";
	echo "nihonngo: " . $kana->toHiragana($input, false) . "\n";
	echo $a["best"]["text"], "\n";
	echo "Convert time: " . ms($elapsed) . " ms\n\n";
}

$averageConvertTime = $totalConvertTime / count($testWords);

/* =========================
 * 結果表示
 * ========================= */
echo "=========================\n";
echo "Preload time: " . ms($preloadTime) . " ms\n";
echo "Average convert time (10 AI words): " . ms($averageConvertTime) . " ms\n";

echo (memory_get_peak_usage() / 1024 / 1024) . " MB\n";
echo (memory_get_usage() / 1024 / 1024) . " MB\n";