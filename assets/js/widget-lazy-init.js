/**
 * Feather Widget Lazy Init.
 *
 * IntersectionObserver-driven initialization for Elementor widgets that
 * carry the heaviest boot cost: Swiper carousels, animated counters,
 * YouTube/Vimeo iframes, and entrance animations.
 *
 * Above-the-fold widgets boot immediately; everything else waits until
 * its section is within 200 px of the viewport. Browsers without
 * IntersectionObserver get Elementor's stock init, unchanged.
 *
 * Loaded with `defer`, so it runs after the parser has built the DOM and
 * after Elementor's own bundle has registered `elementorFrontend`.
 */
( function () {
	'use strict';

	const config = {
		// Start initializing 200 px before the section enters the viewport so
		// fast scrolls don't reveal an un-initialized widget.
		rootMargin: '200px 0px',
		threshold: 0,
	};

	function supportsIO() {
		return (
			'IntersectionObserver' in window &&
			'IntersectionObserverEntry' in window &&
			'intersectionRatio' in window.IntersectionObserverEntry.prototype
		);
	}

	function widgetType( el ) {
		return el.dataset.widgetType || '';
	}

	function elementorFrontend() {
		return typeof window.elementorFrontend !== 'undefined' ? window.elementorFrontend : null;
	}

	/**
	 * Hand a section back to Elementor's own init pipeline.
	 *
	 * Prefer `elementsHandler.runReadyTrigger` (Elementor 3.5+) — it's the
	 * same entry point Elementor uses internally and respects every widget
	 * handler registered through the public API. Fall back to the legacy
	 * jQuery event for older installs.
	 */
	function initElement( el ) {
		const frontend = elementorFrontend();
		if ( ! frontend ) {
			return;
		}

		if ( frontend.elementsHandler && typeof frontend.elementsHandler.runReadyTrigger === 'function' ) {
			try {
				frontend.elementsHandler.runReadyTrigger( el );
				return;
			} catch ( e ) {
				// Fall through to jQuery fallback.
			}
		}

		if ( typeof window.jQuery !== 'undefined' ) {
			const $el = window.jQuery( el );
			$el.trigger( 'elementor/frontend/init' );
			$el.find( '.elementor-widget' ).each( function () {
				const $w = window.jQuery( this );
				const type = $w.data( 'widget_type' );
				if ( type ) {
					$w.trigger( 'elementor/frontend/widget-init', [ type ] );
				}
			} );
		}
	}

	/**
	 * Swiper carousels: instantiate from Elementor's data-attribute config
	 * the moment the carousel container intersects the viewport. Skipped
	 * cleanly if Swiper is already attached or the library is absent.
	 */
	function initSwiper( carouselEl ) {
		if ( carouselEl.swiper || typeof window.Swiper === 'undefined' ) {
			return;
		}

		let cfg = {};
		const raw =
			carouselEl.dataset.swiperOptions ||
			( carouselEl.closest( '[data-swiper-options]' ) || {} ).dataset?.swiperOptions;
		if ( raw ) {
			try {
				cfg = JSON.parse( raw );
			} catch ( e ) {
				cfg = {};
			}
		}
		cfg.lazy = Object.assign( { loadPrevNext: true, loadPrevNextAmount: 2 }, cfg.lazy || {} );

		carouselEl._featherSwiper = new window.Swiper( carouselEl, cfg );
	}

	/**
	 * Counter widgets: run the count animation only once the user can see
	 * it. Running off-screen wastes CPU and the visual payoff lands while
	 * the user is looking somewhere else.
	 */
	function initCounter( widget ) {
		if ( widget.dataset.featherCounted === '1' ) {
			return;
		}
		widget.dataset.featherCounted = '1';

		const numberEl = widget.querySelector( '.elementor-counter-number' );
		if ( ! numberEl ) {
			return;
		}

		const target = parseFloat( numberEl.dataset.toValue || '0' );
		const start = parseFloat( numberEl.dataset.fromValue || '0' );
		const duration = parseInt( widget.dataset.duration || '2000', 10 );
		const prefix = numberEl.dataset.prefix || '';
		const suffix = numberEl.dataset.suffix || '';
		const delimiter = numberEl.dataset.delimiter || '';

		const formatNumber = ( num ) => {
			const rounded = Math.round( num );
			if ( ! delimiter ) {
				return String( rounded );
			}
			return String( rounded ).replace( /\B(?=(\d{3})+(?!\d))/g, delimiter );
		};
		const ease = ( t ) => t * ( 2 - t );
		const t0 = performance.now();

		function tick( now ) {
			const progress = Math.min( ( now - t0 ) / duration, 1 );
			numberEl.textContent = prefix + formatNumber( start + ( target - start ) * ease( progress ) ) + suffix;
			if ( progress < 1 ) {
				requestAnimationFrame( tick );
			}
		}
		requestAnimationFrame( tick );
	}

	/**
	 * Video widgets: swap the placeholder for the real iframe only on
	 * intersection. A YouTube iframe alone fetches ~540 KB the moment it
	 * appears in the DOM, regardless of `loading="lazy"`.
	 */
	function initVideo( widget ) {
		const wrapper = widget.querySelector( '.elementor-wrapper' );
		if ( ! wrapper || wrapper.querySelector( 'iframe' ) ) {
			return;
		}
		const placeholder = widget.querySelector( '[data-feather-video-src]' );
		if ( ! placeholder ) {
			initElement( widget );
			return;
		}
		const src = placeholder.dataset.featherVideoSrc;
		if ( ! src ) {
			return;
		}

		const iframe = document.createElement( 'iframe' );
		iframe.src = src;
		iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
		iframe.allowFullscreen = true;
		iframe.loading = 'lazy';
		iframe.style.cssText = 'width:100%;height:100%;border:0;';
		placeholder.replaceWith( iframe );
	}

	function revealAnimated( el ) {
		const raw = el.dataset.settings;
		if ( ! raw ) {
			el.classList.remove( 'elementor-invisible' );
			return;
		}
		let settings = {};
		try {
			settings = JSON.parse( raw );
		} catch ( e ) {
			el.classList.remove( 'elementor-invisible' );
			return;
		}
		const animationClass = settings._animation || settings.animation || '';
		const delay = parseInt( settings._animation_delay || settings.animation_delay || '0', 10 );

		if ( ! animationClass ) {
			el.classList.remove( 'elementor-invisible' );
			return;
		}
		setTimeout( () => {
			el.classList.remove( 'elementor-invisible' );
			el.classList.add( animationClass, 'animated' );
		}, delay );
	}

	function bootSection( section ) {
		const widgets = section.querySelectorAll( '.elementor-widget' );
		widgets.forEach( ( widget ) => {
			const type = widgetType( widget );

			if ( type.includes( 'image-carousel' ) || type.includes( 'media-carousel' ) ) {
				const swiperEl = widget.querySelector( '.swiper, .swiper-container' );
				if ( swiperEl ) {
					initSwiper( swiperEl );
					return;
				}
			}
			if ( type.includes( 'counter' ) ) {
				initCounter( widget );
				return;
			}
			if ( type.includes( 'video' ) ) {
				initVideo( widget );
				return;
			}
			initElement( widget );
		} );
	}

	function isAboveFold( el ) {
		const rect = el.getBoundingClientRect();
		return rect.top < window.innerHeight && rect.bottom > 0;
	}

	function start() {
		if ( ! elementorFrontend() ) {
			return;
		}
		if ( ! supportsIO() ) {
			return;
		}

		const sectionObserver = new IntersectionObserver(
			( entries ) => {
				entries.forEach( ( entry ) => {
					if ( ! entry.isIntersecting ) {
						return;
					}
					sectionObserver.unobserve( entry.target );
					bootSection( entry.target );
				} );
			},
			config
		);

		const animObserver = new IntersectionObserver(
			( entries ) => {
				entries.forEach( ( entry ) => {
					if ( ! entry.isIntersecting ) {
						return;
					}
					animObserver.unobserve( entry.target );
					revealAnimated( entry.target );
				} );
			},
			{ rootMargin: '0px', threshold: 0.1 }
		);

		const sections = document.querySelectorAll( '.elementor-section, .e-con, .elementor-top-section' );
		sections.forEach( ( section ) => {
			if ( isAboveFold( section ) ) {
				bootSection( section );
			} else {
				sectionObserver.observe( section );
			}
		} );

		document.querySelectorAll( '.elementor-invisible' ).forEach( ( el ) => {
			if ( isAboveFold( el ) ) {
				revealAnimated( el );
			} else {
				animObserver.observe( el );
			}
		} );

		window.addEventListener( 'pagehide', () => {
			sectionObserver.disconnect();
			animObserver.disconnect();
			document.querySelectorAll( '.swiper, .swiper-container' ).forEach( ( el ) => {
				if ( el._featherSwiper && typeof el._featherSwiper.destroy === 'function' ) {
					el._featherSwiper.destroy( true, true );
				}
			} );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
} )();
