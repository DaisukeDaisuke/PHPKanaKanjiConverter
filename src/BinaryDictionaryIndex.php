<?php

declare(strict_types=1);

namespace kanakanjiconverter;

use pocketmine\utils\BinaryStream;

/**
 * @internal
 */
final class BinaryDictionaryIndex
{
	private const HEADER_SIZE = 12;
	private const RECORD_SIZE = 13;

	private string $baseDir;
	private int $recordCount = 0;
	private BinaryStream $idxStream;
	private BinaryStream $strStream;

	/** @var array<int, resource> */
	private array $dictHandles = [];

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

		$this->idxStream = new BinaryStream((string) file_get_contents($idxFile));
		$this->strStream = new BinaryStream((string) file_get_contents($strFile));

		$this->idxStream->setOffset(0);
		$this->recordCount = $this->idxStream->getLInt();

		for ($i = 0; $i <= 9; $i++) {
			$fname = $this->baseDir . DIRECTORY_SEPARATOR . sprintf('dictionary%02d.txt', $i);
			if (!is_file($fname)) {
				continue;
			}

			$fh = fopen($fname, 'rb');
			if ($fh !== false) {
				$this->dictHandles[$i] = $fh;
			}
		}
	}

	/**
	 * @return array<string, array[]>
	 */
	public function search(string $hiragana): array
	{
		$result = [];
		$checked = [];
		$mbCharsAll = mb_str_split($hiragana, 1, 'UTF-8');
		$totalChars = count($mbCharsAll);
		$maxLen = min($totalChars, 15);

		for ($startPos = 0; $startPos < $totalChars; $startPos++) {
			$partial = '';
			$limit = min($startPos + $maxLen, $totalChars);
			for ($endPos = $startPos; $endPos < $limit; $endPos++) {
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

	private function findByReading(string $reading): ?array
	{
		$lo = 0;
		$hi = $this->recordCount - 1;
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
				$hi = $mid - 1;
			}
		}

		if ($first === -1) {
			return null;
		}

		$entries = [];
		for ($i = $first; $i < $this->recordCount; $i++) {
			$entry = $this->fetchEntryWithReading($i, $reading);
			if ($entry === false) {
				break;
			}
			if ($entry !== null) {
				$entries[] = $entry;
			}
		}

		return $entries ?: null;
	}

	/**
	 * @return array|null|false
	 */
	private function fetchEntryWithReading(int $i, string $expected): array|null|false
	{
		$pos = self::HEADER_SIZE + $i * self::RECORD_SIZE;
		$this->idxStream->setOffset($pos);

		$strOffset = $this->idxStream->getLInt();
		$strLen = $this->idxStream->getLShort();
		$fileId = $this->idxStream->getByte();
		$lineOffset = $this->idxStream->getLInt();

		$this->strStream->setOffset($strOffset);
		$actualReading = $this->strStream->get($strLen);
		if ($actualReading !== $expected) {
			return false;
		}

		$fh = $this->dictHandles[$fileId] ?? null;
		if ($fh === null) {
			return null;
		}

		if (fseek($fh, $lineOffset) !== 0) {
			return null;
		}

		$line = fgets($fh);
		if ($line === false) {
			return null;
		}

		return $this->parseLine(rtrim($line, "\r\n"), $expected);
	}

	private function readReading(int $i): string
	{
		$pos = self::HEADER_SIZE + $i * self::RECORD_SIZE;
		$this->idxStream->setOffset($pos);

		$strOffset = $this->idxStream->getLInt();
		$strLen = $this->idxStream->getLShort();

		$this->strStream->setOffset($strOffset);
		return $this->strStream->get($strLen);
	}

	private function parseLine(string $line, string $reading): ?array
	{
		$parts = explode("\t", $line, 5);
		if (count($parts) < 5) {
			return null;
		}

		return [
			'reading' => $reading,
			'left_id' => (int) $parts[1],
			'right_id' => (int) $parts[2],
			'word_cost' => (int) $parts[3],
			'surface' => $parts[4],
		];
	}

	public function __destruct()
	{
		foreach ($this->dictHandles as $fh) {
			fclose($fh);
		}
	}
}
