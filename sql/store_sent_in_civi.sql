-- Script to alter the schema and data to store 'sent' mailjet events in
-- civicrm_mailing_event_delivered instead of civicrm_mailing_mailjet_event

-- This column will receive the civi timestamp, when we receive the mailjet one
ALTER TABLE civicrm_mailing_event_delivered 
  ADD COLUMN (original_time_stamp DATETIME);

-- Move existing data
UPDATE civicrm_mailing_event_delivered civi
  JOIN civicrm_mailing_event_queue q ON civi.event_queue_id=q.id
  JOIN civicrm_email e ON e.id=q.email_id AND e.is_primary=1
  JOIN civicrm_mailing_mailjet_event mj ON mj.email=e.email AND mj.mailing_id=q.job_id AND mj.event='sent'
  SET civi.original_time_stamp=civi.time_stamp, civi.time_stamp=mj.time, mj.event='xxxx'; 
