import { Chart, BarController, BarElement, CategoryScale, LinearScale, Tooltip } from 'chart.js';

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip);

/**
 * Grafik batang horizontal untuk Dus Bonus Terkirim per varian produk.
 * Menggunakan warna violet/ungu (#8b5cf6) yang konsisten dengan tema bonus.
 */
export function pasangChartDusBonus(elementId, data) {
    const el = document.getElementById(elementId);
    if (!el) return null;

    const chart = new Chart(el, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Dus Bonus Terkirim',
                data: data.data,
                backgroundColor: '#8b5cf6',
                borderRadius: 4,
            }],
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            return ` ${context.parsed.x} Dus`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: { precision: 0 }
                }
            },
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
