<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { useT } from '@/composables/useT'

const { t } = useT()
defineProps({ prices: Object })

const form = useForm({ months: 1 })
const buy = (months) => { form.months = months; form.post(route('subscriptions.store')) }

import { useMoney } from '@/composables/useMoney'
const { money: fmtR } = useMoney()

const perMonth = (months, total) => Math.round(total / months / 100) + ' ' + t('pricing.per_month')

const features = computed(() => [
    t('pricing.feat_1'),
    t('pricing.feat_2'),
    t('pricing.feat_3'),
    t('pricing.feat_4'),
    t('pricing.feat_5'),
    t('pricing.feat_6'),
])

const monthLabel = (n) => n === 1 ? t('pricing.month_singular') : t('pricing.month_genitive')
</script>

<template>
    <Head :title="t('pricing.title')" />
    <AuthenticatedLayout>
        <template #header>
            <h1 class="font-display text-2xl font-bold text-white">{{ t('pricing.header_title') }}</h1>
            <p class="text-sm text-slate-400 mt-1">{{ t('pricing.header_sub') }}</p>
        </template>

        <div class="py-8 space-y-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <article
                    v-for="(price, months) in prices"
                    :key="months"
                    class="relative rounded-2xl border bg-gradient-to-b from-slate-900/80 to-slate-950 p-7 transition-all hover:border-slate-600"
                    :class="Number(months) === 3 ? 'border-blue-500/60 ring-1 ring-blue-500/40' : 'border-slate-800'"
                >
                    <div v-if="Number(months) === 3" class="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-1 rounded-full bg-blue-500 text-white text-xs font-semibold uppercase tracking-wider shadow-lg shadow-blue-500/30">
                        {{ t('pricing.popular_badge') }}
                    </div>

                    <h3 class="font-display text-2xl font-bold text-white">{{ months }} {{ monthLabel(Number(months)) }}</h3>

                    <div class="mt-4 flex items-baseline gap-2">
                        <span class="font-display text-4xl font-bold text-white">{{ fmtR(price) }}</span>
                    </div>
                    <p class="mt-1 text-sm text-slate-400">{{ perMonth(Number(months), price) }}</p>

                    <button
                        type="button"
                        :disabled="form.processing"
                        @click="buy(Number(months))"
                        class="mt-6 w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl font-semibold transition-all"
                        :class="Number(months) === 3
                            ? 'bg-orange-500 hover:bg-orange-400 text-white shadow-lg shadow-orange-500/30'
                            : 'bg-slate-800 hover:bg-slate-700 text-white border border-slate-700'"
                    >
                        {{ t('pricing.buy') }}
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-7 7 7-7 7"/></svg>
                    </button>
                </article>
            </div>

            <section class="rounded-2xl border border-slate-800 bg-gradient-to-b from-slate-900/80 to-slate-950 p-7">
                <h3 class="text-xs uppercase tracking-wider text-slate-400 font-semibold mb-4">{{ t('pricing.included_label') }}</h3>
                <ul class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3">
                    <li v-for="f in features" :key="f" class="flex items-center gap-2 text-slate-200">
                        <svg class="w-4 h-4 text-emerald-400 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0z" clip-rule="evenodd"/>
                        </svg>
                        {{ f }}
                    </li>
                </ul>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
