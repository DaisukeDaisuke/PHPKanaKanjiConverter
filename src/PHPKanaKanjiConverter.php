<?php

declare(strict_types=1);

namespace kanakanjiconverter;

final class PHPKanaKanjiConverter
{
	private ConvertibleRomaji $romaji;
	private KanaKanjiConverter $kannziconverter;

	/** @var UserDictionary[] キー: 任意の識別子 */
	private array $userDicts = [];

	public function __construct()
	{
		$this->romaji = new ConvertibleRomaji();
		$dictDir = realpath(__DIR__ . '/dictionary_oss') ?: (__DIR__ . '/dictionary_oss');
		$this->kannziconverter = new KanaKanjiConverter($dictDir);
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
	 * @return array{original: string, kana: string, best: array, candidates: list<array>}
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
		$altEntries    = $this->collectEntries(UserDictionary::MODE_KANA_ALT);
		$mergeEntries  = $this->collectEntries(UserDictionary::MODE_MERGE);
		$serverEntries = $this->collectEntries(UserDictionary::MODE_SERVER);

		$hasUserEntries = ($altEntries !== [] || $mergeEntries !== [] || $serverEntries !== []);

		if ($altEntries !== []) {
			// KANA_ALT: ユーザー辞書のみで変換
			$result = $this->kannziconverter->convertWithUserEntries(
				$kana,
				$altEntries,
				false,
				$numofbest
			);
		} elseif ($hasUserEntries) {
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
	// 既存メソッドはそのまま維持
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
		$entries = $this->collectEntries(UserDictionary::MODE_KANA);

		if ($entries === []) {
			return $this->romaji->toHiragana($input, $removeIllegalFlag);
		}

		if (method_exists($this->romaji, 'addCustomRule')) {
			foreach ($entries as $reading => $entryList) {
				foreach ($entryList as $e) {
					$this->romaji->addCustomRule($reading, $e['surface']);
				}
			}
			$result = $this->romaji->toHiragana($input, $removeIllegalFlag);
			if (method_exists($this->romaji, 'clearCustomRules')) {
				$this->romaji->clearCustomRules();
			}
			return $result;
		}

		return $this->romaji->toHiragana($input, $removeIllegalFlag);
	}

	private function buildSingleTokenResult(string $surface, string $reading, array $entry): array
	{
		$token = [
			'surface'   => $surface,
			'reading'   => $reading,
			'word_cost' => $entry['word_cost'],
			'penalty'   => 0,
			'pos'       => $entry['pos'],
			'subpos'    => $entry['subpos'],
			'pos_label' => $entry['pos'] . '-' . $entry['subpos'],
		];
		$candidate = ['text' => $surface, 'tokens' => [$token], 'cost' => $entry['word_cost']];
		return [
			'best'       => $candidate,
			'candidates' => [$candidate],
		];
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
				if (!in_array($entry['mode'], [
					UserDictionary::MODE_NO_CONVERT,
					UserDictionary::MODE_REPLACE,
				], true)) {
					continue;
				}
				$normalizedReading = $this->normalizeReading($entry['reading']);
				if ($normalizedReading !== $kana) {
					continue;
				}
				if ($entry['mode'] === UserDictionary::MODE_NO_CONVERT) {
					return $this->buildSingleTokenResult($kana, $kana, $entry);
				}
				if ($entry['mode'] === UserDictionary::MODE_REPLACE) {
					return $this->buildSingleTokenResult($entry['surface'], $kana, $entry);
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
}