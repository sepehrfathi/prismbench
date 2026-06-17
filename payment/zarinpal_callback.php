<?php
declare(strict_types=1);
require __DIR__ . '/../app/lib.php';
pl_boot();

$authority = isset($_GET['Authority']) ? trim((string)$_GET['Authority']) : '';
$statusParam = isset($_GET['Status']) ? strtoupper((string)$_GET['Status']) : '';
if ($authority === '') pl_render_payment_page('خطا در پرداخت', 'شناسه Authority نامعتبر است.');

$settings = pl_load_settings();
$payment = pl_payment_find('zarinpal', 'authority', $authority);
if (!$payment) pl_render_payment_page('پرداخت یافت نشد', 'تراکنشی با این شناسه در سامانه پیدا نشد.');
if ((string)($payment['status'] ?? '') === 'paid') pl_render_payment_page('شارژ کیف‌پول', 'پرداخت شما قبلاً ثبت شده است.');

if ($statusParam !== 'OK') {
  pl_payment_mark_failed((int)$payment['id']);
  pl_render_payment_page('پرداخت ناموفق', 'پرداخت لغو شد یا تایید نشد.');
}

$pay = pl_payment_config($settings);
if ($pay['zarinpal_merchant'] === '') pl_render_payment_page('خطا در تایید پرداخت', 'مرچنت زرین‌پال تنظیم نشده است.');

$verifyResp = pl_zarinpal_post('/pg/v4/payment/verify.json', [
  'merchant_id' => $pay['zarinpal_merchant'],
  'authority' => $authority,
  'amount' => (int)($payment['amount_rial'] ?? ((float)($payment['amount_toman'] ?? 0) * 10)),
], (bool)$pay['zarinpal_sandbox']);

$data = is_array($verifyResp['data'] ?? null) ? $verifyResp['data'] : null;
$code = is_array($data) ? (int)($data['code'] ?? 0) : 0;
$refId = is_array($data) ? (string)($data['ref_id'] ?? '') : '';
if ($code !== 100 && $code !== 101) {
  pl_payment_mark_failed((int)$payment['id']);
  $msg = is_array($verifyResp) ? (string)($verifyResp['errors']['message'] ?? 'پرداخت توسط درگاه تایید نشد.') : 'پرداخت توسط درگاه تایید نشد.';
  pl_render_payment_page('پرداخت ناموفق', $msg);
}

$extra = pl_payment_extra($payment);
$extra['_zarinpal_verify'] = $verifyResp;
if (!pl_payment_mark_paid_and_credit($payment, $refId !== '' ? $refId : $authority, $extra)) {
  $fresh = pl_payment_find('zarinpal', 'authority', $authority);
  if (is_array($fresh) && (string)($fresh['status'] ?? '') === 'paid') pl_render_payment_page('شارژ کیف‌پول', 'پرداخت شما قبلاً ثبت شده است.');
  pl_render_payment_page('خطا در ثبت پرداخت', 'پرداخت تایید شد اما تراکنش در وضعیت قابل شارژ نیست. لطفاً با پشتیبانی تماس بگیرید.');
}

pl_render_payment_page('شارژ کیف‌پول', 'پرداخت با موفقیت انجام شد و کیف‌پول شما شارژ شد.');
