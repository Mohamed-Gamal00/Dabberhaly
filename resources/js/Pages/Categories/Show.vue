<template>
    <div style="background:#f7f0e5; min-height:100vh; padding-bottom:48px">
        <SeoHead
            :title="pick(category, 'name')"
            :description="category.description || categoryMetaDescription"
            :image="category.image_url"
        />

        <!-- Header -->
        <div style="background:linear-gradient(135deg,#76543D,#8d6b51); padding:40px 16px 50px">
            <h1 class="text-white font-weight-bold text-center" style="font-size:28px">
                {{ pick(category, 'name') }}
            </h1>
            <p class="text-center mt-2" style="color:rgba(255,255,255,0.75); font-size:14px">
                {{ products.total }} {{ t('products_in_category') }}
            </p>
        </div>

        <div style="padding:32px 16px; max-width:1400px; margin:0 auto">

            <!-- Empty state -->
            <div v-if="!products.data?.length" style="text-align:center; padding:64px 0">
                <v-icon size="64" color="grey-lighten-1">mdi-shopping-outline</v-icon>
                <p style="margin-top:12px; color:#9ca3af">{{ t('no_products_in_category') }}</p>
                <v-btn color="primary" rounded="lg" href="/products" class="mt-4" style="text-transform:none">{{ t('browse_all_products') }}</v-btn>
            </div>

            <!-- Products grid -->
            <v-row v-else>
                <v-col
                    v-for="product in products.data"
                    :key="product.id"
                    cols="6"
                    sm="4"
                    md="3"
                    lg="2"
                >
                    <ProductCard :item="product" @quick-view="openQuickView" />
                </v-col>
            </v-row>

            <!-- Pagination -->
            <div class="d-flex justify-center mt-8" v-if="products.last_page > 1">
                <v-pagination
                    v-model="page"
                    :length="products.last_page"
                    total-visible="5"
                    rounded="lg"
                    @update:model-value="goto"
                />
            </div>
        </div>
    </div>
</template>

<script setup>
import { inject, computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import ProductCard from '../../components/Shared/ProductCard.vue';
import SeoHead from '../../components/Shared/SeoHead.vue';
import { useLocale } from '../../composables/useLocale';
const { t, pick } = useLocale();
const props = defineProps({ category: Object, products: Object });

// t() has no interpolation, so fill the {count}/{name} placeholders manually.
const categoryMetaDescription = computed(() =>
    t('category_meta_description')
        .replace('{count}', String(props.products?.total ?? 0))
        .replace('{name}', pick(props.category, 'name'))
);

const page = ref(props.products.current_page);
watch(() => props.products.current_page, (val) => page.value = val);
function goto(val) {
    router.get(`/categories/${props.category.slug}?page=${val}`, {}, { preserveState: true, preserveScroll: true });
}

const Emitter = inject('Emitter');
function openQuickView(product) {
    Emitter.emit('openQuickView', product);
}
</script>
