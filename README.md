# PHPKanaKanjiConverter
Vibe coded romaji to hiragana to kanji conversion

> [!CAUTION]
> Vibe coded!!!!!

# usage

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

```php
string(57) "きょうのてんきははれですごしやすいです"
今日の天気は晴れで過ごしやすいです
Input: kilyounotennkihaharedesugoshiyasuidesu
Convert time: 851.82213783264 ms

string(60) "わたしはにほんごのべんきょうをしています"
私は日本語の勉強をしています
Input: watashihanihongonobenkyouwoshiteimasu
Convert time: 613.26599121094 ms

string(57) "あしたはともだちととうきょうにいきます"
明日は友達と東京に行きます
Input: ashitahatomodachitotoukyouniikimasu
Convert time: 613.3759021759 ms

string(63) "これはかなをかんじにへんかんするてすとです"
これはカナを感じに変換するテストです
Input: korehakanawokanjinihenkansurutesutodesu
Convert time: 627.45499610901 ms

string(48) "このすくりぷとはべんちまーくです"
このスクリプトはベンチマークです
Input: konosukuriputohabenchima-kudesu
Convert time: 614.30788040161 ms

string(66) "にほんのじゅうようなぶんかをおおくまなびます"
日本の重要な文化を多く学びます
Input: nihonnojuuyounabunkawoookumanabimasu
Convert time: 632.06601142883 ms

string(63) "あたらしいぷろじぇくとをかいはつちゅうです"
新しいプロジェクトを開発中です
Input: atarasiipurojekutowokaihatsuchuudesu
Convert time: 620.35799026489 ms

string(57) "さいきんのぱそこんはせいのうがいいです"
最近のパソコンは性能が良いです
Input: saikinnopasokonhaseinougaiidesu
Convert time: 616.72401428223 ms

string(63) "かなかんじこんばーたーのてすとをしています"
かな漢字コンバーターのテストをしています
Input: kanakanjikonnba-ta-notesutowoshiteimasu
Convert time: 628.43608856201 ms

string(69) "ながいろーまじぶんをこうそくでけんしょうします"
長いローマ字分を高速で検証します
Input: nagairo-majibunnwokousokudekenshoushimasu
Convert time: 642.5039768219 ms

=========================
Preload time: 1.8649101257324 ms
Average convert time (10 AI words): 646.031498909 ms
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