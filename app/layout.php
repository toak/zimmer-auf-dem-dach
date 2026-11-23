<?php
function beginPage(string $title, string $error=''): void { ?>
<!doctype html><html lang="de-AT"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($title)?> · Adventistische Privatschule Klagenfurt</title><link rel="stylesheet" href="/assets/site.css"><script src="/assets/site.js" defer></script></head><body>
<header><a href="<?=SCHOOL?>/"><img src="<?=SCHOOL?>/images/logo.jpg" alt="Adventistische Privatschule Klagenfurt"></a></header>
<nav aria-label="Schulwebsite"><?php foreach(['Home'=>'/','Schulkonzept'=>'/schulkonzept/','Volks-/Mittelschule'=>'/volkschule_nms/','GTS'=>'/gts/','Blog'=>'/blog/','Kontakt'=>'/kontakt/'] as $name=>$path): ?><a href="<?=SCHOOL.$path?>"><?=e($name)?></a><?php endforeach ?></nav>
<main><?php if ($error): ?><p class="notice error" role="alert"><?=e($error)?></p><?php endif;
if (isset($_SESSION['notice'])): ?><p class="notice" role="status"><?=e($_SESSION['notice'])?></p><?php unset($_SESSION['notice']); endif;
}
function endPage(): void { ?></main><footer>Adventistische Privatschule Klagenfurt · Ebentaler Straße 22
<a href="<?=SCHOOL?>/kontakt/">Kontakt</a><a href="<?=SCHOOL?>/unterstuetze-uns/">Unterstütze uns</a><a href="<?=SCHOOL?>/impressum/">Impressum</a><a href="<?=SCHOOL?>/datenschutz/">Datenschutz</a><a href="/admin/">Verwaltung</a></footer></body></html><?php }
