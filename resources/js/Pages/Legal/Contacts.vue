<script setup>
defineProps({ support: { type: Object, default: () => ({}) } })
</script>

<template>
    <div class="space-y-6 text-[15px] leading-relaxed text-slate-300">
        <h2 class="font-display text-2xl font-bold text-white">Контакты поддержки</h2>

        <p>
            Мы отвечаем на обращения ежедневно. Среднее время ответа — в течение нескольких часов,
            максимум — 24 часа.
        </p>

        <div class="grid gap-4 sm:grid-cols-2">
            <a
                :href="`mailto:${support.email}`"
                class="rounded-xl border border-slate-700 hover:border-blue-500 transition-colors p-5 block"
            >
                <p class="text-xs uppercase tracking-wider text-slate-500 font-semibold">Email</p>
                <p class="mt-2 text-lg font-semibold text-white">{{ support.email }}</p>
                <p class="mt-1 text-sm text-slate-400">Вопросы по оплате, подписке и настройке</p>
            </a>

            <a
                v-if="support.telegram"
                :href="`https://t.me/${String(support.telegram).replace('@', '')}`"
                target="_blank"
                rel="noopener"
                class="rounded-xl border border-slate-700 hover:border-blue-500 transition-colors p-5 block"
            >
                <p class="text-xs uppercase tracking-wider text-slate-500 font-semibold">Telegram</p>
                <p class="mt-2 text-lg font-semibold text-white">{{ support.telegram }}</p>
                <p class="mt-1 text-sm text-slate-400">Оперативные вопросы</p>
            </a>
        </div>

        <div class="rounded-xl border border-slate-800 bg-slate-900/50 p-5 text-sm text-slate-400">
            <p class="font-semibold text-slate-300 mb-2">Что указать в обращении, чтобы мы помогли быстрее:</p>
            <ul class="list-disc pl-5 space-y-1">
                <li>email, на который зарегистрирован аккаунт;</li>
                <li>суть проблемы и когда она возникла;</li>
                <li>для вопросов по оплате — дату и сумму платежа.</li>
            </ul>
        </div>

        <p v-if="support.operator" class="text-sm text-slate-500">
            {{ support.operator }}<span v-if="support.operator_details"> · {{ support.operator_details }}</span>
        </p>
    </div>
</template>
