# PHPKanaKanjiConverter
Vibe coded romaji to hiragana to kanji conversion

> [!CAUTION]
> Vibe coded!!!!!

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

# caution

## May often give incorrect results

```php
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
```


### result

```php
機能はすき焼きを食べました
機能はすき焼きを食べました
昨日はすき焼きを食べました
機能はスキヤキを食べました
```

## Input other than Roman letters is not possible

```php
<?php

declare(strict_types=1);

use kanakanjiconverter\PHPKanaKanjiConverter;

include __DIR__ . '/vendor/autoload.php';

$input = "imaconverterですか";
$converter = new PHPKanaKanjiConverter();
$a = $converter->convert($input);
echo $a["best"]["text"], "\n";
```


### result

```txt
今今ヴェ手ですか
```

# benchmark

```
string(57) "きょうのてんきははれですごしやすいです"
今日の天気は晴れで過ごしやすいです
Input: kilyounotennkihaharedesugoshiyasuidesu
Convert time: 51.547050476074 ms

string(60) "わたしはにほんごのべんきょうをしています"
私は日本語の勉強をしています
Input: watashihanihongonobenkyouwoshiteimasu
Convert time: 16.898155212402 ms

string(57) "あしたはともだちととうきょうにいきます"
明日は友達と東京に行きます
Input: ashitahatomodachitotoukyouniikimasu
Convert time: 29.9232006073 ms

string(63) "これはかなをかんじにへんかんするてすとです"
これはカナを感じに変換するテストです
Input: korehakanawokanjinihenkansurutesutodesu
Convert time: 23.651123046875 ms

string(48) "このすくりぷとはべんちまーくです"
このスクリプトはベンチマークです
Input: konosukuriputohabenchima-kudesu
Convert time: 7.4038505554199 ms

string(66) "にほんのじゅうようなぶんかをおおくまなびます"
日本の重要な文化を多く学びます
Input: nihonnojuuyounabunkawoookumanabimasu
Convert time: 21.245956420898 ms

string(63) "あたらしいぷろじぇくとをかいはつちゅうです"
新しいプロジェクトを開発中です
Input: atarasiipurojekutowokaihatsuchuudesu
Convert time: 17.961978912354 ms

string(57) "さいきんのぱそこんはせいのうがいいです"
最近のパソコンは性能が良いです
Input: saikinnopasokonhaseinougaiidesu
Convert time: 30.332088470459 ms

string(63) "かなかんじこんばーたーのてすとをしています"
かな漢字コンバーターのテストをしています
Input: kanakanjikonnba-ta-notesutowoshiteimasu
Convert time: 35.962104797363 ms

string(69) "ながいろーまじぶんをこうそくでけんしょうします"
長いローマ字分を高速で検証します
Input: nagairo-majibunnwokousokudekenshoushimasu
Convert time: 43.026924133301 ms

=========================
Preload time: 2.0620822906494 ms
Average convert time (10 AI words): 27.795243263245 ms
113.64364624023 MB
111.65933990479 MB
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