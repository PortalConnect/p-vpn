import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

/**
 * Форматирование денег в валюте пользователя (шарится бэкендом по локали).
 * Все суммы приходят в копейках базовой валюты (RUB).
 *   const { money, currency } = useMoney()
 *   money(20000) // '200 ₽' | '$2.22'
 */
export function useMoney() {
    const page = usePage()
    const currency = computed(() => page.props.currency || { code: 'RUB', symbol: '₽', rate: 1, decimals: 0 })

    const money = (kopecks) => {
        const c = currency.value
        const amount = kopecks / 100 / (c.rate || 1)
        const formatted = amount.toFixed(c.decimals).replace(/\.00$/, '')
        return c.code === 'RUB' ? `${formatted} ${c.symbol}` : `${c.symbol}${formatted}`
    }

    return { money, currency }
}
