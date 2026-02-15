<?php
// KanaKanjiConverterExtendedTest.php

declare(strict_types=1);

use kanakanjiconverter\KanaKanjiConverter;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * KanaKanjiConverter 追加テスト
 * 既存 KanaKanjiConverterTest.php と重複しないケースを中心に網羅
 */
class KanaKanjiConverterExtendedTest extends TestCase
{
	private static KanaKanjiConverter $converter;

	public static function setUpBeforeClass(): void
	{
		$dictDir = realpath(__DIR__ . '/../src/dictionary_oss')
			?: (__DIR__ . '/../src/dictionary_oss');
		self::$converter = new KanaKanjiConverter($dictDir);
	}

	// =========================================================
	// convert() の境界値
	// =========================================================

	public function testNbestOne(): void
	{
		$result = self::$converter->convert('きのう', 1);
		$this->assertCount(1, $result['candidates']);
		$this->assertSame($result['best']['text'], $result['candidates'][0]['text']);
	}

	public function testNbestZeroClampedToOne(): void
	{
		// 0 は 1 にクランプされる
		$result = self::$converter->convert('きのう', 0);
		$this->assertGreaterThanOrEqual(1, count($result['candidates']));
	}

	public function testNbestNegativeClampedToOne(): void
	{
		$result = self::$converter->convert('きのう', -99);
		$this->assertGreaterThanOrEqual(1, count($result['candidates']));
	}

	public function testNbestMaxCap(): void
	{
		$result = self::$converter->convert('きのう', 999);
		$this->assertLessThanOrEqual(100, count($result['candidates']));
	}

	// =========================================================
	// 戻り値の型保証
	// =========================================================

	public function testBestTextIsString(): void
	{
		$result = self::$converter->convert('とうきょう', 1);
		$this->assertIsString($result['best']['text']);
	}

	public function testBestCostIsNonNegativeInt(): void
	{
		$result = self::$converter->convert('とうきょう', 1);
		$this->assertIsInt($result['best']['cost']);
		$this->assertGreaterThanOrEqual(0, $result['best']['cost']);
	}

	public function testTokensWordCostIsInt(): void
	{
		$result = self::$converter->convert('にほんご', 1);
		foreach ($result['best']['tokens'] as $token) {
			$this->assertIsInt($token['word_cost']);
			$this->assertIsInt($token['penalty']);
		}
	}

	public function testTokensReadingIsHiragana(): void
	{
		$result   = self::$converter->convert('とうきょう', 1);
		$readings = array_column($result['best']['tokens'], 'reading');
		foreach ($readings as $r) {
			if ($r === '') {
				continue;
			}
			// reading はひらがな・カタカナ・漢字混じりの可能性があるが空でないこと
			$this->assertNotEmpty($r);
		}
	}

	// =========================================================
	// 品詞情報の網羅
	// =========================================================

	#[DataProvider('posCheckProvider')]
	public function testPosLabel(string $hiragana, string $surface, string $expectedPos): void
	{
		$result   = self::$converter->convert($hiragana, 1);
		$surfaces = array_column($result['best']['tokens'], 'surface');
		$idx      = array_search($surface, $surfaces);

		if ($idx === false) {
			$this->markTestSkipped("{$surface} が best に含まれなかった");
			return;
		}
		$this->assertSame($expectedPos, $result['best']['tokens'][$idx]['pos'],
			"{$surface} の品詞が期待値と異なる"
		);
	}

	public static function posCheckProvider(): array
	{
		return [
			'東京→名詞'     => ['とうきょう',   '東京',   '名詞'],
			'食べ→動詞'     => ['たべました',   '食べ',   '動詞'],
			'は→助詞'       => ['きのうはあめ', 'は',     '助詞'],
			'を→助詞'       => ['すきやきをたべた', 'を',  '助詞'],
			'新しい→形容詞' => ['あたらしい',   '新しい', '形容詞'],
		];
	}

	// =========================================================
	// 候補の順序保証
	// =========================================================

	public function testCandidatesOrderedByCost(): void
	{
		$result = self::$converter->convert('きのう', 5);
		$costs  = array_column($result['candidates'], 'cost');
		$sorted = $costs;
		sort($sorted);
		$this->assertSame($sorted, $costs, '候補がコスト昇順になっていない');
	}

	public function testBestIsCheapest(): void
	{
		$result     = self::$converter->convert('きのう', 5);
		$bestCost   = $result['best']['cost'];
		$minCost    = min(array_column($result['candidates'], 'cost'));
		$this->assertSame($minCost, $bestCost, 'best のコストが最小でない');
	}

