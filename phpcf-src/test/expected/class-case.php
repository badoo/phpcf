<?php
/**
 * Test for not appending lines, when class reference is in switch
 */
class One
{
    public function create()
    {
        switch (get_class($this)) {
            case \DateTime::class:
            case \DateInterval::class:
                return $this;
                break;

            case \Iterator::class:
                return $this;
                break;

            default:
                return \Iterator::class;
        }
    }
}
