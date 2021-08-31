<?php
function dieWithFallback(array $urls)
{
    $url = $urls['url'];


    $fallbackURL = $urls['fallback_url'];


    print <<<EOF
<!doctype html>
<html>
<head>
<title></title>
<script>
    var fallbackUrl = '$fallbackURL';
    if (navigator.platform && navigator.platform.match(/(iPad|iPhone|iPod)/g)) {
        setTimeout(function() {
            var onPageHide = function() {
                clearTimeout(id);
                window.removeEventListener('pagehide', onPageHide);
            };
            window.addEventListener('pagehide', onPageHide);
            window.location.href = '$url';
            var id = window.setTimeout(function() {
                window.location.href = fallbackUrl;
            }, 3500);
        }, 0);
    } else {
        window.location.href = fallbackUrl;
    }
</script>
<noscript><META http-equiv="refresh" content="0;URL=$fallbackURL"></noscript>
</head>
<body></body>
</html>
EOF;


    exit(0);
}

var_dump(
/** @lang JavaScript */
    <<<JS
setTimeout(function(){el.setAttribute("style",os);},300);
JS
    , true
);
