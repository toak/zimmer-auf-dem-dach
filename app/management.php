<?php
function loginCode(string $tokenHash): string {
    global $config;
    $digest=hash_hmac('sha256','admin-otp:'.$tokenHash,$config['rate_secret']);
    return sprintf('%06d',hexdec(substr($digest,0,8)) % 1000000);
}
function requestLink(): void {
    global $config;
    limitRequests('login',20);
    $email=strtolower(field('email'));
    $token=bin2hex(random_bytes(32));
    $_SESSION['login_email']=$email;
    $now=time();
    $updated=query('UPDATE admins SET token_hash=?,expires_at=?,issued_at=? WHERE email=? AND (issued_at IS NULL OR issued_at<=?)',
        [hash('sha256',$token),$now+900,$now,$email,$now-90])->rowCount();
    if ($updated) {
        $url=rtrim($config['url'],'/').'/admin/?token='.$token;
        $code=loginCode(hash('sha256',$token));
        $body="Dein Einmalcode: ".$code."\n\nGib den Code unter Verwaltung ein oder öffne den folgenden Link. Beide gelten 15 Minuten und können zusammen nur einmal verwendet werden:\n\n".$url;
        $sent=mail($email,'Dein Einmalcode und Anmeldelink | Zimmer auf dem Dach',$body,[
            'From'=>$config['sender'],'Content-Type'=>'text/plain; charset=UTF-8',
        ]);
        if (!$sent) error_log('GTS: Anmeldemail konnte nicht an den Maildienst übergeben werden.');
    }
    redirect('/admin/','Wenn deine Adresse freigeschaltet ist, bekommst du einen Einmalcode und einen Anmeldelink. Schau bitte auch in deinen Spamordner.');
}
function redeemCode(): void {
    $email=strtolower(field('email'));
    $code=field('code');
    if (!validEmail($email) || !preg_match('/^[0-9]{6}$/D',$code)) throw new FormError('Bitte gib deine E-Mail-Adresse und den sechsstelligen Code ein.');
    limitRequests('otp-ip',20);
    limitRequests('otp-account',8,$email);
    $row=query('SELECT token_hash FROM admins WHERE email=? AND expires_at>?',[$email,time()])->fetch();
    if (!$row || !$row['token_hash'] || !hash_equals(loginCode($row['token_hash']),$code))
        throw new FormError('Der Code ist ungültig oder abgelaufen.');
    redeemLink($row['token_hash']);
}
function redeemLink(?string $codeHash=null): void {
    $hash=$codeHash??$_SESSION['pending']??'';
    $admin=transaction(function() use($hash) {
        $key=query('SELECT identity_key FROM admins WHERE token_hash=? AND expires_at>?',[$hash,time()])->fetchColumn();
        if (!$key) throw new FormError('Dieser Anmeldelink ist abgelaufen oder bereits verwendet. Bitte einen neuen Link anfordern.');
        query('UPDATE admins SET token_hash=NULL, expires_at=NULL WHERE identity_key=?',[$key]);
        return $key;
    });
    session_regenerate_id(true);
    $_SESSION=['admin'=>$admin,'until'=>time()+8*3600,'csrf'=>bin2hex(random_bytes(32))];
    redirect('/admin/');
}
function changeBalance(): string {
    $c=amount(true); $date=field('date');
    $valid=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
    if (!$valid || $valid->format('Y-m-d')!==$date || $date>date('Y-m-d')) throw new FormError('Bitte einen gültigen Stichtag bis einschließlich heute wählen.');
    $state=query('SELECT * FROM campaign WHERE id=1')->fetch();
    if ((string)$state['version_no']!==field('version')) throw new FormError('Der Stand wurde inzwischen geändert. Bitte den neuen Stand prüfen.');
    if ($state['last_snapshot'] && $date<$state['last_snapshot']) throw new FormError('Der Stichtag liegt vor dem zuletzt erfassten Kontoauszug.');
    $later=query('SELECT COALESCE(SUM(cents),0) AS total,MAX(received_at) AS latest FROM pledges WHERE received_at>=?',[$valid->modify('+1 day')->getTimestamp()])->fetch();
    $asOf=$later['latest'] ? date('Y-m-d',(int)$later['latest']) : $date;
    query('UPDATE campaign SET cents=?,as_of=?,last_snapshot=?,version_no=version_no+1 WHERE id=1',[$c+(int)$later['total'],$asOf,$date]);
    return 'Der Gesamtstand wurde aktualisiert.';
}
function handleAdmin(string $action): string {
    $admin=currentAdmin();
    if (!$admin) throw new FormError('Bitte melde dich erneut an.');
    if ($action==='balance') return changeBalance();
    if ($action==='add' || $action==='revoke') {
        $email=strtolower(field('email'));
        if (!validEmail($email)) throw new FormError('Bitte eine gültige E-Mail-Adresse eingeben.');
        if ($action==='add') {
            if (query('SELECT email FROM admins WHERE email=?',[$email])->fetchColumn()) return 'Diese Adresse ist bereits freigeschaltet.';
            query('INSERT INTO admins(email,identity_key) VALUES (?,?)',[$email,bin2hex(random_bytes(32))]);
            return 'Der Zugang ist freigeschaltet.';
        }
        if ($email===$admin['email']) throw new FormError('Der eigene Zugang muss aktiv bleiben.');
        query('DELETE FROM admins WHERE email=?',[$email]);
        return 'Der Zugang wurde entzogen.';
    }
    $id=field('id');
    $row=query('SELECT cents,received_at FROM pledges WHERE id=?',[$id])->fetch();
    if (!$row) throw new FormError('Zusage nicht gefunden.');
    if ($row['received_at']!==null) return 'Diese Zusage ist bereits verbucht.';
    if ($action==='remind') {
        query('UPDATE pledges SET reminded_at=? WHERE id=?',[time(),$id]);
        return 'Manuelle Erinnerung vermerkt.';
    }
    if ($action!=='receive') throw new FormError('Unbekannte Aktion.');
    query('UPDATE pledges SET received_at=? WHERE id=?',[time(),$id]);
    query('UPDATE campaign SET cents=cents+?,as_of=?,version_no=version_no+1 WHERE id=1',[$row['cents'],date('Y-m-d')]);
    return 'Zahlungseingang verbucht. Der Spendenstand wurde erhöht.';
}
