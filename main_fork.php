<?php


ini_set( 'display_errors', 'on' );
error_reporting(E_ALL);
//error_reporting(E_ERROR);

require_once 'osslib.inc.php';
require_once 'CKV.class.php';


$dirPath=$_SERVER['argv'][1];

$service = $_SERVER['argv'][2];

$iActivityId = $_SERVER['argv'][3];

$iTaskId = $_SERVER['argv'][4];

//渠道号
$channel=$_SERVER['argv'][5];

echo "filePath:".$dirPath."\n";
echo "service:".$service."\n";
echo "iActivityId:".$iActivityId."\n";
echo "iTaskId:".$iTaskId."\n";
echo "channel:".$channel."\n";



CKV_LOG(__FILE__, __LINE__, LP_ERROR,"filePath:{$dirPath}\n");
CKV_LOG(__FILE__, __LINE__, LP_ERROR,"service:{$service}\n");
CKV_LOG(__FILE__, __LINE__, LP_ERROR,"iActId:{$iActivityId}\n");
CKV_LOG(__FILE__, __LINE__, LP_ERROR,"iId:{$iTaskId}\n");
CKV_LOG(__FILE__, __LINE__, LP_ERROR,"channel:{$channel}\n");



if(!is_dir($dirPath)){

	//echo "{$filePath} is not exist\n";
	//记录失败日志，并且更新数据库为失败
	CKV_LOG(__FILE__, __LINE__, LP_ERROR, "{$dirPath} is not a dir\n");
	failedTask($iTaskId);
	exit();
	
}


$dataDir = opendir($dirPath);

$processNum=0;

$pids=array();

$starttime = microtime(TRUE);

while(($filePath = readdir($dataDir))!==false){

	if(!is_file($dirPath."/".$filePath)){
		continue;
	}

	CKV_LOG(__FILE__, __LINE__, LP_INFO, " process on {$filePath}\n");
	
	$pid = pcntl_fork();
	
	//fork成功，父进程
	if($pid>0){
		
		$processNum++;
		$pids[]=$pid;
	}
	//子进程
	else if($pid===0){
	
		//处理单个文件
		CKV_LOG(__FILE__, __LINE__, LP_ERROR, "process on {$dirPath}/{$filePath}\n");
		$failNum = processSingleFile($dirPath."/".$filePath,$service,$channel,$iActivityId);
		exit($failNum);
	}
	//fork失败
	else{
		CKV_LOG(__FILE__, __LINE__, LP_ERROR, "fork failed {$filePath} not process\n");
	}
	
}


CKV_LOG(__FILE__, __LINE__, LP_INFO, "processNum={$processNum}\n");

$finishedProcessNum=0;

while(count($pids) > 0) 
{
	
	$myId = pcntl_waitpid(-1, $status, WNOHANG);
	foreach($pids as $key => $pid) 
	{
		//$finishedProcessNum++;
		if($myId == $pid) {
			$finishedProcessNum++;
			unset($pids[$key]);
		}
	}
	//usleep(100);
}

$elapsed = microtime(TRUE) - $starttime;

CKV_LOG(__FILE__, __LINE__, LP_ERROR,"used {$elapsed}\n");

CKV_LOG(__FILE__, __LINE__, LP_ERROR,"finishedProcessNum={$finishedProcessNum}\n");

echo "used {$elapsed}\n";

echo "finishedProcessNum={$finishedProcessNum}\n";


//结束之后，更新任务为成功
finishTask($iTaskId);





//处理单个文件
function processSingleFile($filePath,$service,$channel,$iActivityId){

	$failNum=0;

	$fp = file($filePath);

	foreach($fp as &$line){

		//过滤掉换行符
		$line = str_replace("\n","",$line);
		
		$line = str_replace("\r","",$line);
		
		//空格转成\t，方便统一处理
		$line = str_replace(" ","\t",$line);
		
		$lineArray = explode("\t",$line);
		
		
		$iUin= $lineArray[0];
		$iArea=$lineArray[1];
		
		//如果没有大区，则为0
		if(empty($iArea)){
			$iArea=0;
		}

		$key = "{$service}_{$channel}_clientpopup_{$iUin}";
		
		
		$newValue = array(
			'iUin'=>$iUin,
			'iArea'=>$iArea,
			'iPopUpCount'=>0,
			'isPoped'=>0,
			'iActivityId'=>$iActivityId,
			'isPrized'=>0,
			'iUrlType'=>0,
			'iLevel'=>0,
			'dtPopDate'=>'0000-00-00 00:00:00',
			'dtValidTime'=>'0000-00-00 00:00:00'

		);
		
		
		//saveErrorLog($iUin,$iArea,$iActivityId,$service);
		//finishTask($iTaskId);
		//exit();
		
		$oldValue = CommonCKV::get($key);

		if($oldValue===false){
			//echo "get {$key} return false\n";
			CKV_LOG(__FILE__, __LINE__, LP_ERROR, "get {$key} return false\n");
		}

		//循环数组，查看是否需要更新
		$isInCache= false;
		foreach($oldValue as $value){
			if($value['iActivityId']==$iActivityId && $value['iArea']==$iArea){
				$isInCache =true;
			}
		}
		
		//CKV_LOG(__FILE__, __LINE__, LP_ERROR, "set {$key} return false\n");

		//不在缓存中，则追加
		if($isInCache==false){
			
			$oldValue[]=$newValue;
			//0表示永久有效
			$iRet = CommonCKV::set($key, $oldValue, 0, 60*60*3);
			if($iRet===false){
				//echo "set {$key} return false\n";
				CKV_LOG(__FILE__, __LINE__, LP_ERROR, "set {$key} return false\n");
				$failNum++;
				//保存失败信息
				saveErrorLog($iUin,$iArea,$iActivityId,$service);
			}
			
		}
	}
	
	return $failNum;

}




