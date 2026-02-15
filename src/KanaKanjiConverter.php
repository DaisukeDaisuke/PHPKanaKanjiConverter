<?php

declare(strict_types=1);

namespace kanakanjiconverter;

final class KanaKanjiConverter
{
	private const INF = 1000000000;
	private const SAME_SURFACE_PENALTY = 1000;
	private const UNKNOWN_PENALTY = 10000;

	private string $dictFile;
	private string $connectionFile;

	public function __construct(string $dictPath)
	{
		if (is_file($dictPath)) {
			$this->dictFile = $dictPath;
			$this->connectionFile = dirname($dictPath) . DIRECTORY_SEPARATOR . 'connection_single_column.txt';
		} else {
			$base = rtrim($dictPath, DIRECTORY_SEPARATOR);
			$this->dictFile = $base . DIRECTORY_SEPARATOR . 'dictionary00.txt';
			$this->connectionFile = $base . DIRECTORY_SEPARATOR . 'connection_single_column.txt';
		}
	}

	/**
	 * $hiragana: 変換対象のひらがな文字列（UTF-8）
	 * $nbest: 取得する候補数
	 */
	public function convert(string $hiragana, int $nbest = 1): array
	{
		$nbest = max(1, min($nbest, 100)); // 上限は適宜調整可能

		$entriesByReading = $this->collectEntriesFromDictionaries($hiragana);

		$lattice = $this->buildLattice($hiragana, $entriesByReading);
		$this->loadConnectionCosts($lattice);

		$forward = $this->forwardDp($lattice);
		$candidates = $this->backwardAStar($lattice, $forward['costs'], $nbest);

		$best = $candidates[0] ?? [
			'text' => $hiragana,
			'tokens' => [
				[
					'surface' => $hiragana,
					'reading' => $hiragana,
					'word_cost' => 0,
					'penalty' => 0,
				],
			],
			'cost' => 0,
		];

		return [
			'best' => $best,
			'candidates' => $candidates,
		];
	}
	private ?BinaryDictionaryIndex $binaryIndex = null;

	private function getBinaryIndex(): BinaryDictionaryIndex
	{
		if ($this->binaryIndex === null) {
			$this->binaryIndex = new BinaryDictionaryIndex(dirname($this->dictFile));
		}
		return $this->binaryIndex;
	}

	private function collectEntriesFromDictionaries(string $hiragana): array
	{
		return $this->getBinaryIndex()->search($hiragana);
	}



	private function parseDictionaryLine(string $line): ?array
	{
		$pos1 = strpos($line, "\t");
		if ($pos1 === false) {
			return null;
		}
		$pos2 = strpos($line, "\t", $pos1 + 1);
		if ($pos2 === false) {
			return null;
		}
		$pos3 = strpos($line, "\t", $pos2 + 1);
		if ($pos3 === false) {
			return null;
		}
		$pos4 = strpos($line, "\t", $pos3 + 1);
		if ($pos4 === false) {
			return null;
		}

		$reading = substr($line, 0, $pos1);
		$leftId = (int)substr($line, $pos1 + 1, $pos2 - $pos1 - 1);
		$rightId = (int)substr($line, $pos2 + 1, $pos3 - $pos2 - 1);
		$wordCost = (int)substr($line, $pos3 + 1, $pos4 - $pos3 - 1);
		$surface = substr($line, $pos4 + 1);

		return [
			'reading' => $reading,
			'left_id' => $leftId,
			'right_id' => $rightId,
			'word_cost' => $wordCost,
			'surface' => $surface,
		];
	}

