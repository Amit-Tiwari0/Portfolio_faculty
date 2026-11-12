<?php
declare(strict_types=1);
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store, private');
ini_set('session.use_strict_mode','1');
ini_set('session.use_only_cookies','1');
$requestIsHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
  || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
  || str_contains((string)($_SERVER['HTTP_CF_VISITOR'] ?? ''), 'https');
session_set_cookie_params(['httponly'=>true,'secure'=>$requestIsHttps,'samesite'=>'Lax']);
session_start();
$adminConfigFile = dirname(__DIR__) . '/data/admin-config.php';
$adminConfig = is_file($adminConfigFile) ? require $adminConfigFile : [];
$adminEmail = filter_var((string)($adminConfig['email'] ?? ''), FILTER_VALIDATE_EMAIL) ? (string)$adminConfig['email'] : 'admin@example.com';
$remoteAddress = (string)($_SERVER['REMOTE_ADDR']??'');
$allowInitialSetup = in_array($remoteAddress,['127.0.0.1','::1'],true)
  && empty($_SERVER['HTTP_CF_CONNECTING_IP'])
  && empty($_SERVER['HTTP_X_FORWARDED_FOR'])
  && empty($_SERVER['HTTP_CF_RAY'])
  && empty($_SERVER['HTTP_FORWARDED'])
  && empty($_SERVER['HTTP_X_FORWARDED_PROTO']);
$root = dirname(__DIR__);
$adminBase = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/admin/index.php'), '/') . '/';
$siteBase = rtrim(dirname(rtrim($adminBase, '/')), '/') . '/';
$authFile = $root . '/data/admin-auth.php';
$throttleFile = $root . '/data/admin-auth-throttle.json';
$contentFile = $root . '/data/content.php';
$authData = is_file($authFile) ? require $authFile : [];
$storedAccounts = is_array($authData['users']??null) ? $authData['users'] : (is_string($authData) && $authData!=='' ? [$adminEmail=>$authData] : []);
$userAccounts = [];
foreach ($storedAccounts as $email=>$hash) {
  $normalizedEmail = strtolower(trim((string)$email));
  if (filter_var($normalizedEmail,FILTER_VALIDATE_EMAIL) && is_string($hash) && $hash!=='') $userAccounts[$normalizedEmail] = $hash;
}
$hasPassword = count($userAccounts)>0;
$message = '';
$error = '';
$error = !$hasPassword && !$allowInitialSetup ? 'Initial admin setup is available only from the portfolio host computer.' : '';
$showPasswordPanel = false;
if (empty($_SESSION['admin_csrf'])) $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['admin_csrf'];
$content = require (is_file($contentFile) ? $contentFile : $root . '/data/content.example.php');
$jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
$collections = [
  'notices'=>'Notice','research_interests'=>'Research interests','education'=>'Education','teaching'=>'Teaching',
  'publications'=>'Publications','projects'=>'Projects','experience'=>'Experience',
  'supervision'=>'Student supervision','skills'=>'Technical skills','awards'=>'Awards and recognition',
  'service'=>'Academic service','talks'=>'Talks and outreach','custom_sections'=>'Additional sections',
];
$escape = static fn($s): string => htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');

