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
Input: kilyounotennkihaharedesugoshiyasuidesu
nihonngo: きょうのてんきははれですごしやすいです
今日の天気は晴れで過ごしやすいです
Convert time: 54.537057876587 ms

Input: watashihanihongonobenkyouwoshiteimasu
nihonngo: わたしはにほんごのべんきょうをしています
私は日本語の勉強をしています
Convert time: 15.737056732178 ms

Input: ashitahatomodachitotoukyouniikimasu
nihonngo: あしたはともだちととうきょうにいきます
明日は友達と東京に行きます
Convert time: 26.669025421143 ms

Input: korehakanawokanjinihenkansurutesutodesu
nihonngo: これはかなをかんじにへんかんするてすとです
これはカナを感じに変換するテストです
Convert time: 21.369934082031 ms

Input: konosukuriputohabenchima-kudesu
nihonngo: このすくりぷとはべんちまーくです
このスクリプトはベンチマークです
Convert time: 7.1561336517334 ms

Input: nihonnojuuyounabunkawoookumanabimasu
nihonngo: にほんおじゅうようなぶんかをおおくまなびます
日本お重要な文化を多く学びます
Convert time: 19.139051437378 ms

Input: atarasiipurojekutowokaihatsuchuudesu
nihonngo: あたらしいぷろじぇくとをかいはつちゅうです
新しいプロジェクトを開発中です
Convert time: 16.45302772522 ms

Input: saikinnopasokonhaseinougaiidesu
nihonngo: さいきんおぱそこんはせいのうがいいです
最近おパソコンは性能が良いです
Convert time: 27.8160572052 ms

Input: kanakanjikonnba-ta-notesutowoshiteimasu
nihonngo: かなかんじこんばーたーのてすとをしています
かな漢字コンバーターのテストをしています
Convert time: 32.999038696289 ms

Input: nagairo-majibunnwokousokudekenshoushimasu
nihonngo: ながいろーまじぶんをこうそくでけんしょうします
長いローマ字分を高速で検証します
Convert time: 40.204048156738 ms

Input: kinounopurozilylekutowosuisinnsuru
nihonngo: きのうのぷろじlyぇくとをすいしんする
昨日のプロジlyぇクトを推進する
Convert time: 17.627954483032 ms

Input: kinounoyorugohannha,sukiyakidesu
nihonngo: きのうのよるごはんは，すきやきです
昨日の夜ご飯は，すき焼きです
Convert time: 14.137983322144 ms

Input: asitanoasa
nihonngo: あしたのあさ
明日の朝
Convert time: 3.0620098114014 ms

Input: kinounokaigiwokaisaisita
nihonngo: きのうのかいぎをかいさいした
昨日の会議を開催した
Convert time: 22.455930709839 ms

Input: imanozidai
nihonngo: いまのじだい
今の時代
Convert time: 3.3860206604004 ms

Input: mainitiwotaisetuni
nihonngo: まいにちをたいせつに
毎日を大切に
Convert time: 6.9539546966553 ms

Input: kikaiha
nihonngo: きかいは
機会は
Convert time: 8.5809230804443 ms

Input: taiki
nihonngo: たいき
待機
Convert time: 4.7600269317627 ms

Input: mottokireini
nihonngo: もっときれいに
もっとキレイに
Convert time: 10.912895202637 ms

Input: hanasigaaru
nihonngo: はなしがある
話がある
Convert time: 3.8678646087646 ms

Input: sannninndeiku
nihonngo: さんにんでいく
3人で行く
Convert time: 3.9899349212646 ms

Input: toukilyuu
nihonngo: とうきゅう
東急
Convert time: 7.1439743041992 ms

Input: kinougasugureteiru
nihonngo: きのうがすぐれている
機能が優れている
Convert time: 6.483793258667 ms

Input: atarasiikikaku
nihonngo: あたらしいきかく
新しい企画
Convert time: 16.777992248535 ms

Input: ookinakabu
nihonngo: おおきなかぶ
大きな株
Convert time: 11.013984680176 ms

Input: terebiwomiru
nihonngo: てれびをみる
テレビを見る
Convert time: 1.1301040649414 ms

Input: kannsilyasareteiru
nihonngo: かんしゃされている
感謝されている
Convert time: 8.4738731384277 ms

Input: kinoutabeta
nihonngo: きのうたべた
昨日食べた
Convert time: 4.0340423583984 ms

Input: suuzinotesuto1234567890tesuto
nihonngo: すうじのてすと1234567890てすと
数字のテスト1234567890テスト
Convert time: 5.9258937835693 ms
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