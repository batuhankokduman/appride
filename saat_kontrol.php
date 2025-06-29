<?php
date_default_timezone_set('Europe/Istanbul');

echo "PHP Default Timezone: " . date_default_timezone_get() . "<br>";
echo "PHP Şu anki zaman: " . date('Y-m-d H:i:s') . "<br>";
echo "30 dakika önce: " . date('Y-m-d H:i:s', strtotime('-30 minutes')) . "<br>";
?>
