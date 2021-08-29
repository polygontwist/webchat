<?php
header('Content-Type: application/json; charset=UTF-8');

//$_SERVER['PHP_SELF'] --> /test/webchat/chat.php
//__FILE__ --> /srv/dev-disk-by-label-myRAID/www/test/webchat/chat.php
$shm_key = ftok(__FILE__, 't');			//generateKey
$shm_keyChat = ftok(__FILE__, 'c');

//TODO: wenn save fehlschlägt (->speicher?)
function save_cache($id, $data) {
	
	//altes löschen
	$shm_id=@shmop_open($id, "a", 0, 0);
	if($shm_id){
		shmop_delete($shm_id);
		shmop_close($shm_id);
	}

	//neues speichern	
	$size=strlen($data);
	$shm_id = @shmop_open($id, //ID
		"c", 	//erzeugen oder wenn vorhanden öffnen
		0644, 	//berechtigung
		$size	//größe
		);
		
	if(!$shm_id)return false;
	
	
	$shm_bytes_written = shmop_write($shm_id, $data, 0);
	if ($shm_bytes_written != strlen($data)){
		return false;
	}
	
	shmop_close($shm_id);
	return true;
}

function get_cache($id) {
	$shm_id = @shmop_open($id, "a", 0, 0);
	if(!$shm_id){
		return false;
	}
    $data=shmop_read($shm_id, 0, shmop_size($shm_id));
   
	shmop_close($shm_id);
	
    return trim($data);
}

function delete_cache($id){
	$shm_id=@shmop_open($id, "a", 0, 0);
	if($shm_id){
		shmop_delete($shm_id);
		shmop_close($shm_id);
		return true;
	}
	return false;
}


function create_clientID($ip,$useragent){
	$data="";
	$iparr=explode('.',$ip);
	//192.168.0.46 -> 192168000046 -> 
	$minip="";
	for($i=0;$i<count($iparr);$i++){
		$s=$iparr[$i];
		while(strlen($s)<3){
			$s='0'.$s;
		}
		$data.=$s;
	}
		
	$uanr=0;//alle ZeichenCodes vom $useragent zusammen addieren
	//reicht aus für einfache Kennung
	for($i=0;$i<strlen($useragent);$i++){
		$s=ord(substr($useragent,$i,1));
		$uanr+=$s;
	}
	$data.='.'.$uanr;
	
	return trim($data);
}


$playerdaten=[];	//"id|time|action"
$chatdaten=[];		//"id|time|msg" 
$chatzeilenmax=20;	//nur die letzten speichern
$gosafingClient=false;
$gosafingChat=false;

$tmp=get_cache($shm_key);//hole Daten
if($tmp!=false){
	$playerdaten=explode(chr(13),$tmp);//Daten in Liste umwandeln
}
$tmp=get_cache($shm_keyChat);//hole Daten
if($tmp!=false){
	$chatdaten=explode(chr(13),$tmp);//Daten in Liste umwandeln
}


$ip=$_SERVER['REMOTE_ADDR'];
$useragent=$_SERVER['HTTP_USER_AGENT'];

$clientinListpos=-1;
$clientID=create_clientID($ip,$useragent);
$clienttime=microtime(true);//ms time();//Sekunden

$chatmsg="";
$action="";
$actionto="";
$istaction=false;


$actiontome="";

if(isset($_GET['msg'])){
	$chatmsg=urldecode($_GET['msg']);
	
	if(count($chatdaten)>=$chatzeilenmax){
		$arrneu=[];
		for($i=1;$i<count($chatdaten);$i++){
			array_push($arrneu,$chatdaten[$i]);
		}
		$chatdaten=$arrneu;
	}
	
	array_push($chatdaten,$clientID.'|'.$clienttime.'|'.$chatmsg);
	
	$gosafingChat=true;
}




if(isset($_GET['action'])){
	$arr=explode('|',urldecode($_GET['action']));// "clientid|action²
	if(count($arr)==2){
		$actionto	=$arr[0];
		$action		=$arr[1];
		$istaction=true;
	}
	
	if($actionto==="host" && $action==="clear"){
		//delete_cache($shm_keyChat);
		$chatdaten=[];
		$istaction=false;
		$gosafingChat=true;
		array_push($chatdaten,"host|host|chat is clear");
	}
}

if($gosafingChat){
	save_cache($shm_keyChat, implode(chr(13),$chatdaten));
}


//gibt es client in Liste? 
for($i=0;$i<count($playerdaten);$i++){
	$c=explode('|',$playerdaten[$i]);
	if($c[0]==$clientID)$clientinListpos=$i;
}
//neuer Client
if($clientinListpos===-1){
	$gosafingClient=true;
	array_push($playerdaten,$clientID.'|'.$clienttime.'|');//id|firstviewtime|action
	$clientinListpos=count($playerdaten)-1;
}


//action an client?
for($i=0;$i<count($playerdaten);$i++){
	$c=explode('|',$playerdaten[$i]);
		
	//add
	if($istaction){
		if($actionto==$c[0]
			||
			$actionto=="all"
		){
			$c[2]=$action;
			$playerdaten[$i]=implode('|',$c);
			$gosafingClient=true;
		}
	}
	
	//get+(headbeat)
	if($c[0]==$clientID){
		$actiontome=$c[2];
		if(strlen($actiontome)>0){
			$c[1]=$clienttime;
			$c[2]="";
			$playerdaten[$i]=implode('|',$c);
			$gosafingClient=true;
		}
	}
	
}

if($gosafingClient){
	save_cache($shm_key, implode(chr(13),$playerdaten));
}





//time aktualisieren?

//action übergeben
/*for($i=0;$i<count($playerdaten);$i++){
	$c=explode('|',$playerdaten[$i]);
	if($i!=$clientinListpos){
		$c[2]=$chatmsg;
	}else{
		$c[2]="";
	}
}	
*/

echo '{';
echo '"ip":"'.$ip.'"';
echo ',"clienttime":"'.$clienttime.'"';
echo ',"clientID":"'.$clientID.'"';//
echo ',"clientinListpos":"'.$clientinListpos.'"';
echo ',"actiontome":"'.$actiontome.'"';
//echo ',"action":"'.$action.'"';
//echo ',"actionto":"'.$actionto.'"';
//echo ',"shm_key":"'.$shm_key.'"';
//echo ',"shm_keyChat":"'.$shm_keyChat.'"';
echo ',"clients":[';
for($i=0;$i<count($playerdaten);$i++){
	if($i>0)echo ",";
	echo '"'.$playerdaten[$i].'"';	
}
echo ']';

echo ',"chat":[';
for($i=0;$i<count($chatdaten);$i++){
	if($i>0)echo ",";
	$chatstr=$chatdaten[$i];
	$chatstr=str_replace('"','\"',$chatstr);
	$chatstr=str_ireplace('<script','-script',$chatstr);
	$chatstr=str_ireplace('</script','-script',$chatstr);
	//$chatstr=str_ireplace('script','',$chatstr);
	echo '"'.$chatstr.'"';
}

echo ']';

echo '}';



?>
