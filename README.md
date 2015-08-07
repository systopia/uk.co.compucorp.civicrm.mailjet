CiviMailJet - MailJet integration for CiviCRM 
===============================

## Features
This extension allows to automatically update the number of bounces for civimail mailings. As with the standard civiway of handing bounces, it automatically flag invalid emails "on hold". You DO NOT have to configure or run the cronjob that fetch the bounces from a mailbox, mailjet directly sends these events to civicrm using the endpoint provided by this extension.

Thanks to [WeMove.EU](https://www.wemove.eu) contribution, this extension also flags transactional emails (eg. automatic emails sent to confirm a donation or event registration, request to confirm their subscriptions...). In order to do that, it automatically create a fake campaign on mailjet with the sender email as its name.


##setup for jetmail
you do not need that extension to send emails, simply use mailjet smtp interface (using the api key as login and secret as password, as explained on their site)

You should be able to send emails and see on mailjet site how many were sent.

##Setup instructions for CiviMailjet extensions

1. Download and install the extension into the site extension directory.
2. Set up the CiviCRM Outbond email using SMTP  with  Mailjet's SMTP Credentials.
3. Config Event Tracking Endpoint Url in your Mailjet account using http://<yoursite>/civicrm/mailjet/event/endpoint
Do not trigger events for open and click if you expect to handle any big mailinngs

4. Add add the code below into the site civicrm settings file and put your mailjet api and secret key


>define( 'MAILJET_API_KEY', 'YOUR MAILJET API KEY');<br/>
>define( 'MAILJET_SECRET_KEY', 'YOUR MAILJET SECRET KEY');



