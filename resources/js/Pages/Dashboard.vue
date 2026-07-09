<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'
import { useT } from '@/composables/useT'

const { t, locale } = useT()

const props = defineProps({
    wallet: Object,
    active_subscription: Object,
    vpn_key: Object,
    prices: Object,
})

const buyForm = useForm({ months: 1 })
const buy = (months) => { buyForm.months = months; buyForm.post(route('subscriptions.store')) }

const dateLocale = computed(() => locale.value === 'en' ? 'en-US' : 'ru-RU')
import { useMoney } from '@/composables/useMoney'
const { money: fmtR } = useMoney()
const fmtDate = (d) => d ? new Date(d).toLocaleDateString(dateLocale.value, { day: '2-digit', month: 'long', year: 'numeric' }) : '—'

const daysLeft = computed(() => {
    if (!props.active_subscription?.ends_at) return null
    const ms = new Date(props.active_subscription.ends_at) - new Date()
    return Math.max(0, Math.floor(ms / (1000 * 60 * 60 * 24)))
})
</script>

<template>
    <Head :title="t('dashboard.title')" />
    <AuthenticatedLayout>
        <template #header>
            <h1 class="font-display text-2xl font-bold text-white">{{ t('dashboard.header_title') }}</h1>
            <p class="text-sm text-slate-400 mt-1">{{ t('dashboard.header_sub') }}</p>
        </template>

        <div class="py-8 grid grid-cols-1 lg:grid-cols-3 gap-5">

            <!-- Subscription card -->
            <article class="lg:col-span-2 rounded-2xl border border-slate-800 bg-gradient-to-br from-slate-900/80 to-slate-950 p-7 relative overflow-hidden">
                <div class="pointer-events-none absolute -top-24 -right-24 w-72 h-72 bg-blue-500/15 blur-3xl rounded-full"></div>

                <p class="text-xs uppercase tracking-wider text-slate-400 font-semibold">{{ t('dashboard.sub_label') }}</p>

                <template v-if="active_subscription">
                    <div class="mt-2 flex items-baseline gap-3">
                        <h2 class="font-display text-3xl font-bold text-white">{{ t('dashboard.sub_active') }}</h2>
                        <span class="text-xs px-2 py-1 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                            {{ t('common.days_left', { n: daysLeft }) }}
                        </span>
                    </div>
                    <p class="mt-2 text-slate-300">
                        {{ t('dashboard.sub_until') }} <span class="text-white font-medium">{{ fmtDate(active_subscription.ends_at) }}</span>
                    </p>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ t('dashboard.sub_paid_for', { months: active_subscription.months, price: fmtR(active_subscription.price_kopecks) }) }}
                    </p>
                    <div class="mt-6 flex flex-wrap gap-2">
                        <Link href="/keys" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold transition-colors">
                            {{ t('dashboard.sub_manage_key') }}
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-7 7 7-7 7"/></svg>
                        </Link>
                        <Link href="/pricing" class="px-4 py-2 rounded-lg border border-slate-700 hover:border-slate-500 text-slate-200 text-sm transition-colors">
                            {{ t('dashboard.sub_renew') }}
                        </Link>
                    </div>
                </template>

                <template v-else>
                    <div class="mt-2">
                        <h2 class="font-display text-3xl font-bold text-white">{{ t('dashboard.sub_none_title') }}</h2>
                        <p class="mt-2 text-slate-400">{{ t('dashboard.sub_none_sub') }}</p>
                    </div>
                    <div class="mt-6 flex flex-wrap gap-2">
                        <button
                            v-for="(price, months) in prices"
                            :key="months"
                            type="button"
                            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg border border-blue-500/40 hover:border-blue-400 bg-blue-500/10 hover:bg-blue-500/15 text-blue-200 hover:text-white text-sm font-medium transition-colors"
                            :disabled="buyForm.processing"
                            @click="buy(Number(months))"
                        >
                            <span>{{ months }} {{ t('common.months_short') }}</span>
                            <span class="text-blue-300/80">·</span>
                            <span class="font-semibold text-white">{{ fmtR(price) }}</span>
                        </button>
                    </div>
                </template>
            </article>

            <!-- Wallet card -->
            <article class="rounded-2xl border border-slate-800 bg-gradient-to-br from-slate-900/80 to-slate-950 p-7">
                <p class="text-xs uppercase tracking-wider text-slate-400 font-semibold">{{ t('dashboard.wallet_label') }}</p>
                <div class="mt-2 flex items-baseline gap-2">
                    <h2 class="font-display text-3xl font-bold text-white">{{ fmtR(wallet.balance_kopecks) }}</h2>
                </div>
                <p class="mt-2 text-sm" :class="wallet.auto_renew ? 'text-emerald-400' : 'text-slate-400'">
                    <svg v-if="wallet.auto_renew" class="inline w-4 h-4 -mt-0.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0z" clip-rule="evenodd"/></svg>
                    {{ wallet.auto_renew ? t('dashboard.wallet_auto_on') : t('dashboard.wallet_auto_off') }}
                </p>
                <Link href="/wallet" class="mt-6 inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-100 text-sm font-medium transition-colors">
                    {{ t('dashboard.wallet_topup_history') }}
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-7 7 7-7 7"/></svg>
                </Link>
            </article>

            <!-- VPN key card (full width) -->
            <article class="lg:col-span-3 rounded-2xl border border-slate-800 bg-gradient-to-br from-slate-900/80 to-slate-950 p-7">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <p class="text-xs uppercase tracking-wider text-slate-400 font-semibold">{{ t('dashboard.key_label') }}</p>
                        <template v-if="vpn_key">
                            <p class="mt-2 font-display text-2xl font-semibold text-white">{{ vpn_key.name }}</p>
                            <p class="text-sm text-slate-400 mt-1">{{ t('dashboard.server_short', { id: vpn_key.panel_server_id }) }}</p>
                        </template>
                        <template v-else>
                            <p class="mt-2 font-display text-2xl font-semibold text-white">{{ t('dashboard.key_none_title') }}</p>
                            <p class="text-sm text-slate-400 mt-1">{{ t('dashboard.key_none_sub') }}</p>
                        </template>
                    </div>
                    <Link
                        href="/keys"
                        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-orange-500 hover:bg-orange-400 text-white text-sm font-semibold shadow-lg shadow-orange-500/20 transition-colors"
                    >
                        {{ vpn_key ? t('dashboard.key_open') : t('dashboard.key_choose_location') }}
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-7 7 7-7 7"/></svg>
                    </Link>
                </div>
            </article>
        </div>
    </AuthenticatedLayout>
</template>
