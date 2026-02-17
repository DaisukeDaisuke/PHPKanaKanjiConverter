# PHPKanaKanjiConverter
Vibe coded romaji to hiragana to kanji conversion  
ローマ字入力を漢字表記にできるPHPライブラリ

> [!CAUTION]
> Vibe coded!!!!!

# What is this?
This library converts romaji to hiragana and kanji.  
However, because it does not understand the text, it may produce worse results than advanced IMEs.  

# the purpose
There may be cases where users want to convert Romanized input offline using an IME.
```
kilyouhaharedesu
```
This library enables it using pure PHP.
```
今日は晴れです
```

# Requirement
PHP 8.0 or later and mbstring extension

# usage

```php
composer require daisukedaisuke/kanakanjiconverter
```

```php
<?php

declare(strict_types=1);

use kanakanjiconverter\PHPKanaKanjiConverter;

include __DIR__ . '/vendor/autoload.php';

$input = "kilyounotennkihaharede,kilyounotilyuusilyokuhaaisudesu!";
$converter = new PHPKanaKanjiConverter();
$a = $converter->convert($input);
echo $a["best"]["text"], "\n";
```

## result

```txt
今日の天気は晴れで，今日の昼食はアイスです！
```

## Input other than Roman letters is not possible

```php
<?php

declare(strict_types=1);

use kanakanjiconverter\PHPKanaKanjiConverter;

include __DIR__ . '/vendor/autoload.php';

$input = "imaconverterですか";
$converter = new PHPKanaKanjiConverter();
$result = $converter->convert($input);
echo $result["best"]["text"], "\n";
```

### result

```txt
今今ヴェ手ですか
```

## Malfunction detection

```php
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
foreach(["kannzeidekilyogisinnkokuwosaigennsurudoramagaarimasu","server", "konn", "sinnkannsenn", "converterですか", "annigasuki", "anninnsuto-ru"] as $input){
	$result = $converter->convert($input);


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

```

### result

```php
no-maru:関税で虚偽申告を再現するドラマがあります
no-maru:せrヴェr
kana: こん
no-maru:新幹線
no-maru:今ヴェrてrですか
no-maru:Annihilationが好き
no-maru:アンインストール
Time: 0.10237002372742 sec
124.99425506592 MB
123.27954101562 MB
```

# treedumping

```php
<?php
declare(strict_types=1);

use kanakanjiconverter\PHPKanaKanjiConverter;
use kanakanjiconverter\UserDictionary;

include __DIR__ . '/vendor/autoload.php';

$time = microtime(true);
$basemem = memory_get_usage();
$basemem1 = memory_get_peak_usage();
$converter = new PHPKanaKanjiConverter();
$input = "gaitametoennsouba";
$result = $converter->convert($input);
//var_dump($result["kana"]);

if(!$converter->isValid($result)){
	echo "kana: ".$result["kana"], "\n";
	return;
}

echo "no-maru:". $result["best"]["text"], "\n";

foreach($result["best"]["tokens"] as $item){
	var_dump($item);
}

$time = microtime(true) - $time;

echo "Time: {$time} sec\n";

echo ((memory_get_peak_usage() - $basemem) / 1024 / 1024) . " MB\n";
echo ((memory_get_usage() - $basemem1) / 1024 / 1024) . " MB\n";```

```

## result
```
no-maru:外為と円相場
array(7) {
  ["surface"]=>
  string(6) "外為"
  ["reading"]=>
  string(12) "がいため"
  ["word_cost"]=>
  int(6485)
  ["penalty"]=>
  int(0)
  ["pos"]=>
  string(6) "名詞"
  ["subpos"]=>
  string(6) "一般"
  ["pos_label"]=>
  string(13) "名詞-一般"
}
array(7) {
  ["surface"]=>
  string(3) "と"
  ["reading"]=>
  string(3) "と"
  ["word_cost"]=>
  int(0)
  ["penalty"]=>
  int(1000)
  ["pos"]=>
  string(6) "助詞"
  ["subpos"]=>
  string(12) "並立助詞"
  ["pos_label"]=>
  string(19) "助詞-並立助詞"
}
array(7) {
  ["surface"]=>
  string(9) "円相場"
  ["reading"]=>
  string(15) "えんそうば"
  ["word_cost"]=>
  int(6976)
  ["penalty"]=>
  int(0)
  ["pos"]=>
  string(6) "名詞"
  ["subpos"]=>
  string(6) "一般"
  ["pos_label"]=>
  string(13) "名詞-一般"
}
Time: 0.051853179931641 sec
124.31408691406 MB
123.28768157959 MB
```

# multiresult

```php
<?php

declare(strict_types=1);

use kanakanjiconverter\PHPKanaKanjiConverter;

include __DIR__ . '/vendor/autoload.php';

$time = microtime(true);
$basemem = memory_get_usage();
$basemem1 = memory_get_peak_usage();
$converter = new PHPKanaKanjiConverter();
$input = "konoraiburarihamainnkurahutonotamenitukuraremasita";
$result = $converter->convert($input, false, 5);//Number of pieces
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
```

## result

```
no-maru:このライブラリはマインクラフトの為に作られました
string(72) "このライブラリはマインクラフトの為に作られました"
string(75) "このライブラリはマインクラフトのために作られました"
string(72) "このライブラリはマインクラフトの為に造られました"
Time: 0.063277006149292 sec
124.9249420166 MB
123.29665374756 MB
```

# benchmark

```
Input: kilyounotennkihaharedesugoshiyasuidesu
nihonngo: きょうのてんきははれですごしやすいです
今日の天気は晴れで過ごしやすいです
Convert time: 47.377109527588 ms

