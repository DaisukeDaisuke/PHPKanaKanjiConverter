<?php

declare(strict_types=1);


namespace kanakanjiconverter;

/**
 * ローマ字変換＋簡易かな→漢字変換の実装例
 *
 * 注意:
 * - 本実装は簡易実演用です。実用の精度を上げるには大辞書や形態素解析器（MeCab 等）との連携を推奨します。
 *
 * @internal
 */
final class ConvertibleRomaji{

	// 変換辞書（長い候補を優先して扱うことを前提）
	/**
	 * @var array<string, string>
	 */
	private static array $map = [
		// 2文字（子音+母音等）
		'ka' => 'か', 'ki' => 'き', 'ku' => 'く', 'ke' => 'け', 'ko' => 'こ',
		'sa' => 'さ', 'shi' => 'し', 'si' => 'し', 'su' => 'す', 'se' => 'せ', 'so' => 'そ',
		'ta' => 'た', 'chi' => 'ち', 'ti' => 'ち', 'tsu' => 'つ', 'tu' => 'つ', 'te' => 'て', 'to' => 'と',
		'na' => 'な', 'ni' => 'に', 'nu' => 'ぬ', 'ne' => 'ね', 'no' => 'の',
		'ha' => 'は', 'hi' => 'ひ', 'fu' => 'ふ', 'hu' => 'ふ', 'he' => 'へ', 'ho' => 'ほ',
		'ma' => 'ま', 'mi' => 'み', 'mu' => 'む', 'me' => 'め', 'mo' => 'も',
		'ya' => 'や', 'yu' => 'ゆ', 'yo' => 'よ',
		'ra' => 'ら', 'ri' => 'り', 'ru' => 'る', 're' => 'れ', 'ro' => 'ろ',
		'wa' => 'わ', 'wo' => 'を',
		'ga' => 'が', 'gi' => 'ぎ', 'gu' => 'ぐ', 'ge' => 'げ', 'go' => 'ご',
		'za' => 'ざ', 'ji' => 'じ', 'zu' => 'ず', 'ze' => 'ぜ', 'zo' => 'ぞ',
		'da' => 'だ', 'de' => 'で', 'do' => 'ど',
		'ba' => 'ば', 'bi' => 'び', 'bu' => 'ぶ', 'be' => 'べ', 'bo' => 'ぼ',
		'pa' => 'ぱ', 'pi' => 'ぴ', 'pu' => 'ぷ', 'pe' => 'ぺ', 'po' => 'ぽ',

		// 母音と単独子音
		'a' => 'あ', 'i' => 'い', 'u' => 'う', 'e' => 'え', 'o' => 'お',
		'n' => 'ん', 'm' => 'ん',

		// --- 小文字系（x / l 系） ---
		'xa' => 'ぁ', 'la' => 'ぁ',
		'xi' => 'ぃ', 'li' => 'ぃ',
		'xu' => 'ぅ', 'lu' => 'ぅ',
		'xe' => 'ぇ', 'le' => 'ぇ',
		'xo' => 'ぉ', 'lo' => 'ぉ',

		'xtu' => 'っ', 'ltu' => 'っ',

		'xya' => 'ゃ', 'lya' => 'ゃ',
		'xyu' => 'ゅ', 'lyu' => 'ゅ',
		'xyo' => 'ょ', 'lyo' => 'ょ',

		// 記号 半角 → 全角 追加分
		'!' => '！',
		'"' => '＂',
		'#' => '＃',
		'$' => '＄',
		'%' => '％',
		'&' => '＆',
		'\'' => '＇',
		'(' => '（',
		')' => '）',
		'*' => '＊',
		'+' => '＋',
		',' => '，',
		'-' => 'ー',   // 長音は既存仕様維持
		'.' => '．',
		'/' => '／',
		':' => '：',
		';' => '；',
		'<' => '＜',
		'=' => '＝',
		'>' => '＞',
		'?' => '？',
		'@' => '＠',
		'[' => '［',
		'\\' => '＼',
		']' => '］',
		'^' => '＾',
		'_' => '＿',
		'`' => '｀',
		'{' => '｛',
		'|' => '｜',
		'}' => '｝',
		'~' => '～',
		// --- ぢ / づ ---
		'di' => 'ぢ',
		'du' => 'づ',

		'va' => 'ゔぁ',
		'vi' => 'ゔぃ',
		'vu' => 'ゔ',
		've' => 'ゔぇ',
		'vo' => 'ゔぉ',

		'co' => 'こ',
		'ca' => 'か',
		'cu' => 'く',
		'ce' => 'せ',

		// --- 拡張拗音・短縮系 追加分 ---
		// K 系
		'kya' => 'きゃ', 'kyi' => 'きぃ', 'kyu' => 'きゅ', 'kye' => 'きぇ', 'kyo' => 'きょ',

		// Q 系（くぁ系）
		'qa' => 'くぁ', 'qi' => 'くぃ', 'qwu' => 'くぅ', 'qe' => 'くぇ', 'qo' => 'くぉ',

		// G 系
		'gya' => 'ぎゃ', 'gyi' => 'ぎぃ', 'gyu' => 'ぎゅ', 'gye' => 'ぎぇ', 'gyo' => 'ぎょ',

		// GWA 系
		'gwa' => 'ぐぁ', 'gwi' => 'ぐぃ', 'gwu' => 'ぐぅ', 'gwe' => 'ぐぇ', 'gwo' => 'ぐぉ',

		// S / SH 系
		'sya' => 'しゃ', 'syi' => 'しぃ', 'syu' => 'しゅ', 'sye' => 'しぇ', 'syo' => 'しょ',
		'sha' => 'しゃ', 'she' => 'しぇ', 'shu' => 'しゅ', 'sho' => 'しょ',

		// SW 系（すぁ系）
		'swa' => 'すぁ', 'swi' => 'すぃ', 'swu' => 'すぅ', 'swe' => 'すぇ', 'swo' => 'すぉ',

		// J / Z 系
		'ja'  => 'じゃ', 'jyi' => 'じぃ', 'ju'  => 'じゅ', 'je'  => 'じぇ', 'jo'  => 'じょ',
		'zya' => 'じゃ', 'zyi' => 'じぃ', 'zyu' => 'じゅ', 'zye' => 'じぇ', 'zyo' => 'じょ',

		// 互換入力用（よくある別表記）
		'zi' => 'じ',

		// T / CH 系
		'tya' => 'ちゃ', 'tyi' => 'ちぃ', 'tyu' => 'ちゅ', 'tye' => 'ちぇ', 'tyo' => 'ちょ',
		'cha' => 'ちゃ', 'che' => 'ちぇ', 'chu' => 'ちゅ', 'cho' => 'ちょ',

		// TH 系（てゃ系）
		'tha' => 'てゃ', 'thi' => 'てぃ', 'thu' => 'てゅ', 'the' => 'てぇ', 'tho' => 'てょ',

		// TW 系（とぁ系）
		'twa' => 'とぁ', 'twi' => 'とぃ', 'twu' => 'とぅ', 'twe' => 'とぇ', 'two' => 'とぉ',

		// DY 系（ぢゃ等）
		'dya' => 'ぢゃ', 'dyi' => 'ぢぃ', 'dyu' => 'ぢゅ', 'dye' => 'ぢぇ', 'dyo' => 'ぢょ',

		// DH 系（でゃ等）
		'dha' => 'でゃ', 'dhi' => 'でぃ', 'dhu' => 'でゅ', 'dhe' => 'でぇ', 'dho' => 'でょ',

		// DW 系（どぁ等）
		'dwa' => 'どぁ', 'dwi' => 'どぃ', 'dwu' => 'どぅ', 'dwe' => 'どぇ', 'dwo' => 'どぉ',

		// N 系
		'nya' => 'にゃ', 'nyi' => 'にぃ', 'nyu' => 'にゅ', 'nye' => 'にぇ', 'nyo' => 'にょ',

		// H 系
		'hya' => 'ひゃ', 'hyi' => 'ひぃ', 'hyu' => 'ひゅ', 'hye' => 'ひぇ', 'hyo' => 'ひょ',

		// F 系
		'fa'  => 'ふぁ', 'fi'  => 'ふぃ', 'fwu' => 'ふぅ', 'fe'  => 'ふぇ', 'fo'  => 'ふぉ',

		// B 系
		'bya' => 'びゃ', 'byi' => 'びぃ', 'byu' => 'びゅ', 'bye' => 'びぇ', 'byo' => 'びょ',

		// P 系
		'pya' => 'ぴゃ', 'pyi' => 'ぴぃ', 'pyu' => 'ぴゅ', 'pye' => 'ぴぇ', 'pyo' => 'ぴょ',

		// M 系
		'mya' => 'みゃ', 'myi' => 'みぃ', 'myu' => 'みゅ', 'mye' => 'みぇ', 'myo' => 'みょ',

		// R 系
		'rya' => 'りゃ', 'ryi' => 'りぃ', 'ryu' => 'りゅ', 'rye' => 'りぇ', 'ryo' => 'りょ',

		// W 系（うぁ/うぃ/うぇ/うぉ）
		'wha' => 'うぁ', 'wi' => 'うぃ', 'we' => 'うぇ', 'who' => 'うぉ',

	];

