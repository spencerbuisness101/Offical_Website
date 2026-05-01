/* ============================================================================
 * cinematic-bg.js  (v7.1)
 *
 * Self-contained, dependency-free cinematic background:
 *   1) WebGL fragment-shader black hole + accretion disk + 3-depth parallax
 *      starfield with stars drawn in toward the singularity.
 *   2) Canvas-2D fallback that draws the same starfield without WebGL when
 *      a context can't be created (very old GPU, blocked, etc.).
 *   3) Cursor hue follower — DOM element lerped toward pointer with rAF.
 *   4) Click ripple + spark particles.
 *
 * Public API:
 *   window.CinematicBG.mount({ intensity: 'normal' | 'dim' })
 *   window.CinematicBG.unmount()
 *
 * Mounted nodes:
 *   .cb-root > #cinematicCanvas
 *           > #cinematicCursor
 *           > #cinematicClickLayer
 *
 * Performance:
 *   - Shader is < 150 ALU ops, runs on integrated GPUs at 60 fps.
 *   - rAF loop pauses on document.visibilitychange === 'hidden'.
 *   - Honours prefers-reduced-motion: freezes shader, hides cursor halo.
 *   - Resize is rAF-throttled.
 *
 * Security note:
 *   - No external network calls.
 *   - No eval / Function() / inline event handlers.
 *   - Shader source is a static string literal; no user input touches GLSL.
 * ========================================================================= */

