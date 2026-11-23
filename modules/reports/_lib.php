<?php
/** Extract a YouTube video id from common URL formats, or ''. */
function youtube_id(string $url): string
{
    if (preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/))([A-Za-z0-9_-]{11})~', $url, $m)) return $m[1];
    return '';
}
