<?php 
require_once(dirname(dirname(dirname(__FILE__)))."/cplatform/utils/RandomUtil.php");
require_once(dirname(dirname(dirname(__FILE__)))."/cplatform/db/OraDbHelper.php");
require_once(dirname(dirname(dirname(__FILE__)))."/cplatform/conf/conf.php");


class PayService{

	/**
	 * 支付成功后的操作
	 * @param $team	团信息
	 * @param $order 订单信息
	 * @param $user	用户信息
	 * @param $area	地市信息
	 * @param $orderId	订单id
	 */
	public static function afterPayDoSomething(&$team,&$order,&$user,&$area,&$orderId,&$pay=''){
		//插入T_12580_uht_log		
      	OraDbHelper::insertTable("T_12580_uht_log",
      	               			array(	"id"=>OraDbHelper::getSequenceNextVal('SEQ_comm_id'),
                               			"name"=>mb_convert_encoding($team['title'],'GBK','UTF-8'),//订单名称
                               			"area_code"=>$area['area_code'],//订单归属地市   
										"num"=>$order['quantity'],//订单数量   
										"total_price"=>$order['money'],//总价
										"order_id"=>$order['id'],//订单id 
										"order_time"=>strftime('%Y%m%d%H%M%S',$team["create_time"]),//生成时间
										"terminal_id"=>$user["mobile"]
      	               			));
		if($team['coupon_rule']=='single'){
      		PayService::createValidateCode($team,$order,$user,$area,$orderId,$pay);
      	}else{
      		for($i=1;$i<=$order['quantity'];$i++){
      			PayService::createValidateCode($team,$order,$user,$area,$orderId,$pay);
      		}
      	}
			
	}
	
	/**
	 * 重新下发验证码
	 */
	public static function sms_coupon($coupon) {
		global $INI;
		global $conf;
		$coupon_user = Table::Fetch('user', $coupon['user_id']);
		if ( $coupon['consume'] == 'Y' 
				|| $coupon['expire_time'] < strtotime(date('Y-m-d'))) {
			return $INI['system']['couponname'] . '已失效';
		}
		else if ( !Utility::IsMobile($coupon_user['mobile']) ) {
			return '请设置合法的手机号码，以便接受短信';
		}
	
		$team = Table::Fetch('team', $coupon['team_id']);
		$user = Table::Fetch('user', $coupon['user_id']);
		$area = Table::Fetch('t_city_category_rel',$team["city_id"],'category_id');
		$order = Table::Fetch('order', $coupon['order_id']);
		$partner = Table::Fetch('partner', $team['partner_id']);
		$coupon_rule = $coupon['coupon_rule']?$coupon['coupon_rule']:$team['coupon_rule'];
		if($coupon_rule == 'single'){
			$quantity = $order['quantity'];
		}else{
			$quantity = 1;
		}
		
      	$msg = PayService::getValidateCodeSmsMsg($team['wap_title'], empty($area['area_name'])?'江苏':$area['area_name'], $quantity, $coupon['secret'], $coupon['expire_time'], $partner['phone']);
		$code = OraDbHelper::insertTable("sms_mt_wait",
      							 array(
      							 	"sequence_id"=>OraDbHelper::getSequenceNextVal('sequence_sms_mt_wait'),
  									"act_code"=>$conf["SEND_SMS_ACTCODE"],
  									"sp_code"=>$conf["SEND_SMS_SPCODE"],
								    "fee_terminal_id"=>$user["mobile"],
								    "dest_terminal_id"=>$user["mobile"],
								    "register_delivery"=>"0",
								    "msg_content"=>mb_convert_encoding($msg,'GBK','UTF-8'),
								    "request_time"=>strftime('%Y%m%d%H%M%S',time()),
								    "service_id"=>"FREE",
								    "fee_type"=>"1",
								    "fee_code"=>"0",
								    "msg_level"=>"0",
								    "valid_time"=>"120",
								    "area_code"=>$area['area_code'],
								    "operator_code"=>"JSYD",
      	));
      	
		if ($code) {
			Table::UpdateCache('coupon', $coupon['id'], array(
				'sms' => array('`sms` + 1'),
			));
			return true;
		}
		return "500";
	}
	
