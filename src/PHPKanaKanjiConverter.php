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

		$result = $this->kannziconverter->convert($input, $numofbest);

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
		$allPreprocess = $this->buildPreprocessRules();

		if ($allPreprocess === []) {
			return $this->romaji->toHiragana($input, $removeIllegalFlag);
		}

		// プレースホルダ変換しながら入力を左から処理する
		$result = $this->preprocessInput($input, $allPreprocess, $removeIllegalFlag);
		return $result;
	}

	/**
	 * 全辞書からASCIIを含む reading のルールを収集し、長い順にソートして返す
	 *
	 * @return list<array{reading: string, surface: string, len: int}>
	 */
	private function buildPreprocessRules(): array
	{
		$rules = [];
		$seen  = [];
		foreach ($this->userDicts as $dict) {
			foreach ($dict->getAll() as $entry) {
				$raw = $entry['reading'];
				if (!preg_match('/[A-Za-z0-9]/u', $raw)) {
					continue;
				}
				$key = mb_strtolower($raw, 'UTF-8');
				if (isset($seen[$key])) {
					continue;
				}
				$seen[$key] = true;
				$rules[] = [
					'reading' => $raw,
					'surface' => $entry['surface'],
					'len'     => mb_strlen($raw, 'UTF-8'),
				];
			}
		}
		// 長い reading を優先（最長一致）
		usort($rules, static fn($a, $b) => $b['len'] <=> $a['len']);
		return $rules;
	}

	/**
	 * 入力文字列を左から走査し、ユーザー辞書の reading に最長一致したら
	 * surface に置換する。一致しなかった部分は toHiragana() で変換する。
	 *
	 * 衝突回避ルール:
	 *   ある reading にマッチしたとき、その直後の文字が
	 *   「ローマ字として継続しうる ASCII アルファベット」であれば
	 *   そのマッチは無効とみなしてスキップする（より長い別ルールに任せる）。
	 *   例: reading="sod", 入力="sodoku" → 直後が 'o' (母音) → スキップ
	 *       reading="server", 入力="servero" → 直後が 'o' → スキップしない
	 *       （"server" より長いルールがないため "server" が確定し "o" は別処理）
	 *
	 * 実際には「reading の末尾が子音で、直後も ASCII アルファベット」のとき
	 * のみスキップすることで「単語が途中で切れる」誤爆を防ぐ。
	 */
	private function preprocessInput(string $input, array $rules, bool $removeIllegalFlag): string
	{
		$inputLower = mb_strtolower($input, 'UTF-8');
		$inputLen   = mb_strlen($input, 'UTF-8');
		$pos        = 0;
		$result     = '';
		$pending    = ''; // まだ toHiragana() に渡していないバッファ

		while ($pos < $inputLen) {
			$matched = false;

			foreach ($rules as $rule) {
				$rLen   = $rule['len'];
				$rLower = mb_strtolower($rule['reading'], 'UTF-8');

				if ($pos + $rLen > $inputLen) {
					continue;
				}

				$chunk = mb_substr($inputLower, $pos, $rLen, 'UTF-8');
				if ($chunk !== $rLower) {
					continue;
				}

				// 直後の文字を確認
				$nextChar = ($pos + $rLen < $inputLen)
					? mb_substr($inputLower, $pos + $rLen, 1, 'UTF-8')
					: '';

				// 衝突回避: reading の末尾が子音 かつ 直後も ASCII アルファベットなら無効
				$readingLastChar = mb_substr($rLower, -1, 1, 'UTF-8');
				$isReadingEndsWithConsonant = preg_match('/[bcdfghjklmnpqrstvwxyz]/u', $readingLastChar);
				$isNextAlpha = ($nextChar !== '' && preg_match('/[a-z]/u', $nextChar));

				if ($isReadingEndsWithConsonant && $isNextAlpha) {
					// 後続 ASCII が続くので、このマッチは無効
					// → より長いルールが存在するか、ローマ字として処理させる
					continue;
				}

				// マッチ確定: pending を先に toHiragana() で変換して flush
				if ($pending !== '') {
					$result  .= $this->romaji->toHiragana($pending, $removeIllegalFlag);
					$pending  = '';
				}

				$result .= $rule['surface'];
				$pos    += $rLen;
				$matched = true;
				break;
			}

			if (!$matched) {
				// マッチしなかった文字は pending に積む（まとめて toHiragana() へ）
				$pending .= mb_substr($input, $pos, 1, 'UTF-8');
				$pos++;
			}
		}

		// 残った pending を変換
		if ($pending !== '') {
			$result .= $this->romaji->toHiragana($pending, $removeIllegalFlag);
		}

		return $result;
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