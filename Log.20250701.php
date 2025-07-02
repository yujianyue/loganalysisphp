<?php
if (isset($_GET['action']) && $_GET['action'] === 'analyze') {
    header('Content-Type: application/json');
    
    try {
        $logFile = $_POST['logFile'] ?? '';
        $dimension = $_POST['dimension'] ?? 'time';
        
        if (!file_exists($logFile)) {
            throw new Exception('日志文件不存在');
        }
        
        $stats = analyzeLog($logFile, $dimension);
        
        echo json_encode([
            'success' => true,
            'dimension' => $dimension,
            'stats' => $stats
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

function analyzeLog($logFile, $dimension) {
    $stats = [];
    $handle = fopen($logFile, 'r');
    
    if (!$handle) {
        throw new Exception('无法打开日志文件');
    }
    
    $lineCount = 0;
    $batchSize = 1000; // 每次处理1000行，避免内存不足
    $memoryLimit = ini_get('memory_limit');
    
    // 记录开始时间，用于性能监控
    $startTime = microtime(true);
    
    while (!feof($handle)) {
        $lines = [];
        
        // 批量读取1000行
        for ($i = 0; $i < $batchSize && !feof($handle); $i++) {
            $line = fgets($handle);
            if ($line !== false) {
                $lines[] = trim($line);
            }
        }
        
        // 处理这批数据
        foreach ($lines as $line) {
            if (empty($line)) continue;
            
            $lineCount++;
            
            // 解析Nginx日志格式
            if (preg_match('/^(\S+) \S+ \S+ \[(.*?)\] "\S+ \S+ \S+" (\d+) \d+/', $line, $matches)) {
                $ip = $matches[1];
                $timestamp = $matches[2];
                $statusCode = $matches[3];
                
                switch ($dimension) {
                    case 'time':
                        // 转换时间格式为YmdHi
                        if (preg_match('/(\d{2})\/(\w{3})\/(\d{4}):(\d{2}):(\d{2})/', $timestamp, $timeMatches)) {
                            $day = $timeMatches[1];
                            $month = getMonthNumber($timeMatches[2]);
                            $year = $timeMatches[3];
                            $hour = $timeMatches[4];
                            $minute = $timeMatches[5];
                            
                            $timeKey = $year . sprintf('%02d', $month) . $day . $hour . $minute;
                            $stats[$timeKey] = ($stats[$timeKey] ?? 0) + 1;
                        }
                        break;
                        
                    case 'ip':
                        $stats[$ip] = ($stats[$ip] ?? 0) + 1;
                        break;
                        
                    case 'status':
                        $stats[$statusCode] = ($stats[$statusCode] ?? 0) + 1;
                        break;
                }
            }
            
            // 每处理10000行检查一次内存使用情况
            if ($lineCount % 10000 === 0) {
                $memoryUsed = memory_get_usage(true);
                // 如果内存使用超过限制的80%，提前结束处理
                if ($memoryUsed > getMemoryLimitInBytes($memoryLimit) * 0.8) {
                    error_log("内存使用接近限制，提前结束处理。已处理 {$lineCount} 行");
                    break 2; // 跳出两层循环
                }
            }
        }
        
        // 释放内存
        unset($lines);
    }
    
    fclose($handle);
    
    // 记录处理时间
    $processingTime = microtime(true) - $startTime;
    error_log("日志处理完成，共处理 {$lineCount} 行，耗时 {$processingTime} 秒");
    
    // 根据维度排序
    switch ($dimension) {
        case 'time':
            ksort($stats); // 时间升序
            break;
        case 'ip':
            arsort($stats); // 数量降序
            break;
        case 'status':
            // 保持原序，不排序
            break;
    }
    
    return $stats;
}

// 将内存限制字符串转换为字节数
function getMemoryLimitInBytes($memoryLimit) {
    $unit = strtolower(substr($memoryLimit, -1));
    $value = (int)$memoryLimit;
    
    switch ($unit) {
        case 'g':
            $value *= 1024;
        case 'm':
            $value *= 1024;
        case 'k':
            $value *= 1024;
    }
    
    return $value;
}

function getMonthNumber($monthName) {
    $months = [
        'Jan' => 1, 'Feb' => 2, 'Mar' => 3, 'Apr' => 4,
        'May' => 5, 'Jun' => 6, 'Jul' => 7, 'Aug' => 8,
        'Sep' => 9, 'Oct' => 10, 'Nov' => 11, 'Dec' => 12
    ];
    
    return $months[$monthName] ?? 1;
}
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nginx日志分析工具</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .form-row {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: nowrap;
        }
        .form-group {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        select, button {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        button {
            background-color: #007bff;
            color: white;
            cursor: pointer;
            border: none;
        }
        button:hover {
            background-color: #0056b3;
        }
        .chart-container {
            margin-top: 30px;
            position: relative;
            overflow-x: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
            -webkit-overflow-scrolling: touch; /* 提升移动设备上的滑动体验 */
        }
        .chart {
            padding: 20px;
            min-width: 6000px;
            height: 300px;
            position: relative;
            touch-action: pan-x; /* 允许水平平移手势 */
        }
        svg {
            width: 100%;
            height: 100%;
        }
        .bar {
            fill: #007bff;
            padding: 1px;
            cursor: pointer;
            min-width: 2px;
            transition: fill 0.2s;
        }
        .bar:hover {
            fill: #0056b3;
        }
        .tooltip {
            position: absolute;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 12px;
            pointer-events: none;
            z-index: 1000;
            display: none;
            white-space: nowrap;
        }
        .loading {
            text-align: center;
            padding: 20px;
            color: #666;
        }
        .error {
            color: #dc3545;
            background: #f8d7da;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
        }
        .stats {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Nginx日志分析工具</h1>
        
        <form id="logForm" class="form-row">
            <div class="form-group">
                <label for="logFile">日志文件:</label>
                <select id="logFile" name="logFile">
                    <?php
                    $logsDir = 'logs';
                    if (is_dir($logsDir)) {
                        $files = glob($logsDir . '/*.log');
                        foreach ($files as $file) {
                            $filename = basename($file);
                            echo "<option value='$file'>$filename</option>";
                        }
                    }
                    ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="dimension">维度:</label>
                <select id="dimension" name="dimension">
                    <option value="time">按时间(时间升序)</option>
                    <option value="ip">按IP(数量降序)</option>
                    <option value="status">状态码(原序)</option>
                </select>
            </div>
            
            <button type="button" id="analyzeBtn">分析</button>
        </form>
        
        <div id="result"></div>
        <div class="tooltip" id="tooltip"></div>
    </div>

    <script>
        document.getElementById('analyzeBtn').addEventListener('click', function() {
            const form = document.getElementById('logForm');
            const formData = new FormData(form);
            const resultDiv = document.getElementById('result');
            
            resultDiv.innerHTML = '<div class="loading">正在分析日志...</div>';
            
            fetch('?action=analyze', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    resultDiv.innerHTML = `<div class="error">${data.error}</div>`;
                } else {
                    displayChart(data);
                }
            })
            .catch(error => {
                resultDiv.innerHTML = `<div class="error">请求失败: ${error.message}</div>`;
            });
        });
        
        function displayChart(data) {
            const resultDiv = document.getElementById('result');
            
            if (data.dimension === 'time') {
                // 时间维度显示图表
                const maxCount = Math.max(...Object.values(data.stats));
                const totalCount = Object.values(data.stats).reduce((a, b) => a + b, 0);
                const entries = Object.entries(data.stats);
                
                // 计算SVG的宽度，每个柱子2px宽度，间隔2px
                const svgWidth = entries.length * 4; // (2px宽度 + 2px间隔) * 数量
                
                let chartHtml = `
                    <div class="stats">
                        <strong>统计结果:</strong> 共 ${entries.length} 个时间点，总访问量 ${totalCount} 次
                    </div>
                    <div class="chart-container">
                        <div class="chart" id="chart">
                            <svg width="${svgWidth}" height="260" viewBox="0 0 ${svgWidth} 260" preserveAspectRatio="none">
                `;
                
                // 生成SVG柱状图
                entries.forEach(([time, count], index) => {
                    const height = maxCount > 0 ? (count / maxCount) * 260 : 0; // 最大高度260px
                    const x = index * 4; // 每个柱子占4个单位（2px宽度+2px间隔）
                    const y = 260 - height; // SVG坐标系从上到下
                    
                    chartHtml += `<rect class="bar" x="${x}" y="${y}" width="2" height="${height}" data-time="${time}" data-count="${count}"/>`;
                });
                
                chartHtml += `
                            </svg>
                        </div>
                    </div>
                `;
                
                resultDiv.innerHTML = chartHtml;
                
                // 添加鼠标悬停事件
                addTooltipEvents();
            } else {
                // IP和状态码维度显示表格
                let tableHtml = `
                    <div class="stats">
                        <strong>统计结果:</strong> 共 ${Object.keys(data.stats).length} 个${data.dimension === 'ip' ? 'IP地址' : '状态码'}
                    </div>
                    <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
                        <thead>
                            <tr style="background: #f8f9fa;">
                                <th style="padding: 10px; border: 1px solid #ddd;">${data.dimension === 'ip' ? 'IP地址' : '状态码'}</th>
                                <th style="padding: 10px; border: 1px solid #ddd;">访问次数</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
                
                Object.entries(data.stats).forEach(([key, count]) => {
                    tableHtml += `
                        <tr>
                            <td style="padding: 10px; border: 1px solid #ddd;">${key}</td>
                            <td style="padding: 10px; border: 1px solid #ddd; text-align: right;">${count}</td>
                        </tr>
                    `;
                });
                
                tableHtml += '</tbody></table>';
                resultDiv.innerHTML = tableHtml;
            }
        }
        
        function addTooltipEvents() {
            const bars = document.querySelectorAll('.bar');
            const tooltip = document.getElementById('tooltip');
            
            bars.forEach(bar => {
                bar.addEventListener('mouseenter', function(e) {
                    const time = this.getAttribute('data-time');
                    const count = this.getAttribute('data-count');
                    
                    if (time && count) {
                        const formattedTime = time.substring(0, 4) + '-' + time.substring(4, 6) + '-' + time.substring(6, 8) + ' ' + time.substring(8, 10) + ':' + time.substring(10, 12);
                        
                        tooltip.innerHTML = `时间: ${formattedTime}<br>访问数: ${count}`;
                        tooltip.style.display = 'block';
                    }
                });
                
                bar.addEventListener('mousemove', function(e) {
                    tooltip.style.left = (e.pageX + 10) + 'px';
                    tooltip.style.top = (e.pageY - 10) + 'px';
                });
                
                bar.addEventListener('mouseleave', function() {
                    tooltip.style.display = 'none';
                });
            });
        }
    </script>
</body>
</html>
