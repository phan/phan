<?php
class Sample
{
    const REF = '';

    public function action($data)
    {
        return call_user_func(self::REF.'::make', $data);
    }
}
