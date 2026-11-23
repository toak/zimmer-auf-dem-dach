<?php
declare(strict_types=1);
date_default_timezone_set('Europe/Vienna');
ini_set('display_errors', '0');
set_exception_handler(static function (Throwable $e): void {
    if (isset($GLOBALS['db']) && $GLOBALS['db']->inTransaction()) $GLOBALS['db']->rollBack();
    error_log('GTS: '.get_class($e).' in '.basename($e->getFile()).':'.$e->getLine());
    http_response_code(500);
    exit('Der Dienst ist vorübergehend nicht verfügbar. Bitte versuch es später noch einmal.');
});
$config = require dirname(__DIR__).'/env.php';
$db = new PDO($config['dsn'], $config['user'], $config['password'], [
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES=>false,
]);
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; img-src 'self' https://www.privatschule-klagenfurt.at; style-src 'self'; font-src 'self' https://www.privatschule-klagenfurt.at; script-src 'self'; form-action 'self' https://www.privatschule-klagenfurt.at; frame-ancestors 'none'; base-uri 'none'");
ini_set('session.use_strict_mode','1');
session_set_cookie_params(['secure'=>true,'httponly'=>true,'samesite'=>'Lax','path'=>'/']);
session_start();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
class FormError extends RuntimeException {}
function query(string $sql, array $args=[]): PDOStatement {
    global $db;
    $stmt=$db->prepare($sql); $stmt->execute($args); return $stmt;
}
function e(mixed $s): string { return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function eur(int $c): string { return number_format($c/100,$c%100 ? 2 : 0,',','.').' €'; }
function field(string $key): string { $v=$_POST[$key]??''; return is_string($v)?trim($v):''; }
function amount(bool $zero=false): int {
    $v=field('amount');
    if (!preg_match('/^([0-9]{1,9})(?:[.,]([0-9]{1,2}))?$/D',$v,$m)) throw new FormError('Bitte einen Eurobetrag mit höchstens zwei Nachkommastellen eingeben.');
    $c=(int)$m[1]*100+(int)str_pad($m[2]??'',2,'0');
    if (!$zero && !$c) throw new FormError('Bitte einen Betrag größer als null eingeben.');
    return $c;
}
function csrf(): string { return '<input type="hidden" name="csrf" value="'.e($_SESSION['csrf']).'">'; }
function guard(): void {
    if (!hash_equals($_SESSION['csrf'],field('csrf'))) throw new FormError('Das Formular ist abgelaufen. Bitte erneut versuchen.');
}
function redirect(string $path,string $msg=''): never {
    if ($msg!=='') $_SESSION['notice']=$msg;
    header('Location: '.$path,true,303); exit;
}
// Serialize short write transactions, including concurrent receipt and balance updates.
function transaction(callable $work): mixed {
    global $db;
    $db->beginTransaction();
    try {
        query('UPDATE campaign SET version_no=version_no WHERE id=1');
        $result=$work(); $db->commit(); return $result;
    } catch(Throwable $e) { $db->rollBack(); throw $e; }
}
function limitRequests(string $kind,int $max, ?string $subject=null): void {
    global $config;
    $now=time();
    $key=hash_hmac('sha256',$kind.'|'.($subject??($_SERVER['REMOTE_ADDR']??'')),$config['rate_secret']);
    transaction(function() use($key,$now,$max) {
        query('DELETE FROM request_limits WHERE starts_at <= ?',[$now-3600]);
        $hits=query('SELECT hits FROM request_limits WHERE bucket=?',[$key])->fetchColumn();
        if ($hits===false) query('INSERT INTO request_limits VALUES (?,?,1)',[$key,$now]);
        elseif ((int)$hits >= $max) throw new FormError('Zu viele Versuche. Bitte später erneut versuchen.');
        else query('UPDATE request_limits SET hits=hits+1 WHERE bucket=?',[$key]);
    });
}
function validEmail(string $email): bool { return strlen($email)<=254 && (bool)filter_var($email,FILTER_VALIDATE_EMAIL); }
function currentAdmin(): ?array {
    if (time() >= ($_SESSION['until']??0)) return null;
    return query('SELECT email,identity_key FROM admins WHERE identity_key=?',[$_SESSION['admin']??''])->fetch() ?: null;
}
function dateAT(string $day): string {return implode('.',array_reverse(explode('-',$day)));}
const SCHOOL='https://www.privatschule-klagenfurt.at';
