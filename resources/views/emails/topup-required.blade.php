@php
    $shortfall = number_format($shortfallKopecks / 100, 2, '.', ' ');
    $months = $expiredSubscription->months;
    $graceDays = (int) config('wallet.grace_days', 3);
@endphp
<!DOCTYPE html>
<html lang="ru">
<body style="font-family: -apple-system, system-ui, sans-serif; color: #1f2937; line-height: 1.5;">
<h2 style="color: #b91c1c;">Подписка истекла — пополните, чтобы продлить</h2>

<p>Подписка истекла, а на балансе кошелька недостаточно средств для автопродления.</p>

<p>Не хватает: <strong>{{ $shortfall }} ₽</strong> для продления на {{ $months }} мес.</p>

<p>Ваш ключ доступа продолжит работать ещё {{ $graceDays }} дн. — успейте пополнить кошелёк, чтобы не потерять доступ.</p>

<p style="margin: 24px 0;">
    <a href="{{ $payUrl }}"
       style="display: inline-block; background: #4f46e5; color: white; padding: 12px 20px; border-radius: 6px; text-decoration: none;">
        Пополнить и продлить
    </a>
</p>

<p style="color: #6b7280; font-size: 13px;">
    Хотите изменить срок продления? Перейдите в <a href="{{ url('/wallet') }}">личный кабинет</a>.
</p>
</body>
</html>
