<?php

$host = $_SERVER['HTTP_HOST'];
$path = $_SERVER['REQUEST_URI'];
$https = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on") || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == "https")) ? "https://" : "http://";
//$anyip值为1发送服务器IP头，值为2则发送随机IP，值为3发送客户端IP，仅在部分网站中有效
$anyip = 1;

//代理的域名及使用的协议最后不用加/
$target_host = "https://www.baidu.com";
if (substr($target_host, 0, 4) != "http") {
    $target_host = "http://" . $target_host;
}
//处理代理的主机得到协议和主机名称
$protocal_host = parse_url($target_host);
//以.分割域名字符串
$rootdomain = explode(".",$host);
//获取数组的长度
$lenth = count($rootdomain);
//获取顶级域名
$top = ".".$rootdomain[$lenth-1];
//获取主域名
$root = ".".$rootdomain[$lenth-2];
//判断请求的域名或ip是否合法
if (strstr($target_host, ".") === false || $protocal_host['host'] == $host) {
    del_cookie();
    echo "<script>alert('请求的域名有误！');window.location.href='" . $https . $host . "';</script>";
    exit;
}
$PageIP = gethostbyname($protocal_host['host']);
if (filter_var($PageIP, FILTER_VALIDATE_IP)) {
    if (filter_var($PageIP, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        del_cookie();
        echo "<script>alert('请求的ip被禁止！');window.location.href='" . $https . $host . "';</script>";
        exit;
    }
} else {
    del_cookie();
    echo "<script>alert('请求的域名有误！');window.location.href='" . $https . $host . "';</script>";
    exit;
}
// set URL and other appropriate options
$aAccess = curl_init();
curl_setopt($aAccess, CURLOPT_URL, $protocal_host['scheme'] . "://" . $protocal_host['host'] . $path);
curl_setopt($aAccess, CURLOPT_HEADER, true);
curl_setopt($aAccess, CURLOPT_RETURNTRANSFER, true);
curl_setopt($aAccess, CURLOPT_FOLLOWLOCATION, 1);
curl_setopt($aAccess, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($aAccess, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($aAccess, CURLOPT_TIMEOUT, 10);
curl_setopt($aAccess, CURLOPT_BINARYTRANSFER, true);
//关系数组转换成字符串，每个键值对中间用=连接，以; 分割
function array_to_str($array) {
    $string = "";
    if (is_array($array)) {
        foreach ($array as $key => $value) {
            if (!empty($string)) $string.= "; " . $key . "=" . $value;
            else $string.= $key . "=" . $value;
        }
    } else {
        $string = $array;
    }
    return urldecode($string);
}
if ($_SERVER['HTTP_REFERER']) {
    $referer = str_replace($host, $protocal_host['host'], $_SERVER['HTTP_REFERER']);
}
if ($anyip == "1") {
    $remoteip = $_SERVER['HTTP_CLIENT_IP'];
} elseif ($anyip == "2") {
    $remoteip = mt_rand(1,255).".".mt_rand(1,255).".".mt_rand(1,255).".".mt_rand(1,255);
} elseif (empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $remoteip = $_SERVER['REMOTE_ADDR'];
} else {
    $remoteip = $_SERVER['HTTP_X_FORWARDED_FOR'];
}
//$headers = get_client_header();
$headers = array();
$headers[] = "Accept-language: " . $_SERVER['HTTP_ACCEPT_LANGUAGE'];
$headers[] = "Referer: " . $referer;
$headers[] = "CLIENT-IP: " . $remoteip;
$headers[] = "X-FORWARDED-FOR: " . $remoteip;
$headers[] = "Cookie: " . array_to_str($_COOKIE);
$headers[] = "user-agent: " . $_SERVER['HTTP_USER_AGENT'];
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $headers[] = "Content-Type: " . $_SERVER['CONTENT_TYPE'];
    curl_setopt($aAccess, CURLOPT_POST, 1);
    curl_setopt($aAccess, CURLOPT_POSTFIELDS, http_build_query($_POST));
}
curl_setopt($aAccess, CURLOPT_HTTPHEADER, $headers);
// grab URL and pass it to the browser
$sResponse = curl_exec($aAccess);
//判断请求url是否被重定向
$locurl = parse_url(curl_getinfo($aAccess, CURLINFO_EFFECTIVE_URL));
if ($locurl['scheme'] . "://" . $locurl['host'] != $protocal_host['scheme'] . "://" . $protocal_host['host']) {
    setcookie("urlss", $locurl['scheme'] . "://" . $locurl['host'], 0, "/");
}
list($headerstr, $sResponse) = parse_header($sResponse);
$headarr = explode("\r\n", $headerstr);
foreach ($headarr as $h) {
    if (strlen($h) > 0) {
        if (strpos($h, 'ETag') !== false) continue;
        if (strpos($h, 'Connection') !== false) continue;
        if (strpos($h, 'Cache-Control') !== false) continue;
        if (strpos($h, 'Content-Length') !== false) continue;
        if (strpos($h, 'Transfer-Encoding') !== false) continue;
        if (strpos($h, 'HTTP/1.1 100 Continue') !== false) continue;
        if (strpos($h, 'Strict-Transport-Security') !== false) continue;
        if (strpos($h, 'Set-Cookie') !== false) {
            $targetcookie = $h . ";";
            //如果返回到客户端cookie不正常可把下行中的$root . $top换成$host
            $res_cookie = preg_replace("/domain=.*?;/", "domain=" . $root . $top .";", $targetcookie);
            $h = substr($res_cookie, 0, strlen($res_cookie) - 1);
            header($h, false);
        } else {
            header($h);
        }
    }
}
function del_cookie() {
    foreach ($_COOKIE as $key => $value) {
        if ($key == "Any-Proxy") continue;
        setcookie($key, null, time() - 3600, "/");
    }
}
function get_client_header() {
    $headers = array();
    foreach ($_SERVER as $k => $v) {
        if (strpos($k, 'HTTP_') === 0) {
            $k = strtolower(preg_replace('/^HTTP/', '', $k));
            $k = preg_replace_callback('/_\w/', 'header_callback', $k);
            $k = preg_replace('/^_/', '', $k);
            $k = str_replace('_', '-', $k);
            if ($k == 'Host') continue;
            $headers[] = "$k: $v";
        }
    }
    return $headers;
}
function header_callback($str) {
    return strtoupper($str[0]);
}
function parse_header($sResponse) {
    list($headerstr, $sResponse) = explode("\r\n\r\n", $sResponse, 2);
    $ret = array($headerstr, $sResponse);
        if (preg_match('/^HTTP\/1\.1 \d{3}/', $sResponse)) {
            $ret = parse_header($sResponse);
        }
    return $ret;
}
//解决中文乱码
$charlen = stripos($sResponse, "charset");
if (stristr(substr($sResponse, $charlen, 18) , "GBK") || stristr(substr($sResponse, $charlen, 18) , "GB2312")) {
    $sResponse = mb_convert_encoding($sResponse, "UTF-8", "GBK,GB2312,BIG5");
}
header("Pragma: no-cache");
// close cURL resource, and free up system resources
$sResponse = str_replace("http://" . $protocal_host['host'], $https . $host, $sResponse);
$sResponse = str_replace("https://" . $protocal_host['host'], $https . $host, $sResponse);
$sResponse = str_replace("https://www." . $protocal_host['host'], $https . $host, $sResponse);
curl_close($aAccess);
echo $sResponse;
