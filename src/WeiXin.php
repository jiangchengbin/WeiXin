<?php namespace Jiangchengbin\WeiXin;

use App\Pay;
use Input;
use Session;

class WeiXin
{
	// 获取默认微信公众号的 Access
	public function getAccess()
	{
		$access_token = \Cache::remember('weixin.access', 100, function () {
			$content=file_get_contents('https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.env('WX_ID', '').'&secret='.env('WX_SR', ''));
        	return json_decode($content)->access_token;
        });
        return $access_token;
	}
	
	// 获取2号微信公众号的Access
	public function getAccess2()
	{
		$access_token = \Cache::remember('weixin.access2', 100, function () {
			$content=file_get_contents('https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.env('WX_ID2', '').'&secret='.env('WX_SR2', ''));
        	return json_decode($content)->access_token;
        });
        return $access_token;
	}
	
	// 使用JSON数据通知URL
	public function notifyJSON($url,$menu)
	{			
		$json_menu=json_encode($menu,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $ch = curl_init();
        $timeout = 300;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_menu);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,FALSE);  // HTTPS要加的
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,FALSE);  // HTTPS要加的
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        $result = curl_exec($ch);
        curl_close($ch);

        // 4.判断返回结果
        $err_code = json_decode($result);
        if($err_code->errcode == '0'){
            return true;
        }
        return false;
	}
	
	// 获取微信openID
	public function getID($flag=false)
	{
		if($flag == false){	// 是否强制调用微信接口获取open_id
			$open_id = Session::get('wx_openid','');
			if(!empty($open_id)) return $open_id;
		}

		$code           = Input::input("code");
		if(empty($code)){	// 没有CODE
			// 准备获取open_id
			$ret_url        = env('APP_URL','http://www.lixijing520.com').$_SERVER['REQUEST_URI'];
			$scope          = "snsapi_base";
	        $dialog_url     = "https://open.weixin.qq.com/connect/oauth2/authorize?appid="
	                        .env('WX_ID', '')."&redirect_uri=".urlencode($ret_url)
	                        ."&response_type=code&scope=".$scope."#wechart_redirect";
			header("Location:{$dialog_url}");
			exit;			
		}
		
		// 获取openID
        $req_url        = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=".env('WX_ID', '').
                        "&secret=".env('WX_SR', '').
                        "&code=".$code.
                        "&grant_type=authorization_code";
     	$response       = file_get_contents($req_url);
		$msg            = json_decode($response);
		if (isset($msg->error)) return '';
		
		// 保存open_id到Session
		Session::put('wx_openid',$msg->openid);
        Session::save();
		return $msg->openid;		
	}
	
	// 发送模板消息
	public function notifyMessage($message)
	{
        $access_token 	= $this->getAccess();
        $url 			= 'https://api.weixin.qq.com/cgi-bin/message/template/send?access_token='.$access_token;
        return $this->notifyJSON($url,$message);
	}
	
	// 更新菜单
	public function updateMenu($menu)
	{
        $access_token 	= $this->getAccess();
        $url 			= 'https://api.weixin.qq.com/cgi-bin/menu/create?access_token='.$access_token;
        return $this->notifyJSON($url,$menu);
	}
	
	// 更新菜单2（第二个微信公众号的菜单）
	public function updateMenu2($menu)
	{
        $access_token 	= $this->getAccess2();
        $url 			= 'https://api.weixin.qq.com/cgi-bin/menu/create?access_token='.$access_token;
        return $this->notifyJSON($url,$menu);
	}
	
	public function ToUrlParams($values)
	{
		$buff = "";
		foreach ($values as $k => $v)
		{
			if($k != "sign" && $v != "" && !is_array($v)){
				$buff .= $k . "=" . $v . "&";
			}
		}
		$buff = trim($buff, "&");
		return $buff;
	}
	
	/**
	 * 生成签名
	 * @return 签名，本函数不覆盖sign成员变量，如要设置签名需要调用SetSign方法赋值
	 */
	public function MakeSign($values)
	{
		ksort($values);
		$string = $this->ToUrlParams($values);
		$string = $string . "&key=".env('WX_KEY','');
		$string = md5($string);
		$result = strtoupper($string);
		return $result;
	}
	
