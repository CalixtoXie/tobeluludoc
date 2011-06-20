<?php
/*
12580商盟论坛 定制方法文件
*/

//生成本地定制COOKIE信息
function customSetCookie($_cookies=array(),$_isPersist=false){
	global $customCookie;
	foreach($_cookies as $key => $var){
		setcookie($key,$var,($_isPersist?$customCookie['expriseDate']:$customCookie['expriseDateDefault']),$customCookie['path'],$customCookie['domain']);
		//setcookie($key,$var,0,'/');
	}
}

//获得用户地区
function customGetAreaCode (){
	global $customCookie;
	global $customCache;
	global $customAreaCodeCache;
	
	$areacode = "";//$customCookie['defaultAreaCode'];
	
	//当未指定城市时
	if($_COOKIE[$customCookie['areaCode']]==''){
		//当是登录会员时
		if($_SESSION['areacode'] != ''){
			$areacode = $_SESSION['areacode'];
			//echo '0-'.$areacode;
		}
		//当未知用户城市时
		else{
			include_once $customCache['ipAccessPath'];
			$ip = new ipaccess;
			$customClientIP = $ip->query($ip->getIp());
			if(isset($customAreaCodeCache[$customClientIP])){
				$areacode = $customClientIP;
				//echo '1-'.$areacode.','.$ip->getIp();
			}
			else{
				$areacode = $customCookie['defaultAreaCode'];
				//echo '2-'.$areacode;
			}
			
		}
		customSetCookie(array('areaCode'=>$areacode),false);
	}
	return $areacode;
}

//调用全局登出
function customLogout(){
		global $customCookie;
		customSetCookie(array($customCookie['ticket'] => ''),false);
		return customGetVerifyString('LOGOUT');
}

//转化参数数组为URL参数字符串
function customGetHttpParams($PARAMS){
	if(is_array($PARAMS)){
			$tmp = "?";
			foreach($PARAMS as $key => $var){
				$tmp .= ($tmp=='?'?'':'&') .$key .'='. $var;
			}
			return $tmp;
	}
	else{
		return $PARAMS;	
	}
	return '';
}

function customCreateLogFile($filename,$content){
	$f = @fopen($filename,"a+");
	@fwrite($f,str_repeat('#',50)."\n".date('Y-m-d H:i:s')."\n".$content."\n\n");
	@fclose($f);
}

//调用HTTP Client
function customHttpClient( $SERVER, $PATH, $COOKIES, $PARAMS){
		//return;//return '{"resHeader":{"backUrl":"","flag":"0","msg":"访问失败"}}';	
	
		//到远程服务器查询登录状态
		include_once dirname(__FILE__) . '/httpclient.inc.php';
		
		try{
			$client = new HttpClient($SERVER);
			$client->timeout = 10;
			$client->setCookies($COOKIES);
			$client->setPersistCookies(true);
			
			$postURL = $PATH;
			customCreateLogFile(dirname(__FILE__)."/httpclient_log/". date('Ymd') .".txt",getHexDecode($client->buildQueryString($PARAMS)));
			if(!$client->post($postURL,$PARAMS)){
				customCreateLogFile(dirname(__FILE__)."/httpclient_log/". date('Ymd') .".txt","\nPUT SERVER:".$SERVER."".$PATH."\nFAILD1");
				return '{"resHeader":{"backUrl":"","flag":"0","msg":"访问失败1"}}';			
			}
		}
		catch(Exception $e){
			customCreateLogFile(dirname(__FILE__)."/httpclient_log/". date('Ymd') .".txt","\nPUT SERVER:".$SERVER."".$PATH."\nFAILD2");
			return '{"resHeader":{"backUrl":"","flag":"0","msg":"访问失败2"}}';
		}
		
		$response = $client->getContent();
		customCreateLogFile(dirname(__FILE__)."/httpclient_log/". date('Ymd') .".txt",
				"\nPUT SERVER:".$SERVER."".$PATH.
				"\nPUT COOKIES:".(is_array($COOKIES)?var_export($COOKIES,true):'').
				"\nPUT DATA:".(is_array($PARAMS)?var_export($PARAMS,true):$PARAMS).
				"\nGET DATA:".iconv('GBK','UTF-8',$response)
		);
		
		return iconv('GBK','UTF-8',$response);
	
}

