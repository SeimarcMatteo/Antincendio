<div class="bg-white shadow rounded-xl p-4">
    <h2 class="text-lg font-semibold mb-2">Grafico Interventi per Cliente</h2>

    {{-- Passaggio JSON dati al grafico --}}
    <script id="json_interventi_clienti" type="application/json">
        {!! $dati->map(fn($r) => ['label' => $r->nome, 'value' => $r->totale])->values()->toJson() !!}
    </script>

    <div wire:ignore style="position: relative; height: 400px;">
        <canvas id="canvas_interventi_clienti"></canvas>
    </div>
    <script>
        let myBarChart;

        function renderChart() {
            console.log("ESEGUO renderChart()");
            const canvas = document.getElementById('canvas_interventi_clienti');
            const jsonTag = document.getElementById('json_interventi_clienti');

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

            const labels = dati.map(item => item.label);
            const interventi = dati.map(item => parseInt(item.value));
            
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
