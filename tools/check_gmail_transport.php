<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only.');}
require __DIR__.'/../vendor/PHPMailer/src/SMTP.php';
$smtp=new PHPMailer\PHPMailer\SMTP();
try {
    if(!$smtp->connect('smtp.gmail.com',587,10))throw new RuntimeException('Cannot connect to Gmail SMTP port 587.');
    if(!$smtp->hello('localhost'))throw new RuntimeException('Gmail SMTP handshake failed.');
    if(!$smtp->startTLS())throw new RuntimeException('Verified TLS negotiation failed. Check the PHP CA bundle.');
    if(!$smtp->hello('localhost'))throw new RuntimeException('Encrypted SMTP handshake failed.');
    echo "Gmail SMTP is reachable and verified STARTTLS is working. No authentication or email sending was attempted.\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
finally{$smtp->quit();$smtp->close();}
