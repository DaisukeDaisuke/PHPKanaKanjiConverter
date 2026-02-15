<?php

declare(strict_types=1);

namespace app;

include __DIR__ . '/vendor/autoload.php';


$romaji = new ConvertibleRomaji('kilyouhanitiyoubi');
$hiragana = $romaji->toHiragana(true);
var_dump($hiragana);

$dictDir = realpath(__DIR__ . '/mozc/src/data/dictionary_oss') ?: (__DIR__ . '/mozc/src/data/dictionary_oss');
$converter = new KanaKanjiConverter($dictDir);

$ret = $converter->convert($hiragana, 3);
var_dump($ret);