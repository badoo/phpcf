<?php
/**
 * Test for class reference inside arrays
 */
class Test
{
    public static function provider_42h()
    {
        $a = 
            MimeMails_TheyCanNotSendYouAMessages::class;
        return array(
            array(Partner::ID_DEFAULT,                MimeMails_TheyCanNotSendYouAMessages::class),
            array(Partner::ID_HOTORNOT_BMA, 
                \Mime\MimeMails\HotornotNoPhotoRemind
                ::class),
        );
    }
}
