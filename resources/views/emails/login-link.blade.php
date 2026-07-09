<!DOCTYPE html>
<html lang="ru">
<body style="font-family: -apple-system, system-ui, sans-serif; color: #1f2937; line-height: 1.5;">
<h2 style="color: #4f46e5;">Вход в P·VPN</h2>

<p>Нажмите кнопку, чтобы войти в личный кабинет. Ссылка действует {{ \App\Models\LoginToken::TTL_MINUTES }} минут и сработает один раз.</p>

<p style="margin: 24px 0;">
    <a href="{{ $url }}"
       style="display: inline-block; background: #4f46e5; color: white; padding: 12px 20px; border-radius: 6px; text-decoration: none;">
        Войти
    </a>
</p>

<p style="color: #6b7280; font-size: 13px;">
    Если кнопка не работает, скопируйте ссылку в браузер:<br>
    <a href="{{ $url }}">{{ $url }}</a>
</p>

<p style="color: #6b7280; font-size: 13px;">
    Если вы не запрашивали вход — просто проигнорируйте это письмо.
</p>
</body>
</html>
