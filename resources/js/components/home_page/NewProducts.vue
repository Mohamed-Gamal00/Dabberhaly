<!-- resources/js/Components/home_page/NewProducts.vue -->
<template>
    <div class="new-products pt-15" :style="{ backgroundColor: bgColor }">
        <!-- Title -->
        <div class="title px-5 d-flex align-center justify-space-between">
            <h2 class="section-title">{{ t(title) }}</h2>
            <Link :href="viewAllHref" class="shop-all-link">
                {{ t('shop_all') }}
            </Link>
        </div>

        <v-container fluid>
            <v-row>
                <!-- Loading state -->
                <v-col
                    cols="7"
                    class="pt-14"
                    v-if="!products || !products.length"
                >
                    <v-row>
                        <v-col cols="4" v-for="num in 3" :key="num">
                            <v-skeleton-loader type="image,article,button" />
                        </v-col>
                    </v-row>
                </v-col>

                <!-- Products grid -->
                <v-col cols="12" lg="7" sm="12" md="7" class="pt-14" v-else>
                    <Swiper
                        :modules="modules"
                        :pagination="{
                            el: '.swiper-pagination',
                            clickable: true,
                        }"
                        :breakpoints="{
                            0: { slidesPerView: 2, spaceBetween: 10 },
                            600: { slidesPerView: 2, spaceBetween: 15 },
                            960: { slidesPerView: 3, spaceBetween: 25 },
                            1280: { slidesPerView: 4, spaceBetween: 35 },
                        }"
                        :space-between="20"
                        class="pb-9"
                    >
                        <swiper-slide v-for="item in products" :key="item.id">
                            <ProductCard
                                :item="item"
                                @quick-view="openQuickView"
                            />
                        </swiper-slide>

                        <div class="swiper-pagination"></div>
                    </Swiper>
                </v-col>

                <!-- Banner Image (Dynamic) -->
                <v-col cols="12" lg="5" sm="12" md="6" v-if="bannerImage">
                    <div class="banner-wrapper">
                        <img
                            :src="bannerImage"
                            class="banner-image"
                            alt="New Products Banner"
                            @error="onImgError"
                        />
                    </div>
                </v-col>
            </v-row>
        </v-container>
    </div>
</template>

<script setup>
import ProductCard from "../../components/Shared/ProductCard.vue";
import { Link } from "@inertiajs/vue3";
import { Swiper, SwiperSlide } from "swiper/vue";
import { Pagination } from "swiper/modules";
import "swiper/css";
import "swiper/css/pagination";
import { inject, computed } from "vue";
import { usePage } from "@inertiajs/vue3";
import { useLocale } from "../../composables/useLocale";

const props = defineProps({
    products: Array,
    title: {
        type: String,
        default: 'all_products',
    },
    viewAllHref: {
        type: String,
        default: '/products',
    },
    bgColor: {
        type: String,
        default: '#F7F0E5' // Vanilla Cream fallback
    }
});
const Emitter = inject("Emitter");
const { t } = useLocale();

// Get banner image from page props
const bannerImage = computed(() => usePage().props.newProductsBanner);

// Local placeholder (Vanilla Latte) shown when the banner image fails to load.
const IMG_FALLBACK = 'data:image/svg+xml;utf8,' + encodeURIComponent(
    `<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200"><rect width="200" height="200" fill="#F7F0E5"/><g fill="none" stroke="#76543D" stroke-width="6"><path d="M55 75 L100 55 L145 75 L145 130 L100 150 L55 130 Z"/><path d="M55 75 L100 95 L145 75 M100 95 L100 150"/></g></svg>`
);

function onImgError(e) {
    if (e.target.src !== IMG_FALLBACK) e.target.src = IMG_FALLBACK;
}

function openQuickView(product) { Emitter.emit("openQuickView", product); }
const modules = [Pagination];
</script>

<style lang="scss" scoped>
.new-products {
    .section-title {
        font-weight: 900;
        font-size: 30px;
        color: #302F2B;
        margin: 0;
    }

    .shop-all-link {
        font-size: 14px;
        font-weight: 600;
        color: #76543D;
        text-decoration: none;
        transition: color 0.2s;
        position: relative;
        padding-bottom: 2px;

        &::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: #C69763;
            transition: width 0.3s ease;
        }

        &:hover {
            color: #C69763;

            &::after {
                width: 100%;
            }
        }
    }

    .banner-wrapper {
        position: relative;
        overflow: hidden;
        border-radius: 12px;
        height: 100%;
        min-height: 300px;
    }

    .banner-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;

        &:hover {
            transform: scale(1.02);
        }
    }

    .img-parent:hover {
        .quick-view-btn {
            opacity: 1 !important;
        }
    }

    .swiper-pagination-bullet {
        width: 10px;
        height: 10px;
        background: #D7CDBD;
        opacity: 0.5;
    }

    .swiper-pagination-bullet-active {
        background: #76543D;
        opacity: 1;
    }
}

/* Responsive adjustments */
@media (max-width: 599px) {
    .section-title {
        font-size: 22px;
    }

    .shop-all-link {
        font-size: 12px;
    }

    .banner-wrapper {
        min-height: 200px;
    }
}

@media (min-width: 600px) and (max-width: 959px) {
    .section-title {
        font-size: 26px;
    }

    .banner-wrapper {
        min-height: 250px;
    }
}
</style>
