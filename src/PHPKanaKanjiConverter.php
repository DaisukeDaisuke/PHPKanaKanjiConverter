<?php

declare(strict_types=1);


namespace kanakanjiconverter;

final class PHPKanaKanjiConverter{

	private ConvertibleRomaji $romaji;
	private KanaKanjiConverter $kannziconverter;


	/**
	 * Constructor for initializing the ConvertibleRomaji instance and the KanaKanjiConverter with a dictionary path.
	 *
	 * @return void
	 */
	public function __construct(){
		$this->romaji = new ConvertibleRomaji();
		//$hiragana = $romaji->toHiragana(true);

		$dictDir = realpath(__DIR__ . '/dictionary_oss') ?: (__DIR__ . '/dictionary_oss');
		$this->kannziconverter = new KanaKanjiConverter($dictDir);
	}


	/**
	 * Converts the provided input string into Hiragana and then performs a Kana-Kanji conversion.
	 *
	 * @param string $input The input string to be converted.
	 * @param bool $removeIllegalFlag Optional flag to remove illegal characters during conversion. Default is false.
	 * @param int $numofbest The number of best conversion results to return. Default is 3.
	 *
	 * @return array{best: array{text: string, tokens: array<int, array{surface: string, reading: string, word_cost: string, penalty: string}>}, cost: int, candidates: list<array{surface: string, reading: string, word_cost: string, penalty: string}>} An array of conversion results after performing Kana-Kanji conversion.
	 */
	public function convert(string $input, bool $removeIllegalFlag = false, int $numofbest = 3) : array{
		$input = $this->romaji->toHiragana($input, $removeIllegalFlag);
		return $this->kannziconverter->convert($input, $numofbest);
	}

	public function isValid(array $result) : bool{
		if(isset($result["best"]["tokens"])){
			return false;
		}
		foreach ($result["best"]["tokens"] as $t) {
			if(!isset($t["pos"]) || !isset($t["subpos"])){
				return false;
			}
			if ($t["pos"] === "名詞" && $t["subpos"] !== "非自立") {
				return true;
			}

			if (in_array($t["pos"], ["動詞", "形容詞"])) {
				return true;
			}
		}

		return false;
	}

	public function getRomajiConverter() : ConvertibleRomaji{
		return $this->romaji;
	}
}