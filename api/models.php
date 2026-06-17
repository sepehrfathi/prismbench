<?php
declare(strict_types=1);
require __DIR__ . '/../app/lib.php';
pl_api_boot();
pl_boot();

$settings = pl_load_settings();
$user = pl_user_current();
$livePrices = pl_live_prices($settings);
pl_json([
  'ok' => true,
  'providers' => pl_public_model_catalog($settings, $user),
  'billing' => [
    'initial_credit_toman' => (float)($settings['initial_credit_toman'] ?? 0),
    'anonymous_free_runs' => (int)($settings['anonymous_free_runs'] ?? 0),
    'min_model_charge_toman' => (float)($settings['min_model_charge_toman'] ?? 0),
    'fixed_fee_per_run_toman' => (float)($settings['fixed_fee_per_run_toman'] ?? 0),
    'fixed_fee_per_model_toman' => (float)($settings['fixed_fee_per_model_toman'] ?? 0),
    'platform_markup_percent' => (float)($settings['platform_markup_percent'] ?? 0),
    'usd_to_toman' => pl_effective_usd_to_toman($settings),
    'topup_packages_toman' => pl_topup_packages($settings),
  ],
  'live_prices' => $livePrices,
]);
