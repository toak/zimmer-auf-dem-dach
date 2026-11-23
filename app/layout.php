<?php
function beginPage(string $title, string $error='', bool $embedded=false): void { ?>
<!doctype html><html lang="de-AT"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($title)?> · Adventistische Privatschule Klagenfurt</title><link rel="stylesheet" href="/assets/site.css"><script src="/assets/site.js" defer></script></head><body>
<?php if (!$embedded): ?><div class="school-strip"><img src="<?=SCHOOL?>/images/logo_top_abteilungen.png" alt=""><details><summary>Standorte</summary><div><?php schoolLocations(); ?></div></details><div class="locations"><?php schoolLocations(); ?></div></div>
<header class="mast"><a class="school-mark" href="<?=SCHOOL?>/"><img src="<?=SCHOOL?>/images/logo.jpg" alt="Adventistische Privatschule Klagenfurt"></a>
<div class="tools"><a href="<?=SCHOOL?>/sitemap/">Sitemap</a><a href="<?=SCHOOL?>/kontakt/">Kontakt</a>
<form method="post" action="<?=SCHOOL?>/suche/"><label>Suche <input type="search" name="v_suchbegriff"></label></form>
<span class="sizes" aria-label="Schriftgröße"><?php foreach(['normal','groß','größer'] as $i=>$size): ?><button type="button" aria-label="Schriftgröße <?=e($size)?>" data-size="<?=$i?>">A</button><?php endforeach ?></span></div>
<nav class="school-menu" aria-label="Schulwebsite"><?php schoolMenu(); ?></nav>
<details class="mobile-menu"><summary>Menu</summary><div><?php schoolMenu(); ?><a href="<?=SCHOOL?>/kontakt/">Kontakt</a><a href="<?=SCHOOL?>/suche/">Suche</a></div></details></header><?php endif ?>
<main><?php if ($error): ?><p class="notice error" role="alert"><?=e($error)?></p><?php endif;
if (isset($_SESSION['notice'])): ?><p class="notice" role="status"><?=e($_SESSION['notice'])?></p><?php unset($_SESSION['notice']); endif;
}
function schoolMenu(): void {
    foreach(['Schulkonzept'=>'schulkonzept','Blog'=>'blog','Volks-/Mittelschule'=>'volkschule_nms','GTS'=>'gts','Unterstütze uns'=>'unterstuetze-uns'] as $label=>$slug)
        echo '<a href="'.SCHOOL.'/'.$slug.'/">'.e($label).'</a>';
}
function schoolLocations(): void {
    foreach(['Bogenhofen'=>'www.privatschule-bogenhofen.at','Fürstenfeld'=>'www.privatschule-fuerstenfeld.at','Klagenfurt'=>null,'Linz'=>'www.privatschule-linz.at','Voitsberg'=>'www.privatschule-voitsberg.at','Wien'=>'www.psan.at','Wr. Neustadt'=>'www.lernwelt-wrneustadt.at'] as $label=>$host)
        echo $host ? '<a href="https://'.$host.'/">'.e($label).'</a>' : '<span>Klagenfurt</span>';
}
function endPage(bool $embedded=false): void { ?></main><?php if (!$embedded): ?><footer>© Adventistische Privatschule Klagenfurt | Ebentaler Straße 22 | A-9020 Klagenfurt | <a href="<?=SCHOOL?>/impressum/">Impressum</a> | <a href="<?=SCHOOL?>/datenschutz/">Datenschutz / Cookies</a> | <a href="/admin/">Verwaltung</a></footer><?php endif ?></body></html><?php }
