<?php
$import_part = Vars::get('form', '');
            switch ($import_part) {
                case 'social':
                    includeOnce(PHPWEB_PATH_PACKAGES.'Providers/ProviderFilter.php');
                    if (!ProviderFilter::isSocialProvider($this->provider_id)) $this->error = SHARE_ERROR_PROVIDER_INVALID;
                break;

                default:
            }
echo 'Hello world!';