/** Atomically update small local auth state files while serializing concurrent requests. */
$updateJsonState = static function (string $path, callable $update): array {
  $lock = fopen($path.'.lock','c');
  if ($lock===false) return [];
  try {
    if (!flock($lock,LOCK_EX)) return [];
    $state = is_file($path) ? json_decode((string)file_get_contents($path),true) : [];
    if (!is_array($state)) $state = [];
    $next = $update($state);
    if (!is_array($next)) $next = $state;
    $temporary = tempnam(dirname($path),'auth-state-');
    if ($temporary!==false) {
      $written = file_put_contents($temporary,json_encode($next,JSON_THROW_ON_ERROR),LOCK_EX);
      if ($written===false || !rename($temporary,$path)) @unlink($temporary);
      else @chmod($path,0600);
    }
    flock($lock,LOCK_UN);
    return $next;
  } catch (Throwable $e) {
    return [];
  } finally {
    fclose($lock);
  }
};
$clientAddress = static function (): string {
  $forwarded = (string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? '');
  $remote = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
  $candidate = filter_var($forwarded,FILTER_VALIDATE_IP) ? $forwarded : $remote;
  return hash('sha256',$candidate);
};
$consumeLimit = static function (string $key, int $limit, int $window) use ($updateJsonState,$throttleFile): bool {
  $now = time();
  $allowed = false;
  $updateJsonState($throttleFile,static function (array $state) use ($key,$limit,$window,$now,&$allowed): array {
    $row = is_array($state[$key]??null) ? $state[$key] : ['count'=>0,'start'=>$now,'blocked_until'=>0];
    if ((int)($row['blocked_until']??0)>$now) {
      $allowed = false;
      return $state;
    }
    if ($now-(int)($row['start']??0)>$window) $row = ['count'=>0,'start'=>$now,'blocked_until'=>0];
    $row['count'] = (int)($row['count']??0)+1;
    if ($row['count']>$limit) {
      $row['blocked_until'] = $now+$window;
      $allowed = false;
    } else $allowed = true;
    $state[$key] = $row;
    return $state;
  });
  return $allowed;
};
$clearLimit = static function (string $key) use ($updateJsonState,$throttleFile): void {
  $updateJsonState($throttleFile,static function (array $state) use ($key): array { unset($state[$key]); return $state; });
};
/** Store a set of email-to-password-hash accounts for future multi-user support. */
$saveUserAccounts = static function (string $path, array $accounts): bool {
  $temporary = tempnam(dirname($path), 'auth-');
  if ($temporary === false) return false;
  $contents = "<?php\nreturn ".var_export(['users'=>$accounts],true).";\n";
  if (file_put_contents($temporary,$contents,LOCK_EX) === false) {
    @unlink($temporary);
    return false;
  }
  @chmod($temporary,0600);
  if (!rename($temporary,$path)) {
    @unlink($temporary);
    return false;
  }
  return true;
};
if (is_string($authData) && $authData!=='' && $saveUserAccounts($authFile,$userAccounts)) {
  $authData = ['users'=>$userAccounts];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET')==='POST') {
  if (!hash_equals($csrf,(string)($_POST['csrf']??''))) {
    $error = 'This form expired. Refresh and try again.';
  } else {
    $action = (string)($_POST['action']??'');
    if ($action==='setup' && !$hasPassword && $allowInitialSetup) {
      $password = (string)($_POST['password']??'');
      $confirm = (string)($_POST['confirm_password']??'');
      if (strlen($password)<12) $error = 'Choose a password with at least 12 characters.';
      elseif (!hash_equals($password,$confirm)) $error = 'The two passwords do not match.';
      else {
        $accounts = [strtolower($adminEmail)=>password_hash($password,PASSWORD_DEFAULT)];
        if (!$saveUserAccounts($authFile,$accounts)) {
          $error = 'Could not save the admin password. Check folder permissions and try again.';
        } else {
          $hasPassword = true;
          $userAccounts = $accounts;
          session_regenerate_id(true);
          $_SESSION['admin_authenticated'] = true;
          $_SESSION['admin_username'] = strtolower($adminEmail);
          $message = 'Admin password created. You can now edit the portfolio.';
        }
      }
    } elseif ($action==='login' && $hasPassword) {
      $loginLimitKey = 'login:'.$clientAddress();
      $submittedEmail = strtolower(trim((string)($_POST['username']??'')));
      $passwordHash = (string)($userAccounts[$submittedEmail]??'');
      $passwordMatches = $passwordHash!=='' && password_verify((string)($_POST['password']??''),$passwordHash);
      if (!$consumeLimit($loginLimitKey,5,900)) {
        $error = 'Too many sign-in attempts. Please try again in 15 minutes.';
      } elseif ($passwordMatches) {
        $clearLimit($loginLimitKey);
        session_regenerate_id(true);
        $_SESSION['admin_authenticated'] = true;
        $_SESSION['admin_username'] = $submittedEmail;
        header('Location: '.$adminBase); exit;
      } else $error = 'The email or password was not recognized. Please try again.';
    } elseif ($action==='change_password' && !empty($_SESSION['admin_authenticated']) && $hasPassword) {
      $showPasswordPanel = true;
      $username = strtolower((string)($_SESSION['admin_username']??''));
      $passwordHash = (string)($userAccounts[$username]??'');
      $currentPassword = (string)($_POST['current_password']??'');
      $newPassword = (string)($_POST['new_password']??'');
      $confirmPassword = (string)($_POST['confirm_new_password']??'');
      if (!password_verify($currentPassword,$passwordHash)) {
        $error = 'The current password is incorrect.';
      } elseif (strlen($newPassword)<12) {
        $error = 'Choose a new password with at least 12 characters.';
      } elseif (!hash_equals($newPassword,$confirmPassword)) {
        $error = 'The new password and confirmation do not match.';
      } else {
        $hash = password_hash($newPassword,PASSWORD_DEFAULT);
        $updatedAccounts = $userAccounts;
        $updatedAccounts[$username] = $hash;
        if (!$saveUserAccounts($authFile,$updatedAccounts)) {
          $error = 'Could not update the password. Check folder permissions and try again.';
        } else {
          $userAccounts = $updatedAccounts;
          session_regenerate_id(true);
          $message = 'Admin password changed successfully.';
        }
      }
    } elseif ($action==='logout') {
      $_SESSION = [];
      session_regenerate_id(true);
      header('Location: '.$adminBase); exit;
    } elseif ($action==='save' && !empty($_SESSION['admin_authenticated'])) {
      try {
        $updated = json_decode((string)($_POST['content']??''),true,512,JSON_THROW_ON_ERROR);
        if (!is_array($updated) || !isset($updated['profile']) || !is_array($updated['profile'])) throw new RuntimeException('The form data could not be read. Please reload and try again.');
        foreach ($collections as $key=>$title) if (!isset($updated[$key]) || !is_array($updated[$key])) throw new RuntimeException("The $title section is missing or invalid.");
        if (trim((string)($updated['profile']['name']??''))==='') throw new RuntimeException('The profile name cannot be blank.');
        $socialLinks = is_array($updated['social_links']??null) ? array_slice($updated['social_links'], 0, 100) : [];
        $updated['social_links'] = [];
        foreach ($socialLinks as $link) {
          if (!is_array($link)) continue;
          $title = trim((string)($link['title']??''));
          $url = trim((string)($link['url']??''));
          if ($title!=='' || $url!=='') $updated['social_links'][] = ['title'=>$title, 'url'=>$url];
        }
        $profileDetails = is_array($updated['profile_details']??null) ? array_slice($updated['profile_details'], 0, 40) : [];
        $updated['profile_details'] = [];
        $detailIds = [];
        foreach ($profileDetails as $detail) {
          if (!is_array($detail)) continue;
          $id = preg_match('/^detail_[a-zA-Z0-9_-]{1,70}$/', (string)($detail['id']??'')) ? (string)$detail['id'] : 'detail_'.bin2hex(random_bytes(6));
          if (in_array($id, $detailIds, true)) $id = 'detail_'.bin2hex(random_bytes(6));
          $detailIds[] = $id;
          $updated['profile_details'][] = [
            'id'=>$id,
            'label'=>trim((string)($detail['label']??'')),
            'value'=>trim((string)($detail['value']??'')),
            'url'=>trim((string)($detail['url']??'')),
            'visible'=>!isset($detail['visible']) || !empty($detail['visible']),
          ];
        }
        $allowedOrder = array_values(array_diff(array_keys($collections), ['research_interests','custom_sections']));
        $customIds = [];
        foreach ($updated['custom_sections'] as $i=>&$customSection) {
          if (!is_array($customSection)) $customSection = [];
          $customSection['id'] = preg_match('/^custom_[a-zA-Z0-9_-]{1,60}$/', (string)($customSection['id']??'')) ? (string)$customSection['id'] : 'custom_'.bin2hex(random_bytes(6));
          if (in_array($customSection['id'], $customIds, true)) $customSection['id'] = 'custom_'.bin2hex(random_bytes(6));
          $customIds[] = $customSection['id'];
          $customSection['visible'] = !isset($customSection['visible']) || !empty($customSection['visible']);
        }
        unset($customSection);
        $orderable = array_merge($allowedOrder, $customIds);
        $updated['section_order'] = array_values(array_filter((array)($updated['section_order']??[]), static fn($key): bool => is_string($key) && in_array($key, $orderable, true)));
        foreach ($allowedOrder as $key) if (!in_array($key, $updated['section_order'], true)) $updated['section_order'][] = $key;
        foreach ($customIds as $key) if (!in_array($key, $updated['section_order'], true)) $updated['section_order'][] = $key;
        $updated['section_settings'] = is_array($updated['section_settings']??null) ? $updated['section_settings'] : [];
        $settingsTitles = array_merge(array_diff_key($collections, ['research_interests'=>true,'custom_sections'=>true]), ['about'=>'About','contact'=>'Contact']);
        foreach ($settingsTitles as $key=>$defaultTitle) if ($key!=='research_interests') {
          $setting = is_array($updated['section_settings'][$key]??null) ? $updated['section_settings'][$key] : [];
          $setting['title'] = trim((string)($setting['title']??$defaultTitle));
          if ($setting['title']==='') $setting['title'] = $defaultTitle;
          $setting['visible'] = !empty($setting['visible']);
          $updated['section_settings'][$key] = $setting;
        }
        if (isset($_FILES['entry_images']) && is_array($_FILES['entry_images']['name']??null)) {
          $uploadRoot = $root.'/assets/uploads';
          if (!is_dir($uploadRoot) && !mkdir($uploadRoot, 0755, true) && !is_dir($uploadRoot)) throw new RuntimeException('Could not create the image upload folder.');
          $extensions = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
          foreach ($_FILES['entry_images']['name'] as $collection=>$entries) foreach ($entries as $index=>$originalName) {
            $errorCode = $_FILES['entry_images']['error'][$collection][$index] ?? UPLOAD_ERR_NO_FILE;
            if ($errorCode===UPLOAD_ERR_NO_FILE) continue;
            if ($errorCode!==UPLOAD_ERR_OK) throw new RuntimeException('An entry image upload failed. Try a smaller image.');
            $tmp = $_FILES['entry_images']['tmp_name'][$collection][$index] ?? '';
            $size = (int)($_FILES['entry_images']['size'][$collection][$index] ?? 0);
            if ($size>5*1024*1024) throw new RuntimeException('Choose entry images smaller than 5 MB.');
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
            if (!isset($extensions[$mime]) || @getimagesize($tmp)===false) throw new RuntimeException('Use a JPG, PNG, or WebP image for entries.');
            $filename = bin2hex(random_bytes(12)).'.'.$extensions[$mime];
            if (!move_uploaded_file($tmp,$uploadRoot.'/'.$filename)) throw new RuntimeException('An entry image could not be saved. Check folder permissions.');
            if (!isset($updated[$collection][$index]) || !is_array($updated[$collection][$index])) $updated[$collection][$index] = [];
            $updated[$collection][$index]['image'] = 'assets/uploads/'.$filename;
          }
        }
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error']!==UPLOAD_ERR_NO_FILE) {
          if ($_FILES['profile_image']['error']!==UPLOAD_ERR_OK) throw new RuntimeException('The image upload did not complete. Try a smaller image.');
          if ($_FILES['profile_image']['size']>5*1024*1024) throw new RuntimeException('Choose an image smaller than 5 MB.');
          $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['profile_image']['tmp_name']);
          $extensions = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
          if (!isset($extensions[$mime]) || @getimagesize($_FILES['profile_image']['tmp_name'])===false) throw new RuntimeException('Use a JPG, PNG, or WebP image.');
          $filename = 'profile.' . $extensions[$mime];
          if (!move_uploaded_file($_FILES['profile_image']['tmp_name'],$root.'/assets/'.$filename)) throw new RuntimeException('The image could not be saved. Check folder permissions.');
          $updated['profile']['photo'] = 'assets/'.$filename;
          $updated['profile']['image_alt'] = 'Portrait of '.$updated['profile']['name'];
        }
        $updated['profile']['name'] = trim((string)$updated['profile']['name']);
        $temporary = tempnam($root.'/data','site-');
        if ($temporary===false) throw new RuntimeException('Could not prepare the saved profile. Check folder permissions.');
        $php = "<?php\nreturn ".var_export($updated,true).";\n";
        if (file_put_contents($temporary,$php,LOCK_EX)===false || !rename($temporary,$contentFile)) {
          @unlink($temporary);
          throw new RuntimeException('Could not save the portfolio. Check folder permissions.');
        }
        $content = $updated;
        $message = 'Portfolio saved successfully.';
      } catch (Throwable $e) { $error = $e->getMessage(); }
    }
  }
}
$authenticated = !empty($_SESSION['admin_authenticated'])
  && isset($userAccounts[strtolower((string)($_SESSION['admin_username']??''))]);
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin · Faculty Portfolio</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&amp;family=Roboto:wght@400;500;600;700&amp;display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $escape($adminBase) ?>admin.css?v=24">
</head>
<body>
<?php if (!$authenticated): ?>
  <main class="auth-wrap">
    <a class="back-link" href="<?= $escape($siteBase) ?>">← View faculty portfolio</a>
    <section class="auth-card">
      <div class="eyebrow">Portfolio administration</div>
      <h1><?= $hasPassword ? 'Admin sign in' : 'Set up admin access' ?></h1>
      <p class="hint"><?= $hasPassword
        ? 'Sign in to edit the faculty profile and all portfolio sections.'
        : ($allowInitialSetup ? 'Create a password before editing the portfolio. Use at least 12 characters and keep it private.' : 'Initial admin setup is restricted to the portfolio host computer.') ?></p>
      <?php if ($message): ?><div class="notice success"><?= $escape($message) ?></div><?php endif ?>
      <?php if ($error): ?><div class="notice error"><?= $escape($error) ?></div><?php endif ?>
      <?php if ($hasPassword || $allowInitialSetup): ?><form method="post">
        <input type="hidden" name="csrf" value="<?= $escape($csrf) ?>">
        <input type="hidden" name="action" value="<?= $hasPassword ? 'login' : 'setup' ?>">
        <?php if ($hasPassword): ?>
          <label>Username / email
            <input type="email" name="username" autocomplete="username" value="<?= $escape($adminEmail) ?>" required>
          </label>
          <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
        <?php else: ?>
          <label>Password<input type="password" name="password" autocomplete="new-password" required minlength="12"></label>
        <?php endif ?>
        <?php if (!$hasPassword): ?>
          <label>Confirm password
            <input type="password" name="confirm_password" autocomplete="new-password" required minlength="12">
          </label>
        <?php endif ?>
        <button class="button" type="submit"><?= $hasPassword ? 'Sign in' : 'Create admin password' ?></button>
      </form>
      <?php endif ?>
    </section>
  </main>
