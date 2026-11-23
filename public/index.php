<?php
require dirname(__DIR__).'/app/common.php';
require dirname(__DIR__).'/app/layout.php';
$error='';
$_SESSION['offer'] ??= bin2hex(random_bytes(16));
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        guard();
        if (!hash_equals($_SESSION['offer'],field('offer'))) redirect('/');
        $cents=amount(); $name=field('person'); $contact=field('contact');
        if ($name==='' || mb_strlen($name)>120) throw new FormError('Bitte gib deinen Namen ein.');
        if (!validEmail($contact) && !(preg_match('/^\+?[\d\s().\/-]{5,35}$/D',$contact) && strlen(preg_replace('/\D/','',$contact))>=6))
            throw new FormError('Bitte gib deine E-Mail-Adresse oder Telefonnummer ein.');
        if (field('website')!=='') throw new FormError('Die Zusage konnte nicht angenommen werden.');
        limitRequests('offer',20);
        query('INSERT INTO pledges (id, donor, contact, cents, created_at, wait_tax) VALUES (?,?,?,?,?,?)',[$_SESSION['offer'],$name,$contact,$cents,time(),field('wait')==='1'?1:0]);
        unset($_SESSION['offer']);
        redirect('/','Herzlichen Dank! Deine Spendenzusage wurde aufgenommen.');
    } catch(FormError $ex) { $error=$ex->getMessage(); }
}
$goal = 4000000; // Cent
$stand = query('SELECT cents, as_of, (SELECT COALESCE(SUM(cents),0) FROM pledges
    WHERE received_at IS NULL AND (wait_tax=1 OR created_at>?)) AS pledged
    FROM campaign WHERE id=1', [time()-28*86400])->fetch();
$received = (int)$stand['cents'];
$sum = (int)$stand['pledged'];
$receivedWidth = min($goal, $received);
$pledgedWidth = min($sum, $goal - $receivedWidth);
beginPage('Zimmer auf dem Dach',$error);
?>
<section class="hero"><div><small>Ganztagsschule Klagenfurt</small><h1>Zimmer auf dem Dach</h1><p>Auf dem Dach unserer Schule haben die Kinder rund 43 m² mehr Platz zum Lernen, Spielen und Zusammensein. Hilf uns, die Finanzierung dieses Raums abzuschließen.</p></div><img src="<?=SCHOOL?>/media_blog/images/5588_index_square_1787769950_s500.jpg" alt="Holzbau für das neue Dachzimmer während der Bauphase"></section>
<article><small>Unser Spendenziel · <?=eur($goal)?></small>
<h2><?=eur($received + $sum)?> inklusive Zusagen</h2>
<svg width="100%" height="18" viewBox="0 0 <?=$goal?> 18" preserveAspectRatio="none" role="img" aria-label="<?=eur($received)?> eingegangen und <?=eur($sum)?> zugesagt bei einem Ziel von <?=eur($goal)?>">
  <rect width="<?=$goal?>" height="18" fill="#e9edf1"/>
  <rect width="<?=$receivedWidth?>" height="18" fill="#183e70"/>
  <rect x="<?=$receivedWidth?>" width="<?=$pledgedWidth?>" height="18" fill="#e7ab24"/>
