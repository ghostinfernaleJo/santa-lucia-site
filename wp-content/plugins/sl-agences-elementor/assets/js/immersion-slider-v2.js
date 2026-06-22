/**
 * JS for Immersion Slider Widget — v2 (robuste)
 * Utilizes GSAP for smooth animations between slides.
 *
 * Correctif v2 : suppression du verrou fragile `isAnimating` et de la
 * dépendance à `currentIndex` pour le slide sortant. goToSlide() est désormais
 * idempotent : il tue les tweens en cours sur les slides et force TOUJOURS un
 * état cohérent (cible = visible + lecture vidéo ; autres = masqués + pause).
 * Cela élimine le bug "onglet actif mais fond/vidéo restent à opacity:0".
 */

document.addEventListener('DOMContentLoaded', () => {
    initImmersionSliders();
});

// Also initialize on Elementor frontend loaded (for preview mode)
window.addEventListener('elementor/frontend/init', () => {
    elementorFrontend.hooks.addAction('frontend/element_ready/sl_immersion_slider.default', function($scope) {
        initImmersionSlider($scope[0].querySelector('.sl-immersion-container'));
    });
});

function initImmersionSliders() {
    const sliders = document.querySelectorAll('.sl-immersion-container');
    sliders.forEach(slider => {
        initImmersionSlider(slider);
    });
}

function initImmersionSlider(container) {
    if (!container) return;

    // Prevent double initialization
    if (container.dataset.initialized === 'true') return;
    container.dataset.initialized = 'true';

    const slides = container.querySelectorAll('.sl-immersion-slide');
    const steps = container.querySelectorAll('.sl-timeline-step');
    const progressBar = container.querySelector('.sl-progress-fill');

    if (!slides.length || !steps.length) return;

    let currentIndex = 0;
    const totalSlides = slides.length;
    let autoPlayTimer;

    const autoplay = container.dataset.autoplay === 'true';
    const delay = parseInt(container.dataset.delay) || 8000;
    const animDuration = parseFloat(container.dataset.animDuration) || 0.8;

    // Helper : éléments texte + boutons d'un slide (dans l'ordre d'apparition)
    function slideElements(slide) {
        const btns = [...slide.querySelectorAll('.sl-immersion-btn')];
        return [
            slide.querySelector('.sl-slide-subtitle'),
            slide.querySelector('.sl-slide-title'),
            ...btns
        ].filter(Boolean);
    }

    // Initialize first slide texts — hide all non-active slides' content
    slides.forEach((slide, i) => {
        const els = slideElements(slide);
        if (i === 0) {
            gsap.set(slide, { opacity: 1 });
            gsap.set(els, { y: 0, opacity: 1 });
        } else {
            gsap.set(slide, { opacity: 0 });
            gsap.set(els, { y: 50, opacity: 0 });
        }
    });

    // Active/désactive un slide de façon ATOMIQUE et idempotente.
    function applySlideState(slide, isTarget) {
        const els = slideElements(slide);
        const vid = slide.querySelector('video');

        // Stoppe tout tween en cours sur ce slide pour éviter les états figés.
        gsap.killTweensOf(slide);
        gsap.killTweensOf(els);

        if (isTarget) {
            slide.classList.add('active');
            gsap.to(slide, { opacity: 1, duration: animDuration, ease: 'power2.inOut' });
            gsap.fromTo(els,
                { y: 50, opacity: 0 },
                { y: 0, opacity: 1, duration: 0.6, delay: 0.35, stagger: 0.12, ease: 'power3.out' }
            );
            if (vid) {
                try {
                    vid.currentTime = 0;
                    const p = vid.play();
                    if (p !== undefined) p.catch(() => {}); // autoplay bloqué : on ignore
                } catch (e) {}
            }
        } else {
            gsap.to(slide, {
                opacity: 0,
                duration: animDuration,
                ease: 'power2.inOut',
                onComplete: () => {
                    slide.classList.remove('active');
                    gsap.set(els, { y: 50, opacity: 0 });
                }
            });
            if (vid) vid.pause();
        }
    }

    function goToSlide(index) {
        if (index === currentIndex) return;
        if (index < 0 || index >= totalSlides) return;

        // État autoritaire sur TOUS les slides (aucune dépendance à un index sortant).
        slides.forEach((slide, i) => applySlideState(slide, i === index));

        // Timeline
        steps.forEach(s => s.classList.remove('active'));
        if (steps[index]) steps[index].classList.add('active');

        // Barre de progression
        if (progressBar) {
            gsap.to(progressBar, {
                xPercent: index * 100,
                duration: animDuration,
                ease: 'power3.inOut'
            });
        }

        currentIndex = index;
        updateMobileCounter(currentIndex);

        if (autoplay) resetAutoPlay();
    }

    // Click on timeline step
    steps.forEach((step, index) => {
        step.addEventListener('click', () => {
            goToSlide(index);
        });
    });

    // Mobile controls
    const mobilePrev = container.querySelector('.sl-mobile-prev');
    const mobileNext = container.querySelector('.sl-mobile-next');
    const mobileCurrent = container.querySelector('.sl-mobile-current');

    if (mobilePrev) {
        mobilePrev.addEventListener('click', () => {
            let prevIndex = (currentIndex - 1 + totalSlides) % totalSlides;
            goToSlide(prevIndex);
        });
    }

    if (mobileNext) {
        mobileNext.addEventListener('click', () => {
            let nextIndex = (currentIndex + 1) % totalSlides;
            goToSlide(nextIndex);
        });
    }

    function updateMobileCounter(index) {
        if (mobileCurrent) {
            mobileCurrent.textContent = String(index + 1).padStart(2, '0');
        }
    }

    // Touch/Swipe Logic for Mobile
    let touchStartX = 0;
    let touchEndX = 0;

    container.addEventListener('touchstart', e => {
        touchStartX = e.changedTouches[0].screenX;
    }, { passive: true });

    container.addEventListener('touchend', e => {
        touchEndX = e.changedTouches[0].screenX;
        handleSwipe();
    }, { passive: true });

    function handleSwipe() {
        if (touchEndX < touchStartX - 50) {
            let nextIndex = (currentIndex + 1) % totalSlides;
            goToSlide(nextIndex);
        }
        if (touchEndX > touchStartX + 50) {
            let prevIndex = (currentIndex - 1 + totalSlides) % totalSlides;
            goToSlide(prevIndex);
        }
    }

    // Auto Play Logic with Progress Fill animation
    let progressTween;

    function nextSlideTimer() {
        let nextIndex = (currentIndex + 1) % totalSlides;
        goToSlide(nextIndex);
    }

    function startAutoPlay() {
        const activeStep = steps[currentIndex];
        if (activeStep) {
            activeStep.style.setProperty('--tab-progress', '0');
        }

        progressTween = gsap.to({ p: 0 }, {
            p: 1,
            duration: delay / 1000,
            ease: 'none',
            onUpdate: function() {
                if (activeStep) {
                    activeStep.style.setProperty('--tab-progress', this.targets()[0].p);
                }
            },
            onComplete: () => {
                nextSlideTimer();
            }
        });
    }

    function resetAutoPlay() {
        if (progressTween) progressTween.kill();
        startAutoPlay();
    }

    if (autoplay) {
        startAutoPlay();

        // Pause on hover
        container.addEventListener('mouseenter', () => {
            if (progressTween) progressTween.pause();
        });
        container.addEventListener('mouseleave', () => {
            if (progressTween) progressTween.play();
        });
    }
}
