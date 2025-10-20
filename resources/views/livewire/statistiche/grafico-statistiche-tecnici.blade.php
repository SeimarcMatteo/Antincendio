<div class="bg-white shadow rounded-xl p-4">
    <h2 class="text-lg font-semibold mb-2">Interventi per Tecnico</h2>

    <script id="datiTecniciJson" type="application/json">
        {!! isset($datiTecnici) ? $datiTecnici->toJson() : '[]' !!}
    </script>

    <div wire:ignore style="position: relative; height: 400px;">
        <canvas id="graficoTecnici"></canvas>
    </div>

    <script>
        let myBarChart;

        function renderChart() {
            console.log("ESEGUO renderChart()");
            const canvas = document.getElementById('graficoTecnici');
            const jsonTag = document.getElementById('datiTecniciJson');

            if (!canvas || !jsonTag) {
                console.warn("Canvas o dati non trovati");
                return;
            }

            const ctx = canvas.getContext('2d');
            let dati = [];

            try {
                dati = JSON.parse(jsonTag.textContent);
            } catch (err) {
                console.error("Errore parsing JSON:", err);
                return;
            }

            console.log("Dati JSON ricevuti:", dati);

            const labels = dati.map(item => item.name);
            const interventi = dati.map(item => parseInt(item.totale_interventi));
            const minuti = dati.map(item => parseInt(item.durata_totale));

            if (myBarChart) myBarChart.destroy();

            myBarChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'N. Interventi',
                            data: interventi,
                            backgroundColor: 'rgba(255, 99, 132, 0.7)',
                        },
                        {
                            label: 'Minuti Totali',
                            data: minuti,
                            backgroundColor: 'rgba(54, 162, 235, 0.7)',
                        }
                    ]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            console.log("DOM CONTENT LOADED");
            renderChart();
        });

        window.addEventListener('refreshChart', () => {
            console.log("EVENTO browser refreshChart");
            setTimeout(renderChart, 100);
        });
    </script>
</div>
