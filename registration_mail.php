<?php
require_once __DIR__.'/mail_settings.php';

/** Keep mail transport failures out of the registration response. */
function sendRegistrationVerification(string $email, string $subject, string $message, string $headers): bool
{
    try {
        $settings=loadAppMailSettings();
        if($settings['gmail_address']===''||$settings['app_password']==='') {
            error_log('Email unavailable: configure Gmail SMTP in Administrator Email settings.');
            return false;
        }
        $mailer=configuredGmailMailer($settings);
        $mailer->addAddress($email);$mailer->Subject=$subject;$mailer->Body=$message;
        return $mailer->send();
    } catch (Throwable $e) {
        // Never log credentials, SMTP dialogue, reset links, or message bodies.
        error_log('Gmail SMTP email delivery failed. Check email settings and server connectivity.');
        return false;
    }
}
