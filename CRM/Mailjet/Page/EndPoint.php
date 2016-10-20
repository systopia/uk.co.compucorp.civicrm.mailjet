<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.4                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2013                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

require_once 'CRM/Core/Page.php';

class CRM_Mailjet_Page_EndPoint extends CRM_Core_Page {

  function run() {
    $post = trim(file_get_contents('php://input'));
    if (empty($post)) {
      header('HTTP/1.1 421 No Event');
      CRM_Core_Error::debug_var("ENDPOINT EVENT", "Needs to be called by mailjet", true, true);
      return;
    }

    $httpHeader = CRM_Mailjet_Page_EndPoint::processMessage($post);
    header($httpHeader);
    CRM_Utils_System::civiExit();
  }

  function processMessage($msg) {

    //Decode Trigger Informations
    $trigger = json_decode($msg, true);

    //No Informations sent with the Event
    if (!is_array($trigger) || !isset($trigger['event'])) {
      CRM_Core_Error::debug_var("ENDPOINT EVENT", "Invalid JSON or no event", true, true);
      return 'HTTP/1.1 422 Not ok';
    }

    $event = trim($trigger['event']);
    $email = trim($trigger['email']);
    $time = date('YmdHis', $trigger['time']);
    $mailingId = CRM_Utils_Array::value('customcampaign', $trigger); //CiviCRM mailling ID

    CRM_Core_Error::debug_var("MAILJET TRIGGER", $trigger, true, true);
    if (substr($mailingId, 0, 5) === "TRANS" || substr($mailingId, 0, 15) === "=?utf-8?Q?TRANS") {
      CRM_Core_Error::debug_var("TRANS EMAIL", array($mailingId, $event, $email), true, true);

      $emailResult = civicrm_api3('Email', 'get', array('email' => $email, 'sequential' => 1));
      if (isset($emailResult['values']) && !empty($emailResult['values'])) {
        if ($event == 'bounce' && $trigger['hard_bounce']) {
          //we always get the first result
          $emailId = $emailResult['values'][0]['id'];
          $sql = "UPDATE civicrm_email SET on_hold = %2, hold_date = %3 WHERE id = %1";
          $sqlParams = array(
            1 => array($emailId, 'Integer'),
            2 => array(2, 'Integer'),
            3 => array(date('YmdHis'), 'Timestamp'),
          );
          CRM_Core_DAO::executeQuery($sql, $sqlParams);
        }

        $contactId = $emailResult['values'][0]['contact_id'];
        $params = array(
          'sequential' => 1,
          'activity_type_id' => 58, // Bounce
          'activity_date_time' => $time,
          'status_id' => 'Completed',
          'subject' => $event,
          'details' => 'Added by mailjet extension, error: '
	      .CRM_Utils_Array::value('error_related_to', $trigger).', '
	      .CRM_Utils_Array::value('error', $trigger)
	      .'. blocked='
	      .(int)CRM_Utils_Array::value('blocked', $trigger)
	      .'. hard_bounce='
	      .(int)CRM_Utils_Array::value('hard_bounce', $trigger),
          'source_contact_id' => $contactId,
        );
        civicrm_api3('Activity', 'create', $params);

        if ($event == 'unsub') {
          $sql = "UPDATE civicrm_email SET on_hold = %3, hold_date = %1 WHERE  email = %2";
          $sqlParams = array(
            1 => array(date('YmdHis'), 'Timestamp'),
            2 => array($email, 'String'),
            3 => array(2, 'Integer'),
          );
          CRM_Core_DAO::executeQuery($sql, $sqlParams);
          $params = array(
            'sequential' => 1,
            'id' => $contactId,
            'is_opt_out' => 1,
          );
          civicrm_api3('Contact', 'create', $params);
        }
        return 'HTTP/1.1 200 Ok';
      }
    }

    if ($mailingId) { //we only process if mailing_id exist - marketing email
      $mailjetCampaignId = CRM_Utils_Array::value('mj_campaign_id', $trigger);
      $mailjetContactId = CRM_Utils_Array::value('mj_contact_id' , $trigger);

      $mailjetEvent = new CRM_Mailjet_DAO_Event();
      $mailjetEvent->mailing_id = $mailingId;
      $mailjetEvent->email = $email;
      $mailjetEvent->event = $event;
      $mailjetEvent->mj_campaign_id = $mailjetCampaignId;
      $mailjetEvent->mj_contact_id = $mailjetContactId;
      $mailjetEvent->time = $time;
      $mailjetEvent->data = serialize($trigger);
      $mailjetEvent->created_date = date('YmdHis');
      $mailjetEvent->save(); //log event

      if ($event == 'typofix') {
        //we do not handle typofix
        // TODO:: notifiy admin
        return 'HTTP/1.1 200 Ok';
      }

      $emailResult = civicrm_api3('Email', 'get', array('email' => $email, 'sequential' => 1));
      if (isset($emailResult['values']) && !empty($emailResult['values'])) {
        //we always get the first result
        $contactId = $emailResult['values'][0]['contact_id'];
        $emailId = $emailResult['values'][0]['id'];
        $params = array(
          'mailing_id' => $mailingId,
          'contact_id' => $contactId,
          'email_id' => $emailId,
          'date_ts' =>  $trigger['time'],
        );
        /*
        *  Event handler
        *  - please check https://www.mailjet.com/docs/event_tracking for further informations.
        */
        switch ($trigger['event']) {
          case 'sent':
          case 'open':
          case 'click':
          case 'unsub':
          case 'typofix':
            break;
          //we treat bounce, span and blocked as bounce mailing in CiviCRM
          case 'bounce':
          case 'spam':
          case 'blocked':
            $params['hard_bounce'] =  CRM_Utils_Array::value('hard_bounce', $trigger);
            $params['blocked'] = CRM_Utils_Array::value('blocked', $trigger);
            $params['source'] = CRM_Utils_Array::value('source', $trigger);
            $params['error_related_to'] =  CRM_Utils_Array::value('error_related_to', $trigger);
            $params['error'] =   CRM_Utils_Array::value('error', $trigger);
            $job_id = explode('MJ', $mailingId); // $mailingId is not exactly ID, this is CustomValue!
            $params['job_id'] = (int) $job_id[0];
            $params['email'] = $email;
            if (!empty($params['source'])) {
              $params['is_spam'] = TRUE;
            } else {
              $params['is_spam'] = FALSE;
            }
            CRM_Mailjet_BAO_Event::recordBounce($params);
            //TODO: handle error
            break;
          # No handler
          default:
            CRM_Core_Error::debug_var("MAILJET TRIGGER", "No handler ", true, true);
            return 'HTTP/1.1 423 No handler';
        }
      }
    } else { //assumed if there is not mailing_id, this should be a transaction email
      //TODO::process a transaction email
    }
    return 'HTTP/1.1 200 Ok';
  }

}
