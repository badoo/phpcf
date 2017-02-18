<?php

$var = new ClassName(
    "Do something for a long time",
    $with_one,
    $with_two
);

throw new Exception(
    'Wrong action type, you should use UPLOADED_PRIVATE_PHOTOS for private photos',
    Exception::WRONG_PARAMS
);
