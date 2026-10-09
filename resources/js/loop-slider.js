/**
 * Infinite, draggable slider (Owl-carousel style).
 *
 * Markup: a clipped `track` element whose first child is the flex list of items.
 * The items are repeated on both sides, so dragging never reaches an end; letting go
 * glides to the nearest item. Optional `dotsBox` receives one dash per page.
 */
export function loopSlider(track, { dotsBox = null, autoplay = 0, dotClass = null } = {}) {
    const inner = track.firstElementChild;
    const originals = Array.from(inner.children);
    const N = originals.length;
    const EASE = 'transform 0.4s cubic-bezier(0.22, 0.61, 0.36, 1)';

    const copies = () => originals.map((el) => {
        const c = el.cloneNode(true);
        c.setAttribute('aria-hidden', 'true');
        return c;
    });
    copies().forEach((c) => inner.insertBefore(c, originals[0]));
    copies().forEach((c) => inner.appendChild(c));
    const all = Array.from(inner.children);

    let x = 0;
    let page = 0;
    let dots = [];
    let idleTimer = null;

    const step = () => all[1].offsetLeft - all[0].offsetLeft;
    const setWidth = () => step() * N;
    const perView = () => Math.max(1, Math.floor((track.clientWidth + (step() - all[0].offsetWidth)) / step()));
    const pages = () => Math.ceil(N / perView());
    const logicalIndex = () => (((Math.round((-x - setWidth()) / step())) % N) + N) % N;

    function setX(value, animate) {
        x = value;
        inner.style.transition = animate ? EASE : 'none';
        inner.style.transform = `translate3d(${value}px, 0, 0)`;
    }

    // Keep x inside the middle copy; the copies are identical, so the jump is invisible.
    function normalize() {
        const W = setWidth();
        let shift = 0;
        while (x + shift > -W) shift -= W;
        while (x + shift < -2 * W) shift += W;
        x += shift;
        return shift;
    }

    function paint() {
        dots.forEach((d, i) => {
            const active = i === page;
            d.className = dotClass ? dotClass(active) : `h-[3px] w-8 transition-colors ${active ? 'bg-emerald-950' : 'bg-gray-300'}`;
        });
    }

    function syncPage() {
        const p = Math.min(Math.floor(logicalIndex() / perView()), pages() - 1);
        if (p !== page) {
            page = p;
            paint();
        }
    }

    function settle() {
        if (normalize()) {
            inner.style.transition = 'none';
            inner.style.transform = `translate3d(${x}px, 0, 0)`;
        }
        syncPage();
    }

    function glideTo(value) {
        setX(value, true);
        clearTimeout(idleTimer);
        idleTimer = setTimeout(settle, 450);
    }

    function goTo(p) {
        const target = Math.min(p * perView(), N - 1);
        let diff = target - logicalIndex();
        if (diff > N / 2) diff -= N;
        else if (diff < -N / 2) diff += N;
        page = p;
        paint();
        glideTo(x - diff * step());
    }

    function build() {
        if (dotsBox) {
            dotsBox.innerHTML = '';
            dots = [];
            for (let i = 0; i < pages(); i++) {
                const d = document.createElement('button');
                d.type = 'button';
                d.setAttribute('aria-label', `Show page ${i + 1}`);
                d.addEventListener('click', () => goTo(i));
                dotsBox.appendChild(d);
                dots.push(d);
            }
        }
        setX(-setWidth(), false);
        page = 0;
        paint();
    }

    // Drag with mouse, touch or pen
    const drag = { down: false, moved: false, startX: 0, startPos: 0, lastX: 0, lastT: 0, vel: 0, id: null };

    track.addEventListener('pointerdown', (e) => {
        if (e.pointerType === 'mouse' && e.button !== 0) return;
        clearTimeout(idleTimer);
        settle();
        Object.assign(drag, {
            down: true, moved: false, id: e.pointerId,
            startX: e.clientX, lastX: e.clientX, startPos: x, lastT: performance.now(), vel: 0,
        });
    });

    window.addEventListener('pointermove', (e) => {
        if (!drag.down || e.pointerId !== drag.id) return;
        const dx = e.clientX - drag.startX;
        if (!drag.moved) {
            if (Math.abs(dx) < 6) return;
            drag.moved = true;
            track.setPointerCapture(e.pointerId);
            track.classList.add('cursor-grabbing');
        }
        const now = performance.now();
        drag.vel = (e.clientX - drag.lastX) / Math.max(1, now - drag.lastT);
        drag.lastX = e.clientX;
        drag.lastT = now;

        setX(drag.startPos + dx, false);
        const shift = normalize();
        if (shift) {
            drag.startPos += shift;
            setX(x, false);
        }
    });

    function endDrag(e) {
        if (!drag.down || (e && e.pointerId !== drag.id)) return;
        drag.down = false;
        track.classList.remove('cursor-grabbing');
        if (drag.moved) glideTo(-Math.round(-(x + drag.vel * 180) / step()) * step());
    }
    window.addEventListener('pointerup', endDrag);
    window.addEventListener('pointercancel', endDrag);

    // A drag must not count as a click on anything inside the slider
    track.addEventListener('click', (e) => {
        if (drag.moved) {
            e.stopPropagation();
            e.preventDefault();
            drag.moved = false;
        }
    }, true);
    track.addEventListener('dragstart', (e) => e.preventDefault());

    window.addEventListener('resize', build);
    build();

    // Optional autoplay: pauses while hovering or dragging
    let timer = null;
    const play = () => {
        if (!autoplay) return;
        clearInterval(timer);
        timer = setInterval(() => { if (!drag.down) goTo((page + 1) % pages()); }, autoplay);
    };
    track.addEventListener('mouseenter', () => clearInterval(timer));
    track.addEventListener('mouseleave', play);
    play();

    return { glideTo, goTo };
}
