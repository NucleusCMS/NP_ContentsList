<?php

class UPDATE {
	function __construct() {

/**
  * 0.2からのバージョンアップの場合は、
  * 元の(0.2の)ファイルの設定部分を丸ごと以下に
  * 上書きで貼り付けてください
  * 
  */


// 貼り付けここから --------------------------------------------------

/*
***********************************************************
*  設定                                                   *
***********************************************************/
/* 表示したくないblogがある場合、()内にカンマ区切りで。
  [ショートネームではじきたい場合(''で囲ってください)]
    $hide = array('himitsu'); とか $hide =array('himitsu','test');
  [blogIDではじきたい場合]
    $hide =array(5) とか $hide = array(3,5);
*/
$hide = array();

/********************************************
[ 全体のテンプレート ]
     それぞれ $xxx = 'yyy'."\n"; の yyy部分を編集

本体には以下が使えます。(ヘッダとフッタはダメ)
  <%blogid%> ・・・ブログID
  <%blogurl%> ・・・ブログのURI
  <%blogname%> ・・・ブログの名前
  <%blogdesc%> ・・・ブログの説明
  <%categorylist%> ・・・そのブログのカテゴリーリスト
  <%flag%> ・・・そのブログが現在選択されている場合につく目印
  <%skinfile()%> ・・・Nucleusスキンと使い方一緒
********************************************/
// ヘッダ
$tp['header'] = '<ul class="nobullets">'."\n";
// 本体
$tp['list'] = '<li><a href="<%blogurl%>"<%flag%>><%blogname%></a><%categorylist%></li>'."\n";
// フッタ
$tp['footer'] = '</ul>'."\n";

/*******************************************
[ フラグのテンプレート ]
  <%skinfile()%>が使えます
********************************************/
//ブログのフラグ
$tp['flag'] = ' style="font-size:110%;font-weight:bold;text-decoration:none"';
//カテゴリーのフラグ
$tp['catflag'] =  ' style="list-style-type:square;font-weight:bold;"';

/*******************************************
[ カテゴリーリストのテンプレート ]
     それぞれ $xxx = 'yyy'."\n"; の yyy部分を編集

本体には以下が使えます。(ヘッダとフッタはダメ)
(Nucleusのカテゴリーリストテンプレで使えるのと同じもの + catflag +ammount)
  <%blogid%>,<%blogurl%>,<%self%>,
  <%catlink%>,<%catid%>,<%catname%>,<%catdesc%>
  <%catflag%> ・・・ 上で設定したテンプレートが使われます
  <%amount%> ・・・そのカテゴリのポスト数
********************************************/
// ヘッダ
$tp['catheader'] = '<ul style="line-height:150%;margin:5px 0px 15px 20px;padding:0px;">'."\n";
// 本体
$tp['catlist'] = '<li<%catflag%>><a href="<%catlink%>"><%catname%></a>(<%amount%>)</li>'."\n";
// フッタ
$tp['catfooter'] = '</ul>'."\n";

/*******************************************
[ リストの並び順 ]
********************************************/
/* ブログの並び順 */
$blogOrder = 'ORDER BY bnumber ASC'; /* ブログID昇順 */
//$blogOrder = 'ORDER BY bnumber DESC'; /* ブログID降順 */
//$blogOrder = 'ORDER BY bname ASC'; /* ブログ名昇順 */
//$blogOrder = 'ORDER BY bname DESC'; /* ブログ名降順 */

/* カテゴリーリストの並び順 */
$catOrder = 'ORDER BY c.catid ASC'; /* カテゴリID昇順 */
//$catOrder = 'ORDER BY c.catid DESC'; /* カテゴリID降順 */
//$catOrder = 'ORDER BY c.cname ASC'; /* カテゴリ名昇順 */
//$catOrder = 'ORDER BY c.cname DESC'; /* カテゴリ名降順 */

/**********************  設定ここまで **********************/


// 貼り付けここまで --------------------------------------------------


	$this->tp = $tp;
	if(count($hide) > 0){
		foreach($hide as $val){
			if(gettype($val) == 'string' && $val != ''){
				$blogids[] = getBlogIDFromName($val);
			}else{
				$blogids[] = intval($val);
			}
		}
		$hide = $blogids;
	}
	$this->hide = $hide;
	$this->blogOrder = preg_replace("/ORDER BY /","b.",$blogOrder);
	$this->catOrder = preg_replace("/ORDER BY /","",$catOrder);

	}


/** 
  * 2.function changeTemplate()で別のテンプレートの設定をしていた場合、
  *   それを丸ごと以下に上書きで貼り付けてください。
  *   今現在設定していない場合は、いじらないでください。
  */

// 貼り付けここから --------------------------------------------------
	
 	function changeTemplate($str,&$tp){
		switch($str){
			case 'example':
				$tp=array(
'header'=>'<ul class="nobullets">'."\n",

'list'=>'<li><a href="<%blogurl%>"<%flag%>><%blogname%></a><br />'."\n"
.'<%blogdesc%><br />'."\n"
.'<%categorylist%></li>'."\n",

'footer'=>'</ul>'."\n",

'catheader'=>'<ul style="line-height:150%;margin:5px 0px 0px 20px;padding:0px;">
'."\n",

'catlist'=>'<li<%catflag%>><a href="<%catlink%>"><%catname%></a>(<%amount%>)</li>'."\n",

'catfooter'=>'</ul><hr />'."\n",

'flag'=>' style="font-size:110%;font-weight:bold;text-decoration:none"',
'catflag'=>' style="list-style-type:square;font-weight:bold;"');
				break;
		}
	}

// 貼り付けここまで --------------------------------------------------


	function multipleTemplateSetting() {
		
/** 
  * 3.function changeTemplate()で別のテンプレートの設定をしていた場合、
  *   テンプレート名(case 'xxx': の「'xxx'」の部分)をカンマ区切りでカッコの
  *   中に記述してください。(ex. $tnames = array('map','foot');)
  *   今現在設定していない場合は、いじらないでください。
  */

$tnames = array('example');


/* ------------------------------------------------------------------
	  バージョンアップのための設定はこれで完了です。後は、一旦現在の
	  NP_ContentsListをアンインストール後、新しいNP_ContentsList.phpと
	  contentslistフォルダをNucleusのプラグインフォルダにアップロード
	  してから再インストールしてください。
------------------------------------------------------------------- */

// ------------------------------------------------------------------
		return $tnames;
	}
}
?>
