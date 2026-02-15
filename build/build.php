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