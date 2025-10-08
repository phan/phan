<?php

/**
 * @phan-file-suppress PhanPluginNoCommentOnFunction
 * @phan-file-suppress PhanPluginNoCommentOnFile
 */
function assignInt(): void
{
    $data = (object) [];
    $data->status = 3;
}

function assignString(): void
{
    $data = (object) ['status' => 'string'];
    $var = $data->status;
    '@phan-debug-var $var';
}
