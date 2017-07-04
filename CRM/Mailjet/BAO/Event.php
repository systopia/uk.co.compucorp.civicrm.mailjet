<?php

class CRM_Mailjet_BAO_Event extends CRM_Mailjet_DAO_Event {

  static function getMailjetCustomCampaignId($jobId) {
    if ($jobId !== null) {
      $mailingJob = civicrm_api3('MailingJob', 'get', $params = array('id' => $jobId));
      if ($mailingJob['values'][$jobId]['job_type'] == 'child') {
        $timestamp = strtotime($mailingJob['values'][$jobId]['scheduled_date']);
        return $jobId . 'MJ' . $timestamp;
      }
    }
    return 0 . 'MJ' . strtotime("now");
  }

  /**
   * Store a raw event in the mailjet table
   *
   * @param \CRM_Mailjet_Logic_Message $message
   */
  static function createFromPostData(CRM_Mailjet_Logic_Message $message) {
    $mailjetEvent = new CRM_Mailjet_DAO_Event();
    $mailjetEvent->mailing_id = $message->mailingId;
    $mailjetEvent->email = $message->email;
    $mailjetEvent->event = $message->event;
    $mailjetEvent->mj_campaign_id = $message->mailjetCampaignId;
    $mailjetEvent->mj_contact_id = $message->mailjetContactId;
    $mailjetEvent->time = $message->time;
    $mailjetEvent->data = serialize($message->trigger);
    $mailjetEvent->created_date = date('YmdHis');
    $mailjetEvent->save(); 
  }

  static function recordBounce($params) {
    $isSpam =  CRM_Utils_Array::value('is_spam', $params);
    $mailingId = CRM_Utils_Array::value('mailing_id', $params); //CiviCRM mailling ID
    $contactId = CRM_Utils_Array::value('contact_id' , $params);
    $emailId =  CRM_Utils_Array::value('email_id' , $params);
    $email = CRM_Utils_Array::value('email' , $params);
    $jobId = CRM_Utils_Array::value('job_id' , $params);
    $eqParams = array(
      'job_id' => $jobId,
      'contact_id' => $contactId,
      'email_id' => $emailId,
    );
    $eventQueue = CRM_Mailing_Event_BAO_Queue::create($eqParams);
    $time =  date('YmdHis', CRM_Utils_Array::value('date_ts', $params));
    $bounceType = array();
    CRM_Core_PseudoConstant::populate($bounceType, 'CRM_Mailing_DAO_BounceType', TRUE, 'id', NULL, NULL, NULL, 'name');
    $bounce = new CRM_Mailing_Event_BAO_Bounce();
    $bounce->time_stamp = $time;
    $bounce->event_queue_id = $eventQueue->id;
    if ($isSpam) {
      $bounce->bounce_type_id = $bounceType[CRM_Mailjet_Upgrader::SPAM];
      $bounce->bounce_reason = CRM_Utils_Array::value('source', $params); //bounce reason when spam occured
    } else {
      $hardBounce = CRM_Utils_Array::value('hard_bounce', $params);
      $blocked = CRM_Utils_Array::value('blocked', $params); //  blocked : true if this bounce leads to recipient being blocked
      if ($hardBounce && $blocked) {
        $bounce->bounce_type_id = $bounceType[CRM_Mailjet_Upgrader::BLOCKED];
      } elseif ($hardBounce && !$blocked){
        $bounce->bounce_type_id = $bounceType[CRM_Mailjet_Upgrader::HARD_BOUNCE];
      } else {
        if (self::isHardError($params)) {
          $bounce->bounce_type_id = $bounceType[CRM_Mailjet_Upgrader::HARD_BOUNCE];
        } else {
          $bounce->bounce_type_id = $bounceType[CRM_Mailjet_Upgrader::SOFT_BOUNCE];
        }
      }
      $bounce->bounce_reason  =  $params['error_related_to'] . " - " . $params['error'];
    }
    $bounce->save();
    if ($bounce->bounce_type_id != $bounceType[CRM_Mailjet_Upgrader::SOFT_BOUNCE]) {
      $params = array(
        'id' => $contactId,
        'do_not_email' => 1,
      );
      civicrm_api3('Contact', 'create', $params);
    }
    return TRUE;
  }

  /**
   * Check if error should be hard bounce.
   *
   * @param $params
   *
   * @return bool
   */
  private static function isHardError($params) {
    $error = CRM_Utils_Array::value('error', $params);
    $hardErrors = [
      'invalid domain',
      'relay/access denied',
      'typofix',
    ];
    return in_array($error, $hardErrors);
  }
}
