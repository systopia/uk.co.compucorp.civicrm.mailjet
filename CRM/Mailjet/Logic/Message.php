<?php

class CRM_Mailjet_Logic_Message {
  public $event = '';
  public $email = '';

  /** @var int|mixed CiviCRM mailing id - is not exactly ID, this is CustomValue */
  public $mailingId = 0;
  public $job_id = 0;
  public $time = '';
  public $hard_bounce = '';
  public $blocked = '';
  public $source = '';
  public $error_related_to = '';
  public $error = '';
  public $message;

  function __construct($message) {
    $this->message = $message;
    $trigger = json_decode($message, true);
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
  }

  public function isValid() {
    return !!$this->event;
  }
}
