#!/bin/bash 

ENDPOINT=https://civi-dev.wemove.eu/civicrm/mailjet/event/endpoint
DB=civi_dev

# This can be pretty much anything
TYPOFIX_TIME=$(date +%s)
TYPOFIX_EMAIL="typo@gmail.cmo"
TYPOFIX_JOBID=12345

# Put here the details of a mailing and email delivered by CiviCRM
BOUNCE_TIME=$(date +%s)
BOUNCE_EMAIL="de@wemove.thouvenin.pro"
BOUNCE_JOBID=10052

# Put here the details of a mailing and email delivered by CiviCRM
SENT_TIME=$(date +%s)
SENT_EMAIL="es@wemove.thouvenin.pro"
SENT_JOBID=10052


echo " [x] Test: typofix"
echo "This event is not handled but should be stored for possible later processing"
curl $ENDPOINT -d "{
    \"event\":\"typofix\",
    \"time\":${TYPOFIX_TIME},
    \"MessageID\":123456789,
    \"email\":\"${TYPOFIX_EMAIL}\",
    \"customcampaign\":\"${TYPOFIX_JOBID}MJ1476454150\"
  }"

TYPOFIX_QUERY="SELECT 1 as expected, COUNT(*) as actual 
  FROM civicrm_mailing_mailjet_event 
  WHERE event='typofix' AND email='${TYPOFIX_EMAIL}' AND time=FROM_UNIXTIME(${TYPOFIX_TIME})"
TYPOFIX_CANCEL="DELETE FROM civicrm_mailing_mailjet_event 
  WHERE event='typofix' AND email='${TYPOFIX_EMAIL}' AND time=FROM_UNIXTIME(${TYPOFIX_TIME})"

echo "Number of rows created in civicrm_mailing_mailjet_event:"
mysql $DB -e "$TYPOFIX_QUERY"
echo -e "\n"


echo " [x] Test: bounce"
echo "This event should create a row in civicrm_mailing_event_bounce (on top of the one in _delivered)"
curl $ENDPOINT -d "{
    \"event\":\"bounce\",
    \"time\":${BOUNCE_TIME},
    \"MessageID\":123456789,
    \"email\":\"${BOUNCE_EMAIL}\",
    \"customcampaign\":\"${BOUNCE_JOBID}MJ1476454150\"
  }"

BOUNCE_QUERY="SELECT 1 AS expected_delivered, COUNT(d.time_stamp) AS actual_delivered, 
    1 AS expected_bounced, COUNT(b.time_stamp) as actual_bounced 
  FROM civicrm_mailing_job j JOIN civicrm_mailing_event_queue q ON q.job_id=j.id 
  JOIN civicrm_email e ON e.id=q.email_id 
  LEFT JOIN civicrm_mailing_event_delivered d on d.event_queue_id=q.id 
  LEFT JOIN civicrm_mailing_event_bounce b ON b.event_queue_id=q.id 
  WHERE j.id=${BOUNCE_JOBID} AND e.email='${BOUNCE_EMAIL}'"
BOUNCE_CANCEL="DELETE FROM civicrm_mailing_event_bounce
  WHERE time_stamp=FROM_UNIXTIME(${BOUNCE_TIME})"

echo "Number of events"
mysql $DB -e "$BOUNCE_QUERY"
echo -e "\n"


echo " [x] Test: sent"
echo "This event should update a row from civicrm_mailing_event_delivered"
echo "A second call with same email and job id shoudl have no effect"
SENT_OTIME=$(mysql civi_dev -sse "SELECT d.time_stamp FROM civicrm_mailing_event_queue q JOIN civicrm_email e ON e.id=q.email_id JOIN civicrm_mailing_event_delivered d ON d.event_queue_id=q.id WHERE q.job_id=${SENT_JOBID} and e.email='${SENT_EMAIL}'")
curl $ENDPOINT -d "{
    \"event\":\"sent\",
    \"time\":${SENT_TIME},
    \"MessageID\":123456789,
    \"email\":\"${SENT_EMAIL}\",
    \"customcampaign\":\"${SENT_JOBID}MJ1476454150\"
  }"

SENT_QUERY="SELECT FROM_UNIXTIME(${SENT_TIME}) AS expected, time_stamp AS actual, 
  '${SENT_OTIME}' AS expected_original, original_time_stamp AS actual_original
  FROM civicrm_mailing_event_queue q 
  JOIN civicrm_email e ON e.id=q.email_id 
  JOIN civicrm_mailing_event_delivered d on d.event_queue_id=q.id 
  WHERE q.job_id=${SENT_JOBID} AND e.email='${SENT_EMAIL}'"
SENT_CANCEL="UPDATE civicrm_mailing_event_delivered
  SET time_stamp=original_time_stamp, original_time_stamp=NULL
  WHERE time_stamp=FROM_UNIXTIME(${SENT_TIME})"

echo "New delivery times:"
mysql $DB -e "$SENT_QUERY"

curl $ENDPOINT -d "{
    \"event\":\"sent\",
    \"time\":${SENT_TIME},
    \"MessageID\":123456789,
    \"email\":\"${SENT_EMAIL}\",
    \"customcampaign\":\"${SENT_JOBID}MJ1476454150\"
  }"
echo "After a second call:"
mysql $DB -e "$SENT_QUERY"
echo -e "\n"


echo "Test execution finished"
echo "You can revert the effects of the tests with these queries"
echo "${TYPOFIX_CANCEL};"
echo "${BOUNCE_CANCEL};"
echo "${SENT_CANCEL};"
echo