function printbox($content){
	//echo "<textarea style='width:100%;height:100px;'>";
	echo (is_array($content)?var_export($content,true):$content);
	//echo "</textarea>";
	echo "\n\n";
}

//申请积分扣减
function customApplyIntegral( $tmpUID,  $tmpCredits){
	global $db,$tablepre;
	
//	$row = $db->fetch_first("select c_terminalId from {$tablepre}members where uid='".intval($tmpUID)."'");
	$row = $db->fetch_first("select c_id from {$tablepre}members where uid='".intval($tmpUID)."'");
		
	if(!$row){
		return 1;
		return false;
	}
	/*
	else if($row['c_terminalId']==""){
		return 2;
		return false;
	}
	*/
	else if($row['c_id']==""){
		return 2;
		return false;
	}
	
	else{
		global $customMainSiteURL;
		
		//获得当前用户的手机号
//		$terminalId = $row['c_terminalId'];
		
		$lmsh_id = $row['c_id'];
		
		//配置请求相关参数
		$SERVER  = $customMainSiteURL['score']['SERVER'];
		$PATH    = $customMainSiteURL['score']['UPDATE_URL'];
		$COOKIES = null;//
		
		$PARAMS  = array(
			'SCORE'      => ($tmpCredits['SCORE']==''||$tmpCredits['SCORE']==0?"":$tmpCredits['SCORE']),
			'SCOREDESC'  => ($tmpCredits['SCOREDESC']==''?"":$tmpCredits['SCOREDESC'].($tmpCredits['SCORE']==''||$tmpCredits['SCORE']==0?"":((intval($tmpCredits['SCORE'])>0?'+':'').$tmpCredits['SCORE']))),
			'UB'         => ($tmpCredits['UB']==''||$tmpCredits['UB']==0?"":$tmpCredits['UB']),
			'UBDESC'     => ($tmpCredits['UBDESC']==''?"":$tmpCredits['UBDESC'].($tmpCredits['UB']==''||$tmpCredits['UB']==0?"":((intval($tmpCredits['UB'])>0?'+':'').$tmpCredits['UB']))),
			'TYPE'       => (empty($tmpCredits['TYPE'])?"0":$tmpCredits['TYPE']),
//			'TERMINALID' => $terminalId,
			'USER_ID' => $lmsh_id,
		);
		
		//编码 & 加密
		$PARAMS  = getHexEncode(json_encode($PARAMS));
		
		//请求积分接口
		$response = customHttpClient( $SERVER, $PATH, $COOKIES, $PARAMS);
		
		//处理积分返回数据
		if(is_array($res = json_decode($response,true))){   //当返回为JSON数据时
			if(isset($res['resHeader']['flag'])){             //当访问失败时
				return 3;
				return false;
			}
			else if(isset($res['SCORE'])){                    //当为积分返回数据时
				if($res['IP']=='-1'){                            //当IP不正常时
					return 4;
					return false;
				}
				else{
					return 5;
					return true;
				}
			}
			else{
				return 6;
				return false;
			}
		}
		else{
			return 7;
			return false;	
		}
	}
}


//获得鉴权信息
function customGetVerifyString($type='LOGIN'){
		global $customMainSiteURL;
		global $customCookie;
		
		//相关参数
		$SERVER = $customMainSiteURL['verify']['SERVER'];
		
		$PATH = $customMainSiteURL['verify'][$type.'_URL'];
		
		$COOKIES = array($customCookie['ticket'] => $_COOKIE[$customCookie['ticket']],);
		
		$cidHex = getHexEncode($customMainSiteURL['verify']['CID']);
		$PARAMS = array(
			'cid' => getHexEncode($customMainSiteURL['verify']['CID']),
			$customCookie['ticket'] => $_COOKIE[$customCookie['ticket']],
		);
		
		return customHttpClient( $customMainSiteURL['verify']['SERVER'], $PATH, $COOKIES, $PARAMS);
}

	//printbox("123123123");
	//printbox($_DCOOKIE['auth']);
	//printbox(daddslashes(explode("\t", authcode($_DCOOKIE['auth'], 'DECODE')), 1));

