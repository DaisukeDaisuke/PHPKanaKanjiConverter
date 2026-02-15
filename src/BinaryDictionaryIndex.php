<?php

declare(strict_types=1);

namespace kanakanjiconverter;

use pocketmine\utils\BinaryStream;

// BinaryDictionaryIndex.php
/**
 * 固定長バイナリインデックスを使った辞書検索
 * ファイルハンドルの代わりに BinaryStream でメモリ上バッファを管理
 *
 * @internal
 */
final class BinaryDictionaryIndex
{
	private const HEADER_SIZE = 12;
	private const RECORD_SIZE = 13;

	private string $baseDir;
	private int    $recordCount = 0;

	// fopen/fseek/fread の代わりに BinaryStream を使用
	private BinaryStream $idxStream;
	private BinaryStream $strStream;

	/** @var array<int, BinaryStream> file_id => stream */
	private array $dictStreams = [];

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
			throw new \RuntimeException(
				"インデックスが見つかりません。build_dictionary_index.php を実行してください。\n" .
				"  php build_dictionary_index.php {$this->baseDir}"
			);
		}

		// file_get_contents で一括読み込み → BinaryStream でラップ
		$this->idxStream = new BinaryStream((string)file_get_contents($idxFile));
		$this->strStream = new BinaryStream((string)file_get_contents($strFile));

		// ヘッダ読み込み（先頭4バイトがレコード数）
		$this->idxStream->setOffset(0);
		$this->recordCount = $this->idxStream->getLInt();
		// reserved 8バイトはスキップ（ヘッダ計12バイト）

		// 辞書ファイルも同様に一括読み込み
		for ($i = 0; $i <= 9; $i++) {
			$fname = $this->baseDir . DIRECTORY_SEPARATOR . sprintf('dictionary%02d.txt', $i);
			if (is_file($fname)) {
				$this->dictStreams[$i] = new BinaryStream((string)file_get_contents($fname));
			}
		}
	}

	/**
	 * hiragana中に含まれるreadingを全検索
	 * @return array<string, array[]>
	 */
	public function search(string $hiragana): array
	{
		$result  = [];
		$checked = [];
		$mbCharsAll = mb_str_split($hiragana, 1, 'UTF-8');
		$totalChars = count($mbCharsAll);

		for ($startPos = 0; $startPos < $totalChars; $startPos++) {
			$partial = '';
			for ($endPos = $startPos; $endPos < $totalChars; $endPos++) {
				$partial .= $mbCharsAll[$endPos];

				if (isset($checked[$partial])) {
					continue;
				}
				$checked[$partial] = true;

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
	 */
	private function findByReading(string $reading): ?array
	{
		$lo    = 0;
		$hi    = $this->recordCount - 1;
		$first = -1;

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

	/**
	 * インデックスのi番目レコードからreading文字列を読む
	 * BinaryStream のオフセットを直接操作
	 */
	private function readReading(int $i): string
	{
		$pos = self::HEADER_SIZE + $i * self::RECORD_SIZE;
		$this->idxStream->setOffset($pos);

		$strOffset = $this->idxStream->getLInt();   // 4byte
		$strLen    = $this->idxStream->getLShort();  // 2byte

		$this->strStream->setOffset($strOffset);
		return $this->strStream->get($strLen);
	}

	/**
	 * インデックスのi番目レコードからエントリ本体を取得
	 */
	private function fetchEntry(int $i, string $reading): ?array
	{
		$pos = self::HEADER_SIZE + $i * self::RECORD_SIZE;
		$this->idxStream->setOffset($pos);

		$this->idxStream->getLInt();    // str_offset（読み飛ばし）
		$this->idxStream->getLShort();  // str_len（読み飛ばし）
		$fileId     = $this->idxStream->getByte();   // 1byte
		$lineOffset = $this->idxStream->getLInt();   // 4byte

		$stream = $this->dictStreams[$fileId] ?? null;
		if ($stream === null) {
			return null;
		}

		// 指定オフセットから1行読む
		$stream->setOffset($lineOffset);
		$line = '';
		while (!$stream->feof()) {
			$c = $stream->get(1);
			if ($c === "\n") {
				break;
			}
			$line .= $c;
		}

		return $this->parseLine(rtrim($line, "\r"), $reading);
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

	// __destruct 不要（ファイルハンドルなし）
}