<?php
require dirname(__DIR__,2).'/app/common.php';
require dirname(__DIR__,2).'/app/management.php';
require dirname(__DIR__,2).'/app/layout.php';
require dirname(__DIR__,2).'/app/history.php';
if (currentAdmin()) prepareHistory();
$error='';
if (isset($_GET['token']) && is_string($_GET['token']) && preg_match('/^[a-f0-9]{64}$/D',$_GET['token'])) {
    $_SESSION['pending']=hash('sha256',$_GET['token']); redirect('/admin/');
}
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        guard(); $action=field('action');
        if ($action==='request') requestLink();
        if ($action==='login') redeemLink();
        if ($action==='code') redeemCode();
        if ($action==='logout') { $_SESSION=[]; session_destroy(); redirect('/admin/'); }
        $target=match($action){'receive','remind','delete_pledge'=>'zusagen','add','revoke'=>'zugriff',default=>'spenden'};
        redirect('/admin/#'.$target,transaction(fn()=>handleAdmin($action)));
    } catch(FormError $ex) { $error=$ex->getMessage(); if (field('action')==='login') unset($_SESSION['pending']); }
}
beginPage('Verwaltung',$error);
function formFields(string $action): string {
    return csrf().'<input type="hidden" name="action" value="'.e($action).'">';
}
$admin=currentAdmin();
if (!$admin): ?>
<h1>Verwaltung</h1>
<?php if(isset($_SESSION['pending'])): ?><article><h2>Anmeldung bestätigen</h2><form method="post"><?=formFields('login')?><button>Jetzt anmelden</button></form></article>
<?php else: ?><article><h2>Mit E-Mail anmelden</h2><p>Gib deine freigeschaltete Adresse ein. Du erhältst einen sechsstelligen Code und einen Anmeldelink. Wähle einen der beiden Wege.</p><form method="post"><?=formFields('request')?><label>E-Mail-Adresse<input type="email" name="email" autocomplete="email" required value="<?=e($_SESSION['login_email']??'')?>"></label><button>Code und Link anfordern</button></form></article>
<p><a id="open-code" href="?einmalcode=1">Einmalcode eingeben</a></p>
<dialog id="login-code" aria-labelledby="code-title" <?=isset($_SESSION['login_email']) || isset($_GET['einmalcode']) || field('action')==='code'?'open':''?>>
<h2 id="code-title">Einmalcode eingeben</h2>
<?php if ($error && field('action')==='code'): ?><p class="notice error" role="alert"><?=e($error)?></p><?php endif ?>
<form method="post" action="/admin/"><?=formFields('code')?>
<label>E-Mail-Adresse<input type="email" name="email" autocomplete="email" required value="<?=e(field('action')==='code'?field('email'):($_SESSION['login_email']??''))?>"></label>
<p id="code-help">Gib den sechsstelligen Code aus deiner E-Mail ein.</p>
<div id="code-fields"><input aria-label="Sechsstelliger Code" aria-describedby="code-help" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required></div>
<div class="actions"><button>Anmelden</button><button id="close-code" class="secondary" type="button" hidden>Schließen</button></div>
</form></dialog><script src="/assets/admin.js" defer></script><?php endif ?>
<?php else:
$state=query('SELECT * FROM campaign WHERE id=1')->fetch(); ?>
<div class="admin-heading">
  <h1>Verwaltung</h1>
  <div class="actions admin-heading-actions">
    <a class="button" href="/">Zur Spendenseite</a>
    <form method="post"><?=formFields('logout')?><button class="secondary" type="submit">Abmelden</button></form>
  </div>
