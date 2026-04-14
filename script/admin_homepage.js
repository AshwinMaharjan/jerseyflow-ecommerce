/* admin_homepage.js — JerseyFlow Admin Dashboard Charts */

(function () {
    'use strict';

    const D = window.DASH;

    // ── Shared theme tokens ───────────────────────────────────────────────────
    const TEXT    = 'rgba(238,229,216,.85)';
    const MUTED   = 'rgba(238,229,216,.45)';
    const GRID    = 'rgba(255,255,255,.05)';
    const TOOLTIP_BG = '#1A1A1A';
    const TOOLTIP_BORDER = 'rgba(255,255,255,.1)';

    Chart.defaults.color       = MUTED;
    Chart.defaults.font.family = "'Segoe UI', system-ui, sans-serif";
    Chart.defaults.font.size   = 11;

    const gridOpts = { color: GRID, drawBorder: false };
    const tickOpts = { color: MUTED };

    function tooltipBase(extraCallbacks) {
        return {
            backgroundColor: TOOLTIP_BG,
            borderColor:     TOOLTIP_BORDER,
            borderWidth:     1,
            titleColor:      TEXT,
            bodyColor:       MUTED,
            padding:         10,
            callbacks:       extraCallbacks || {}
        };
    }

    // ── 1. Revenue Over Time (Line) ───────────────────────────────────────────
    (function () {
        const el = document.getElementById('revenueChart');
        if (!el) return;

        const grad = el.getContext('2d').createLinearGradient(0, 0, 0, 260);
        grad.addColorStop(0, 'rgba(74,222,128,.28)');
        grad.addColorStop(1, 'rgba(74,222,128,.01)');

        new Chart(el, {
            type: 'line',
            data: {
                labels: D.revenue.labels,
                datasets: [{
                    label:                'Revenue (Rs)',
                    data:                 D.revenue.data,
                    borderColor:          '#4ade80',
                    backgroundColor:      grad,
                    borderWidth:          2,
                    pointRadius:          D.revenue.data.length > 10 ? 3 : 4,
                    pointHoverRadius:     6,
                    pointBackgroundColor: '#4ade80',
                    pointBorderColor:     '#1A1A1A',
                    pointBorderWidth:     2,
                    tension:              0.4,
                    fill:                 true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: tooltipBase({
                        label: ctx => ' Rs ' + Number(ctx.parsed.y).toLocaleString(undefined, { minimumFractionDigits: 2 })
                    })
                },
                scales: {
                    x: { grid: gridOpts, ticks: { ...tickOpts, maxRotation: 30 } },
                    y: {
                        beginAtZero: true,
                        grid: gridOpts,
                        ticks: {
                            ...tickOpts,
                            callback: v => 'Rs ' + Number(v).toLocaleString()
                        }
                    }
                }
            }
        });
    })();

    // ── 2. Orders Over Time (Line) ────────────────────────────────────────────
    (function () {
        const el = document.getElementById('ordersChart');
        if (!el) return;

        const grad = el.getContext('2d').createLinearGradient(0, 0, 0, 230);
        grad.addColorStop(0, 'rgba(96,165,250,.28)');
        grad.addColorStop(1, 'rgba(96,165,250,.01)');

        new Chart(el, {
            type: 'line',
            data: {
                labels: D.orders.labels,
                datasets: [{
                    label:                'Orders',
                    data:                 D.orders.data,
                    borderColor:          '#60a5fa',
                    backgroundColor:      grad,
                    borderWidth:          2,
                    pointRadius:          4,
                    pointHoverRadius:     6,
                    pointBackgroundColor: '#60a5fa',
                    pointBorderColor:     '#1A1A1A',
                    pointBorderWidth:     2,
                    tension:              0.4,
                    fill:                 true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: tooltipBase({
                        label: ctx => ' ' + ctx.parsed.y + ' orders'
                    })
                },
                scales: {
                    x: { grid: gridOpts, ticks: { ...tickOpts, maxRotation: 30 } },
                    y: {
                        beginAtZero: true,
                        grid: gridOpts,
                        ticks: { ...tickOpts, precision: 0 }
                    }
                }
            }
        });
    })();

    // ── 3. Top Selling Products (Bar) ─────────────────────────────────────────
    (function () {
        const el = document.getElementById('topProductsChart');
        if (!el) return;

        // Truncate long product names for readability
        const labels = D.topProducts.labels.map(l => l.length > 18 ? l.slice(0, 16) + '…' : l);

        new Chart(el, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label:                    'Qty Sold',
                    data:                     D.topProducts.data,
                    backgroundColor:          'rgba(196,181,253,.6)',
                    hoverBackgroundColor:      'rgba(196,181,253,.85)',
                    borderColor:              'rgba(196,181,253,.9)',
                    borderWidth:              1,
                    borderRadius:             5,
                    borderSkipped:            false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: tooltipBase({
                        title: (items) => D.topProducts.labels[items[0].dataIndex],
                        label: ctx => ' ' + ctx.parsed.y + ' units sold'
                    })
                },
                scales: {
                    x: {
                        grid: gridOpts,
                        ticks: { ...tickOpts, maxRotation: 20 }
                    },
                    y: {
                        beginAtZero: true,
                        grid: gridOpts,
                        ticks: { ...tickOpts, precision: 0 }
                    }
                }
            }
        });
    })();

    // ── Shared doughnut/pie options factory ───────────────────────────────────
    function circleOptions(tooltipLabelFn, cutout) {
        return {
            responsive: true,
            maintainAspectRatio: false,
            cutout: cutout || '0%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color:    TEXT,
                        boxWidth: 11,
                        padding:  12,
                        font:     { size: 11 }
                    }
                },
                tooltip: tooltipBase({ label: tooltipLabelFn })
            }
        };
    }

    // ── 4. Order Status (Pie) ─────────────────────────────────────────────────
    (function () {
        const el = document.getElementById('orderStatusChart');
        if (!el) return;

        new Chart(el, {
            type: 'pie',
            data: {
                labels: D.orderStatus.labels,
                datasets: [{
                    data: D.orderStatus.data,
                    backgroundColor: [
                        'rgba(251,191,36,.85)',   // pending   - amber
                        'rgba(74,222,128,.85)',   // delivered - green
                        'rgba(248,113,113,.85)',  // cancelled - red
                        'rgba(196,181,253,.85)',  // shipped   - purple
                    ],
                    borderColor:  '#1A1A1A',
                    borderWidth:  3,
                    hoverOffset:  6
                }]
            },
            options: circleOptions(ctx => ' ' + ctx.label + ': ' + ctx.parsed + ' orders')
        });
    })();

    // ── 5. Payment Status (Doughnut) ──────────────────────────────────────────
    (function () {
        const el = document.getElementById('paymentStatusChart');
        if (!el) return;

        new Chart(el, {
            type: 'doughnut',
            data: {
                labels: D.paymentStatus.labels,
                datasets: [{
                    data: D.paymentStatus.data,
                    backgroundColor: [
                        'rgba(74,222,128,.85)',          // paid      - green
                        'rgba(238,229,216,.25)',         // unpaid    - muted
                        'rgba(248,113,113,.85)',         // failed    - red
                        'rgba(94,234,212,.85)',          // refunded  - teal
                    ],
                    borderColor:  '#1A1A1A',
                    borderWidth:  3,
                    hoverOffset:  6
                }]
            },
            options: circleOptions(ctx => ' ' + ctx.label + ': ' + ctx.parsed, '62%')
        });
    })();

    // ── 6. Inventory Health (Pie) ─────────────────────────────────────────────
    (function () {
        const el = document.getElementById('inventoryChart');
        if (!el) return;

        new Chart(el, {
            type: 'pie',
            data: {
                labels: ['In Stock', 'Low Stock'],
                datasets: [{
                    data: [D.inventory.inStock, D.inventory.lowStock],
                    backgroundColor: [
                        'rgba(74,222,128,.85)',   // in stock  - green
                        'rgba(248,113,113,.85)',  // low stock - red
                    ],
                    borderColor:  '#1A1A1A',
                    borderWidth:  3,
                    hoverOffset:  6
                }]
            },
            options: circleOptions(ctx => ' ' + ctx.label + ': ' + ctx.parsed + ' products')
        });
    })();

    // ── 7. New Users Per Month (Bar) ──────────────────────────────────────────
    (function () {
        const el = document.getElementById('newUsersChart');
        if (!el) return;

        new Chart(el, {
            type: 'bar',
            data: {
                labels: D.newUsers.labels,
                datasets: [{
                    label:               'New Users',
                    data:                D.newUsers.data,
                    backgroundColor:     'rgba(96,165,250,.6)',
                    hoverBackgroundColor:'rgba(96,165,250,.85)',
                    borderColor:         'rgba(96,165,250,.9)',
                    borderWidth:         1,
                    borderRadius:        5,
                    borderSkipped:       false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: tooltipBase({
                        label: ctx => ' ' + ctx.parsed.y + ' new users'
                    })
                },
                scales: {
                    x: { grid: gridOpts, ticks: { ...tickOpts, maxRotation: 30 } },
                    y: {
                        beginAtZero: true,
                        grid: gridOpts,
                        ticks: { ...tickOpts, precision: 0 }
                    }
                }
            }
        });
    })();

})();