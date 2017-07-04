<?php

class CRM_Mailjet_Logic_Message {
  public $event = '';
  public $email = '';

  /** @var int|mixed CiviCRM mailing id - is not exactly ID, this is CustomValue */
  public $mailingId = '';
  public $job_id = 0;
  public $time = '';
  public $mailjetCampaignId = '';
  public $mailjetContactId = '';
  public $hard_bounce = 0;
  public $blocked = 0;
  public $source = '';
  public $error_related_to = '';
  public $error = '';
  public $message;
  public $trigger;

  function __construct($message) {
    $this->message = $message;
    $trigger = json_decode($message, true);
    $this->trigger = $trigger;
    $this->event = trim(CRM_Utils_Array::value('event', $trigger));
    $this->email = str_replace('"', '', trim(CRM_Utils_Array::value('email', $trigger)));
    $this->mailingId = CRM_Utils_Array::value('customcampaign', $trigger);
    $this->job_id = (int)explode('MJ', $this->mailingId)[0];
    $this->time = date('YmdHis', CRM_Utils_Array::value('time', $trigger));
    $this->hard_bounce = (int)CRM_Utils_Array::value('hard_bounce', $trigger);
    $this->blocked = (int)CRM_Utils_Array::value('blocked', $trigger);
    $this->source = CRM_Utils_Array::value('source', $trigger);
    $this->error_related_to = CRM_Utils_Array::value('error_related_to', $trigger);
    $this->error = CRM_Utils_Array::value('error', $trigger);
    $this->mailjetCampaignId = CRM_Utils_Array::value('mj_campaign_id', $trigger);
    $this->mailjetContactId = CRM_Utils_Array::value('mj_contact_id' , $trigger);
  }

  public function isValid() {
    return !!$this->event;
  }
}
