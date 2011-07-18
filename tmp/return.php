<?php
require_once(dirname(dirname(dirname(__FILE__))) . '/app.php');
require_once(dirname(dirname(dirname(__FILE__)))."/cplatform/service/PayService.php");

$_input_charset = 'utf-8';
$partner = $INI['alipay']['mid'];
$security_code = $INI['alipay']['sec'];
$sign_type = 'MD5';
$transport = 'http';

/* very import, this value is add by my phpframewrok */
unset($_GET['param']);  
/* end */

$alipay = new AlipayNotify($partner, $security_code, $sign_type, $_input_charset, $transport);
$verify_result = $alipay->return_verify();

$out_trade_no = $_GET['out_trade_no'];   //获取订单号
$total_fee  = $_GET['total_fee'];      //获取总价格  
@list($_, $order_id, $quantity, $_) = explode('-', $out_trade_no, 4);

if ( $_ == 'charge' ) {
	if($_GET['trade_status'] == 'TRADE_FINISHED' || $_GET['trade_status'] == 'TRADE_SUCCESS') {
		@list($_, $user_id, $create_time, $_) = explode('-', $out_trade_no, 4);
		if(ZFlow::CreateFromCharge($total_fee, $user_id, $create_time, 'alipay')){
			Session::Set('notice', "支付宝充值{$total_fee}元成功！");
			Phplog::RecordChargeLog("支付宝充值{$total_fee}元成功！支付订单号:{$out_trade_no}");
		}
	}
	Utility::Redirect(WEB_ROOT . '/credit/index.php');
}

if($verify_result) {
	if($_GET['trade_status'] == 'TRADE_FINISHED' || $_GET['trade_status'] == 'TRADE_SUCCESS') {
		$order = Table::Fetch('order', $order_id);
		if($order['state'] == 'pay'){
			if($order['pay_id']<>$out_trade_no){
				//重复支付
				Utility::Redirect( WEB_ROOT . "/order/result.php?id={$order['id']}&msg=repeat");
			}
			//支付成功
			Utility::Redirect( WEB_ROOT . "/order/pay.php?id={$order['id']}");
		}else{
			//查找团购
			$team=Table::Fetch('team',$order['team_id'],'id');
			if(!$team) {
				Phplog::RecordOrderFailLog(" 团购不存在 团购号:".$team['id']);
      			Utility::Redirect( WEB_ROOT . "/order/result.php?id={$order['id']}");
			}
			
			$plus = $team['conduser']=='Y' ? 1 : $quantity;
			$team['now_number'] += $plus;
			
			//团购数量已超过上限
			if ($team['max_number']>0 &&  $team['now_number'] > $team['max_number'] ) {
				Phplog::RecordOrderFailLog(" 团购数量已超过上限 团购订单号:".$order_id);
      			Utility::Redirect( WEB_ROOT . "/order/result.php?id={$order['id']}");
			}elseif($team['end_time'] < time()){
				Phplog::RecordOrderFailLog(" 团购已结束 团购订单号:".$order_id);
				Utility::Redirect( WEB_ROOT . "/order/result.php?id={$order['id']}");
			}
			
			//1
			$table = new Table('order');
			$table->SetPk('id', $order_id);
			$table->pay_id = $out_trade_no;
			$table->money = $total_fee;
			$table->order_id = $out_trade_no;
			$table->state = 'pay';
			$table->quantity = $quantity;
			$table->service = 'alipay';
			$flag = $table->update( array('state', 'pay_id', 'money','order_id','quantity','service') );
		
			if ( $flag ) {
				$table = new Table('pay');
				$table->id = $out_trade_no;
				$table->order_id = $order_id;
				$table->money = $total_fee;
				$table->currency = 'CNY';
				$table->bank = '支付宝';
				$table->service = 'alipay';
				$table->create_time = time();
				$table->insert( array('id', 'order_id', 'money', 'currency', 'service', 'create_time', 'bank') );
					
				$order = Table::Fetch('order', $order_id);
						
				//update team,user,order,flow state//
				ZTeam::BuyOne($order);
				//查找地市
		    	$area=Table::Fetch('t_city_category_rel',$team["city_id"],'category_id');
		    	//查找用户
		    	$user=Table::Fetch('user',$order["user_id"],'id');
		    	$order_type = '2';
		    	//更新支付状态
		  		PayService::afterPayDoSomething($team,$order,$user,$area,$out_trade_no,$order_type);
		  		Phplog::RecordOrderSuccessLog(" 流水号:".$_POST['trade_no']." 通知id:".$_POST['notify_id']." 团购订单号:".$order_id." 支付金额:".$total_fee." 通知时间时间：".$_POST['notify_time']);
		  		$team=Table::Fetch('team',$order['team_id'],'id');
				//团购结束或者数量已经卖完关闭没有完成支付的订单
				if ($team['end_time'] < time() || ($team['max_number']>0 && $team['now_number'] >= $team['max_number']) ) {
						//关闭没有完成支付的订单
						ZTeam::CloseTrade($order['team_id']);
						//将团购权重置为0
						Table::UpdateCache('team',$order['team_id'],array('sort'=>0));
				}
		  		//支付成功
				if($sso_login_type == "baidu" || ($_COOKIE['hao123_tn'] && $_COOKIE['hao123_baiduid'])){
			  		$partner = Table::Fetch('partner', $team['partner_id']);
					//hao123 baidu api 这个接口要放到最后，因为有重定义加载方法
					require_once(DIR_LIBARAY."/hao123OpenApi/BaiduOpenAPI.inc.php");
					customSaveOrder($order, $team, $partner, $sso_login_type, $_COOKIE['hao123_baiduid'], $_COOKIE['hao123_tn']);
				}
				Utility::Redirect( WEB_ROOT . "/order/pay.php?id={$order['id']}");
			}else{
				Phplog::RecordOrderFailLog(" 流水号:".$payNo." 团购订单号:".$orderId." 支付金额:".$amount." 支付银行:".$banks." 送货信息：".$contractName." 发票抬头：".$invoiceTitle." 支付人：".$mobile." 支付时间：".$payDate." 保留字段：".$reserved);
			}
			
		}
		//支付未完成，等待支付流程结束。
      	Utility::Redirect( WEB_ROOT . "/order/result.php?id={$order['id']}");
	}
}

Utility::Redirect( WEB_ROOT . "/order/pay.php?id={$order_id}");
