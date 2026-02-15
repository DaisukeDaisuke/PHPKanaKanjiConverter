<?php
// build_dictionary_index.php
// 使い方: php build_dictionary_index.php /path/to/dictionaries/

declare(strict_types=1);

function buildDictionaryIndex(string $baseDir): void
{
	$idxFile = $baseDir . DIRECTORY_SEPARATOR . 'dictionary.idx';
	$strFile = $baseDir . DIRECTORY_SEPARATOR . 'dictionary.str';

	$entries   = [];  // [reading, file_id, line_offset]
	$strPool   = '';  // reading文字列を連結
	$strOffsets = []; // reading => offset in strPool（重複排除）

	for ($i = 0; $i <= 9; $i++) {
		$fname = $baseDir . DIRECTORY_SEPARATOR . sprintf('dictionary%02d.txt', $i);
		if (!is_file($fname)) {
			continue;
		}

		$fh = fopen($fname, 'rb');
		if ($fh === false) {
			continue;
		}

		echo "Reading dictionary{$i}...\n";
		$offset = 0;

		while (($line = fgets($fh)) !== false) {
			$lineLen = strlen($line);
			$tab = strpos($line, "\t");
			if ($tab !== false && $tab > 0) {
				$reading = substr($line, 0, $tab);

				// 文字列プールに未登録なら追加
				if (!isset($strOffsets[$reading])) {
					$strOffsets[$reading] = strlen($strPool);
					$strPool .= $reading;
				}

				$entries[] = [
					'str_offset'  => $strOffsets[$reading],
					'str_len'     => strlen($reading),
					'file_id'     => $i,
					'line_offset' => $offset,
				];
			}
			$offset += $lineLen;
		}
		fclose($fh);
	}

	echo "Sorting " . count($entries) . " entries...\n";

	// readingでソート（文字列プールから復元して比較）
	usort($entries, function($a, $b) use ($strPool) {
		$ra = substr($strPool, $a['str_offset'], $a['str_len']);
		$rb = substr($strPool, $b['str_offset'], $b['str_len']);
		return strcmp($ra, $rb);
	});

	echo "Writing index...\n";

	// .str ファイル書き出し
	file_put_contents($strFile, $strPool);

	// .idx ファイル書き出し（1レコード13バイト固定）
	$fh = fopen($idxFile, 'wb');
	if ($fh === false) {
		throw new RuntimeException("Cannot write: $idxFile");
	}

	// ヘッダ: レコード数(4byte) + reserved(8byte) = 12byte
	fwrite($fh, pack('V', count($entries)));
	fwrite($fh, str_repeat("\x00", 8));

	foreach ($entries as $e) {
		fwrite($fh, pack(
			'VvCVv',
			$e['str_offset'],   // V: uint32 4byte
			$e['str_len'],      // v: uint16 2byte
			$e['file_id'],      // C: uint8  1byte
			$e['line_offset'],  // V: uint32 4byte
			0                   // v: reserved 2byte
		));
	}

	fclose($fh);

	$idxSize = round(filesize($idxFile) / 1024 / 1024, 2);
	$strSize = round(filesize($strFile) / 1024 / 1024, 2);
	echo "Done! idx={$idxSize}MB str={$strSize}MB\n";
}

$baseDir = realpath(__DIR__."/../src/dictionary_oss");
buildDictionaryIndex(rtrim($baseDir, DIRECTORY_SEPARATOR));


// build_dictionary_index.php の末尾に追記

function buildConnectionBinary(string $baseDir): void
{
	$srcFile = $baseDir . DIRECTORY_SEPARATOR . 'connection_single_column.txt';
	$binFile = $baseDir . DIRECTORY_SEPARATOR . 'connection.bin';

	if (!is_file($srcFile)) {
		echo "connection_single_column.txt が見つかりません\n";
		return;
	}

	echo "Reading connection file...\n";

	$fh = fopen($srcFile, 'rb');
	if ($fh === false) {
		throw new RuntimeException("Cannot open: $srcFile");
	}

	// 1行目：サイズ
	$firstLine = trim((string)fgets($fh));
	$firstLine = ltrim($firstLine, "\xEF\xBB\xBF"); // BOM除去
	$reportedSize = (int)$firstLine;

	// resolveConnectionSize 相当：実際の行数から正確なサイズを決定
	// 先にファイルサイズから行数を推定するより、全部読んで数える
	$costs = [];
	while (($line = fgets($fh)) !== false) {
		$costs[] = (int)trim($line);
	}
	fclose($fh);

	$lineCount = count($costs);
	echo "Line count: {$lineCount}\n";

	// resolveConnectionSize と同じロジック
	$size = $reportedSize;
	if ($lineCount === $size * $size) {
		// そのまま
	} elseif ($lineCount === ($size + 1) * ($size + 1)) {
		$size = $size + 1;
	} else {
		$root = (int)floor(sqrt((float)$lineCount));
		if ($root > 0 && $root * $root === $lineCount) {
			$size = $root;
		}
	}

	echo "Matrix size: {$size}×{$size}\n";

	// バイナリ書き出し
	// ヘッダ: size(4byte) + reserved(4byte) = 8byte
	// データ: int16 × size × size
	$out = fopen($binFile, 'wb');
	if ($out === false) {
		throw new RuntimeException("Cannot write: $binFile");
	}

	fwrite($out, pack('VV', $size, 0)); // ヘッダ8byte

	// int16 は -32768〜32767 なので pack('v') では符号なしになる
	// 接続コストは負値もありうるので符号付きで扱う
	// → pack('s*') で一括書き出しが最速だがメモリを食うので分割
	$chunkSize = 10000;
	$total = count($costs);
	for ($i = 0; $i < $total; $i += $chunkSize) {
		$chunk = array_slice($costs, $i, $chunkSize);
		// 's' = signed short (machine byte order) → ポータビリティのため 'v' + 符号変換
		$bin = '';
		foreach ($chunk as $v) {
			// 負値を uint16 に変換して pack
			$bin .= pack('v', $v & 0xFFFF);
		}
		fwrite($out, $bin);
	}
	fclose($out);

	$binSize = round(filesize($binFile) / 1024 / 1024, 2);
	echo "Done! connection.bin = {$binSize}MB  (size={$size})\n";
}

$baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR);
buildDictionaryIndex($baseDir);
buildConnectionBinary($baseDir);