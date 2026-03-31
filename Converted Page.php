<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseDriver;

function leFmtDate(?string $date): string
{
    if (empty($date)) {
        return '';
    }
    $d = DateTime::createFromFormat('Y-m-d', substr($date, 0, 10));
    return $d ? $d->format('m-d-Y') : '';
}

function leFmtDateLong(?string $date): string
{
    if (empty($date)) {
        return '—';
    }
    $d = DateTime::createFromFormat('Y-m-d', substr($date, 0, 10));
    return $d ? $d->format('F j, Y') : '—';
}

function lePad2(string $value): string
{
    return str_pad((string)(int)$value, 2, '0', STR_PAD_LEFT);
}

function leResolveLogo(string $stateAbrev, string $gName, string $gameId = ''): array
{
    $result = ['url' => '', 'exists' => false];
    if (!defined('JPATH_ROOT')) {
        return $result;
    }
    $base       = JPATH_ROOT . '/images/lottery-logos/';
    $candidates = [];
    if ($gameId !== '') {
        $candidates[] = strtolower($gameId) . '.png';
    }
    if ($gName !== '') {
        $slug         = preg_replace('/[^a-zA-Z0-9]+/', '-', strtolower($gName));
        $candidates[] = trim($slug, '-') . '.png';
    }
    if ($stateAbrev !== '') {
        $candidates[] = strtolower($stateAbrev) . '.png';
    }
    foreach ($candidates as $file) {
        if (is_file($base . $file)) {
            $result['url']    = Uri::root() . 'images/lottery-logos/' . $file;
            $result['exists'] = true;
            return $result;
        }
    }
    return $result;
}

function leInitRange(int $min, int $max): array
{
    $counts        = [];
    $lastSeenIndex = [];
    for ($i = $min; $i <= $max; $i++) {
        $counts[(string)$i]        = 0;
        $lastSeenIndex[(string)$i] = null;
    }
    return [$counts, $lastSeenIndex];
}

function leDrawingsAgoLabel(?int $idx, int $window): array
{
    if ($idx === null) {
        return [$window + 1, 'Not in last ' . $window . ' drws'];
    }
    if ($idx === 0) {
        return [1, 'In last drw'];
    }
    return [$idx + 1, ($idx + 1) . ' drws ago'];
}

function leBuildNaturalLabels(int $min, int $max): array
{
    $labels = [];
    for ($i = $min; $i <= $max; $i++) {
        $labels[] = lePad2((string)$i);
    }
    return $labels;
}

function leTopKeysByValue(array $counts, int $limit, bool $ascending): array
{
    if ($ascending) {
        asort($counts);
    } else {
        arsort($counts);
    }
    return array_slice(array_keys($counts), 0, $limit);
}

function leFindRepeatedFromLatest(array $latestBalls, array $previousRows): array
{
    $cols     = ['first', 'second', 'third', 'fourth', 'fifth'];
    $repeated = [];
    foreach ($previousRows as $row) {
        foreach ($cols as $col) {
            if (!isset($row[$col]) || $row[$col] === '') {
                continue;
            }
            $val = (string)(int)$row[$col];
            if (in_array($val, $latestBalls, true) && !in_array($val, $repeated, true)) {
                $repeated[] = $val;
            }
        }
    }
    return $repeated;
}

function leCommaList(array $items): string
{
    return implode(', ', $items);
}

function leFetchRecentDraws(DatabaseDriver $db, string $dbCol, string $gameId, int $limit): array
{
    try {
        $query = $db->getQuery(true);
        $query->select($db->quoteName(['draw_date', 'next_draw_date', 'next_jackpot', 'first', 'second', 'third', 'fourth', 'fifth']))
              ->from($db->quoteName($dbCol))
              ->where($db->quoteName('game_id') . ' = ' . $db->quote($gameId))
              ->order($db->quoteName('draw_date') . ' DESC')
              ->setLimit($limit);
        $db->setQuery($query);
        return (array)($db->loadAssocList() ?: []);
    } catch (\Exception $e) {
        return [];
    }
}

function leGetPreviousOccurrenceDate(DatabaseDriver $db, string $dbCol, string $gameId, string $drawDate, string $ball): ?string
{
    try {
        $qBall = $db->quote($ball);
        $query = $db->getQuery(true);
        $query->select($db->quoteName('draw_date'))
              ->from($db->quoteName($dbCol))
              ->where($db->quoteName('game_id') . ' = ' . $db->quote($gameId))
              ->where($db->quoteName('draw_date') . ' < ' . $db->quote($drawDate))
              ->where(
                  '(' .
                  $db->quoteName('first')  . ' = ' . $qBall . ' OR ' .
                  $db->quoteName('second') . ' = ' . $qBall . ' OR ' .
                  $db->quoteName('third')  . ' = ' . $qBall . ' OR ' .
                  $db->quoteName('fourth') . ' = ' . $qBall . ' OR ' .
                  $db->quoteName('fifth')  . ' = ' . $qBall .
                  ')'
              )
              ->order($db->quoteName('draw_date') . ' DESC')
              ->setLimit(1);
        $db->setQuery($query);
        $result = $db->loadResult();
        return $result ?: null;
    } catch (\Exception $e) {
        return null;
    }
}

function leGetDrawingsSinceDate(DatabaseDriver $db, string $dbCol, string $gameId, ?string $previousDate, string $currentDate): ?int
{
    if ($previousDate === null) {
        return null;
    }
    try {
        $query = $db->getQuery(true);
        $query->select('COUNT(*)')
              ->from($db->quoteName($dbCol))
              ->where($db->quoteName('game_id') . ' = ' . $db->quote($gameId))
              ->where($db->quoteName('draw_date') . ' > ' . $db->quote($previousDate))
              ->where($db->quoteName('draw_date') . ' <= ' . $db->quote($currentDate));
        $db->setQuery($query);
        return (int)$db->loadResult();
    } catch (\Exception $e) {
        return null;
    }
}

function leEscapeJsString(string $value): string
{
    return str_replace(
        ['\\',   "'",    '"',    "\n",   "\r",   '</'],
        ['\\\\', "\\'",  '\\"',  '\\n',  '\\r',  '<\\/'],
        $value
    );
}

// ── Game identity ─────────────────────────────────────────────────────────────
$gId        = isset($gId)        ? (string)$gId        : (isset($_GET['gameId']) ? htmlspecialchars(strip_tags((string)$_GET['gameId']), ENT_QUOTES, 'UTF-8') : 'ID4');
$stateName  = isset($stateName)  ? (string)$stateName  : '';
$stateAbrev = isset($stateAbrev) ? (string)$stateAbrev : '';
$gName      = isset($gName)      ? (string)$gName      : '';
$dbCol      = isset($dbCol)      ? (string)$dbCol      : 'lottery_draws';
$sTn        = $stateAbrev;

$nodCurrentMain = 100;
if (isset($_GET['nodCurrentMain'])) {
    $tmp = (int)$_GET['nodCurrentMain'];
    if ($tmp >= 10 && $tmp <= 700) {
        $nodCurrentMain = $tmp;
    }
}

// ── JSON config ───────────────────────────────────────────────────────────────
$mainMin = 1;
$mainMax = 39;
if (defined('JPATH_ROOT')) {
    $cfgPath = JPATH_ROOT . '/lottery_skip_config.json';
    if (is_file($cfgPath)) {
        $cfgRaw = @file_get_contents($cfgPath);
        if ($cfgRaw !== false) {
            $cfgData = @json_decode($cfgRaw, true);
            if (isset($cfgData['lotteries'][$gId]['lotteryConfig']['max_main_ball_number'])) {
                $mainMax = (int)$cfgData['lotteries'][$gId]['lotteryConfig']['max_main_ball_number'];
            }
        }
    }
}

// ── DB queries ────────────────────────────────────────────────────────────────
$db        = Factory::getDbo();
$rowsMain  = leFetchRecentDraws($db, $dbCol, $gId, $nodCurrentMain);
$rows100   = leFetchRecentDraws($db, $dbCol, $gId, 100);
$window50  = leFetchRecentDraws($db, $dbCol, $gId, 50);
$window300 = leFetchRecentDraws($db, $dbCol, $gId, 300);

// ── Init ranges ───────────────────────────────────────────────────────────────
[$mainCounts, $mainLastSeenIndex] = leInitRange($mainMin, $mainMax);
[$mainCounts100, ]                = leInitRange($mainMin, $mainMax);
[$counts50, ]                     = leInitRange($mainMin, $mainMax);
[$counts300, ]                    = leInitRange($mainMin, $mainMax);

