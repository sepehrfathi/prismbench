<?php
declare(strict_types=1);
require __DIR__ . '/../app/lib.php';
pl_boot();

$trackId = isset($_GET['trackId']) ? (string)(int)$_GET['trackId'] : '';
$successFlag = isset($_GET['success']) ? (int)$_GET['success'] : 0;
if ($trackId === '' || $trackId === '0') pl_render_payment_page('خطا در پرداخت', 'شناسه تراکنش نامعتبر است.');

$settings = pl_load_settings();
$payment = pl_payment_find('zibal', 'track_id', $trackId);
if (!$payment) pl_render_payment_page('پرداخت یافت نشد', 'تراکنشی با این شناسه در سامانه پیدا نشد.');
if ((string)($payment['status'] ?? '') === 'paid') pl_render_payment_page('شارژ کیف‌پول', 'پرداخت شما قبلاً ثبت شده است.');

$pay = pl_payment_config($settings);
$verified = false;
$verifyResp = null;
if ($successFlag === 1) {
  $verifyResp = pl_zibal_post('/v1/verify', [
    'merchant' => $pay['zibal_merchant'],
    'trackId' => (int)$trackId,
  ]);
  if ($verifyResp && (int)($verifyResp['result'] ?? 0) === 100) $verified = true;
}

if (!$verified) {
  pl_payment_mark_failed((int)$payment['id']);
  pl_render_payment_page('پرداخت ناموفق', 'پرداخت توسط درگاه تایید نشد یا لغو شد.');
}

$extra = pl_payment_extra($payment);
$extra['_zibal_verify'] = $verifyResp;
$refNumber = isset($verifyResp['refNumber']) ? (string)$verifyResp['refNumber'] : $trackId;
if (!pl_payment_mark_paid_and_credit($payment, $refNumber, $extra)) {
  $fresh = pl_payment_find('zibal', 'track_id', $trackId);
  if (is_array($fresh) && (string)($fresh['status'] ?? '') === 'paid') pl_render_payment_page('شارژ کیف‌پول', 'پرداخت شما قبلاً ثبت شده است.');
  pl_render_payment_page('خطا در ثبت پرداخت', 'پرداخت تایید شد اما تراکنش در وضعیت قابل شارژ نیست. لطفاً با پشتیبانی تماس بگیرید.');
}

pl_render_payment_page('شارژ کیف‌پول', 'پرداخت با موفقیت انجام شد و کیف‌پول شما شارژ شد.');
