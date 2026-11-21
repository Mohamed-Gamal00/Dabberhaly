<template>
    <div class="gs-wrap" ref="wrapRef">
        <div class="gs-input-wrap" :class="{ 'gs-open': open }">
            <input
                ref="inputRef"
                v-model="query"
                class="gs-input"
                :placeholder="t('search_placeholder')"
                :aria-label="t('search_placeholder')"
                @input="onInput"
                @focus="open = true"
                @keydown.escape="close"
                @keydown.enter="goToProducts"
            />
            <button v-if="query" class="gs-clear" @click.stop="clear">
                <v-icon size="15" color="grey">mdi-close</v-icon>
            </button>
            <v-icon size="17" color="grey" class="gs-icon">mdi-magnify</v-icon>
        </div>

        <!-- Teleport dropdown to body to avoid z-index issues -->
        <Teleport to="body">
            <div
                v-if="open && query.length >= 2"
                class="gs-dropdown"
                :style="dropdownStyle"
            >
                <div v-if="loading" class="gs-state">
                    <v-progress-circular indeterminate size="18" color="primary" />
                    <span>{{ t('searching') }}</span>
                </div>

                <template v-else-if="results.length">
                    <div class="gs-section">{{ t('products') }}</div>
                    <a
                        v-for="item in results"
                        :key="item.id"
                        :href="`/products/${item.slug}`"
                        class="gs-item"
                        @click="close"
                    >
                        <img :src="item.image_url" class="gs-item-img" alt="" @error="onImgError" />
                        <div class="gs-item-info">
                            <div class="gs-item-name">{{ pick(item, 'name') }}</div>
                            <div class="gs-item-price">
                                {{ Math.ceil(item.discount_price || item.price) }} {{ t('currency_egp') }}
                                <span v-if="item.discount_price && item.discount_price < item.price" class="gs-item-old">
                                    {{ item.price }} {{ t('currency_egp') }}
                                </span>
                            </div>
                        </div>
                        <v-icon size="13" color="grey">{{ isAr ? 'mdi-chevron-left' : 'mdi-chevron-right' }}</v-icon>
                    </a>
                    <a :href="`/products?search=${encodeURIComponent(query)}`" class="gs-view-all" @click="close">
                        {{ t('search_view_all') }} "{{ query }}"
                        <v-icon size="14">{{ isAr ? 'mdi-arrow-left' : 'mdi-arrow-right' }}</v-icon>
                    </a>
                </template>

                <div v-else class="gs-state">
                    <v-icon size="28" color="grey-lighten-1">mdi-magnify-close</v-icon>
                    <span>{{ t('search_no_results') }} "{{ query }}"</span>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { useLocale } from '../../composables/useLocale';
const { t, pick, isAr } = useLocale();

// Local placeholder (Vanilla Latte) shown when a product image fails to load.
const IMG_FALLBACK = 'data:image/svg+xml;utf8,' + encodeURIComponent(
    `<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 120 120"><rect width="120" height="120" fill="#F7F0E5"/><g fill="none" stroke="#76543D" stroke-width="4"><path d="M35 45 L60 32 L85 45 L85 78 L60 91 L35 78 Z"/><path d="M35 45 L60 58 L85 45 M60 58 L60 91"/></g></svg>`
);

function onImgError(e) {
    if (e.target.src !== IMG_FALLBACK) e.target.src = IMG_FALLBACK;
}

const query    = ref('');
const results  = ref([]);
const loading  = ref(false);
const open     = ref(false);
const wrapRef  = ref(null);
const inputRef = ref(null);
const rect     = ref({ top: 0, left: 0, width: 0 });
let debounce   = null;

// Position dropdown under the input
const dropdownStyle = computed(() => ({
    position: 'fixed',
    top:      `${rect.value.top + rect.value.height + 6}px`,
    left:     `${rect.value.left}px`,
    width:    `${Math.max(rect.value.width, 320)}px`,
    zIndex:   99999,
}));

function updateRect() {
    if (wrapRef.value) {
        const r = wrapRef.value.getBoundingClientRect();
        rect.value = { top: r.top, left: r.left, width: r.width, height: r.height };
    }
}

