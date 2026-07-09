<script setup>
import { computed, ref, watch } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { useT } from '@/composables/useT'

const { t, locale } = useT()

const props = defineProps({
    wallet: Object,
    transactions: Array,
    min_topup_rubles: Number,
})

import { useMoney } from '@/composables/useMoney'
const { money: fmtR, currency } = useMoney()

// Пресеты и учёт — в рублях (шлюзы списывают RUB); ввод и подписи — в валюте юзера.
const presets = [200, 600, 1000]
const rate = computed(() => currency.value.rate || 1)
const isRub = computed(() => currency.value.code === 'RUB')
const toDisplay = (rubles) => (isRub.value ? rubles : Math.round((rubles / rate.value) * 100) / 100)
const toRubles = (display) => (isRub.value ? Math.round(display) : Math.round(display * rate.value))

const topupForm = useForm({ amount_rubles: presets[0] })
const customAmount = ref(toDisplay(presets[0]))
const selectPreset = (amount) => {
    topupForm.amount_rubles = amount
    customAmount.value = toDisplay(amount)
}
watch(customAmount, (v) => { topupForm.amount_rubles = toRubles(Number(v) || 0) })

const minDisplay = computed(() =>
    isRub.value ? props.min_topup_rubles : Math.ceil((props.min_topup_rubles / rate.value) * 100) / 100
)

const autoForm = useForm({ auto_renew: props.wallet.auto_renew })

const submitTopup = () => topupForm.post(route('wallet.topup'))
const toggleAutoRenew = () => {
    autoForm.auto_renew = !autoForm.auto_renew
    autoForm.post(route('wallet.auto-renew'))
}

const dateLocale = computed(() => locale.value === 'en' ? 'en-US' : 'ru-RU')

const txMeta = (type) => {
    const tones = {
        topup: 'text-emerald-400',
        subscription_debit: 'text-rose-400',
        refund: 'text-emerald-400',
        bonus: 'text-emerald-400',
        manual_credit: 'text-emerald-400',
        manual_debit: 'text-rose-400',
    }
    return { label: t(`wallet.tx_${type}`), tone: tones[type] || 'text-slate-300' }
}
</script>