	public function ToXml($values)
	{
		if(!is_array($values) 
			|| count($values) <= 0)
		{
    		throw new WxPayException("数组数据异常！");
    	}
    	
    	$xml = "<xml>";
    	foreach ($values as $key=>$val)
    	{
    		if (is_numeric($val)){
    			$xml.="<".$key.">".$val."</".$key.">";
    		}else{
    			$xml.="<".$key."><![CDATA[".$val."]]></".$key.">";
    		}
        }
        $xml.="</xml>";
        return $xml; 
	}
	
	
	/**
	 * 以post方式提交xml到对应的接口url
	 * 
	 * @param string $xml  需要post的xml数据
	 * @param string $url  url
	 * @param bool $useCert 是否需要证书，默认不需要
	 * @param int $second   url执行超时时间，默认30s
	 * @throws WxPayException
	 */
	private static function postXmlCurl($xml, $url, $useCert = false, $second = 30)
	{		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_TIMEOUT, $second);			// 设置超时
		curl_setopt($ch,CURLOPT_URL, $url);
		curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
		curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,FALSE);
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	
		if($useCert == true){
			//设置证书
			//使用证书：cert 与 key 分别属于两个.pem文件
			curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');
			$cert_path = __DIR__."/../cert/";
			curl_setopt($ch,CURLOPT_SSLCERT, $cert_path.WxPayConfig::SSLCERT_PATH);
			curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
			curl_setopt($ch,CURLOPT_SSLKEY, $cert_path.WxPayConfig::SSLKEY_PATH);
		}
		
		
		curl_setopt($ch, CURLOPT_POST, TRUE);				// post提交方式
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
		$data = curl_exec($ch);		
	
	
		//返回结果
		if($data){
			curl_close($ch);
			return $data;
		} else { 
			$error = curl_errno($ch);
			curl_close($ch);
			throw new WxPayException("curl出错，错误码:$error");
		}
	}
	
	// 统一下单
	public function unifiedOrder($id)
	{
		$values = array();
		$values['appid'] 			= env('WX_ID','');
		$values['mch_id'] 			= env('WX_MID','');
		$values['device_info']	 	= 'WEB';
		$values['nonce_str'] 		= 'WEB';
		$values['body'] 			= '付款';
		$values['out_trade_no']		= $id;
		$values['total_fee'] 		= 1;
		$values['spbill_create_ip']= $_SERVER["REMOTE_ADDR"];
		$values['notify_url']		= urlencode(env('APP_URL',''));
		$values['trade_type']		= 'JSAPI';
		$values['openid']			= 'o0gGmt0JQk4ijk539lCU9auPuomQ'; // $this->getID();
		$values['sign']			= $this->MakeSign($values);
		
		$xml = $this->ToXml($values);
		$url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
		$data= $this->postXmlCurl($xml,$url); 	
		
		//将XML转为array 
        $val = json_decode(json_encode(simplexml_load_string($data, 'SimpleXMLElement', LIBXML_NOCDATA)));		
		return $val->prepay_id;
	}
	
	// 统一下单
	public function getPrepayID($id,$amt)
	{
		$values = array();
		$values['appid'] 			= env('WX_ID','');
		$values['mch_id'] 			= env('WX_MID','');
		$values['device_info']	 	= 'WEB';
		$values['nonce_str'] 		= 'WEB';
		$values['body'] 			= '付款';
		$values['detail'] 			= '付款';
		$values['out_trade_no']		= $id;
		$values['total_fee'] 		= $amt * 100;
		$values['spbill_create_ip'] = $_SERVER["REMOTE_ADDR"];
		$values['notify_url']		= urlencode(env('APP_URL',''));
		$values['trade_type']		= 'JSAPI';
		$values['openid']			= $this->getID();
	//	$values['openid']			= 'o0gGmt0JQk4ijk539lCU9auPuomQ';
		$values['sign']				= $this->MakeSign($values);
		
		$xml = $this->ToXml($values);
		$url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
		$data= $this->postXmlCurl($xml,$url); 	
		
		//将XML转为array 
        $val = simplexml_load_string($data, 'SimpleXMLElement', LIBXML_NOCDATA);
  		if(!empty($val) && $val->result_code == 'SUCCESS'){
			$jsApiObj				= array();
			$jsApiObj["appId"] 		= env('WX_ID','');
			$timeStamp 				= time();
		    $jsApiObj["timeStamp"] 	= "$timeStamp";
		    $jsApiObj["nonceStr"] 	= str_random(32);
			$jsApiObj["package"] 	= "prepay_id=".$val->prepay_id;
		    $jsApiObj["signType"] 	= "MD5";
		    $jsApiObj["paySign"] 	= $this->MakeSign($jsApiObj);
		    $val->jsApiParameters 	= json_encode($jsApiObj);
		    $val->openID 			= $this->getID();
        }
		return $val;
	}
}



