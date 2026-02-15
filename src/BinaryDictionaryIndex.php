<?php

declare(strict_types=1);

namespace kanakanjiconverter;
// BinaryDictionaryIndex.php

/**
 * 固定長バイナリインデックスを使った辞書検索
 * PHPメモリに配列を持たず fseek+fread のみで動作
 */
class BinaryDictionaryIndex
{
	private const HEADER_SIZE  = 12;
	private const RECORD_SIZE  = 13;  // V(4)+v(2)+C(1)+V(4)+v(2)

	private string $baseDir;
	private int    $recordCount = 0;

	/** @var resource */
	private $idxFh;
	/** @var resource */
	private $strFh;
	/** @var array<int, resource> file_id => fh */
	private array $dictFh = [];

	public function __construct(string $baseDir)
	{
		$this->baseDir = $baseDir;
		$this->open();
	}

	private function open(): void
	{
		$idxFile = $this->baseDir . DIRECTORY_SEPARATOR . 'dictionary.idx';
		$strFile = $this->baseDir . DIRECTORY_SEPARATOR . 'dictionary.str';

		if (!is_file($idxFile) || !is_file($strFile)) {
			throw new RuntimeException(
				"インデックスが見つかりません。build_dictionary_index.php を実行してください。\n" .
				"  php build_dictionary_index.php {$this->baseDir}"
			);
		}

		$this->idxFh = fopen($idxFile, 'rb');
		$this->strFh = fopen($strFile, 'rb');

		if ($this->idxFh === false || $this->strFh === false) {
			throw new RuntimeException("インデックスファイルを開けません");
		}

		// ヘッダ読み込み
		$header = fread($this->idxFh, self::HEADER_SIZE);
		$unpacked = unpack('Vcount', $header);
		$this->recordCount = $unpacked['count'];

		// 辞書ファイルハンドルを開いておく
		for ($i = 0; $i <= 9; $i++) {
			$fname = $this->baseDir . DIRECTORY_SEPARATOR . sprintf('dictionary%02d.txt', $i);
			if (is_file($fname)) {
				$fh = fopen($fname, 'rb');
				if ($fh !== false) {
					$this->dictFh[$i] = $fh;
				}
			}
		}
	}

	/**
	 * hiragana中に含まれるreadingを全検索
	 * @return array<string, array[]>  [reading => [entry, ...]]
	 */
	public function search(string $hiragana): array
	{
		$result = [];

		// バイナリサーチ可能な最短prefix候補を収集
		// 全レコードをなめず「hiraganaの先頭文字」でバイナリサーチで絞る
		$chars   = mb_str_split($hiragana, 1, 'UTF-8');
		$checked = [];

		foreach ($chars as $startPos => $char) {
			// hiraganaのこの位置から始まる部分文字列を順に試す
			$partial = '';
			$mbChars = mb_str_split(mb_substr($hiragana, $startPos), 1, 'UTF-8');

			foreach ($mbChars as $c) {
				$partial .= $c;

				if (isset($checked[$partial])) {
					continue;
				}
				$checked[$partial] = true;

				// バイナリサーチでpartialと一致するreadingを探す
				$entries = $this->findByReading($partial);
				if ($entries !== null) {
					$result[$partial] = $entries;
				}
			}
		}

		return $result;
	}

	/**
	 * バイナリサーチで reading に完全一致するエントリを全取得
	 * @return array[]|null  見つからなければ null
	 */
	private function findByReading(string $reading): ?array
	{
		$lo    = 0;
		$hi    = $this->recordCount - 1;
		$first = -1;

		// 左端バイナリサーチ
		while ($lo <= $hi) {
			$mid = ($lo + $hi) >> 1;
			$cmp = strcmp($this->readReading($mid), $reading);
			if ($cmp < 0) {
				$lo = $mid + 1;
			} elseif ($cmp > 0) {
				$hi = $mid - 1;
			} else {
				$first = $mid;
				$hi    = $mid - 1;
			}
		}

		if ($first === -1) {
			return null;
		}

		// 同一readingを右方向に収集
		$entries = [];
		for ($i = $first; $i < $this->recordCount; $i++) {
			if ($this->readReading($i) !== $reading) {
				break;
			}
			$entry = $this->fetchEntry($i, $reading);
			if ($entry !== null) {
				$entries[] = $entry;
			}
		}

		return $entries ?: null;
	}

	/** インデックスのi番目レコードからreading文字列を読む */
	private function readReading(int $i): string
	{
		$pos = self::HEADER_SIZE + $i * self::RECORD_SIZE;
		fseek($this->idxFh, $pos);
		$rec = fread($this->idxFh, self::RECORD_SIZE);

		$u = unpack('Vstr_offset/vstr_len', $rec);
		fseek($this->strFh, $u['str_offset']);
		return fread($this->strFh, $u['str_len']);
	}

	/** インデックスのi番目レコードからエントリ本体を取得 */
	private function fetchEntry(int $i, string $reading): ?array
	{
		$pos = self::HEADER_SIZE + $i * self::RECORD_SIZE;
		fseek($this->idxFh, $pos);
		$rec = fread($this->idxFh, self::RECORD_SIZE);

		$u = unpack('Vstr_offset/vstr_len/Cfile_id/Vline_offset', $rec);

		$fh = $this->dictFh[$u['file_id']] ?? null;
		if ($fh === null) {
			return null;
		}

		fseek($fh, $u['line_offset']);
		$line = fgets($fh);
		if ($line === false) {
			return null;
		}

		return $this->parseLine(rtrim($line, "\r\n"), $reading);
	}

	private function parseLine(string $line, string $reading): ?array
	{
		$parts = explode("\t", $line, 5);
		if (count($parts) < 5) {
			return null;
		}
		return [
			'reading'   => $reading,
			'left_id'   => (int)$parts[1],
			'right_id'  => (int)$parts[2],
			'word_cost' => (int)$parts[3],
			'surface'   => $parts[4],
		];
	}

	public function __destruct()
	{
		if (isset($this->idxFh)) fclose($this->idxFh);
		if (isset($this->strFh)) fclose($this->strFh);
		foreach ($this->dictFh as $fh) fclose($fh);
	}
}