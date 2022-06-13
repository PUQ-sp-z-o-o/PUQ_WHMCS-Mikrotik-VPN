<?php
/*
 +-----------------------------------------------------------------------------------------+
 | This file is part of the WHMCS module. "PUQ_WHMCS-Mikrotik-VPN"                         |
 | The module allows you to manage the /ppp/secret/ users as a product in the system WHMCS.|
 | This program is free software: you can redistribute it and/or modify it                 |
 +-----------------------------------------------------------------------------------------+
 | Author: Ruslan Poloviy ruslan.polovyi@puq.pl                                            |
 | Warszawa 04.2021 PUQ sp. z o.o. www.puq.pl                                              |
 | version: 1.1.1                                                                          |
 +-----------------------------------------------------------------------------------------+
*/

use WHMCS\Database\Capsule;

function puqMikrotikVPN_MetaData(){
  return array(
      'DisplayName' => 'PUQ Mikrotik VPN',
      'DefaultSSLPort' => '443',
      'language' => 'english',
  );
}

function puqMikrotikVPN_ConfigOptions() {
  $configarray = array(
      'Comment PREFIX' => array( 'Type' => 'text', 'Default' => 'WHMCS'),
      'Profile' => array( 'Type' => 'text', 'Default' => 'default' ,'Size' => '20','Description' => 'PPP Secret Profile',),
      'Max Limit Upload' => array( 'Type' => 'text', 'Default' => '10' ,'Size' => '10','Description' => 'M',),
      'Max Limit Download' => array( 'Type' => 'text', 'Default' => '10' ,'Size' => '10','Description' => 'M',),

      'Service' => array( 'Type' => 'dropdown',
          'Options' => array(
              'any' => 'any',
              'async' => 'async',
              'l2tp' => 'l2tp',
              'ovpn' => 'ovpn',
              'pppoe' => 'pppoe',
              'ppptp' => 'ppptp',
              'sstp' => 'sstp',
          ), 'Default' => 'any' ,'Size' => '20','Description' => 'PPP Secret Servive',),

  );
  return $configarray;
}

function puqMikrotikVPN_apiCurl($params,$data,$url,$method){

  $curl_url = 'https://' . $params['serverhostname'] . ':'. $params['serverport'] . '/rest' . $url;
  $postdata = json_encode($data);
  $curl = curl_init();
  curl_setopt($curl, CURLOPT_URL, $curl_url);
  curl_setopt($curl, CURLOPT_HTTPHEADER, array('content-type: application/json'));
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
  curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
  curl_setopt($curl, CURLOPT_USERPWD, $params['serverusername'].':'.$params['serverpassword']);
  curl_setopt($curl, CURLOPT_POSTFIELDS, $postdata);
  curl_setopt($curl,CURLOPT_TIMEOUT,5);
  $answer = curl_exec($curl);
  $array = json_decode($answer,TRUE);
  curl_close($curl);
  return $array;
}

function puqMikrotikVPN_GetIP($params) {
  $serverid = $params['serverid'];
  $ips_sql = json_decode(json_encode( Capsule::table('tblservers')
      ->select('tblservers.assignedips')
      ->where('id',$serverid)
      ->get(), true));

  $ips = explode("\r\n",$ips_sql[0]->assignedips);


  $hosting_ips_sql = json_decode(json_encode( Capsule::table('tblhosting')
      ->select('tblhosting.dedicatedip')
      ->where(array(
          array('server',$serverid),
          array('domainstatus','!=','Terminated')
          )
      )
      ->get(), true));

  $hosting_ips = array();
  foreach ($hosting_ips_sql as $ip) {
    array_push($hosting_ips, $ip->dedicatedip);
  }

  foreach ($ips as $ip) {
    if (!in_array($ip,$hosting_ips)){
      return $ip;
    }
  }
  return '0.0.0.0';
  //logModuleCall('puqMikrotikVPN', 'GetIP', $ips, $hosting_ips);
}

function puqMikrotikVPN_CreateAccount($params) {
  $ip = puqMikrotikVPN_GetIP($params);
  $serviceid = $params['serviceid'];
  $username = $params['username'];
  $password = $params['password'];
  $mikrotik_profile = $params['configoption2'];
  $mikrotik_service = $params['configoption5'];
  $mikrotik_comment = $params['configoption1'] . '|Product ID:'. $params['serviceid'] . '|' . $params['clientsdetails']['email'];
  $mikrotik_max_limit = $params['configoption3'] . 'M/' . $params['configoption4'].'M';

  Capsule::table('tblhosting')->where('id', $serviceid)->update(["dedicatedip"=>$ip]);

  $data = array(
    'name'=> $username,
    'password' => $password,
    'remote-address'=>$ip,
    'profile'=> $mikrotik_profile,
    'service'=> $mikrotik_service,
    'comment'=> $mikrotik_comment
    );

    #add ppp user
    $create_user = puqMikrotikVPN_apiCurl($params,$data,'/ppp/secret', 'PUT');
    if(!$create_user){
      return 'API problem';
    }
    if($create_user['error']){
      return 'Error: ' . $create_user['error'] . '| Message' . $create_user['message'];
    }

    #add queue
    puqMikrotikVPN_apiCurl($params,$data,'/queue/simple/'.$username, 'DELETE');
    $data = array(
        'name'=> $username,
        'target'=>$ip,
        'max-limit'=> $mikrotik_max_limit,
        'comment'=> $mikrotik_comment
    );

    $add_queue = puqMikrotikVPN_apiCurl($params,$data,'/queue/simple', 'PUT');
    if(!$add_queue){
      return 'API problem';
    }
    if($add_queue['error']){
      return 'Error: ' . $add_queue['error'] . '| Message' . $add_queue['message'];
    }

    return 'success';
}

