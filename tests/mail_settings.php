<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../mail_settings.php';
function ensureMail($condition,$label){if(!$condition)throw new RuntimeException($label);echo "PASS: $label\n";}
$temp=sys_get_temp_dir().'/mudiang-mail-test-'.bin2hex(random_bytes(8)).'.json';
$settings=['gmail_address'=>'fixture@gmail.com','app_password'=>'abcd efgh ijkl mnop','sender_name'=>'Barangay Mudiang','base_url'=>'http://localhost/System'];
try {
    $validated=validateAppMailSettings($settings);ensureMail($validated['app_password']==='abcdefghijklmnop','Google app password spaces removed');
    saveAppMailSettings($settings,$temp);$loaded=loadAppMailSettings($temp);ensureMail($loaded===$validated,'Protected settings save and reload');
    $mailer=configuredGmailMailer($loaded);ensureMail($mailer->Host==='smtp.gmail.com'&&$mailer->Port===587&&$mailer->SMTPSecure==='tls'&&$mailer->SMTPAuth,'Gmail uses authenticated STARTTLS on port 587');
    ensureMail($mailer->SMTPDebug===0,'SMTP credential dialogue is not logged');
    $mailer->addAddress('recipient@example.com');$mailer->Subject='Verification fixture';$mailer->Body='Test body — unicode';
    ensureMail($mailer->preSend(),'PHPMailer prepares a UTF-8 message without sending it');
    ensureMail(!str_contains($mailer->getSentMIMEMessage(),$loaded['app_password']),'Credentials omitted from email content');
    foreach(['https://user:pass@example.com/System','http://example.com/System','https://example.com/System?token=1','javascript:alert(1)'] as $badUrl){$bad=$settings;$bad['base_url']=$badUrl;$rejected=false;try{validateAppMailSettings($bad);}catch(InvalidArgumentException $e){$rejected=true;}ensureMail($rejected,'Unsafe website URL rejected');}
    $bad=$settings;$bad['app_password']='normalpassword';$rejected=false;try{validateAppMailSettings($bad);}catch(InvalidArgumentException $e){$rejected=true;}ensureMail($rejected,'Invalid app password format rejected');
    $context=stream_context_create(['http'=>['ignore_errors'=>true]]);file_get_contents('http://localhost/System/storage/mail-settings.json',false,$context);ensureMail(str_contains($http_response_header[0],'403'),'Web access to stored mail credentials blocked');
    echo "Mail configuration checks passed. No email was sent.\n";
} finally {if(is_file($temp))unlink($temp);}
