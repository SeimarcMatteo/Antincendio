<div class="bg-white shadow rounded-xl p-4">
    <h2 class="text-lg font-semibold mb-2">Grafico Presidi per Categoria</h2>

    <script id="json_presidi_categoria" type="application/json">
        {-- Inserire qui JSON dei dati --}
    </script>

    <div wire:ignore style="position: relative; height: 400px;">
        <canvas id="canvas_presidi_categoria"></canvas>
    </div>

    <script>
        document.addEventListener('livewire:load', function () {
            renderChart_canvas_presidi_categoria();

            Livewire.on('refreshChart', () => {
                setTimeout(renderChart_canvas_presidi_categoria, 100);
            });
        });

        function renderChart_canvas_presidi_categoria() {
            const canvas = document.getElementById('canvas_presidi_categoria');
            const jsonTag = document.getElementById('json_presidi_categoria');
            if (!canvas || !jsonTag) return;

            const ctx = canvas.getContext('2d');
            const dati = JSON.parse(jsonTag.textContent);

            const labels = dati.map(item => item.label);
            const valori = dati.map(item => parseInt(item.value));

            if (window.myChart_canvas_presidi_categoria) window.myChart_canvas_presidi_categoria.destroy();

            window.myChart_canvas_presidi_categoria = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Grafico Presidi per Categoria',
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
