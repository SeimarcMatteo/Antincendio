<div class="bg-white shadow rounded-xl p-4">
    <h2 class="text-lg font-semibold mb-2">Grafico Trend Interventi nel Tempo</h2>

    <script id="json_trend_interventi" type="application/json">
        {-- Inserire qui JSON dei dati --}
    </script>

    <div wire:ignore style="position: relative; height: 400px;">
        <canvas id="canvas_trend_interventi"></canvas>
    </div>

    <script>
        document.addEventListener('livewire:load', function () {
            renderChart_canvas_trend_interventi();

            Livewire.on('refreshChart', () => {
                setTimeout(renderChart_canvas_trend_interventi, 100);
            });
        });

        function renderChart_canvas_trend_interventi() {
            const canvas = document.getElementById('canvas_trend_interventi');
            const jsonTag = document.getElementById('json_trend_interventi');
            if (!canvas || !jsonTag) return;

            const ctx = canvas.getContext('2d');
            const dati = JSON.parse(jsonTag.textContent);

            const labels = dati.map(item => item.label);
            const valori = dati.map(item => parseInt(item.value));

            if (window.myChart_canvas_trend_interventi) window.myChart_canvas_trend_interventi.destroy();

            window.myChart_canvas_trend_interventi = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Grafico Trend Interventi nel Tempo',
                        data: valori,
                        backgroundColor: 'rgba(75, 192, 192, 0.6)',
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        }
    </script>
</div>
