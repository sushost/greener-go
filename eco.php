<?php
// PHP 7.0–friendly eco.php (no strict_types, no return types)

header('Content-Type: application/json; charset=utf-8');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: interest-cohort=()');

// ---- CONFIG (tune as needed) ----
$E_AI_EXTRA  = getenv('E_AI_EXTRA_WH')      !== false ? (float)getenv('E_AI_EXTRA_WH')      : 0.20; // Wh/search avoided
$W_INTENSITY = getenv('W_INTENSITY_MLWH')   !== false ? (float)getenv('W_INTENSITY_MLWH')   : 0.20; // mL/Wh
$RATE_WINDOW = getenv('RATE_WINDOW_SEC')    !== false ? (int)getenv('RATE_WINDOW_SEC')      : 60;   // seconds

$DB_HOST = getenv('ECO_DB_HOST') ?: '127.0.0.1';   // force TCP to avoid socket issues
$DB_NAME = getenv('ECO_DB_NAME') ?: 'eco';
$DB_USER = getenv('ECO_DB_USER') ?: 'eco_app';
$DB_PASS = getenv('ECO_DB_PASS') ?: 'REPLACE-ME';

// Use your site-local salt (outside web/) – adjust if needed
$SALT_FILE = getenv('ECO_SALT_FILE') ?: __DIR__ . '/../private/eco_salt';

// ---- HELPERS ----
function iphash($salt) {
  $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
  return hash('sha256', $salt . $ip);
}
function apcu_get_safe($key) {
  return function_exists('apcu_fetch') ? apcu_fetch($key) : false;
}
function apcu_set_safe($key, $val, $ttl) {
  if (function_exists('apcu_store')) apcu_store($key, $val, $ttl);
}
function apcu_exists_safe($key) {
  return function_exists('apcu_exists') ? apcu_exists($key) : false;
}
function tinycache_path($key) {
  $dir = sys_get_temp_dir() . '/eco_cache';
  if (!is_dir($dir)) @mkdir($dir, 0700, true);
  return $dir . '/' . $key . '.json';
}
function tinycache_get($key, $maxAge) {
  $f = tinycache_path($key);
  if (is_file($f) && (time() - filemtime($f) <= $maxAge)) {
    $raw = @file_get_contents($f);
    $j = @json_decode($raw, true);
    if (is_array($j)) return $j;
  }
  return array();
}
function tinycache_set($key, $val) {
  $f = tinycache_path($key);
  @file_put_contents($f, json_encode($val), LOCK_EX);
}
function jerr($msg, $code = 500) {
  http_response_code($code);
  echo json_encode(array('ok'=>false, 'error'=>$msg));
  exit;
}

// ---- MAIN ----
$mode = isset($_GET['mode']) ? $_GET['mode'] : (isset($_POST['mode']) ? $_POST['mode'] : 'read');

// Load salt
$salt = @file_get_contents($SALT_FILE);
if ($salt === false || strlen(trim($salt)) < 16) {
  jerr('SALT missing or invalid', 500);
}
$hash = iphash($salt);

// Per-address today key (UTC day bucket)
$today = gmdate('Y-m-d');
$addrKey = 'eco_addr_' . $today . '_' . $hash;

// DB connect
try {
  $dsn = 'mysql:host='.$DB_HOST.';dbname='.$DB_NAME.';charset=utf8mb4';
  $pdo = new PDO($dsn, $DB_USER, $DB_PASS, array(
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
  ));
} catch (Exception $e) {
  jerr('DB connection failed', 500);
}

if ($mode === 'increment') {
  // Rate limit per address hash
  $rlKey = 'eco_rl_' . $hash;
  $limited = apcu_exists_safe($rlKey);
  if (!$limited) apcu_set_safe($rlKey, 1, $RATE_WINDOW);

  if (!$limited) {
    // Update global totals
    try {
      // some MariaDB versions ignore transactions on MyISAM, but this is safe
      $pdo->beginTransaction();
      $stmt = $pdo->prepare('UPDATE eco_counter
        SET total_searches = total_searches + 1,
            energy_saved_wh = energy_saved_wh + :e,
            water_saved_ml  = water_saved_ml  + :w
        WHERE id = 1');
      $stmt->execute(array(':e'=>$E_AI_EXTRA, ':w'=>($E_AI_EXTRA * $W_INTENSITY)));
      $pdo->commit();
    } catch (Exception $e) {
      // Try to roll back if possible; ignore rollback errors
      try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (Exception $ee) {}
      // Do not hard-fail—just continue to report current totals
    }

    // Update per-address today totals (APCu or tiny cache)
    $addr = apcu_get_safe($addrKey);
    if (!is_array($addr)) {
      $addr = tinycache_get($addrKey, 86400); // 1 day
      if (!is_array($addr) || !$addr) $addr = array('s'=>0, 'e'=>0.0, 'w'=>0.0);
    }
    $addr['s'] += 1;
    $addr['e'] += $E_AI_EXTRA;
    $addr['w'] += ($E_AI_EXTRA * $W_INTENSITY);

    if (function_exists('apcu_store')) {
      apcu_set_safe($addrKey, $addr, 86400);
    } else {
      tinycache_set($addrKey, $addr);
    }
  }
}

// Read totals (always)
try {
  $row = $pdo->query('SELECT total_searches, energy_saved_wh, water_saved_ml FROM eco_counter WHERE id=1')->fetch();
  if (!$row) $row = array('total_searches'=>0,'energy_saved_wh'=>0.0,'water_saved_ml'=>0.0);
} catch (Exception $e) {
  // If even SELECT fails, still return structure
  $row = array('total_searches'=>0,'energy_saved_wh'=>0.0,'water_saved_ml'=>0.0);
}

// Per-address "today"
$addr = apcu_get_safe($addrKey);
if (!is_array($addr)) {
  $addr = tinycache_get($addrKey, 86400);
  if (!is_array($addr) || !$addr) $addr = array('s'=>0,'e'=>0.0,'w'=>0.0);
}

// Output
echo json_encode(array(
  'ok' => true,
  'totals' => array(
    'searches' => (int)$row['total_searches'],
    'energy_saved_wh' => (float)$row['energy_saved_wh'],
    'water_saved_ml'  => (float)$row['water_saved_ml']
  ),
  'per_address_today' => array(
    'searches' => (int)$addr['s'],
    'energy_saved_wh' => round((float)$addr['e'], 6),
    'water_saved_ml'  => round((float)$addr['w'], 6)
  ),
  'assumptions' => array(
    'e_ai_extra_wh'  => (float)$E_AI_EXTRA,
    'water_intensity_ml_per_wh' => (float)$W_INTENSITY,
    'rate_window_sec' => (int)$RATE_WINDOW
  )
), JSON_UNESCAPED_SLASHES);
