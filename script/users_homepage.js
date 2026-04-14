/* ── users_homepage.js ──────────────────────────────────────────── */
/* Depends on: chart.umd.min.js (offline), dashData (inline PHP)    */

(function () {
    'use strict';

    // ── Shared defaults ──────────────────────────────────────────
    const FONT_FAMILY = "'Segoe UI', system-ui, sans-serif";
    const TEXT_COLOR  = 'rgba(238,229,216,0.85)';
    const MUTED_COLOR = 'rgba(238,229,216,0.4)';
    const GRID_COLOR  = 'rgba(255,255,255,0.05)';

    Chart.defaults.color          = TEXT_COLOR;
    Chart.defaults.font.family    = FONT_FAMILY;
    Chart.defaults.font.size      = 12;

    // ── Shared axis config for line charts ───────────────────────
    function lineScales(yLabel) {
        return {
            x: {
                grid:  { color: GRID_COLOR, drawBorder: false },
                ticks: { color: MUTED_COLOR, maxRotation: 30 }
            },
            y: {
                beginAtZero: true,
                grid:  { color: GRID_COLOR, drawBorder: false },
                ticks: {
                    color: MUTED_COLOR,
                    precision: 0,
                    callback: yLabel === 'amount'
                        ? v => 'Rs. ' + Number(v).toLocaleString()
                        : v => v
                },
                title: { display: false }
            }
        };
    }

    function sharedLineOptions(yLabel) {
        return {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1A1A1A',
                    borderColor: 'rgba(255,255,255,0.1)',
                    borderWidth: 1,
                    titleColor: TEXT_COLOR,
                    bodyColor: MUTED_COLOR,
                    padding: 10,
                    callbacks: yLabel === 'amount' ? {
                        label: ctx => ' Rs. ' + Number(ctx.parsed.y).toLocaleString(undefined, { minimumFractionDigits: 2 })
                    } : {}
                }
            },
            scales: lineScales(yLabel)
        };
    }

    // ── 1. Orders Over Time ──────────────────────────────────────
    (function buildOrdersLine() {
        const ctx = document.getElementById('ordersLineChart');
        if (!ctx) return;

        const labels = dashData.ordersOverTime.labels;
        const data   = dashData.ordersOverTime.data;

        const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 220);
        gradient.addColorStop(0,   'rgba(96,165,250,0.3)');
        gradient.addColorStop(1,   'rgba(96,165,250,0.0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label: 'Orders',
                    data,
                    borderColor:          '#60a5fa',
                    backgroundColor:      gradient,
                    borderWidth:          2,
                    pointRadius:          4,
                    pointBackgroundColor: '#60a5fa',
                    pointBorderColor:     '#121212',
                    pointBorderWidth:     2,
                    tension:              0.35,
                    fill:                 true
                }]
            },
            options: sharedLineOptions('count')
        });
    })();

    // ── 2. Spending Trends ───────────────────────────────────────
    (function buildSpendingLine() {
        const ctx = document.getElementById('spendingLineChart');
        if (!ctx) return;

        const labels = dashData.spendingTrends.labels;
        const data   = dashData.spendingTrends.data;

        const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 220);
        gradient.addColorStop(0,   'rgba(74,222,128,0.25)');
        gradient.addColorStop(1,   'rgba(74,222,128,0.0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label: 'Amount Spent',
                    data,
                    borderColor:          '#4ade80',
                    backgroundColor:      gradient,
                    borderWidth:          2,
                    pointRadius:          4,
                    pointBackgroundColor: '#4ade80',
                    pointBorderColor:     '#121212',
                    pointBorderWidth:     2,
                    tension:              0.35,
                    fill:                 true
                }]
            },
            options: sharedLineOptions('amount')
        });
    })();

    // ── 3. Order Status Distribution (Pie) ───────────────────────
    (function buildStatusPie() {
        const ctx = document.getElementById('statusPieChart');
        if (!ctx) return;

        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: dashData.statusDist.labels,
                datasets: [{
                    data:            dashData.statusDist.data,
                    backgroundColor: [
                        'rgba(251,191,36,0.85)',   // pending    - amber
                        'rgba(252,165,165,0.85)',  // cancelled - red   ← was processing blue
                        'rgba(196,181,253,0.85)',  // shipped    - purple
                        'rgba(74,222,128,0.85)',   // delivered  - green
                    ],
                    borderColor: '#1A1A1A',
                    borderWidth: 3,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color:       TEXT_COLOR,
                            padding:     14,
                            boxWidth:    12,
                            font:        { size: 12 }
                        }
                    },
                    tooltip: {
                        backgroundColor: '#1A1A1A',
                        borderColor:     'rgba(255,255,255,0.1)',
                        borderWidth:     1,
                        titleColor:      TEXT_COLOR,
                        bodyColor:       MUTED_COLOR,
                        padding:         10
                    }
                }
            }
        });
    })();

    // ── 4. Payment Method Usage (Doughnut) ───────────────────────
    (function buildPaymentDoughnut() {
        const ctx = document.getElementById('paymentDoughnutChart');
        if (!ctx) return;

        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['eSewa', 'COD'],
                datasets: [{
                    data:            [dashData.paymentMethods.esewa, dashData.paymentMethods.cod],
                    backgroundColor: [
                        'rgba(96,165,250,0.85)',  // eSewa - blue
                        'rgba(251,191,36,0.75)'   // COD   - amber
                    ],
                    borderColor: '#1A1A1A',
                    borderWidth: 3,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color:    TEXT_COLOR,
                            padding:  14,
                            boxWidth: 12,
                            font:     { size: 12 }
                        }
                    },
                    tooltip: {
                        backgroundColor: '#1A1A1A',
                        borderColor:     'rgba(255,255,255,0.1)',
                        borderWidth:     1,
                        titleColor:      TEXT_COLOR,
                        bodyColor:       MUTED_COLOR,
                        padding:         10,
                        callbacks: {
                            label: ctx => {
                                const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                const pct   = total > 0 ? Math.round(ctx.parsed / total * 100) : 0;
                                return ` ${ctx.label}: ${ctx.parsed} orders (${pct}%)`;
                            }
                        }
                    }
                }
            }
        });
    })();

})();