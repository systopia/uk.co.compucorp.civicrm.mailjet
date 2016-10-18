<?php

session_start();
$settingsFile = trim(implode('', file('sitepath.inc'))).'/civicrm.settings.php';
define('CIVICRM_SETTINGS_PATH', $settingsFile);
$error = @include_once( $settingsFile );
if ( $error == false ) {
  echo "Could not load the settings file at: {$settingsFile}\n";
  exit( );
}

// Load class loader
global $civicrm_root;
require_once $civicrm_root . '/CRM/Core/ClassLoader.php';
CRM_Core_ClassLoader::singleton()->register();
require_once 'CRM/Core/Config.php';
$civicrm_config = CRM_Core_Config::singleton();

require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;


const MJ_MAX_LOAD = 2;
const MJ_LOAD_CHECK_FREQ = 20;

$msg_since_check = MJ_LOAD_CHECK_FREQ;
$arguments = getopt('q:');
$queue_name = $arguments['q'];

$connection = new AMQPStreamConnection(
    CIVICRM_AMQP_HOST, CIVICRM_AMQP_PORT,
    CIVICRM_AMQP_USER, CIVICRM_AMQP_PASSWORD, CIVICRM_AMQP_VHOST);
$channel = $connection->channel();

$callback = function($msg) {
  global $msg_since_check;
  $msg_handler = new CRM_Mailjet_Page_EndPoint();
  $msg_handler->processMessage($msg->body);
  $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
  $msg_since_check++;
};
$channel->basic_consume($queue_name, '', false, false, false, false, $callback);

echo ' [*] Waiting for messages. To exit press CTRL+C', "\n";
while(count($channel->callbacks)) {
  if ($msg_since_check >= MJ_LOAD_CHECK_FREQ) {
    $load = sys_getloadavg()[0];
    if ($load > MJ_MAX_LOAD) {
      echo "Current load greater than ".MJ_MAX_LOAD.", suspending polling...\n";
      sleep(5);
      continue;
    } else {
      $msg_since_check = 0;
    }
  }
  $channel->wait();
}

