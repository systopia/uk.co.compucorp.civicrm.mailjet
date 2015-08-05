<?php

require_once 'mailjet.civix.php';

/**
 * Implementation of hook_civicrm_alterMailParams( )
 * To add Mailjet headers in mail
 */
function mailjet_civicrm_alterMailParams(&$params, $context) {
  $jobId = CRM_Utils_Array::value('job_id', $params); //CiviCRM job ID
  if (isset($jobId)){
    //$apiParams = array( // TP this line is redundant, never used
    //  'id' => $jobId
    //);
    //$mailJobResult = civicrm_api3('MailingJob', 'get', $apiParams); // TP this line is redundant, never used
    //$mailingId = $mailJobResult['values'][$jobId]['mailing_id']; // TP this line is redundant, never used
    $params['headers']['X-Mailjet-Campaign'] = CRM_Mailjet_BAO_Event::getMailjetCustomCampaignId($jobId);
    $params['headers']['X-Mailjet-CustomValue'] = CRM_Mailjet_BAO_Event::getMailjetCustomCampaignId($jobId);
  } else {
    $params['headers']['X-Mailjet-Campaign'] = "TRANS-".$params["from"];
    $params['headers']['X-Mailjet-CustomValue'] = "Trans-".time(); // CustomValue have to be unique
  }
}


/**
 * Implementation of hook_civicrm_pageRun
 *
 * Handler for pageRun hook.
 */
function mailjet_civicrm_pageRun(&$page) {
  $t = 'a';
  if(get_class($page) == 'CRM_Mailing_Page_Report'){
    $t .= '1';
    $page->assign('test', $t);
    $mailingId = $page->_mailing_id;
    $mailingJobs = civicrm_api3('MailingJob', 'get', $params = array('mailing_id' => $mailingId));
    $t .= '2';
    $page->assign('test', $t);

	$jobId = 0;
	foreach($mailingJobs['values'] as $key => $job){
		if($job['job_type'] == 'child'){
			$jobId = $key;
    $t .= '3';
    $page->assign('test', $t);

    require_once('packages/mailjet-0.3/php-mailjet-v3-simple.class.php');
    // Create a new Mailjet Object
    $mj = new Mailjet(MAILJET_API_KEY, MAILJET_SECRET_KEY);
    $mj->debug = 0;
    $mailJetParams = array(
       'method' => 'VIEW',
       'unique' => CRM_Mailjet_BAO_Event::getMailjetCustomCampaignId($jobId),
    );
    $response = $mj->campaign($mailJetParams);
    $page->assign('mailjet_params', $mailJetParams);

    $t .= '4';
    $page->assign('test', $t);
    if(!empty($response)){
      $t .= '5';
      $page->assign('test', $t);
      if ($response->Count == 1){
        $t .= '6';
        $page->assign('test', $t);
        $campaign = $response->Data[0];
        $mailJetParams = array(
          'method' => 'VIEW',
          'unique' => $campaign->ID
        );
        $response = $mj->campaignstatistics($mailJetParams);
        if($response->Count == 1){
          $t .= '7';
          $page->assign('test', $t);
          $stats = $response->Data[0];
          $page->assign('mailjet_stats', get_object_vars($stats));
        }
      }
    }
	}
	}
    $t .= '8';
    $page->assign('test', $t);
    CRM_Core_Region::instance('page-header')->add(array(
      'template' => 'CRM/Mailjet/Page/Report.tpl',
    ));
  }
}



/**
 * Implementation of hook_civicrm_config
 */
function mailjet_civicrm_config(&$config) {
  _mailjet_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 */
function mailjet_civicrm_xmlMenu(&$files) {
  _mailjet_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_install
 */
function mailjet_civicrm_install() {
  return _mailjet_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_uninstall
 */
function mailjet_civicrm_uninstall() {

  return _mailjet_civix_civicrm_uninstall();
}

/**
 * Implementation of hook_civicrm_enable
 */
function mailjet_civicrm_enable() {
  return _mailjet_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_disable
 */
function mailjet_civicrm_disable() {
  return _mailjet_civix_civicrm_disable();
}

/**
 * Implementation of hook_civicrm_upgrade
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed  based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 */
function mailjet_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _mailjet_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implementation of hook_civicrm_managed
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 */
function mailjet_civicrm_managed(&$entities) {
  return _mailjet_civix_civicrm_managed($entities);
}