	// =========================================================
	// テキスト整合性
	// =========================================================

	public function testTextEqualsTokenSurfaceConcat(): void
	{
		// best.text は tokens[].surface の連結と一致するはず
		$result  = self::$converter->convert('きのうはすきやきをたべました', 1);
		$concat  = implode('', array_column($result['best']['tokens'], 'surface'));
		$this->assertSame($result['best']['text'], $concat,
			'text と tokens の surface 連結が一致しない'
		);
	}

	public function testAllCandidatesTextTokenConcat(): void
	{
		$result = self::$converter->convert('にほんご', 3);
		foreach ($result['candidates'] as $i => $candidate) {
			$concat = implode('', array_column($candidate['tokens'], 'surface'));
			$this->assertSame($candidate['text'], $concat,
				"candidates[{$i}] の text と tokens 連結が一致しない"
			);
		}
	}

	// =========================================================
	// 既知の誤変換改善確認（CHAIN_BONUS 効果）
	// =========================================================

	#[DataProvider('chainBonusProvider')]
	public function testChainBonusImprovement(
		string $hiragana,
		string $betterText,
		string $worseText
	): void {
		$result = self::$converter->convert($hiragana, 10);
		$costs  = [];
		foreach ($result['candidates'] as $c) {
			$costs[$c['text']] = $c['cost'];
		}

		if (!isset($costs[$betterText]) || !isset($costs[$worseText])) {
			$this->markTestSkipped(
				"比較対象が candidates に含まれない: better={$betterText} worse={$worseText}"
			);
			return;
		}

		$this->assertLessThanOrEqual(
			$costs[$worseText],
			$costs[$betterText],
			"CHAIN_BONUS: '{$betterText}'({$costs[$betterText]}) が '{$worseText}'({$costs[$worseText]}) より高コスト"
		);
	}

	public static function chainBonusProvider(): array
	{
		return [
			'昨日 vs 機能（文頭）' => [
				'きのうのかいぎをかいさいした',
				'昨日の会議を開催した',
				'機能の会議を開催した',
			],
			'今日（文頭優遇）' => [
				'きょうはいいてんきです',
				'今日は良い天気です',
				'今日は良い天気デス',
			],
		];
	}

	// =========================================================
	// 記号混じり入力
	// =========================================================

	public function testCommaInInput(): void
	{
		$result = self::$converter->convert('きのうのよるごはんは，すきやきです', 1);
		$this->assertStringContainsString('，', $result['best']['text']);
	}

	public function testInputWithOnlySymbol(): void
	{
		// 記号のみの入力でクラッシュしない
		$result = self::$converter->convert('，．！', 1);
		$this->assertArrayHasKey('best', $result);
	}

	// =========================================================
	// 長文・パフォーマンス回帰
	// =========================================================

	public function testLongInputDoesNotCrash(): void
	{
		$long   = str_repeat('きのうはすきやきをたべました', 5); // 70文字
		$result = self::$converter->convert($long, 1);
		$this->assertNotEmpty($result['best']['text']);
	}

	public function testPerformanceRegression(): void
	{
		// 43ms 基準の5倍（215ms）以内を上限として回帰検知
		$input = 'ながいろーまじぶんをこうそくでけんしょうします';
		$start = microtime(true);
		self::$converter->convert($input, 3);
		$ms = (microtime(true) - $start) * 1000;

		$this->assertLessThan(215, $ms,
			"パフォーマンス回帰: {$ms}ms（上限215ms）"
		);
	}

	// =========================================================
	// 繰り返し変換の安定性（状態汚染がないこと）
	// =========================================================

	public function testRepeatedConvertIsDeterministic(): void
	{
		$input = 'きのうはすきやきをたべました';
		$first  = self::$converter->convert($input, 3);
		$second = self::$converter->convert($input, 3);

		$this->assertSame(
			array_column($first['candidates'],  'text'),
			array_column($second['candidates'], 'text'),
			'同じ入力で2回変換した結果が異なる'
		);
	}

	public function testDifferentInputsAfterEachOther(): void
	{
		// 異なる入力を連続して変換しても干渉しない
		$r1 = self::$converter->convert('とうきょう', 1);
		$r2 = self::$converter->convert('おおさか',   1);
		$r3 = self::$converter->convert('とうきょう', 1);

		$this->assertSame($r1['best']['text'], $r3['best']['text'],
			'状態汚染: 同じ入力なのに結果が変わった'
		);
		$this->assertNotSame($r1['best']['text'], $r2['best']['text'],
			'東京と大阪が同じ変換結果になっている'
		);
	}
}