<?php
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo 'OPcache geleert.';
} else {
    echo 'OPcache nicht aktiv.';
}
