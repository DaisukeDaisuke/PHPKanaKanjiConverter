<?php
declare(strict_types=1);

namespace kanakanjiconverter;

final class PHPKanaKanjiConverter
{
	private ConvertibleRomaji $romaji;
	private KanaKanjiConverter $kannziconverter;

	/** @var UserDictionary[] */
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

	public function convert(string $input, bool $removeIllegalFlag = false, int $numofbest = 3): array
	{
		// Step1: ユーザー辞書のローマ字読みをプリプロセスで先に置換 → 残りをtoHiragana
		$kana = $this->applyKanaMode($input, $removeIllegalFlag);

		// Step2: 漢字変換（ユーザー辞書はプリプロセス済みなので内蔵辞書のみ）
		$result = $this->kannziconverter->convert($kana, $numofbest);

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

		$inputLower = mb_strtolower($input, 'UTF-8');
		$inputLen   = mb_strlen($input, 'UTF-8');
		$pos        = 0;
		$result     = '';
		$pending    = '';

		while ($pos < $inputLen) {
			$matched = false;

			foreach ($rules as $rule) {
				if ($pos + $rule['len'] > $inputLen) {
					continue;
				}
				if (mb_substr($inputLower, $pos, $rule['len'], 'UTF-8') !== $rule['lower']) {
					continue;
				}

				$nextChar      = ($pos + $rule['len'] < $inputLen)
					? mb_substr($inputLower, $pos + $rule['len'], 1, 'UTF-8') : '';
				$lastChar      = mb_substr($rule['lower'], -1, 1, 'UTF-8');
				$endsConsonant = (bool)preg_match('/[bcdfghjklmnpqrstvwxyz]/u', $lastChar);
				$nextIsAlpha   = ($nextChar !== '' && (bool)preg_match('/[a-z]/u', $nextChar));

				if ($endsConsonant && $nextIsAlpha) {
					continue;
				}

				if ($pending !== '') {
					$result  .= $this->romaji->toHiragana($pending, $removeIllegalFlag);
					$pending  = '';
				}
				$result .= $rule['surface'];
				$pos    += $rule['len'];
				$matched = true;
				break;
			}

			if (!$matched) {
				$pending .= mb_substr($input, $pos, 1, 'UTF-8');
				$pos++;
			}
		}

		if ($pending !== '') {
			$result .= $this->romaji->toHiragana($pending, $removeIllegalFlag);
		}

		return $result;
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