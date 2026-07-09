document.addEventListener('DOMContentLoaded', function () {
    const canvases = document.querySelectorAll('canvas.dashboard-chart');
    if (!window.Chart || !canvases.length) return;

    canvases.forEach((canvas) => {
        const labels = JSON.parse(canvas.dataset.labels || '[]');
        const values = JSON.parse(canvas.dataset.values || '[]');
        const chartType = canvas.dataset.chartType || 'bar';
        const colors = canvas.dataset.colors ? JSON.parse(canvas.dataset.colors) : [];
        const datasetLabel = canvas.dataset.label || '';

        new Chart(canvas.getContext('2d'), {
            type: chartType,
            data: {
                labels,
                datasets: [{
                    label: datasetLabel,
                    data: values,
                    backgroundColor: colors.length ? colors : 'rgba(41, 170, 225, 0.65)',
                    borderColor: colors.length ? colors : 'rgba(41, 170, 225, 1)',
                    borderWidth: 1,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: chartType === 'bar' ? {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 }
                    }
                } : undefined,
                plugins: {
                    legend: { display: chartType !== 'bar' },
                    tooltip: { mode: 'index', intersect: false }
                }
            }
        });
    });
});