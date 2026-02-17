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
		//一部エッジケースでだめ
		'助詞-格助詞' => [
			'名詞-形容動詞語幹' => +1000,
			'動詞-自立' => -200,  // -400から-200に弱める（過剰な優遇を避ける）
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
			'名詞-数' => -800,
		],
		'漢数字' => [
			'名詞-接尾'       => -800,
			'名詞-一般'       => +800,
			'名詞-固有名詞' => +1500,
			'名詞-固有名詞-人名' => +4000,
			'名詞-接尾-助数詞' => -2000,
			'漢数字' => -800,
		],
		//丁寧に~
		'名詞-形容動詞語幹' => [
			'漢数字' => 10000,
			'名詞-数' => 10000,
		],
		'助動詞' => [
			'助詞-接続助詞' => -2000,
		],
		'助詞-接続助詞' => [
			'助詞-終助詞' => -2000,
		],
		'名詞-一般' => [
			'助詞-格助詞' => -200,
			//'名詞-数' => 100000// 数十~
		],
		'動詞-自立' => [
			'助動詞' => -500,
		],
		'助詞-連体化' => [
			'名詞-一般' => -200,  // 「の川」「の印」等を優遇
			'名詞-接尾' => +1000, // 「の皮(接尾)」をペナルティ
		],
	];

	// 3連品詞連鎖パターン：[品詞1][品詞2][品詞3] => 加算コスト
	// 例：「経験を生かす」「手を振る」などの自然な組み合わせを優遇
	private const CHAIN_TRIPLET = [
		'BOS' => [
			'名詞-一般' => [
				'助詞-格助詞' => -400,  // 「○○を」で始まる文
				'助詞-連体化' => -300,  // 「○○の」で始まる文
			],
			'名詞-副詞可能' => [
				'名詞-一般' => -500,     // 「今日天気」など
				'助詞-格助詞' => -300,   // 「今日を」など
			],
		],
		'名詞-一般' => [
			//誤変換が増える
			'助詞-格助詞' => [
				'動詞-自立' => -600,     // 「手を振る」「経験を生かす」などの基本パターン
				'名詞-副詞可能' => +1500,  // 不自然な組み合わせ
				'名詞-サ変接続' => -400,  // 「会議を開催」など//
			],
			'助詞-連体化' => [
				'名詞-一般' => -300,      // 「時の流れ」など
				'動詞-自立' => +1000,     // 「時の振る」など不自然
			],
		],
		'名詞-サ変接続' => [
			'助詞-格助詞' => [
				'動詞-自立' => 0,        // +500から0に戻す（ニュートラル）
				'名詞-一般' => +800,
			],
		],
		'動詞-自立' => [
			'助詞-接続助詞' => [
				'動詞-自立' => -400,      // 「行って見る」など
				'名詞-一般' => -200,      // 「行って場所」など
			],
			'助詞-格助詞' => [
				'動詞-自立' => -300,      // 「言って行く」など
			],
		],
		'助詞-格助詞' => [
			'動詞-自立' => [
				'助詞-接続助詞' => -500,  // 「を振って」「を言って」など
				'助動詞' => -400,         // 「を振った」「を言った」など
			],
		],
		// 新規追加: 特定の動詞との相性
		'助詞-連体化' => [
			'名詞-サ変接続' => -300,    // 「の設計」「の医療」等を優遇
		],
		'名詞-非自立' => [
			'助詞-格助詞' => [
				'動詞-自立' => -600,  // 「ことを進める」等を優遇
				'名詞-一般' => +500,   // 「ことを勧め」等をペナルティ
			],
		],

		// 感動詞の後に動詞が来るのを防ぐ
		'感動詞' => [
			'動詞-自立' => +2000,  // 「さよなら行った」を強くペナルティ
			'助詞-格助詞' => -300, // 「さよならを」は許容
		],
	];

	// 4連品詞連鎖パターン：[品詞1][品詞2][品詞3][品詞4] => 加算コスト
	// より長い文脈での自然な組み合わせを優遇
	private const CHAIN_QUADRUPLET = [
		'BOS' => [
			'名詞-一般' => [
				'助詞-格助詞' => [
					'動詞-自立' => -500,    // 「手を振る」などの文頭パターン
					'名詞-サ変接続' => -400, // 「会議を開催」など
				],
			],
		],
		'名詞-一般' => [
			'助詞-格助詞' => [
				'動詞-自立' => [
					'助詞-接続助詞' => -300,  // -600から-300に弱める
					'助動詞' => -200,         // -500から-200に弱める
					'名詞-非自立' => -400,  // 「写真を撮るとき」を優遇
				],
			],
		],
		'名詞-サ変接続' => [
			'助詞-格助詞' => [
				'動詞-自立' => [
					'助動詞' => +1000,        // 「サヨナラを行った」等を強くペナルティ
					'助詞-接続助詞' => +1000, // 「サヨナラを行って」等も
				],
			],
		],
		'助詞-連体化' => [  // 「の」
			'名詞-一般' => [
				'助詞-連体化' => [  // 「の」
					'名詞-一般' => -100,  // 「川のせせらぎ」を優遇
				],
			],
		],

		'助詞-格助詞' => [  // 「から」
			'名詞-一般' => [
				'助詞-格助詞' => [  // 「を」
					'動詞-自立' => -200,  // 「印を押す」を優遇
				],
			],
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
	 * 品詞連鎖ボーナス/ペナルティを返す（2連）
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
	 * 3連品詞連鎖のボーナス/ペナルティを返す
	 * @param int $pos1RightId  1つ目のノードのright_id（0=BOS）
	 * @param int $pos2RightId  2つ目のノードのright_id
	 * @param int $pos3LeftId   3つ目のノードのleft_id
	 */
	public function getTripletAdjustment(int $pos1RightId, int $pos2RightId, int $pos3LeftId): int
	{
		$pos1Label = $pos1RightId === 0 ? 'BOS' : $this->getPos($pos1RightId)['label'];
		$pos2Label = $this->getPos($pos2RightId)['label'];
		$pos3Label = $this->getPos($pos3LeftId)['label'];

		// 完全一致で探す
		if (isset(self::CHAIN_TRIPLET[$pos1Label][$pos2Label][$pos3Label])) {
			return self::CHAIN_TRIPLET[$pos1Label][$pos2Label][$pos3Label];
		}

		// 部分一致（pos1のみ簡略化）
		$pos1 = $pos1RightId === 0 ? 'BOS' : $this->getPos($pos1RightId)['pos'];
		if (isset(self::CHAIN_TRIPLET[$pos1][$pos2Label][$pos3Label])) {
			return self::CHAIN_TRIPLET[$pos1][$pos2Label][$pos3Label];
		}

		return 0;
	}

	/**
	 * 4連品詞連鎖のボーナス/ペナルティを返す
	 * @param int $pos1RightId  1つ目のノードのright_id（0=BOS）
	 * @param int $pos2RightId  2つ目のノードのright_id
	 * @param int $pos3RightId  3つ目のノードのright_id
	 * @param int $pos4LeftId   4つ目のノードのleft_id
	 */
	public function getQuadrupletAdjustment(
		int $pos1RightId,
		int $pos2RightId,
		int $pos3RightId,
		int $pos4LeftId
	): int {
		$pos1Label = $pos1RightId === 0 ? 'BOS' : $this->getPos($pos1RightId)['label'];
		$pos2Label = $this->getPos($pos2RightId)['label'];
		$pos3Label = $this->getPos($pos3RightId)['label'];
		$pos4Label = $this->getPos($pos4LeftId)['label'];

		// 完全一致で探す
		if (isset(self::CHAIN_QUADRUPLET[$pos1Label][$pos2Label][$pos3Label][$pos4Label])) {
			return self::CHAIN_QUADRUPLET[$pos1Label][$pos2Label][$pos3Label][$pos4Label];
		}

		// 部分一致（pos1のみ簡略化）
		$pos1 = $pos1RightId === 0 ? 'BOS' : $this->getPos($pos1RightId)['pos'];
		if (isset(self::CHAIN_QUADRUPLET[$pos1][$pos2Label][$pos3Label][$pos4Label])) {
			return self::CHAIN_QUADRUPLET[$pos1][$pos2Label][$pos3Label][$pos4Label];
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