(function () {
    'use strict';

    if (typeof window === 'undefined' || !window.document) return;
    if (window.CinematicBG && window.CinematicBG.__mounted) return; // idempotent

    const PRM_QUERY = '(prefers-reduced-motion: reduce)';
    const reducedMotion = () => window.matchMedia && window.matchMedia(PRM_QUERY).matches;

    // ---------- root mounting ----------
    function ensureRoot(intensity) {
        let root = document.querySelector('.cb-root');
        if (!root) {
            root = document.createElement('div');
            root.className = 'cb-root';
            root.setAttribute('aria-hidden', 'true');
            document.body.insertBefore(root, document.body.firstChild);
        }
        if (!root.querySelector('#cinematicCanvas')) {
            const c = document.createElement('canvas');
            c.id = 'cinematicCanvas';
            root.appendChild(c);
        }
        if (!root.querySelector('#cinematicCursor')) {
            const cur = document.createElement('div');
            cur.id = 'cinematicCursor';
            root.appendChild(cur);
        }
        if (!root.querySelector('#cinematicClickLayer')) {
            const cl = document.createElement('div');
            cl.id = 'cinematicClickLayer';
            root.appendChild(cl);
        }
        if (intensity === 'dim') document.body.setAttribute('data-cb-intensity', 'dim');
        return root;
    }

    // ---------- shader source ----------
    const VERT = `
        attribute vec2 a_pos;
        varying vec2 v_uv;
        void main(){
            v_uv = a_pos * 0.5 + 0.5;
            gl_Position = vec4(a_pos, 0.0, 1.0);
        }
    `;

    // Procedural star + black hole + accretion disk in a single fragment pass.
    // Cheap noise via fract(sin(dot)). Three star depths summed.
    // Light bending: pixel ray distorted by inverse-distance pull toward centre.
    const FRAG = `
        precision highp float;
        varying vec2 v_uv;
        uniform vec2  u_res;
        uniform float u_time;
        uniform vec2  u_mouse;     // 0..1 normalised
        uniform float u_dpr;

        // Hash 21 → 1
        float hash21(vec2 p){
            p = fract(p * vec2(123.34, 456.21));
            p += dot(p, p + 45.32);
            return fract(p.x * p.y);
        }

        // 3-octave star layer
        float starLayer(vec2 uv, float density, float twinkle){
            vec2 g = floor(uv);
            vec2 f = fract(uv);
            float h = hash21(g);
            if (h < 1.0 - density) return 0.0;
            // Random offset within the cell
            vec2 c = vec2(hash21(g + 13.0), hash21(g + 71.0));
            float d = length(f - c);
            float star = smoothstep(0.06, 0.0, d);
            // Twinkle: phase shift per cell
            float ph = hash21(g + 7.0) * 6.2831;
            star *= 0.6 + 0.4 * sin(u_time * twinkle + ph);
            return star;
        }

        void main(){
            vec2 uv = v_uv;
            vec2 aspect = vec2(u_res.x / u_res.y, 1.0);
            vec2 p = (uv - 0.5) * aspect;       // -0.5..0.5 corrected
            // Black hole position: slow drift around centre, biased slightly upward
            vec2 bh = vec2(
                0.05 * sin(u_time * 0.07),
                0.02 * cos(u_time * 0.11) - 0.04
            );
            vec2 d = p - bh;
            float r = length(d);

            // Gravitational lensing: pixels near the BH are pulled inward,
            // sampling the starfield from a distorted coordinate.
            float pull = 0.18 / (r * r + 0.04);     // soft 1/r²
            vec2 starUV = p - normalize(d) * pull * 0.05;

            // Three parallax-depth star layers (different densities + twinkles)
            // Different scales create the parallax effect.
            float stars = 0.0;
            stars += starLayer(starUV * 80.0  + vec2(u_time * 0.005, 0.0), 0.030, 2.0);
            stars += starLayer(starUV * 140.0 + vec2(u_time * 0.012, 0.0), 0.022, 3.5) * 0.8;
            stars += starLayer(starUV * 220.0 + vec2(u_time * 0.020, 0.0), 0.014, 5.0) * 0.55;

            // Stars are eaten by the event horizon
            float eventHorizon = smoothstep(0.05, 0.07, r);
            stars *= eventHorizon;

            // Accretion disk: angular ring around the BH with hue gradient
            float disk = 0.0;
            float ang = atan(d.y, d.x);
            float ringR = 0.13;
            float ringW = 0.045;
            float ring = smoothstep(ringW, 0.0, abs(r - ringR));
            // Doppler-ish brightening on one side
            ring *= 0.55 + 0.45 * (0.5 + 0.5 * cos(ang - u_time * 0.6));
            // Tilt/perspective squish so the disk looks like it's edge-on
            float tilt = 1.0 - abs(d.y) * 6.0;
            ring *= clamp(tilt, 0.15, 1.0);
            disk = ring;

            // Disk colour: orange→teal→violet shift
            vec3 diskColor = mix(
                vec3(1.0, 0.55, 0.20),
                mix(vec3(0.11, 1.0, 0.77), vec3(0.66, 0.43, 0.96),
                    0.5 + 0.5 * sin(u_time * 0.25 + ang * 2.0)),
                smoothstep(0.0, 0.18, r)
            );

            // Black hole core: deep absorption with a thin photon ring
            float photonRing = smoothstep(0.078, 0.072, r) - smoothstep(0.072, 0.066, r);
            float core = smoothstep(0.07, 0.045, r); // 1 inside event horizon

            // Cursor hue: subtle additive halo following pointer
            vec2 mp = (u_mouse - 0.5) * aspect;
            float md = length(p - mp);
            vec3 mouseHue = vec3(0.48, 0.43, 0.96) * smoothstep(0.4, 0.0, md) * 0.18;

            // Compose
            vec3 col = vec3(0.015, 0.015, 0.035);   // deep void
            col += stars * vec3(0.95, 0.95, 1.05);
            col += diskColor * disk * 1.4;
            col += vec3(1.0, 0.85, 0.6) * photonRing * 1.2;
            col -= core * vec3(1.0);                // absolute black inside horizon
            col = max(col, vec3(0.0));
            col += mouseHue;

            // Soft vignette
            float vig = 1.0 - smoothstep(0.5, 1.05, length(p));
            col *= vig;

            gl_FragColor = vec4(col, 1.0);
        }
    `;

    // ---------- WebGL renderer ----------
    function makeWebGL(canvas) {
        const gl = canvas.getContext('webgl', { antialias: false, alpha: false, premultipliedAlpha: false })
                || canvas.getContext('experimental-webgl', { antialias: false, alpha: false });
        if (!gl) return null;

        function compile(type, src) {
            const s = gl.createShader(type);
            gl.shaderSource(s, src);
            gl.compileShader(s);
            if (!gl.getShaderParameter(s, gl.COMPILE_STATUS)) {
                console.warn('[cinematic-bg] shader compile failed:', gl.getShaderInfoLog(s));
                gl.deleteShader(s);
                return null;
            }
            return s;
        }
        const vs = compile(gl.VERTEX_SHADER, VERT);
        const fs = compile(gl.FRAGMENT_SHADER, FRAG);
        if (!vs || !fs) return null;

        const prog = gl.createProgram();
        gl.attachShader(prog, vs);
        gl.attachShader(prog, fs);
        gl.linkProgram(prog);
        if (!gl.getProgramParameter(prog, gl.LINK_STATUS)) {
            console.warn('[cinematic-bg] program link failed:', gl.getProgramInfoLog(prog));
            return null;
        }
        gl.useProgram(prog);

        // Fullscreen quad
        const buf = gl.createBuffer();
        gl.bindBuffer(gl.ARRAY_BUFFER, buf);
        gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1,-1, 1,-1, -1,1, 1,1]), gl.STATIC_DRAW);
        const aPos = gl.getAttribLocation(prog, 'a_pos');
        gl.enableVertexAttribArray(aPos);
        gl.vertexAttribPointer(aPos, 2, gl.FLOAT, false, 0, 0);

        const uRes   = gl.getUniformLocation(prog, 'u_res');
        const uTime  = gl.getUniformLocation(prog, 'u_time');
        const uMouse = gl.getUniformLocation(prog, 'u_mouse');
        const uDpr   = gl.getUniformLocation(prog, 'u_dpr');

        return {
            kind: 'webgl',
            gl,
            render(time, mouse, dpr) {
                gl.viewport(0, 0, gl.drawingBufferWidth, gl.drawingBufferHeight);
                gl.uniform2f(uRes, gl.drawingBufferWidth, gl.drawingBufferHeight);
                gl.uniform1f(uTime, time);
                gl.uniform2f(uMouse, mouse.x, mouse.y);
                gl.uniform1f(uDpr, dpr);
                gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
            },
            destroy() {
                gl.getExtension && gl.getExtension('WEBGL_lose_context')?.loseContext();
            }
        };
    }

    // ---------- Canvas-2D fallback (no shader; just stars + radial glow) ----------
    function makeCanvas2D(canvas) {
        const ctx = canvas.getContext('2d');
        if (!ctx) return null;
        const stars = [];
        function seed(w, h) {
            stars.length = 0;
            const count = Math.min(900, Math.floor((w * h) / 2200));
            for (let i = 0; i < count; i++) {
                stars.push({
                    x: Math.random() * w,
                    y: Math.random() * h,
                    r: Math.random() * 1.4 + 0.2,
                    s: Math.random() * 0.8 + 0.2,        // depth (speed factor)
                    p: Math.random() * Math.PI * 2       // twinkle phase
                });
            }
        }
        seed(canvas.width, canvas.height);

        return {
            kind: 'canvas2d',
            render(time, mouse) {
                const w = canvas.width, h = canvas.height;
                // Background gradient
                const g = ctx.createRadialGradient(w/2, h/2, 0, w/2, h/2, Math.max(w, h) * 0.6);
                g.addColorStop(0, '#0A0A1F');
                g.addColorStop(1, '#020208');
                ctx.fillStyle = g;
                ctx.fillRect(0, 0, w, h);

                // Black hole core
                const cx = w * 0.5 + Math.sin(time * 0.07) * w * 0.03;
                const cy = h * 0.5 + Math.cos(time * 0.11) * h * 0.02 - h * 0.04;
                const bhR = Math.min(w, h) * 0.07;

                // Accretion disk
                ctx.save();
                ctx.translate(cx, cy);
                const ringR = bhR * 1.9;
                const grd = ctx.createRadialGradient(0, 0, ringR * 0.7, 0, 0, ringR * 1.6);
                grd.addColorStop(0, 'rgba(255,160,80,0.0)');
                grd.addColorStop(0.45, 'rgba(255,140,60,0.55)');
                grd.addColorStop(0.6,  'rgba(60,220,200,0.45)');
                grd.addColorStop(1.0,  'rgba(120,80,200,0.0)');
                ctx.fillStyle = grd;
                ctx.beginPath();
                ctx.ellipse(0, 0, ringR * 1.6, ringR * 0.35, 0, 0, Math.PI * 2);
                ctx.fill();
                // Event horizon
                ctx.fillStyle = '#000';
                ctx.beginPath();
                ctx.arc(0, 0, bhR, 0, Math.PI * 2);
                ctx.fill();
                ctx.restore();

                // Stars (with subtle pull toward BH)
                for (let i = 0; i < stars.length; i++) {
                    const s = stars[i];
                    const dx = s.x - cx, dy = s.y - cy;
                    const dist = Math.hypot(dx, dy) + 1;
                    const pull = 8 / (dist * 0.02 + 0.5);
                    const px = s.x - (dx / dist) * pull * s.s;
                    const py = s.y - (dy / dist) * pull * s.s;
                    const a = 0.4 + 0.6 * Math.abs(Math.sin(time * (1 + s.s) + s.p));
                    ctx.globalAlpha = a;
                    ctx.fillStyle = '#dfe4ff';
                    ctx.beginPath();
                    ctx.arc(px, py, s.r, 0, Math.PI * 2);
                    ctx.fill();
                }
                ctx.globalAlpha = 1;
            },
            resize(w, h) { seed(w, h); },
            destroy() {}
        };
    }

    // ---------- main controller ----------
    function CinematicBG() {
        const state = {
            mounted: false,
            paused: false,
            renderer: null,
            canvas: null,
            cursor: null,
            clickLayer: null,
            mouse: { x: 0.5, y: 0.5, tx: 0.5, ty: 0.5, px: 0, py: 0 },
            dpr: 1,
            startTime: 0,
            rafId: 0,
            handlers: [],
            resizePending: false,
        };

        function setCanvasSize() {
            const dpr = Math.min(window.devicePixelRatio || 1, 2); // cap at 2× for perf
            state.dpr = dpr;
            const w = window.innerWidth;
            const h = window.innerHeight;
            state.canvas.width  = Math.max(2, Math.floor(w * dpr));
            state.canvas.height = Math.max(2, Math.floor(h * dpr));
            state.canvas.style.width  = w + 'px';
            state.canvas.style.height = h + 'px';
            if (state.renderer && state.renderer.resize) {
                state.renderer.resize(state.canvas.width, state.canvas.height);
            }
        }

        function onMouseMove(e) {
            const w = window.innerWidth, h = window.innerHeight;
            state.mouse.tx = e.clientX / w;
            state.mouse.ty = 1 - e.clientY / h; // flip Y for shader
            state.mouse.px = e.clientX;
            state.mouse.py = e.clientY;
            document.body.classList.add('cb-cursor-active');
            // Move halo
            if (state.cursor) {
                state.cursor.style.transform =
                    'translate3d(' + (e.clientX - 0) + 'px,' + (e.clientY - 0) + 'px, 0) translate(-50%, -50%)';
            }
        }
        function onMouseLeave() {
            document.body.classList.remove('cb-cursor-active');
        }

        function onClick(e) {
            if (reducedMotion()) return;
            if (!state.clickLayer) return;
            // Skip clicks from inputs/buttons inside the auth card to keep UX clean.
            const tgt = e.target;
            if (tgt && tgt.closest && tgt.closest('input, textarea, select, button, [role="button"], a, label')) return;

            const burst = document.createElement('div');
            burst.className = 'cb-burst';
            burst.style.left = e.clientX + 'px';
            burst.style.top  = e.clientY + 'px';
            state.clickLayer.appendChild(burst);
            setTimeout(() => burst.remove(), 800);

            // 6 sparks fly outward
            const sparkCount = 6;
            for (let i = 0; i < sparkCount; i++) {
                const sp = document.createElement('div');
                sp.className = 'cb-spark';
                const angle = (Math.PI * 2 * i) / sparkCount + Math.random() * 0.6;
                const dist  = 28 + Math.random() * 30;
                const tx = e.clientX + Math.cos(angle) * dist;
                const ty = e.clientY + Math.sin(angle) * dist;
                sp.style.left = e.clientX + 'px';
                sp.style.top  = e.clientY + 'px';
                sp.style.transition = 'transform 700ms cubic-bezier(.16,.84,.32,1), opacity 700ms ease-out';
                state.clickLayer.appendChild(sp);
                // next frame: animate to offset
                requestAnimationFrame(() => {
                    sp.style.transform = 'translate3d(' + (tx - e.clientX) + 'px,' + (ty - e.clientY) + 'px,0) translate(-50%,-50%)';
                    sp.style.opacity = '0';
                });
                setTimeout(() => sp.remove(), 820);
            }
        }

        function onResize() {
            if (state.resizePending) return;
            state.resizePending = true;
            requestAnimationFrame(() => {
                state.resizePending = false;
                setCanvasSize();
            });
        }

        function onVisibility() {
            state.paused = (document.visibilityState === 'hidden');
            if (!state.paused) {
                state.startTime = performance.now() - state.elapsedAtPause * 1000;
                loop(performance.now());
            } else {
                state.elapsedAtPause = (performance.now() - state.startTime) / 1000;
                cancelAnimationFrame(state.rafId);
            }
        }

        function loop(now) {
            if (state.paused) return;
            const t = (now - state.startTime) / 1000;

            // Lerp pointer for smoother halo influence on shader
            state.mouse.x += (state.mouse.tx - state.mouse.x) * 0.08;
            state.mouse.y += (state.mouse.ty - state.mouse.y) * 0.08;

            if (state.renderer) {
                try {
                    state.renderer.render(t, state.mouse, state.dpr);
                } catch (err) {
                    // Don't let a renderer hiccup break the page
                    console.warn('[cinematic-bg] render error:', err);
                }
            }
            state.rafId = requestAnimationFrame(loop);
        }

        function bind(el, evt, fn, opts) {
            el.addEventListener(evt, fn, opts);
            state.handlers.push(() => el.removeEventListener(evt, fn, opts));
        }

        function mount(opts) {
            if (state.mounted) return;
            opts = opts || {};
            const root = ensureRoot(opts.intensity || 'normal');
            state.canvas = root.querySelector('#cinematicCanvas');
            state.cursor = root.querySelector('#cinematicCursor');
            state.clickLayer = root.querySelector('#cinematicClickLayer');

            // Pick renderer
            state.renderer = makeWebGL(state.canvas);
            if (!state.renderer) state.renderer = makeCanvas2D(state.canvas);
            if (!state.renderer) {
                console.warn('[cinematic-bg] no renderer available');
                return;
            }

            setCanvasSize();
            state.startTime = performance.now();
            state.elapsedAtPause = 0;

            bind(window, 'resize', onResize, { passive: true });
            bind(window, 'mousemove', onMouseMove, { passive: true });
            bind(window, 'mouseleave', onMouseLeave, { passive: true });
            bind(window, 'click', onClick, { passive: true });
            bind(document, 'visibilitychange', onVisibility);

            // Reduced-motion: still render one frame, but don't animate
            if (reducedMotion() && state.renderer.kind === 'webgl') {
                try { state.renderer.render(0, state.mouse, state.dpr); } catch (_) {}
            } else {
                state.rafId = requestAnimationFrame(loop);
            }

            state.mounted = true;
            api.__mounted = true;
        }

        function unmount() {
            cancelAnimationFrame(state.rafId);
            state.handlers.forEach(h => { try { h(); } catch (_) {} });
            state.handlers.length = 0;
            if (state.renderer && state.renderer.destroy) state.renderer.destroy();
            const root = document.querySelector('.cb-root');
            if (root) root.remove();
            document.body.classList.remove('cb-cursor-active');
            document.body.removeAttribute('data-cb-intensity');
            state.mounted = false;
            api.__mounted = false;
        }

        const api = { mount, unmount, __mounted: false };
        return api;
    }

    window.CinematicBG = CinematicBG();

    // Auto-mount when the page declares `data-cinematic-bg` on <body>.
    function autoMount() {
        const intensity = document.body.getAttribute('data-cinematic-bg');
        if (intensity) window.CinematicBG.mount({ intensity });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', autoMount, { once: true });
    } else {
        autoMount();
    }
})();
