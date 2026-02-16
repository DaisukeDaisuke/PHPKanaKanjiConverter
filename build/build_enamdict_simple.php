<?php
// build_enamdict_simple.php
// 解凍済みの enamdict (EUC-JP) を読み、dictionary10.txt を生成する
declare(strict_types=1);

$baseDir = realpath(__DIR__ . "/../src/dictionary_oss");
if ($baseDir === false) {
	fwrite(STDERR, "error: baseDir not found\n");
	exit(1);
}

$inPath = $baseDir . DIRECTORY_SEPARATOR . 'enamdict';
$outPath = $baseDir . DIRECTORY_SEPARATOR . 'dictionary10.txt';
$noticePath = $baseDir . DIRECTORY_SEPARATOR . 'NOTICE_ENAMDICT.txt';
$mapPath = $baseDir . DIRECTORY_SEPARATOR . 'map.json';

// 入力ファイルチェック
if (!is_file($inPath)) {
	fwrite(STDERR, "error: input file not found: {$inPath}\n");
	exit(1);
}

// 設定: 必要ならここを修正
$defaultCost = 5000;

// ENAMDICT 品詞コード -> LID/RID マップ（必要に応じて編集してください）
$code2lid = [
	's'  => 1924, // surname -> 姓
	'p'  => 1925, // place-name -> 地域一般
	'u'  => 1922, // person unclassified -> 人名一般
	'g'  => 1923, // given name -> 名
	'f'  => 1923,
	'm'  => 1923,
	'h'  => 1922,
	'pr' => 1921,
	'c'  => 1930,
	'o'  => 1930,
	'st' => 1925,
	'wk' => 1921,
	'default' => 1921,
];
$code2rid = $code2lid; // ここでは同じ値を使う

// ひらがなをカタカナへ変換（mb_ord / mb_chr を使用）
function hira_to_kata(string $s): string {
	$out = '';
	$len = mb_strlen($s, 'UTF-8');
	for ($i = 0; $i < $len; $i++) {
		$ch = mb_substr($s, $i, 1, 'UTF-8');
		$code = mb_ord($ch, 'UTF-8');
		if ($code >= 0x3041 && $code <= 0x3096) {
			$out .= mb_chr($code + 0x60, 'UTF-8');
		} else {
			$out .= $ch;
		}
	}
	return $out;
}

// 正規化: 読みをトリムし、ひらがなをカタカナに変換
function normalize_reading(string $r): string {
	$r = trim($r);
	if ($r === '') return $r;
	if (preg_match('/[\x{3041}-\x{3096}]/u', $r)) {
		// ひらがなを含む -> カタカナへ
		// 内部処理のため一度 UTF-8 前提で操作
		return hira_to_kata($r);
	}
	return $r;
}

// ENAMDICT 形式行をパース
function parse_enamdict_line(string $line): ?array {
	$line = trim($line);
	if ($line === '' || $line[0] === '#') return null;
	// 例: 外安孫 [そとやすまご] /(p) Sotoyasumago/
	if (!preg_match('/^([^\s\[]+)\s*\[([^\]]+)\]\s*\/(.+)\/$/u', $line, $m)) {
		return null;
	}
	$surface = $m[1];
	$reading = $m[2];
	$rest = $m[3];

	$codes = [];
	if (preg_match_all('/\(([a-z]{1,3})\)/i', $rest, $mc)) {
		foreach ($mc[1] as $c) $codes[] = strtolower($c);
	}
	if (empty($codes)) $codes = ['default'];
	return ['surface' => $surface, 'reading' => $reading, 'codes' => $codes];
}

// コード配列から LID を決定
function map_code_to_lid(array $codes, array $map): int {
	foreach ($codes as $c) {
		if (isset($map[$c])) return (int)$map[$c];
	}
	return (int)$map['default'];
}

// --- メイン処理 ---
$lines = file($inPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if ($lines === false) {
	fwrite(STDERR, "error: cannot read input file\n");
	exit(1);
}

// 入力は EUC-JP → UTF-8 に変換して扱う
// 出力は UTF-8 とする
$outFp = fopen($outPath, 'w');
if ($outFp === false) {
	fwrite(STDERR, "error: cannot write output file: {$outPath}\n");
	exit(1);
}

$englishMap = [];

$seen = [];
$processed = 0;
$written = 0;
foreach ($lines as $rawLine) {
	$processed++;
	// 文字コード変換（EUC-JP -> UTF-8）
	$line = mb_convert_encoding($rawLine, 'UTF-8', 'EUC-JP');

	$parsed = parse_enamdict_line($line);
	if ($parsed === null) continue;

	$surface = $parsed['surface'];
	$reading = normalize_reading($parsed['reading']);
	if ($reading === '' || $surface === '') continue;

	$surface = mb_convert_kana($surface, "KVa");
	$surface = mb_strtolower($surface, "UTF-8");

// 英字のみかどうか判定
	if (preg_match('/^[a-z]+$/', $surface)) {
		// ここで英語専用mapに登録する
		$englishMap[$surface] = $surface;
		continue;
	}


	$codes = $parsed['codes'];
	$lid = map_code_to_lid($codes, $code2lid);
	// RID は最初の code に対応する mapping があれば使う。なければ lid を使う
	$firstCode = $codes[0] ?? 'default';
	$rid = isset($code2rid[$firstCode]) ? (int)$code2rid[$firstCode] : (int)$lid;
	$cost = $defaultCost;

	// 出力行
	$outLine = "{$reading}\t{$lid}\t{$rid}\t{$cost}\t{$surface}\n";
	$key = $reading . '|' . $lid . '|' . $rid . '|' . $cost . '|' . $surface;
	if (isset($seen[$key])) continue;
	$seen[$key] = true;
	fwrite($outFp, $outLine);
	$written++;
}

fclose($outFp);

// NOTICE ファイル作成（EDRDG クレジット）
$noticeText = <<<EOT
JMnedict / ENAMDICT data derived from The Electronic Dictionary Research and Development Group (EDRDG).
Please maintain appropriate acknowledgement when redistributing.
EDRDG: https://www.edrdg.org/
If you combine other sources, ensure those sources' attribution terms are followed.
EOT;
file_put_contents($noticePath, $noticeText);

file_put_contents($mapPath, json_encode($englishMap, JSON_UNESCAPED_UNICODE));

// LICENSE/NOTICE/README があればコピー（上書きしない）
$copied = 0;
$dirList = scandir($baseDir);
foreach ($dirList as $name) {
	if (preg_match('/^(LICENSE|NOTICE|README)/i', $name)) {
		$src = $baseDir . DIRECTORY_SEPARATOR . $name;
		$dst = $baseDir . DIRECTORY_SEPARATOR . $name; // same dir, skip if same file
		// コピー先が存在している場合はスキップ（上書きしない）
		if (!file_exists($dst)) {
			if (@copy($src, $dst)) $copied++;
		}
	}
}

// レポート
fwrite(STDOUT, "baseDir: {$baseDir}\n");
fwrite(STDOUT, "input: " . basename($inPath) . "\n");
fwrite(STDOUT, "output: " . basename($outPath) . " (written={$written})\n");
fwrite(STDOUT, "processed lines: {$processed}\n");
fwrite(STDOUT, "NOTICE written: " . basename($noticePath) . "\n");

exit(0);
