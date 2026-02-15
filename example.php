<?php

declare(strict_types=1);

use kanakanjiconverter\PHPKanaKanjiConverter;
use kanakanjiconverter\ConnectionBinary;

include __DIR__ . '/vendor/autoload.php';

$cb = new ConnectionBinary("src/dictionary_oss");

// BOS(right=0) → 昨日(left=1910) の接続コスト
var_dump($cb->getCost(0, 1910));

// BOS(right=0) → 機能(left=1842) の接続コスト
var_dump($cb->getCost(0, 1842));