	public function __construct(){
	}

	/**
	 * ローマ字をひらがなに変換する（簡易ルール）
	 *
	 * $removeIllegalFlag: true の場合、変換できなかった文字は削る
	 */
	public function toHiragana(string $originText,bool $removeIllegalFlag = true){
		// 正規化: マクロン（ō 等） -> ou / uu のように簡易変換
		$norm = str_replace(
			['ā', 'ī', 'ū', 'ē', 'ō', 'Ā', 'Ī', 'Ū', 'Ē', 'Ō'],
			['aa', 'ii', 'uu', 'ee', 'ou', 'aa', 'ii', 'uu', 'ee', 'ou'],
			mb_strtolower($originText, 'UTF-8')
		);

		// 半角スペースや記号はそのままにするためトークン化（簡易）
		$tokens = preg_split('/(\s+)/u', $norm, -1, PREG_SPLIT_DELIM_CAPTURE);

		$out = '';
		foreach($tokens as $token){
			// 空白トークンそのまま
			if(preg_match('/^\s+$/u', $token)){
				$out .= $token;
				continue;
			}
			$out .= $this->convertTokenToHiragana($token, $removeIllegalFlag);
		}

		// 連続する「んん」を単一に正規化
		return str_replace("んん", "ん", $out);
	}

