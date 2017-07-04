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

    $message = new CRM_Mailjet_Logic_Message($msg);
    if (!$message->isValid()) {
      CRM_Core_Error::debug_var("ENDPOINT EVENT", "Invalid JSON or no event", true, true);
      return 'HTTP/1.1 422 Not ok';
    }

    //Decode Trigger Informations
    $trigger = json_decode($msg, true);

    if (substr($message->mailingId, 0, 5) === "TRANS" || substr($message->mailingId, 0, 15) === "=?utf-8?Q?TRANS") {
      $allowedEvents = array('bounce', 'blocked', 'spam', 'unsub');
      if (!in_array($message->event, $allowedEvents)) {
        return 'HTTP/1.1 200 Ok';
      }

      $emailResult = civicrm_api3('Email', 'get', array('email' => $message->email, 'sequential' => 1));
      if (isset($emailResult['values']) && !empty($emailResult['values'])) {
        //we always get the first result
        $emailId = $emailResult['values'][0]['id'];
        $contactId = $emailResult['values'][0]['contact_id'];

        if ($message->event == 'bounce' && $message->hard_bounce) {
          $this->setOnHoldHard($emailId, $message->email);
        }
        $this->createBounceActivity($trigger, $contactId);
      }

      if ($message->event == 'unsub') {
        $this->setOnHoldHard($emailId, $message->email);
        $this->setOptOut($contactId);
      }
      return 'HTTP/1.1 200 Ok';
    }

    if ($message->mailingId && $message->mailingId[0] != '0') { //we only process if mailing_id exist - marketing email
      /* https://www.mailjet.com/docs/event_tracking for more informations. */
      switch ($message->event) {
        //For unsupported events, we just store them raw
        case 'open':
        case 'click':
        case 'unsub':
        case 'typofix':
          CRM_Mailjet_BAO_Event::createFromPostData($trigger);
          return 'HTTP/1.1 200 Ok';

        //We replace the civi delivery time with the mailjet one
        //but keep the civi one for comparison
        case 'sent':
          $emailResult = civicrm_api3('Email', 'get', array('email' => $message->email, 'sequential' => 1));
          if (isset($emailResult['values']) && !empty($emailResult['values'])) {
            CRM_Mailjet_Page_EndPoint::updateDelivery($trigger, $emailResult);
            return 'HTTP/1.1 200 Ok';
          }
          else {
            //This shouldn't happen, let's log the event
            CRM_Mailjet_BAO_Event::createFromPostData($trigger);
            CRM_Core_Error::debug_var("MAILJET TRIGGER", "Unknown address $message->email event " . $message->event, true, true);
            return  'HTTP/1.1 422 unknown email address';
          }

        //we treat bounce, span and blocked as bounce mailing in CiviCRM
        case 'bounce':
        case 'spam':
        case 'blocked':
          $emailResult = civicrm_api3('Email', 'get', array('email' => $message->email, 'sequential' => 1));
          if (isset($emailResult['values']) && !empty($emailResult['values'])) {
            $params = CRM_Mailjet_Page_EndPoint::prepareBounceParams($trigger, $emailResult);
            CRM_Mailjet_BAO_Event::recordBounce($params);
            return 'HTTP/1.1 200 Ok';
          }
          else {
            //This shouldn't happen, let's log the event
            CRM_Mailjet_BAO_Event::createFromPostData($trigger);
            CRM_Core_Error::debug_var("MAILJET TRIGGER", "Unknown address $message->email event " . $message->event, true, true);
            return  'HTTP/1.1 422 unknown email address';
          }
        # No handler
        default:
          CRM_Core_Error::debug_var("MAILJET TRIGGER", "No handler for $message->event", true, true);
          return 'HTTP/1.1 422 unknown event';
      }
    } else { //assumed if there is not mailing_id, this should be a transaction email
      //TODO::process a transaction email
    }
    return 'HTTP/1.1 200 Ok';
  }

  function updateDelivery($trigger, $emailResult) {
    $mailingId = CRM_Utils_Array::value('customcampaign', $trigger);
    $job_id = explode('MJ', $mailingId)[0]; // $mailingId is not exactly ID, this is CustomValue!
    $email_id = $emailResult['values'][0]['id'];
    $time = date('YmdHis', CRM_Utils_Array::value('time', $trigger));
    $query = "UPDATE civicrm_mailing_event_delivered d
              JOIN civicrm_mailing_event_queue q ON d.event_queue_id = q.id
              SET d.mailjet_time_stamp = %3
              WHERE q.job_id = %1 AND q.email_id = %2 AND d.mailjet_time_stamp = '1970-01-01'";
    $params = array(
      1 => array($job_id, 'Integer'),
      2 => array($email_id, 'Integer'),
      3 => array($time, 'String'),
    );
    CRM_Core_DAO::executeQuery($query, $params);
  }

  function prepareBounceParams($trigger, $emailResult) {
    $mailingId = CRM_Utils_Array::value('customcampaign', $trigger);
    //we always get the first result
    $contactId = $emailResult['values'][0]['contact_id'];
    $emailId = $emailResult['values'][0]['id'];
    $params = array(
      'mailing_id' => $mailingId,
      'contact_id' => $contactId,
      'email_id' => $emailId,
      'date_ts' =>  $trigger['time'],
    );
    $params['hard_bounce'] =  CRM_Utils_Array::value('hard_bounce', $trigger);
    $params['blocked'] = CRM_Utils_Array::value('blocked', $trigger);
    $params['source'] = CRM_Utils_Array::value('source', $trigger);
    $params['error_related_to'] =  CRM_Utils_Array::value('error_related_to', $trigger);
    $params['error'] =   CRM_Utils_Array::value('error', $trigger);
    $job_id = explode('MJ', $mailingId); // $mailingId is not exactly ID, this is CustomValue!
    $params['job_id'] = (int) $job_id[0];
    $params['email'] = trim($trigger['email']);
    $params['is_spam'] = !empty($params['source']);

    return $params;
  }

  function setOnHoldHard($emailId, $email) {
    $params = array(
      'sequential' => 1,
      'id' => $emailId,
      'email' => $email,
      'on_hold' => 2,
      'hold_date' => date('YmdHis'),
    );
    civicrm_api3('Email', 'create', $params);
  }

  function setOptOut($contactId) {
    $params = array(
      'sequential' => 1,
      'id' => $contactId,
      'is_opt_out' => 1,
    );
    civicrm_api3('Contact', 'create', $params);
  }

  function createBounceActivity($trigger, $contactId) {
    $event = trim(CRM_Utils_Array::value('event', $trigger));
    $time = date('YmdHis', CRM_Utils_Array::value('time', $trigger));
    $params = array(
      'sequential' => 1,
      'activity_type_id' => 58, // Bounce
      'activity_date_time' => $time,
      'status_id' => 'Completed',
      'subject' => $event,
      'details' => $this->prepareDetails($trigger),
      'source_contact_id' => $contactId,
    );
    civicrm_api3('Activity', 'create', $params);
  }

  function prepareDetails($trigger) {
    return  'Added by mailjet extension, error: '
      . CRM_Utils_Array::value('error_related_to', $trigger)
      . ', '
      . CRM_Utils_Array::value('error', $trigger)
      . '. blocked='
      . (int)CRM_Utils_Array::value('blocked', $trigger)
      . '. hard_bounce='
      . (int)CRM_Utils_Array::value('hard_bounce', $trigger)
      . ', json=' . json_encode($trigger);
  }
}
