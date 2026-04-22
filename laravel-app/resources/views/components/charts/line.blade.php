@props([
    'chartData' => null,
    'height' => 'h-80',
    'title' => '',
    'yUnit' => 'auto', // 'proportion', 'percent', 'count', or 'auto'
])

<div
    x-data="lineChartComponent({{ json_encode($chartData) }}, '{{ addslashes($title) }}', '{{ $yUnit }}')"
    wire:ignore
    class="w-full"
>
    @if($title)
        <div class="mb-3">
            <h3 class="text-sm font-semibold text-gray-800">{{ $title }}</h3>
        </div>
    @endif
    
    <div class="relative {{ $height }} w-full">
        <canvas x-ref="canvas" class="!w-full !h-full"></canvas>
    </div>
</div>

@once
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('lineChartComponent', (initialData, title, yUnit) => ({
                chartData: initialData,
                chart: null,
                yUnit: yUnit || 'auto',
                
                init() {
                    this.$nextTick(() => {
                        this.attemptRender(0);
                    });
                    
                    // Re-render if Livewire updates data
                    window.addEventListener('livewire:update', () => {
                        this.$nextTick(() => this.renderChart());
                    });
                },

                attemptRender(attempt) {
                    if (typeof window.Chart === 'undefined') {
                        if (attempt < 25) {
                            setTimeout(() => this.attemptRender(attempt + 1), 150);
                        }
                        return;
                    }
                    this.renderChart();
                },

                destroy() {
                    if (this.chart) {
                        this.chart.destroy();
                        this.chart = null;
                    }
                },

                detectUnit(series) {
                    if (this.yUnit !== 'auto') return this.yUnit;
                    // Detect if values are proportions (0-1) or percentages (0-100)
                    const allValues = (series || []).flatMap(s => s.data || []).map(Number).filter(v => !isNaN(v));
                    if (!allValues.length) return 'proportion';
                    const max = Math.max(...allValues);
                    return max <= 1.01 ? 'proportion' : (max <= 101 ? 'percent' : 'count');
                },
                
                renderChart() {
                    if (this.chart) {
                        this.chart.destroy();
                        this.chart = null;
                    }
                    
                    if (!this.chartData || !this.chartData.series || typeof window.Chart === 'undefined') {
                        return;
                    }

                    const labels = this.chartData.years || [];
                    const series = this.chartData.series || [];
                    const unit = this.detectUnit(series);
                    const totalByYear = {}; // for computing percentage tooltips

                    // Build a map of year -> total doc count if available
                    if (this.chartData.year_totals) {
                        Object.assign(totalByYear, this.chartData.year_totals);
                    }

                    const colorPool = ['#0f766e', '#1d4ed8', '#be123c', '#9333ea', '#b45309', '#0369a1', '#15803d', '#c2410c', '#334155', '#4d7c0f'];
                    const datasets = series.map((s, idx) => {
                        const color = colorPool[idx % colorPool.length];
                        return {
                            label: s.label || `Topik ${s.topic_id}`,
                            data: s.data || [],
                            borderColor: color,
                            backgroundColor: color + '22',
                            borderWidth: 2,
                            tension: 0.3,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                            fill: false
                        };
                    });
                    
                    this.chart = new window.Chart(this.$refs.canvas, {
                        type: 'line',
                        data: { labels, datasets },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: {
                                mode: 'index',
                                intersect: false,
                            },
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        usePointStyle: true,
                                        boxWidth: 8,
                                        padding: 15,
                                        font: { size: 11, family: "'Inter', sans-serif" }
                                    }
                                },
                                tooltip: {
                                    backgroundColor: 'rgba(255, 255, 255, 0.97)',
                                    titleColor: '#1e293b',
                                    bodyColor: '#334155',
                                    borderColor: '#e2e8f0',
                                    borderWidth: 1,
                                    padding: 12,
                                    boxPadding: 6,
                                    usePointStyle: true,
                                    callbacks: {
                                        title: (items) => {
                                            const year = items[0]?.label || '';
                                            const total = totalByYear[year];
                                            return total ? `Tahun ${year} · Total ${total} dokumen` : `Tahun ${year}`;
                                        },
                                        label: (ctx) => {
                                            const raw = Number(ctx.raw);
                                            const topicLabel = ctx.dataset.label || '';
                                            if (unit === 'proportion') {
                                                const pct = (raw * 100).toFixed(1);
                                                return `${topicLabel}: ${pct}% (proporsi ${raw.toFixed(4)})`;
                                            } else if (unit === 'percent') {
                                                return `${topicLabel}: ${raw.toFixed(1)}%`;
                                            } else {
                                                return `${topicLabel}: ${raw.toFixed(0)} dokumen`;
                                            }
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    grid: { display: false },
                                    ticks: { font: { size: 11 } }
                                },
                                y: {
                                    beginAtZero: true,
                                    border: { display: false },
                                    grid: { color: '#f1f5f9' },
                                    ticks: {
                                        font: { size: 11 },
                                        callback: (val) => {
                                            if (unit === 'proportion') return (val * 100).toFixed(0) + '%';
                                            if (unit === 'percent') return val.toFixed(0) + '%';
                                            return val;
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            }));
        });
    </script>
@endonce
