<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$email=$argv[1]??'';
if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Usage: php bin/reset-password.php email@example.com');
$user=one('SELECT id FROM users WHERE email=?',[$email]);if(!$user)throw new RuntimeException('User not found');
echo 'New password (6–12 characters; input is visible in this terminal): ';
$password=rtrim((string)fgets(STDIN), "\r\n");validate_new_password($password);
query('UPDATE users SET password_hash=? WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$user['id']]);audit('reset_password','users',(int)$user['id']);
echo "Password updated.\n";
