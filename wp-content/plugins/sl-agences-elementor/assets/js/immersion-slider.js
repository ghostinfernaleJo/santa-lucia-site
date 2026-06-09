/**
 * JS for Immersion Slider Widget
 * Utilizes GSAP for smooth animations between slides.
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
    let isAnimating = false;
    let autoPlayTimer;

    const autoplay = container.dataset.autoplay === 'true';
    const delay = parseInt(container.dataset.delay) || 8000;
    const animDuration = parseFloat(container.dataset.animDuration) || 0.8;

    // Initialize first slide texts — hide all non-active slides' buttons
    slides.forEach((slide, i) => {
        const btns = [...slide.querySelectorAll('.sl-immersion-btn')];
        const subtitle = slide.querySelector('.sl-slide-subtitle');
        const title = slide.querySelector('.sl-slide-title');
        if (i === 0) {
            gsap.set([subtitle, title, ...btns].filter(Boolean), { y: 0, opacity: 1 });
        } else {
            gsap.set([subtitle, title, ...btns].filter(Boolean), { y: 50, opacity: 0 });
        }
    });

    function goToSlide(index) {
        if (isAnimating || index === currentIndex) return;
        isAnimating = true;

        const currentSlide = slides[currentIndex];
        const nextSlide = slides[index];

        // 1. Text Out Animation (Current Slide)
        const currentBtns = [...currentSlide.querySelectorAll('.sl-immersion-btn')];
        const currentElements = [
            ...currentBtns,
            currentSlide.querySelector('.sl-slide-title'),
            currentSlide.querySelector('.sl-slide-subtitle')
        ].filter(el => el);

        if (currentElements.length > 0) {
            gsap.to(currentElements, {
                y: -50,
                opacity: 0,
                duration: 0.4,
                stagger: 0.05,
                ease: "power2.in",
            });
        }

        // 2. Background Transition
        gsap.to(currentSlide, {
            opacity: 0,
            duration: animDuration,
            delay: 0.3,
            ease: "power2.inOut",
            onComplete: () => {
                currentSlide.classList.remove('active');

                // Reset text positions for later
                if (currentElements.length > 0) {
                    gsap.set(currentElements, { y: 50, opacity: 0 });
                }
            }
        });

        nextSlide.classList.add('active');
        gsap.fromTo(nextSlide, 
            { opacity: 0 }, 
            { opacity: 1, duration: animDuration, delay: 0.3, ease: "power2.inOut" }
        );

        // 3. Text In Animation (Next Slide)
        const nextBtns = [...nextSlide.querySelectorAll('.sl-immersion-btn')];
        const nextElements = [
            nextSlide.querySelector('.sl-slide-subtitle'),
            nextSlide.querySelector('.sl-slide-title'),
            ...nextBtns
        ].filter(el => el);

        if (nextElements.length > 0) {
            gsap.fromTo(nextElements,
                { y: 50, opacity: 0 },
                {
                    y: 0,
                    opacity: 1,
                    duration: 0.6,
                    delay: 0.8,
                    stagger: 0.15,
                    ease: "power3.out"
                }
            );
        }

        // 4. Timeline Update
        steps.forEach(s => s.classList.remove('active'));
        steps[index].classList.add('active');

        // Move Progress Bar (Translate X based on index)
        gsap.to(progressBar, {
            xPercent: index * 100,
            duration: animDuration,
            ease: "power3.inOut"
        });

        // 5. Play/Pause Videos
        const currentVid = currentSlide.querySelector('video');
        if (currentVid) currentVid.pause();
        
        const nextVid = nextSlide.querySelector('video');
        if (nextVid) {
            nextVid.currentTime = 0;
            const playPromise = nextVid.play();
            if (playPromise !== undefined) {
                playPromise.catch(error => {
                    // Auto-play was prevented
                    console.log("Video auto-play prevented:", error);
                });
            }
        }

        currentIndex = index;
        updateMobileCounter(currentIndex);

        // Reset timer if autoplay
        if (autoplay) {
            resetAutoPlay();
        }

        // Release lock
        setTimeout(() => {
            isAnimating = false;
        }, (animDuration * 1000) + 400);
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
            // Swipe left -> Next
            let nextIndex = (currentIndex + 1) % totalSlides;
            goToSlide(nextIndex);
        }
        if (touchEndX > touchStartX + 50) {
            // Swipe right -> Prev
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
        // Animate the fill of the current tab marker using GSAP
        // We tween a dummy object and update the CSS variable on the active step
        const activeStep = steps[currentIndex];
        
        // Reset scale instantly
        if (activeStep) {
            activeStep.style.setProperty('--tab-progress', '0');
        }

        progressTween = gsap.to({ p: 0 }, {
            p: 1,
            duration: delay / 1000,
            ease: "none",
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
