<?php
// ConvertibleRomajiExtendedTest.php

declare(strict_types=1);

use kanakanjiconverter\ConvertibleRomaji;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * ConvertibleRomaji 追加テスト
 * 既存 ConvertibleRomajiTest.php と重複しないケースを中心に網羅
 *
 * @internal
 */
final class ConvertibleRomajiExtendedTest extends TestCase
{
	private ConvertibleRomaji $romaji;

	protected function setUp(): void
	{
		$this->romaji = new ConvertibleRomaji((__DIR__ . '/../src/dictionary_oss/map.json'));
	}

	// =========================================================
	// 促音の境界ケース
	// =========================================================

	public function testSokuonVariants(): void
	{
		// 各子音での促音
		$this->assertSame('ばっば', $this->romaji->toHiragana('babba'));
		$this->assertSame('かっか', $this->romaji->toHiragana('kakka'));
		$this->assertSame('まっま', $this->romaji->toHiragana('mamma'));
		$this->assertSame('さっさ', $this->romaji->toHiragana('sassa'));
		$this->assertSame('たった', $this->romaji->toHiragana('tatta'));
		$this->assertSame('ぱっぱ', $this->romaji->toHiragana('pappa'));
		$this->assertSame('らっら', $this->romaji->toHiragana('rarra'));
	}

	public function testSokuonBeforeYouon(): void
	{
		// 促音 + 拗音
		$this->assertSame('きっきゃ', $this->romaji->toHiragana('kikkya'));
		$this->assertSame('はっしゃ', $this->romaji->toHiragana('hassha'));
		$this->assertSame('まっちゃ', $this->romaji->toHiragana('maccha'));
	}

	public function testSokuonAtEnd(): void
	{
		// 末尾の促音（変換できない子音が残る）
		$result = $this->romaji->toHiragana('att', false);
		$this->assertStringContainsString('っ', $result);
	}

	public function testNNotSokuon(): void
	{
		// n は促音にならない（nn → ん）
		$this->assertSame('あんい', $this->romaji->toHiragana('anni'));
		$this->assertNotSame('あっに', $this->romaji->toHiragana('anni'));
	}

	// =========================================================
	// ん の境界ケース
	// =========================================================

	public function testNBeforeConsonant(): void
	{
		$this->assertSame('あんか', $this->romaji->toHiragana('anka'));
		$this->assertSame('あんさ', $this->romaji->toHiragana('ansa'));
		$this->assertSame('あんた', $this->romaji->toHiragana('anta'));
		$this->assertSame('あんま', $this->romaji->toHiragana('anma'));
		$this->assertSame('あんは', $this->romaji->toHiragana('anha'));
		$this->assertSame('あんわ', $this->romaji->toHiragana('anwa'));
	}

	public function testNAtEnd(): void
	{
		$this->assertSame('あん',     $this->romaji->toHiragana('ann'));
		$this->assertSame('しんぶん', $this->romaji->toHiragana('sinbun'));
		$this->assertSame('かん',     $this->romaji->toHiragana('kann'));
	}

	public function testNBeforeVowel(): void
	{
		// n + 母音 → 合字優先
		$this->assertSame('なに',   $this->romaji->toHiragana('nani'));
		$this->assertSame('いのう', $this->romaji->toHiragana('inou'));
		// nn + 母音 → ん + 合字
		$this->assertSame('あんい', $this->romaji->toHiragana('anni'));
		$this->assertSame('あんあ', $this->romaji->toHiragana('anna'));
		$this->assertSame('あんう', $this->romaji->toHiragana('annu'));
		$this->assertSame('あんえ', $this->romaji->toHiragana('anne'));
		$this->assertSame('あんお', $this->romaji->toHiragana('anno'));
	}

	public function testNBeforeY(): void
	{
		// n + y → nya 等の拗音
		$this->assertSame('にゃ', $this->romaji->toHiragana('nya'));
		$this->assertSame('にゅ', $this->romaji->toHiragana('nyu'));
		$this->assertSame('にょ', $this->romaji->toHiragana('nyo'));
		// nn + ya → ん + や
		$this->assertSame('あんや', $this->romaji->toHiragana('annya'));
	}