</svg>
<p class="stat"><svg width="12" height="12" aria-hidden="true"><rect width="12" height="12" fill="#183e70"/></svg> <?=eur($received)?> eingegangen ·
<svg width="12" height="12" aria-hidden="true"><rect width="12" height="12" fill="#e7ab24"/></svg> <?=eur($sum)?> zugesagt</p>
<?php if ($stand['as_of']): ?><p><small>Kontostand vom <?=dateAT($stand['as_of'])?></small></p><?php endif ?>
<div class="actions"><a id="open-pledge" class="button" href="/?zusage=1#pledge">Spende zusagen</a><a class="button secondary" href="#konto">Jetzt überweisen</a></div></article>
<dialog id="pledge" aria-labelledby="formtitle" <?=($error || isset($_GET['zusage']))?'open':''?>><form method="post" action="/"><?=csrf()?><input type="hidden" name="offer" value="<?=e($_SESSION['offer'])?>"><h2 id="formtitle">Spende zusagen</h2>
<label>Betrag in Euro<input name="amount" inputmode="decimal" required value="<?=e(field('amount'))?>"></label>
<label>Name<input name="person" maxlength="120" autocomplete="name" required value="<?=e(field('person'))?>"></label>
<label>E-Mail-Adresse oder Telefonnummer<input name="contact" maxlength="254" required value="<?=e(field('contact'))?>"></label>
<label class="trap" aria-hidden="true">Website<input name="website" tabindex="-1" autocomplete="off"></label>
<label><input type="checkbox" name="wait" value="1" <?=field('wait')?'checked':''?>> Ich möchte mit meiner Spende warten, bis sie steuerlich absetzbar ist. Bitte gebt mir dann persönlich Bescheid.</label>
<p><small>Eine Zusage zählt 28 Tage. Wenn du auf die Spendenabsetzbarkeit wartest, bleibt sie ohne Frist offen. Die Schule verwendet deine Kontaktdaten für Rückfragen zu deiner Zusage. Öffentlich erscheint nur die Summe.</small></p>
<div class="actions"><button>Spende zusagen</button><button type="button" class="secondary" id="close-pledge">Schließen</button></div></form></dialog>
<section><h2>Vom Flachdach zum Zimmer - Blogartikel</h2><div class="reports">
<?php foreach ([
    ['Ein Zimmer auf dem Dach','Ein-Zimmer-auf-dem-Dach/5588','5588_index_square_1787769950_s500.jpg'],
    ['Die Vision nimmt Raum an!','Die-Vision-nimmt-Raum-an/5602','5602_index_square_1789024333_s500.jpg'],
    ['Teamwork – Der Weg eines Eimers','Teamwork-Der-Weg-eines-Eimers/5622','5622_index_square_1791404540_s500.jpg'],
] as [$title,$slug,$image]): ?>
<a class="report" href="<?=SCHOOL?>/blog/<?=e($slug)?>/"><img loading="lazy" src="<?=SCHOOL?>/media_blog/images/<?=e($image)?>" alt=""><h3><?=e($title)?></h3></a>
<?php endforeach ?></div></section>
<article id="konto"><img src="/assets/ueberweisung.png" alt="Spenden-QR-Code zum Scannen mit deiner Banking-App"><h2>Spendenkonto</h2><p>Scanne den QR-Code mit deiner Banking-App oder kopiere die IBAN. Den Betrag wählst du selbst.</p><p>Schulverein der Siebenten-Tags-Adventisten<br>IBAN: <strong>AT65 3219 5000 0021 5897</strong><br>BIC: RLNWATWWASP</p><p>Verwendungszweck: <strong>zimmer auf dem dach</strong></p><button class="secondary" id="copy-iban" hidden>IBAN kopieren</button></article>
<article id="teilen">
  <h2>Spendenaktion teilen</h2>
  <div class="share-layout">
    <div>
      <div class="actions">
        <a class="button" href="https://www.facebook.com/sharer/sharer.php?u=https%3A%2F%2Fapk.toak.de" target="_blank" rel="noopener noreferrer">Auf Facebook teilen</a>
        <a class="button secondary" href="https://www.instagram.com/" target="_blank" rel="noopener noreferrer">Instagram öffnen</a>
      </div>
      <p>Für Instagram kannst du den Link in einer Nachricht verschicken oder als Link-Sticker in deine Story einfügen.</p>
      <div class="actions"><a id="share-url" href="https://apk.toak.de">https://apk.toak.de</a>
        <button id="copy-share-url" class="secondary" type="button" hidden>Link kopieren</button><span id="share-status" role="status"></span>
      </div>
    </div>
    <div class="share-qr">
      <img src="/assets/spendenseite-qr.svg" width="160" height="160" alt="QR-Code zur Spendenseite">
      <p>QR-Code für deine Präsentation</p>
      <a href="/assets/spendenseite-qr.svg" download>SVG herunterladen</a> · <a href="/assets/spendenseite-qr.png" download>PNG herunterladen</a>
    </div>
  </div>
</article>
<?php endPage(); ?>
