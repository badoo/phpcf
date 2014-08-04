<?php

/**
 * check for finally keyword correct format
 */
try {
    throw new InvalidArgumentException("Catch me, if you can");
} catch (InvalidArgumentException $e) {
    echo "got you!";
} finally {
    echo "OLOLO";
}
