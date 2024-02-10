<?php
//------------------------------------------------------------------------------
// HSP3NetModules (netmod.php)                                          Ver.1.1 
//------------------------------------------------------------------------------
error_reporting(E_ALL & ~E_NOTICE);

//定数
define("DataFileName", "hsp3netmodules.data");
//定数(エラーメッセージ)
define("NotFound_Datafile", "not_found__data_file");
define("NotFound_Modulefile", "not_found__module_file");
define("Bad_Request", "bad__request");
define("Bad_ModuleName", "bad__module_name");
define("Bad_GithubAccess", "bad__github_access");
define("Arledy_ModuleName", "arledy__module_name");
define("Found_Modulefile", "found__module_file");
define("NotFound_ModuleName", "not_found__module_name");
define("Failure_Update", "failure__update");

//リスエスト取得
$request = $_POST['req'];
if($_GET['req']!=""){ $request = $_GET['req']; }

do{ //処理開始

//$request ==
// 'data'
// 'html'
// 'post'
// 'delete'

//すべての登録情報を返す
if($request == 'data') {
	if( Get_ModulesData($data) ){
		echo $data;
	}//失敗したときはGet_ModulesData()の中でエラー詳細をechoしている
	break;

//すべての登録情報をHTMLとして返す
}else if($request == 'html') {
	if( Get_ModulesHtml($html) ){
		echo $html;
	}//失敗したときはGet_ModulesHtml()の中でエラー詳細をechoしている
	break;

//データ登録
}else if($request == 'post') {
	$hsp = $_POST['hsp'];
	if($_GET['hsp']!=""){ $hsp = $_GET['hsp']; }

	if( Register_PostModule($hsp) ){
		echo 'success';
	}//失敗したときはRegister_PostModule()の中でエラー詳細をechoしている
	break;
	
//データ削除
}else if($request == 'delete') {
	$hsp = $_POST['hsp'];
	if($_GET['hsp']!=""){ $hsp = $_GET['hsp']; }
	
	if( Register_DeleteModule($hsp) ){
		echo 'success';
	}//失敗したときはRegister_DeleteModule()の中でエラー詳細をechoしている
	break;

//リクエスト失敗
}else {
	echo 'false: '.Bad_Request.';';
	break;
}

}while(0); //処理終了


//------------------------------------------------------------------------------
// function
//------------------------------------------------------------------------------
function Get_ModulesData(&$data){
	//データファイル確認
	if( !file_exists(DataFileName) ){
		echo 'false: '.NotFound_Datafile.';';
		return FALSE;
	}
	$data = file_get_contents(DataFileName);
	$data = preg_replace('/{{JSON(.*?)JSON}}/','',$data); //後々、指定データを残すオプションを考えるかも。
	return TRUE;
}
function Get_ModulesHtml(&$html){
	//データファイル確認
	if( !file_exists(DataFileName) ){
		echo 'false: '.NotFound_Datafile.';';
		return FALSE;
	}
	$html = file_get_contents(DataFileName);
	$html = preg_replace('/{{JSON(.*?)JSON}}/','',$html); //後々、指定データを残すオプションを考えるかも。
	
	$html = preg_replace('/\r\n/',"<br>\n",$html);
	$html = preg_replace('/\t/'," - ",$html);
	$html = '<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>HSP3NetModules</title>
</head>
<body>' . $html . '</body>
</html>';
	return TRUE;
}
function Register_PostModule($hsp){
	//モジュール名分割
	if( !DevideRepMod($hsp, $repName, $modName) ){
		echo 'false: '.Bad_ModuleName.';';
		return FALSE;
	}
	//モジュールの存在を確認
	$code = curl_get("https://raw.githubusercontent.com/${repName}${modName}", $buf);
	if( $code != 200 ){
		echo 'false: '.NotFound_Modulefile.";=>https://raw.githubusercontent.com/${repName}${modName};";
		return FALSE;
	}
	//データファイル確認
	if( !file_exists(DataFileName) ){
		echo 'false: '.NotFound_Datafile.';';
		return FALSE;
	}
	$data = file_get_contents(DataFileName);
	
	//登録済みでないか確認
	$qmodName = preg_quote($modName);
	if( preg_match("<(?:^|\r\n)(${qmodName})\t(.*?)(?:{{JSON(.*?)JSON}})?(?:\r\n|$)>i", $data, $matches) ){
		if( $matches[2] != $repName ){
			echo 'false: '.Arledy_ModuleName.";=>${matches[2]}${matches[1]};";
			return FALSE;
		}else {
			//登録済みのリポジトリがpostされた => JSONデータ部の更新
			$json = json_decode($matches[3],true);
			$hsdata = GetHsData($buf);//hs情報
			return _Register_UpdateModule($repName, $modName, $data, $json, $hsdata);
		}
	}
	//登録！
	$wdata = "\r\n${modName}\t${repName}";  // 登録0の場合 $wdata = "${modName}\t${repName}"; としたいが省略
	$json = array('postDateU' => date("U"));
	$json = $json + GetHsData($buf);//hs情報追加
	$ejson = json_encode($json);
	$wdata .= "{{JSON${ejson}JSON}}";
	file_put_contents(DataFileName, $wdata, FILE_APPEND);
	return TRUE;
}
function _Register_UpdateModule($repName, $modName, $data, $json, $hsdata){
	$qmodName = preg_quote($modName);
	$qrepName = preg_quote($repName);
	if( $json["postDateU"] != null ){
		$json = array('postDateU' => $json["postDateU"]);
		$json = $json + array('updateDateU' => date("U"));
	}else {
		$json = array('postDateU' => date("U"));
	}
	$json = $json + $hsdata;
	$ejson = json_encode($json);
	$wdata = preg_replace("<(^|\r\n)(${qmodName}\t${qrepName}).*?(\r\n|$)>i", "$1$2{{JSON${ejson}JSON}}$3", $data);
	if( $wdata == $data ){
		echo 'false: '.Failure_Update.';';
		return FALSE;
	}//更新！
	file_put_contents(DataFileName, $wdata);
	return TRUE;
}
function Register_DeleteModule($hsp){
	//モジュール名分割
	if( !DevideRepMod($hsp, $repName, $modName) ){
		echo 'false: '.Bad_ModuleName.';';
		return FALSE;
	}
	//モジュールの存在を確認
	$code = curl_get('https://raw.githubusercontent.com/'.$hsp, $buf);
	if( $code == 200 ){
		//モジュールがまだあったら特殊削除方法も確認
		if( !CheckGithubRepAbout($repName, $modName, $error) ){
			if($error == ''){
				echo 'false: '.Found_Modulefile.";=>https://raw.githubusercontent.com/${hsp};";
			}else {
				echo $error;
			}
			return FALSE;
		}
	}
	//データファイル確認
	if( !file_exists(DataFileName) ){
		echo 'false: '.NotFound_Datafile.';';
		return FALSE;
	}
	$data = file_get_contents(DataFileName);
	
	//登録済みか確認
	$qmodName = preg_quote($modName);
	$qrepName = preg_quote($repName);
	$wdata = preg_replace("<(?:^|\r\n)${qmodName}\t${qrepName}.*?(\r\n|$)>i", "$1", $data);
	if( $wdata == $data ){
		echo 'false: '.NotFound_ModuleName.';';
		return FALSE;
	}
	//削除！
	file_put_contents(DataFileName, $wdata);
	return TRUE;
}

