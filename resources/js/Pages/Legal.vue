<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import AppLogo from '@/Components/AppLogo.vue'
import LocaleSwitcher from '@/Components/LocaleSwitcher.vue'
import { useT } from '@/composables/useT'
import LegalPrivacy from '@/Pages/Legal/Privacy.vue'
import LegalTerms from '@/Pages/Legal/Terms.vue'
import LegalContacts from '@/Pages/Legal/Contacts.vue'

const { t } = useT()

const props = defineProps({
    section: { type: String, default: 'privacy' },
    support: { type: Object, default: () => ({}) },
})

const tabs = [
    { key: 'privacy', label: () => t('legal.tab_privacy'), component: LegalPrivacy },
    { key: 'terms', label: () => t('legal.tab_terms'), component: LegalTerms },
    { key: 'contacts', label: () => t('legal.tab_contacts'), component: LegalContacts },
]

const open = (key) => router.visit(`/about/${key}`, { preserveScroll: false })
const current = () => tabs.find((tab) => tab.key === props.section) ?? tabs[0]
</script>

<template>
    <Head :title="t('legal.title')" />

    <div class="min-h-screen bg-[#0a0e1a] text-slate-100 antialiased font-body selection:bg-orange-500/30">
        <header class="border-b border-slate-800/60">
            <div class="mx-auto max-w-6xl px-6 py-5 flex items-center justify-between">
                <Link href="/" class="flex items-center gap-2" aria-label="P-Net">
                    <AppLogo :size="36" with-wordmark wordmark-class="text-lg text-white" />
                </Link>
                <div class="flex items-center gap-3">
                    <LocaleSwitcher />
                    <Link
                        href="/login"
                        class="px-4 py-2 text-sm font-semibold rounded-lg bg-white text-slate-900 hover:bg-slate-100 transition-colors"
                    >
                        {{ t('nav.cabinet') }}
                    </Link>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-4xl px-6 py-10">
            <h1 class="font-display text-3xl font-bold text-white">{{ t('legal.title') }}</h1>
            <p class="mt-2 text-sm text-slate-400">{{ t('legal.sub') }}</p>

            <div class="mt-8 flex flex-wrap gap-2">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    class="px-4 py-2 rounded-lg text-sm font-semibold transition-colors border"
                    :class="section === tab.key
                        ? 'border-blue-500 bg-blue-500/10 text-white'
                        : 'border-slate-700 text-slate-300 hover:border-slate-500'"
                    @click="open(tab.key)"
                >
                    {{ tab.label() }}
                </button>
            </div>

            <article class="mt-6 rounded-2xl border border-slate-800 bg-gradient-to-br from-slate-900/80 to-slate-950 p-6 sm:p-10">
                <component :is="current().component" :support="support" />
            </article>

            <p class="mt-6 text-center text-xs text-slate-600">
                {{ t('brand.name') }} — {{ t('brand.tagline') }}
            </p>
        </main>
    </div>
</template>
