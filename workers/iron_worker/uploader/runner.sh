export PHP_INI_SCAN_DIR=""

TERM=dumb php -c `pwd`/php.ini __runner__.php "$@"