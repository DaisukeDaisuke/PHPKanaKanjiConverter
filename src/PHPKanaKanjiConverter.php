<?php
declare(strict_types=1);

namespace kanakanjiconverter;

use Symfony\Component\Filesystem\Path;

final class PHPKanaKanjiConverter
{
	private ConvertibleRomaji $romaji;
	private KanaKanjiConverter $kannziconverter;

	/** @var UserDictionary[] */
	private array $userDicts = [];

	public function __construct()
	{
		$oss = Path::join(__DIR__ , 'dictionary_oss');
		$dictDir = realpath($oss) ?: (__DIR__ . '/dictionary_oss');
		$this->romaji = new ConvertibleRomaji(Path::join($dictDir , "map.json"));
		$this->kannziconverter = new KanaKanjiConverter($dictDir);

		SystemDictionary::apply($this);
	}

	// ----------------------------------------------------------------
	// ユーザー辞書管理
	// ----------------------------------------------------------------

	public function registerUserDict(string $name, UserDictionary $dict): void
	{
		$this->userDicts[$name] = $dict;
	}

	public function getUserDict(string $name): ?UserDictionary
	{
		return $this->userDicts[$name] ?? null;
	}

	public function removeUserDict(string $name): void
	{
		unset($this->userDicts[$name]);
	}

	// ----------------------------------------------------------------
	// 変換本体
	// ----------------------------------------------------------------

	/**
	 * phpstormなどの補完で有益なため消さないこと
	 * @return array{original: string, kana: string, best: array{text: string, cost: int,tokens: list<array{surface: string, reading: string, word_cost: int, penalty: int, pos: string, subpos: string, pos_label: string}>}, candidates: list<array{text: string, cost: int, tokens: list<array{surface: string, reading: string, word_cost: int, penalty: int, pos: string, subpos: string, pos_label: string}>>}}
	 */
	public function convert(string $input, bool $removeIllegalFlag = false, int $numofbest = 3): array
	{
		// Step1: ローマ字→かな
		$kana = $this->applyKanaMode($input, $removeIllegalFlag);

		// Step2: MODE_NO_CONVERT / MODE_REPLACE の全体一致チェック
		//        （MODE_SERVER / MODE_MERGE はここでは処理しない）
		$earlyResult = $this->applyEarlyModes($kana);
		if ($earlyResult !== null) {
			return $earlyResult + ['original' => $input, 'kana' => $kana];
		}

		// Step3: 漢字変換 --- ユーザーエントリをマージ ---
		// MODE_KANA_ALT / MODE_MERGE / MODE_SERVER をまとめて収集
		$mergeEntries  = $this->collectEntries(UserDictionary::MODE_MERGE);
		$serverEntries = $this->collectEntries(UserDictionary::MODE_SERVER);

		if((count($mergeEntries) + count($serverEntries)) !== 0){
			// MERGE / SERVER: 内蔵辞書 + ユーザー辞書でマージ変換
			$userEntries = array_merge($mergeEntries, $serverEntries);
			$result = $this->kannziconverter->convertWithUserEntries(
				$kana,
				$userEntries,
				true,
				$numofbest
			);
		} else {
			// ユーザー辞書なし: 通常変換
			$result = $this->kannziconverter->convert($kana, $numofbest);
		}

		$result['original'] = $input;
		$result['kana']     = $kana;
		return $result;
	}

	// ----------------------------------------------------------------
	// 既存メソッド維持
	// ----------------------------------------------------------------

	public function isValid(array $result): bool
	{
		if (!isset($result['best']['tokens'])) {
			return false;
		}
		foreach ($result['best']['tokens'] as $t) {
			if (!isset($t['pos']) || !isset($t['subpos'])) {
				return false;
			}
			if ($t['pos'] === '名詞' && $t['subpos'] !== '非自立') {
				return true;
			}
			if (in_array($t['pos'], ['動詞', '形容詞'])) {
				return true;
			}
		}
		return false;
	}

	public function hasBOS(array $result): bool
	{
		if (!isset($result['best']['tokens'])) {
			return true;
		}
		foreach ($result['best']['tokens'] as $t) {
			if (!isset($t['pos'])) {
				return true;
			}
			if ($t['pos'] === 'BOS/EOS') {
				return true;
			}
		}
		return false;
	}

	public function getRomajiConverter(): ConvertibleRomaji
	{
		return $this->romaji;
	}

	// ----------------------------------------------------------------
	// 内部ヘルパー
	// ----------------------------------------------------------------