$ballCols = ['first', 'second', 'third', 'fourth', 'fifth'];

foreach ($rowsMain as $drawIdx => $row) {
    foreach ($ballCols as $col) {
        if (!isset($row[$col]) || $row[$col] === '' || $row[$col] === null) {
            continue;
        }
        $b = (string)(int)$row[$col];
        if (!array_key_exists($b, $mainCounts)) {
            continue;
        }
        $mainCounts[$b]++;
        if ($mainLastSeenIndex[$b] === null) {
            $mainLastSeenIndex[$b] = $drawIdx;
        }
    }
}

foreach ($rows100 as $row) {
    foreach ($ballCols as $col) {
        if (!isset($row[$col]) || $row[$col] === '' || $row[$col] === null) {
            continue;
        }
        $b = (string)(int)$row[$col];
        if (array_key_exists($b, $mainCounts100)) {
            $mainCounts100[$b]++;
        }
    }
}

foreach ($window50 as $row) {
    foreach ($ballCols as $col) {
        if (!isset($row[$col]) || $row[$col] === '' || $row[$col] === null) {
            continue;
        }
        $b = (string)(int)$row[$col];
        if (array_key_exists($b, $counts50)) {
            $counts50[$b]++;
        }
    }
}

foreach ($window300 as $row) {
    foreach ($ballCols as $col) {
        if (!isset($row[$col]) || $row[$col] === '' || $row[$col] === null) {
            continue;
        }
        $b = (string)(int)$row[$col];
        if (array_key_exists($b, $counts300)) {
            $counts300[$b]++;
        }
    }
}

// ── Chart arrays ──────────────────────────────────────────────────────────────
$mainChartLabels    = leBuildNaturalLabels($mainMin, $mainMax);
$mainChartValues    = [];
$mainChartValues100 = [];
$mainRecencyValues  = [];

for ($i = $mainMin; $i <= $mainMax; $i++) {
    $key                  = (string)$i;
    $mainChartValues[]    = (int)($mainCounts[$key] ?? 0);
    $mainChartValues100[] = (int)($mainCounts100[$key] ?? 0);
    $rIdx                 = $mainLastSeenIndex[$key] ?? null;
    $mainRecencyValues[]  = ($rIdx === null) ? ($nodCurrentMain + 1) : ($rIdx + 1);
}

// ── Top active ────────────────────────────────────────────────────────────────
$topActiveKeys   = leTopKeysByValue($mainCounts, 10, false);
$topActiveLabels = array_map('lePad2', $topActiveKeys);
$topActiveValues = array_map(function ($k) use ($mainCounts) {
    return (int)($mainCounts[$k] ?? 0);
}, $topActiveKeys);

// ── Quietest ──────────────────────────────────────────────────────────────────
$recencySort = [];
foreach ($mainLastSeenIndex as $k => $v) {
    $recencySort[$k] = ($v === null) ? PHP_INT_MAX : $v;
}
arsort($recencySort);
$quietestKeys   = array_slice(array_keys($recencySort), 0, 10);
$quietestLabels = array_map('lePad2', $quietestKeys);
$quietestValues = array_map(function ($k) use ($mainLastSeenIndex, $nodCurrentMain) {
    $v = $mainLastSeenIndex[$k] ?? null;
    return ($v === null) ? ($nodCurrentMain + 1) : ($v + 1);
}, $quietestKeys);

// ── Latest draw ───────────────────────────────────────────────────────────────
$latestRow   = $rowsMain[0] ?? null;
$latestBalls = [];
if ($latestRow) {
    foreach ($ballCols as $col) {
        if (isset($latestRow[$col]) && $latestRow[$col] !== '') {
            $latestBalls[] = (string)(int)$latestRow[$col];
        }
    }
}

// ── Repeated numbers ──────────────────────────────────────────────────────────
$repeatedNumbers = [];
if ($latestRow && count($rowsMain) > 1) {
    $repeatedNumbers = leFindRepeatedFromLatest($latestBalls, array_slice($rowsMain, 1, 5));
}
$repeatedDisplay = count($repeatedNumbers)
    ? leCommaList(array_map('lePad2', $repeatedNumbers))
    : 'None detected';

// ── Summaries ─────────────────────────────────────────────────────────────────
$mostActiveSummary = count($topActiveKeys)
    ? leCommaList(array_map('lePad2', array_slice($topActiveKeys, 0, 5)))
    : '—';
$quietSummary = count($quietestKeys)
    ? leCommaList(array_map('lePad2', array_slice($quietestKeys, 0, 3)))
    : '—';

// ── Window shift ──────────────────────────────────────────────────────────────
$counts50sorted  = $counts50;
$counts300sorted = $counts300;
arsort($counts50sorted);
arsort($counts300sorted);
$top50          = array_slice(array_keys($counts50sorted), 0, 10);
$top300         = array_slice(array_keys($counts300sorted), 0, 10);
$windowShiftIn  = array_values(array_diff($top50, $top300));
$windowShiftOut = array_values(array_diff($top300, $top50));

if (count($windowShiftIn) > 0) {
    $windowChangeNarrative = 'Numbers ' . leCommaList(array_map('lePad2', $windowShiftIn)) . ' have entered the top 10 in the recent 50-draw window but were absent from the top 10 over 300 draws.';
} elseif (count($windowShiftOut) > 0) {
    $windowChangeNarrative = 'Numbers ' . leCommaList(array_map('lePad2', $windowShiftOut)) . ' were top 10 over 300 draws but have cooled in the recent 50-draw window.';
} else {
    $windowChangeNarrative = 'The top 10 numbers are consistent between the 50-draw and 300-draw windows, indicating stable frequency patterns.';
}

// ── Draw history rows (5 most recent) ─────────────────────────────────────────
$drawHistoryRows = [];
for ($hi = 0; $hi < 5; $hi++) {
    if (!isset($rowsMain[$hi])) {
        break;
    }
    $hRow   = $rowsMain[$hi];
    $hBalls = [];
    foreach ($ballCols as $col) {
        $hBalls[] = (isset($hRow[$col]) && $hRow[$col] !== '')
            ? lePad2((string)(int)$hRow[$col])
            : '—';
    }
    $drawHistoryRows[] = [
        'date'  => leFmtDate($hRow['draw_date'] ?? null),
        'balls' => $hBalls,
    ];
}

// ── Hero display ──────────────────────────────────────────────────────────────
$heroLatestDate  = leFmtDateLong($latestRow['draw_date'] ?? null);
$heroNextDraw    = leFmtDateLong($latestRow['next_draw_date'] ?? null);
$heroNextJackpot = htmlspecialchars((string)($latestRow['next_jackpot'] ?? '—'), ENT_QUOTES, 'UTF-8');
$heroLatestBalls = [];
foreach ($ballCols as $col) {
    $heroLatestBalls[] = lePad2((string)(int)($latestRow[$col] ?? 0));
}
$heroInsight  = 'Latest verified draw and recent number behavior at a glance. Review the most active numbers, quiet stretches, and full historical frequency before moving into deeper SKAI analysis.';
$overviewNote = 'Frequency shows historical occurrence within the selected window. It can help identify recent concentration and quiet periods, but it should be interpreted as context rather than prediction.';

// ── Logo ──────────────────────────────────────────────────────────────────────
$logo = leResolveLogo($stateAbrev, $gName, $gId);

// ── Routes ────────────────────────────────────────────────────────────────────
$gIdEncoded   = rawurlencode($gId);
$stateNameEnc = rawurlencode($stateName);
$gNameEnc     = rawurlencode($gName);
$stAbEnc      = rawurlencode($stateAbrev);
$routeSkai    = '/picking-winning-numbers/artificial-intelligence/skai-lottery-prediction?gameId=' . $gIdEncoded;
$routeAi      = '/picking-winning-numbers/artificial-intelligence/ai-powered-predictions?game_id=' . $gIdEncoded;
$routeMcmc    = '/picking-winning-numbers/artificial-intelligence/markov-chain-monte-carlo-mcmc-analysis?gameId=' . $gIdEncoded;
$routeHeatmap = '/all-lottery-heatmaps?gId=' . $gIdEncoded . '&stateName=' . $stateNameEnc . '&gName=' . $gNameEnc . '&sTn=' . $stAbEnc;
$routeSkipHit = '/picking-winning-numbers/artificial-intelligence/skip-and-hit-analysis?game_id=' . $gIdEncoded;
$routeArchives = '/lottery-archives-pick5?gId=' . $gIdEncoded . '&stateName=' . $stateNameEnc . '&gName=' . $gNameEnc . '&sTn=' . $stAbEnc;
$routeLowest  = '/lowest-drawn-number-analysis?gId=' . $gIdEncoded . '&stateName=' . $stateNameEnc . '&gName=' . $gNameEnc . '&sTn=' . $stAbEnc;

