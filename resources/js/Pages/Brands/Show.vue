<template>
    <div style="background:#f7f0e5; min-height:100vh; padding-bottom:48px">
        <SeoHead :title="pick(brand, 'name')" :image="brand.image_url" />

        <div style="background:linear-gradient(135deg,#76543D,#8d6b51); padding:40px 16px 50px">
            <div style="display:flex; align-items:center; justify-content:center; gap:16px; flex-wrap:wrap">
                <div style="width:64px; height:64px; background:rgba(255,255,255,0.15); border-radius:50%; display:flex; align-items:center; justify-content:center; overflow:hidden">
                    <img v-if="brand.image_url && !brand.image_url.includes('no-image')" :src="brand.image_url" style="width:100%; height:100%; object-fit:contain; padding:8px" @error="onImgError" />
                    <span v-else style="font-size:24px; font-weight:800; color:white">{{ brand.name_en?.charAt(0) }}</span>
                </div>
                <div class="text-center">
                    <h1 class="text-white font-weight-bold" style="font-size:28px">{{ brand.name }}</h1>
                    <p style="color:rgba(255,255,255,0.75); font-size:14px">{{ products.total }} {{ t('products_count') }}</p>
                </div>
            </div>
        </div>

        <div style="padding:32px 16px; max-width:1400px; margin:0 auto">
            <v-row v-if="products.data?.length">
                <v-col v-for="product in products.data" :key="product.id" cols="6" sm="4" md="3" lg="2">
                    <ProductCard :item="product" @quick-view="openQuickView" />
                </v-col>
            </v-row>

            <div v-else style="text-align:center; padding:64px 0">
                <v-icon size="64" color="grey-lighten-1">mdi-shopping-outline</v-icon>
                <p style="margin-top:12px; color:#9ca3af">{{ t('no_products_brand') }}</p>
            </div>

            <div class="d-flex justify-center mt-8" v-if="products.last_page > 1">
                <v-pagination v-model="page" :length="products.last_page" total-visible="5" rounded="lg" @update:model-value="goto" />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, inject } from 'vue';
import { router } from '@inertiajs/vue3';
import ProductCard from '../../components/Shared/ProductCard.vue';
import SeoHead from '../../components/Shared/SeoHead.vue';
import { useLocale } from '../../composables/useLocale';

const { t, pick } = useLocale();
const props = defineProps({ brand: Object, products: Object });
const Emitter = inject('Emitter');
const page = ref(props.products.current_page);

function goto(val) { router.visit(`/brands/${props.brand.id}?page=${val}`, { preserveState: true, preserveScroll: true }); }
function openQuickView(product) { Emitter.emit('openQuickView', product); }

// Local placeholder (Vanilla Latte) shown when the brand logo fails to load.
const IMG_FALLBACK = 'data:image/svg+xml;utf8,' + encodeURIComponent(
    `<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200"><rect width="200" height="200" fill="#F7F0E5"/><g fill="none" stroke="#76543D" stroke-width="6"><path d="M55 75 L100 55 L145 75 L145 130 L100 150 L55 130 Z"/><path d="M55 75 L100 95 L145 75 M100 95 L100 150"/></g></svg>`
);

function onImgError(e) {
    if (e.target.src !== IMG_FALLBACK) e.target.src = IMG_FALLBACK;
}
watch(() => props.products.current_page, (val) => page.value = val);
</script>
