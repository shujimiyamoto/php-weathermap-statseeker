<?php

$VERSION=0.2.5;
$CONFIG_FILE="";

$myscript = $_SERVER["SCRIPT_FILENAME"];

if(!file_exists($CONFIG_FILE)) {
  printf("$myscript:  Missing config file: $CONFIG_FILE in ss-wm-api.php\n");
  exit(0);
} 

$ini_array = parse_ini_file($CONFIG_FILE);

if (strpos($ini_array["URL"], 'https') !== false) {
   $HTTP="https";
} else {
   $HTTP="http";
}

#if(substr($ini_array["URL"],"https")) {
#   printf("GOT HTTPS in %s", $ini_array["URL"]);

$STATSEEKER_IP=$ini_array["STATSEEKER"];
$username = $ini_array["USERNAME"];
$password = $ini_array["PASSWORD"];


$DeviceId2Name = array ();
$Ports = array ();





# Clean json_decode function.
function json_decode_nice($json, $assoc = FALSE){ 
    $json = str_replace(array("\n","\r"),"",$json); 
    $json = preg_replace('/([{,]+)(\s*)([^"]+?)\s*:/','$1"$3":',$json);
    $json = preg_replace('/(,)\s*}$/','}',$json);
    return json_decode($json,$assoc); 
}


function api_test() {
	global $username;
	global $password;
	global $STATSEEKER_IP;
	global $DeviceId2Name;
        global $HTTP;

	$context = stream_context_create(array(
	    'http' => array( 'header'  => "Authorization: Basic " . base64_encode("$username:$password"))
	));

$url = <<<EOD
$HTTP://$STATSEEKER_IP/api
EOD;
	$response = @file_get_contents($url,false,$context);
        if($response === FALSE) {
	    printf("AUTHFAIL: $username\n");
            exit;
        }
}



# Get all the devices in a group via the API.
function get_ss_group_id($group){
	global $username;
	global $password;
	global $STATSEEKER_IP;
	global $DeviceId2Name;
        global $HTTP;


	$groupstr = str_replace(' ', '%20', $group);

	$context = stream_context_create(array(
	    'http' => array( 'header'  => "Authorization: Basic " . base64_encode("$username:$password"))
	));

$url = <<<EOD
$HTTP://$STATSEEKER_IP/api/group?indent=2&links=none&fields=name&where={"name":["=","$groupstr"]}
EOD;
	$response = file_get_contents($url,false,$context);
	$res = json_decode_nice($response,true);
#	if(empty($res["result"])) {
#            printf("BAD GROUP: %s\n",$groupstr);
#            exit;
#        }

	if(!isset($res["result"][0]["id"])) {
		#printf("ERROR: Can't find group: $group\n");
		printf("0");
		exit;
	}
	return $res["result"][0]["id"];
}

# Get all the ports in a group via the API.
function get_ss_group_port($group){
	global $username;
	global $password;
	global $STATSEEKER_IP;
	global $DeviceId2Name;
	global $ini_array;
        global $HTTP;

	$context = stream_context_create(array(
	    'http' => array( 'header'  => "Authorization: Basic " . base64_encode("$username:$password"))
	));

	$group = get_ss_group_id($ini_array["GROUP"]);

#http://$STATSEEKER_IP/api/latest/cdt_port/?fields=deviceid,name,IF-MIB.ifIndex&groups=$group&links=none&index=3&limit=4000
$url = <<<EOD
$HTTP://$STATSEEKER_IP/api/port/?fields=device.name,deviceid,name,IF-MIB.ifIndex&links=none&index=3&indent=3&where={"groupid":["=",$group]}&limit=200
EOD;
	$response = file_get_contents($url,false,$context);
	$res = json_decode_nice($response,true);
	foreach ($res["result"] as $port) {
		$ifname = $port["name"];
		$ifnameFix = str_replace("/","-", $ifname);
		$cmd = sprintf("mv {$ini_array["TMP_DIR"]}/graph/%s.%s.png {$ini_array["GRAPH_DIR"]}/%s.%s.png\n", md5($port["device.name"]),$port["IF-MIB.ifIndex"], $port["device.name"],$ifnameFix);
		exec($cmd);
	}
}


// Parse without sections

$options = getopt("grt");

if(isset($options["g"])) {
	$group = get_ss_group_id($ini_array["GROUP"]);
	print $group;
} 
elseif(isset($options["r"])) {
	get_ss_group_port($ini_array["GROUP"]);
}
elseif(isset($options["t"])) {
	api_test();
} else {
	print "Incorrect Options passed to PHP script";
}

?>
