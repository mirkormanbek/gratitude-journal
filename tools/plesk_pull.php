<?php
/**
 * Plesk scheduled task (every 2 min): asks Plesk Git to pull the `production` branch.
 * Usage (Plesk "Run PHP script", PHP 7.4):
 *   journal.luckysport.kz/tools/plesk_pull.php  <Plesk Git webhook URL as argument>
 * The webhook URL lives only in the Plesk task settings, never in git.
 * Plesk deploys only when the branch has new commits.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found');
}
$url = isset($argv[1]) ? (string)$argv[1] : '';
if (strpos($url, 'https://') !== 0) {
    fwrite(STDERR, "usage: php plesk_pull.php <webhook-url>\n");
    exit(2);
}

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => '',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
]);
curl_exec($ch);
$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($code < 200 || $code >= 300) {
    fwrite(STDERR, "[growth-os pull] webhook returned HTTP $code $err\n");
    exit(1);
}
echo "pull requested (HTTP $code)\n";