</div>
<div class="tabs" aria-label="Bereiche der Verwaltung"><a href="#spenden">Spenden</a><a href="#verlauf">Verlauf</a><a href="#zusagen">Zusagen</a><a href="#zugriff">Admin Zugriff</a></div>
<script src="/assets/admin.js" defer></script><article id="spenden"><h2>Spendenstand</h2><p class="stat"><?=$state['as_of']?eur((int)$state['cents']).' · '.dateAT($state['as_of']):'Erster Kontostand ausständig'?></p>
<form method="post"><?=formFields('balance')?><input type="hidden" name="version" value="<?=e($state['version_no'])?>">
<label>Spendenstand für das Zimmer auf dem Dach<input name="amount" required inputmode="decimal"></label><label>Stichtag<input type="date" name="date" value="<?=date('Y-m-d')?>" max="<?=date('Y-m-d')?>" required></label><p><small>Der Betrag enthält alle bis zum Stichtag verbuchten Spenden. Bereits erfasste Eingänge nach dem Stichtag zählt die Seite dazu.</small></p><button>Gesamtstand übernehmen</button></form></article>
<article id="verlauf"><h2>Spendenverlauf</h2><?php showHistory(dailyBalances()); ?></article>
<article id="zusagen"><h2>Spendenzusagen</h2><p>„Erinnert“ dokumentiert deine persönliche Kontaktaufnahme. „Eingegangen“ verbucht den zugesagten Betrag zum heutigen Datum.</p>
<div class="filters"><label>Suche<input id="term" type="search" placeholder="Name oder Kontakt"></label><label>Status<select id="status"><option value="">Alle Zusagen</option><option>Offen</option><option>Wartet auf Absetzbarkeit</option><option>Abgelaufen</option><option>Eingegangen</option></select></label><label>Erinnerung<select id="reminder"><option value="">Alle</option><option value="0">Noch nicht erinnert</option><option value="1">Bereits erinnert</option></select></label></div>
<p id="matches" aria-live="polite"></p>
<table class="pledges"><thead><tr><th>Person</th><th>Betrag</th><th>Status</th><th>Zusage erhalten</th><th>Letzte Erinnerung</th><th>Zahlung eingegangen</th><th>Aktionen</th></tr></thead><tbody id="records">
<?php $count=0; foreach(query("SELECT * FROM pledges WHERE donor <> '' ORDER BY CASE WHEN received_at IS NULL THEN 0 ELSE 1 END, created_at DESC,id") as $p): $count++; $received=$p['received_at']!==null; ?>
<?php
$status=$received?'Eingegangen':($p['wait_tax']?'Wartet auf Absetzbarkeit':(((int)$p['created_at']+28*86400<=time())?'Abgelaufen':'Offen'));
$stamp=static fn($ts)=>$ts?date('d.m.Y · H:i',(int)$ts):'—';
?>
<tr data-status="<?=e($status)?>" data-reminded="<?=$p['reminded_at']?'1':'0'?>">
<td data-label="Person"><strong><?=e($p['donor'])?></strong><small><?=e($p['contact'])?></small></td>
<td data-label="Betrag"><?=eur((int)$p['cents'])?></td><td data-label="Status"><span class="badge"><?=e($status)?></span>
<?php if(!$received): ?><small><?=$p['wait_tax']?'Ohne Frist':'Bis '.date('d.m.Y',(int)$p['created_at']+28*86400)?></small><?php endif ?></td>
<td data-label="Zusage erhalten"><?=$stamp($p['created_at'])?></td>
<td data-label="Letzte Erinnerung"><?=$stamp($p['reminded_at'])?></td>
<td data-label="Zahlung eingegangen"><?=$stamp($p['received_at'])?></td><td data-label="Aktionen">
<?php if(!$received): ?><form method="post" action="/admin/#zusagen"><?=csrf()?><input type="hidden" name="id" value="<?=e($p['id'])?>"><button class="secondary" name="action" value="remind">Erinnert</button><button name="action" value="receive">Eingegangen</button></form><?php endif ?>
<details class="delete-pledge">
  <summary aria-label="Zusage von <?=e($p['donor'])?> löschen" title="Zusage löschen"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7m4-7v7"/></svg></summary>
  <p>Zusage von <?=e($p['donor'])?> über <?=eur((int)$p['cents'])?> löschen?<?php if ($received): ?> Der Zahlungseingang bleibt verbucht.<?php endif ?></p>
  <form method="post" action="/admin/#zusagen"><?=formFields('delete_pledge')?><input type="hidden" name="id" value="<?=e($p['id'])?>"><button>Endgültig löschen</button></form>
</details></td></tr>
<?php endforeach ?></tbody></table></article>
<article id="zugriff"><h2>Benutzerverwaltung</h2><?php foreach(query('SELECT email FROM admins ORDER BY email') as $u): ?><div class="row"><?=e($u['email'])?>
<?php if ($u['email']!==$admin['email']): ?><form method="post"><?=formFields('revoke')?><input type="hidden" name="email" value="<?=e($u['email'])?>"><button class="secondary">Zugang entziehen</button></form><?php else: ?><small>Eigener Zugang</small><?php endif ?></div><?php endforeach ?>
<form method="post"><?=formFields('add')?><label>Weitere E-Mail-Adresse<input type="email" name="email" required></label><button>Freischalten</button></form></article>
<?php endif; endPage(); ?>
