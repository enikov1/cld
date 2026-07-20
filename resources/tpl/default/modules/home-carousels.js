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
    if (root._lsSyncingNav) return;
    root._lsSyncingNav = true;

    try {
        if (typeof swiper.checkOverflow === 'function') {
            swiper.checkOverflow();
        }

        var locked = !!swiper.isLocked;
        root.classList.toggle('carou-content--no-scroll', locked);
        root.classList.toggle('carou-content--at-start', !!swiper.isBeginning);
        root.classList.toggle('carou-content--at-end', !!swiper.isEnd);

        swiper.allowTouchMove = !locked;
        if (locked && swiper.translate !== 0 && typeof swiper.setTranslate === 'function') {
            swiper.setTranslate(0);
        }
    } finally {
        root._lsSyncingNav = false;
    }
}

/**
 * @param {HTMLElement} root
 * @param {number} spaceBetween
 */
function buildSwiperOptions(root, spaceBetween) {
    var prev = root.querySelector('.carou-nav--prev');
    var next = root.querySelector('.carou-nav--next');
    var type = root.getAttribute('data-carou-type') || 'series';
    var isCollections = type === 'collections';

    /** @type {import('swiper').SwiperOptions} */
    var options = {
        modules: [FreeMode, Navigation, Mousewheel],
        spaceBetween: spaceBetween,
        speed: 450,
        watchOverflow: true,
        resistanceRatio: 0.65,
        navigation: {
            prevEl: prev || null,
            nextEl: next || null,
            disabledClass: 'is-disabled',
        },
        mousewheel: {
            forceToAxis: true,
            releaseOnEdges: true,
        },
        on: {
            init: function (instance) {
                syncNavState(instance, root);
                window.requestAnimationFrame(function () {
                    instance.update();
                    syncNavState(instance, root);
                });
            },
            lock: function (instance) {
                syncNavState(instance, root);
            },
            unlock: function (instance) {
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
    };

    if (isCollections) {
        // ПК: 2 карточки, мобильные: 1 — ширину считает Swiper
        options.slidesPerView = 1;
        options.breakpoints = {
            768: {
                slidesPerView: 2,
            },
        };
        options.freeMode = false;
    } else {
        options.slidesPerView = 'auto';
        options.freeMode = {
            enabled: true,
            momentum: true,
            momentumRatio: 0.85,
            momentumVelocityRatio: 0.9,
            momentumBounce: false,
            sticky: false,
        };
    }

    return options;
}

/**
 * @param {HTMLElement} root
 */
function bindCarousel(root) {
    var track = root.querySelector('.carou-track');
    if (!track) return;

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

    var spaceBetween = 10;
    var spaceAttr = root.getAttribute('data-carou-space');
    if (spaceAttr) {
        var parsedSpace = parseInt(spaceAttr, 10);
        if (!isNaN(parsedSpace)) spaceBetween = parsedSpace;
    }

    var swiper = new Swiper(root, buildSwiperOptions(root, spaceBetween));
    root.lsSwiper = swiper;

    track.querySelectorAll('img').forEach(function (img) {
        if (img.complete) return;
        img.addEventListener('load', function () {
            swiper.update();
            syncNavState(swiper, root);
        }, { once: true });
    });
}

export function initHomeCarousels() {
    document.querySelectorAll('[data-carou]').forEach(bindCarousel);
}

window.lsInitHomeCarousels = initHomeCarousels;
