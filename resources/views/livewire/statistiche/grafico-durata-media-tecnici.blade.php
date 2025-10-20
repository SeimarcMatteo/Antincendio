<div class="bg-white shadow rounded-xl p-4">
    <h2 class="text-lg font-semibold mb-2">Grafico Durata Media per Tecnico</h2>

    <script id="json_durata_media_tecnici" type="application/json">
        {!! json_encode($dati->map(fn($r) => ['label' => $r->name, 'value' => $r->media])->values()) !!}
    </script>

    <div wire:ignore style="position: relative; height: 400px;">
        <canvas id="canvas_durata_media_tecnici"></canvas>
    </div>

    <script>
        let myBarChart;

        function renderChart() {
            console.log("ESEGUO renderChart()");
            const canvas = document.getElementById('canvas_durata_media_tecnici');
            const jsonTag = document.getElementById('json_durata_media_tecnici');
            console.log("Dati JSON ricevuti:", dati);
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
            const media = dati.map(item => parseInt(item.value));
            
            if (myBarChart) myBarChart.destroy();

            myBarChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Durata Media',
                            data: media,
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
