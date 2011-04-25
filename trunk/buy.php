<?php
require_once(dirname(dirname(__FILE__)) . '/app.php');
require_once '../cplatform/utils/RandomUtil.php';

$id = abs(intval($_GET['id']));

$team = Table::Fetch('team', $id);
if ( !$team || $team['begin_time']>time() || $team['check_state']==0) {
	Session::Set('error', '团购项目不存在');
	Utility::Redirect( WEB_ROOT . '/index.php' );
}

$ex_con = array(
		'user_id' => $login_user_id,
		'team_id' => $team['id'],
		'state' => 'unpay',
		);
$order = DB::LimitQuery('order', array(
	'condition' => $ex_con,
	'one' => true,
));

//buyonce
if (strtoupper($team['buyonce'])=='Y') {
	$ex_con['state'] = 'pay';
	if ( Table::Count('order', $ex_con) ) {
		Session::Set('error', '您已经成功购买了本单产品，请勿重复购买，快去关注一下其他产品吧！');
		redirect( WEB_ROOT . "/team.php?id={$id}"); 
	}
}

//peruser buy count
if ($team['per_number']>0) {
	$now_count = Table::Count('order', array(
		'user_id' => $login_user_id,
		'team_id' => $id,
		'state' => 'pay',
	), 'quantity');
	$team['per_number'] -= $now_count;
	if ($team['per_number']<=0) {
		Session::Set('error', '您购买本单产品的数量已经达到上限，快去关注一下其他产品吧！');
		redirect( WEB_ROOT . "/team.php?id={$id}"); 
	}
}

if ( $_POST ) {
	need_login(true);
	$table = new Table('order', $_POST);
	$table->quantity = abs(intval($table->quantity));
	if ( $table->quantity == 0 ) {
		Session::Set('error', '购买数量不能小于1份');
		Utility::Redirect( WEB_ROOT . "/team/buy.php?id={$team['id']}");
	} 
	elseif ( $team['per_number']>0 && $table->quantity > $team['per_number'] ) {
		Session::Set('error', '您本次购买本单产品已超出限额！');
		Utility::Redirect( WEB_ROOT . "/team.php?id={$id}"); 
	}
	if ( $team['per_min_number']>0 && $table->quantity < $team['per_min_number'] ) {
		Session::Set('error', "本单产品最少需要购买{$team['per_min_number']}个！");
		Utility::Redirect( WEB_ROOT . "/team/buy.php?id={$team['id']}");
	}
		
		if ($order && $order['state']=='unpay') {
			$table->id = $order['id'];
		}
		$table->user_id = $login_user_id;
		$table->team_id = $team['id'];
		$table->city_id = $team['city_id'];
		$table->express = ($team['delivery']=='express') ? 'Y' : 'N';
		$table->fare = $table->express=='Y' ? $team['fare'] : 0;
		$table->price = $team['team_price'];
		$table->credit = 0;
		$table->create_time = time();
		//订单id，由系统生成订单
		//$table->order_id=RandomUtil::getGroupBuyOrderId();
	
		if ( $table->id ) {
			$eorder = Table::Fetch('order', $table->id);
			if ($eorder['state']=='unpay' 
					&& $eorder['team_id'] == $id
					&& $eorder['user_id'] == $login_user_id
			   ) {
				$table->origin = ($table->quantity * $team['team_price']) + ($team['delivery'] == 'express' ? $team['fare'] : 0) - $eorder['card'];
			} else {
				$eorder = null;
			}
		}
		
		if (!$eorder) {
			$table->origin = ($table->quantity * $team['team_price']) + ($team['delivery'] == 'express' ? $team['fare'] : 0);
		}
	
		$insert = array(
				'user_id', 'team_id', 'city_id', 'state', 
				'fare', 'express', 'origin', 'price',
				'address', 'zipcode', 'realname', 'mobile', 'quantity',
				'create_time', 'remark'
			);
		
		if ($flag = $table->update($insert)) {
			$order_id = abs(intval($table->id));
			
			Utility::Redirect(WEB_ROOT."/order/check.php?id={$order_id}");
		}
}

//each user per day per buy
if (!$order) { 
	$order = json_decode(Session::Get('loginpagepost'),true);
	settype($order, 'array');
	if ($order['mobile']) $login_user['mobile'] = $order['mobile'];
	if ($order['zipcode']) $login_user['zipcode'] = $order['zipcode'];
	if ($order['address']) $login_user['address'] = $order['address'];
	if ($order['realname']) $login_user['realname'] = $order['realname'];
	$order['quantity'] = 1;
} else {
	if ($order['state']!='unpay') {
		Session::Set('error', '每人每单只能购买一次，你已经成功购买过！');
		Utility::Redirect( WEB_ROOT . "/team.php?id={$id}"); 
	}
}
//end;

//last order info fill in the express info 
//if last order info is null then user info fill in the express info  
if ($team['delivery']=='express'){
	if(!$order['mobile']){
		$condition = array( 'user_id' => $login_user_id, 'team_id > 0','mobile is not null');
		$last_order = DB::LimitQuery('order', array(
			'condition' => $condition,
			'order' => 'ORDER BY id DESC',
			'one' => true
		));
		$e_user_name = !empty($last_order['realname'])?$last_order['realname']:$login_user['realname'];
		$e_mobile = $last_order['mobile']?$last_order['mobile']:$login_user['mobile'];
		$e_zipcode = $last_order['zipcode']?$last_order['zipcode']:$login_user['zipcode'];
		$e_address = $last_order['address']?$last_order['address']:$login_user['address'];
	}else{
		$e_user_name = $order['realname'];
		$e_mobile = $order['mobile'];
		$e_zipcode = $order['zipcode'];
		$e_address = $order['address'];
	}
}
//end
if ($team['max_number']>0 && $team['conduser']=='N') {
	$left = $team['max_number'] - $team['now_number'];
	if ($team['per_number']>0) {
		$team['per_number'] = min($team['per_number'], $left);
	} else {
		$team['per_number'] = $left;
	}
	if ($team['per_min_number']>0) {
		$order['quantity'] = $team['per_min_number'];
	}
}

$order['origin'] = ($order['quantity'] * $team['team_price']) + ($team['delivery']=='express' ? $team['fare'] : 0);
//是否显示0元短信提醒( 0不显示，1显示 )@zouyulu 2011-03-23
$order['show_zero_smsnote'] = 0;//默认不显示
if(!$order['origin']){
	//获取短信订阅记录
	$custom_con = array('area_code' => $team['city_id'], 'terminal_id' => $login_user['mobile']);
	if(!DB::Exist("custom", $custom_con)){
		$order['show_zero_smsnote'] = 1;
	}
}

include template('team_buy');
