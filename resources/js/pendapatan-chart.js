import { Chart, BarController, BarElement, CategoryScale, LinearScale, Tooltip } from 'chart.js';

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip);

export function pasangChartPendapatan(elementId, data) {
    const el = document.getElementById(elementId);
    if (!el) return null;

    const chart = new Chart(el, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [{ label: 'Pendapatan', data: data.data, backgroundColor: '#2563eb' }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } },
        },
    });

    return {
        gambar(baru) {
            chart.data.labels = baru.labels;
            chart.data.datasets[0].data = baru.data;
            chart.update();
        },
    };
}
