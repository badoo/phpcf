<?php

class One
{
    public function Test() {
        if(true){
            fwrite(STDOUT,"UNFORMATTED");
        }
    }

    public function TestTwo()
    {
        if (true) {
            fwrite(STDOUT, "UNFORMATTED");
        }
    }
}