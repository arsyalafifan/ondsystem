import { Chart, BarController, BarElement, CategoryScale, LinearScale, Tooltip } from 'chart.js';

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip);

/**
 * Papan peringkat dus per sales — batang horizontal, bukan tegak seperti
 * grafik Pendapatan: namanya perlu terbaca penuh di sumbu, dan batang
 * mendatar tidak memaksa nama panjang dipotong atau diputar miring.
 */
export function pasangChartInsentif(elementId, data) {
    const el = document.getElementById(elementId);
    if (!el) return null;

    const chart = new Chart(el, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [{ label: 'Dus', data: data.data, backgroundColor: '#059669' }],
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true } },
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
