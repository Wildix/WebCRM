<?php

    $path = ( dirname(__DIR__) );
    set_include_path(get_include_path() . PATH_SEPARATOR . $path);

    require_once('config.php');
    /**
    * URL Verfication - Required to overcome Apache mis-configuration and leading to shared setup mode.
    */
    if (file_exists('config_override.php')) {
        include_once('config_override.php');
    }

    require_once('include/Webservices/Utils.php');
    require_once('include/Webservices/State.php');
    require_once('include/Webservices/OperationManager.php');
    require_once('include/Webservices/SessionManager.php');
    require_once('include/Zend/Json.php');
    require_once('include/database/PearDatabase.php');

    function getRequestParamsArrayForOperation($operation){
        global $operationInput;
        return $operationInput[$operation];
    }

    function setResponseHeaders() {
        header('Content-type: application/json');
    }

    function writeErrorOutput($operationManager, $error){

        setResponseHeaders();
        $state = new State();
        $state->success = false;
        $state->error = $error;
        unset($state->result);
        $output = $operationManager->encode($state);
        echo $output;

    }

    function getContactID($phone){
        $sql = "SELECT contactid FROM vtiger_contactdetails WHERE phone=". $phone . " LIMIT 1";
        $adb = PearDatabase::getInstance();
        $data = $adb->query($sql);

        $value = null;
        if( isset($data->fields) && isset($data->fields['contactid']) ) {
            $value = $data->fields['contactid'];
        }
        return $value;
    }

    function getCAllStartTime($uuid){
        $sql = "SELECT starttime FROM vtiger_pbxmanager WHERE sourceuuid='". $uuid . "' LIMIT 1";
        $adb = PearDatabase::getInstance();
        $data = $adb->query($sql);

        $value = null;
        if( isset($data->fields) && isset($data->fields['starttime']) ) {
            $value = $data->fields['starttime'];
        }
        return $value;
    }

    $operation = vtws_getParameter($_REQUEST, "operation");
    $operation = strtolower($operation);
    $format = vtws_getParameter($_REQUEST, "format","json");
    $sessionId = vtws_getParameter($_REQUEST, "sessionName");

    $sessionManager = new SessionManager();
    $operationManager = new OperationManager($adb,$operation,$format,$sessionManager);

    try{

        if(!$sessionId || strcasecmp($sessionId, "null")===0){
            $sessionId = null;
        }

        $input = $operationManager->getOperationInput();
        $adoptSession = false;
        if(strcasecmp($operation, "extendsession")===0){
            if(isset($input['operation'])){
                // Workaround fix for PHP 5.3.x: $_REQUEST doesn't have PHPSESSID
                if(isset($_REQUEST['PHPSESSID'])) {
                    $sessionId = vtws_getParameter($_REQUEST, "PHPSESSID");
                } else {
                    // NOTE: Need to evaluate for possible security issues
                    $sessionId = vtws_getParameter($_COOKIE, "PHPSESSID");
                }
                // END
                $adoptSession = true;
            }else{
                writeErrorOutput($operationManager, new WebServiceException(WebServiceErrorCode::$AUTHREQUIRED, "Authencation required"));
                return;
            }
        }

        $sid = $sessionManager->startSession($sessionId,$adoptSession);

        if(!$sessionId && !$operationManager->isPreLoginOperation()){
            writeErrorOutput($operationManager,new WebServiceException(WebServiceErrorCode::$AUTHREQUIRED, "Authencation required"));
            return;
        }

        if(!$sid){
            writeErrorOutput($operationManager, $sessionManager->getError());
            return;
        }

        $userid = $sessionManager->get("authenticatedUserId");

        if(!$userid){
            writeErrorOutput($operationManager,new WebServiceException(WebServiceErrorCode::$AUTHREQUIRED, "Authencation required"));
            return;
        }

        // Init database
        $adb = PearDatabase::getInstance();

        $_query = $input['query'];

        //Increase counter only in case new INSERT
        if ($_query === 'INSERT') {
            $crmID = $adb->getUniqueID('vtiger_crmentity');
        } else {
            $crmID = null;
        }

        $timeOfCall = date('Y-m-d H:i:s');
        $getTotalduration = (strtotime($endtime) - strtotime($starttime));

        //UUID:
        // 1. call.uid from AJAX
        // OR
        // 2. md5 (channel + current date (yyyy-mm-dd) )
        $UUID = vtws_getParameter($_REQUEST, "calluid");
        if (!isset($UUID)) {
            $fix = date('Y-m-d');
            $UUID = md5(vtws_getParameter($_REQUEST, "callchannel") . $fix);
        }

        $getDirection = vtws_getParameter($_REQUEST, "direction");
        $getCallstatus = vtws_getParameter($_REQUEST, "callstatus");
        $getCallExitCode = vtws_getParameter($_REQUEST, "callexitcode");

        $getCallstate = vtws_getParameter($_REQUEST, "callstate");
        $getCallDuration = vtws_getParameter($_REQUEST, "callduration");

        $getCallId = vtws_getParameter($_REQUEST, "callid");

        $getCustomernumber = vtws_getParameter($_REQUEST, "customernumber");
        $getCustomer = getContactID($getCustomernumber);

        //Get entries for mysql query
        $pbxmanagerid = $crmID;
        $direction = $getDirection;
        $callstatus = $getCallstatus;
        $starttime = $timeOfCall;
        $endtime = $timeOfCall;
        $totalduration = $getTotalduration;
        $billduration = $totalduration;
        $recordingurl = "http://127.0.0.1/call?" . $UUID;
        $sourceuuid = $UUID;
        $gateway = 'PBXManager';
        $customer = $getCustomer;
        $user = $userid;
        $customernumber = $getCustomernumber;
        $customertype = 'Contacts';

        //FIX some points:
        //'outgoing' and 'incoming' should be replaced as 'inbound' and 'outbound'//
        if ($direction == 'outgoing'){
            $direction = 'outbound';
        } elseif ($direction == 'incoming'){
            $direction = 'inbound';
        }
        //Change 'Normal Clearing' to 'completed'//
        if ($getCallExitCode == '16'){
            $callstatus = 'completed';
        }

        if ($_query === 'INSERT') {
            syslog(LOG_INFO, "CallHistoryService: New call performed");
            $query = "INSERT INTO `vtiger_crmentity` (crmid,smcreatorid,smownerid,modifiedby,setype,description,createdtime,modifiedtime,viewedtime,status,version,presence,deleted,label) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            $params = array($pbxmanagerid, $user, $user, 0, $gateway, "", $timeOfCall, $timeOfCall, NULL, NULL, 0, 1, 0, $customernumber);
            $adb->pquery($query, $params);

            $sql = "INSERT INTO `vtiger_pbxmanager` (pbxmanagerid,direction,callstatus,starttime,endtime,totalduration,billduration,recordingurl,sourceuuid,gateway,customer,user,customernumber,customertype) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            $params = array($pbxmanagerid, $direction, $callstatus, $starttime, $endtime, $totalduration, $billduration, $recordingurl, $sourceuuid, $gateway, $customer, $user, $customernumber, $customertype);
            $adb->pquery($sql, $params);

        } elseif ($_query === 'FINISH') {
            syslog(LOG_INFO, "CallHistoryService: Call finished.");

            $callStartTime = getCAllStartTime($sourceuuid);
            $getTotalduration = (strtotime($endtime) - strtotime($callStartTime));

            $sql = "UPDATE vtiger_pbxmanager SET callstatus=\"" . $callstatus ."\", endtime='" .$endtime . "', totalduration='" .$getTotalduration ."', billduration='" .$getTotalduration . "' WHERE sourceuuid='" .$sourceuuid. "'";
            $adb->query($sql);

        } elseif ($_query === 'RESTORE') {

            $count = 0;
            //Check if call already in CDR
            $sql = "SELECT count(*) as c  FROM `vtiger_pbxmanager` WHERE sourceuuid='" .$sourceuuid. "' LIMIT 1";
            $data = $adb->query($sql);

            if( isset($data->fields) && isset($data->fields['c']) ) {
                $count = $data->fields['c'];
            }

            if ($count == 0 AND isset($sourceuuid)) {

                $pbxmanagerid = $adb->getUniqueID('vtiger_crmentity');
                syslog(LOG_INFO, "CallHistoryService: Call restored.");

                //Calculate start of the call
                if (!isset($getCallDuration)){
                    $getCallDuration = 0;
                }
                $currentTime = strtotime($timeOfCall);
                $callStarted = ($currentTime - $getCallDuration);

                $starttime = date('Y-m-d H:i:s', $callStarted);

                //Insert new record
                $query = "INSERT INTO `vtiger_crmentity` (crmid,smcreatorid,smownerid,modifiedby,setype,description,createdtime,modifiedtime,viewedtime,status,version,presence,deleted,label) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
                $params =array($pbxmanagerid, $user, $user, 0, $gateway, "", $starttime, $starttime, NULL, NULL, 0, 1, 0, $customernumber);
                $adb->pquery($query, $params);

                $sql = "INSERT INTO `vtiger_pbxmanager` (pbxmanagerid,direction,callstatus,starttime,endtime,totalduration,billduration,recordingurl,sourceuuid,gateway,customer,user,customernumber,customertype) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
                $params = array($pbxmanagerid, $direction, $callstatus, $starttime, $endtime, $totalduration, $billduration, $recordingurl, $sourceuuid, $gateway, $customer, $user, $customernumber, $customertype);
                $adb->pquery($sql, $params);
            }

        }

    }catch(WebServiceException $e){
        writeErrorOutput($operationManager,$e);
    }catch(Exception $e){
        writeErrorOutput($operationManager,
            new WebServiceException(WebServiceErrorCode::$INTERNALERROR, "Unknown Error while processing request"));
    }