function GetCommonConfig()
{
    $g_config = parse_ini_file("/usr/local/commweb/cfg/CommConfig/commconf.cfg", true);
    return $g_config;
}

function GetGlobalConfig()
{
    global $g_config;
    if(!isset($g_config)){
        $g_config = parse_ini_file(dirname(__FILE__).'/ckv.cfg', true);
    }
    
    return $g_config;
}

/**
**保存失败信息
**/
function saveErrorLog($iUin,$iArea,$iActivityId,$sService){
    
    $_config = GetCommonConfig();
	$db = new DBProxy(
		$_config["6125_ieod_test_db"]["proxy_ip"],
		$_config["6125_ieod_test_db"]["proxy_port"],
		'dbCKVLogGavinwen'
	);
	
	
	$sql = "insert into tbCKVErrorLog(iUin,iArea,iActivityId,sService,dtCreateTime) values({$iUin},{$iArea},{$iActivityId},'{$sService}',now());";
	
	
	try{
		$res = $db->ExecUpdate($sql);
		if($res >= 0){
			return true;
		}
		else{
			return false;
		}
	}
	catch(Exception $e){
		return -1;
	}
    
}


/**
**更新任务为成功
**/
function finishTask($iTaskId){
    
    $_config = GetCommonConfig();
	
	$db = new DBProxy(
		$_config["6205_ieod_cf_gpm_db"]["proxy_ip"],
		$_config["6205_ieod_cf_gpm_db"]["proxy_port"],
		'dbCFClientPopJensenZhang'
	);
	
	$sql = "update tbUploadFile set iCKVStatus=1,dtUpdateTime=now() where iId={$iTaskId}";
	
	try{
		$res = $db->ExecUpdate($sql);
		if($res >= 0){
			return true;
		}
		else{
			return false;
		}
	}
	catch(Exception $e){
		CKV_LOG(__FILE__, __LINE__, LP_ERROR, "{$sql} error\n");
		CKV_LOG(__FILE__, __LINE__, LP_ERROR, $e->getMessage()."\n");
		return -1;
	}
    
}


/**
**更新任务为失败
**/
function failedTask($iTaskId){
    
    $_config = GetCommonConfig();
	
	$db = new DBProxy(
		$_config["6205_ieod_cf_gpm_db"]["proxy_ip"],
		$_config["6205_ieod_cf_gpm_db"]["proxy_port"],
		'dbCFClientPopJensenZhang'
	);
	
	$sql = "update tbUploadFile set iCKVStatus=-1,dtUpdateTime=now() where iId={$iTaskId}";
	
	try{
		$res = $db->ExecUpdate($sql);
		if($res >= 0){
			return true;
		}
		else{
			return false;
		}
	}
	catch(Exception $e){
		CKV_LOG(__FILE__, __LINE__, LP_ERROR, "{$sql} error\n");
		CKV_LOG(__FILE__, __LINE__, LP_ERROR, $e->getMessage()."\n");
		return -1;
	}
    
}




function CKV_LOG($codefilename, $codefileline, $loglevel, $log){
	$config = GetGlobalConfig();
	$logger = new Logger();
	$logger->initLogger(ROLL_FILE_LOGGER,"./log/",$config['FRAMEWORK_DEFAULT']['log_file_name'],$config['FRAMEWORK_DEFAULT']['roll_log_size'],$config['FRAMEWORK_DEFAULT']['roll_log_num']);
	//$log->setNullLoger(LP_BASE|LP_TRACE|LP_DEBUG);
	$logger->writeLog($codefilename,$codefileline,$loglevel,$log);

}








?>
