/* payment_reports.js — Chart.js analytics for the payment reports page */

(function () {
    'use strict';

    const D = window.CHART_DATA;

    // ── Shared palette ────────────────────────────────────────────────────────
    const COLORS = {
        paid:     '#4ade80',
        failed:   '#f87171',
        refunded: '#fbbf24',
        unpaid:   'rgba(238,229,216,.35)',
        esewa:    '#5B21B6',
        cod:      '#0ea5e9',
        other:    '#64748b',
        blue:     '#60a5fa',
    };

    const STATUS_COLORS = {
        Paid:     COLORS.paid,
        Failed:   COLORS.failed,
        Refunded: COLORS.refunded,
        Unpaid:   COLORS.unpaid,
    };

    // ── Global Chart.js defaults ──────────────────────────────────────────────
    Chart.defaults.color          = 'rgba(238,229,216,.6)';
    Chart.defaults.font.family    = 'inherit';
    Chart.defaults.font.size      = 12;
    Chart.defaults.borderColor    = 'rgba(255,255,255,.08)';

    const gridOpts = {
        color:     'rgba(255,255,255,.06)',
        drawBorder: false,
    };
    const tickOpts = {
        color: 'rgba(238,229,216,.5)',
        font:  { size: 11 },
    };

    // ── 1. Daily Revenue (Line chart) ─────────────────────────────────────────
    const dailyCtx = document.getElementById('dailyRevenueChart');
    if (dailyCtx) {
        const gradient = dailyCtx.getContext('2d').createLinearGradient(0, 0, 0, 220);
        gradient.addColorStop(0,   'rgba(74,222,128,.25)');
        gradient.addColorStop(1,   'rgba(74,222,128,.01)');

        new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: D.daily.labels,
                datasets: [{
                    label:           'Revenue (Rs)',
                    data:            D.daily.data,
                    borderColor:     COLORS.paid,
                    backgroundColor: gradient,
                    borderWidth:     2,
                    pointRadius:     D.daily.data.length > 20 ? 0 : 3,
                    pointHoverRadius: 5,
                    pointBackgroundColor: COLORS.paid,
                    tension:         0.4,
                    fill:            true,
                }]
            },
            options: {
                responsive:          true,
                maintainAspectRatio: false,
                interaction: { intersect: false, mode: 'index' },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1A1A1A',
                        borderColor:     'rgba(255,255,255,.1)',
                        borderWidth:     1,
                        titleColor:      'rgba(238,229,216,.7)',
                        bodyColor:       '#EEE5D8',
                        padding:         10,
                        callbacks: {
                            label: ctx => ` Rs ${Number(ctx.parsed.y).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})}`
                        }
                    }
                },
                scales: {
                    x: { grid: gridOpts, ticks: { ...tickOpts, maxRotation: 0 } },
                    y: {
                        grid: gridOpts,
                        ticks: {
                            ...tickOpts,
                            callback: v => 'Rs ' + Number(v).toLocaleString()
                        },
                        beginAtZero: true,
                    }
                }
            }
        });
    }

    // ── 2. Payment Status Doughnut ────────────────────────────────────────────
    const statusCtx = document.getElementById('statusChart');
    if (statusCtx) {
        const bgColors  = D.status.labels.map(l => STATUS_COLORS[l] || COLORS.other);
        const hoverBgs  = bgColors.map(c => c);

        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: D.status.labels,
                datasets: [{
                    data:            D.status.data,
                    backgroundColor: bgColors,
                    hoverBackgroundColor: hoverBgs,
                    borderColor:     '#1A1A1A',
                    borderWidth:     3,
                    hoverOffset:     6,
                }]
            },
            options: {
                responsive:          true,
                maintainAspectRatio: false,
                cutout:              '68%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color:     'rgba(238,229,216,.7)',
                            boxWidth:  12,
                            padding:   14,
                            font:      { size: 11 },
                        }
                    },
                    tooltip: {
                        backgroundColor: '#1A1A1A',
                        borderColor:     'rgba(255,255,255,.1)',
                        borderWidth:     1,
                        titleColor:      'rgba(238,229,216,.7)',
                        bodyColor:       '#EEE5D8',
                        padding:         10,
                        callbacks: {
                            label: ctx => ` ${ctx.label}: ${ctx.parsed} transactions`
                        }
                    }
                }
            }
        });
    }

    // ── 3. Gateway Breakdown Doughnut ─────────────────────────────────────────
    const gwCtx = document.getElementById('gatewayChart');
    if (gwCtx) {
        const gwColors = D.gateway.labels.map(l => {
            const key = (l || '').toLowerCase();
            if (key.includes('esewa') || key.includes('esewa')) return COLORS.esewa;
            if (key.includes('cod'))    return COLORS.cod;
            return COLORS.other;
        });

        new Chart(gwCtx, {
            type: 'doughnut',
            data: {
                labels: D.gateway.labels,
                datasets: [{
                    data:            D.gateway.counts,
                    backgroundColor: gwColors,
                    borderColor:     '#1A1A1A',
                    borderWidth:     3,
                    hoverOffset:     6,
                }]
            },
            options: {
                responsive:          true,
                maintainAspectRatio: false,
                cutout:              '68%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color:    'rgba(238,229,216,.7)',
                            boxWidth:  12,
                            padding:   14,
                            font:      { size: 11 },
                        }
                    },
                    tooltip: {
                        backgroundColor: '#1A1A1A',
                        borderColor:     'rgba(255,255,255,.1)',
                        borderWidth:     1,
                        titleColor:      'rgba(238,229,216,.7)',
                        bodyColor:       '#EEE5D8',
                        padding:         10,
                        callbacks: {
                            label: ctx => {
                                const idx = ctx.dataIndex;
                                const total = 'Rs ' + Number(D.gateway.totals[idx]).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});
                                return ` ${ctx.label}: ${ctx.parsed} orders · ${total}`;
                            }
                        }
                    }
                }
            }
        });
    }

    // ── 4. Monthly Revenue (Bar chart) ────────────────────────────────────────
    const moCtx = document.getElementById('monthlyRevenueChart');
    if (moCtx) {
        new Chart(moCtx, {
            type: 'bar',
            data: {
                labels: D.monthly.labels,
                datasets: [{
                    label:           'Revenue (Rs)',
                    data:            D.monthly.data,
                    backgroundColor: 'rgba(96,165,250,.55)',
                    hoverBackgroundColor: 'rgba(96,165,250,.8)',
                    borderColor:     'rgba(96,165,250,.9)',
                    borderWidth:     1,
                    borderRadius:    5,
                    borderSkipped:   false,
                }]
            },
            options: {
                responsive:          true,
                maintainAspectRatio: false,
                interaction: { intersect: false, mode: 'index' },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1A1A1A',
                        borderColor:     'rgba(255,255,255,.1)',
                        borderWidth:     1,
                        titleColor:      'rgba(238,229,216,.7)',
                        bodyColor:       '#EEE5D8',
                        padding:         10,
                        callbacks: {
                            label: ctx => ` Rs ${Number(ctx.parsed.y).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})}`
                        }
                    }
                },
                scales: {
                    x: { grid: gridOpts, ticks: { ...tickOpts, maxRotation: 30 } },
                    y: {
                        grid: gridOpts,
                        ticks: {
                            ...tickOpts,
                            callback: v => 'Rs ' + Number(v).toLocaleString()
                        },
                        beginAtZero: true,
                    }
                }
            }
        });
    }

})();