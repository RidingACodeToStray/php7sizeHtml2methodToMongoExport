<?php
$manager = new MongoDB\Driver\Manager("mongodb://localhost:27017");  
//读取本地国家代码
$code_string = file_get_contents('code.json');
$code_arr = json_decode($code_string,true);

$url = 'http://ipblock.chacuo.net/down/t_txt=c_';
$fail_code = array();
foreach($code_arr as $value){
	//方法1.使用file_get_contents抓包
	// $ctx = stream_context_create(array('http' => array('timeout' => 10)));      
	// $str1 = @file_get_contents($url.$value, 0, $ctx);
	
	//方法2.使用curl抓包
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url.$value);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	// curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); //此段代码用于获取重定向后的内容，这里不需要
	$str1 = curl_exec($ch);
	curl_close($ch);
	
	$res = stristr($str1,'<pre>');
	$arr1 = array();
	if($res){
		$str2 = trim(str_replace(array("<pre>","</pre>"),"",$str1)); //获取网页内容
		$str3 = preg_replace('/\s(?=\S)/',' ',$str2); //将每条信息修改成单个空格隔开
		$arr1 = explode(" ",$str3); //将所有的信息转化为一个数组
		$arr2 = array_chunk($arr1,4); //由于每四条是一个有效信息，将数组按4个拆成一个数组
		//将国家代码插入拆开后的每个数组存入数据库
		$bulk = new MongoDB\Driver\BulkWrite;
		foreach ($arr2 as &$val) {
			array_unshift($val,$value);
			$bulk->insert($val);
		}
		$manager->executeBulkWrite('ming.country_ip', $bulk);
	}else{      
		$fail_code[] = $value;//收集抓取失败的国家代码
	} 
}
//将抓取失败的国家代码写入文件
$file = fopen("fail_code.txt", "w") or die("Unable to open file!");
$txt = json_encode($fail_code);
fwrite($file, $txt);
fclose($file);