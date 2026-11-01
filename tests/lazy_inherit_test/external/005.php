<?php
class V005Base
{
    public function magic(): int { return 1; }
    public function plain(): int { return 2; }
}
/**
 * @method string parentMagic()
 */
class V005Mid extends V005Base {}