//进行鉴权操作
function customVerifyCurrent(){
	
	if($_REQUEST['action']=='logout'){
		return;
	}
	
	global $customMainSiteURL;
	global $customCookie;
	global $customCache;
	global $_DCOOKIE;
	
	//echo MD5(MD5('8458402').'k00wHf');
	/*
	global $uid;
	*/
	/*
	global $discuz_pw;
	global $discuz_secques;
	global $discuz_uid;
	global $discuz_user;
	global $discuz_ticket;
	global $adminid,$groupid;
	
	
	printbox(daddslashes(explode("\t", authcode($_DCOOKIE['auth'], 'DECODE')), 1));
	*/
	list($discuz_pw, $discuz_secques, $discuz_uid, $lmsh_ticket, $lmsh_version) = empty($_DCOOKIE['auth']) ? array('', '', 0) : daddslashes(explode("\t", authcode($_DCOOKIE['auth'], 'DECODE')), 1);
	//echo $discuz_uid;
	
	//if($_COOKIE[$customCookie['ticket']]!=''){
	//	echo "COOKIE[LMSH_SID]=".$_COOKIE[$customCookie['ticket']] .",AUTH[LMSH_SID]=".$lmsh_ticket;	
	//}
	$selfURL = $_SERVER['PHP_SELF'].($_SERVER['QUERY_STRING']!=''?'?'.$_SERVER['QUERY_STRING']:'');
	//$selfURL = $selfURL.(isset($_POST)?((strpos($selfURL,'?')===false?'?':'&').'POSTDATA='.json_encode($_POST)):'');
	
	if($_COOKIE[$customCookie['ticket']]=='' && $discuz_uid != 0){
		require_once DISCUZ_ROOT.'./include/misc.func.php';
		require_once DISCUZ_ROOT.'./include/login.func.php';
		require_once DISCUZ_ROOT.'./uc_client/client.php';
		
		$ucsynlogout = $allowsynlogin ? uc_user_synlogout() : '';
		clearcookies();
		/*
		global $discuz_uid, $discuz_user, $discuz_pw, $discuz_secques, $adminid, $credits;
		foreach(array('sid', 'auth', 'visitedfid', 'onlinedetail', 'loginuser', 'activationauth', 'indextype') as $k) {
			dsetcookie($k);
		}
		$discuz_uid = $adminid = $credits = 0;
		$discuz_user = $discuz_pw = $discuz_secques = '';
		*/
		
		
		//stopPage("123");
		$groupid = 7;
		$discuz_uid = 0;
		$discuz_user = $discuz_pw = '';
		$styleid = $_DCACHE['settings']['styleid'];
		
		//echo "000";
		//exit();
		//list($discuz_pw, $discuz_secques, $discuz_uid, $lmsh_ticket, $lmsh_version) = empty($_DCOOKIE['auth']) ? array('', '', 0) : daddslashes(explode("\t", authcode($_DCOOKIE['auth'], 'DECODE')), 1);
		//echo $selfURL.'<br>';
		//echo $discuz_uid.'<br>';
		header('Location:'.$selfURL);
	}
	/*
	*/
	//updatesession();
	
	if($discuz_uid!=0){
		//echo ",已登录";
		//判断ticket值
		//如果当前的ticket值与登录的ticket值不一致，重新登录
		if(($_COOKIE[$customCookie['ticket']] != '' && $_COOKIE[$customCookie['ticket']] != $lmsh_ticket) 
				|| ($_COOKIE[$customCookie['version']] != '' && $_COOKIE[$customCookie['version']] != $lmsh_version )){
				customExecuteUser(customGetVerifyString());
				//echo "<script>alert('1,$lmsh_ticket');</script>";	
				//clearcookies();
				//header("location:./custom/login.php?redirect=".urlencode($selfURL));
		}
	}
	//本地未登录
	else if($_COOKIE[$customCookie['ticket']] != ''){
		//echo ",未登录";
		customExecuteUser(customGetVerifyString());
		//clearcookies();
		//header("location:./custom/login.php?redirect=".urlencode($selfURL));
	}
	//echo "000";
	//echo $discuz_uid;
	if(!$discuz_uid)
		list($discuz_pw, $discuz_secques, $discuz_uid, $lmsh_ticket, $lmsh_version) = empty($_DCOOKIE['auth']) ? array('', '', 0) : daddslashes(explode("\t", authcode($_DCOOKIE['auth'], 'DECODE')), 1);
	//updatesession();
	//if($_COOKIE[$customCookie['ticket']]!='' && empty($_DCOOKIE['auth'])){
		//echo "<script>alert('1,$discuz_uid');location.href=location.href;</script>";	
	//}
		//echo "<script>alert('1,$discuz_uid');</script>";	
}

