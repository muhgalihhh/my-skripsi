@props([
    'topicId' => null,
    'label' => '',
    'docCount' => 0,
    'words' => [], // Array of {text: string, rawWeight: number|string}
    'height' => 'h-48',
    'wordLimit' => 15,
])

<div
    x-data="wordCloudComponent({{ json_encode($words) }}, '{{ addslashes($label) }}', '{{ $topicId }}', {{ $wordLimit }})"
    wire:ignore
    class="min-w-0 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm"
>
    <div class="mb-3 flex items-center justify-between gap-2 border-b border-gray-100 pb-2">
        <p class="truncate text-sm font-semibold text-gray-800" title="{{ $label }}">{{ $label }}</p>
        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-600">
            {{ number_format((int) $docCount) }} dok
        </span>
    </div>

    <div class="relative {{ $height }} w-full">
        <canvas x-ref="canvas" class="!w-full !h-full"></canvas>
    </div>
</div>

@once
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-chart-wordcloud@4.4.4/build/index.umd.min.js"></script>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('wordCloudComponent', (wordsData, label, topicId, wordLimit) => ({
                words: wordsData || [],
                chart: null,
                init() {
                    this.$nextTick(() => {
                        this.attemptRender(0);
                    });
                    
                    this.$watch('words', () => {
                        this.renderChart();
                    });
                },

                attemptRender(attempt) {
                    // Wait for both Chart.js and the wordCloud plugin to be ready
                    const chartReady = typeof window.Chart !== 'undefined';
                    const pluginReady = chartReady && Boolean(window.Chart.registry?.getController?.('wordCloud'));
                    
                    if (!chartReady || !pluginReady) {
                        if (attempt < 30) {
                            setTimeout(() => this.attemptRender(attempt + 1), 150);
                        }
                        return;
                    }
                    this.renderChart();
                },
                
                renderChart() {
                    if (this.chart) {
                        this.chart.destroy();
                    }
                    
                    if (!this.words || !this.words.length || typeof window.Chart === 'undefined') {
                        return;
                    }

                    const seenWords = new Set();
                    const normalizedWords = this.words.map(w => {
                        const text = String(w?.text || '').trim().toLowerCase();
                        if (!text || seenWords.has(text)) return null;
                        seenWords.add(text);
                        
                        let weight = parseFloat(w?.raw_weight || w?.rawWeight || w?.weight || 0);
                        return { text, rawWeight: weight };
                    }).filter(w => w && w.rawWeight > 0).sort((a,b) => b.rawWeight - a.rawWeight).slice(0, wordLimit);
                    
                    if (!normalizedWords.length) return;
                    
                    const minRaw = Math.min(...normalizedWords.map(w => w.rawWeight));
                    const maxRaw = Math.max(...normalizedWords.map(w => w.rawWeight));
                    const denominator = maxRaw - minRaw || 1;
                    
                    const labels = normalizedWords.map(w => w.text);
                    const values = normalizedWords.map(w => {
                        const ratio = (w.rawWeight - minRaw) / denominator;
                        return Math.max(12, Math.round(10 + Math.pow(ratio, 1.05) * 26));
                    });
                    
                    const colorPool = ['#0ea5e9', '#f59e0b', '#10b981', '#8b5cf6', '#ec4899', '#14b8a6', '#f43f5e', '#3b82f6', '#6366f1', '#84cc16'];
                    const colors = normalizedWords.map((w, i) => {
                        const ratio = (w.rawWeight - minRaw) / denominator;
                        const band = Math.round(ratio * (colorPool.length - 1));
                        let hash = 0;
                        for (let j = 0; j < w.text.length; j++) {
                            hash = ((hash << 5) - hash) + w.text.charCodeAt(j);
                            hash |= 0;
                        }
                        return colorPool[Math.abs(hash + band + (i * 7)) % colorPool.length];
                    });
                    
                    this.chart = new window.Chart(this.$refs.canvas, {
                        type: 'wordCloud',
                        data: {
                            labels,
                            datasets: [{
                                label: label || `Topik ${topicId}`,
                                data: values,
                                color: colors,
                                fit: true,
                                minRotation: -15,
                                maxRotation: 15,
                                rotationSteps: 2,
                                padding: 4,
                                autoGrow: { maxTries: 50, scalingFactor: 1.1 }
                            }]
                        },
                        options: {
                            animation: false,
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        title: () => label || `Topik ${topicId}`,
                                        label: (ctx) => {
                                            const w = labels[ctx.dataIndex];
                                            const raw = normalizedWords[ctx.dataIndex].rawWeight;
                                            return `${w}: bobot ${raw.toFixed(4)}`;
                                        }
                                    }
                                }
                            },
                            elements: {
                                word: {
                                    fit: true,
                                    minRotation: -15,
                                    maxRotation: 15,
                                    rotationSteps: 2,
                                    padding: 4,
                                }
                            }
                        }
                    });
                }
            }));
        });
    </script>
@endonce
