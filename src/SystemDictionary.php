<?php

declare(strict_types=1);


namespace kanakanjiconverter;

final class SystemDictionary{
	public static function apply(PHPKanaKanjiConverter $converter) : void{
		$dict = new UserDictionary();
		$dict->addAll([
			['reading' => 'test',       'surface' => 'テスト',  'mode' => UserDictionary::MODE_REPLACE, 'word_cost' => -5000, 'pos' => "名詞"],
		]);
		$converter->registerUserDict("system_replace", $dict);


		$NOUN_GENERAL = 1852/* findPosId(id.def, '名詞','一般') 実値 */;//1852 名詞,一般,*,*,*,*,*
		$ADVERB_GENERAL = 12/* '副詞','一般' の id */;//12 副詞,一般,*,*,*,*,*
		$NOUN_PRONOUN = 1900/* '名詞','代名詞' の id */;//1900 名詞,代名詞,一般,*,*,*,*
		$PROPER_NOUN = 1921 /* 固有名詞のid */;//1921 名詞,固有名詞,一般,*,*,*,*
		$CASE_PARTICLE = 368/*助詞の id*/; //368 助詞,格助詞,一般,*,*,*,から
		$VERB_INDEPENDENT = 577;
		// 安全な word_cost に調整した例
		$dict->addAll([
			[
				'reading'   => 'tikakude',
				'surface'   => '近くで',
				'mode'      => UserDictionary::MODE_SERVER,
				'word_cost' => -1000,
				'pos'       => '副詞',
				'subpos'    => '一般',
				'pos_label' => '副詞-一般',
				'left_id'   => $ADVERB_GENERAL,
				'right_id'  => $ADVERB_GENERAL,
			],

			[
				'reading'   => 'daare',
				'surface'   => 'だあれ',
				'mode'      => UserDictionary::MODE_SERVER,
				'word_cost' => -1200,
				'pos'       => '名詞',
				'subpos'    => '代名詞',
				'pos_label' => '名詞-代名詞',
				'left_id'   => $NOUN_PRONOUN,
				'right_id'  => $NOUN_PRONOUN,
			],

			[
				'reading'   => 'oite',
				'surface'   => 'おいて',
				'mode'      => UserDictionary::MODE_SERVER,
				'word_cost' => -800,
				'pos'       => '助詞',
				'subpos'    => '格助詞',
				'pos_label' => '助詞-格助詞',
				'left_id'   => $CASE_PARTICLE,
				'right_id'  => $CASE_PARTICLE,
			],

			[
				'reading'   => 'masumasu',
				'surface'   => 'ますます',
				'mode'      => UserDictionary::MODE_SERVER,
				'word_cost' => -1500,
				'pos'       => '副詞',
				'subpos'    => '一般',
				'pos_label' => '副詞-一般',
				'left_id'   => $ADVERB_GENERAL,
				'right_id'  => $ADVERB_GENERAL,
			],

			[
				'reading'   => 'banngohann',
				'surface'   => '晩ご飯',
				'mode'      => UserDictionary::MODE_SERVER,
				'word_cost' => -1800,
				'pos'       => '名詞',
				'subpos'    => '一般',
				'pos_label' => '名詞-一般',
				'left_id'   => $NOUN_GENERAL,
				'right_id'  => $NOUN_GENERAL,
			],

			[
				'reading'   => 'hataite',
				'surface'   => 'はたいて',
				'mode'      => UserDictionary::MODE_SERVER,
				'word_cost' => -800,
				'pos'       => '動詞',
				'subpos'    => '自立',
				'pos_label' => '動詞-自立',
				'left_id'   => $VERB_INDEPENDENT,
				'right_id'  => $VERB_INDEPENDENT,
			],
			[
				'reading'   => 'anni',
				'surface'   => 'Annihilation',
				'mode'      => UserDictionary::MODE_SERVER,
				'word_cost' => 2000,
				'pos'       => '名詞',
				'subpos'    => '一般',
				'pos_label' => '名詞-一般',
				'left_id'   => 1852,
				'right_id'  => 1852,
			],
			[
				'reading'   => 'maaiiya',
				'surface'   => 'まあいいや',
				'mode'      => UserDictionary::MODE_SERVER,
				'word_cost' => 1000,
				'pos'       => '名詞',
				'subpos'    => '一般',
				'pos_label' => '名詞-一般',
				'left_id'   => 1852,
				'right_id'  => 1852,
			],
			[
				'reading'   => 'maaiiyo',
				'surface'   => 'まあいいよ',
				'mode'      => UserDictionary::MODE_SERVER,
				'word_cost' => 1000,
				'pos'       => '名詞',
				'subpos'    => '一般',
				'pos_label' => '名詞-一般',
				'left_id'   => 1852,
				'right_id'  => 1852,
			],
			[
				'reading'   => 'ai',
				'surface'   => 'AI',
				'mode'      => UserDictionary::MODE_SERVER,
				'word_cost' => 2000,
				'pos'       => '名詞',
				'subpos'    => '一般',
				'pos_label' => '名詞-一般',
				'left_id'   => 1852,
				'right_id'  => 1852,
			],
			[
				'reading'   => 'ime',
				'surface'   => 'IME',
				'mode'      => UserDictionary::MODE_SERVER,
				'word_cost' => 2000,
				'pos'       => '名詞',
				'subpos'    => '一般',
				'pos_label' => '名詞-一般',
				'left_id'   => 1852,
				'right_id'  => 1852,
			],
			[
				'reading'   => 'op',
				'surface'   => 'Operator',
				'mode'      => UserDictionary::MODE_SERVER,
				'word_cost' => 2000,
				'pos'       => '名詞',
				'subpos'    => '一般',
				'pos_label' => '名詞-一般',
				'left_id'   => 1852,
				'right_id'  => 1852,
			],
			[
				'reading'   => 'unn',
				'surface'   => 'うん',
				'mode'      => UserDictionary::MODE_SERVER,
				'word_cost' => 2000,
				'pos'       => '名詞',
				'subpos'    => '一般',
				'pos_label' => '名詞-一般',
				'left_id'   => 1852,
				'right_id'  => 1852,
			],
			[
				'reading'   => 'unnsouda',
				'surface'   => 'うんそうだ',
				'mode'      => UserDictionary::MODE_SERVER,
				'word_cost' => 2000,
				'pos'       => '名詞',
				'subpos'    => '一般',
				'pos_label' => '名詞-一般',
				'left_id'   => 1852,
				'right_id'  => 1852,
			],
		]);

		$converter->registerUserDict("system_server", $dict);
	}
}