<?php else: ?>
  <header class="admin-header">
    <div>
      <span>Portfolio admin</span>
    </div>
    <div class="admin-header-actions">
      <button class="button secondary" id="toggle-password-panel" type="button" aria-expanded="<?= $showPasswordPanel ? 'true' : 'false' ?>" aria-controls="password-panel">Change password</button>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= $escape($csrf) ?>">
        <input type="hidden" name="action" value="logout">
        <button class="button secondary" type="submit">Sign out</button>
      </form>
    </div>
  </header>
  <main class="admin-main">
    <div class="page-heading">
      <h1>Portfolio admin</h1>
      <a class="view-link" href="<?= $escape($siteBase) ?>" target="_blank" rel="noreferrer">View portfolio ↗</a>
    </div>
    <?php if ($message): ?><div class="notice success"><?= $escape($message) ?></div><?php endif ?>
    <?php if ($error): ?><div class="notice error"><?= $escape($error) ?></div><?php endif ?>

    <section class="password-panel" id="password-panel" <?= $showPasswordPanel ? '' : 'hidden' ?>>
      <h2>Change admin password</h2>
      <p>Enter your current password and confirm the new one. New passwords must have at least 12 characters.</p>
      <form method="post" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= $escape($csrf) ?>">
        <input type="hidden" name="action" value="change_password">
        <div class="password-fields">
          <label class="field">Current password<input type="password" name="current_password" autocomplete="current-password" required></label>
          <label class="field">New password<input type="password" name="new_password" autocomplete="new-password" minlength="12" required></label>
          <label class="field">Confirm new password<input type="password" name="confirm_new_password" autocomplete="new-password" minlength="12" required></label>
        </div>
        <div class="password-actions">
          <button class="button" type="submit">Save new password</button>
          <button class="button secondary" id="cancel-password-panel" type="button">Cancel</button>
        </div>
      </form>
    </section>

    <form id="portfolio-form" method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= $escape($csrf) ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" id="content-json" name="content">
      <section class="editor-section">
        <h2>Profile details</h2>
        <p class="section-help">Edit personal information and the detail rows shown beside the portrait. Rename, hide, delete, or add detail rows; contact details appear only at the bottom of the public page.</p>
        <div id="profile-fields" class="profile-fields"></div>
        <div class="profile-detail-editor">
          <h3>Details shown on the profile</h3>
          <div id="profile-detail-fields" class="profile-detail-fields"></div>
          <button class="button secondary" type="button" id="add-profile-detail">+ Add profile detail</button>
        </div>
        <div class="image-editor">
          <img id="current-photo" src="<?= $escape($siteBase . ($content['profile']['photo'] ?? '')) ?>" alt="Current portrait">
          <div>
            <label for="profile-image">Profile photo</label>
            <input id="profile-image" name="profile_image" type="file" accept="image/jpeg,image/png,image/webp">
            <small>JPG, PNG, or WebP. Maximum 5 MB.</small>
          </div>
        </div>
      </section>
      <section class="editor-section">
        <h2>Additional contact and social links</h2>
        <p class="section-help">Add the links you want shown beside LinkedIn in the bottom Contact section. Give each link a name and URL.</p>
        <div id="social-link-fields" class="social-link-fields"></div>
        <button class="button secondary" type="button" id="add-social-link">+ Add link</button>
      </section>
      <section class="editor-section">
        <h2>Public section settings and order</h2>
        <p class="section-help">Rename, hide or show, add, delete, and reorder sections. Order numbers are shown only here in Admin.</p>
        <div id="section-settings" class="section-settings"></div>
        <div id="custom-section-editors" class="custom-section-editors"></div>
        <button class="button secondary" type="button" id="add-custom-section">+ Add section</button>
        <ol id="section-order-list" class="section-order-list"></ol>
      </section>
      <?php foreach ($collections as $key => $title): if ($key === 'custom_sections') continue; ?>
        <section class="editor-section collection-section" data-section="<?= $escape($key) ?>">
          <div class="section-heading">
            <div>
              <h2><?= $escape($title) ?></h2>
              <p class="section-help"><?php if ($key === 'supervision'): ?>Create and rename your own categories, then add students with their roll number, university, year range, and problem statement.<?php else: ?>Add entries, edit their details and links, or remove entries from this section.<?php endif ?></p>
              <?php if ($key === 'publications'): ?>
                <p class="section-help">Enter a publication category here. After saving, it appears as a filter button above the publications list.</p>
              <?php endif ?>
            </div>
            <button class="button add-entry" type="button">+ Add</button>
          </div>
          <?php if ($key === 'supervision'): ?>
            <div class="supervision-category-editor">
              <h3>Student categories</h3>
              <div id="supervision-category-fields"></div>
              <button class="button secondary" type="button" id="add-supervision-category">+ Add category</button>
            </div>
          <?php endif ?>
          <div class="entries" id="entries-<?= $escape($key) ?>"></div>
        </section>
      <?php endforeach ?>
      <div class="save-bar">
        <span>Changes appear on the public portfolio after saving.</span>
        <button class="button" type="submit">Save portfolio</button>
      </div>
    </form>
  </main>
  <script>window.adminPortfolioData = <?= json_encode($content,$jsonFlags) ?>;</script>
  <script src="<?= $escape($adminBase) ?>admin.js?v=15"></script>
<?php endif ?>
</body></html>