	private function buildLattice(string $hiragana, array $entriesByReading): array
	{
		$len = mb_strlen($hiragana, 'UTF-8');
		$nodes = [];
		$nodesByStart = array_fill(0, $len + 1, []);
		$nodesByEnd = array_fill(0, $len + 1, []);

		$bosId = 0;
		$nodes[$bosId] = [
			'id' => $bosId,
			'start' => 0,
			'end' => 0,
			'reading' => '',
			'surface' => '',
			'left_id' => 0,
			'right_id' => 0,
			'word_cost' => 0,
			'penalty' => 0,
			'cost' => 0,
		];
		$nodesByStart[0][] = $bosId;
		$nodesByEnd[0][] = $bosId;

		$nextId = 1;

		// entriesByReading のキーは reading、値は entry の配列
		foreach ($entriesByReading as $reading => $entries) {
			$readingLen = mb_strlen($reading, 'UTF-8');
			$offset = 0;

			while (($pos = mb_strpos($hiragana, $reading, $offset, 'UTF-8')) !== false) {
				$start = $pos;
				$end = $pos + $readingLen;

				foreach ($entries as $entry) {
					$penalty = ($entry['surface'] === $reading) ? self::SAME_SURFACE_PENALTY : 0;

					$nodes[$nextId] = [
						'id' => $nextId,
						'start' => $start,
						'end' => $end,
						'reading' => $reading,
						'surface' => $entry['surface'],
						'left_id' => $entry['left_id'],
						'right_id' => $entry['right_id'],
						'word_cost' => $entry['word_cost'],
						'penalty' => $penalty,
						'cost' => $entry['word_cost'] + $penalty,
					];

					$nodesByStart[$start][] = $nextId;
					$nodesByEnd[$end][] = $nextId;
					$nextId++;
				}

				$offset = $pos + 1;
			}
		}

		for ($i = 0; $i < $len; $i++) {
			$hasReal = false;
			foreach ($nodesByStart[$i] as $nodeId) {
				if ($nodeId !== $bosId) {
					$hasReal = true;
					break;
				}
			}

			if ($hasReal) {
				continue;
			}

			$ch = mb_substr($hiragana, $i, 1, 'UTF-8');
			$nodes[$nextId] = [
				'id' => $nextId,
				'start' => $i,
				'end' => $i + 1,
				'reading' => $ch,
				'surface' => $ch,
				'left_id' => 0,
				'right_id' => 0,
				'word_cost' => self::UNKNOWN_PENALTY,
				'penalty' => 0,
				'cost' => self::UNKNOWN_PENALTY,
			];
			$nodesByStart[$i][] = $nextId;
			$nodesByEnd[$i + 1][] = $nextId;
			$nextId++;
		}

		$eosId = $nextId;
		$nodes[$eosId] = [
			'id' => $eosId,
			'start' => $len,
			'end' => $len,
			'reading' => '',
			'surface' => '',
			'left_id' => 0,
			'right_id' => 0,
			'word_cost' => 0,
			'penalty' => 0,
			'cost' => 0,
		];
		$nodesByStart[$len][] = $eosId;
		$nodesByEnd[$len][] = $eosId;

		return [
			'nodes' => $nodes,
			'nodesByStart' => $nodesByStart,
			'nodesByEnd' => $nodesByEnd,
			'bos' => $bosId,
			'eos' => $eosId,
			'len' => $len,
		];
	}

	private function forwardDp(array $lattice): array
	{
		$nodes = $lattice['nodes'];
		$nodesByStart = $lattice['nodesByStart'];
		$nodesByEnd = $lattice['nodesByEnd'];
		$len = $lattice['len'];
		$bos = $lattice['bos'];
		$eos = $lattice['eos'];

		$count = count($nodes);
		$costs = array_fill(0, $count, self::INF);
		$prev = array_fill(0, $count, -1);
		$costs[$bos] = 0;

		for ($pos = 0; $pos <= $len; $pos++) {
			$prevNodes = $nodesByEnd[$pos];
			$nextNodes = $nodesByStart[$pos];

			foreach ($prevNodes as $prevId) {
				if ($costs[$prevId] === self::INF) {
					continue;
				}
				if ($prevId === $eos) {
					continue;
				}

				foreach ($nextNodes as $nextId) {
					if ($nextId === $bos) {
						continue;
					}
					if ($nextId === $eos && $pos !== $len) {
						continue;
					}
					if ($prevId === $bos && $nextId === $eos && $len > 0) {
						continue;
					}

					$edgeCost = $this->getConnectionCost($nodes[$prevId]['right_id'], $nodes[$nextId]['left_id']);
					$cost = $costs[$prevId] + $edgeCost + $nodes[$nextId]['cost'];

					if ($cost < $costs[$nextId]) {
						$costs[$nextId] = $cost;
						$prev[$nextId] = $prevId;
					}
				}
			}
		}

		return [
			'costs' => $costs,
			'prev' => $prev,
		];
	}