// ── Page meta ─────────────────────────────────────────────────────────────────
$gameFull     = htmlspecialchars(trim($gName ?: $gId), ENT_QUOTES, 'UTF-8');
$pageTitle    = $gameFull . ' Number Frequency Analysis | LottoExpert';
$metaDesc     = 'Explore ' . $gameFull . ' number frequency, recency, and draw history. Analytical lottery tools powered by LottoExpert for informed, data-driven play.';
$canonicalUrl = defined('JPATH_ROOT') ? htmlspecialchars(Uri::current(), ENT_QUOTES, 'UTF-8') : '';
$jsonLdArr    = [
    '@context'    => 'https://schema.org',
    '@type'       => 'WebPage',
    'name'        => strip_tags($gameFull) . ' Number Frequency Analysis',
    'description' => strip_tags($metaDesc),
    'url'         => strip_tags($canonicalUrl),
    'publisher'   => [
        '@type' => 'Organization',
        'name'  => 'LottoExpert',
        'url'   => 'https://lottoexpert.net',
    ],
];
$jsonLd = json_encode($jsonLdArr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);

// ── Table rows ────────────────────────────────────────────────────────────────
$quietSetFlip  = array_flip($quietestKeys);
$activeSetFlip = array_flip($topActiveKeys);
$tableRows     = [];
for ($i = $mainMin; $i <= $mainMax; $i++) {
    $key  = (string)$i;
    $freq = (int)($mainCounts[$key] ?? 0);
    $lsi  = $mainLastSeenIndex[$key] ?? null;
    [$agoNum, $agoLabel] = leDrawingsAgoLabel($lsi, $nodCurrentMain);
    $pct   = $nodCurrentMain > 0 ? number_format($freq / $nodCurrentMain * 100, 1) : '0.0';
    $types = [];
    if (isset($activeSetFlip[$key])) {
        $types[] = 'active';
    }
    if (isset($quietSetFlip[$key])) {
        $types[] = 'quiet';
    }
    if ($lsi !== null && $lsi < 10) {
        $types[] = 'recent';
    }
    if (empty($types)) {
        $types[] = 'other';
    }
    $tableRows[] = [
        'num'      => lePad2($key),
        'freq'     => $freq,
        'agoNum'   => $agoNum,
        'agoLabel' => $agoLabel,
        'pct'      => $pct,
        'rowType'  => implode(' ', $types),
    ];
}

// ── Form token ────────────────────────────────────────────────────────────────
$formToken     = defined('JPATH_ROOT') ? Factory::getApplication()->getFormToken() : '_token';
$formActionUrl = $canonicalUrl;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
<meta name="description" content="<?php echo htmlspecialchars($metaDesc, ENT_QUOTES, 'UTF-8'); ?>">
<?php if ($canonicalUrl): ?>
<link rel="canonical" href="<?php echo $canonicalUrl; ?>">
<link rel="alternate" hreflang="en" href="<?php echo $canonicalUrl; ?>">
<?php endif; ?>
<script type="application/ld+json"><?php echo $jsonLd; ?></script>
<style>
:root {
    --skai-blue: #1C66FF;
    --deep-navy: #0A1A33;
    --sky-gray: #EFEFF5;
    --soft-slate: #7F8DAA;
    --success-green: #20C997;
    --caution-amber: #F5A623;
    --white: #ffffff;
    --border-color: #E2E4ED;
    --radius-sm: 6px;
    --radius-md: 10px;
    --radius-lg: 14px;
    --shadow-sm: 0 1px 4px rgba(0, 0, 0, .06);
    --shadow-md: 0 2px 12px rgba(0, 0, 0, .08);
    --shadow-lg: 0 4px 24px rgba(0, 0, 0, .12);
    --font-sans: 'Segoe UI', system-ui, -apple-system, BlinkMacSystemFont, 'Helvetica Neue', Arial, sans-serif;
    --transition: 0.18s ease;
    --hero-bg: #0A1A33;
    --strip-bg: #F0F4FF;
    --section-bg: #ffffff;
    --alt-bg: #F8F9FC;
}

*, *::before, *::after {
    box-sizing: border-box;
}

.skai-page {
    font-family: var(--font-sans);
    color: var(--deep-navy);
    background: var(--sky-gray);
    margin: 0;
    padding: 0;
    line-height: 1.55;
    -webkit-font-smoothing: antialiased;
}

/* ── Hero ────────────────────────────────────────────────────────────────── */
.skai-hero {
    background: var(--hero-bg);
    padding: 48px 0 36px;
    position: relative;
    overflow: hidden;
}

.skai-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse 80% 60% at 70% 40%, rgba(28, 102, 255, .18) 0%, transparent 70%);
    pointer-events: none;
}

.skai-hero-inner {
    max-width: 1140px;
    margin: 0 auto;
    padding: 0 24px;
    position: relative;
    z-index: 1;
}

.skai-hero-top {
    display: flex;
    align-items: flex-start;
    gap: 28px;
}

.skai-logo {
    flex-shrink: 0;
    width: 72px;
    height: 72px;
    border-radius: var(--radius-md);
    overflow: hidden;
    background: rgba(255, 255, 255, .08);
    display: flex;
    align-items: center;
    justify-content: center;
}

.skai-logo img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.skai-logo-placeholder {
    width: 72px;
    height: 72px;
    border-radius: var(--radius-md);
    background: rgba(255, 255, 255, .10);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
    color: rgba(255, 255, 255, .5);
    flex-shrink: 0;
}

.skai-hero-copy {
    flex: 1;
    min-width: 0;
}

.skai-kicker {
    display: inline-block;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: .10em;
    text-transform: uppercase;
    color: var(--skai-blue);
    background: rgba(28, 102, 255, .15);
    border-radius: 4px;
    padding: 3px 9px;
    margin-bottom: 10px;
}

.skai-title {
    font-size: clamp(22px, 4vw, 32px);
    font-weight: 800;
    color: #ffffff;
    margin: 0 0 10px;
    line-height: 1.2;
    letter-spacing: -.02em;
}

.skai-hero-summary {
    font-size: 14px;
    color: rgba(255, 255, 255, .65);
    max-width: 540px;
    margin: 0;
    line-height: 1.6;
}

.skai-result-panel {
    flex-shrink: 0;
    background: rgba(255, 255, 255, .07);
    border: 1px solid rgba(255, 255, 255, .12);
    border-radius: var(--radius-lg);
    padding: 20px 24px;
    min-width: 280px;
    max-width: 340px;
}

.skai-panel-label {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .10em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, .40);
    margin-bottom: 12px;
}

.skai-meta-stack {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-bottom: 16px;
}

.skai-meta-row {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.skai-meta-box {
    font-size: 12px;
    color: rgba(255, 255, 255, .75);
    background: rgba(255, 255, 255, .08);
    border-radius: 5px;
    padding: 4px 10px;
    white-space: nowrap;
}

.skai-ball-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
    margin-bottom: 18px;
}

.skai-ball-gap {
    width: 6px;
}

.skai-ball {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 800;
    line-height: 1;
    flex-shrink: 0;
}

.skai-ball--main {
    background: var(--skai-blue);
    color: #ffffff;
    box-shadow: 0 3px 10px rgba(28, 102, 255, .45);
}

.skai-ball--bonus {
    background: var(--caution-amber);
    color: #ffffff;
    box-shadow: 0 3px 10px rgba(245, 166, 35, .45);
}

.skai-hero-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 14px;
}

.skai-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 9px 20px;
    border-radius: var(--radius-sm);
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    transition: opacity var(--transition), transform var(--transition);
    cursor: pointer;
    white-space: nowrap;
    border: none;
}

.skai-btn:hover {
    opacity: .88;
    transform: translateY(-1px);
}

.skai-btn--primary {
    background: var(--skai-blue);
    color: #ffffff;
    box-shadow: 0 3px 12px rgba(28, 102, 255, .40);
}

