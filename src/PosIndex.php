<?php

declare(strict_types=1);

namespace kanakanjiconverter;

/**
 * id.def をロードして left_id/right_id → 品詞情報に変換
 * 品詞連鎖ルールによる接続コスト補正も担う
 *
 * @internal
 */
final class PosIndex
{
	/** @var array<int, array{pos: string, subpos: string, label: string}> */
	private array $index = [];

	// 品詞連鎖ペナルティ：[前品詞カテゴリ][後品詞カテゴリ] => 加算コスト
	// 負値で有利、正値で不利
	private const CHAIN_BONUS = [
		'BOS' => [
			'名詞-副詞可能'   => -500,  // 昨日・今日・明日・去年 等（修正）
			'名詞-一般'       => -200,
			'名詞-固有名詞'   => -200,
			'副詞-一般'       => -300,
			'名詞-サ変接続'   => 0,
			'名詞-接尾' => +5000,
		],
		'助詞' => [
			'名詞-副詞可能'   => -300,
			'名詞-一般'       => -100,
			'名詞-固有名詞'   => -100,
		],
		'助詞-格助詞' => [
			//'動詞-自立' => -500,
			'名詞-形容動詞語幹' => +200,
		],
		'名詞-接尾' => [
			'助詞-連体化' => +3000,
		],
		'名詞-副詞可能' => [
			'名詞-一般' => +400,
		],
		'名詞-数' => [
			'名詞-接尾'       => -800,
			'名詞-一般'       => +800,
			'名詞-固有名詞' => +1500,
			'名詞-固有名詞-人名' => +4000,
			'名詞-接尾-助数詞' => -2000,
		],
	];

	public function __construct(string $idDefPath)
	{
		if (!is_file($idDefPath)) {
			return;
		}
		$lines = file($idDefPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		if ($lines === false) {
			return;
		}

		foreach ($lines as $line) {
			// フォーマット: "0 BOS/EOS,*,*,*,*,*,*"
			$spacePos = strpos($line, ' ');
			if ($spacePos === false) {
				continue;
			}
			$id    = (int)substr($line, 0, $spacePos);
			$rest  = substr($line, $spacePos + 1);
			$parts = explode(',', $rest);

			$pos    = $parts[0] ?? '*';
			$subpos = $parts[1] ?? '*';

			$this->index[$id] = [
				'pos'    => $pos,
				'subpos' => $subpos,
				'label'  => $subpos === '*' ? $pos : "{$pos}-{$subpos}",
			];
		}
	}

	/**
	 * IDから品詞情報を取得
	 * @return array{pos: string, subpos: string, label: string}
	 */
	public function getPos(int $id): array
	{
		return $this->index[$id] ?? ['pos' => '不明', 'subpos' => '*', 'label' => '不明'];
	}

	/**
	 * 品詞連鎖ボーナス/ペナルティを返す
	 * @param int $prevRightId  前ノードのright_id（0=BOS）
	 * @param int $nextLeftId   次ノードのleft_id
	 */
	public function getChainAdjustment(int $prevRightId, int $nextLeftId): int
	{
		$prevLabel = $prevRightId === 0 ? 'BOS' : $this->getPos($prevRightId)['label'];
		$nextLabel = $this->getPos($nextLeftId)['label'];

		// 完全一致で探す
		if (isset(self::CHAIN_BONUS[$prevLabel][$nextLabel])) {
			return self::CHAIN_BONUS[$prevLabel][$nextLabel];
		}

		// 前品詞のprefixで探す（例："名詞-副詞的" → "名詞"）
		$prevPos = $prevRightId === 0 ? 'BOS' : $this->getPos($prevRightId)['pos'];
		if (isset(self::CHAIN_BONUS[$prevPos][$nextLabel])) {
			return self::CHAIN_BONUS[$prevPos][$nextLabel];
		}

		return 0;
	}

	/**
	 * インデックス全体を返す（デバッグ用）
	 */
	public function getAll(): array
	{
		return $this->index;
	}

	public function isLoaded(): bool
	{
		return count($this->index) > 0;
	}
}