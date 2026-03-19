<?php

declare(strict_types=1);

namespace kanakanjiconverter;

use Symfony\Component\Filesystem\Path;

/**
 * @internal
 */
final class KanaKanjiConverter
{
	private const INF = 1000000000;
	private const SAME_SURFACE_PENALTY = 1000;
	private const UNKNOWN_PENALTY = 10000;

	private string $dictFile;
	private string $connectionFile;

	public function __construct(string $dictPath)
	{
		$base = rtrim($dictPath, DIRECTORY_SEPARATOR);
		$this->dictFile = Path::join($base, 'dictionary00.txt');
		$this->connectionFile = Path::join($base, 'connection_single_column.txt');
	}

	/**
	 * $hiragana: 変換対象のひらがな文字列（UTF-8）
	 * $nbest: 取得する候補数
	 * @throws KanjiConverterTimeoutException
	 */
	public function convert(string $hiragana, int $nbest = 1): array
	{
		$this->startTimer();
		$nbest = max(1, min($nbest, 100)); // 上限は適宜調整可能

		$entriesByReading = $this->collectEntriesFromDictionaries($hiragana);

		$lattice = $this->buildLattice($hiragana, $entriesByReading);
		$this->loadConnectionCosts();

		$forward = $this->forwardDp($lattice);
		$candidates = $this->backwardAStar($lattice, $forward['costs'], $forward['prev'], $forward['prevPrev'], $nbest);

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
			$reading = (string) $reading;
			$readingLen = mb_strlen($reading, 'UTF-8');
			$offset = 0;

			while (($pos = mb_strpos($hiragana, $reading, $offset, 'UTF-8')) !== false) {
				$start = $pos;
				$end = $pos + $readingLen;

				foreach ($entries as $entry) {
					$penalty = ($entry['surface'] === $reading) ? self::SAME_SURFACE_PENALTY : 0;

					$nodes[$nextId] = [
						'id'        => $nextId,
						'start'     => $start,
						'end'       => $end,
						'reading'   => $reading,   // 正規化済みreadingをそのまま使う
						'surface'   => $entry['surface'],
						'left_id'   => $entry['left_id'],
						'right_id'  => $entry['right_id'],
						'word_cost' => $entry['word_cost'],
						'penalty'   => $penalty,
						'cost'      => $entry['word_cost'] + $penalty,
						// ユーザー辞書由来の pos/subpos を保持（なければ空文字）
						'pos'       => $entry['pos']       ?? '',
						'subpos'    => $entry['subpos']    ?? '',
						'pos_label' => $entry['pos_label'] ?? '',
					];

					$nodesByStart[$start][] = $nextId;
					$nodesByEnd[$end][]     = $nextId;
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
		$nodes        = $lattice['nodes'];
		$nodesByStart = $lattice['nodesByStart'];
		$nodesByEnd   = $lattice['nodesByEnd'];
		$len          = $lattice['len'];
		$bos          = $lattice['bos'];
		$eos          = $lattice['eos'];

		$count    = count($nodes);
		$costs    = array_fill(0, $count, self::INF);
		$prev     = array_fill(0, $count, -1);
		$prevPrev = array_fill(0, $count, -1);
		$costs[$bos] = 0;

		// right_id → posLabel のキャッシュ（同じIDを何度もgetPosしない）
		$posIndex     = $this->getPosIndex();
		$labelCache   = [];
		$labelOf = static function(int $id) use ($posIndex, &$labelCache): string {
			if ($id === 0) return 'BOS';
			return $labelCache[$id] ??= $posIndex->getPos($id)['label'];
		};

		// (rightId, leftId) → connectionCost のキャッシュ
		$connCache  = [];
		$connBinary = $this->getConnectionBinary();
		$getConn = function(int $r, int $l) use ($connBinary, $posIndex, &$connCache): int {
			$key = ($r << 16) | $l;
			if (!isset($connCache[$key])) {
				$base       = $connBinary->getCost($r, $l);
				$adjustment = $posIndex->getChainAdjustment($r, $l);
				$connCache[$key] = $base + $adjustment;
			}
			return $connCache[$key];
		};

		// (label1, label2, label3) → tripletAdj のキャッシュ
		$tripletCache = [];
		$quadCache    = [];

		for ($pos = 0; $pos <= $len; $pos++) {
			if ($this->timeout !== null) $this->checkTimeout(); // ← 追加（nullチェックでホットループ回避）

			$prevNodes = $nodesByEnd[$pos];
			$nextNodes = $nodesByStart[$pos];

			foreach ($prevNodes as $prevId) {
				if ($costs[$prevId] === self::INF) continue;
				if ($prevId === $eos) continue;

				$prevCost    = $costs[$prevId];
				$prevRightId = $nodes[$prevId]['right_id'];
				$prevPrevId  = $prev[$prevId];

				foreach ($nextNodes as $nextId) {
					if ($nextId === $bos) continue;
					if ($nextId === $eos && $pos !== $len) continue;
					if ($prevId === $bos && $nextId === $eos && $len > 0) continue;

					$nextLeftId = $nodes[$nextId]['left_id'];

					$edgeCost = $getConn($prevRightId, $nextLeftId);

					// 3連キャッシュ
					$tripletAdj = 0;
					if ($prevPrevId !== -1) {
						$k = $nodes[$prevPrevId]['right_id'] . ',' . $prevRightId . ',' . $nextLeftId;
						if (!isset($tripletCache[$k])) {
							$tripletCache[$k] = $posIndex->getTripletAdjustment(
								$nodes[$prevPrevId]['right_id'],
								$prevRightId,
								$nextLeftId
							);
						}
						$tripletAdj = $tripletCache[$k];
					}

					// 4連キャッシュ
					$quadrupletAdj = 0;
					if ($prevPrevId !== -1 && $prevPrev[$prevId] !== -1) {
						$prevPrevPrevId = $prevPrev[$prevId];
						$k = $nodes[$prevPrevPrevId]['right_id'] . ',' . $nodes[$prevPrevId]['right_id'] . ',' . $prevRightId . ',' . $nextLeftId;
						if (!isset($quadCache[$k])) {
							$quadCache[$k] = $posIndex->getQuadrupletAdjustment(
								$nodes[$prevPrevPrevId]['right_id'],
								$nodes[$prevPrevId]['right_id'],
								$prevRightId,
								$nextLeftId
							);
						}
						$quadrupletAdj = $quadCache[$k];
					}

					$cost = $prevCost + $edgeCost + $nodes[$nextId]['cost'] + $tripletAdj + $quadrupletAdj;

					if ($nodes[$prevId]['left_id'] === 0 && $nextLeftId === 0) {
						$cost += 8000;
					}

					if ($cost < $costs[$nextId]) {
						$costs[$nextId] = $cost;
						$prev[$nextId]     = $prevId;
						$prevPrev[$nextId] = $prevPrevId;
					}
				}
			}
		}

		return ['costs' => $costs, 'prev' => $prev, 'prevPrev' => $prevPrev];
	}

	private function backwardAStar(array $lattice, array $bestCosts, array $prevArray, array $prevPrevArray, int $nbest): array
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

				// 基本的な接続コスト（2連）
				$edgeCost = $this->getConnectionCost($nodes[$prevId]['right_id'], $nodes[$nodeId]['left_id']);

				$newBackCost = $data['backCost'] + $nodes[$nodeId]['cost'] + $edgeCost; // tripletAdj, quadrupletAdj を削除
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
// プロパティ追加
	private ?PosIndex $posIndex = null;

	private function getPosIndex(): PosIndex
	{
		if ($this->posIndex === null) {
			$idDefPath = dirname($this->dictFile) . DIRECTORY_SEPARATOR . 'id.def';
			$this->posIndex = new PosIndex($idDefPath);
		}
		return $this->posIndex;
	}

// getConnectionCost() を差し替え
// 接続コスト＋品詞連鎖補正を合算
	private function getConnectionCost(int $rightId, int $leftId): int
	{
		$base       = $this->getConnectionBinary()->getCost($rightId, $leftId);
		$adjustment = $this->getPosIndex()->getChainAdjustment($rightId, $leftId);
		return $base + $adjustment;
	}

// buildCandidate() の tokens に品詞情報を追加
// KanaKanjiConverter.php
// buildCandidate() を以下に差し替え

	private function buildCandidate(array $path, array $nodes, int $totalCost): array
	{
		$ids      = array_reverse($path);
		$text     = '';
		$tokens   = [];
		$posIndex = $this->getPosIndex();

		foreach ($ids as $id) {
			$node = $nodes[$id];
			if ($node['surface'] === '' && $node['reading'] === '') {
				continue;
			}

			// ユーザー辞書由来のノードは pos/subpos が直接埋め込まれている
			// left_id=0 は BOS/EOS なので PosIndex を通すと BOS/EOS になってしまう
			if (isset($node['pos']) && $node['pos'] !== '') {
				$pos      = $node['pos'];
				$subpos   = $node['subpos']   ?? '*';
				$posLabel = $node['pos_label'] ?? ($pos . '-' . $subpos);
			} else {
				$posInfo  = $posIndex->getPos($node['left_id']);
				$pos      = $posInfo['pos'];
				$subpos   = $posInfo['subpos'];
				$posLabel = $posInfo['label'];
			}

			$tokens[] = [
				'surface'   => $node['surface'],
				'reading'   => $node['reading'],
				'word_cost' => $node['word_cost'],
				'penalty'   => $node['penalty'],
				'pos'       => $pos,
				'subpos'    => $subpos,
				'pos_label' => $posLabel,
			];
			$text .= $node['surface'];
		}

		return [
			'text'   => $text,
			'tokens' => $tokens,
			'cost'   => $totalCost,
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
	private function loadConnectionCosts(): void
	{
		// ConnectionBinary の初回アクセス時に自動ロードされるため何もしない
		$this->getConnectionBinary();
	}


// KanaKanjiConverter.php への追加メソッド（既存クラスの末尾に追記）

// ----------------------------------------------------------------
// ユーザーエントリを受け取る変換エントリポイント
// ----------------------------------------------------------------

	// KanaKanjiConverter.php の末尾クラス内に追記
	// ----------------------------------------------------------------
// KanaKanjiConverter.php への追記
// 既存の convert() メソッドの直後に追加する
// ----------------------------------------------------------------

	/**
	 * ユーザー辞書エントリをマージして変換する
	 *
	 * @param string                     $hiragana       変換対象のひらがな
	 * @param array<string, list<array>> $userEntries    読み => [エントリ配列] 形式
	 * @param bool                       $useBuiltinDict 内蔵辞書を使うか
	 * @param int                        $nbest          候補数
	 *
	 * @throws KanjiConverterTimeoutException
	 */
	public function convertWithUserEntries(
		string $hiragana,
		array  $userEntries,
		bool   $useBuiltinDict,
		int    $nbest = 1
	): array {
		$this->startTimer();
		$nbest = max(1, min($nbest, 100));

		if ($useBuiltinDict) {
			$builtinEntries   = $this->collectEntriesFromDictionaries($hiragana);
			$entriesByReading = $this->mergeEntries($builtinEntries, $userEntries);
		} else {
			$entriesByReading = $userEntries;
		}

		$lattice = $this->buildLattice($hiragana, $entriesByReading);
		$this->loadConnectionCosts();

		$forward    = $this->forwardDp($lattice);
		$candidates = $this->backwardAStar($lattice, $forward['costs'], $forward['prev'], $forward['prevPrev'], $nbest);

		$best = $candidates[0] ?? [
			'text'   => $hiragana,
			'tokens' => [[
				'surface'   => $hiragana,
				'reading'   => $hiragana,
				'word_cost' => 0,
				'penalty'   => 0,
				'pos'       => '名詞',
				'subpos'    => '一般',
				'pos_label' => '名詞-一般',
			]],
			'cost' => 0,
		];

		return ['best' => $best, 'candidates' => $candidates];
	}

	/**
	 * 内蔵辞書エントリとユーザーエントリをマージする
	 * ユーザーエントリは先頭に挿入して優先度を上げる
	 *
	 * @param array<string, list<array>> $builtin
	 * @param array<string, list<array>> $user
	 * @return array<string, list<array>>
	 */
	private function mergeEntries(array $builtin, array $user): array
	{
		$merged = $builtin;
		foreach ($user as $reading => $entries) {
			if (!isset($merged[$reading])) {
				$merged[$reading] = [];
			}
			// ユーザーエントリを先頭に挿入（低コスト = 高優先）
			array_unshift($merged[$reading], ...$entries);
		}
		return $merged;
	}

	private ?float $timeout = null;       // タイムアウト秒数（nullで無効）
	private float  $startTime = 0.0;

	public function setTimeout(?float $seconds): void
	{
		$this->timeout = $seconds;
	}

	private function startTimer(): void
	{
		if ($this->timeout !== null) {
			$this->startTime = microtime(true);
		}
	}

	/** @throws KanjiConverterTimeoutException */
	private function checkTimeout(): void
	{
		// $this->timeout が null のときはこのメソッド自体呼ばない運用
		if (microtime(true) - $this->startTime >= $this->timeout) {
			throw new KanjiConverterTimeoutException(
				"変換がタイムアウトしました（{$this->timeout}秒）"
			);
		}
	}
}
