<?php
// KanaKanjiConverterTest.php

declare(strict_types=1);

namespace test;

use kanakanjiconverter\KanaKanjiConverter;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class KanaKanjiConverterTest extends TestCase{
	private static KanaKanjiConverter $converter;

	public static function setUpBeforeClass() : void{
		$dictDir = realpath(__DIR__ . '/../src/dictionary_oss')
			?: (__DIR__ . '/../src/dictionary_oss');
		self::$converter = new KanaKanjiConverter($dictDir);
	}

	// =========================================================
	// 戻り値の構造テスト
	// =========================================================

	public function testReturnStructure() : void{
		$result = self::$converter->convert('きのう', 3);
		$this->assertArrayHasKey('best', $result);
		$this->assertArrayHasKey('candidates', $result);
		$this->assertArrayHasKey('text', $result['best']);
		$this->assertArrayHasKey('tokens', $result['best']);
		$this->assertArrayHasKey('cost', $result['best']);
		$this->assertIsArray($result['candidates']);
		$this->assertNotEmpty($result['candidates']);
	}

	public function testTokenStructure() : void{
		$result = self::$converter->convert('すきやき', 1);
		$token = $result['best']['tokens'][0];
		$this->assertArrayHasKey('surface', $token);
		$this->assertArrayHasKey('reading', $token);
		$this->assertArrayHasKey('word_cost', $token);
		$this->assertArrayHasKey('penalty', $token);
		$this->assertArrayHasKey('pos', $token);
		$this->assertArrayHasKey('pos_label', $token);
	}

	public function testNbestLimit() : void{
		$result = self::$converter->convert('きのう', 5);
		$this->assertLessThanOrEqual(5, count($result['candidates']));

		$result1 = self::$converter->convert('きのう', 1);
		$this->assertCount(1, $result1['candidates']);
	}

	// =========================================================
	// 基本変換テスト
	// =========================================================

	#[DataProvider('basicConversionProvider')]
	public function testBasicConversion(string $hiragana, string $expected) : void{
		$result = self::$converter->convert($hiragana, 1);
		$this->assertSame($expected, $result['best']['text'],
			"Input: {$hiragana} → expected: {$expected}, got: {$result['best']['text']}"
		);
	}

	public static function basicConversionProvider() : array{
		return [
			'すき焼き' => ['すきやき', 'すき焼き'],
			'食べました' => ['たべました', '食べました'],
			'東京' => ['とうきょう', '東京'],
			'日本語' => ['にほんご', '日本語'],
			'勉強' => ['べんきょう', '勉強'],
			'開発中' => ['かいはつちゅう', '開発中'],
			'新しい' => ['あたらしい', '新しい'],
			'友達' => ['ともだち', '友達'],
		];
	}

	// =========================================================
	// 誤変換ケーステスト（candidatesに正解が含まれること）
	// =========================================================

	#[DataProvider('knownIssueProvider')]
	public function testKnownIssueInCandidates(string $hiragana, string $expectedText) : void{
		$result = self::$converter->convert($hiragana, 5);
		$texts = array_column($result['candidates'], 'text');
		$this->assertContains($expectedText, $texts,
			"Expected '{$expectedText}' in candidates for '{$hiragana}'.\nGot: " . implode(', ', $texts)
		);
	}

	public static function knownIssueProvider() : array{
		return [
			'昨日はすき焼き' => ['きのうはすきやきをたべました', '昨日はすき焼きを食べました'],
			'昨日単体' => ['きのう', '昨日'],
			'今日' => ['きょう', '今日'],
			'明日' => ['あした', '明日'],
			'毎日' => ['まいにち', '毎日'],
			'感謝' => ['かんしゃ', '感謝'],
		];
	}

	// =========================================================
	// 品詞情報テスト
	// =========================================================

	public function testPosInfoAttached() : void{
		$result = self::$converter->convert('たべました', 1);
		$hasPosInfo = false;
		foreach($result['best']['tokens'] as $token){
			if(isset($token['pos']) && $token['pos'] !== '不明'){
				$hasPosInfo = true;
				break;
			}
		}
		$this->assertTrue($hasPosInfo, '品詞情報が付与されていない');
	}

	public function testVerbPos() : void{
		$result = self::$converter->convert('たべました', 1);
		$surfaces = array_column($result['best']['tokens'], 'surface');
		$idx = array_search('食べ', $surfaces);
		if($idx !== false){
			$this->assertSame('動詞', $result['best']['tokens'][$idx]['pos']);
		}else{
			$this->markTestSkipped('食べ が best に含まれなかった');
		}
	}

	public function testNounPos() : void{
		$result = self::$converter->convert('とうきょう', 1);
		$token = $result['best']['tokens'][0];
		$this->assertSame('名詞', $token['pos']);
	}

	// =========================================================
	// エッジケース
	// =========================================================

	public function testEmptyString() : void{
		$result = self::$converter->convert('', 1);
		$this->assertArrayHasKey('best', $result);
		$this->assertArrayHasKey('candidates', $result);
	}

	public function testSingleChar() : void{
		$result = self::$converter->convert('あ', 1);
		$this->assertNotEmpty($result['best']['text']);
	}

	public function testSymbolPassthrough() : void{
		// '，'（全角カンマ）がそのまま出力に含まれること
		$result = self::$converter->convert('きのうのよるごはんは，すきやきです', 1);
		$this->assertStringContainsString('，', $result['best']['text']);
	}

	public function testLongString() : void{
		$long = str_repeat('あいうえお', 20);
		$result = self::$converter->convert($long, 1);
		$this->assertArrayHasKey('best', $result);
	}

	public function testNbestMaxCap() : void{
		$result = self::$converter->convert('きのう', 200);
		$this->assertLessThanOrEqual(100, count($result['candidates']));
	}

	public function testCostIsInt() : void{
		$result = self::$converter->convert('とうきょう', 1);
		$this->assertIsInt($result['best']['cost']);
		$this->assertGreaterThanOrEqual(0, $result['best']['cost']);
	}

	public function testAllCandidatesHaveText() : void{
		$result = self::$converter->convert('きのう', 5);
		foreach($result['candidates'] as $candidate){
			$this->assertNotEmpty($candidate['text'], 'candidateのtextが空');
		}
	}

	public function testNoDuplicateCandidates() : void{
		$result = self::$converter->convert('きのう', 10);
		$texts = array_column($result['candidates'], 'text');
		$unique = array_unique($texts);
		$this->assertCount(count($unique), $texts, '重複candidateがある');
	}

	// =========================================================
	// ベンチマーク回帰テスト
	// =========================================================

	#[DataProvider('benchmarkRegressionProvider')]
	public function testBenchmarkRegression(string $hiragana, string $expectedBest) : void{
		$result = self::$converter->convert($hiragana, 3);
		$this->assertSame($expectedBest, $result['best']['text'],
			"Regression: '{$hiragana}' → expected '{$expectedBest}', got '{$result['best']['text']}'"
		);
	}

	public static function benchmarkRegressionProvider() : array{
		return [
			'今日の天気' => ['きょうのてんきははれですごしやすいです', '今日の天気は晴れで過ごしやすいです'],
			'私は日本語' => ['わたしはにほんごのべんきょうをしています', '私は日本語の勉強をしています'],
			'明日は友達' => ['あしたはともだちととうきょうにいきます', '明日は友達と東京に行きます'],
			'新しいプロジェクト' => ['あたらしいぷろじぇくとをかいはつちゅうです', '新しいプロジェクトを開発中です'],
			'日本の重要' => ['にほんのじゅうようなぶんかをおおくまなびます', '日本の重要な文化を多く学びます'],
			'昨日の会議' => ['きのうのかいぎをかいさいした', '昨日の会議を開催した'],
			'昨日の夜ご飯' => ['きのうのよるごはんは，すきやきです', '昨日の夜ご飯は，すき焼きです'],
			'明日の朝' => ['あしたのあさ', '明日の朝'],
			'今の時代' => ['いまのじだい', '今の時代'],
			'話がある' => ['はなしがある', '話がある'],
			'テレビを見る' => ['てれびをみる', 'テレビを見る'],
			'昨日食べた' => ['きのうたべた', '昨日食べた'],
		];
	}
}