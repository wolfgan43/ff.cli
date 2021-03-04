<?php
class Config extends \phpformsframework\libs\Constant
{
    const APPID                     = 'appid';
    const APPNAME                   = 'appname';

    const MYSQL_DATABASE_HOST       = 'mysqlhost';
    const MYSQL_DATABASE_NAME       = 'mysqldbname';
    const MYSQL_DATABASE_USER       = 'mysqluser';
    const MYSQL_DATABASE_SECRET     = 'mysqlsecret';

    const MONGO_DATABASE_HOST       = 'mongohost';
    const MONGO_DATABASE_NAME       = 'mongodbname';
    const MONGO_DATABASE_USER       = 'mongouser';
    const MONGO_DATABASE_SECRET     = 'mongosecret';

    const SMTP_DRIVER               = 'smtp';
    const SMTP_HOST                 = 'localhost';
    const SMTP_AUTH                 = false;
    const SMTP_USER                 = 'smtpuser';
    const SMTP_SECRET               = 'smtpsecret';
    const SMTP_PORT                 = '1025';
    const SMTP_SECURE               = false;

    const FROM_EMAIL                = 'fromemail';
    const FROM_NAME                 = 'fromname';
    const DEBUG_EMAIL               = 'debugemail';

    const TWILIO_SMS_SID            = 'twiliosmssid';
    const TWILIO_SMS_TOKEN          = 'twiliosmstoken';
    const TWILIO_SMS_FROM           = 'twiliosmsfrom';

    const DEBUG                     = true;
    const DISABLE_CACHE             = true;

    const API_SERVER                = [];
}
