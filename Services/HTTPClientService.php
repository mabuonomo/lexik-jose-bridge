<?php

namespace SpomkyLabs\LexikJoseBundle\Services;

class HTTPClientService
{
    /**
     * @param $file
     * @param $url
     * @param int    $hours
     * @param string $fn
     * @param string $fn_args
     *
     * @return bool|false|string
     */
    public function getContent($file, $url, $hours = 24, $fn = '', $fn_args = '')
    {
        $current_time = \time();
        $expire_time = $hours * 60 * 60;
        $file_time = \filemtime($file);
        if (($current_time - $expire_time < $file_time) && \file_exists($file)) {
            return \file_get_contents($file);
        }

        $content = \file_get_contents($url);
        \file_put_contents($file, $content);

        return $content;
    }
}
