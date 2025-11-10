<?php

function render_dev_css(bool $flag, string $asset, int $port): string
{
    if ($flag) {
        $css = maybe_get_css($asset);
        if ($css === '') {
            $uri = "http://localhost:$port/?type=css&asset=$asset";
            $context = stream_context_create(['http' => ['ignore_errors' => true]]);
            $css = file_get_contents($uri, false, $context);
        }
    } else {
        $context = stream_context_create(['http' => ['ignore_errors' => true]]);
        $css = file_get_contents($asset, false, $context);
    }

    return $css;
}

function maybe_get_css(string $asset): string
{
    return '';
}
