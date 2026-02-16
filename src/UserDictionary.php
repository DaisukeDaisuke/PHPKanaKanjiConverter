<?php
declare(strict_types=1);

namespace kanakanjiconverter;

final class UserDictionary
{
	public const DEFAULT_WORD_COST = -3000;

	/** @var list<array{reading:string, surface:string, word_cost:int, pos:string, subpos:string, left_id:int, right_id:int}> */
	private array $entries = [];

	public function add(array $entry): void
	{
		$reading = $this->sanitize((string)($entry['reading'] ?? ''));
		if ($reading === '') {
			return;
		}
		$this->entries[] = [
			'reading'   => $reading,
			'surface'   => $this->sanitize((string)($entry['surface'] ?? $reading)),
			'word_cost' => (int)($entry['word_cost'] ?? self::DEFAULT_WORD_COST),
			'pos'       => $this->sanitize((string)($entry['pos']    ?? '名詞')),
			'subpos'    => $this->sanitize((string)($entry['subpos'] ?? '一般')),
			'left_id'   => (int)($entry['left_id']  ?? 0),
			'right_id'  => (int)($entry['right_id'] ?? 0),
		];
	}

	public function addAll(array $entries): void
	{
		foreach ($entries as $e) {
			$this->add($e);
		}
	}

	public function getAll(): array { return $this->entries; }

	public function clear(): void { $this->entries = []; }

	private function sanitize(string $v): string
	{
		return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $v) ?? $v;
	}
}