<template>
    <Head :title="t('wallet.title')" />
    <AuthenticatedLayout>
        <template #header>
            <h1 class="font-display text-2xl font-bold text-white">{{ t('wallet.header_title') }}</h1>
            <p class="text-sm text-slate-400 mt-1">{{ t('wallet.header_sub') }}</p>
        </template>

        <div class="py-8 grid grid-cols-1 lg:grid-cols-3 gap-5">

            <!-- balance card (large) -->
            <section class="lg:col-span-2 rounded-2xl border border-slate-800 bg-gradient-to-br from-slate-900/80 to-slate-950 p-7 relative overflow-hidden">
                <div class="pointer-events-none absolute -top-24 right-0 w-72 h-72 bg-blue-500/15 blur-3xl rounded-full"></div>

                <p class="text-xs uppercase tracking-wider text-slate-400 font-semibold">{{ t('wallet.balance_label') }}</p>
                <div class="mt-2 flex items-baseline gap-3">
                    <h2 class="font-display text-5xl font-bold text-white tracking-tight">{{ fmtR(wallet.balance_kopecks) }}</h2>
                </div>

                <div class="mt-6 flex flex-wrap items-center gap-3">
                    <button
                        type="button"
                        @click="toggleAutoRenew"
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border transition-colors"
                        :class="wallet.auto_renew
                            ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-300 hover:bg-emerald-500/15'
                            : 'border-slate-700 bg-slate-900/40 text-slate-300 hover:border-slate-500'"
                    >
                        <span class="relative inline-flex w-9 h-5 rounded-full transition-colors"
                              :class="wallet.auto_renew ? 'bg-emerald-500' : 'bg-slate-700'">
                            <span class="absolute top-0.5 left-0.5 w-4 h-4 rounded-full bg-white transition-transform"
                                  :class="wallet.auto_renew ? 'translate-x-4' : ''"></span>
                        </span>
                        <span class="text-sm font-medium">
                            {{ wallet.auto_renew ? t('wallet.auto_on_label') : t('wallet.auto_off_label') }}
                        </span>
                    </button>
                </div>

                <p v-if="wallet.auto_renew" class="mt-3 text-sm text-slate-400">{{ t('wallet.auto_on_hint') }}</p>
                <p v-else class="mt-3 text-sm text-slate-400">{{ t('wallet.auto_off_hint') }}</p>
            </section>

            <!-- top up card -->
            <section class="rounded-2xl border border-slate-800 bg-gradient-to-br from-slate-900/80 to-slate-950 p-7">
                <p class="text-xs uppercase tracking-wider text-slate-400 font-semibold">{{ t('wallet.topup_label') }}</p>
                <form @submit.prevent="submitTopup" class="mt-4 space-y-3">
                    <div class="grid grid-cols-3 gap-2">
                        <button
                            v-for="amount in presets"
                            :key="amount"
                            type="button"
                            class="px-3 py-3 rounded-lg border transition-colors text-center font-semibold"
                            :class="topupForm.amount_rubles === amount
                                ? 'border-blue-500 bg-blue-500/10 text-white'
                                : 'border-slate-700 hover:border-slate-500 text-slate-200'"
                            @click="selectPreset(amount)"
                        >
                            {{ fmtR(amount * 100) }}
                        </button>
                    </div>

                    <div>
                        <label class="block text-xs text-slate-400 mb-1">
                            {{ t('wallet.topup_custom_label', { min: minDisplay, symbol: currency.symbol }) }}
                        </label>
                        <div class="relative">
                            <input
                                v-model.number="customAmount"
                                type="number"
                                inputmode="decimal"
                                :min="minDisplay"
                                :step="isRub ? 1 : 0.01"
                                class="w-full bg-slate-900 border border-slate-700 rounded-lg pl-3 pr-10 py-2.5 text-base text-white placeholder-slate-500 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/40 focus:outline-none tabular-nums"
                            />
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 text-sm pointer-events-none">{{ currency.symbol }}</span>
                        </div>
                        <p v-if="topupForm.errors.amount_rubles" class="mt-1 text-xs text-rose-400">
                            {{ topupForm.errors.amount_rubles }}
                        </p>
                        <p v-if="!isRub" class="mt-1 text-xs text-slate-500">
                            {{ t('wallet.topup_rub_note', { rub: topupForm.amount_rubles + ' ₽' }) }}
                        </p>
                    </div>

                    <button
                        type="submit"
                        :disabled="topupForm.processing"
                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-lg bg-orange-500 hover:bg-orange-400 disabled:opacity-50 disabled:cursor-not-allowed text-white font-semibold shadow-lg shadow-orange-500/20 transition-colors"
                    >
                        {{ t('wallet.topup_submit') }}
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-7 7 7-7 7"/></svg>
                    </button>
                </form>
            </section>

            <!-- transactions -->
            <section class="lg:col-span-3 rounded-2xl border border-slate-800 bg-gradient-to-br from-slate-900/80 to-slate-950 p-7">
                <h3 class="text-xs uppercase tracking-wider text-slate-400 font-semibold mb-4">{{ t('wallet.history_label') }}</h3>

                <div v-if="transactions.length" class="overflow-x-auto -mx-7">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-slate-500 border-b border-slate-800">
                                <th class="px-7 py-3 font-medium">{{ t('wallet.col_date') }}</th>
                                <th class="py-3 font-medium">{{ t('wallet.col_op') }}</th>
                                <th class="py-3 font-medium text-right">{{ t('wallet.col_amount') }}</th>
                                <th class="px-7 py-3 font-medium text-right">{{ t('wallet.col_balance_after') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="tx in transactions"
                                :key="tx.id"
                                class="border-b border-slate-800/50 hover:bg-slate-800/30 transition-colors"
                            >
                                <td class="px-7 py-3 text-slate-400 whitespace-nowrap">
                                    {{ new Date(tx.created_at).toLocaleString(dateLocale, { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }) }}
                                </td>
                                <td class="py-3">
                                    <span class="text-slate-200">{{ txMeta(tx.type).label }}</span>
                                    <div v-if="tx.description" class="text-xs text-slate-500 mt-0.5">{{ tx.description }}</div>
                                </td>
                                <td class="py-3 text-right font-medium tabular-nums" :class="txMeta(tx.type).tone">
                                    {{ tx.amount_kopecks >= 0 ? '+' : '' }}{{ fmtR(tx.amount_kopecks) }}
                                </td>
                                <td class="px-7 py-3 text-right text-slate-300 tabular-nums">{{ fmtR(tx.balance_after_kopecks) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="text-slate-500 text-sm">{{ t('wallet.history_empty') }}</p>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
