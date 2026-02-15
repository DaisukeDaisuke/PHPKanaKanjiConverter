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
D:\software\php\bin\php\php.exe D:\php\PHPKanaKanjiConverter\benchmark.php
今日の天気は晴れで過ごしやすいです
Input: kilyounotennkihaharedesugoshiyasuidesu
Convert time: 855.28302192688 ms

私は日本語の勉強をしています
Input: watashihanihongonobenkyouwoshiteimasu
Convert time: 638.68093490601 ms

明日は友達と東京に行きます
Input: ashitahatomodachitotoukyouniikimasu
Convert time: 630.0630569458 ms

これはカナを感じに変換するテストです
Input: korehakanawokanjinihenkansurutesutodesu
Convert time: 622.79200553894 ms

このスクリプトはベンチマークです
Input: konosukuriputohabenchima-kudesu
Convert time: 616.80603027344 ms

日本の重要な文化大奥の学びます
Input: nihonnojuuyounabunkaoookunomanabimasu
Convert time: 650.37393569946 ms

新シプロジェクトを開発中です
Input: atarashipurojekutowokaihatsuchuudesu
Convert time: 630.98502159119 ms

最近のパソコンは性能が良いです
Input: saikinnopasokonhaseinougaiidesu
Convert time: 622.50995635986 ms

かな漢字コンバーターのテストをしています
Input: kanakanjikonnba-ta-notesutowoshiteimasu
Convert time: 634.02199745178 ms

長いローマ字分を高速で検証します
Input: nagairo-majibunnwokousokudekenshoushimasu
Convert time: 647.8590965271 ms

=========================
Preload time: 1.7600059509277 ms
Average convert time (10 AI words): 654.93750572205 ms

プロセスは終了コード 0 で終了しました

```


# source
## C#でかな漢字変換を実装してみた
https://zenn.dev/kx_ras/articles/83c1a2668aecdf

## 日本語かな漢字変換を実装してみた
https://qiita.com/taka7n/items/d8ac724ee5f5634c545c

## ラティスのNbestを求める
https://jetbead.hatenablog.com/entry/20160119/1453139047

# special Thanks
google/mozc
https://github.com/google/mozc