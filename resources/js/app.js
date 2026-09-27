import '@fontsource/ibm-plex-sans-arabic/400.css';
import '@fontsource/ibm-plex-sans-arabic/500.css';
import '@fontsource/ibm-plex-sans-arabic/600.css';
import '@fontsource/ibm-plex-sans-arabic/700.css';
import {
    Chart, LineController, BarController, LineElement, BarElement, PointElement,
    LinearScale, CategoryScale, Filler, Tooltip, Legend,
} from 'chart.js';

Chart.register(LineController, BarController, LineElement, BarElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip, Legend);

const css = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();
const fmt = (v, d = 0) => Number(v).toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: d });

/* ---------- الوضع الليلي ---------- */
document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
    btn.addEventListener('click', () => {
        const dark = document.documentElement.classList.toggle('dark');
        try { localStorage.setItem('theme', dark ? 'dark' : 'light'); } catch (e) {}
        window.dispatchEvent(new Event('themechange'));
    });
});

/* ---------- القائمة على الجوال ---------- */
document.querySelectorAll('[data-menu-toggle]').forEach((btn) => {
    btn.addEventListener('click', () => document.getElementById('mobile-menu')?.classList.toggle('hidden'));
});

/* ---------- الرسوم البيانية ---------- */
function baseOptions(extra = {}) {
    return {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 300 },
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { display: false },
            tooltip: { rtl: true, callbacks: { label: (c) => ' ' + fmt(c.parsed.y, c.dataset.decimals ?? 0) } },
        },
        scales: {
            x: { grid: { display: false }, ticks: { color: css('--muted'), maxTicksLimit: 6, font: { family: 'IBM Plex Sans Arabic' } } },
            y: { position: 'right', grid: { color: css('--border') }, ticks: { color: css('--muted'), callback: (v) => fmt(v), font: { family: 'IBM Plex Sans Arabic' } } },
        },
        ...extra,
    };
}

function labelFor(iso, range) {
    const d = new Date(iso);
    if (range === '1d') return d.toLocaleTimeString('ar-IQ', { hour: '2-digit', minute: '2-digit', numberingSystem: 'latn' });
    return d.toLocaleDateString('ar-IQ', { day: 'numeric', month: 'short', numberingSystem: 'latn' });
}

function initPriceChart(el) {
    const canvas = el.querySelector('canvas');
    const lockEl = el.querySelector('[data-chart-lock]');
    const code = el.dataset.code;
    const decimals = Number(el.dataset.decimals || 0);
    let chart;

    async function load(range) {
        el.querySelectorAll('[data-range]').forEach((b) => b.classList.toggle('active', b.dataset.range === range));
        const params = new URLSearchParams({ range });
        if (el.dataset.city) params.set('city', el.dataset.city);
        const res = await fetch(`/api/series/${code}?${params}`, { headers: { Accept: 'application/json' } });
        const data = await res.json();
        lockEl?.classList.toggle('hidden', !data.locked);
        canvas.classList.toggle('locked-blur', !!data.locked);
        if (data.locked) return;

        const labels = data.points.map((p) => labelFor(p.t, range));
        const values = data.points.map((p) => p.v);
        const up = values.length > 1 && values[values.length - 1] >= values[0];
        const color = el.dataset.color || (up ? css('--down') : css('--up'));

        const ctx = canvas.getContext('2d');
        const grad = ctx.createLinearGradient(0, 0, 0, canvas.clientHeight || 300);
        grad.addColorStop(0, color + '40');
        grad.addColorStop(1, color + '00');

        const dataset = { data: values, borderColor: color, backgroundColor: grad, fill: true, tension: 0.25, pointRadius: 0, borderWidth: 2, decimals };
        if (chart) {
            chart.data.labels = labels;
            chart.data.datasets[0] = dataset;
            chart.update();
        } else {
            chart = new Chart(canvas, { type: 'line', data: { labels, datasets: [dataset] }, options: baseOptions() });
        }
    }

    el.querySelectorAll('[data-range]').forEach((btn) => btn.addEventListener('click', () => load(btn.dataset.range)));
    load(el.dataset.range || '30d');
    window.addEventListener('themechange', () => { chart?.destroy(); chart = null; load(el.querySelector('[data-range].active')?.dataset.range || '30d'); });
}
document.querySelectorAll('[data-price-chart]').forEach(initPriceChart);

