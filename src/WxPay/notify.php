<?php
ini_set('date.timezone','Asia/Shanghai');
error_reporting(E_ERROR);

require_once "lib/WxPay.Api.php";
require_once 'lib/WxPay.Notify.php';

//初始化日志
class PayNotifyCallBack extends WxPayNotify
{
	//查询订单
	public function Queryorder($transaction_id)
	{
		$input = new WxPayOrderQuery();
		$input->SetTransaction_id($transaction_id);
		$result = WxPayApi::orderQuery($input);
		\Log::info("query:" . json_encode($result));
		if(array_key_exists("return_code", $result)
			&& array_key_exists("result_code", $result)
			&& $result["return_code"] == "SUCCESS"
			&& $result["result_code"] == "SUCCESS")
		{
			// 处理订单
			$pay = \App\Pay::find($result['out_trade_no']);	
			$pay->pay($result['transaction_id']);
			
			if( $pay->state < 2 ){
				$pay->state		= '2';
				$pay->bak_id	= '0';
				$pay->other_id	= $result['transaction_id'];
				$pay->save();
			}else if ( $pay->state == 2){
				$pay->bak_id++;
				$pay->save();
			}
			return true;
		}
		return false;
	}
	
	
	//重写回调处理函数
	public function NotifyProcess($data, &$msg)
	{
		\Log::info("call back:" . json_encode($data));
		$notfiyOutput = array();		
		if(!array_key_exists("transaction_id", $data)){
			$msg = "输入参数不正确";
			return false;
		}
		//查询订单，判断订单真实性
		if(!$this->Queryorder($data["transaction_id"])){
			$msg = "订单查询失败";
			return false;
		}
		return true;
	}
}
