<?php
// UserDictionary.php

declare(strict_types=1);

namespace kanakanjiconverter;

/**
 * 外部ユーザー辞書
 *
 * モード定数:
 *   MODE_KANA      (0) ローマ字→かな と かな→漢字の両フェーズで辞書を適用
 *   MODE_NO_CONVERT(1) reading 一致 → そのまま出力（漢字変換しない）
 *   MODE_REPLACE   (2) reading 一致 → surface に置換出力
 *   MODE_KANA_ALT  (3) ユーザー辞書の独自よみがなルール（独自かな変換テーブル）
 *   MODE_MERGE     (4) 複数ユーザー辞書を統合して1つとして扱う（静的ファクトリ用）
 *   MODE_SERVER    (5) サーバー側辞書（MODE_MERGE 相当・出所区別用）
 *
 * エントリ配列形式:
 *   [
 *     'reading'   => 'だあれ',            // ひらがな（よみ）必須
 *     'surface'   => '誰',               // 変換後表記（省略時 = reading）
 *     'mode'      => UserDictionary::MODE_REPLACE,
 *     'word_cost' => -5000,              // 任意。省略時 DEFAULT_WORD_COST
 *     'left_id'   => 0,                  // 任意
 *     'right_id'  => 0,                  // 任意
 *     'pos'       => '名詞',             // 任意
 *     'subpos'    => '一般',             // 任意
 *     // MODE_KANA_ALT 専用
 *     'romaji'    => 'server',           // ローマ字入力キー（reading がかな出力）
 *   ]
 */
final class UserDictionary
{
	public const MODE_REPLACE    = 2;
	public const MODE_MERGE      = 4;
	public const MODE_SERVER     = 5;

	/** word_cost 未指定時のデフォルト */
	public const DEFAULT_WORD_COST = -3000;

	private const VALID_MODES = [
		self::MODE_REPLACE,
		self::MODE_MERGE,
		self::MODE_SERVER,
	];

	/** @var list<array<string,mixed>> */
	private array $entries = [];

	// ----------------------------------------------------------------
	// エントリ追加
	// ----------------------------------------------------------------

	/**
	 * エントリを1件追加する
	 *
	 * @param array<string,mixed> $entry
	 */
	public function add(array $entry): void
	{
		if(!isset($entry['reading'])){
			throw new \InvalidArgumentException('UserDictionary is broken');
		}
		$reading = $this->sanitize((string)($entry['reading'] ?? ''));
		if ($reading === '') {
			return;
		}

		$mode = (int)($entry['mode'] ?? self::MODE_REPLACE);
		if (!in_array($mode, self::VALID_MODES, true)) {
			$mode = self::MODE_REPLACE;
		}

		$surface = $this->sanitize((string)($entry['surface'] ?? $reading));

		$normalized = [
			'reading'   => $reading,
			'surface'   => $surface,
			'mode'      => $mode,
			'word_cost' => (int)($entry['word_cost'] ?? self::DEFAULT_WORD_COST),
			'left_id'   => (int)($entry['left_id']  ?? 0),
			'right_id'  => (int)($entry['right_id'] ?? 0),
			'pos'       => $this->sanitize((string)($entry['pos']    ?? '名詞')),
			'subpos'    => $this->sanitize((string)($entry['subpos'] ?? '一般')),
		];
		$this->entries[] = $normalized;
	}

	/**
	 * エントリを複数まとめて追加する
	 *
	 * @param list<array<string,mixed>> $entries
	 */
	public function addAll(array $entries): void
	{
		foreach ($entries as $entry) {
			$this->add($entry);
		}
	}

	// ----------------------------------------------------------------
	// 検索・取得
	// ----------------------------------------------------------------

	/** @return list<array<string,mixed>> */
	public function getAll(): array
	{
		return $this->entries;
	}

	/**
	 * 指定モードのエントリのみ返す
	 *
	 * @return list<array<string,mixed>>
	 */
	public function getByMode(int $mode): array
	{
		return array_values(
			array_filter($this->entries, static fn(array $e): bool => $e['mode'] === $mode)
		);
	}

	/**
	 * 漢字変換フェーズ用エントリ（MODE_KANA / MODE_MERGE / MODE_SERVER）を
	 * KanaKanjiConverter 形式で返す
	 *
	 * @param int[] $modes  対象モードの配列
	 * @return array<string, list<array<string,mixed>>>
	 */
	public function getConverterEntries(array $modes): array
	{
		$result = [];
		foreach ($this->entries as $entry) {
			if (!in_array($entry['mode'], $modes, true)) {
				continue;
			}
			$result[$entry['reading']][] = $this->toConverterEntry($entry);
		}
		return $result;
	}

	// ----------------------------------------------------------------
	// エントリ削除
	// ----------------------------------------------------------------