Input: watashihanihongonobenkyouwoshiteimasu
nihonngo: わたしはにほんごのべんきょうをしています
私は日本語の勉強をしています
Convert time: 17.940998077393 ms

Input: ashitahatomodachitotoukyouniikimasu
nihonngo: あしたはともだちととうきょうにいきます
明日は友達と東京に行きます
Convert time: 30.944108963013 ms

Input: korehakanawokanjinihenkansurutesutodesu
nihonngo: これはかなをかんじにへんかんするてすとです
これはカナを感じに変換するテストです
Convert time: 25.392055511475 ms

Input: konosukuriputohabenchima-kudesu
nihonngo: このすくりぷとはべんちまーくです
このスクリプトはベンチマークです
Convert time: 8.4488391876221 ms

Input: nihonnojuuyounabunkawoookumanabimasu
nihonngo: にほんおじゅうようなぶんかをおおくまなびます
日本お重要な文化を多く学びます
Convert time: 24.056196212769 ms

Input: atarasiipurojekutowokaihatsuchuudesu
nihonngo: あたらしいぷろじぇくとをかいはつちゅうです
新しいプロジェクトを開発中です
Convert time: 23.412227630615 ms

Input: saikinnopasokonhaseinougaiidesu
nihonngo: さいきんおぱそこんはせいのうがいいです
最近おパソコンは性能が良いです
Convert time: 32.288074493408 ms

Input: kanakanjikonnba-ta-notesutowoshiteimasu
nihonngo: かなかんじこんばーたーのてすとをしています
かな漢字コンバーターのテストをしています
Convert time: 38.14697265625 ms

Input: nagairo-majibunnwokousokudekenshoushimasu
nihonngo: ながいろーまじぶんをこうそくでけんしょうします
長いローマ字分を高速で検証します
Convert time: 46.870946884155 ms

Input: kinounopurozilekutowosuisinnsuru
nihonngo: きのうのぷろじぇくとをすいしんする
昨日のプロジェクトを推進する
Convert time: 20.473003387451 ms

Input: kinounoyorugohannha,sukiyakidesu
nihonngo: きのうのよるごはんは，すきやきです
昨日の夜ご飯は，すき焼きです
Convert time: 17.143964767456 ms

Input: asitanoasa
nihonngo: あしたのあさ
明日の朝
Convert time: 3.8127899169922 ms

Input: kinounokaigiwokaisaisita
nihonngo: きのうのかいぎをかいさいした
昨日の会議を開催した
Convert time: 29.093980789185 ms

Input: imanozidai
nihonngo: いまのじだい
今の時代
Convert time: 4.241943359375 ms

Input: mainitiwotaisetuni
nihonngo: まいにちをたいせつに
毎日を大切に
Convert time: 9.0830326080322 ms

Input: kikaiha
nihonngo: きかいは
機会は
Convert time: 11.415958404541 ms

Input: taiki
nihonngo: たいき
待機
Convert time: 5.7158470153809 ms

Input: mottokireini
nihonngo: もっときれいに
もっとキレイに
Convert time: 12.524127960205 ms

Input: hanasigaaru
nihonngo: はなしがある
話がある
Convert time: 4.3768882751465 ms

Input: sannninndeiku
nihonngo: さんにんでいく
3人で行く
Convert time: 4.5528411865234 ms

Input: toukilyuu
nihonngo: とうきゅう
東急
Convert time: 9.2229843139648 ms

Input: kinougasugureteiru
nihonngo: きのうがすぐれている
機能が優れている
Convert time: 7.706880569458 ms

Input: atarasiikikaku
nihonngo: あたらしいきかく
新しい企画
Convert time: 19.798040390015 ms

Input: ookinakabu
nihonngo: おおきなかぶ
大きな株
Convert time: 13.098955154419 ms

Input: terebiwomiru
nihonngo: てれびをみる
テレビを見る
Convert time: 1.2779235839844 ms

Input: kannsilyasareteiru
nihonngo: かんしゃされている
感謝されている
Convert time: 9.5610618591309 ms

Input: kinoutabeta
nihonngo: きのうたべた
昨日食べた
Convert time: 4.8551559448242 ms

Input: suuzinotesuto1234567890tesuto
nihonngo: すうじのてすと1234567890てすと
数字のテスト1234567890テスト
Convert time: 7.1470737457275 ms

=========================
Preload time: 0.20289421081543 ms
Average convert time (10 AI words): 16.895861461245 ms
113.64087677002 MB
111.66150665283 MB
```


# source
## C#でかな漢字変換を実装してみた
https://zenn.dev/kx_ras/articles/83c1a2668aecdf

## 日本語かな漢字変換を実装してみた
https://qiita.com/taka7n/items/d8ac724ee5f5634c545c

## ラティスのNbestを求める
https://jetbead.hatenablog.com/entry/20160119/1453139047

# special Thanks
## google/mozc
https://github.com/google/mozc


# enamdict

https://www.edrdg.org/enamdict/enamdict_doc.html  
  
`http://ftp.edrdg.org/pub/Nihongo/enamdict.gz`  