	public function testTripleN(): void
	{
		// nnn + 母音 → ん + に等
		$this->assertSame('あんに', $this->romaji->toHiragana('annni'));
		$this->assertSame('しんねん', $this->romaji->toHiragana('sinnnen'));
	}

	// =========================================================
	// 記号変換
	// =========================================================

	#[DataProvider('symbolProvider')]
	public function testSymbolConversion(string $input, string $expected): void
	{
		$result = $this->romaji->toHiragana($input);
		$this->assertSame($expected, $result);
	}

	public static function symbolProvider(): array
	{
		return [
			'カンマ'       => [',',  '，'],
			'ピリオド'     => ['.',  '．'],
			'長音'         => ['-',  'ー'],
			'感嘆符'       => ['!',  '！'],
			'疑問符'       => ['?',  '？'],
			'コロン'       => [':',  '：'],
			'セミコロン'   => [';',  '；'],
			'アットマーク' => ['@',  '＠'],
			'スラッシュ'   => ['/',  '／'],
			'アンダースコア' => ['_', '＿'],
			'括弧開'       => ['(',  '（'],
			'括弧閉'       => [')',  '）'],
		];
	}

	public function testSymbolMixedWithRomaji(): void
	{
		$this->assertSame('きのう，すきやき', $this->romaji->toHiragana('kinou,sukiyaki'));
		$this->assertSame('こんにちは！',     $this->romaji->toHiragana('konnnichiha!'));
		$this->assertSame('なに？',           $this->romaji->toHiragana('nani?'));
	}

	// =========================================================
	// 長音（ー）
	// =========================================================

	public function testLongVowelVariants(): void
	{
		$this->assertSame('こーひー',     $this->romaji->toHiragana('ko-hi-'));
		$this->assertSame('べんちまーく', $this->romaji->toHiragana('benchima-ku'));
		$this->assertSame('さーびす',     $this->romaji->toHiragana('sa-bisu'));
		// 複数の長音
		$this->assertSame('おーいー',     $this->romaji->toHiragana('o-i-'));
	}

	// =========================================================
	// 拡張ローマ字（shi/chi/tsu）
	// =========================================================

	#[DataProvider('extendedRomajiProvider')]
	public function testExtendedRomaji(string $input, string $expected): void
	{
		$this->assertSame($expected, $this->romaji->toHiragana($input));
	}

	public static function extendedRomajiProvider(): array
	{
		return [
			'shi'  => ['shi',  'し'],
			'chi'  => ['chi',  'ち'],
			'tsu'  => ['tsu',  'つ'],
			'sha'  => ['sha',  'しゃ'],
			'shu'  => ['shu',  'しゅ'],
			'sho'  => ['sho',  'しょ'],
			'cha'  => ['cha',  'ちゃ'],
			'chu'  => ['chu',  'ちゅ'],
			'cho'  => ['cho',  'ちょ'],
			'ja'   => ['ja',   'じゃ'],
			'ju'   => ['ju',   'じゅ'],
			'jo'   => ['jo',   'じょ'],
			'fu'   => ['fu',   'ふ'],
			'fa'   => ['fa',   'ふぁ'],
			'fi'   => ['fi',   'ふぃ'],
			'fe'   => ['fe',   'ふぇ'],
			'fo'   => ['fo',   'ふぉ'],
		];
	}

	// =========================================================
	// toKatakana
	// =========================================================

	#[DataProvider('katakanaProvider')]
	public function testToKatakana(string $input, string $expected): void
	{
		$this->assertSame($expected, $this->romaji->toKatakana($input));
	}

	public static function katakanaProvider(): array
	{
		return [
			'キノウ'         => ['kinou',       'キノウ'],
			'ベンチマーク'   => ['benchima-ku', 'ベンチマーク'],
			'トウキョウ'     => ['toukyou',      'トウキョウ'],
			'スキヤキ'       => ['sukiyaki',     'スキヤキ'],
			'コーヒー'       => ['ko-hi-',       'コーヒー'],
			'プロジェクト'   => ['purojekuto',   'プロジェクト'],
		];
	}

