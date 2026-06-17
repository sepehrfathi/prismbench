<?php
declare(strict_types=1);
require __DIR__ . '/app/lib.php';
$settings = pl_load_settings();
$name = trim((string)($settings['app_title'] ?? 'PrismBench')) ?: 'PrismBench';
header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode([
  'name' => $name,
  'short_name' => pl_sub($name, 0, 20),
  'description' => 'مقایسه و قیمت‌گذاری مدل‌های هوش مصنوعی',
  'dir' => 'rtl',
  'lang' => 'fa-IR',
  'display' => 'standalone',
  'orientation' => 'portrait-primary',
  'theme_color' => '#1d2d44',
  'background_color' => '#ffffff',
  'scope' => pl_url('/', $settings),
  'start_url' => pl_url('/', $settings),
  'icons' => [
    ['src' => pl_url('/assets/img/pwa-icon.svg', $settings), 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any maskable'],
  ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}';
