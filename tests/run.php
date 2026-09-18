<?php
$failed = 0;
foreach (glob(__DIR__ . '/unit-*.php') ?: [] as $file) {
    echo '== ' . basename($file) . " ==\n";
    passthru('php ' . escapeshellarg($file), $code);
    if ($code !== 0) {
        $failed++;
    }
}
if ($failed > 0) {
    fwrite(STDERR, $failed . " suite(s) failed\n");
    exit(1);
}
echo "all suites passed\n";