	public static function createValidateCode(&$team,&$order,&$user,&$area,&$orderId,&$pay){
		global $conf;	
		$paymess="网银";
		if('1'==$pay){$paymess='手机';}else if('2'==$pay){$paymess=='支付宝';}else if('3'==$pay){$paymess=='网银';}	
		//验证码
      	//$validateCode=date("ymd").RandomUtil::genRandomNum(5,1);
      	$currentTime = time();
      	$rand_postion = rand(0,9);
      	$validateCode=substr($currentTime,0,$rand_postion).RandomUtil::genRandomNum(2,1).substr($currentTime,$rand_postion);
      	$id=OraDbHelper::getSequenceNextVal('SEQ_Validate_Code_Log_ID');
      	if($team['coupon_rule']=='single'){
      		$quantity = $order['quantity'];
      		$remark='购买数量为'.$order['quantity'].',付款方式为'.$paymess;
      	}else{
      		$quantity =1;
      		$remark='购买数量为1,付款方式为'.$paymess;
      	}
      	//插入下发验证码记录
      	OraDbHelper::insertTable("t_12580_validate_code_log",
      	               			array(	"id"=>$id,
                               			"info_id"=>intval($team["id"]),
					   					"user_id"=>intval($user["id"]),
										"create_time"=>strftime('%Y%m%d%H%M%S',time()),
										"shop_id"=>$team['shop_id'],
										"area_code"=>$area['area_code'],
										"verify_code"=>$validateCode,
										"end_time"=>strftime('%Y%m%d%H%M%S',mktime(0, 0, 0, date("m",$team["expire_time"]),   date("d",$team["expire_time"])+1,   date("Y",$team["expire_time"])) ),
										"verify_flag"=>"0",
										"consumer_terminal_id"=>$user["mobile"],
										"terminal_id"=>$user["mobile"],
										"sort"=>6,
										"act_name"=>mb_convert_encoding($team["title"],'GBK','UTF-8'),
										"buy_num"=>$quantity,
      	               					"remark" => mb_convert_encoding($remark,'GBK','UTF-8'),
      	               					"order_id"=>$order['id'],
      	               					"user_info_id"=>intval($user["user_id"])
      	               			));
      	//下发验证
		$partner = Table::Fetch('partner', $team['partner_id']);
		$msg = PayService::getValidateCodeSmsMsg($team['wap_title'], empty($area['area_name'])?'江苏':$area['area_name'], $quantity, $validateCode, $team["expire_time"], $partner['phone']);
      	OraDbHelper::insertTable("sms_mt_wait",
      							 array(
      							 	"sequence_id"=>OraDbHelper::getSequenceNextVal('sequence_sms_mt_wait'),
  									"act_code"=>$conf["SEND_SMS_ACTCODE"],
  									"sp_code"=>$conf["SEND_SMS_SPCODE"],
								    "fee_terminal_id"=>$user["mobile"],
								    "dest_terminal_id"=>$user["mobile"],
								    "register_delivery"=>"0",
								    "msg_content"=>mb_convert_encoding($msg,'GBK','UTF-8'),
								    "request_time"=>strftime('%Y%m%d%H%M%S',time()),
								    "service_id"=>"FREE",
								    "fee_type"=>"1",
								    "fee_code"=>"0",
								    "msg_level"=>"0",
								    "valid_time"=>"120",
								    "area_code"=>$area['area_code'],
								    "operator_code"=>"JSYD",
      							 ));
      	//生成团购券
      	DB::Insert('coupon',array(
      							"id"=>$id,
      							"user_id"=>$user["id"],
      							"team_id"=>$team["id"],
				      			"order_id"=>$order['id'],
				      			"secret"=>$validateCode,
				      			"expire_time"=>$team["expire_time"],
				      			"create_time"=>time(),
      							"partner_id"=>$team['partner_id'],
      							"coupon_rule"=>$team['coupon_rule']
      							));
	}
	
	private static function getValidateCodeSmsMsg($wap_title, $city_name, $quantity, $validateCode, $expire_time, $shop_phone){
		$expire_time = date("Y.m.d", $expire_time);
		$msg = '【12580团】您已成功参与团购：'.$wap_title.'（限'.$city_name.'地区），此次购买数量为'.$quantity.'份，验证码是：'.$validateCode.'，有效期止：'.$expire_time.'，电话：'.$shop_phone;
		return $msg;
	}
}


?>