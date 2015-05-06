<?php
for ($i = 0; $i < 100; $i++) {
    echo "$i\n";
}

for ($i = 100; $i >= 0; $i--) {
    echo "$i\n";
}

for ($i = 0; $i < 100; ++$i) {
    echo "$i\n";
}

for ($i = 100; $i >= 0; --$i) {
    echo "$i\n";
}

$j -= ++$i;
