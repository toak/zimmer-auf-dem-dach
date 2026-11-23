<?php
// Store the balance before the day's recorded receipts so later receipts on
// the same date still increase the total. Older snapshots were overwritten.
function prepareHistory(): void {
    try {
        query('SELECT day FROM balance_history LIMIT 1');
    } catch (PDOException $error) {
        if (!in_array($error->getCode(), ['42S02','HY000'], true)) throw $error;
        query('CREATE TABLE IF NOT EXISTS balance_history (day DATE PRIMARY KEY, base_cents BIGINT NOT NULL, legacy_start INT NOT NULL)');
    }
    if (query('SELECT day FROM balance_history LIMIT 1')->fetchColumn()) return;
    transaction(function () {
        $state = query('SELECT cents,last_snapshot FROM campaign WHERE id=1')->fetch();
        $day = $state['last_snapshot'];
        if (!$day || query('SELECT day FROM balance_history LIMIT 1')->fetchColumn()) return;
        $receipts = query('SELECT COALESCE(SUM(cents),0) FROM pledges WHERE received_at>=?', [strtotime($day)])->fetchColumn();
        query('INSERT INTO balance_history VALUES (?,?,1)', [$day, $state['cents'] - $receipts]);
    });
}
function recordSnapshot(string $day, int $cents): void {
    $start = new DateTimeImmutable($day);
    $receipts = query('SELECT COALESCE(SUM(cents),0) FROM pledges WHERE received_at>=? AND received_at<?',
        [$start->getTimestamp(), $start->modify('+1 day')->getTimestamp()])->fetchColumn();
    $legacy = (int)query('SELECT legacy_start FROM balance_history WHERE day=?', [$day])->fetchColumn();
    query('DELETE FROM balance_history WHERE day=?', [$day]);
    query('INSERT INTO balance_history VALUES (?,?,?)', [$day, $cents - $receipts, $legacy]);
}
function dailyBalances(): array {
    $days = [];
    $firstKnown = '';
    foreach (query('SELECT day,base_cents,legacy_start FROM balance_history') as $row) {
        if ($row['legacy_start']) $firstKnown = $row['day'];
        $days[$row['day']]['base'] = (int)$row['base_cents'];
    }
    foreach (query('SELECT received_at,cents FROM pledges WHERE received_at IS NOT NULL') as $row) {
        $day = date('Y-m-d', (int)$row['received_at']);
        if ($day < $firstKnown) continue;
        $days[$day]['receipts'] = ($days[$day]['receipts'] ?? 0) + $row['cents'];
    }
    ksort($days);
    $points = [];
    $balance = 0;
    foreach ($days as $day => $values) {
        $balance = ($values['base'] ?? $balance) + ($values['receipts'] ?? 0);
        $points[$day] = $balance;
    }
    return $points;
}
function showHistory(array $points): void {
    if (!$points) {
        echo '<p>Sobald du den ersten Kontostand oder Zahlungseingang erfasst, siehst du hier den Verlauf.</p>';
        return;
    }
    $first = array_key_first($points);
    $last = array_key_last($points);
    $span = max(1, strtotime($last) - strtotime($first));
    $max = max(100, max($points));
    $path = '';
    $dots = [];
    foreach ($points as $day => $balance) {
        $x = round(2 + 96 * (strtotime($day) - strtotime($first)) / $span, 3);
        $y = round(96 - 92 * $balance / $max, 3);
        $path .= $path === '' ? "M$x $y" : " H$x V$y";
        $dots[] = [$x,$y,dateAT($day).' · '.eur($balance)];
    }
    ?>
    <p>Stand am <?=dateAT($last)?> <strong><?=eur($points[$last])?></strong></p>
    <div class="history-chart">
      <div class="history-scale"><span><?=eur($max)?></span><span><?=eur((int)round($max/2))?></span><span>0 €</span></div>
      <svg width="100%" height="230" role="img" aria-label="Eingegangene Spenden im Zeitverlauf. Tageswerte stehen unter dem Diagramm.">
        <svg width="100%" height="100%" viewBox="0 0 100 100" preserveAspectRatio="none">
          <path d="M0 4 H100 M0 50 H100 M0 96 H100" fill="none" stroke="#d7dfe8" vector-effect="non-scaling-stroke"/>
          <path d="<?=e($path)?>" fill="none" stroke="#183e70" stroke-width="3" vector-effect="non-scaling-stroke"/>
        </svg>
        <?php foreach ($dots as [$x,$y,$label]): ?><circle cx="<?=$x?>%" cy="<?=$y?>%" r="3" fill="#183e70"><title><?=e($label)?></title></circle><?php endforeach ?>
      </svg>
      <div class="history-dates"><span><?=dateAT($first)?></span><?php if ($first !== $last): ?><span><?=dateAT($last)?></span><?php endif ?></div>
    </div>
    <details><summary>Tageswerte anzeigen</summary><table><thead><tr><th>Datum</th><th>Spendenstand</th></tr></thead><tbody>
    <?php foreach (array_reverse($points,true) as $day => $balance): ?><tr><td><?=dateAT($day)?></td><td><?=eur($balance)?></td></tr><?php endforeach ?>
    </tbody></table></details>
    <?php
}