.skai-btn--secondary {
    background: rgba(255, 255, 255, .12);
    color: #ffffff;
    border: 1px solid rgba(255, 255, 255, .20);
}

.skai-advanced-links {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

.skai-mini-link {
    font-size: 12px;
    font-weight: 600;
    color: rgba(255, 255, 255, .55);
    text-decoration: none;
    letter-spacing: .02em;
    transition: color var(--transition);
}

.skai-mini-link:hover {
    color: rgba(255, 255, 255, .90);
}

/* ── Key Takeaways Strip ─────────────────────────────────────────────────── */
.skai-strip {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    background: var(--strip-bg);
    border-bottom: 1px solid var(--border-color);
}

.skai-stat {
    padding: 0;
    border-right: 1px solid var(--border-color);
}

.skai-stat:last-child {
    border-right: none;
}

.skai-stat-head {
    padding: 7px 16px;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .09em;
    text-transform: uppercase;
    color: #ffffff;
}

.skai-stat-head--horizon {
    background: var(--skai-blue);
}

.skai-stat-head--radiant {
    background: #7B4CFF;
}

.skai-stat-head--success {
    background: var(--success-green);
}

.skai-stat-head--ember {
    background: var(--caution-amber);
}

.skai-stat-body {
    padding: 12px 16px 14px;
}

.skai-stat-value {
    font-size: 15px;
    font-weight: 700;
    color: var(--deep-navy);
    margin-bottom: 3px;
    word-break: break-word;
}

.skai-stat-note {
    font-size: 11px;
    color: var(--soft-slate);
}

/* ── Nav Tabs ────────────────────────────────────────────────────────────── */
.skai-tabs {
    display: flex;
    gap: 0;
    background: #ffffff;
    border-bottom: 2px solid var(--border-color);
    padding: 0 24px;
    max-width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
}

.skai-tabs::-webkit-scrollbar {
    display: none;
}

.skai-tab {
    display: inline-flex;
    align-items: center;
    padding: 14px 18px;
    font-size: 13px;
    font-weight: 600;
    color: var(--soft-slate);
    text-decoration: none;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    cursor: pointer;
    white-space: nowrap;
    transition: color var(--transition), border-color var(--transition);
    background: none;
    border-top: none;
    border-left: none;
    border-right: none;
}

.skai-tab:hover {
    color: var(--deep-navy);
}

.skai-tab--active {
    color: var(--skai-blue);
    border-bottom-color: var(--skai-blue);
}

/* ── Sections ────────────────────────────────────────────────────────────── */
.skai-section {
    max-width: 1140px;
    margin: 0 auto;
    padding: 40px 24px 0;
}

.skai-section:last-of-type {
    padding-bottom: 60px;
}

.skai-section-head {
    margin-bottom: 22px;
}

.skai-section-title {
    font-size: clamp(18px, 3vw, 24px);
    font-weight: 800;
    color: var(--deep-navy);
    margin: 0 0 6px;
    letter-spacing: -.02em;
}

.skai-section-sub {
    font-size: 13px;
    color: var(--soft-slate);
    margin: 0;
}

.skai-section-body {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

/* ── Overview Grid ───────────────────────────────────────────────────────── */
.skai-overview-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

/* ── Cards ───────────────────────────────────────────────────────────────── */
.skai-card {
    background: var(--section-bg);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-md);
    overflow: hidden;
}

.skai-card-head {
    padding: 12px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.skai-card-head--horizon {
    background: var(--skai-blue);
    color: #ffffff;
}

.skai-card-head--radiant {
    background: #7B4CFF;
    color: #ffffff;
}

.skai-card-head--success {
    background: var(--success-green);
    color: #ffffff;
}

.skai-card-head--ember {
    background: var(--caution-amber);
    color: #ffffff;
}

.skai-card-head h3,
.skai-card-head h2 {
    margin: 0;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: .02em;
    color: inherit;
}

.skai-card-sub {
    font-size: 11px;
    color: var(--soft-slate);
    margin: 0;
    padding: 8px 20px 0;
}

.skai-card-body {
    padding: 16px 20px 20px;
}

.skai-chart-frame {
    position: relative;
    height: 220px;
    width: 100%;
}

.skai-chart-frame--tall {
    height: 460px;
}

.skai-note {
    background: var(--alt-bg);
    border-left: 3px solid var(--skai-blue);
    border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
    padding: 12px 16px;
    font-size: 13px;
    color: var(--soft-slate);
    line-height: 1.55;
    margin-top: 16px;
}

/* ── Two Column Layout ───────────────────────────────────────────────────── */
.skai-two-col {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

/* ── Draw History ────────────────────────────────────────────────────────── */
.skai-history-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.skai-history-item {
    background: var(--alt-bg);
    border-radius: var(--radius-md);
    padding: 12px 16px;
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
}

.skai-history-name {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: var(--soft-slate);
    min-width: 72px;
}

.skai-history-date {
    font-size: 12px;
    font-weight: 600;
    color: var(--deep-navy);
    min-width: 82px;
}

.skai-history-badge {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

/* ── Window Shift Panel ──────────────────────────────────────────────────── */
.skai-window-shift {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}

.skai-shift-panel {
    background: var(--alt-bg);
    border-radius: var(--radius-md);
    overflow: hidden;
}

.skai-shift-label {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .07em;
    text-transform: uppercase;
    padding: 8px 14px;
    background: var(--deep-navy);
    color: rgba(255, 255, 255, .75);
}

.skai-shift-text {
    padding: 12px 14px;
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    min-height: 56px;
}

/* ── Controls ────────────────────────────────────────────────────────────── */
.skai-controls {
    background: #ffffff;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    padding: 16px 20px;
    margin-bottom: 16px;
}

.skai-controls form {
    margin: 0;
    padding: 0;
}

.skai-controls-row {
    display: flex;
    align-items: flex-end;
    gap: 16px;
    flex-wrap: wrap;
}

.skai-controls-left {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.skai-controls-right {
    display: flex;
    align-items: flex-end;
    gap: 10px;
}

.skai-controls label {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: .05em;
    text-transform: uppercase;
    color: var(--soft-slate);
}

.skai-select {
    appearance: none;
    -webkit-appearance: none;
    background: var(--sky-gray) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%237F8DAA' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E") no-repeat right 10px center;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    padding: 8px 32px 8px 12px;
    font-size: 13px;
    color: var(--deep-navy);
    font-family: inherit;
    cursor: pointer;
    min-width: 160px;
    transition: border-color var(--transition);
}

.skai-select:focus {
    outline: none;
    border-color: var(--skai-blue);
}

.skai-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 9px 22px;
    background: var(--skai-blue);
    color: #ffffff;
    border: none;
    border-radius: var(--radius-sm);
    font-size: 13px;
    font-weight: 700;
    font-family: inherit;
    cursor: pointer;
    transition: opacity var(--transition);
    white-space: nowrap;
}

.skai-button:hover {
    opacity: .88;
}

/* ── Filter Group ────────────────────────────────────────────────────────── */
.skai-filter-group {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 14px;
}

.skai-filter {
    display: inline-flex;
    align-items: center;
    padding: 6px 14px;
    border-radius: 20px;
    border: 1px solid var(--border-color);
    background: #ffffff;
    font-size: 12px;
    font-weight: 600;
    color: var(--soft-slate);
    cursor: pointer;
    transition: all var(--transition);
    font-family: inherit;
}

.skai-filter:hover {
    border-color: var(--skai-blue);
    color: var(--skai-blue);
}

.skai-filter.is-active {
    background: var(--skai-blue);
    border-color: var(--skai-blue);
    color: #ffffff;
}

/* ── Table ───────────────────────────────────────────────────────────────── */
.skai-table-wrap {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-sm);
    min-width: 320px;
}

table.skai-table {
    width: 100%;
    border-collapse: collapse;
    background: #ffffff;
    font-size: 13px;
    min-width: 320px;
}

table.skai-table thead th {
    padding: 8px 6px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: var(--soft-slate);
    background: var(--alt-bg);
    border-bottom: 2px solid var(--border-color);
    text-align: left;
    white-space: nowrap;
}

table.skai-table thead th:first-child {
    padding-left: 16px;
    border-radius: var(--radius-sm) 0 0 0;
}

table.skai-table thead th:last-child {
    padding-right: 16px;
    border-radius: 0 var(--radius-sm) 0 0;
}

table.skai-table tbody td {
    padding: 9px 7px;
    border-bottom: 1px solid var(--border-color);
    color: var(--deep-navy);
    vertical-align: middle;
}

table.skai-table tbody td:first-child {
    padding-left: 16px;
}

table.skai-table tbody td:last-child {
    padding-right: 16px;
}

table.skai-table tbody tr:last-child td {
    border-bottom: none;
}

table.skai-table tbody tr:hover {
    background: var(--sky-gray);
}

.skai-pill--main {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--skai-blue);
    color: #ffffff;
    font-size: 12px;
    font-weight: 800;
    flex-shrink: 0;
}

.skai-pill--bonus {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--caution-amber);
    color: #ffffff;
    font-size: 12px;
    font-weight: 800;
    flex-shrink: 0;
}

.skai-checkbox {
    width: 16px;
    height: 16px;
    accent-color: var(--skai-blue);
    cursor: pointer;
}

/* ── Tracked Panel ───────────────────────────────────────────────────────── */
.skai-tracked {
    background: var(--section-bg);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    margin-top: 16px;
    overflow: hidden;
}

.skai-tracked-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    background: var(--alt-bg);
    border-bottom: 1px solid var(--border-color);
}