/* رسوم ثابتة: البيانات مضمّنة في الصفحة */
function initStaticChart(el) {
    const cfg = JSON.parse(el.dataset.staticChart);
    const colors = { brand: '#0f9f7f', gold: '#c99a2e', down: css('--down'), up: css('--up'), muted: css('--muted') };
    const datasets = cfg.datasets.map((ds) => ({
        borderWidth: 2, pointRadius: 0, tension: 0.3, ...ds,
        borderColor: colors[ds.color] || ds.color || colors.brand,
        backgroundColor: (colors[ds.color] || ds.color || colors.brand) + (cfg.type === 'bar' ? 'cc' : '22'),
        fill: cfg.type !== 'bar' && ds.fill !== false,
    }));
    const opts = baseOptions();
    if (cfg.legend) opts.plugins.legend = { display: true, position: 'bottom', labels: { color: css('--muted'), font: { family: 'IBM Plex Sans Arabic' } } };
    if (cfg.y2) {
        opts.scales.y2 = { position: 'left', grid: { display: false }, ticks: { color: css('--muted'), callback: (v) => fmt(v) } };
    }
    new Chart(el.querySelector('canvas'), { type: cfg.type || 'line', data: { labels: cfg.labels, datasets }, options: opts });
}
document.querySelectorAll('[data-static-chart]').forEach(initStaticChart);

/* ---------- التحديث المباشر كل 30 ثانية ---------- */
const liveEls = document.querySelectorAll('[data-live]');
if (liveEls.length) {
    const refresh = async () => {
        try {
            const res = await fetch('/api/live?' + (document.body.dataset.liveQuery || ''), { headers: { Accept: 'application/json' } });
            const { prices } = await res.json();
            liveEls.forEach((el) => {
                const p = prices[el.dataset.live];
                if (!p) return;
                const value = p[el.dataset.field || 'mid'];
                const text = el.dataset.field === 'change_pct' ? (value > 0 ? '+' : '') + fmt(value, 2) + '%' : fmt(value, p.decimals);
                if (el.textContent.trim() !== text) {
                    el.textContent = text;
                    el.classList.remove('flash-pulse');
                    void el.offsetWidth;
                    el.classList.add('flash-pulse');
                }
            });
            document.querySelectorAll('[data-live-clock]').forEach((el) => {
                el.textContent = new Date().toLocaleTimeString('ar-IQ', { hour: '2-digit', minute: '2-digit', second: '2-digit', numberingSystem: 'latn' });
            });
        } catch (e) { /* الشبكة غير متاحة — نعيد المحاولة لاحقاً */ }
    };
    setInterval(refresh, Number(document.body.dataset.refresh || 30000));
}

/* ---------- صورة الحالة اليومية ---------- */
document.querySelectorAll('[data-download-card]').forEach((btn) => {
    btn.addEventListener('click', async () => {
        const { toPng } = await import('html-to-image');
        const node = document.getElementById(btn.dataset.downloadCard);
        btn.disabled = true;
        const url = await toPng(node, { pixelRatio: 2, cacheBust: true });
        const a = document.createElement('a');
        a.href = url;
        a.download = `dinar-${new Date().toISOString().slice(0, 10)}.png`;
        a.click();
        btn.disabled = false;
    });
});

/* ---------- نسخ النص ---------- */
document.querySelectorAll('[data-copy]').forEach((btn) => {
    btn.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(document.getElementById(btn.dataset.copy).value || document.getElementById(btn.dataset.copy).textContent);
            const old = btn.textContent;
            btn.textContent = 'تم النسخ ✓';
            setTimeout(() => (btn.textContent = old), 1500);
        } catch (e) {}
    });
});

/* ---------- الخريطة ---------- */
const mapEl = document.querySelector('[data-map]');
if (mapEl) {
    import('leaflet').then((L) => {
        const places = JSON.parse(mapEl.dataset.map);
        const map = L.map(mapEl, { scrollWheelZoom: false }).setView([33.3, 44.4], 6);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18, attribution: '&copy; OpenStreetMap',
        }).addTo(map);
        const bounds = [];
        places.forEach((p) => {
            if (!p.lat) return;
            const icon = L.divIcon({ className: '', html: `<div style="font-size:22px">${p.type === 'gold' ? '🥇' : '💱'}</div>`, iconSize: [24, 24] });
            L.marker([p.lat, p.lng], { icon }).addTo(map).bindPopup(`<b>${p.name}</b><br>${p.address ?? ''}<br>⭐ ${p.rating}`);
            bounds.push([p.lat, p.lng]);
        });
        if (bounds.length) map.fitBounds(bounds, { padding: [30, 30] });
    });
}

/* ---------- تطبيق الويب (PWA) ---------- */
if ('serviceWorker' in navigator && location.protocol === 'https:') {
    navigator.serviceWorker.register('/sw.js').catch(() => {});
}
