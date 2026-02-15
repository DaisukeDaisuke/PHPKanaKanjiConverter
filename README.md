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
Convert time: 49.514055252075 ms

string(60) "わたしはにほんごのべんきょうをしています"
私は日本語の勉強をしています
Input: watashihanihongonobenkyouwoshiteimasu
Convert time: 16.273975372314 ms

string(57) "あしたはともだちととうきょうにいきます"
明日は友達と東京に行きます
Input: ashitahatomodachitotoukyouniikimasu
Convert time: 27.282953262329 ms

string(63) "これはかなをかんじにへんかんするてすとです"
これはカナを感じに変換するテストです
Input: korehakanawokanjinihenkansurutesutodesu
Convert time: 22.221088409424 ms

string(48) "このすくりぷとはべんちまーくです"
このスクリプトはベンチマークです
Input: konosukuriputohabenchima-kudesu
Convert time: 7.1070194244385 ms

string(66) "にほんおじゅうようなぶんかをおおくまなびます"
日本お重要な文化を多く学びます
Input: nihonnojuuyounabunkawoookumanabimasu
Convert time: 19.716024398804 ms

string(63) "あたらしいぷろじぇくとをかいはつちゅうです"
新しいプロジェクトを開発中です
Input: atarasiipurojekutowokaihatsuchuudesu
Convert time: 16.783952713013 ms

string(57) "さいきんおぱそこんはせいのうがいいです"
最近おパソコンは性能が良いです
Input: saikinnopasokonhaseinougaiidesu
Convert time: 28.454065322876 ms

string(63) "かなかんじこんばーたーのてすとをしています"
かな漢字コンバーターのテストをしています
Input: kanakanjikonnba-ta-notesutowoshiteimasu
Convert time: 33.342123031616 ms

string(69) "ながいろーまじぶんをこうそくでけんしょうします"
長いローマ字分を高速で検証します
Input: nagairo-majibunnwokousokudekenshoushimasu
Convert time: 40.879011154175 ms

string(51) "きのうのぷろじぇくとをすいしんする"
昨日のプロジェクトを推進する
Input: kinounopurozilylekutowosuisinnsuru
Convert time: 17.14301109314 ms

string(51) "きのうのよるごはんは，すきやきです"
昨日の夜ご飯は，すき焼きです
Input: kinounoyorugohannha,sukiyakidesu
Convert time: 13.586044311523 ms

string(18) "あしたのあさ"
明日の朝
Input: asitanoasa
Convert time: 3.5469532012939 ms

string(42) "きのうのかいぎをかいさいした"
昨日の会議を開催した
Input: kinounokaigiwokaisaisita
Convert time: 23.138046264648 ms

string(18) "いまのじだい"
今の時代
Input: imanozidai
Convert time: 3.284215927124 ms

string(30) "まいにちをたいせつに"
毎日を大切に
Input: mainitiwotaisetuni
Convert time: 6.9200992584229 ms

string(12) "きかいは"
機会は
Input: kikaiha
Convert time: 8.7289810180664 ms

string(9) "たいき"
待機
Input: taiki
Convert time: 4.8410892486572 ms

string(21) "もっときれいに"
もっとキレイに
Input: mottokireini
Convert time: 10.550022125244 ms

string(18) "はなしがある"
話がある
Input: hanasigaaru
Convert time: 3.870964050293 ms

string(21) "さんにんでいく"
3人で行く
Input: sannninndeiku
Convert time: 4.0280818939209 ms

string(12) "とうきゅ"
当キュ
Input: toukilyu
Convert time: 5.18798828125 ms

string(30) "きのうがすぐれている"
機能が優れている
Input: kinougasugureteiru
Convert time: 6.5081119537354 ms

string(24) "あたらしいきかく"
新しい企画
Input: atarasiikikaku
Convert time: 16.986131668091 ms

string(18) "おおきなかぶ"
大きな株
Input: ookinakabu
Convert time: 11.334180831909 ms

string(18) "てれびをみる"
テレビを見る
Input: terebiwomiru
Convert time: 1.3740062713623 ms

string(27) "かんしゃされている"
感謝されている
Input: kannsilyasareteiru
Convert time: 8.1348419189453 ms

string(18) "きのうたべた"
昨日食べた
Input: kinoutabeta
Convert time: 4.2438507080078 ms

=========================
Preload time: 1.5549659729004 ms
Average convert time (10 AI words): 14.820746013096 ms
113.70761108398 MB
111.71558380127 MB
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