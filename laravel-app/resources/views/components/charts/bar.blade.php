@props([
    'chartData' => [], // Trend: [{topic_label, slope}], Distribution: [{topic_label, doc_count}]
    'type' => 'distribution', // 'trend' or 'distribution'
    'height' => 'h-80',
    'title' => '',
])

<div
    x-data="barChartComponent({{ json_encode($chartData) }}, '{{ $type }}', '{{ addslashes($title) }}')"
    wire:ignore
    class="w-full"
>
    @if($title)
        <div class="mb-3 flex items-center justify-between gap-3">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-unsoed-blue-700">{{ $title }}</h3>
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
            Alpine.data('barChartComponent', (initialData, type, title) => ({
                chartData: initialData || [],
                type: type,
                chart: null,
                
                init() {
                    this.$nextTick(() => {
                        this.attemptRender(0);
                    });

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
                
                buildGradient(ctx, chartArea, startColor, endColor) {
                    if (!ctx || !chartArea) return startColor;
                    const gradient = ctx.createLinearGradient(chartArea.left, 0, chartArea.right, 0);
                    gradient.addColorStop(0, startColor);
                    gradient.addColorStop(1, endColor);
                    return gradient;
                },
                
                renderChart() {
                    if (this.chart) {
                        this.chart.destroy();
                        this.chart = null;
                    }
                    
                    if (!this.chartData || !this.chartData.length || typeof window.Chart === 'undefined') {
                        return;
                    }

                    const canvas = this.$refs.canvas;
                    if (!canvas || !canvas.isConnected) return;

                    const ctx = canvas.getContext('2d');
                    if (!ctx) return;
                    
                    let labels = [];
                    let data = [];
                    let backgroundColor = [];
                    let borderColor = [];
                    let labelName = '';
                    let tooltipCallback = null;

                    if (this.type === 'trend') {
                        let displayData = [];
                        
                        if (this.chartData.length <= 20) {
                            displayData = [...this.chartData].sort((a, b) => Number(b.slope ?? b.relative_slope ?? 0) - Number(a.slope ?? a.relative_slope ?? 0));
                        } else {
                            const sorted = [...this.chartData].sort((a, b) => Number(b.slope ?? b.relative_slope ?? 0) - Number(a.slope ?? a.relative_slope ?? 0));
                            const topTopics = sorted.slice(0, 10);
                            const bottomTopics = sorted.slice(-10);
                            displayData = [...topTopics, ...bottomTopics].sort((a, b) => Number(b.slope ?? b.relative_slope ?? 0) - Number(a.slope ?? a.relative_slope ?? 0));
                        }
                        
                        if (!displayData.length) return;
                        
                        labels = displayData.map(r => String(r.topic_label || r.label || `Topik ${r.topic_id || '-'}`));
                        data = displayData.map(r => Number(r.slope ?? r.relative_slope ?? 0));
                        labelName = 'Trend Slope';
                        
                        backgroundColor = (context) => {
                            const val = context.raw || 0;
                            const row = displayData[context.dataIndex];
                            
                            if (row?.trend_label === 'stable') {
                                return this.buildGradient(context.chart.ctx, context.chart.chartArea, 'rgba(59, 130, 246, 0.8)', 'rgba(37, 99, 235, 0.9)');
                            } else if (row?.trend_label === 'emerging' || val >= 0) {
                                return this.buildGradient(context.chart.ctx, context.chart.chartArea, 'rgba(16, 185, 129, 0.8)', 'rgba(5, 150, 105, 0.9)');
                            } else {
                                return this.buildGradient(context.chart.ctx, context.chart.chartArea, 'rgba(239, 68, 68, 0.8)', 'rgba(220, 38, 38, 0.9)');
                            }
                        };
                        borderColor = (context) => {
                            const val = context.raw || 0;
                            const row = displayData[context.dataIndex];
                            if (row?.trend_label === 'stable') return '#2563eb';
                            return (row?.trend_label === 'emerging' || val >= 0) ? '#059669' : '#dc2626';
                        };
                        
                        tooltipCallback = (context) => {
                            const val = Number(context.raw).toFixed(4);
                            const row = displayData[context.dataIndex];
                            const isSig = row?.is_significant ? '(Signifikan)' : '';
                            const tLabel = row?.trend_label ? `(${row.trend_label.toUpperCase()})` : '';
                            return `Slope: ${val} ${isSig} ${tLabel}`.trim();
                        };
                        
                    } else if (this.type === 'distribution') {
                        // Normalize: support both array-of-objects [{topic_label, doc_count}]
                        // and object format {labels: [], counts: []} (used in mahasiswa payload)
                        let normalized = [];
                        if (Array.isArray(this.chartData)) {
                            normalized = this.chartData;
                        } else if (this.chartData && Array.isArray(this.chartData.labels)) {
                            // {labels: [...], counts: [...]} format
                            normalized = this.chartData.labels.map((lbl, i) => ({
                                topic_label: lbl,
                                doc_count: Number((this.chartData.counts || [])[i] || 0),
                            }));
                        }

                        const displayData = normalized
                            .filter(row => Number(row.doc_count || row.count || 0) > 0)
                            .slice(0, 12);
                            
                        if (!displayData.length) return;
                        
                        labels = displayData.map(r => String(r.topic_label || `Topik ${r.topic_id || '-'}`));
                        data = displayData.map(r => Number(r.doc_count || r.count || 0));
                        labelName = 'Jumlah Dokumen';
                        
                        const totalDocs = data.reduce((a, b) => a + b, 0);
                        
                        const gradientPairs = [
                            ['#3b82f6', '#1d4ed8'], ['#06b6d4', '#0e7490'], ['#22c55e', '#15803d'],
                            ['#f59e0b', '#b45309'], ['#ef4444', '#b91c1c'], ['#a855f7', '#7e22ce'],
                            ['#14b8a6', '#0f766e'], ['#f97316', '#c2410c'], ['#6366f1', '#4338ca'],
                            ['#ec4899', '#be185d'], ['#0ea5e9', '#0369a1'], ['#84cc16', '#4d7c0f'],
                        ];
                        
                        backgroundColor = (context) => {
                            const pair = gradientPairs[context.dataIndex % gradientPairs.length];
                            return this.buildGradient(context.chart.ctx, context.chart.chartArea, pair[0], pair[1]);
                        };
                        borderColor = (context) => gradientPairs[context.dataIndex % gradientPairs.length][1];
                        
                        tooltipCallback = (context) => {
                            const val = Number(context.raw);
                            const pct = totalDocs > 0 ? (val / totalDocs * 100).toFixed(1) : 0;
                            return `Jumlah: ${val} skripsi (${pct}%)`;
                        };
                    }
                    
                    this.chart = new window.Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels,
                            datasets: [{
                                label: labelName,
                                data,
                                backgroundColor,
                                borderColor,
                                borderWidth: 1,
                                borderRadius: 6,
                                barPercentage: 0.7,
                                categoryPercentage: 0.8,
                            }]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    backgroundColor: 'rgba(15, 23, 42, 0.95)',
                                    titleColor: '#f8fafc',
                                    bodyColor: '#e2e8f0',
                                    padding: 10,
                                    cornerRadius: 8,
                                    callbacks: { label: tooltipCallback }
                                }
                            },
                            scales: {
                                x: {
                                    grid: { color: '#f1f5f9' },
                                    ticks: { font: { size: 11 } }
                                },
                                y: {
                                    grid: { display: false },
                                    ticks: { 
                                        font: { size: 11 },
                                        callback: function(value) {
                                            const lbl = String(this.getLabelForValue(value) || '');
                                            return lbl.length > 30 ? lbl.slice(0, 30) + '...' : lbl;
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