	public function testToKatakanaSymbolPassthrough(): void
	{
		// 記号はカタカナ変換でもそのまま
		$result = $this->romaji->toKatakana('kinou,toukyou');
		$this->assertStringContainsString('，', $result);
		$this->assertStringContainsString('キノウ', $result);
	}

	public function testToKatakanaEmpty(): void
	{
		$this->assertSame('', $this->romaji->toKatakana(''));
	}

	// =========================================================
	// 大文字・正規化
	// =========================================================

	public function testUppercaseEqualsLowercase(): void
	{
		$pairs = [
			'KINOU', 'TOUKYOU', 'SUKIYAKI', 'NIHONGO',
			'SHA', 'CHI', 'TSU', 'KYA',
		];
		foreach ($pairs as $upper) {
			$lower = strtolower($upper);
			$this->assertSame(
				$this->romaji->toHiragana($lower),
				$this->romaji->toHiragana($upper),
				"大文字/小文字で結果が異なる: {$upper}"
			);
		}
	}

	public function testMacronNormalization(): void
	{
		// ō → 'ou' に正規化される仕様（ConvertibleRomaji コード内コメント通り）
		$this->assertSame(
			$this->romaji->toHiragana('ou'),
			$this->romaji->toHiragana('ō')
		);
		// ū → 'uu' に正規化される仕様
		$this->assertSame(
			$this->romaji->toHiragana('uu'),
			$this->romaji->toHiragana('ū')
		);
	}

	// =========================================================
	// removeIllegalFlag
	// =========================================================

	public function testRemoveIllegalTrue(): void
	{
		// 英数字は削除
		$this->assertSame('',       $this->romaji->toHiragana('xyz',  true));
		$this->assertSame('',       $this->romaji->toHiragana('123',  true));
		$this->assertSame('あい',   $this->romaji->toHiragana('a1i',  true));
	}

	public function testRemoveIllegalFalse(): void
	{
		// 英数字を保持
		$this->assertSame('xyz',    $this->romaji->toHiragana('xyz',  false));
		$this->assertSame('123',    $this->romaji->toHiragana('123',  false));
		$this->assertSame('あ1い',  $this->romaji->toHiragana('a1i',  false));
	}

	// =========================================================
	// 空白処理
	// =========================================================

	public function testSpacePassthrough(): void
	{
		$result = $this->romaji->toHiragana('kinou ha sukiyaki');
		$this->assertStringContainsString('きのう', $result);
		$this->assertStringContainsString(' ',      $result);
		$this->assertStringContainsString('すきやき', $result);
	}

	public function testMultipleSpaces(): void
	{
		$result = $this->romaji->toHiragana('a  i');
		$this->assertStringContainsString('あ', $result);
		$this->assertStringContainsString('い', $result);
		$this->assertMatchesRegularExpression('/\s{2}/', $result);
	}

	// =========================================================
	// 実文ベンチマーク入力の再現
	// =========================================================

	#[DataProvider('benchmarkInputProvider')]
	public function testBenchmarkInputs(string $input, string $expected): void
	{
		$this->assertSame($expected, $this->romaji->toHiragana($input));
	}

	public static function benchmarkInputProvider(): array
	{
		return [
			'今日の天気'     => [
				'kilyounotennkihaharedesugoshiyasuidesu',
				'きょうのてんきははれですごしやすいです',
			],
			'私は日本語'     => [
				'watashihanihongonobenkyouwoshiteimasu',
				'わたしはにほんごのべんきょうをしています',
			],
			'明日は友達'     => [
				'ashitahatomodachitotoukyouniikimasu',
				'あしたはともだちととうきょうにいきます',
			],
			'昨日の夜ご飯'   => [
				'kinounoyorugohannha,sukiyakidesu',
				'きのうのよるごはんは，すきやきです',
			],
			'ベンチマーク'   => [
				'konosukuriputohabenchima-kudesu',
				'このすくりぷとはべんちまーくです',
			],
			'長いローマ字'   => [
				'nagairo-majibunnwokousokudekenshoushimasu',
				'ながいろーまじぶんをこうそくでけんしょうします',
			],
		];
	}
}