//------------------------------------------------------------------------------
// SubRoutine
//------------------------------------------------------------------------------
function curl_get($url, &$buf){
	$CURLERR = NULL;

    $ch = curl_init($url);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // 証明書の検証を行わない
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    $result = curl_exec($ch);
	if($result != false){
	    $header = curl_getinfo($ch);
	}
    if(curl_errno($ch)){        //curlでエラー発生
        $CURLERR .= 'curl_errno：' . curl_errno($ch) . "\n";
        $CURLERR .= 'curl_error：' . curl_error($ch) . "\n";
        $CURLERR .= '▼curl_getinfo' . "\n";
        foreach(curl_getinfo($ch) as $key => $val){
            $CURLERR .= '■' . $key . '：' . $val . "\n";
        }
        echo nl2br($CURLERR);
        return 0;
    }
    curl_close($ch);
	if($result == false){
		return 0;
	}
	$buf = $result;
    return $header['http_code'];
}
function DevideRepMod($hsp, &$repName, &$modName){
	if( !preg_match('/\.(?:hsp|as|dll|hpi)$/', $hsp, $matches) ){
		return FALSE;
	}
	if( !preg_match('|^([\w/%#$()~+-.]+/)([\w%#$()~+-.]+)$|', $hsp, $matches) ){
		return FALSE;
	}
	$repName = $matches[1];
	$modName = $matches[2];
	//二重区切りでmod名の区切り位置を制御できるようにする。
	if( preg_match('|^([\w/%#$()~+-.]+?/)/([\w/%#$()~+-.]*)$|', $repName, $matches) ){
		$repName = $matches[1];
		$modName = $matches[2] . $modName;
	}
	//二重区切りしてもrep名は「ユーザー/リポジトリ/」の形式は確保しないといけない。
	if( !preg_match('|^[\w%#$()~+-.]+/[\w%#$()~+-.]+/|', $repName) ){
		return FALSE;
	}
	return TRUE;
}
function GetHsData($script){
	$arr = array();
	$keys = array('dll','ver','author','date','note');
	foreach( $keys as $key ) {
		if( preg_match('#(?:^|\r\n)%'.$key.'.*?\r\n((?:.|\r\n)*?)(?:\*/|%|$)#i', $script, $matches) ){
			$arr[$key] = mb_convert_encoding( trim($matches[1]), "UTF-8", "SJIS" );
		}
	}
	return $arr;
}
function CheckGithubRepAbout($repName, $modName, &$error){
	$error = '';
	if( !preg_match('|^([\w%#$()~+-.]+/[\w%#$()~+-.]+)/|', $repName, $matches) ){
		$error = 'false: '.Bad_ModuleName.';';
		return FALSE;
	}
	$TrueRepName = $matches[1];
	//取得
	$code = curl_get("https://github.com/${TrueRepName}", $buf);
	if( $code != 200 ){
		$error = 'false: '.Bad_GithubAccess.";=>https://github.com/search?o=desc&s=updated&type=Repositories&q=${TrueRepName};";
		return FALSE;
	}
	if( !preg_match('#<meta name="description" content="(.*?)">#s',$buf, $matches) ){
		$error = 'false: '.Bad_GithubAccess.";=>https://github.com/search?o=desc&s=updated&type=Repositories&q=${TrueRepName};";
		return FALSE;
	}
	$qmodName = preg_quote($modName);
	if( preg_match("|#netinclude_delete\s*=\s*${qmodName}\s*;|i",htmlspecialchars_decode($matches[1])) ){
		return TRUE;
	}
	return FALSE;
}
