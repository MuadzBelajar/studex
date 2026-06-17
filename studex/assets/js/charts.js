// ============================================================
//  STUDEX — Student Index
//  assets/js/charts.js — Chart.js Wrapper & Inisialisasi
//  Bar · Line · Donut · Radar · Attendance
//  Dependency: Chart.js v4 (CDN)
// ============================================================

(function () {
    'use strict';

    // ============================================================
    // DESIGN TOKENS — sinkron dengan variables.css
    // ============================================================
    var COLORS = {
        army        : '#395917',
        darkGreen   : '#4C8C6A',
        softGreen   : '#A4C8AE',
        softGreen0  : '#E6EFEA',
        purple      : '#595D75',
        beige       : '#C9A96E',
        tosca       : '#C1D8DA',
        red         : '#8B1408',
        warning     : '#C97C10',
        success     : '#2D7A4F',
        grey        : '#45515C',
        greyLight   : '#C8CDD2',
        white       : '#FFFFFF',
        black       : '#121212',
    };

    // ============================================================
    // GLOBAL DEFAULTS
    // ============================================================
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof Chart === 'undefined') {
            console.warn('STUDEX Charts: Chart.js belum dimuat.');
            return;
        }

        Chart.defaults.font.family  = "'General Sans', 'Inter', sans-serif";
        Chart.defaults.font.size    = 12;
        Chart.defaults.color        = COLORS.grey;
        Chart.defaults.plugins.legend.display          = false;
        Chart.defaults.plugins.tooltip.backgroundColor = COLORS.black;
        Chart.defaults.plugins.tooltip.titleColor      = COLORS.white;
        Chart.defaults.plugins.tooltip.bodyColor       = 'rgba(255,255,255,0.75)';
        Chart.defaults.plugins.tooltip.padding         = 10;
        Chart.defaults.plugins.tooltip.cornerRadius    = 8;
        Chart.defaults.plugins.tooltip.displayColors   = false;

        // Auto-init canvas[data-chart]
        document.querySelectorAll('canvas[data-chart]').forEach(function (canvas) {
            var type    = canvas.dataset.chart;
            var chartId = canvas.dataset.chartId || canvas.id;
            var data    = window['chartData_' + chartId];
            if (!data) return;
            switch (type) {
                case 'bar':        window.createBarChart(canvas, data);        break;
                case 'line':       window.createLineChart(canvas, data);       break;
                case 'donut':      window.createDonutChart(canvas, data);      break;
                case 'radar':      window.createRadarChart(canvas, data);      break;
                case 'attendance': window.createAttendanceChart(canvas, data); break;
            }
        });
    });

    // ============================================================
    // 1. BAR CHART
    // ============================================================
    window.createBarChart = function (canvas, opts) {
        if (typeof Chart === 'undefined') return null;
        opts = opts || {};
        var ctx = typeof canvas === 'string' ? document.getElementById(canvas) : canvas;
        if (!ctx) return null;
        if (ctx._chartInstance) ctx._chartInstance.destroy();

        var labels   = opts.labels   || [];
        var datasets = opts.datasets || [{
            data               : opts.data || [],
            backgroundColor    : opts.colors || labels.map(function (_, i) {
                return i === opts.activeIndex ? COLORS.army : COLORS.softGreen;
            }),
            borderRadius       : 8,
            borderSkipped      : false,
            maxBarThickness    : opts.maxBarThickness || 48,
            hoverBackgroundColor: COLORS.army,
        }];

        var chart = new Chart(ctx, {
            type: 'bar',
            data: { labels: labels, datasets: datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend : { display: datasets.length > 1 },
                    tooltip: {
                        callbacks: {
                            label: function (c) {
                                return (opts.prefix || '') + c.parsed.y.toLocaleString('id-ID') + (opts.suffix || '');
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false }, border: { display: false }, ticks: { color: COLORS.grey, font: { size: 11 } } },
                    y: { grid: { color: 'rgba(0,0,0,0.05)' }, border: { display: false }, ticks: { color: COLORS.grey, font: { size: 11 }, maxTicksLimit: 5 }, beginAtZero: true }
                },
                onClick: opts.onClick || null,
            }
        });
        ctx._chartInstance = chart;
        return chart;
    };

    // ============================================================
    // 2. LINE CHART
    // ============================================================
    window.createLineChart = function (canvas, opts) {
        if (typeof Chart === 'undefined') return null;
        opts = opts || {};
        var ctx = typeof canvas === 'string' ? document.getElementById(canvas) : canvas;
        if (!ctx) return null;
        if (ctx._chartInstance) ctx._chartInstance.destroy();

        var datasets = opts.datasets || [{
            label               : opts.label || 'Data',
            data                : opts.data  || [],
            borderColor         : COLORS.army,
            backgroundColor     : 'rgba(57,89,23,0.08)',
            borderWidth         : 2.5,
            pointBackgroundColor: COLORS.army,
            pointBorderColor    : COLORS.white,
            pointBorderWidth    : 2,
            pointRadius         : 5,
            pointHoverRadius    : 7,
            fill                : opts.fill !== false,
            tension             : 0.4,
        }];

        var chart = new Chart(ctx, {
            type: 'line',
            data: { labels: opts.labels || [], datasets: datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: datasets.length > 1 },
                    tooltip: {
                        callbacks: {
                            label: function (c) {
                                return ' ' + c.dataset.label + ': ' + c.parsed.y.toLocaleString('id-ID') + (opts.suffix || '');
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false }, border: { display: false }, ticks: { color: COLORS.grey, font: { size: 11 } } },
                    y: { grid: { color: 'rgba(0,0,0,0.05)' }, border: { display: false }, ticks: { color: COLORS.grey, font: { size: 11 }, maxTicksLimit: 5 }, beginAtZero: true }
                }
            }
        });
        ctx._chartInstance = chart;
        return chart;
    };

    // ============================================================
    // 3. DONUT CHART
    // ============================================================
    window.createDonutChart = function (canvas, opts) {
        if (typeof Chart === 'undefined') return null;
        opts = opts || {};
        var ctx = typeof canvas === 'string' ? document.getElementById(canvas) : canvas;
        if (!ctx) return null;
        if (ctx._chartInstance) ctx._chartInstance.destroy();

        var defaultColors = [COLORS.darkGreen, COLORS.beige, COLORS.tosca, COLORS.purple, COLORS.softGreen, COLORS.warning, COLORS.red];

        var chart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels  : opts.labels || [],
                datasets: [{
                    data            : opts.data   || [],
                    backgroundColor : opts.colors || defaultColors,
                    borderWidth     : 3,
                    borderColor     : COLORS.white,
                    hoverBorderWidth: 3,
                    hoverOffset     : 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: opts.cutout || '70%',
                plugins: {
                    legend: { display: opts.showLegend || false },
                    tooltip: {
                        callbacks: {
                            label: function (c) {
                                var total = c.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                                var pct   = total > 0 ? Math.round((c.parsed / total) * 100) : 0;
                                return ' ' + c.label + ': ' + c.parsed.toLocaleString('id-ID') + ' (' + pct + '%)';
                            }
                        }
                    }
                }
            }
        });

        // Center text
        if (opts.centerText) {
            var wrapper = ctx.closest('.donut-chart-canvas');
            if (wrapper) {
                var el = document.createElement('div');
                el.className = 'donut-center-text';
                el.innerHTML = '<div class="donut-center-value">' + (opts.centerText.value || '') + '</div>'
                             + (opts.centerText.label ? '<div class="donut-center-label">' + opts.centerText.label + '</div>' : '');
                wrapper.appendChild(el);
            }
        }

        ctx._chartInstance = chart;
        return chart;
    };

    // ============================================================
    // 4. RADAR CHART — Binjas vs Standarisasi
    // ============================================================
    window.createRadarChart = function (canvas, opts) {
        if (typeof Chart === 'undefined') return null;
        opts = opts || {};
        var ctx = typeof canvas === 'string' ? document.getElementById(canvas) : canvas;
        if (!ctx) return null;
        if (ctx._chartInstance) ctx._chartInstance.destroy();

        var chart = new Chart(ctx, {
            type: 'radar',
            data: {
                labels  : opts.labels || [],
                datasets: [
                    {
                        label               : opts.labelSiswa || 'Nilai Siswa',
                        data                : opts.dataSiswa  || [],
                        borderColor         : COLORS.army,
                        backgroundColor     : 'rgba(57,89,23,0.15)',
                        borderWidth         : 2.5,
                        pointBackgroundColor: COLORS.army,
                        pointBorderColor    : COLORS.white,
                        pointBorderWidth    : 2,
                        pointRadius         : 5,
                        pointHoverRadius    : 7,
                    },
                    {
                        label               : opts.labelStandar || 'Standarisasi',
                        data                : opts.dataStandar  || [],
                        borderColor         : COLORS.greyLight,
                        backgroundColor     : 'rgba(200,205,210,0.12)',
                        borderWidth         : 2,
                        borderDash          : [5, 4],
                        pointBackgroundColor: COLORS.greyLight,
                        pointBorderColor    : COLORS.white,
                        pointBorderWidth    : 2,
                        pointRadius         : 4,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display : true,
                        position: 'bottom',
                        labels  : { boxWidth: 12, padding: 16, font: { size: 11 }, color: COLORS.grey }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (c) { return ' ' + c.dataset.label + ': ' + c.parsed.r; }
                        }
                    }
                },
                scales: {
                    r: {
                        beginAtZero  : true,
                        grid         : { color: 'rgba(0,0,0,0.06)' },
                        angleLines   : { color: 'rgba(0,0,0,0.06)' },
                        pointLabels  : { font: { size: 11 }, color: COLORS.grey },
                        ticks        : { font: { size: 9 }, color: COLORS.greyLight, backdropColor: 'transparent', maxTicksLimit: 5 },
                        suggestedMin : opts.min || 0,
                        suggestedMax : opts.max || 100,
                    }
                }
            }
        });
        ctx._chartInstance = chart;
        return chart;
    };

    // ============================================================
    // 5. ATTENDANCE CHART — stacked bar kehadiran
    // ============================================================
    window.createAttendanceChart = function (canvas, opts) {
        opts = opts || {};
        return window.createBarChart(canvas, {
            labels  : opts.labels || [],
            datasets: [
                { label: 'Hadir', data: opts.hadir || [], backgroundColor: COLORS.success, borderRadius: 6, borderSkipped: false },
                { label: 'Izin',  data: opts.izin  || [], backgroundColor: COLORS.tosca,   borderRadius: 6, borderSkipped: false },
                { label: 'Sakit', data: opts.sakit || [], backgroundColor: COLORS.warning,  borderRadius: 6, borderSkipped: false },
                // FIX: Alpha harus light pink/rose (konsisten dengan legend atas)
                { label: 'Alpha', data: opts.alpha || [], backgroundColor: '#F9E8E7',      borderRadius: 6, borderSkipped: false },
            ],
            suffix: ' siswa',
        });
    };

    // ============================================================
    // UPDATE & DESTROY
    // ============================================================
    window.updateChart = function (canvas, newLabels, newDatasets) {
        var ctx = typeof canvas === 'string' ? document.getElementById(canvas) : canvas;
        if (!ctx || !ctx._chartInstance) return;
        var chart = ctx._chartInstance;
        if (newLabels)   chart.data.labels = newLabels;
        if (newDatasets) {
            newDatasets.forEach(function (ds, i) {
                if (chart.data.datasets[i]) Object.assign(chart.data.datasets[i], ds);
            });
        }
        chart.update('active');
    };

    window.destroyChart = function (canvas) {
        var ctx = typeof canvas === 'string' ? document.getElementById(canvas) : canvas;
        if (ctx && ctx._chartInstance) { ctx._chartInstance.destroy(); ctx._chartInstance = null; }
    };

    // Expose colors
    window.STUDEX_COLORS = COLORS;

})();