	private function convertTokenToHiragana($text, $removeIllegalFlag){
		$pos = 0;
		$len = mb_strlen($text, 'UTF-8');
		$res = '';

		// マップ中の最大キー長を計算して、上限4文字に制限する
		$maxMapLen = 0;
		foreach(array_keys(self::$map) as $k){
			$kl = mb_strlen($k, 'UTF-8');
			if($kl > $maxMapLen) $maxMapLen = $kl;
		}
		$maxKeyLen = min(4, $maxMapLen);

		// 比較は小文字前提で行う（出力時に元の文字は参照しない）
		$textLower = mb_strtolower($text, 'UTF-8');

		while($pos < $len){
			// 促音（っ）の処理: 同一子音が連続する場合
			$cur = mb_substr($textLower, $pos, 1, 'UTF-8');
			$next = ($pos + 1 < $len) ? mb_substr($textLower, $pos + 1, 1, 'UTF-8') : '';
			if($next !== '' && $cur === $next && preg_match('/[bcdfghjklmnpqrstvwxyz]/i', $cur)){
				// ただし n の場合は促音ではない（ん の可能性）
				if($cur !== 'n'){
					$res .= 'っ';
					$pos += 1; // 同一子音を一文字分スキップ
					continue;
				}
			}

			// --- 重要: 撥音 n の補正処理 ---
			// n の後が n の場合は「ん」として1文字消費する（nn -> ん + n の扱い）
			// n の後が母音 (a,i,u,e,o) または y の場合は合字の可能性があるためここで確定しない
			// それ以外（空白/末尾/子音）の場合は「ん」として処理する
			if($cur === 'n'){
				$nextChar = ($pos + 1 < $len) ? mb_substr($textLower, $pos + 1, 1, 'UTF-8') : '';
				if($nextChar === 'n'){
					// nn -> ん（2文字分消費して次のnをループに戻さない）
					// pos+=1 だと残ったnがni等にマッチするバグがあったため pos+=2 に修正
					$res .= 'ん';
					$pos += 2;
					continue;
				}
				// 次が母音または y の場合は 'na' や 'nya' 等を期待してマッチ試行に任せる
				if($nextChar === '' || !preg_match('/[aiueoy]/i', $nextChar)){
					// 次が母音/y ではない（子音か末尾） -> ここで ん として確定
					$res .= 'ん';
					$pos += 1;
					continue;
				}
				// それ以外は通常のマッチ処理に任せる（例: "na", "nya" 等）
			}

			// 長いものから順に照合（最長 $maxKeyLen 文字）
			$matched = false;
			for($l = min($maxKeyLen, $len - $pos); $l >= 1; $l--){
				$substr = mb_substr($textLower, $pos, $l, 'UTF-8');

				// 特殊なケース: "n'" のような撥音明示
				if($substr === "n'"){
					$res .= 'ん';
					$pos += $l;
					$matched = true;
					break;
				}

				if(isset(self::$map[$substr])){
					$res .= self::$map[$substr];
					$pos += $l;
					$matched = true;
					break;
				}
			}

			if(!$matched){
				// マッチしない場合: 英数字は削除/保持、その他はそのまま出力
				$ch = mb_substr($text, $pos, 1, 'UTF-8'); // 出力は元テキストから取得
				if(preg_match('/[a-z0-9]/i', $ch)){
					if($removeIllegalFlag){
						// 削る（何もしない）
					}else{
						$res .= $ch;
					}
				}else{
					$res .= $ch;
				}
				$pos += 1;
			}
		}

		return $res;
	}


	// toKatakana は原文テキストを受け取り toHiragana を通して変換するよう修正
	public function toKatakana(string $originText, $removeIllegalFlag = true){
		$hiragana = $this->toHiragana($originText, $removeIllegalFlag);
		// ひらがなをカタカナに（全角のひらがな範囲→カタカナ範囲へ）
		$out = '';
		$len = mb_strlen($hiragana, 'UTF-8');
		for($i = 0; $i < $len; $i++){
			$ch = mb_substr($hiragana, $i, 1, 'UTF-8');
			$code = mb_ord($ch, 'UTF-8');
			// ひらがな範囲: U+3041 - U+3096
			if($code >= 0x3041 && $code <= 0x3096){
				$out .= mb_chr($code + 0x60, 'UTF-8'); // カタカナはひらがなより0x60進んでいる
			}else{
				$out .= $ch;
			}
		}
		return $out;
	}
}
