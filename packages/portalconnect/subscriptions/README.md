# portalconnect/subscriptions

Подписки с оплатой из внутреннего кошелька: тарифные планы, покупка/активация,
автопродление, отмена. Вдохновлено laravelcm/laravel-subscriptions, адаптировано
под кошелёк (`portalconnect/wallet`) и внешний платёжный слой.

## API пользователя (trait `HasSubscriptions`)

```php
use PortalConnect\Subscriptions\Concerns\HasSubscriptions;

class User extends Authenticatable
{
    use HasSubscriptions;
}

$outcome = $user->newSubscription(3);   // покупка на 3 мес: активация или счёт
$outcome->activated;                    // true — оплачено с баланса
$outcome->bill?->payUrl;                // ссылка на оплату, если баланса не хватило

$user->hasActiveSubscription();
$user->activeSubscription();            // ?Subscription
$user->subscribedFor(3);
$user->onGrace();                       // истёкшая, но в grace-периоде
```

## API подписки

```php
$sub->active();  $sub->ended();  $sub->canceled();  $sub->pending();
$sub->daysLeft();
$sub->cancel();                  // доработает период, автопродления не будет
$sub->cancel(immediately: true); // доступ закрывается сейчас
$sub->renew();                   // продление на тот же период (кошелёк/счёт)
Subscription::active()->get();   // scope
```

## Тарифы

Источник истины — таблица `subscription_plans` (модель `Plan`: slug, months,
price_kopecks, features json, is_active, sort_order). Пока таблица пуста,
работает fallback на `config('subscriptions.prices')` (месяцы => копейки).
`Pricing::priceFor(3)`, `Pricing::all()`, `Pricing::planFor(3)`.

## Интеграция с платежами

Пакет не знает про платёжные шлюзы: при нехватке баланса зовёт контракт
`Contracts\PaymentInitiator` — приложение биндит адаптер к своему платёжному
слою (у нас — `App\Services\Payments\PaymentInitiatorAdapter` поверх
`portalconnect/payments`).

## События

`Events\SubscriptionActivated` — после активации (покупка, продление,
автопродление). Слушатели приложения вешают побочные эффекты
(у нас — восстановление revoked VPN-ключа).

## Конфиг

`config/subscriptions.php`: `model`, `user_model`, `prices` (fallback).
Модели переопределяются наследниками приложения — как в Sanctum/Passport.
