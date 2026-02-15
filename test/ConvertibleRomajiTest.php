<?php
// ConvertibleRomajiTest.php

declare(strict_types=1);

namespace test;

use kanakanjiconverter\ConvertibleRomaji;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class ConvertibleRomajiTest extends TestCase{
	private ConvertibleRomaji $romaji;

	protected function setUp() : void{
		$this->romaji = new ConvertibleRomaji();
	}

	// =========================================================
	// 基本変換
	// =========================================================

	public function testBasicVowels() : void{
		$this->assertSame('あいうえお', $this->romaji->toHiragana('aiueo'));
	}

	public function testBasicConsonants() : void{
		$this->assertSame('かきくけこ', $this->romaji->toHiragana('kakikukeko'));
		$this->assertSame('さしすせそ', $this->romaji->toHiragana('sasisuseso'));
		$this->assertSame('たちつてと', $this->romaji->toHiragana('tatituteto'));
		$this->assertSame('なにぬねの', $this->romaji->toHiragana('naninuneno'));
		$this->assertSame('はひふへほ', $this->romaji->toHiragana('hahifuheho'));
		$this->assertSame('まみむめも', $this->romaji->toHiragana('mamimumemo'));
		$this->assertSame('やゆよ', $this->romaji->toHiragana('yayuyo'));
		// ra ri ru re ro → らりるれろ（ri は1文字）
		$this->assertSame('らりるれろ', $this->romaji->toHiragana('rarirurero'));
		//                               ra-ri-ri-ru-re-ro → らりりるれろ なので入力を修正
		$this->assertSame('らりるれろ', $this->romaji->toHiragana('rarirurero'));
		$this->assertSame('わをん', $this->romaji->toHiragana('wawon'));
	}

	public function testDakuten() : void{
		$this->assertSame('がぎぐげご', $this->romaji->toHiragana('gagigugego'));
		$this->assertSame('ざじずぜぞ', $this->romaji->toHiragana('zazizuzezo'));
		$this->assertSame('だぢづでど', $this->romaji->toHiragana('dadidudedo'));
		$this->assertSame('ばびぶべぼ', $this->romaji->toHiragana('babibubebo'));
	}

	public function testHandakuten() : void{
		$this->assertSame('ぱぴぷぺぽ', $this->romaji->toHiragana('papipupepo'));
	}

	public function testYouon() : void{
		$this->assertSame('きゃきゅきょ', $this->romaji->toHiragana('kyakyukyo'));
		$this->assertSame('しゃしゅしょ', $this->romaji->toHiragana('syasyusyo'));
		$this->assertSame('ちゃちゅちょ', $this->romaji->toHiragana('tyatyutyo'));
		$this->assertSame('にゃにゅにょ', $this->romaji->toHiragana('nyanyunyo'));
		$this->assertSame('ひゃひゅひょ', $this->romaji->toHiragana('hyahyuhyo'));
		$this->assertSame('みゃみゅみょ', $this->romaji->toHiragana('myamyumyo'));
		$this->assertSame('りゃりゅりょ', $this->romaji->toHiragana('ryaryuryo'));
		$this->assertSame('ぎゃぎゅぎょ', $this->romaji->toHiragana('gyagyugyo'));
		$this->assertSame('じゃじゅじょ', $this->romaji->toHiragana('zyazyuzyo'));
		$this->assertSame('びゃびゅびょ', $this->romaji->toHiragana('byabyubyo'));
		$this->assertSame('ぴゃぴゅぴょ', $this->romaji->toHiragana('pyapyupyo'));
	}

	public function testSokuon() : void{
		$this->assertSame('きって', $this->romaji->toHiragana('kitte'));
		$this->assertSame('きっぱり', $this->romaji->toHiragana('kippari'));
		$this->assertSame('ざっし', $this->romaji->toHiragana('zassi'));
		$this->assertSame('にっき', $this->romaji->toHiragana('nikki'));
	}

	public function testN(): void
	{
		$this->assertSame('さんぽ',   $this->romaji->toHiragana('sanpo'));
		$this->assertSame('しんぶん', $this->romaji->toHiragana('sinbun'));
		$this->assertSame('かんじ',   $this->romaji->toHiragana('kanji'));
		$this->assertSame('ほんや',   $this->romaji->toHiragana('honnya'));

		// nn → ん（2文字消費）、残りi → い
		$this->assertSame('あんい',   $this->romaji->toHiragana('anni'));
		// n で終わる → ん確定
		$this->assertSame('あん',     $this->romaji->toHiragana('ann'));
		// n + 子音 → ん確定
		$this->assertSame('あんか',   $this->romaji->toHiragana('anka'));
		$this->assertSame('1234567890',   $this->romaji->toHiragana('1234567890', false));
	}

	public function testLongVowelMark() : void{
		// '-' → 'ー'（長音記号）に変換される仕様
		// toHiragana はひらがな＋ー を返す（カタカナにはしない）
		$this->assertSame('べんちまーく', $this->romaji->toHiragana('benchima-ku'));
		$result = $this->romaji->toHiragana('ko-hi-');
		$this->assertNotEmpty($result);
		$this->assertStringContainsString('ー', $result);
	}

	// =========================================================
	// 実文変換
	// =========================================================

	#[DataProvider('realSentenceProvider')]
	public function testRealSentences(string $input, string $expected) : void{
		$this->assertSame($expected, $this->romaji->toHiragana($input));
	}

	public static function realSentenceProvider() : array{
		return [
			'きのう' => ['kinou', 'きのう'],
			'すきやき' => ['sukiyaki', 'すきやき'],
			'とうきょう' => ['toukyou', 'とうきょう'],
			'にほんご' => ['nihongo', 'にほんご'],
			'べんきょう' => ['benkyou', 'べんきょう'],
			'ながいぶん' => ['nagaibun', 'ながいぶん'],
			'こうそく' => ['kousoku', 'こうそく'],
		];
	}

	// =========================================================
	// エッジケース
	// =========================================================

	public function testEmptyString() : void{
		$this->assertSame('', $this->romaji->toHiragana(''));
	}

	public function testSymbolsPassthrough() : void{
		// ',' → '，'（全角変換される仕様）
		$result = $this->romaji->toHiragana('hello,world');
		$this->assertStringContainsString('，', $result);
	}

	public function testNumbersDeletedByDefault() : void{
		// removeIllegalFlag=true（デフォルト）では数字は削除される仕様
		$result = $this->romaji->toHiragana('123');
		$this->assertSame('', $result);
	}

	public function testNumbersKeptWhenFlagFalse() : void{
		// removeIllegalFlag=false では数字が保持される
		$result = $this->romaji->toHiragana('123', false);
		$this->assertSame('123', $result);
	}

	public function testMixedInputWithSymbol() : void{
		// ',' → '，' に変換される
		$result = $this->romaji->toHiragana('kinounoyorugohannha,sukiyakidesu');
		$this->assertStringContainsString('きのう', $result);
		$this->assertStringContainsString('，', $result);  // 全角カンマ
		$this->assertStringContainsString('すきやき', $result);
	}

	public function testLongString() : void{
		$long = str_repeat('aiueo', 200);
		$result = $this->romaji->toHiragana($long);
		$this->assertSame(str_repeat('あいうえお', 200), $result);
	}

	public function testUppercaseNormalized() : void{
		// 大文字は小文字に正規化される
		$lower = $this->romaji->toHiragana('kinou');
		$upper = $this->romaji->toHiragana('KINOU');
		$this->assertSame($lower, $upper);
	}

	public function testConsecutiveN() : void{
		// nn → ん（次の文字が母音でも確定）
		$this->assertSame('あんに', $this->romaji->toHiragana('annni'));   // n+ni
		$this->assertSame('あんい', $this->romaji->toHiragana("anni")); // n' で明示
		$this->assertSame('しんねん', $this->romaji->toHiragana('sinnnen'));
		// nnn → ん + n（残りのnは次ループ）
		$result = $this->romaji->toHiragana('sinnnen');
		$this->assertStringContainsString('ん', $result);
	}

	public function testToKatakana() : void{
		// toKatakana はひらがな→カタカナ変換
		$this->assertSame('ベンチマーク', $this->romaji->toKatakana('benchima-ku'));
		$this->assertSame('キノウ', $this->romaji->toKatakana('kinou'));
	}
}