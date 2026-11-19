<?php
const APP_MAIL_SETTINGS_FILE=__DIR__.'/storage/mail-settings.json';
function loadAppMailSettings(?string $path=null): array {
    $defaults=['gmail_address'=>'','app_password'=>'','sender_name'=>'Barangay Mudiang','base_url'=>'http://localhost/System'];
    $path??=APP_MAIL_SETTINGS_FILE;
    if(!is_file($path))return $defaults;
    $contents=file_get_contents($path);$settings=json_decode($contents,true);
    if(!is_array($settings))throw new RuntimeException('Email settings could not be read.');
    foreach($defaults as $key=>$default)if(!is_string($settings[$key]??null))$settings[$key]=$default;
    return array_intersect_key($settings,$defaults)+$defaults;
}
function appMailBaseUrl(): string {
    $environment=getenv('APP_BASE_URL');
    return rtrim($environment ?: loadAppMailSettings()['base_url'],'/');
}
function validateAppMailSettings(array $settings): array {
    $address=strtolower(trim($settings['gmail_address']??''));
    $password=preg_replace('/\s+/','',$settings['app_password']??'');
    $sender=trim($settings['sender_name']??'');$base=rtrim(trim($settings['base_url']??''),'/');
    if(!filter_var($address,FILTER_VALIDATE_EMAIL)||strlen($address)>100)throw new InvalidArgumentException('Enter the Gmail or Google Workspace address used to send emails.');
    if(!preg_match('/^[a-zA-Z0-9]{16}$/D',$password))throw new InvalidArgumentException('Enter the 16-character Google app password. Spaces are removed automatically.');
    if($sender===''||mb_strlen($sender)>100||preg_match('/[\r\n]/',$sender))throw new InvalidArgumentException('Enter a sender name of up to 100 characters.');
    $url=parse_url($base);
    if(!$url||!filter_var($base,FILTER_VALIDATE_URL)||isset($url['user'])||isset($url['pass'])||isset($url['query'])||isset($url['fragment'])||!in_array($url['scheme']??'',['http','https'],true))throw new InvalidArgumentException('Enter the full application URL without a query, fragment, or credentials.');
    if($url['scheme']==='http'&&!in_array($url['host'],['localhost','127.0.0.1','[::1]'],true))throw new InvalidArgumentException('Use HTTPS for a deployed website. HTTP is allowed only on localhost.');
    return ['gmail_address'=>$address,'app_password'=>$password,'sender_name'=>$sender,'base_url'=>$base];
}
function saveAppMailSettings(array $settings,?string $path=null): void {
    $settings=validateAppMailSettings($settings);$path??=APP_MAIL_SETTINGS_FILE;
    $temporary=$path.'.'.bin2hex(random_bytes(6)).'.tmp';
    if(file_put_contents($temporary,json_encode($settings,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX)===false)throw new RuntimeException('Email settings storage is unavailable.');
    @chmod($temporary,0600);
    if(!rename($temporary,$path)){@unlink($temporary);throw new RuntimeException('Email settings could not be saved.');}
}
function configuredGmailMailer(array $settings): \PHPMailer\PHPMailer\PHPMailer {
    require_once __DIR__.'/vendor/PHPMailer/src/Exception.php';
    require_once __DIR__.'/vendor/PHPMailer/src/PHPMailer.php';
    require_once __DIR__.'/vendor/PHPMailer/src/SMTP.php';
    $mailer=new \PHPMailer\PHPMailer\PHPMailer(true);
    $mailer->isSMTP();$mailer->Host='smtp.gmail.com';$mailer->Port=587;
    $mailer->SMTPAuth=true;$mailer->Username=$settings['gmail_address'];$mailer->Password=$settings['app_password'];
    $mailer->SMTPSecure=\PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mailer->SMTPDebug=0;$mailer->Timeout=15;$mailer->Timelimit=30;
    $mailer->CharSet='UTF-8';$mailer->setFrom($settings['gmail_address'],$settings['sender_name']);
    return $mailer;
}