function puqMikrotikVPN_resetConnection($params) {

  $data = array();
  $curl = puqMikrotikVPN_apiCurl($params,$data,'/ppp/active/'.$params['username'], 'DELETE');
  if($curl['error']){
    return 'Error: ' . $curl['error'] . '| Message' . $curl['message'] . '|' . $curl['detail'];
  }
  return 'success';

}


function puqMikrotikVPN_AdminCustomButtonArray() {
  $buttonarray = array(
      'Reset connection' => 'resetConnection',
  );
  return $buttonarray;
}

function puqMikrotikVPN_SuspendAccount($params) {

  $data = array(
      'disabled'=> 'yes',
  );
  $curl = puqMikrotikVPN_apiCurl($params,$data,'/ppp/secret/'.$params['username'], 'PATCH');
  if(!$curl){
    return 'API problem';
  }
  if($curl['error']){
    return 'Error: ' . $curl['error'] . '| Message' . $curl['message'] . '|' . $curl['detail'];
  }

  puqMikrotikVPN_resetConnection($params);
  return 'success';
}

function puqMikrotikVPN_UnsuspendAccount($params) {

  $data = array(
      'disabled'=> 'no',
  );
  $curl = puqMikrotikVPN_apiCurl($params,$data,'/ppp/secret/'.$params['username'], 'PATCH');
  if(!$curl){
    return 'API problem';
  }
  if($curl['error']){
    return 'Error: ' . $curl['error'] . '| Message' . $curl['message'] . '|' . $curl['detail'];
  }
  return 'success';
}


function puqMikrotikVPN_TerminateAccount($params) {

  $data = array();
  $curl = puqMikrotikVPN_apiCurl($params,$data,'/ppp/secret/'.$params['username'], 'DELETE');
  if($curl['error']){
    return 'Error: ' . $curl['error'] . '| Message' . $curl['message'] . '|' . $curl['detail'];
  }

  puqMikrotikVPN_apiCurl($params,$data,'/queue/simple/'.$params['username'], 'DELETE');
  puqMikrotikVPN_resetConnection($params);

  return 'success';

}

function puqMikrotikVPN_ChangePassword($params) {

  $username = $params['username'];
  $password = $params['password'];

  $data = array(
      'password' => $password,
  );
  $curl = puqMikrotikVPN_apiCurl($params,$data,'/ppp/secret/'.$username, 'PATCH');
  if(!$curl){
    return 'API problem';
  }
  if($curl['error']){
    return 'Error: ' . $curl['error'] . '| Message' . $curl['message'] . '|' . $curl['detail'];
  }
  puqMikrotikVPN_resetConnection($params);
  return 'success';

}

function puqMikrotikVPN_ChangePackage($params) {
  puqMikrotikVPN_TerminateAccount($params);
  puqMikrotikVPN_CreateAccount($params);
}

function puqMikrotikVPN_loadLangPUQ($params) {

  $lang = $params['model']['client']['language'];

  $langFile = dirname(__FILE__) . "/lang/" . $lang . ".php";
  if (!file_exists($langFile))
    $langFile = dirname(__FILE__) . "/lang/" . ucfirst($lang) . ".php";
  if (!file_exists($langFile))
    $langFile = dirname(__FILE__) . "/lang/english.php";

  require dirname(__FILE__) . '/lang/english.php';
  require $langFile;

  return $_LANG_PUQ;
}


function puqMikrotikVPN_ClientArea($params) {
  $lang = puqMikrotikVPN_loadLangPUQ($params);

  $data = array();
  $curl = puqMikrotikVPN_apiCurl($params,$data,'/ppp/active/'.$params['username'], 'GET');
  if($curl){
    return array(
        'templatefile' => 'clientarea',
        'vars' => array(
            'lang' => $lang,
            'params'=> $params,
            'curl' => $curl
        ),
    );
  }
  return 'API problem';
}


function puqMikrotikVPN_AdminServicesTabFields($params) {
  $data = array();
  $curl = puqMikrotikVPN_apiCurl($params,$data,'/ppp/active/'.$params['username'], 'GET');

  if($curl['error']){
    $fieldsarray = array(
        'API Connection Status' => '<div class="successbox">API Connection OK</div>',
        'Connection information' => 'NOT ONLINE',
    );
  }
  if(!$curl){
    $fieldsarray = array('API Connection Status' => '<div class="errorbox">API connection problem.</div>');
  }

  if($curl['.id']){
    $fieldsarray = array(
        'API Connection Status' => '<div class="successbox">API Connection OK</div>',
        'Connection information' =>
            '<table style="width:30%">

    <tr>
    <td><b>Comment:</b></td>
    <td>' . $curl['comment'] . '</td>
    </tr>

    <tr>
    <td><b>Service:</b></td>
    <td>' . $curl['service'] . '</td>
    </tr>

    <tr>
    <td><b>Name:</b></td>
    <td>' . $curl['name'] . '</td>
    </tr>
    
    <tr>
    <td><b>Caller-id:</b></td>
    <td>' . $curl['caller-id'] . '</td>
    </tr>
        
    <tr>
    <td><b>Address:</b></td>
    <td>' . $curl['address'] . '</td>
    </tr>

    <tr>
    <td><b>Uptime:</b></td>
    <td>' . $curl['uptime'] . '</td>
    </tr>
    </table>'
    );
  }
  return $fieldsarray;
}
?>
