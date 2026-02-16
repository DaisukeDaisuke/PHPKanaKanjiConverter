<?php
// PHPKanaKanjiConverter.php

declare(strict_types=1);

namespace kanakanjiconverter;

final class PHPKanaKanjiConverter
{
	private ConvertibleRomaji $romaji;
	private KanaKanjiConverter $kannziconverter;

	/** @var array<string, UserDictionary>  name => 辞書インスタンス */
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

	/**
	 * ユーザー辞書を登録する（重複名は上書き）
	 */
	public function registerUserDict(string $name, UserDictionary $dict): void
	{
		$this->userDicts[$name] = $dict;
	}

	/**
	 * 複数のユーザー辞書を MODE_MERGE で統合して登録する
	 *
	 * @param string $name          統合後の辞書名
	 * @param string ...$dictNames  統合する既登録辞書名
	 */
	public function mergeDicts(string $name, string ...$dictNames): void
	{
		$targets = [];
		foreach ($dictNames as $dn) {
			if (isset($this->userDicts[$dn])) {
				$targets[] = $this->userDicts[$dn];
			}
		}
		if ($targets !== []) {
			$this->userDicts[$name] = UserDictionary::merge(...$targets);
		}
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
	 * @return array{
	 *   original: string,
	 *   kana: string,
	 *   best: array{text: string, cost: int, tokens: list<array{surface: string, reading: string, word_cost: int, penalty: int, pos: string, subpos: string, pos_label: string}>},
	 *   candidates: list<array{text: string, cost: int, tokens: list<array{surface: string, reading: string, word_cost: int, penalty: int, pos: string, subpos: string, pos_label: string}>}>
	 * }
	 */
	public function convert(string $input, bool $removeIllegalFlag = false, int $numofbest = 3): array
	{
		// Step1: ローマ字 → かな
		//   MODE_KANA_ALT の独自ルール + MODE_KANA の reading を認識した上で変換
		$kana = $this->romajiToKana($input, $removeIllegalFlag);

		// Step2: かな全体一致チェック（MODE_NO_CONVERT / MODE_REPLACE）
		$earlyResult = $this->applyEarlyModes($kana);
		if ($earlyResult !== null) {
			return $earlyResult + ['original' => $input, 'kana' => $kana];
		}

		// Step3: 漢字変換フェーズ用ユーザーエントリを収集
		//   MODE_KANA:   ローマ字→かな と漢字変換の両フェーズで使う → 漢字変換にも注入
		//   MODE_MERGE:  複数辞書統合済みのエントリ → 内蔵辞書にマージ
		//   MODE_SERVER: サーバー側辞書エントリ    → 内蔵辞書にマージ
		$userEntries = $this->collectConverterEntries([
			UserDictionary::MODE_KANA,
			UserDictionary::MODE_MERGE,
			UserDictionary::MODE_SERVER,
		]);

		$result = $this->kannziconverter->convertWithUserEntries(
			$kana,
			$userEntries,
			true,   // 内蔵辞書も使う
			$numofbest
		);

		$result['original'] = $input;
		$result['kana']     = $kana;
		return $result;
	}

	// ----------------------------------------------------------------
	// 既存メソッド（変更なし・維持）
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

	/**
	 * ローマ字→かな変換
	 *
	 * 1. MODE_KANA_ALT のルールを ConvertibleRomaji に一時的に注入
	 *    （例: 'server' => 'サーバー' のような独自かな変換）
	 * 2. MODE_KANA の reading もローマ字として認識させる
	 *    （ConvertibleRomaji に addCustomRule() があれば）
	 */
	private function romajiToKana(string $input, bool $removeIllegalFlag): string
	{
		$hasCustomRule = method_exists($this->romaji, 'addCustomRule');
		$hasClear      = method_exists($this->romaji, 'clearCustomRules');

		if ($hasCustomRule) {
			// MODE_KANA_ALT: 独自ローマ字→かなルール
			foreach ($this->userDicts as $dict) {
				foreach ($dict->getKanaAltRules() as $romaji => $kana) {
					$this->romaji->addCustomRule($romaji, $kana);
				}
			}

			// MODE_KANA: reading（かな）をローマ字として認識させる
			// ※ reading がローマ字の場合のみ意味を持つ
			foreach ($this->userDicts as $dict) {
				foreach ($dict->getByMode(UserDictionary::MODE_KANA) as $entry) {
					// surface（漢字など）への直接マッピングではなく
					// reading（かな）を出力として使う
					$this->romaji->addCustomRule($entry['reading'], $entry['reading']);
				}
			}
		}

		$result = $this->romaji->toHiragana($input, $removeIllegalFlag);

		if ($hasCustomRule && $hasClear) {
			$this->romaji->clearCustomRules();
		}

		return $result;
	}

	/**
	 * MODE_NO_CONVERT / MODE_REPLACE の全体一致チェック
	 * → かな文字列全体が reading に完全一致する場合に早期リターン
	 *
	 * @return array<string,mixed>|null
	 */
	private function applyEarlyModes(string $kana): ?array
	{
		foreach ($this->userDicts as $dict) {
			foreach ($dict->findByReading($kana) as $entry) {
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
	 * 指定モードのエントリを全辞書から収集し
	 * KanaKanjiConverter::convertWithUserEntries() が期待する形式に変換する
	 *
	 * @param int[] $modes
	 * @return array<string, list<array<string,mixed>>>  読み => [エントリ配列]
	 */
	private function collectConverterEntries(array $modes): array
	{
		$result = [];
		foreach ($this->userDicts as $dict) {
			foreach ($dict->getConverterEntries($modes) as $reading => $entries) {
				foreach ($entries as $e) {
					$result[$reading][] = $e;
				}
			}
		}
		return $result;
	}

	/**
	 * 単一トークンの result 配列を組み立てる
	 *
	 * @param array<string,mixed> $entry
	 * @return array<string,mixed>
	 */
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
}