.skai-tracked-title {
    font-size: 12px;
    font-weight: 700;
    color: var(--deep-navy);
    letter-spacing: .04em;
    text-transform: uppercase;
}

.skai-tracked-actions {
    display: flex;
    gap: 8px;
}

.skai-link-btn {
    display: inline-flex;
    align-items: center;
    font-size: 11px;
    font-weight: 600;
    color: var(--skai-blue);
    background: none;
    border: none;
    padding: 0;
    cursor: pointer;
    font-family: inherit;
    text-decoration: underline;
    text-underline-offset: 2px;
}

.skai-chip-wrap {
    padding: 12px 16px;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    min-height: 52px;
    align-items: center;
}

.skai-empty {
    font-size: 12px;
    color: var(--soft-slate);
    font-style: italic;
}

.skai-chip--main {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 34px;
    height: 34px;
    padding: 0 8px;
    border-radius: var(--radius-sm);
    background: rgba(28, 102, 255, .12);
    color: var(--skai-blue);
    font-size: 12px;
    font-weight: 800;
    border: 1px solid rgba(28, 102, 255, .25);
}

.skai-chip--bonus {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 34px;
    height: 34px;
    padding: 0 8px;
    border-radius: var(--radius-sm);
    background: rgba(245, 166, 35, .12);
    color: #c47d00;
    font-size: 12px;
    font-weight: 800;
    border: 1px solid rgba(245, 166, 35, .30);
}

/* ── Advanced Tool Cards ─────────────────────────────────────────────────── */
.skai-tool-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 24px;
}

.skai-tool {
    background: var(--section-bg);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-md);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.skai-tool-head {
    padding: 16px 20px 12px;
    border-bottom: 1px solid var(--border-color);
}

.skai-tool-head h3 {
    margin: 0;
    font-size: 15px;
    font-weight: 800;
    color: var(--deep-navy);
    letter-spacing: -.01em;
}

.skai-tool-body {
    padding: 16px 20px 20px;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.skai-tool-copy {
    font-size: 13px;
    color: var(--soft-slate);
    line-height: 1.6;
    flex: 1;
}

.skai-tool-cta {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 9px 18px;
    background: var(--skai-blue);
    color: #ffffff;
    border-radius: var(--radius-sm);
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    transition: opacity var(--transition);
    align-self: flex-start;
}

.skai-tool-cta:hover {
    opacity: .88;
}

/* ── Utility Links Grid ──────────────────────────────────────────────────── */
.skai-utility-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
}

.skai-utility-link {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
    background: var(--section-bg);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    padding: 14px 16px;
    text-decoration: none;
    transition: border-color var(--transition), box-shadow var(--transition);
}

.skai-utility-link:hover {
    border-color: var(--skai-blue);
    box-shadow: var(--shadow-sm);
}

.skai-utility-link strong {
    font-size: 13px;
    font-weight: 700;
    color: var(--deep-navy);
}

.skai-utility-link span {
    font-size: 11px;
    color: var(--soft-slate);
}

/* ── Method Note ─────────────────────────────────────────────────────────── */
.skai-method-note {
    background: var(--alt-bg);
    border-top: 1px solid var(--border-color);
    padding: 32px 24px;
    margin-top: 40px;
}

.skai-method-note-inner {
    max-width: 1140px;
    margin: 0 auto;
}

.skai-method-note h2 {
    font-size: 15px;
    font-weight: 800;
    color: var(--deep-navy);
    margin: 0 0 10px;
    letter-spacing: -.01em;
}

.skai-method-note p {
    font-size: 13px;
    color: var(--soft-slate);
    line-height: 1.65;
    max-width: 760px;
    margin: 0 0 8px;
}

.skai-method-note p:last-child {
    margin-bottom: 0;
}

/* ── Responsive: 1080px ──────────────────────────────────────────────────── */
@media (max-width: 1080px) {
    .skai-tool-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .skai-utility-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .skai-strip {
        grid-template-columns: repeat(2, 1fr);
    }
    .skai-stat:nth-child(2) {
        border-right: none;
    }
    .skai-stat:nth-child(1),
    .skai-stat:nth-child(2) {
        border-bottom: 1px solid var(--border-color);
    }
}

/* ── Responsive: 780px ───────────────────────────────────────────────────── */
@media (max-width: 780px) {
    .skai-hero-top {
        flex-direction: column;
    }
    .skai-result-panel {
        max-width: 100%;
        min-width: 0;
        width: 100%;
    }
    .skai-overview-grid {
        grid-template-columns: 1fr;
    }
    .skai-two-col {
        grid-template-columns: 1fr;
    }
    .skai-window-shift {
        grid-template-columns: 1fr;
    }
    .skai-strip {
        grid-template-columns: 1fr 1fr;
    }
    .skai-tool-grid {
        grid-template-columns: 1fr;
    }
    .skai-utility-grid {
        grid-template-columns: 1fr 1fr;
    }
    .skai-section {
        padding: 28px 16px 0;
    }
    .skai-hero-inner {
        padding: 0 16px;
    }
    .skai-tabs {
        padding: 0 12px;
    }
    .skai-tab {
        padding: 12px 14px;
        font-size: 12px;
    }
    .skai-chart-frame--tall {
        height: 600px;
    }
}

@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
        animation-duration: .01ms !important;
        transition-duration: .01ms !important;
    }
}
</style>
</head>
<body class="skai-page">

