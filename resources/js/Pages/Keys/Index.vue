<script setup>
import { ref, computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { useT } from '@/composables/useT'

const { t, locale } = useT()

const props = defineProps({
    has_active_subscription: Boolean,
    subscription_ends_at: { type: String, default: null },
    key: { type: Object, default: null },
    locations: { type: Array, default: () => [] },
})

const issueForm = useForm({ server_id: null })
const changeForm = useForm({ server_id: null })

const issue = (id) => { issueForm.server_id = id; issueForm.post(route('keys.store'), { preserveScroll: true }) }
const changeLocation = (id) => {
    if (!props.key) return
    if (id === props.key.panel_server_id) return
    if (!confirm(t('keys.change_confirm'))) return
    changeForm.server_id = id
    changeForm.post(route('keys.change-location', { key: props.key.id }), { preserveScroll: true })
}

const showQr = ref(false)
const qrSrc = computed(() => props.key ? route('keys.qr', { key: props.key.id }) : '')

const dateLocale = computed(() => locale.value === 'en' ? 'en-US' : 'ru-RU')
const fmtDate = (s) => s ? new Date(s).toLocaleDateString(dateLocale.value, { day: '2-digit', month: 'long', year: 'numeric' }) : '—'
const currentLocationName = computed(() =>
    props.key ? (props.locations.find(l => l.id === props.key.panel_server_id)?.name || t('dashboard.server_short', { id: props.key.panel_server_id })) : ''
)
</script>

<template>
    <Head :title="t('keys.title')" />
    <AuthenticatedLayout>
        <template #header>
            <h1 class="font-display text-2xl font-bold text-white">{{ t('keys.header_title') }}</h1>
            <p class="text-sm text-slate-400 mt-1">{{ t('keys.header_sub') }}</p>
        </template>

        <div class="py-8 space-y-5">

            <!-- no subscription -->
            <article v-if="!has_active_subscription" class="rounded-2xl border border-amber-500/30 bg-amber-500/5 p-6">
                <div class="flex items-start gap-4">
                    <span class="grid place-items-center w-10 h-10 rounded-lg bg-amber-500/15 border border-amber-500/30 shrink-0">
                        <svg class="w-5 h-5 text-amber-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.7 3.86a2 2 0 0 0-3.4 0z"/></svg>
                    </span>
                    <div>
                        <h2 class="font-display text-lg font-semibold text-white">{{ t('keys.no_sub_title') }}</h2>
                        <p class="mt-1 text-amber-100/80 text-sm">{{ t('keys.no_sub_sub') }}</p>
                        <Link href="/pricing" class="mt-3 inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-900 text-sm font-semibold transition-colors">
                            {{ t('keys.no_sub_cta') }}
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-7 7 7-7 7"/></svg>
                        </Link>
                    </div>
                </div>
            </article>

            <!-- need to choose location -->
            <article v-else-if="!key" class="rounded-2xl border border-slate-800 bg-gradient-to-br from-slate-900/80 to-slate-950 p-7">
                <p class="text-xs uppercase tracking-wider text-slate-400 font-semibold">{{ t('keys.create_eyebrow') }}</p>
                <h2 class="mt-2 font-display text-2xl font-bold text-white">{{ t('keys.create_title') }}</h2>
                <p class="mt-1 text-slate-400 text-sm">
                    {{ t('keys.create_sub', { date: fmtDate(subscription_ends_at) }) }}
                </p>

                <div v-if="locations.length === 0" class="mt-6 rounded-lg border border-rose-500/30 bg-rose-500/5 p-4 text-rose-300 text-sm">
                    {{ t('keys.no_locations') }}
                </div>
                <div v-else class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <button
                        v-for="loc in locations"
                        :key="loc.id"
                        type="button"
                        class="text-left rounded-xl border border-slate-700 hover:border-blue-500 bg-slate-900/40 hover:bg-blue-500/5 p-5 transition-colors group focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                        :disabled="issueForm.processing"
                        @click="issue(loc.id)"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="font-display text-lg font-semibold text-white">{{ loc.name }}</div>
                                <div class="mt-1 text-xs text-slate-500">{{ t('keys.active_clients', { n: loc.clients_count }) }}</div>
                            </div>
                            <svg class="w-5 h-5 text-slate-500 group-hover:text-blue-400 transition-colors" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-7 7 7-7 7"/></svg>
                        </div>
                    </button>
                </div>
                <p v-if="issueForm.errors.server_id" class="mt-3 text-rose-400 text-sm">{{ issueForm.errors.server_id }}</p>
            </article>

            <!-- key exists -->
            <template v-else>
                <article class="rounded-2xl border border-slate-800 bg-gradient-to-br from-slate-900/80 to-slate-950 p-7 relative overflow-hidden">
                    <div class="pointer-events-none absolute -top-24 -right-24 w-72 h-72 bg-emerald-500/15 blur-3xl rounded-full"></div>

                    <div class="flex flex-col lg:flex-row lg:items-start gap-6 lg:gap-8">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                    {{ t('keys.active_badge') }}
                                </span>
                            </div>

                            <h2 class="mt-3 font-display text-2xl font-semibold text-white">{{ key.name }}</h2>
                            <p class="mt-1 text-sm text-slate-400">
                                {{ t('keys.location_label') }}: <span class="text-white font-medium">{{ currentLocationName }}</span>
                                · {{ t('keys.created', { date: new Date(key.created_at).toLocaleDateString(dateLocale) }) }}
                            </p>

                            <div class="mt-6 flex flex-wrap gap-2">
                                <a
                                    :href="route('keys.download', { key: key.id })"
                                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold transition-colors"
                                >
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
                                    {{ t('keys.download') }}
                                </a>
                                <button
                                    type="button"
                                    @click="showQr = !showQr"
                                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg border border-slate-700 hover:border-slate-500 text-slate-200 text-sm transition-colors"
                                >
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path stroke-linecap="round" d="M14 14h3v3m4 0v3M14 21h3"/></svg>
                                    {{ showQr ? t('keys.hide_qr') : t('keys.show_qr') }}
                                </button>
                            </div>
                        </div>

                        <div v-if="showQr" class="lg:w-72 shrink-0">
                            <div class="rounded-xl bg-white p-3 shadow-2xl shadow-blue-950/30">
                                <img :src="qrSrc" alt="QR-код ключа доступа" class="block w-full h-auto rounded" />
                            </div>
                            <p class="mt-3 text-xs text-slate-400 text-center">
                                {{ t('keys.qr_hint') }}
                            </p>
                        </div>
                    </div>
                </article>

                <article class="rounded-2xl border border-slate-800 bg-gradient-to-br from-slate-900/80 to-slate-950 p-7">
                    <h3 class="text-xs uppercase tracking-wider text-slate-400 font-semibold">{{ t('keys.change_label') }}</h3>
                    <p class="mt-1 text-sm text-slate-400">
                        {{ t('keys.change_sub') }}
                    </p>

                    <div v-if="locations.length === 0" class="mt-4 text-rose-400 text-sm">{{ t('keys.no_locations') }}</div>
                    <div v-else class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <button
                            v-for="loc in locations"
                            :key="loc.id"
                            type="button"
                            class="text-left rounded-xl border p-5 transition-colors group focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                            :class="loc.id === key.panel_server_id
                                ? 'border-emerald-500/50 bg-emerald-500/5 cursor-default'
                                : 'border-slate-700 hover:border-blue-500 bg-slate-900/40 hover:bg-blue-500/5'"
                            :disabled="loc.id === key.panel_server_id || changeForm.processing"
                            @click="changeLocation(loc.id)"
                        >
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="font-display text-lg font-semibold text-white">{{ loc.name }}</div>
                                    <div class="mt-1 text-xs"
                                         :class="loc.id === key.panel_server_id ? 'text-emerald-400' : 'text-slate-500'">
                                        {{ loc.id === key.panel_server_id ? t('keys.current_location') : t('keys.active_clients', { n: loc.clients_count }) }}
                                    </div>
                                </div>
                                <svg v-if="loc.id !== key.panel_server_id" class="w-5 h-5 text-slate-500 group-hover:text-blue-400 transition-colors" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-7 7 7-7 7"/></svg>
                            </div>
                        </button>
                    </div>
                    <p v-if="changeForm.errors.server_id" class="mt-3 text-rose-400 text-sm">{{ changeForm.errors.server_id }}</p>
                </article>
            </template>
        </div>
    </AuthenticatedLayout>
</template>