	public function clear(): void
	{
		$this->entries = [];
	}

	// ----------------------------------------------------------------
	// 静的ファクトリ: MODE_MERGE（複数辞書の統合）
	// ----------------------------------------------------------------

	/**
	 * 複数の UserDictionary を1つに統合して返す
	 *
	 * 同一 (reading + mode) のエントリが重複する場合は word_cost の低い方を優先する。
	 *
	 * @param UserDictionary ...$dicts
	 * @return self
	 */
	public static function merge(UserDictionary ...$dicts): self
	{
		$merged = new self();

		// reading + mode をキーに重複排除
		/** @var array<string, array<string,mixed>> */
		$seen = [];

		foreach ($dicts as $dict) {
			foreach ($dict->getAll() as $entry) {
				$key = $entry['reading'] . "\x00" . $entry['mode'] . "\x00" . $entry['surface'];
				if (!isset($seen[$key]) || $entry['word_cost'] < $seen[$key]['word_cost']) {
					$seen[$key] = $entry;
				}
			}
		}

		foreach ($seen as $entry) {
			$merged->entries[] = $entry;
		}

		return $merged;
	}

	// ----------------------------------------------------------------
	// 内部ヘルパー
	// ----------------------------------------------------------------

	/**
	 * KanaKanjiConverter::buildLattice() が期待するエントリ形式に変換
	 *
	 * @param array<string,mixed> $entry
	 * @return array<string,mixed>
	 */
	private function toConverterEntry(array $entry): array
	{
		return [
			'surface'   => $entry['surface'],
			'left_id'   => $entry['left_id'],
			'right_id'  => $entry['right_id'],
			'word_cost' => $entry['word_cost'],
			'pos'       => $entry['pos'],
			'subpos'    => $entry['subpos'],
			'pos_label' => $entry['pos'] . '-' . $entry['subpos'],
		];
	}

	/**
	 * 文字列サニタイズ: NUL・C0制御文字（タブ・改行を除く）を除去
	 */
	private function sanitize(string $value): string
	{
		return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? $value;
	}

	// UserDictionary.php に追加
// ユーザー辞書のエントリをプレフィックス照合用にインデックス化する

	/**
	 * プレフィックスインデックス（遅延構築）
	 * キー: 読みの先頭1文字、値: その文字で始まる全エントリ
	 * buildLattice側でのループ削減に使う
	 *
	 * @var array<string, list<array<string,mixed>>>|null
	 */
	private ?array $prefixIndex = null;

	/**
	 * エントリ追加時にインデックスを無効化する
	 * （add() / addAll() / clear() の末尾で呼ぶ）
	 */
	private function invalidateIndex(): void
	{
		$this->prefixIndex = null;
	}

	/**
	 * 指定した開始位置・文字列に対してプレフィックスマッチするエントリを返す
	 * KanaKanjiConverter::buildLattice() から呼ばれる想定
	 *
	 * @param  string   $hiragana   入力ひらがな全体
	 * @param  int      $pos        開始位置（文字インデックス）
	 * @param  int[]    $modes      対象モード
	 * @return list<array{reading:string, end:int, entries:list<array>}>
	 */
	public function matchAt(string $hiragana, int $pos, array $modes): array
	{
		$this->buildPrefixIndexIfNeeded($modes);

		$len     = mb_strlen($hiragana, 'UTF-8');
		$firstCh = mb_substr($hiragana, $pos, 1, 'UTF-8');
		$results = [];

		if (!isset($this->prefixIndex[$firstCh])) {
			return [];
		}

		foreach ($this->prefixIndex[$firstCh] as $entry) {
			if (!in_array($entry['mode'], $modes, true)) {
				continue;
			}
			$readingLen = mb_strlen($entry['reading'], 'UTF-8');
			if ($pos + $readingLen > $len) {
				continue;
			}
			$substr = mb_substr($hiragana, $pos, $readingLen, 'UTF-8');
			if ($substr === $entry['reading']) {
				$results[] = [
					'reading' => $entry['reading'],
					'end'     => $pos + $readingLen,
					'entries' => [$this->toConverterEntry($entry)],
				];
			}
		}

		return $results;
	}

	private function buildPrefixIndexIfNeeded(array $modes): void
	{
		if ($this->prefixIndex !== null) {
			return;
		}
		$this->prefixIndex = [];
		foreach ($this->entries as $entry) {
			if ($entry['reading'] === '') {
				continue;
			}
			$firstCh = mb_substr($entry['reading'], 0, 1, 'UTF-8');
			$this->prefixIndex[$firstCh][] = $entry;
		}
	}

	// add() / addAll() / clear() に invalidateIndex() を追記
	public function addChecked(array $entry): void
	{
		$this->add($entry);
		$this->invalidateIndex();
	}
}