<!-- ── Hero ──────────────────────────────────────────────────────────────── -->
<section class="skai-hero">
    <div class="skai-hero-inner">
        <div class="skai-hero-top">
            <?php if ($logo['exists']): ?>
            <div class="skai-logo">
                <img src="<?php echo htmlspecialchars($logo['url'], ENT_QUOTES, 'UTF-8'); ?>"
                     alt="<?php echo $gameFull; ?> logo" width="72" height="72" loading="lazy">
            </div>
            <?php else: ?>
            <div class="skai-logo-placeholder" aria-hidden="true">&#127777;</div>
            <?php endif; ?>

            <div class="skai-hero-copy">
                <span class="skai-kicker"><?php echo htmlspecialchars($stateName ?: 'Lottery', ENT_QUOTES, 'UTF-8'); ?> &middot; Frequency Analysis</span>
                <h1 class="skai-title"><?php echo $gameFull; ?> Numbers</h1>
                <p class="skai-hero-summary"><?php echo htmlspecialchars($heroInsight, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>

            <div class="skai-result-panel">
                <div class="skai-panel-label">Latest Draw</div>
                <div class="skai-meta-stack">
                    <div class="skai-meta-row">
                        <div class="skai-meta-box"><?php echo htmlspecialchars($heroLatestDate ?: '—', ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php if ($heroNextDraw && $heroNextDraw !== '—'): ?>
                        <div class="skai-meta-box">Next: <?php echo htmlspecialchars($heroNextDraw, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>
                    </div>
                    <?php if ($heroNextJackpot && $heroNextJackpot !== '—'): ?>
                    <div class="skai-meta-row">
                        <div class="skai-meta-box">Est. Jackpot: <?php echo $heroNextJackpot; ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="skai-ball-row">
                    <?php foreach ($heroLatestBalls as $hb): ?>
                    <span class="skai-ball skai-ball--main"><?php echo htmlspecialchars($hb, ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endforeach; ?>
                </div>
                <div class="skai-hero-actions">
                    <a href="<?php echo htmlspecialchars($routeSkai, ENT_QUOTES, 'UTF-8'); ?>" class="skai-btn skai-btn--primary">SKAI Analysis</a>
                    <a href="<?php echo htmlspecialchars($routeAi, ENT_QUOTES, 'UTF-8'); ?>" class="skai-btn skai-btn--secondary">AI Predictions</a>
                    <a href="<?php echo htmlspecialchars($routeSkipHit, ENT_QUOTES, 'UTF-8'); ?>" class="skai-btn skai-btn--secondary">Skip &amp; Hit</a>
                </div>
                <div class="skai-advanced-links">
                    <a href="<?php echo htmlspecialchars($routeMcmc, ENT_QUOTES, 'UTF-8'); ?>" class="skai-mini-link">MCMC Analysis</a>
                    <a href="<?php echo htmlspecialchars($routeHeatmap, ENT_QUOTES, 'UTF-8'); ?>" class="skai-mini-link">Heatmap</a>
                    <a href="<?php echo htmlspecialchars($routeArchives, ENT_QUOTES, 'UTF-8'); ?>" class="skai-mini-link">Archives</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── Key Takeaways Strip ────────────────────────────────────────────────── -->
<div class="skai-strip">
    <div class="skai-stat">
        <div class="skai-stat-head skai-stat-head--horizon">Most Active</div>
        <div class="skai-stat-body">
            <div class="skai-stat-value"><?php echo htmlspecialchars($mostActiveSummary, ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="skai-stat-note">Top 5 by frequency — last <?php echo (int)$nodCurrentMain; ?> draws</div>
        </div>
    </div>
    <div class="skai-stat">
        <div class="skai-stat-head skai-stat-head--ember">Quietest Now</div>
        <div class="skai-stat-body">
            <div class="skai-stat-value"><?php echo htmlspecialchars($quietSummary, ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="skai-stat-note">Most draws since last seen</div>
        </div>
    </div>
    <div class="skai-stat">
        <div class="skai-stat-head skai-stat-head--success">Repeated Recently</div>
        <div class="skai-stat-body">
            <div class="skai-stat-value"><?php echo htmlspecialchars($repeatedDisplay, ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="skai-stat-note">Latest draw numbers in prior 5 draws</div>
        </div>
    </div>
    <div class="skai-stat">
        <div class="skai-stat-head skai-stat-head--radiant">Window Analyzed</div>
        <div class="skai-stat-body">
            <div class="skai-stat-value"><?php echo (int)$nodCurrentMain; ?> draws</div>
            <div class="skai-stat-note"><?php echo (int)count($rowsMain); ?> records loaded</div>
        </div>
    </div>
</div>

<!-- ── Nav Tabs ───────────────────────────────────────────────────────────── -->
<nav class="skai-tabs" role="navigation" aria-label="Page sections">
    <a href="#overview" class="skai-tab skai-tab--active">Overview</a>
    <a href="#frequency" class="skai-tab">Frequency</a>
    <a href="#recency" class="skai-tab">Recency</a>
    <a href="#tables" class="skai-tab">Tables</a>
    <a href="#advanced" class="skai-tab">Advanced Tools</a>
</nav>

<!-- ── Section: Overview ─────────────────────────────────────────────────── -->
<section id="overview" class="skai-section">
    <div class="skai-section-head">
        <h2 class="skai-section-title">Overview</h2>
        <p class="skai-section-sub">Top 10 most active and top 10 quietest numbers at a glance — <?php echo (int)$nodCurrentMain; ?>-draw window.</p>
    </div>
    <div class="skai-section-body">
        <div class="skai-overview-grid">
            <div class="skai-card">
                <div class="skai-card-head skai-card-head--horizon">
                    <h3>Top 10 Most Active</h3>
                </div>
                <p class="skai-card-sub">Frequency count &mdash; last <?php echo (int)$nodCurrentMain; ?> draws</p>
                <div class="skai-card-body">
                    <div class="skai-chart-frame">
                        <canvas id="topActiveChart" aria-label="Top 10 most active numbers chart" role="img"></canvas>
                    </div>
                </div>
            </div>
            <div class="skai-card">
                <div class="skai-card-head skai-card-head--ember">
                    <h3>Top 10 Quietest</h3>
                </div>
                <p class="skai-card-sub">Draws since last seen &mdash; most overdue first</p>
                <div class="skai-card-body">
                    <div class="skai-chart-frame">
                        <canvas id="quietChart" aria-label="Top 10 quietest numbers chart" role="img"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="skai-note"><?php echo htmlspecialchars($overviewNote, ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
</section>

<!-- ── Section: Recency ───────────────────────────────────────────────────── -->
<section id="recency" class="skai-section">
    <div class="skai-section-head">
        <h2 class="skai-section-title">Recency</h2>
        <p class="skai-section-sub">Recent draw history and window-shift comparison between 50-draw and 300-draw views.</p>
    </div>
    <div class="skai-section-body">
        <div class="skai-two-col">
            <div class="skai-card">
                <div class="skai-card-head skai-card-head--horizon">
                    <h3>Recent Draw History</h3>
                </div>
                <div class="skai-card-body">
                    <?php if (count($drawHistoryRows) > 0): ?>
                    <div class="skai-history-list">
                        <?php foreach ($drawHistoryRows as $idx => $hRow): ?>
                        <div class="skai-history-item">
                            <div class="skai-history-name">#<?php echo (int)($idx + 1); ?></div>
                            <div class="skai-history-date"><?php echo htmlspecialchars($hRow['date'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="skai-history-badge">
                                <?php foreach ($hRow['balls'] as $hBall): ?>
                                <span class="skai-ball skai-ball--main" style="width:32px;height:32px;font-size:11px;"><?php echo htmlspecialchars($hBall, ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p style="font-size:13px;color:var(--soft-slate);">No draw history available.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="skai-card">
                <div class="skai-card-head skai-card-head--radiant">
                    <h3>Window Shift: 50 vs 300 Draws</h3>
                </div>
                <div class="skai-card-body">
                    <div class="skai-window-shift">
                        <div class="skai-shift-panel">
                            <div class="skai-shift-label">Last 50 Draws &mdash; Top 10</div>
                            <div class="skai-shift-text">
                                <?php foreach ($top50 as $sk): ?>
                                <span class="skai-pill--main" style="<?php echo in_array($sk, $windowShiftIn) ? 'background:var(--success-green);' : ''; ?>"><?php echo htmlspecialchars(lePad2($sk), ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="skai-shift-panel">
                            <div class="skai-shift-label">Last 300 Draws &mdash; Top 10</div>
                            <div class="skai-shift-text">
                                <?php foreach ($top300 as $sk): ?>
                                <span class="skai-pill--main" style="<?php echo in_array($sk, $windowShiftOut) ? 'opacity:.5;' : ''; ?>"><?php echo htmlspecialchars(lePad2($sk), ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="skai-note" style="margin-top:14px;"><?php echo htmlspecialchars($windowChangeNarrative, ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── Section: Frequency Deep Dive ──────────────────────────────────────── -->
<section id="frequency" class="skai-section">
    <div class="skai-section-head">
        <h2 class="skai-section-title">Frequency Deep Dive</h2>
        <p class="skai-section-sub">Full number range frequency and recency distance — toggle between windows.</p>
    </div>
    <div class="skai-section-body">
        <div class="skai-card">
            <div class="skai-card-head skai-card-head--horizon">
                <h3>All Numbers &mdash; Frequency</h3>
                <div class="skai-filter-group" style="margin:0;">
                    <button type="button" id="btnChartN" class="skai-filter is-active">Last <?php echo (int)$nodCurrentMain; ?> draws</button>
                    <button type="button" id="btnChart100" class="skai-filter">Past 100 draws</button>
                </div>
            </div>
            <p class="skai-card-sub" id="fullMainChartSub">Showing: Last <?php echo (int)$nodCurrentMain; ?> draws</p>
            <div class="skai-card-body">
                <div class="skai-chart-frame skai-chart-frame--tall">
                    <canvas id="fullMainChart" aria-label="Full number frequency chart" role="img"></canvas>
                </div>
            </div>
        </div>

        <div class="skai-card">
            <div class="skai-card-head skai-card-head--ember">
                <h3>Recency Distance &mdash; All Numbers</h3>
            </div>
            <p class="skai-card-sub">Draws since each number was last seen. Higher = more overdue within this window.</p>
            <div class="skai-card-body">
                <div class="skai-chart-frame skai-chart-frame--tall">
                    <canvas id="recencyChart" aria-label="Recency distance chart" role="img"></canvas>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── Section: Tables ────────────────────────────────────────────────────── -->
<section id="tables" class="skai-section">
    <div class="skai-section-head">
        <h2 class="skai-section-title">Number Reference Tables</h2>
        <p class="skai-section-sub">Full frequency and recency table for all main numbers. Use filters and the tracker to mark numbers of interest.</p>
    </div>
    <div class="skai-section-body">
        <div class="skai-controls">
            <form method="get" action="<?php echo $formActionUrl; ?>">
                <input type="hidden" name="<?php echo htmlspecialchars($formToken, ENT_QUOTES, 'UTF-8'); ?>" value="1">
                <div class="skai-controls-row">
                    <div class="skai-controls-left">
                        <label for="nodCurrentMain">Analysis Window</label>
                        <select id="nodCurrentMain" name="nodCurrentMain" class="skai-select">
                            <?php for ($w = 10; $w <= 700; $w += 5): ?>
                            <option value="<?php echo (int)$w; ?>"<?php echo ($w === $nodCurrentMain) ? ' selected' : ''; ?>>Last <?php echo (int)$w; ?> draws</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="skai-controls-right">
                        <button type="submit" class="skai-button">Apply</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="skai-card">
            <div class="skai-card-head skai-card-head--horizon">
                <h3>Main Numbers &mdash; <?php echo (int)$nodCurrentMain; ?>-Draw Window</h3>
            </div>
            <div class="skai-card-body">
                <div class="skai-filter-group" data-filter-group="main">
                    <button type="button" class="skai-filter is-active" data-filter="all">All</button>
                    <button type="button" class="skai-filter" data-filter="active">Most Active</button>
                    <button type="button" class="skai-filter" data-filter="quiet">Quietest</button>
                    <button type="button" class="skai-filter" data-filter="recent">Recently Seen</button>
                </div>
                <div class="skai-table-wrap">
                    <table class="skai-table">
                        <thead>
                            <tr>
                                <th scope="col">Track</th>
                                <th scope="col">Number</th>
                                <th scope="col">Frequency</th>
                                <th scope="col">% of Draws</th>
                                <th scope="col">Last Seen</th>
                            </tr>
                        </thead>
                        <tbody id="mainTableBody">
                            <?php foreach ($tableRows as $tRow): ?>
                            <tr data-row-type="<?php echo htmlspecialchars($tRow['rowType'], ENT_QUOTES, 'UTF-8'); ?>">
                                <td>
                                    <input type="checkbox"
                                           class="skai-checkbox"
                                           data-track="main"
                                           value="<?php echo htmlspecialchars($tRow['num'], ENT_QUOTES, 'UTF-8'); ?>"
                                           aria-label="Track number <?php echo htmlspecialchars($tRow['num'], ENT_QUOTES, 'UTF-8'); ?>">
                                </td>
                                <td><span class="skai-pill--main"><?php echo htmlspecialchars($tRow['num'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td><strong><?php echo (int)$tRow['freq']; ?></strong></td>
                                <td><?php echo htmlspecialchars($tRow['pct'], ENT_QUOTES, 'UTF-8'); ?>%</td>
                                <td><?php echo htmlspecialchars($tRow['agoLabel'], ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="skai-tracked">
                    <div class="skai-tracked-head">
                        <span class="skai-tracked-title">Tracked Numbers</span>
                        <div class="skai-tracked-actions">
                            <button type="button" id="clearMainTracked" class="skai-link-btn">Clear all</button>
                        </div>
                    </div>
                    <div class="skai-chip-wrap" id="mainChipWrap">
                        <span class="skai-empty">None selected &mdash; check boxes above to track numbers.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── Section: Advanced Tools ────────────────────────────────────────────── -->
<section id="advanced" class="skai-section">
    <div class="skai-section-head">
        <h2 class="skai-section-title">Advanced Tools</h2>
        <p class="skai-section-sub">Deeper analytical models and supplemental data tools for <?php echo $gameFull; ?>.</p>
    </div>
    <div class="skai-section-body">
        <div class="skai-tool-grid">
            <div class="skai-tool">
                <div class="skai-tool-head" style="border-left:4px solid var(--skai-blue);">
                    <h3>SKAI AI Analysis</h3>
                </div>
                <div class="skai-tool-body">
                    <p class="skai-tool-copy">SKAI applies pattern recognition to historical draw sequences, identifying statistical anomalies and concentration windows across the number pool.</p>
                    <a href="<?php echo htmlspecialchars($routeSkai, ENT_QUOTES, 'UTF-8'); ?>" class="skai-tool-cta">Open SKAI &rarr;</a>
                </div>
            </div>
            <div class="skai-tool">
                <div class="skai-tool-head" style="border-left:4px solid #7B4CFF;">
                    <h3>AI-Powered Predictions</h3>
                </div>
                <div class="skai-tool-body">
                    <p class="skai-tool-copy">Multi-model AI analysis combining neural network pattern detection with long-range historical frequency data to produce statistically grounded number sets.</p>
                    <a href="<?php echo htmlspecialchars($routeAi, ENT_QUOTES, 'UTF-8'); ?>" class="skai-tool-cta">Open AI Tool &rarr;</a>
                </div>
            </div>
            <div class="skai-tool">
                <div class="skai-tool-head" style="border-left:4px solid var(--caution-amber);">
                    <h3>Skip &amp; Hit Analysis</h3>
                </div>
                <div class="skai-tool-body">
                    <p class="skai-tool-copy">Tracks the skip intervals between appearances for each number across the full draw history, revealing cyclical behavior and extended quiet streaks.</p>
                    <a href="<?php echo htmlspecialchars($routeSkipHit, ENT_QUOTES, 'UTF-8'); ?>" class="skai-tool-cta">Open Skip &amp; Hit &rarr;</a>
                </div>
            </div>
        </div>

        <div class="skai-utility-grid">
            <a href="<?php echo htmlspecialchars($routeMcmc, ENT_QUOTES, 'UTF-8'); ?>" class="skai-utility-link">
                <strong>MCMC Analysis</strong>
                <span>Markov Chain Monte Carlo simulation</span>
            </a>
            <a href="<?php echo htmlspecialchars($routeHeatmap, ENT_QUOTES, 'UTF-8'); ?>" class="skai-utility-link">
                <strong>Heatmap</strong>
                <span>Visual frequency heatmap by position</span>
            </a>
            <a href="<?php echo htmlspecialchars($routeArchives, ENT_QUOTES, 'UTF-8'); ?>" class="skai-utility-link">
                <strong>Archives</strong>
                <span>Full draw history &amp; past results</span>
            </a>
            <a href="<?php echo htmlspecialchars($routeLowest, ENT_QUOTES, 'UTF-8'); ?>" class="skai-utility-link">
                <strong>Lowest Number Analysis</strong>
                <span>Statistical view of lowest ball drawn</span>
            </a>
        </div>
    </div>
</section>

<!-- ── Method Note ────────────────────────────────────────────────────────── -->
<aside class="skai-method-note">
    <div class="skai-method-note-inner">
        <h2>About This Analysis</h2>
        <p>This page presents historical frequency and recency data derived from verified draw records for <?php echo $gameFull; ?>. All figures are based on the selected analysis window and reflect actual past draw outcomes, not projections.</p>
        <p>Frequency counts how many times each number has appeared within the selected window. Recency distance measures how many draws have elapsed since each number last appeared. Neither metric constitutes a prediction.</p>
        <p>Lottery draws are independent random events. Past occurrence frequency and recency patterns do not influence future outcomes. This data is provided for informational and analytical context only. Always play responsibly within your means.</p>
    </div>
</aside>

<script>
(function () {
    'use strict';

    var chartsInitialized = false;
    var retryCount = 0;
    var MAX_RETRIES = 20;
    var fullMainChartInstance = null;

    var chartData = {
        topActiveLabels:  <?php echo json_encode(array_values($topActiveLabels),  JSON_UNESCAPED_UNICODE); ?>,
        topActiveValues:  <?php echo json_encode(array_values($topActiveValues)); ?>,
        quietLabels:      <?php echo json_encode(array_values($quietestLabels),   JSON_UNESCAPED_UNICODE); ?>,
        quietValues:      <?php echo json_encode(array_values($quietestValues)); ?>,
        mainLabels:       <?php echo json_encode($mainChartLabels,                JSON_UNESCAPED_UNICODE); ?>,
        mainValues:       <?php echo json_encode($mainChartValues); ?>,
        mainValues100:    <?php echo json_encode($mainChartValues100); ?>,
        mainRecencyValues:<?php echo json_encode($mainRecencyValues); ?>
    };

    var nodCurrentMain = <?php echo (int)$nodCurrentMain; ?>;

    function loadChartJsIfNeeded(done) {
        if (window.Chart) {
            done();
            return;
        }
        var primary = document.createElement('script');
        primary.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
        primary.integrity = 'sha384-mhp2E+BLMiZLe7rtu0ioQ7nYbXpQLp6mU7SnMcA7bqMVq+B6MZ0FPhcjCBMqHrQ';
        primary.crossOrigin = 'anonymous';
        primary.onload = function () { done(); };
        primary.onerror = function () {
            var fallback = document.createElement('script');
            fallback.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
            fallback.onload = function () { done(); };
            fallback.onerror = function () { };
            document.head.appendChild(fallback);
        };
        document.head.appendChild(primary);
    }

    function tryRenderCharts() {
        if (window.Chart) {
            renderCharts();
            return;
        }
        if (retryCount >= MAX_RETRIES) {
            return;
        }
        retryCount = retryCount + 1;
        setTimeout(tryRenderCharts, 200);
    }

    function commonBarOptions(horizontal) {
        return {
            indexAxis: horizontal ? 'y' : 'x',
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 380 },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0A1A33',
                    padding: 10,
                    cornerRadius: 6,
                    titleFont: { family: 'Segoe UI, system-ui, sans-serif', size: 12 },
                    bodyFont: { family: 'Segoe UI, system-ui, sans-serif', size: 13 }
                }
            },
            scales: {
                x: {
                    grid: { color: 'rgba(0,0,0,.06)' },
                    ticks: { font: { size: 11 }, color: '#7F8DAA' }
                },
                y: {
                    grid: { color: 'rgba(0,0,0,.06)' },
                    ticks: { font: { size: 11 }, color: '#7F8DAA' }
                }
            }
        };
    }

    function renderCharts() {
        if (chartsInitialized) {
            return;
        }
        chartsInitialized = true;

        var c1 = document.getElementById('topActiveChart');
        if (c1) {
            new Chart(c1, {
                type: 'bar',
                data: {
                    labels: chartData.topActiveLabels,
                    datasets: [{
                        data: chartData.topActiveValues,
                        backgroundColor: '#1C66FF',
                        borderRadius: 4,
                        hoverBackgroundColor: '#0F50E0'
                    }]
                },
                options: commonBarOptions(false)
            });
        }

        var c2 = document.getElementById('quietChart');
        if (c2) {
            new Chart(c2, {
                type: 'bar',
                data: {
                    labels: chartData.quietLabels,
                    datasets: [{
                        data: chartData.quietValues,
                        backgroundColor: '#F5A623',
                        borderRadius: 4,
                        hoverBackgroundColor: '#D48C0F'
                    }]
                },
                options: commonBarOptions(false)
            });
        }

        var c3 = document.getElementById('fullMainChart');
        if (c3) {
            fullMainChartInstance = new Chart(c3, {
                type: 'bar',
                data: {
                    labels: chartData.mainLabels,
                    datasets: [{
                        data: chartData.mainValues,
                        backgroundColor: '#1C66FF',
                        borderRadius: 3,
                        hoverBackgroundColor: '#0F50E0'
                    }]
                },
                options: commonBarOptions(true)
            });
        }

        var c4 = document.getElementById('recencyChart');
        if (c4) {
            new Chart(c4, {
                type: 'bar',
                data: {
                    labels: chartData.mainLabels,
                    datasets: [{
                        data: chartData.mainRecencyValues,
                        backgroundColor: '#F5A623',
                        borderRadius: 3,
                        hoverBackgroundColor: '#D48C0F'
                    }]
                },
                options: commonBarOptions(false)
            });
        }
    }

    function bindChartToggle() {
        var btnN   = document.getElementById('btnChartN');
        var btn100 = document.getElementById('btnChart100');
        var sub    = document.getElementById('fullMainChartSub');

        if (btnN) {
            btnN.onclick = function () {
                if (!fullMainChartInstance) { return; }
                fullMainChartInstance.data.datasets[0].data = chartData.mainValues;
                fullMainChartInstance.update();
                if (sub) { sub.textContent = 'Showing: Last ' + nodCurrentMain + ' draws'; }
                btnN.classList.add('is-active');
                if (btn100) { btn100.classList.remove('is-active'); }
            };
        }

        if (btn100) {
            btn100.onclick = function () {
                if (!fullMainChartInstance) { return; }
                fullMainChartInstance.data.datasets[0].data = chartData.mainValues100;
                fullMainChartInstance.update();
                if (sub) { sub.textContent = 'Showing: Past 100 draws'; }
                btn100.classList.add('is-active');
                if (btnN) { btnN.classList.remove('is-active'); }
            };
        }
    }

    function renderTracked() {
        var chipWrap = document.getElementById('mainChipWrap');
        if (!chipWrap) { return; }
        var boxes   = document.querySelectorAll('.skai-checkbox[data-track="main"]');
        var tracked = [];
        var i;
        for (i = 0; i < boxes.length; i++) {
            if (boxes[i].checked) {
                tracked.push(boxes[i].value);
            }
        }
        chipWrap.innerHTML = '';
        if (tracked.length === 0) {
            var empty = document.createElement('span');
            empty.className   = 'skai-empty';
            empty.textContent = 'None selected \u2014 check boxes above to track numbers.';
            chipWrap.appendChild(empty);
            return;
        }
        for (i = 0; i < tracked.length; i++) {
            var chip = document.createElement('span');
            chip.className   = 'skai-chip--main';
            chip.textContent = tracked[i];
            chipWrap.appendChild(chip);
        }
    }

    function bindTrackers() {
        var boxes = document.querySelectorAll('.skai-checkbox[data-track="main"]');
        var i;
        for (i = 0; i < boxes.length; i++) {
            boxes[i].onchange = renderTracked;
        }
        var clearBtn = document.getElementById('clearMainTracked');
        if (clearBtn) {
            clearBtn.onclick = function () {
                var cbs = document.querySelectorAll('.skai-checkbox[data-track="main"]');
                var j;
                for (j = 0; j < cbs.length; j++) {
                    cbs[j].checked = false;
                }
                renderTracked();
            };
        }
        renderTracked();
    }

    function bindFilters() {
        var groups = document.querySelectorAll('[data-filter-group]');
        var g;
        for (g = 0; g < groups.length; g++) {
            (function (group) {
                var buttons = group.querySelectorAll('.skai-filter[data-filter]');
                var tableId = group.getAttribute('data-filter-group') === 'main' ? 'mainTableBody' : null;
                var tbody   = tableId ? document.getElementById(tableId) : null;
                var rows    = tbody ? tbody.querySelectorAll('tr') : [];
                var b;
                for (b = 0; b < buttons.length; b++) {
                    (function (btn) {
                        btn.onclick = function () {
                            var siblings = group.querySelectorAll('.skai-filter');
                            var s;
                            for (s = 0; s < siblings.length; s++) {
                                siblings[s].classList.remove('is-active');
                            }
                            btn.classList.add('is-active');
                            var val = btn.getAttribute('data-filter');
                            var r;
                            for (r = 0; r < rows.length; r++) {
                                var rowType = rows[r].getAttribute('data-row-type') || '';
                                if (val === 'all' || rowType.indexOf(val) !== -1) {
                                    rows[r].style.display = '';
                                } else {
                                    rows[r].style.display = 'none';
                                }
                            }
                        };
                    })(buttons[b]);
                }
            })(groups[g]);
        }
    }

    function initAnchors() {
        var tabs = document.querySelectorAll('.skai-tab');
        var i;
        for (i = 0; i < tabs.length; i++) {
            (function (tab) {
                tab.onclick = function () {
                    var all = document.querySelectorAll('.skai-tab');
                    var j;
                    for (j = 0; j < all.length; j++) {
                        all[j].classList.remove('skai-tab--active');
                    }
                    tab.classList.add('skai-tab--active');
                };
            })(tabs[i]);
        }
    }

    function init() {
        initAnchors();
        bindFilters();
        bindTrackers();
        bindChartToggle();
        loadChartJsIfNeeded(function () {
            tryRenderCharts();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
</script>
</body>
</html>