function onInput() {
    updateRect();
    clearTimeout(debounce);
    if (query.value.length < 2) { results.value = []; return; }
    loading.value = true;
    debounce = setTimeout(async () => {
        try {
            const { data } = await axios.get('/search', {
                params: { q: query.value },
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            });
            results.value = data;
        } catch { results.value = []; }
        loading.value = false;
    }, 280);
}

function clear() {
    query.value = '';
    results.value = [];
    inputRef.value?.focus();
}

function close() {
    open.value = false;
}

function goToProducts() {
    if (!query.value.trim()) return;
    close();
    // Navigate to products page with search — same as filter
    router.get('/products', { search: query.value }, { preserveState: false });
}

// Close on outside click
function onClickOutside(e) {
    if (wrapRef.value && !wrapRef.value.contains(e.target)) {
        close();
    }
}

onMounted(() => {
    document.addEventListener('click', onClickOutside);
    window.addEventListener('scroll', updateRect, { passive: true });
    window.addEventListener('resize', updateRect, { passive: true });
});

onUnmounted(() => {
    document.removeEventListener('click', onClickOutside);
    window.removeEventListener('scroll', updateRect);
    window.removeEventListener('resize', updateRect);
});
</script>

<style scoped>
.gs-wrap {
    position: relative;
    width: 100%;
    max-width: 340px;
}

.gs-input-wrap {
    display: flex;
    align-items: center;
    gap: 6px;
    background: rgba(255,255,255,0.15);
    border: 1px solid rgba(255,255,255,0.25);
    border-radius: 10px;
    padding: 7px 12px;
    transition: all 0.2s;
}

.gs-open {
    background: white;
    border-color: white;
    box-shadow: 0 2px 12px rgba(0,0,0,0.15);
}

.gs-input-wrap:focus-within {
    outline: 2px solid #C69763;
    outline-offset: 1px;
}

.gs-input {
    flex: 1;
    border: none;
    outline: none;
    background: transparent;
    font-size: 13px;
    color: rgba(255,255,255,0.9);
    min-width: 0;
}

.gs-open .gs-input { color: #111827; }
.gs-input::placeholder { color: rgba(255,255,255,0.55); }
.gs-open .gs-input::placeholder { color: #9ca3af; }

.gs-clear, .gs-icon {
    background: none;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    padding: 0;
    flex-shrink: 0;
}
</style>

<!-- Dropdown styles are global since it's teleported to body -->
<style>
.gs-dropdown {
    background: white;
    border-radius: 14px;
    box-shadow: 0 12px 40px rgba(0,0,0,0.18);
    overflow: hidden;
    max-height: 420px;
    overflow-y: auto;
    border: 1px solid #e5e7eb;
}

.gs-state {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 20px 16px;
    font-size: 13px;
    color: #6b7280;
    flex-direction: column;
    text-align: center;
}

.gs-section {
    font-size: 10px;
    font-weight: 700;
    color: #77736B;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 10px 14px 4px;
}

.gs-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    text-decoration: none;
    border-bottom: 1px solid #f3f4f6;
    transition: background 0.1s;
}

.gs-item:hover { background: #f8f9fb; }

.gs-item-img {
    width: 42px;
    height: 42px;
    object-fit: cover;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    flex-shrink: 0;
}

.gs-item-info { flex: 1; min-width: 0; }

.gs-item-name {
    font-size: 13px;
    font-weight: 600;
    color: #111827;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.gs-item-price {
    font-size: 12px;
    color: #76543D;
    font-weight: 700;
    margin-top: 2px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.gs-item-old {
    font-size: 11px;
    color: #77736B;
    text-decoration: line-through;
    font-weight: 400;
}

.gs-view-all {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 14px;
    font-size: 13px;
    font-weight: 700;
    color: #76543D;
    text-decoration: none;
    background: #F7F0E5;
    border-top: 1px solid #E6D3BB;
}

.gs-view-all:hover { background: #EFE7D8; }
</style>
