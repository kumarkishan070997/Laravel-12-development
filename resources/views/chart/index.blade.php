<!DOCTYPE html>
<html>
<head>
    <title>Google Charts in Laravel - TheDevNerd</title>
    <!-- Bootstrap for Styling -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Google Charts Loader -->
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>

    <script type="text/javascript">
        google.charts.load('current', { packages: ['corechart'] });
        google.charts.setOnLoadCallback(drawCharts);

        function drawCharts() {
            var data = google.visualization.arrayToDataTable(@json($data));

            // Chart options
            var pieOptions = {
                title: '🍕 Pie Chart (3D)',
                is3D: true
            };

            var columnOptions = {
                title: '📊 Column Chart'
            };

            var barOptions = {
                title: '📋 Bar Chart',
                bars: 'horizontal'
            };

            var donutOptions = {
                title: '🍩 Donut Chart',
                pieHole: 0.4
            };

            // Draw Charts
            new google.visualization.PieChart(document.getElementById('pie_chart')).draw(data, pieOptions);
            new google.visualization.ColumnChart(document.getElementById('column_chart')).draw(data, columnOptions);
            new google.visualization.BarChart(document.getElementById('bar_chart')).draw(data, barOptions);
            new google.visualization.PieChart(document.getElementById('donut_chart')).draw(data, donutOptions);
        }
    </script>
</head>
<body>
    <div class="container my-5">
        <h2 class="text-center mb-4">📊 Google Charts with Laravel - TheDevNerd</h2>

        <div class="row mb-4">
            <div class="col-md-6">
                <div id="pie_chart" style="width: 100%; height: 400px;"></div>
            </div>
            <div class="col-md-6">
                <div id="column_chart" style="width: 100%; height: 400px;"></div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div id="bar_chart" style="width: 100%; height: 400px;"></div>
            </div>
            <div class="col-md-6">
                <div id="donut_chart" style="width: 100%; height: 400px;"></div>
            </div>
        </div>
    </div>
</body>
</html>
