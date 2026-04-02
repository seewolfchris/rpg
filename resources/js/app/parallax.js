const PARALLAX_SCENE_SELECTOR = '[data-parallax-scene]';

export function setupAtmosphericParallax() {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    if (window.matchMedia('(pointer: coarse)').matches || window.innerWidth < 768) {
        return;
    }

    document.querySelectorAll(PARALLAX_SCENE_SELECTOR).forEach((scene) => {
        if (!(scene instanceof HTMLElement) || scene.dataset.parallaxBound === '1') {
            return;
        }

        scene.dataset.parallaxBound = '1';
        const layers = Array.from(scene.querySelectorAll('[data-parallax-layer]'))
            .filter((node) => node instanceof HTMLElement);

        if (layers.length === 0) {
            return;
        }

        scene.addEventListener('pointermove', (event) => {
            const rect = scene.getBoundingClientRect();
            const x = ((event.clientX - rect.left) / rect.width) - 0.5;
            const y = ((event.clientY - rect.top) / rect.height) - 0.5;

            layers.forEach((layer) => {
                const depth = Number(layer.dataset.parallaxDepth || '0.02');
                const translateX = x * depth * 22;
                const translateY = y * depth * 28;
                layer.style.transform = `translate3d(${translateX}px, ${translateY}px, 0)`;
            });
        });

        scene.addEventListener('pointerleave', () => {
            layers.forEach((layer) => {
                layer.style.transform = 'translate3d(0, 0, 0)';
            });
        });
    });
}
