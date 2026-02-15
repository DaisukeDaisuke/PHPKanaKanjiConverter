<?php

declare(strict_types=1);


namespace kanakanjiconverter;

use pocketmine\utils\BinaryStream;

/**
 * バイナリ接続コスト表
 * メモリに配列を持たず BinaryStream でオフセット直読み
 */
class ConnectionBinary
{
	private const HEADER_SIZE = 8;   // size(4) + reserved(4)
	private const ENTRY_SIZE  = 2;   // int16

	private BinaryStream $stream;
	private int $size = 0;

	public function __construct(string $baseDir)
	{
		$binFile = $baseDir . DIRECTORY_SEPARATOR . 'connection.bin';

		if (!is_file($binFile)) {
			throw new \RuntimeException(
				"connection.bin が見つかりません。build_dictionary_index.php を実行してください。\n" .
				"  php build_dictionary_index.php {$baseDir}"
			);
		}

		$this->stream = new BinaryStream((string)file_get_contents($binFile));
		$this->stream->setOffset(0);
		$this->size = $this->stream->getLInt(); // 4byte: size
		// reserved 4byte はスキップ（HEADER_SIZE=8 なので setOffset で対処）
	}

	public function getSize(): int
	{
		return $this->size;
	}

	/**
	 * 接続コストを取得（符号付き int16）
	 */
	public function getCost(int $rightId, int $leftId): int
	{
		if ($this->size <= 0) {
			return 0;
		}

		$index  = $rightId * $this->size + $leftId;
		$offset = self::HEADER_SIZE + $index * self::ENTRY_SIZE;

		$this->stream->setOffset($offset);
		$raw = $this->stream->getLShort(); // uint16

		// uint16 → signed int16
		return $raw >= 0x8000 ? $raw - 0x10000 : $raw;
	}
}