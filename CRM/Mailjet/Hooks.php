<?php
/*
 +-------------------------------------------------------+
| 2020 SYSTOPIA                                          |
| Author: P. Batroff (batroff@systopia.de)               |
 +-------------------------------------------------------+
*/

/**
 * Defines the hooks that allow specialized bounce handling
 */
class CRM_Mailjet_Hooks {

  static $null = NULL;

  /**
   * @param $bounce_message
   *
   * @return mixed
   */
  static function handle_transactional_event(&$bounce_message) {

    if (version_compare(CRM_Utils_System::version(), '4.5', '<')) {
      return CRM_Utils_Hook::singleton()->invoke(3, $bounce_message, self::$null, self::$null, self::$null, self::$null, 'civicrm_mailjet_transactional_event');
    }
    else {
      return CRM_Utils_Hook::singleton()->invoke(3, $bounce_message, self::$null, self::$null, self::$null, self::$null, self::$null, 'civicrm_mailjet_transactional_event');
    }
  }

  /**
   * @param $bounce_message
   *
   * @return mixed
   */
  static function handle_mailing_event(&$bounce_message) {

    if (version_compare(CRM_Utils_System::version(), '4.5', '<')) {
      return CRM_Utils_Hook::singleton()->invoke(3, $bounce_message, self::$null, self::$null, self::$null, self::$null, 'civicrm_mailjet_mailing_event');
    }
    else {
      return CRM_Utils_Hook::singleton()->invoke(3, $bounce_message, self::$null, self::$null, self::$null, self::$null, self::$null, 'civicrm_mailjet_mailing_event');
    }
  }
}
