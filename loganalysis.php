<?php
/*
用于分析linux日志文件的异常IP，比如高频访问蜘蛛，高频探漏洞URL的IP
系统会按IP访问次数排序，几百次以上的都可以分析下他们行为。
比如IP@404 次数多了，URL多为非你常见路径，该IP即为扫描网站漏洞的。
比如IP次数很多，UA有网址或包含spider/bot/crawler等即为蜘蛛，python-requests人工野蜘蛛
性能参考：1核1G服务器 分析76.59MB/404101条日志@执行耗时7.35秒耗内存12,945.10KB
*/
header("Content-type:text/html;charset=utf-8");
ob_implicit_flush();
set_time_limit(8000); //建议修改PHP对应版本的超时时间100为更大值比如8000
$filex= "./logs/yewu.******.com.log"; //修改linux日志文件路径
$filey= "./".basename($filex).".".date("Ymd-His").".txt";
function readcsv($filex){
    $handle = fopen($filex, "r");
    while ($data = fgetcsv($handle,0," ")) { yield $data; }
    fclose($handle);
}
function recips($filey,$txts){
file_put_contents($filey, "\r\n$txts", FILE_APPEND);
}
function isgong($txts){
$txtb = explode("\t",$txts); //次数 状态 大小 UA
$cisu = $txtb[0]; $ztai = $txtb[1]; $newu = $txtb[3];
if($newu==""){
 $zt = "空UA[非常规浏览器]";
}elseif(preg_match_all('/(\\\x[a-zA-Z0-9_]{1,4}){2,4}/', $txts)){
 $zt = "疑似UA攻击[]";
}elseif(preg_match_all('/(spider|bot|crawler|robot)/i', $txts)){
 $zt = "蜘蛛爬虫";
}elseif(preg_match_all('/(curl|requests|robot|python|urllib3|pantest)/i', $txts)){
//ALittle Dalvik wp_is_mobile Go-http-client等疑
 $zt = "蜘蛛爬虫";
}elseif(preg_match("/\@([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61})?\.)+[a-zA-Z]{2,8}/i", $txts)){
 $zt = "邮件UA爬虫";
}elseif(preg_match_all('/(http|https|ftp)/i', $txts) && preg_match("/([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61})?\.)+[a-zA-Z]{2,8}/i", $txts)){
 $zt = "疑似爬虫(UA带网址)";
}elseif($ztai=="404" && $cisu>100){
 $zt = "网站扫洞(100+次404状态)";
}elseif($ztai!="200" && $cisu>100){
 $zt = "高频{$ztai}";
}elseif($cisu>2000){
 $zt = "异常客(2000+次访问)";
}elseif($cisu>300){
 $zt = "常客(300+次访问)";
}else{
 $zt = "";
}
 return "$zt\t$txts";
}
$start = microtime(true); $mstar = memory_get_usage();
$ii=0; $iz=0; $sqla = array(); $sqlb = array();
foreach (readcsv($filex) as $key => $kar) {
$ii++; $ipx = $kar[0]."\t".$kar[6]; //IP 状态 
$kall =$kar[6]."\t".$kar[7]."\t\"".$kar[9]."\"";//状态 大小 UA
if(!$sqla[$ipx]){ $sqla[$ipx] = 1; $sqlb[$ipx] = $kall; }else{ $sqla[$ipx] += 1;}  
}
arsort($sqla); 
recips($filey,"IP\t状态\t异常\t次数\t状态\t大小\tUA");
foreach ($sqla as $tip => $tcs){
 $isgo = isgong($tcs."\t".$sqlb[$tip]); //次数 状态 大小 UA
 recips($filey,"$tip\t$isgo");
}
$sstop = microtime(true); $mstop = memory_get_usage();
$fsize = number_format(filesize($filex)/1024/1024,2);
echo "<p><h3>结果见<a href='$filey'>$filey</a></h3>分析{$fsize}MB/{$ii}条日志@耗时".number_format($sstop-$start, 2)."</p>";
echo "<p>耗内存".number_format(($mstop-$mstar)/1024, 2)."KB</p>';