	private function backwardAStar(array $lattice, array $bestCosts, int $nbest): array
	{
		$nodes = $lattice['nodes'];
		$nodesByEnd = $lattice['nodesByEnd'];
		$bos = $lattice['bos'];
		$eos = $lattice['eos'];

		$queue = new \SplPriorityQueue();
		$queue->setExtractFlags(\SplPriorityQueue::EXTR_BOTH);
		$queue->insert(
			[
				'nodeId' => $eos,
				'backCost' => 0,
				'path' => [$eos],
			],
			-$bestCosts[$eos]
		);

		$results = [];
		$seenText = [];

		while (!$queue->isEmpty() && count($results) < $nbest) {
			$item = $queue->extract();
			$data = $item['data'];
			$nodeId = $data['nodeId'];

			if ($nodeId === $bos) {
				$candidate = $this->buildCandidate($data['path'], $nodes, $data['backCost']);
				if (!isset($seenText[$candidate['text']])) {
					$seenText[$candidate['text']] = true;
					$results[] = $candidate;
				}
				continue;
			}

			$startPos = $nodes[$nodeId]['start'];
			foreach ($nodesByEnd[$startPos] as $prevId) {
				if ($prevId === $eos) {
					continue;
				}

				$edgeCost = $this->getConnectionCost($nodes[$prevId]['right_id'], $nodes[$nodeId]['left_id']);
				$newBackCost = $data['backCost'] + $nodes[$nodeId]['cost'] + $edgeCost;
				$priority = $newBackCost + $bestCosts[$prevId];

				$newPath = $data['path'];
				$newPath[] = $prevId;

				$queue->insert(
					[
						'nodeId' => $prevId,
						'backCost' => $newBackCost,
						'path' => $newPath,
					],
					-$priority
				);
			}
		}

		return $results;
	}

	private function buildCandidate(array $path, array $nodes, int $totalCost): array
	{
		$ids = array_reverse($path);
		$text = '';
		$tokens = [];

		foreach ($ids as $id) {
			$node = $nodes[$id];
			if ($node['surface'] === '' && $node['reading'] === '') {
				continue;
			}

			$tokens[] = [
				'surface' => $node['surface'],
				'reading' => $node['reading'],
				'word_cost' => $node['word_cost'],
				'penalty' => $node['penalty'],
			];
			$text .= $node['surface'];
		}

		return [
			'text' => $text,
			'tokens' => $tokens,
			'cost' => $totalCost,
		];
	}

// 追加するプロパティ
	private ?ConnectionBinary $connectionBinary = null;

// getConnectionBinary() を追加
	private function getConnectionBinary(): ConnectionBinary
	{
		if ($this->connectionBinary === null) {
			$this->connectionBinary = new ConnectionBinary(dirname($this->dictFile));
		}
		return $this->connectionBinary;
	}

// loadConnectionCosts() を丸ごと置き換え
	private function loadConnectionCosts(array $lattice): void
	{
		// ConnectionBinary の初回アクセス時に自動ロードされるため何もしない
		$this->getConnectionBinary();
	}

// getConnectionCost() を置き換え
	private function getConnectionCost(int $rightId, int $leftId): int
	{
		return $this->getConnectionBinary()->getCost($rightId, $leftId);
	}

	private function resolveConnectionSize(int $reportedSize, int $lineCount): int
	{
		if ($reportedSize <= 0) {
			return 0;
		}

		$expected = $reportedSize * $reportedSize;
		if ($lineCount === $expected) {
			return $reportedSize;
		}

		$plusOne = ($reportedSize + 1) * ($reportedSize + 1);
		if ($lineCount === $plusOne) {
			return $reportedSize + 1;
		}

		$root = (int)floor(sqrt((float)$lineCount));
		if ($root > 0 && $root * $root === $lineCount) {
			return $root;
		}

		return $reportedSize;
	}

	private function collectConnectionIndices(array $lattice, int $size): array
	{
		$nodes = $lattice['nodes'];
		$nodesByStart = $lattice['nodesByStart'];
		$nodesByEnd = $lattice['nodesByEnd'];
		$len = $lattice['len'];
		$bos = $lattice['bos'];
		$eos = $lattice['eos'];

		$indices = [];

		for ($pos = 0; $pos <= $len; $pos++) {
			$prevNodes = $nodesByEnd[$pos];
			$nextNodes = $nodesByStart[$pos];

			foreach ($prevNodes as $prevId) {
				if ($prevId === $eos) {
					continue;
				}
				foreach ($nextNodes as $nextId) {
					if ($nextId === $bos) {
						continue;
					}
					if ($nextId === $eos && $pos !== $len) {
						continue;
					}
					if ($prevId === $bos && $nextId === $eos && $len > 0) {
						continue;
					}

					$rightId = $nodes[$prevId]['right_id'];
					$leftId = $nodes[$nextId]['left_id'];
					$index = $rightId * $size + $leftId;
					$indices[$index] = true;
				}
			}
		}

		return $indices;
	}
}