/*
{
	"resHeader":{
		"backUrl":"",
		"flag":"1",
		"msg":"成功获取用户账户信息"
	},
	"resBody":{
		"userInfo":{
			"areaCode":"025",
			"birthday":"",
			"commentCnt":0,
			"customId":"",
			"customUser":false,
			"effect":0,
			"email":"",
			"forever":false,
			"mark":"",
			"nickName":"小豆豆",
			"opened":0,
			"operatorCode":"JSYD",
			"qq":"",
			"regTime":"20100823152813",
			"score":18,
			"sex":0,
			"signature":"",
			"status":1,
			"terminalId":"15100000000",
			"updateTime":"20100823152813",
			"userPhoto":""
		}
	}
}
*/

function stopPage($msg = "",$flag = 0){
if($msg != ''||is_array($msg)) echo "##############<br>".(is_array($msg)?var_export($msg,ture):$msg)."<br>###############<br>";
	if(strpos($_SERVER['PHP_SELF'],"/custom/login.php")===false && strpos($_SERVER['PHP_SELF'],"logging.php")===false){
		if($flag == 0) exit;
	}
}

//创建/更新用户信息，并模拟登录
function customExecuteUser($logins){
	global $db, $tablepre, $timestamp, $onlineip, $initcredits, $cookietime;
	global $customCookie,$customSetting;
	
	$users=json_decode($logins,true);

	if(!is_array($users)){
		return;	
	}
	
	$idstring = random(6);
	$secques = random(8);
	$authstr = $regverify == 1 ? "$timestamp\t2\t$idstring" : '';
	$uid = 0;
	
	if($users['resHeader']['flag']=='1' && isset($users['resBody']['userInfo']['id'])) {
		
		$userInfo = $users['resBody']['userInfo'];
		$randPassword = rand(10000000,99999999).rand(10000000,99999999).rand(10000000,99999999).rand(10000000,99999999);
		
		$username = ($userInfo['nickName']==''?'　':mysql_escape_string($userInfo['nickName']));
		
		//通过用户ID查询是否在论坛注册
		if (isset($users['resBody']['userInfo']['id']))
			$row=$db->fetch_first("select uid from {$tablepre}members where c_id='".$userInfo['id']."'");
		
		if(!$row){
			//$uid = uc_user_register($username, $randPassword, "", "0", "", $onlineip);	
			
			$salt = substr(uniqid(rand()), -6);
			$password = md5(md5($password).$salt);
			$db->query("INSERT INTO {$tablepre}uc_members SET secques='', username='$username', password='". md5(md5($password).$salt) ."', email='$email', regip='$onlineip', regdate='".time()."', salt='$salt'");
			$uid = $db->insert_id();
			$db->query("INSERT INTO {$tablepre}uc_memberfields SET uid='$uid'");
			
			$idstring = random(6);
			$secques = '';
			$authstr = $regverify == 1 ? "$timestamp\t2\t$idstring" : '';
			
			$password = md5(random(10));
			$groupid = 10;
			
			$score = $userInfo['score'];
			$ub = $userInfo['ubScore'];
			$email = $userInfo['email'];
	
			$db->query("INSERT INTO {$tablepre}members 
				(uid, username, password, secques, adminid, groupid, regip, regdate, lastvisit, lastactivity, posts, credits, extcredits1, extcredits2, extcredits3, extcredits4, extcredits5, extcredits6, extcredits7, extcredits8, email, showemail, timeoffset, pmsound, invisible, newsletter,c_terminalid,c_id)
				VALUES ('$uid', '$username', '$password', '$secques', '0', '$groupid', '$onlineip', '$timestamp', '$timestamp', '$timestamp', '0', '0','0','0','0','0','0','0','0','0' , '$email', '0', '9999', '1', '0', '1','".$userInfo['terminalId']."','".$userInfo['id']."')");
			
			$db->query("UPDATE {$tablepre}members SET
				c_areacode = '$userInfo[areaCode]', 
				bday = '".($userInfo['birthday']==''?'0000-00-00':$userInfo['birthday'])."',
				c_commentcnt = '$userInfo[commentCnt]', 
				c_customid = '".$userInfo['customId']."', 
				c_customuser = '".($userInfo['customUser']?1:0)."',
				c_effect = '".intval($userInfo['effect'])."', 
				c_forever = '".($userInfo['forever']?1:0)."', 
				c_mark = '".$userInfo['mark']."', 
				c_opened = '".intval($userInfo['opened'])."', 
				c_operatorcode = '".$userInfo['operatorCode']."',
				c_regTime = '".$userInfo['regTime']."', 
				c_score = '".$userInfo['score']."', 
				c_sex = '".$userInfo['sex']."',
				c_signature = '".$userInfo['signature']."',
				c_status = '".$userInfo['status']."', 
				c_terminalid = '".$userInfo['terminalId']."',
				c_id = '".$userInfo['id']."', 
				c_updatetime = '".$userInfo['updateTime']."',
				c_userphoto = '".$userInfo['userPhoto']."'
				".($customSetting['field']['score']!=''?", ". $customSetting['field']['score'] ." = '".intval($userInfo['score'])."'":"") ."
				".($customSetting['field']['ub']!=''?", ". $customSetting['field']['ub'] ." = '".intval($userInfo['ubScore'])."'":"") ."
			WHERE uid = '$uid'
			");
			
			$db->query("REPLACE INTO {$tablepre}memberfields (uid, nickName, qq) VALUES ('$uid', '". mysql_escape_string($userInfo['nickName']) ."','". mysql_escape_string($userInfo['qq']) ."')");
	
			//require_once DISCUZ_ROOT.'./include/cache.func.php';
			//$_DCACHE['settings']['totalmembers']++;
			//updatesettings();
		
			//manyoulog('user', $discuz_uid, 'add');
		}
		else{
			$uid = $row['uid'];
		}
		
			//clearcookies();
		
			$db->query("UPDATE {$tablepre}members SET
				username = '$username',
				password = '". md5(random(10)) ."',
				c_areacode = '$userInfo[areaCode]', 
				bday = '".($userInfo['birthday']==''?'0000-00-00':$userInfo['birthday'])."',
				c_commentcnt = '$userInfo[commentCnt]', 
				c_customid = '".intval($userInfo['customId'])."', 
				c_customuser = '".($userInfo['customUser']?1:0)."',
				c_effect = '".intval($userInfo['effect'])."', 
				c_forever = '".($userInfo['forever']?1:0)."', 
				c_mark = '".$userInfo['mark']."', 
				c_opened = '".intval($userInfo['opened'])."', 
				c_operatorcode = '".$userInfo['operatorCode']."',
				c_regTime = '".$userInfo['regTime']."', 
				c_score = '".$userInfo['score']."', 
				c_sex = '".$userInfo['sex']."',
				c_signature = '".$userInfo['signature']."',
				c_status = '".$userInfo['status']."', 
				c_terminalid = '".$userInfo['terminalId']."', 
				c_id = '".$userInfo['id']."', 
				c_updatetime = '".$userInfo['updateTime']."',
				c_userphoto = '".$userInfo['userPhoto']."'
				".($customSetting['field']['score']!=''?", ". $customSetting['field']['score'] ." = '".intval($userInfo['score'])."'":"") ."
				".($customSetting['field']['ub']!=''?", ". $customSetting['field']['ub'] ." = '".intval($userInfo['ubScore'])."'":"") ."
			WHERE uid = '$uid'
			");
			
			$db->query("REPLACE INTO {$tablepre}memberfields (uid, authstr, nickName, qq) VALUES ('$uid', '$authstr', '". mysql_escape_string($userInfo['nickName']) ."','". mysql_escape_string($userInfo['qq']) ."')");
	
		/********************************
		模拟登录
		*/
		
		//echo $uid;
		//exit;
		$uid=$uid>0?$uid:$row['uid'];
		
		
		$salt = random(6);
		$db->query("UPDATE {$tablepre}uc_members SET password = '". MD5($userInfo['userPass'].$salt) ."', salt = '". $salt ."' WHERE uid = $uid");
			
		$member = $db->fetch_first("SELECT m.uid AS discuz_uid, m.username AS discuz_user, m.password AS discuz_pw, m.secques AS discuz_secques,
		m.email, m.adminid, m.groupid, m.styleid, m.lastvisit, m.lastpost, u.allowinvisible
		FROM {$tablepre}members m LEFT JOIN {$tablepre}usergroups u USING (groupid)
		WHERE m.uid='$uid'");
		
		$member['discuz_userss'] = $member['discuz_user'];
		$member['discuz_user'] = addslashes($member['discuz_user']);
		foreach($member as $var => $value) {
			$GLOBALS[$var] = $value;
		}

		if(empty($member['discuz_secques'])) {
			$member['discuz_secques'] = random(8);
			$GLOBALS['discuz_secques'] = $member['discuz_secques'];
			$db->query("UPDATE {$tablepre}members SET secques='$GLOBALS[discuz_secques]' WHERE uid='$uid'");
		}
		
		$cookietime = intval(isset($_POST['cookietime']) ? $_POST['cookietime'] : 0);
		
		//customSetCookie(array($customCookie['ticket'] => $users['ticket']));
		/*
		customSetCookie(array(
			'bkO_auth' => authcode("$member[discuz_pw]\t$member[discuz_secques]\t$member[discuz_uid]"."\t". $users['ticket']."\t". $userInfo['version'], 'ENCODE'),
		));
		*/
		dsetcookie('cookietime', $cookietime, 31536000);
		dsetcookie('auth', authcode("$member[discuz_pw]\t$member[discuz_secques]\t$member[discuz_uid]"."\t". $users['ticket']."\t". $userInfo['version'], 'ENCODE'), $cookietime, 1, true);
		dsetcookie('loginuser');
		dsetcookie('activationauth');
		dsetcookie('pmnum');
		
		$GLOBALS['sessionexists'] = 0;
		updatesession();
		
		//会话内容：$discuz_pw, $discuz_secques, $discuz_uid, $discuz_ticket
		//dsetcookie('auth', authcode("\t\t$uid\t". $unm ."\t".$groupid."\t". $_COOKIE[$customCookie['ticket']], 'ENCODE'), $cookietime, 1, true);
		//list($discuz_pw, $discuz_secques, $discuz_uid) = empty($_DCOOKIE['auth']) ? array('', '', 0) : daddslashes(explode("\t", authcode($_DCOOKIE['auth'], 'DECODE')), 1);
		//echo str_repeat("#",10)."".$member[discuz_pw].','.$member[discuz_secques].','.$member[discuz_uid]."<br>";
		/*
		模拟登录
		********************************/
		
		//写ticket
		//customSetCookie(array($customCookie['ticket']=>$userInfo['ticket']),false);
		
		//由于COOKIE需要刷新后生效，所以这里如果登录成功了，则将当前页面重新进行转向
		//echo "<hr>".$row[uid]."<br>".$_SERVER['PHP_SELF'].($_SERVER['QUERY_STRING']!=''?'?'.$_SERVER['QUERY_STRING']:'')."<hr>";
		$selfURL = $_SERVER['PHP_SELF'].($_SERVER['QUERY_STRING']!=''?'?'.$_SERVER['QUERY_STRING']:'');//.'&'. random(15) :''.'?'. random(15));
		
		//$selfURL = trim($_REQUEST['redirect']) == '' ? "./" : $_REQUEST['redirect'];
		
		//echo "#". $userInfo['terminalId'] ."#".$row['uid']."#". $userInfo['terminalId'] ."#".$member[discuz_uid]."##";
		//exit;
		//echo "<script>window.location.href='$selfURL'</script>";
		
		//echo $selfURL.",".random(15)."<br>";
		//exit;
		header('Location:'.$selfURL);
		
		if($pos = strpos($_REQUEST['redirect'],'POSTDATA') === false){
			header('Location:'.$selfURL);
		}
		else{
			$postdata = substr($_REQUEST['redirect'],$pos,strlen($_REQUEST['redirect'])-$pos);
			//echo "<form act='". $_REQUEST['redirect'] ."'"
			echo $postdata;
		}
		
		//header('Refresh: 0; '. $selfURL);
		
		//echo "<script>alert(0);window.location.href = window.location.href;</script>";
		//exit;
		//如果用户为第三方平台用户,未在网站绑定过手机号码
	}
	else{
		//die("用户没有登录");
	}
}

	//dsetcookie('auth', authcode("$member[discuz_pw]\t$member[discuz_secques]\t$member[discuz_uid]", 'ENCODE'), $cookietime, 1, true);



//本地能用HEX编码/解码程序
function getHexEncode($str){
		$str2hex = new SingleHexDecCrypt;
		return $str2hex->getValue('encode',base64_encode($str));
		unset($str2hex);
}
function getHexDecode($str){
		$str2hex = new SingleHexDecCrypt;
		return base64_decode($str2hex->getValue('decode',$str));
		unset($str2hex);
}
class SingleHexDecCrypt{
		function SingleHexDecCrypt(){
		}
		
		function getValue($type,$str){
			switch($type){
				case 'encode':
					return $this->SetToHexString($str);
					break;
				case 'decode':
					return $this->UnsetFromHexString($str);
					break;
				default:
					return '';
			}			
		}
    
    function SingleDecToHex($dec){
        $tmp="";
        $dec=$dec%16;
        if($dec<10)    return $tmp.$dec;
        $arr=array("a","b","c","d","e","f");
        return $tmp.$arr[$dec-10];
    }
    
    function SingleHexToDec($hex){
        $v=Ord($hex);
        if(47<$v&&$v<58)
        return $v-48;
        if(96<$v&&$v<103)
        return $v-87;
    }
    
    function SetToHexString($str){
        if(!$str)return false;
        $tmp="";
        for($i=0;$i<strlen($str);$i++){
            $ord=Ord($str[$i]);
            $tmp.= $this->SingleDecToHex(($ord-$ord%16)/16);
            $tmp.= $this->SingleDecToHex($ord%16);
        }
        return $tmp;
    }
    
    function UnsetFromHexString($str){
        if(!$str)return false;
        $tmp="";
        for($i=0;$i<strlen($str);$i+=2){
        	$tmp.=chr($this->SingleHexToDec(substr($str,$i,1)) * 16 + $this->SingleHexToDec(substr($str,$i+1,1)));
        }
        return $tmp;
    } 
}
	
?>