	private function applyKanaMode(string $input, bool $removeIllegalFlag): string
	{
		$rules = $this->buildPreprocessRules();
		if ($rules === []) {
			return $this->romaji->toHiragana($input, $removeIllegalFlag);
		}

		$lower    = mb_strtolower($input, 'UTF-8');
		$totalLen = strlen($lower);
		$result   = '';
		$offset   = 0;

		while ($offset < $totalLen) {
			// 全ルールで最も手前に現れる位置を strpos で探す
			$bestPos = PHP_INT_MAX;
			foreach ($rules as $rule) {
				$p = strpos($lower, $rule['lower'], $offset);
				if ($p !== false && $p < $bestPos) {
					$bestPos = $p;
				}
			}

			if ($bestPos === PHP_INT_MAX) {
				// 残り全部 toHiragana へ
				$result .= $this->romaji->toHiragana(substr($input, $offset), $removeIllegalFlag);
				break;
			}

			// bestPos で長い順にマッチを試す（rulesは長い順ソート済み）
			// ※ 競合覚悟モード: 末尾子音+直後英字チェックを行わず最長一致で即採用
			$matchedRule  = null;
			$matchedAfter = 0;
			foreach ($rules as $rule) {
				if (strpos($lower, $rule['lower'], $bestPos) !== $bestPos) {
					continue;
				}
				$matchedRule  = $rule;
				$matchedAfter = $bestPos + strlen($rule['lower']);
				break;
			}

			if ($matchedRule === null) {
				// この位置にマッチするルールが一切なかった → 1バイト進めて再探索
				$result .= $this->romaji->toHiragana(
					substr($input, $offset, $bestPos - $offset + 1),
					$removeIllegalFlag
				);
				$offset = $bestPos + 1;
				continue;
			}

			// bestPos より前の pending を flush
			if ($bestPos > $offset) {
				$result .= $this->romaji->toHiragana(
					substr($input, $offset, $bestPos - $offset),
					$removeIllegalFlag
				);
			}

			$result .= $matchedRule['surface'];
			$offset  = $matchedAfter;
		}

		return $result;
	}

	/**
	 * 指定モードのエントリを全辞書から収集し
	 * reading をひらがなに正規化した上で KanaKanjiConverter が期待する形式に変換する
	 *
	 * @return array<string, list<array>>
	 */
	private function collectEntries(int $mode): array
	{
		$result = [];
		foreach ($this->userDicts as $dict) {
			foreach ($dict->getByMode($mode) as $entry) {
				$reading = $this->normalizeReading($entry['reading']);
				if ($reading === '') {
					continue;
				}
				$result[$reading][] = [
					'surface'   => $entry['surface'],
					'left_id'   => $entry['left_id'],
					'right_id'  => $entry['right_id'],
					'word_cost' => $entry['word_cost'],
					// pos/subpos をノードに直接埋め込む
					// buildCandidate() でこれを見てPosIndexをスキップする
					'pos'       => $entry['pos'],
					'subpos'    => $entry['subpos'],
					'pos_label' => $entry['pos'] . '-' . $entry['subpos'],
				];
			}
		}
		return $result;
	}

	/**
	 * applyEarlyModes() も reading を正規化してから比較する
	 * MODE_NO_CONVERT / MODE_REPLACE の全体完全一致のみ処理する
	 */
	private function applyEarlyModes(string $kana): ?array
	{
		foreach ($this->userDicts as $dict) {
			// getAll() で全エントリを走査し、読み正規化して比較
			foreach ($dict->getAll() as $entry) {
				if ($entry['mode'] !== UserDictionary::MODE_REPLACE) {
					continue;
				}
				$normalizedReading = $this->normalizeReading($entry['reading']);
				if ($normalizedReading !== $kana) {
					continue;
				}
			}
		}
		return null;
	}


	/**
	 * reading をひらがなに正規化する
	 *
	 * - すでにひらがな/カタカナ/漢字混じりならそのまま返す
	 * - ASCII を含む場合は toHiragana() を通す（removeIllegalFlag=true で英字残滓を除去）
	 * - 空文字になった場合は元の値を返す（登録ミス防止）
	 */
	private function normalizeReading(string $reading): string
	{
		// ASCII 文字が含まれていれば toHiragana() で変換
		if (preg_match('/[A-Za-z]/u', $reading)) {
			$converted = $this->romaji->toHiragana($reading, false);
			// 変換結果が空でなければ採用
			return $converted !== '' ? $converted : $reading;
		}
		return $reading;
	}

	/**
	 * 全辞書から {lower, surface, len} のみ抽出し長い順にソート
	 */
	private function buildPreprocessRules(): array
	{
		$rules = [];
		$seen  = [];

		foreach ($this->userDicts as $dict) {
			foreach ($dict->getAll() as $entry) {
				if($entry["mode"] !== UserDictionary::MODE_REPLACE){
					continue;
				}
				$lower = mb_strtolower($entry['reading'], 'UTF-8');
				if (isset($seen[$lower])) {
					continue;
				}
				$seen[$lower] = true;
				$rules[] = [
					'lower'   => $lower,
					'surface' => $entry['surface'],
					'len'     => mb_strlen($entry['reading'], 'UTF-8'),
				];
			}
		}

		usort($rules, static fn($a, $b) => $b['len'] <=> $a['len']);
		return $rules;
	}
}