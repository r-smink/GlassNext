<?php
/**
 * Standalone app-shell voor /dealers/ — bewust zonder wp_head()/wp_footer(), zodat thema,
 * pagebuilder, cookiebanner en tracking niet in de app laden.
 *
 * @var string     $state   login|denied|unavailable|ok
 * @var array      $assets  GlassNext_Dealer_Suite::asset_urls()
 * @var array|null $config  app-config (alleen bij ok)
 * @var array      $icons   GND_PWA::icons()
 * @var string     $body    HTML van login, melding of app
 */
if (!defined('ABSPATH')) exit;
?><!doctype html>
<html lang="nl">
<head>
<meta charset="<?php echo esc_attr(get_option('blog_charset')); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#0b1e2d">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="GN Dealer">
<meta name="format-detection" content="telephone=no">
<title>GlassNext Dealer</title>
<link rel="manifest" href="<?php echo esc_url(GND_PWA::manifest_url()); ?>">
<link rel="icon" type="image/png" sizes="192x192" href="<?php echo esc_url($icons['list'][0]['src']); ?>">
<link rel="apple-touch-icon" href="<?php echo esc_url($icons['apple']); ?>">
<link rel="stylesheet" href="<?php echo esc_url($assets['css']); ?>">
</head>
<body class="gnd-body gnd-state-<?php echo esc_attr($state); ?>">
<?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput -- opgebouwd met escaping in de plugin ?>
<?php if ($state === 'ok' && $config) : ?>
<script>window.GND_CONFIG=<?php echo wp_json_encode($config, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?>;</script>
<script src="<?php echo esc_url($assets['jspdf']); ?>" defer></script>
<script src="<?php echo esc_url($assets['xlsx']); ?>" defer></script>
<script src="<?php echo esc_url($assets['app']); ?>" defer></script>
<?php else : ?>
<script>
(function(){
  var f=document.getElementById('loginform');
  if(f){['user_login','user_pass'].forEach(function(id){var el=document.getElementById(id);if(el){el.required=true;}});
    var u=document.getElementById('user_login');if(u){u.setAttribute('autocomplete','username');u.setAttribute('autocapitalize','none');}
    var p=document.getElementById('user_pass');if(p){p.setAttribute('autocomplete','current-password');}}
  if('serviceWorker' in navigator){navigator.serviceWorker.register(<?php echo wp_json_encode(GND_PWA::sw_url(), JSON_UNESCAPED_SLASHES); ?>,{scope:<?php echo wp_json_encode(GlassNext_Dealer_Suite::app_path(), JSON_UNESCAPED_SLASHES); ?>}).catch(function(){});}
})();
</script>
<?php endif; ?>
</body>
</html>
