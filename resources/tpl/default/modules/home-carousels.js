/**
 * Home carousels — modular Swiper (core + FreeMode + Navigation + Mousewheel only).
 * Built by scripts/build-theme-assets.mjs into assets/home-carousels.js
 */
import Swiper from 'swiper';
import { FreeMode, Mousewheel, Navigation } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/free-mode';

/**
 * @param {HTMLElement} track
 */
function prepareSlides(track) {
    Array.from(track.children).forEach(function (child) {
        if (child.nodeType !== 1) return;
        child.classList.add('swiper-slide');
    });
}

/**
 * @param {import('swiper').Swiper} swiper
 * @param {HTMLElement} root
 */
function syncNavState(swiper, root) {
    var locked = !!swiper.isLocked;
    root.classList.toggle('carou-content--no-scroll', locked);
    root.classList.toggle('carou-content--at-start', !!swiper.isBeginning);
    root.classList.toggle('carou-content--at-end', !!swiper.isEnd);
}

/**
 * @param {HTMLElement} root
 */
function bindCarousel(root) {
    var track = root.querySelector('.carou-track');
    if (!track) return;

    var prev = root.querySelector('.carou-nav--prev');
    var next = root.querySelector('.carou-nav--next');

    prepareSlides(track);
    root.classList.add('swiper');
    track.classList.add('swiper-wrapper');

    var slideCount = track.querySelectorAll(':scope > .swiper-slide').length;

    /** @type {import('swiper').Swiper|undefined} */
    var existing = root.lsSwiper;
    if (existing) {
        if (existing.slides && existing.slides.length === slideCount) {
            existing.update();
            syncNavState(existing, root);
            return;
        }
        existing.destroy(true, false);
        root.lsSwiper = undefined;
    }

    if (slideCount === 0) {
        root.classList.add('carou-content--no-scroll');
        return;
    }

    var swiper = new Swiper(root, {
        modules: [FreeMode, Navigation, Mousewheel],
        slidesPerView: 'auto',
        spaceBetween: 10,
        speed: 450,
        watchOverflow: true,
        resistanceRatio: 0.65,
        freeMode: {
            enabled: true,
            momentum: true,
            momentumRatio: 0.85,
            momentumVelocityRatio: 0.9,
            momentumBounce: false,
            sticky: false,
        },
        mousewheel: {
            forceToAxis: true,
            releaseOnEdges: true,
        },
        navigation: {
            prevEl: prev || null,
            nextEl: next || null,
            disabledClass: 'is-disabled',
        },
        on: {
            init: function (instance) {
                syncNavState(instance, root);
            },
            slideChange: function (instance) {
                syncNavState(instance, root);
            },
            reachBeginning: function (instance) {
                syncNavState(instance, root);
            },
            reachEnd: function (instance) {
                syncNavState(instance, root);
            },
            fromEdge: function (instance) {
                syncNavState(instance, root);
            },
            resize: function (instance) {
                syncNavState(instance, root);
            },
            update: function (instance) {
                syncNavState(instance, root);
            },
        },
    });

    root.lsSwiper = swiper;
}

export function initHomeCarousels() {
    document.querySelectorAll('[data-carou]').forEach(bindCarousel);
}

window.lsInitHomeCarousels = initHomeCarousels;
