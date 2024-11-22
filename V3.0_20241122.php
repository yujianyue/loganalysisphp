<?php
//日志分析工具(二维表通用)，最好首行是字段
$action = isset($_GET['action']) ? $_GET['action'] : '';
$logDir = './logs';
$fagefu = " "; //数据分隔符
if ($action === 'get_files') {
// 获取 .log 文件列表
$files = array_filter(scandir($logDir), function ($file) {
return pathinfo($file, PATHINFO_EXTENSION) === 'log';
});
echo json_encode(array_values($files));
exit;
}
if ($action === 'get_fields') {
// 获取日志文件的首行字段
$file = isset($_GET['file']) ? $_GET['file'] : '';
$filePath = $logDir . '/' . $file;
if (!file_exists($filePath)) {
echo json_encode([]);
exit;
}
$handle = fopen($filePath, 'r');
$line = fgetcsv($handle, 0, $fagefu); // 使用空格作为分隔符
fclose($handle);
echo json_encode($line ?: []);
exit;
}
if ($action === 'analyze') {
// 聚合分析
$data = json_decode(file_get_contents('php://input'), true);
$file = isset($data['file']) ? $data['file'] : '';
$fields = isset($data['fields']) ? $data['fields'] : [];
if (count($fields)<1) {
echo json_encode(['error' => '请选择字段']);
exit;
}
$filePath = $logDir . '/' . $file;
if (!file_exists($filePath)) {
echo json_encode(['error' => '文件不存在']);
exit;
}
$handle = fopen($filePath, 'r');
$results = [];
while (($line = fgetcsv($handle, 0, $fagefu)) !== false) {
$keyParts = [];
foreach ($fields as $index) {
$keyParts[] = isset($line[$index]) ? $line[$index] : '';
}
$key = implode("\t", $keyParts);
if (isset($results[$key])) {
$results[$key]++;
} else {
$results[$key] = 1;
}
}
fclose($handle);
// 按出现次数排序
arsort($results);
// 格式化返回数据
$output = [
'columns' => array_merge(array_map(function ($i) {
return "字段{$i}";
}, $fields), ['次数']),
'rows' => [],
];
foreach ($results as $key => $count) {
$output['rows'][] = array_merge(explode("\t", $key), [$count]);
}
echo json_encode($output);
exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Nginx 日志分析工具</title>
<style>
body {
font-family: Arial, sans-serif;
margin: 20px;
}
select, button {
margin: 0;
padding: 5px;
font-size: 16px;
}
table {
border-collapse: collapse;
width: 100%;
margin-top: 20px;
}
th, td {
border: 1px solid #ddd;
padding: 5px 10px;
text-align: left;
}
th {
background-color: #f4f4f4;
}
td {
max-width: 300px;
overflow: hidden;
text-overflow: ellipsis;
white-space: nowrap;
}
</style>
</head>
<body>
<h1>Nginx 日志分析工具</h1>
<label for="fileSelect">选择日志文件：</label>
<select id="fileSelect"><option value="">请先选择日志文件</option></select>
<br>
<div id="fieldSelection" style="display: none;">
<p>选择要统计的字段（按住 Ctrl/Command 可多选）：</p>
<select id="fields" multiple style="width: 300px; height: 150px;"></select>
<br>
<button id="analyzeBtn">开始分析</button>
</div>
<div id="result"></div>
<script>
document.addEventListener("DOMContentLoaded", () => {
// 加载日志文件列表
fetch('?action=get_files')
.then(response => response.json())
.then(files => {
const fileSelect = document.getElementById('fileSelect');
files.forEach(file => {
const option = document.createElement('option');
option.value = file;
option.textContent = file;
fileSelect.appendChild(option);
});
});
// 文件选择事件
document.getElementById('fileSelect').addEventListener('change', (e) => {
const selectedFile = e.target.value;
if (selectedFile) {
fetch(`?action=get_fields&file=${encodeURIComponent(selectedFile)}`)
.then(response => response.json())
.then(fields => {
const fieldsSelect = document.getElementById('fields');
fieldsSelect.innerHTML = ''; // 清空之前的字段
fields.forEach((field, index) => {
const option = document.createElement('option');
option.value = index;
option.textContent = field;
fieldsSelect.appendChild(option);
});
document.getElementById('fieldSelection').style.display = 'block';
});
}
});
// 分析按钮事件
document.getElementById('analyzeBtn').addEventListener('click', () => {
const selectedFile = document.getElementById('fileSelect').value;
const selectedFields = Array.from(document.getElementById('fields').selectedOptions)
.map(option => option.value);
fetch('?action=analyze', {
method: 'POST',
headers: {
'Content-Type': 'application/json',
},
body: JSON.stringify({
file: selectedFile,
fields: selectedFields,
}),
})
.then(response => response.json())
.then(data => {
const resultDiv = document.getElementById('result');
resultDiv.innerHTML = `<h2>分析结果</h2>`;
const table = document.createElement('table');
const headerRow = document.createElement('tr');
data.columns.forEach(col => {
const th = document.createElement('th');
th.textContent = col;
headerRow.appendChild(th);
});
table.appendChild(headerRow);
data.rows.forEach(row => {
const tr = document.createElement('tr');
row.forEach(cell => {
const td = document.createElement('td');
td.textContent = cell;
tr.appendChild(td);
});
table.appendChild(tr);
});
resultDiv.appendChild(table);
});
});
});
</script>
</body>
</html>
