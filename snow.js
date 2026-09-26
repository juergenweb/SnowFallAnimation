/**
 * Snow Theme - Add beautiful snowfall effect to any website
 * https://github.com/ddosnotification/snow-theme
 * MIT License
 */
(function() {

    // Configuration options
    const config = {
        snowflakes: ['❄', '❅', '❆'],      // Snowflake characters
        density: 50,                        // Maximum number of snowflakes
        interval: 200,                      // Interval between snowflake creation (ms)
        minSize: 0.8,                      // Minimum snowflake size
        maxSize: 1.5,                      // Maximum snowflake size
        minDuration: 5,                    // Minimum animation duration (s)
        maxDuration: 15,                   // Maximum animation duration (s)
        wind: 20,                          // Maximum wind effect (px)
        zIndex: 999999,                    // z-index for the container
        color: '#fff'                      // Color of the snowflakes
    };

    // Apply custom settings (e.g. from the SnowFallAnimation module) BEFORE the first snowflakes are created
    // The config is read from the data-config attribute of this script tag (CSP-friendly, no inline script needed).
    // window.SnowThemeConfig is still supported as fallback.
    let custom = Object.assign({}, window.SnowThemeConfig || {});
    const currentScript = document.currentScript;
    if (currentScript && currentScript.dataset && currentScript.dataset.config) {
        try {
            Object.assign(custom, JSON.parse(currentScript.dataset.config));
        } catch (e) {
            // invalid JSON -> use the default config
        }
    }
    Object.assign(config, custom);
    if (!Array.isArray(config.snowflakes) || !config.snowflakes.length) config.snowflakes = ['❄', '❅', '❆'];

    // Derive the interval from density and average duration, so that the density can actually be reached
    // (each snowflake is removed after its duration, so max. visible flakes = duration / interval)
    if (!custom.interval) {
        const avgDuration = (Number(config.minDuration) + Number(config.maxDuration)) / 2;
        config.interval = Math.max(16, Math.round(avgDuration * 1000 / Math.max(1, Number(config.density))));
    }

    // Create container for snowflakes
    const container = document.createElement('div');
    container.id = 'snow-container';
    container.style.zIndex = config.zIndex;
    document.body.appendChild(container);

    // Create a single snowflake
    function createSnowflake() {
        if (container.children.length >= config.density) return;

        const snowflake = document.createElement('div');
        snowflake.className = 'snowflake';
        snowflake.style.color = config.color;

        // textContent instead of innerHTML: the symbols are inserted as plain text, never as HTML (prevents XSS)
        snowflake.textContent = String(config.snowflakes[Math.floor(Math.random() * config.snowflakes.length)]);

        // Random properties
        const startPositionX = Math.random() * window.innerWidth;
        const size = Math.random() * (config.maxSize - config.minSize) + config.minSize;
        const duration = Math.random() * (config.maxDuration - config.minDuration) + config.minDuration;

        // Apply styles
        Object.assign(snowflake.style, {
            left: startPositionX + 'px',
            scale: size,
            opacity: Math.random() * 0.6 + 0.4,
            animation: `snowfall ${duration}s linear infinite`
        });

        // Add to container and set cleanup
        container.appendChild(snowflake);
        setTimeout(() => snowflake.remove(), duration * 1000);
    }

    // Start snowfall effect
    function startSnowfall() {

        // Create initial batch
        for (let i = 0; i < 10; i++) createSnowflake();
        
        // Continue creating snowflakes
        setInterval(createSnowflake, config.interval);
    }

    // Handle window resize
    window.addEventListener('resize', () => {
        const snowflakes = container.getElementsByClassName('snowflake');
        for (let flake of snowflakes) {
            if (parseInt(flake.style.left) > window.innerWidth) {
                flake.style.left = Math.random() * window.innerWidth + 'px';
            }
        }
    });

    // Start the effect
    startSnowfall();

    // Expose configuration to window for customization
    window.SnowTheme = {
        config: config,
        container: container,
        wind: config.wind,
        start: startSnowfall,
        createSnowflake: createSnowflake,

    };

})();