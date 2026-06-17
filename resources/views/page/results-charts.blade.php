{{-- resources/views/page/results-charts.blade.php --}}
@extends('layouts.app')

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        window.resultCharts = window.resultCharts || {};

        function verticalLabel(label) {
            return Array.from(String(label || ''));
        }

        function renderResultsChart(chart) {
            if (!chart || !chart.id || typeof Chart === 'undefined') return;

            setTimeout(function() {
                const canvas = document.getElementById(chart.id);
                if (!canvas) return;

                if (window.resultCharts[chart.id]) {
                    window.resultCharts[chart.id].destroy();
                }

                const valueLabelPlugin = {
                    id: 'valueLabelPlugin',
                    afterDatasetsDraw(chartInstance) {
                        const { ctx } = chartInstance;
                        ctx.save();
                        ctx.font = '14px "Times New Roman", serif';
                        ctx.fillStyle = '#111';
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'bottom';
                        chartInstance.data.datasets.forEach(function(dataset, datasetIndex) {
                            const meta = chartInstance.getDatasetMeta(datasetIndex);
                            meta.data.forEach(function(bar, index) {
                                const value = dataset.data[index];
                                if (value === null || value === undefined || value === '') return;
                                ctx.fillText(value, bar.x, bar.y - 4);
                            });
                        });
                        ctx.restore();
                    }
                };

                window.resultCharts[chart.id] = new Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels: (chart.labels || []).map(verticalLabel),
                        datasets: (chart.datasets || []).map(function(dataset) {
                            return Object.assign({
                                borderRadius: { topLeft: 4, topRight: 4 },
                                borderSkipped: false
                            }, dataset);
                        })
                    },
                    plugins: [valueLabelPlugin],
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: { padding: { top: 28, right: 24, bottom: 18, left: 8 } },
                        scales: {
                            x: {
                                title: {
                                    display: true,
                                    text: '科別',
                                    color: '#111',
                                    font: { family: 'Noto Sans CJK TC, sans-serif', size: 16 }
                                },
                                ticks: {
                                    color: '#111',
                                    autoSkip: false,
                                    maxRotation: 0,
                                    minRotation: 0,
                                    font: { family: 'Noto Sans CJK TC, sans-serif', size: 15 }
                                },
                                grid: { display: false }
                            },
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: '物種數',
                                    color: '#111',
                                    font: { family: 'Noto Sans CJK TC, sans-serif', size: 16 }
                                },
                                ticks: {
                                    color: '#111',
                                    stepSize: 5,
                                    font: { family: 'Times New Roman, serif', size: 14 }
                                },
                                grid: { color: '#D9D9D9' }
                            }
                        },
                        plugins: {
                            legend: {
                                display: chart.type === 'family-comparison',
                                position: 'bottom',
                                labels: {
                                    boxWidth: 14,
                                    color: '#111',
                                    font: { family: 'Noto Sans CJK TC, sans-serif', size: 15 }
                                }
                            },
                            tooltip: {
                                enabled: true,
                                callbacks: {
                                    title(items) {
                                        const item = items?.[0];
                                        const rawLabel = item?.chart?.data?.labels?.[item.dataIndex];
                                        if (Array.isArray(rawLabel)) return rawLabel.join('');
                                        return String(rawLabel ?? item?.label ?? '').replaceAll(',', '');
                                    }
                                }
                            }
                        }
                    }
                });
            }, 80);
        }

        window.addEventListener('results-chart-ready', function(event) {
            renderResultsChart(event.detail?.chart || event.detail?.[0]?.chart);
        });
    </script>
@endpush

@section('content')
    <h2 class="text-xl font-bold mb-4">成果圖表</h2>
    <livewire:results-charts />
@endsection