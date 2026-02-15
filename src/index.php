<?php

declare(strict_types=1);

require_once __DIR__ . '/ConvertibleRomaji.php';
require_once __DIR__ . '/KanaKanjiConverter.php';

use app\ConvertibleRomaji;
use app\KanaKanjiConverter;

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$input = '';
$nbest = 1;

if ($method === 'POST') {
	$raw = file_get_contents('php://input');
	$data = json_decode($raw ?? '', true);
	if (is_array($data)) {
		if (isset($data['q'])) {
			$input = (string)$data['q'];
		}
		if (isset($data['n'])) {
			$nbest = (int)$data['n'];
		}
	}
	if ($input === '' && isset($_POST['q'])) {
		$input = (string)$_POST['q'];
	}
	if (isset($_POST['n'])) {
		$nbest = (int)$_POST['n'];
	}
} else {
	if (isset($_GET['q'])) {
		$input = (string)$_GET['q'];
	}
	if (isset($_GET['n'])) {
		$nbest = (int)$_GET['n'];
	}
}

if ($input === '') {
	http_response_code(400);
	echo json_encode(
		['error' => 'missing q'],
		JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
	);
	exit;
}

$romaji = new ConvertibleRomaji($input);
$hiragana = $romaji->toHiragana(true);
$converter = new KanaKanjiConverter(realpath(__DIR__ . '/../data') ?: (__DIR__ . '/../data'));
$result = $converter->convert($hiragana, $nbest);

echo json_encode(
	[
		'input' => $input,
		'hiragana' => $hiragana,
		'best' => $result['best'],
		'candidates' => $result['candidates'],
	],
	JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
);
