# portalconnect/payments

Мультипровайдерный слой платёжных шлюзов. Новый шлюз = класс с контрактом
`Contracts\PaymentProvider` (createBill/parseWebhook/successResponse) + строка
в `config/payments.php`. Работает с DTO (`PaymentIntent`, `CreatedBill`,
`WebhookResult`) — без Eloquent-зависимостей.

Провайдеры: `cardlink` (API + HMAC-вебхуки), `freekassa` (SCI-ссылка,
md5-подписи, IP-whitelist, ответ `YES`). Выбор: `PAYMENT_PROVIDER` в env.

Обработка вебхуков (идемпотентность, консистентность, зачисление) — забота
приложения: см. `App\Services\Payments\PaymentFulfillmentService`.
